<?php
//SVN $Id$

/*
=====================================================
DC FreeForm GeoIP
-----------------------------------------------------
http://www.designchuchi.ch/
-----------------------------------------------------
Copyright (c) 2008 - today Designchuchi
=====================================================
THIS MODULE IS PROVIDED "AS IS" WITHOUT WARRANTY OF
ANY KIND OR NATURE, EITHER EXPRESSED OR IMPLIED,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE,
OR NON-INFRINGEMENT.
=====================================================
File: ext.dc_freeform_geoip.php
-----------------------------------------------------
Purpose: Geocodes IPs to locations in Freeform.
=====================================================
*/

if (!defined('EXT'))
{
	exit('Invalid file request');
}

class DC_FreeForm_GeoIP
{

	var $settings		= array();

	var $name			= 'FreeForm GeoIP Extension';
	var $version		= '1.0.0';
	var $description	= 'Geocodes IPs to locations in Freeform.';
	var $settings_exist	= 'n';
	var $docs_url		= '';

	// -------------------------------
	//  Constructor - Extensions use this for settings
	// -------------------------------
	function DC_FreeForm_GeoIP($settings='')
	{
		$this->settings = $settings;
	}

	// --------------------------------
	//  Activate Extension
	// --------------------------------

	function activate_extension()
	{
		global $DB;

		// hooks array
		$hooks = array(
			'freeform_module_insert_begin'		=> 'freeform_module_insert_begin',
			'show_full_control_panel_end'		=> 'display_form_entries'
		);

		foreach ($hooks as $hook => $method)
		{
			$sql[] = $DB->insert_string( 'exp_extensions',
				array(
					'extension_id'	=> '',
					'class'			=> get_class($this),
					'method'		=> $method,
					'hook'			=> $hook,
					'settings'		=> '',
					'priority'		=> 10,
					'version'		=> $this->version,
					'enabled'		=> 'y'
				)
			);
		}
		
		// alter freeform entries table
		$sql[] = "ALTER TABLE `exp_freeform_entries` ADD `ip_location_data` VARCHAR( 1024 ) NULL AFTER `ip_address`";

		// run all sql queries
		foreach ($sql as $query)
		{
			$DB->query($query);
		}

		return TRUE;
	}

	// --------------------------------
	//  Update Extension
	// --------------------------------
	function update_extension($current = '')
	{
		global $DB;

		//	=============================================
		//	Is Current?
		//	=============================================
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}
	}

	// --------------------------------
	//  Disable Extension
	// --------------------------------
	function disable_extension()
	{
		global $DB;
		
		$sql[] = "DELETE FROM exp_extensions WHERE class = '" . get_class($this) . "'";
				
		// remove custom column in freeform entries
		$sql[] = "ALTER TABLE `exp_freeform_entries` DROP `ip_location_data`";

		// run all sql queries
		foreach ($sql as $query)
		{
			$DB->query($query);
		}
	}

	/**
	 * Retrieves the location data from this provider http://www.hostip.info/use.html 
	 * and saves it into the database upon a form submission.
	 *
	 * @see		freeform_module_insert_begin hook
	 * @since	Version 1.0.0
	 */
	function freeform_module_insert_begin($data) {
		
		// TODO: Replace this with a hook so that other modules can provide their search
		$url = 'http://api.hostip.info/get_html.php?ip='. $data['ip_address'];
		
		// get ip location data contents
		$handle = @fopen($url, 'r');
		$ip_location_data = stream_get_contents($handle);
		@fclose($handle);
		
		// add to the array, this will be added to the database by freeform
		$data['ip_location_data'] = $ip_location_data;
		
		return $data;
	}
	
	/**
	 * Displays the location data for a freeform entry on a single entry page
	 * based on the IP that was saved for that entry.
	 *
	 * @see		show_full_control_panel_end hook
	 * @since	Version 1.0.0
	 */
	function display_form_entries($out) {
		global $DB, $EXT, $IN, $DSP, $LANG;

		//	check if someone else uses this
		if ($EXT->last_call !== FALSE)
		{
			$out = $EXT->last_call;
		}
		
		//	=============================================
		//	Only do this on the freeform entries page
		//	=============================================
		if($IN->GBL('M') != 'freeform' || ($IN->GBL('P') != 'edit_entry_form'))
		{
			return $out;
		}
		
		//	now we can fetch the language file
		$LANG->fetch_language_file('dc_freeform_geoip');
		
		// get the ip location data
		$query = $DB->query("SELECT ip_location_data FROM exp_freeform_entries WHERE entry_id='".$DB->escape_str($IN->GBL('entry_id'))."'");

		$ip_location_data = $query->row['ip_location_data'];
	
		// and show it only if it's set	
		if(!empty($ip_location_data))
		{
			//	=============================================
			//	Find IP Address Row
			//	=============================================
			preg_match('/ip\ address.*?<\/tr>/si', $out, $row);
			
			// replace line breaks with xhtml breaks
			$ip_location_data = str_replace("\n", "<br />", $ip_location_data);
	
			$r = $DSP->td_c().$DSP->tr_c();
			
			$r .= $DSP->tr();
			$r .= $DSP->table_qcell('tableCellOne', $DSP->qdiv('defaultBold', $LANG->line('ip_location_data')), '30%');
			$r .= $DSP->table_qcell('tableCellOne', $DSP->qdiv('', $ip_location_data), '70%');
			$r .= $DSP->tr_c();
			
			//	=============================================
			//	Add Row
			//	=============================================
			$out = @str_replace($row[0], $row[0].$r, $out);
		}
			
		return $out;
	}
}
//END CLASS
?>
