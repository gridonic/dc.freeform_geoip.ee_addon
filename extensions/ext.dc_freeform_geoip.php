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
			'show_full_control_panel_end'		=> 'show_message'
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
		$DB->query("DELETE FROM exp_extensions WHERE class = '" . get_class($this) . "'");
	}

	/**
	 * Just a test method.
	 *
	 * @see		xxx hook
	 * @since	Version 1.0.0
	 */
	function show_message($out) {
		global $EXT;
	
		//	check if someone else uses this
		if ($EXT->last_call !== FALSE)
		{
			$out = $EXT->last_call;
		}
		
		return $out;
	}
}
//END CLASS
?>
