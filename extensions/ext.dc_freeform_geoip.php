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

// define id for this extension, used in LG_Addon_Updater
if (!defined('DC_FGP_id'))
{
	define("DC_FGP_id", "DC FreeForm GeoIP");
}

class DC_FreeForm_GeoIP
{

	var $settings		= array();

	var $name			= 'FreeForm GeoIP Extension';
	var $version		= '1.0.2';
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
		$append_data	= 'n';
		$check_updates	= 'y';

		// hooks array
		$hooks = array(
			'freeform_module_insert_begin'		=> 'freeform_module_insert_begin',
			'freeform_module_insert_end'		=> 'freeform_module_insert_end',
			'show_full_control_panel_end'		=> 'display_form_entries',
			'lg_addon_update_register_source'	=> 'dc_freeform_geoip_register_source',
			'lg_addon_update_register_addon'	=> 'dc_freeform_geoip_register_addon'
		);

		// default settings
		$default_settings = serialize(
			array(
				'append_data'	=> $append_data,
				'check_updates' => $check_updates
			)
		);

		foreach ($hooks as $hook => $method)
		{
			$sql[] = $DB->insert_string('exp_extensions',
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
				$sql[] = $DB->insert_string('exp_extensions',
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

		// Add hooks for automatic updates using LG_Addon_Updater
		if ($current < '1.0.2')
		{
			// hooks array
			$hooks = array(
				'lg_addon_update_register_source'	=> 'dc_freeform_geoip_register_source',
				'lg_addon_update_register_addon'	=> 'dc_freeform_geoip_register_addon'
			);

			foreach ($hooks as $hook => $method)
			{
				$sql[] = $DB->insert_string('exp_extensions',
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

		$settings['append_data']   = array('s', array('y' => "yes", 'n' => "no"), 'n');
		$settings['check_updates']	 = array('s', array('y' => "yes", 'n' => "no"), 'n');

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
		// This probably won't work on every host, we'll have to wait for bug reports
		// and see what we can come up with.
		// TODO: Get bug reports and see if this works for users
		// FIXME: Use alternative function(s) for this.
		$handle = @fopen($url, 'r');
		$ip_location_data = stream_get_contents($handle);
		@fclose($handle);

		// add geoip values based on the form entry_date and ip to the database
		$DB->query(
			$DB->insert_string('exp_dc_freeform_geoip',
				array(
					'entry_date'		=> $data['entry_date'],
					'ip_address'		=> $data['ip_address'],
					'ip_location_data'	=> $ip_location_data
				)
			)
		);

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
			WHERE f.entry_id='" . $DB->escape_str($IN->GBL('entry_id')) . "'");

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

	/**
	 * Either appends the location data to the end of the message being sent out as a notification
	 * or replaces the {ip_location_data} tag in that message.
	 *
	 * @see		freeform_module_insert_end hook
	 * @since	Version 1.0.1
	 */
	function freeform_module_insert_end($fields, $entry_id, $msg)
	{
		$settings = $this->settings;

		if ($settings['append_data'] == 'yes')
		{
			// This currently does not work because the hook provided by the freeform
			// module sends the message out before the hook is being called. Let's hope Solspace corrects this.
			// $msg['msg'] = $msg['msg'] . "\n\n" .$this->_get_location_data($entry_id);
		}
	}
	
	/**
	 * TODO: Recode this function.
	 */
	function _geocode_ip($ip_address)
	{
		$url = "http://api.hostip.info/get_html.php?ip=$ip_address&position=true";
		$ch = curl_init();	  // initialize curl handle
		curl_setopt($ch, CURLOPT_URL,$url); // set url to post to
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); // return into a variable
		curl_setopt($ch, CURLOPT_TIMEOUT, 4); // times out after 4s
		curl_setopt($ch, CURLOPT_POSTFIELDS, $XPost); // add POST fields
		$result = curl_exec($ch); // run the whole process

		$find_string = "Country:";
		$start = strpos($result,$find_string);
		if ($start != false){
				$start = strpos($result,$find_string);
				$line_end = strpos($result,"\n",$start) - strlen($find_string) - $start;
				$geocode['country'] = trim(substr($result,$start + strlen($find_string),$line_end));
		}

		$find_string = "City:";
		$start = strpos($result,$find_string);
		if ($start != false){
				$line_end = strpos($result,"\n",$start) - strlen($find_string) - $start;
				$city_state = trim(substr($result,$start + strlen($find_string),$line_end));
				$geocode['city'] = trim(substr($city_state,0,strpos($city_state,",")));
				$geocode['state'] = trim(substr($city_state,strpos($city_state,",")+1));
		}

		$find_string = "Latitude:";
		$start = strpos($result,$find_string);
		if ($start != false){
				$line_end = strpos($result,"\n",$start) - strlen($find_string) - $start;
				$geocode['latitude'] = trim(substr($result,$start + strlen($find_string),$line_end));
		}

		$find_string = "Longitude:";
		$start = strpos($result,$find_string);
		if ($start != false){
				$line_end = strpos($result,"\n",$start) - strlen($find_string) - $start;
				$geocode['latitude'] = trim(substr($result,$start + strlen($find_string),$line_end));
		}

		$find_string = "Longitude:";
		$start = strpos($result,$find_string);
		if ($start != false){
				$line_end = strpos($result,"\n",$start) - strlen($find_string) - $start;
				if ($line_end <= 0) $line_end = strlen($result) - $start - strlen($find_string);
				$geocode['longitude'] = trim(substr($result,$start + strlen($find_string),$line_end));
		}
		
		return $geocode;
	}

	/**
	 * Private helper function to retrieve the ip_location_data
	 * for a form entry saved by a previous hook during form submission.
	 *
	 * @since	Version 1.0.1
	 */
	function _get_location_data($entry_id)
	{
		global $DB;

		// get the ip location data
		// the connection between an entry_id and our location data is made through the entry_date
		// this is somehow an ugly workaround but that's because we don't have other that entry_date
		// to work with in the first freeform hook freeform_module_insert_begin we could use
		$query = $DB->query(
			"SELECT g.ip_location_data
			FROM exp_dc_freeform_geoip AS g
			INNER JOIN exp_freeform_entries AS f
			ON g.entry_date = f.entry_date
			WHERE f.entry_id='" . $DB->escape_str($entry_id) .	"'");

		// return location data if found
		// after deactivating the extension, there will be no data for the former entries
		if (isset($query->row['ip_location_data'])) 
		{
			return $query->row['ip_location_data'];
		}
		
		return false;
	}

	/**
	* Register a new Addon Source
	*
	* @param	array $sources The existing sources
	* @return	array The new source list
	* @since	version 1.0.2
	*/
	function dc_freeform_geoip_register_source($sources)
	{
		global $EXT;

		// -- Check if we're not the only one using this hook
		if($EXT->last_call !== FALSE)
			$sources = $EXT->last_call;

		// add a new source
		if($this->settings['check_updates'] == 'y')
		{
			$sources[] = 'http://www.designchuchi.ch/versions.xml';
		}

		return $sources;

	}

	/**
	* Register a new Addon
	*
	* @param	array $addons The existing sources
	* @return	array The new addon list
	* @since	version 1.0.2
	*/
	function dc_freeform_geoip_register_addon($addons)
	{
		global $EXT;

		// -- Check if we're not the only one using this hook
		if ($EXT->last_call !== FALSE)
			$addons = $EXT->last_call;

		// add a new addon
		// the key must match the id attribute in the source xml
		// the value must be the addons current version
		if($this->settings['check_updates'] == 'y')
		{
			$addons[DC_FGP_id] = $this->version;
		}
		return $addons;
	}
}
//END CLASS
?>
