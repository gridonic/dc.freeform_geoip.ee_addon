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
if (!defined('DC_FREEFORM_ID'))
{
	define("DC_FREEFORM_ID", "DC FreeForm GeoIP");
}

class DC_FreeForm_GeoIP
{

	var $settings		= array();

	var $name			= 'DC FreeForm GeoIP Extension';
	var $version		= '1.0.3';
	var $description	= 'Geocodes IPs to locations in Freeform.';
	var $settings_exist = 'y';
	var $docs_url		= 'http://www.designchuchi.ch/index.php/blog/comments/dc-freeform-geoip-extension';
	
	var $freeform_ver   = '2.6.6';

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
		global $DB, $OUT, $LANG;
		
		// fetch language
		$LANG->fetch_language_file('dc_freeform_geoip');

		// check if the FreeForm module is available
		$freeform_check = $DB->query("SELECT module_version FROM exp_modules WHERE module_name = 'Freeform'");
		if ($freeform_check->num_rows < 1)
		{
			$OUT->fatal_error($LANG->line('freeform_not_found'));
			return;
		}
		else if ($freeform_check->row['module_version'] < $this->freeform_ver) {
       	    $OUT->fatal_error(str_replace('%{version}', $this->freeform_ver, $LANG->line('freeform_old_version')));
			return;
       	}

		// default setting values
		$append_data	= 'n';
		$check_updates	= 'y';

		// hooks array
		$hooks = array(
			'freeform_module_insert_begin'		 => 'freeform_module_insert_begin',
			'freeform_module_admin_notification' => 'freeform_module_admin_notification',
			'show_full_control_panel_end'		 => 'display_form_entries',
			'lg_addon_update_register_source'	 => 'dc_freeform_geoip_register_source',
			'lg_addon_update_register_addon'	 => 'dc_freeform_geoip_register_addon'
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

		global $DB, $EXT;
		
		/**	----------------------------------------------------
		 *	"dc_freeform_geocode_ip" hook
		 *	----------------------------------------------------
		 *	Allow developers to add their own ip location data
		 *	--------------------------------------------------*/
		if ($EXT->active_hook('dc_freeform_geocode_ip') === TRUE)
		{
			$ip_location_data = $EXT->call_extension('dc_freeform_geocode_ip', $data['ip_address']);
			if ($EXT->end_script === TRUE) return;
		}
		else
		{
			// if no hook is present, query a database ourselves
			$ip_location_data = $this->_hostip_geocode($data['ip_address']);
		}

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
	 * @param	string 		$out 	The entire html output of the control panel page.
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
	 * @param	array 	$fields		All submitted form fields.
	 * @param	int		$entry_id	The entry id of the form.
	 * @param	array 	$msg		An array containing the message from the form.
	 * @see		freeform_module_insert_end hook
	 * @since	Version 1.0.1
	 */
	function freeform_module_admin_notification($fields, $entry_id, $msg)
	{
    	$settings = $this->settings;
		$location_data = $this->_get_location_data($entry_id);
		
		if ($settings['append_data'] == 'y')
		{
			// This currently does not work because the hook provided by the freeform
			// module sends the message out before the hook is being called. Let's hope Solspace corrects this.
			$msg['msg'] = $msg['msg'] . "\n\n" . $location_data;
		}
		// else try to replace
		else
		{
			$msg['msg'] = str_replace(LD.'ip_location_data'.RD, $location_data, $msg['msg']);
		}
		
		return $msg;
	}
	
	/**
	 * Private helper function to retrieve the ip_location_data
	 * for a form entry saved by a previous hook during form submission.
	 *
	 * @param	int		$entry_id	An entry_id of a freeform entry.
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
	 * Quick and dirty parser of the hostip.info API data.
	 *
	 * @param	string	$ip_address	An ip address string to geocode.
	 * @since	Version 1.0.3
	 */
	function _hostip_geocode($ip_address)
	{
        $url = "http://api.hostip.info/get_html.php?ip=$ip_address&position=true";
        $ch = curl_init();    // initialize curl handle
        curl_setopt($ch, CURLOPT_URL, $url); // set url to post to
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return into a variable
        curl_setopt($ch, CURLOPT_TIMEOUT, 4); // times out after 4s
        $result = curl_exec($ch); // run the whole process
        curl_close($ch);
        
		$ip_string = 'IP Address: ' . $ip_address . "\n";
        return $ip_string . $result;
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
			$addons[DC_FREEFORM_ID] = $this->version;
		}
		return $addons;
	}
}
//END CLASS
?>
