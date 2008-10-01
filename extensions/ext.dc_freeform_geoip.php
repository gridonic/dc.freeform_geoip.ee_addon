<?php
function dprint_r($var, $title = '') {
	echo('<pre>');
	print_r($var);
	echo('</pre>');
}
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
	var $version		= '1.0.1';
	var $description	= 'Geocodes IPs to locations in Freeform.';
	var $settings_exist = 'y';
	var $docs_url		= '';

	// -------------------------------
	//	Constructor - Extensions use this for settings
	// -------------------------------
	function DC_FreeForm_GeoIP($settings='')
	{
		$this->settings = $settings;
	}

	// --------------------------------
	//	Activate Extension
	// --------------------------------

	function activate_extension()
	{
		global $DB;
		
		// default setting values
		$append_data = 'no';

		// hooks array
		$hooks = array(
			'freeform_module_insert_begin'		=> 'freeform_module_insert_begin',
			'freeform_module_insert_end'		=> 'freeform_module_insert_end',
			'show_full_control_panel_end'		=> 'display_form_entries'
		);
		
		// default settings
		$default_settings = serialize(
			array(
				'append_data'	=> $append_data
			)
		);

		foreach ($hooks as $hook => $method)
		{
			$sql[] = $DB->insert_string( 'exp_extensions',
				array(
					'extension_id'	=> '',
					'class'			=> get_class($this),
					'method'		=> $method,
					'hook'			=> $hook,
					'settings'		=> $default_settings,
					'priority'		=> 10,
					'version'		=> $this->version,
					'enabled'		=> 'y'
				)
			);
		}
		
		// add extension table
		$sql[] = "CREATE TABLE `exp_dc_freeform_geoip` (
			`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`entry_date` INT(10) NOT NULL,
			`ip_address` VARCHAR(16) NOT NULL default '0',
			`ip_location_data` TEXT NOT NULL DEFAULT ''
		)";
		$sql[] = 'ALTER TABLE `exp_dc_freeform_geoip` ADD UNIQUE `ENTRY_DATE` ( `entry_date` )';
		
		// run all sql queries
		foreach ($sql as $query)
		{
			$DB->query($query);
		}

		return TRUE;
	}

	// --------------------------------
	//	Update Extension
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
		
		// Add insert_end hook if we have an older version
		if ($current < '1.0.1')
		{
			// hooks array
			$hooks = array(
				'freeform_module_insert_end'		=> 'freeform_module_insert_end',
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
		}
		
		//	=============================================
		//	Update?
		//	=============================================
		$sql[] = "UPDATE exp_extensions SET version = '" . $DB->escape_str($this->version) . "' WHERE class = '" . get_class($this) . "'";
		
		// run all sql queries
		foreach ($sql as $query)
		{
			$DB->query($query);
		}
	}

	// --------------------------------
	//	Disable Extension
	// --------------------------------
	function disable_extension()
	{
		global $DB;
		
		$sql[] = "DELETE FROM exp_extensions WHERE class = '" . get_class($this) . "'";
				
		// remove extension table
		$sql[] = "DROP TABLE IF EXISTS `exp_dc_freeform_geoip`";

		// run all sql queries
		foreach ($sql as $query)
		{
			$DB->query($query);
		}
	}

	// --------------------------------
	//	Extension Settings
	// --------------------------------	
	function settings() {
		$settings = array();
		
	    $settings['append_data']   = array('s', array('yes' => "yes", 'no' => "no"), 'no');
		
		return $settings;
	}

	/**
	 * Retrieves the location data from this provider http://www.hostip.info/use.html 
	 * and saves it into the database upon a form submission.
	 *
	 * @see		freeform_module_insert_begin hook
	 * @since	Version 1.0.0
	 */
	function freeform_module_insert_begin($data) {
		
		global $DB;
		
		// TODO: Replace this with a hook so that other modules can provide their search
		$url = 'http://api.hostip.info/get_html.php?ip=' . $data['ip_address'] . '&position=true';
		
		// get ip location data contents
		$handle = @fopen($url, 'r');
		$ip_location_data = stream_get_contents($handle);
		@fclose($handle);
		
		dprint_r($data);
		
		// add geoip values based on the form entry_date and ip to the database
		$DB->query("INSERT INTO exp_dc_freeform_geoip VALUES('', 
			'".$DB->escape_str($data['entry_date'])."',
			'".$DB->escape_str($data['ip_address'])."',
			'".$DB->escape_str($ip_location_data)."'
		)");
		
		// add to the array, this will be added to the database by freeform
		//$data['ip_location_data'] = $ip_location_data;
		
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
		$query = $DB->query(
			"SELECT g.ip_location_data, g.ip_address 
			FROM exp_dc_freeform_geoip AS g 
			INNER JOIN exp_freeform_entries AS f
			ON g.entry_date = f.entry_date
			WHERE f.entry_id='".$DB->escape_str($IN->GBL('entry_id'))."'");
		
		$ip_location_data = $this->_get_location_data($IN->GBL('entry_id'));

		// and show it only if it's set 
		if(!empty($ip_location_data))
		{
			//	=============================================
			//	Find IP Address Row
			//	=============================================
			preg_match('/ip\ address.*?<\/tr>/si', $out, $row);

			// replace line breaks with xhtml breaks
			$ip_location_data = str_replace("\n", "<br />", $ip_location_data);

			// html placeholder, a country flag could be added here
			$location_html = $ip_location_data;

			$r = $DSP->td_c().$DSP->tr_c();
			
			$r .= $DSP->tr();
			$r .= $DSP->table_qcell('tableCellOne', $DSP->qdiv('defaultBold', $LANG->line('ip_location_data')), '30%');
			$r .= $DSP->table_qcell('tableCellOne', $DSP->qdiv('', $location_html), '70%');
			$r .= $DSP->tr_c();
			
			//	=============================================
			//	Add Row
			//	=============================================
			$out = @str_replace($row[0], $row[0].$r, $out);
		}
			
		return $out;
	}

	function freeform_module_insert_end($fields, $entry_id, $msg)
	{
		$settings = $this->settings;
		
		dprint_r($entry_id);
		dprint_r($this->settings);
		
		if ($settings['append_data'] == 'yes')
		{
			$msg['msg'] = "\n\n" .$this->_get_location_data($entry_id);
		}
		
		dprint_r($msg);
	}
	
	function _get_location_data($entry_id)
	{
		global $DB;
		
		// get the ip location data
		$query = $DB->query(
			"SELECT g.ip_location_data, g.ip_address 
			FROM exp_dc_freeform_geoip AS g 
			INNER JOIN exp_freeform_entries AS f
			ON g.entry_date = f.entry_date
			WHERE f.entry_id='".$DB->escape_str($entry_id)."'");

		return $query->row['ip_location_data'];
	}
}
//END CLASS
?>
