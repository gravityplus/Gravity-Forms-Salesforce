<?php
/*
Plugin Name: Gravity Forms Salesforce - Web-to-Lead Add-On
Plugin URI: https://katz.co/plugins/gravity-forms-salesforce/
Description: Integrate <a href="http://formplugin.com?r=salesforce">Gravity Forms</a> with Salesforce - form submissions are automatically sent to your Salesforce account! <strong>Requires Gravity Forms 1.7+</strong>.
Version: 2.6.3.4
Requires at least: 3.3
Author: Katz Web Services, Inc.
Author URI: http://www.katz.co

------------------------------------------------------------------------
Copyright 2014 Katz Web Services, Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

add_action('plugins_loaded', 'KWSGFWebToLeadAddon_load', 100);

/**
 * Just a front-end loader. Check the `web-to-lead.addon.php` file for the meat.
 */
function KWSGFWebToLeadAddon_load($value='') {

	if(!class_exists('KWSGFAddOn2_1')) {
		require_once(plugin_dir_path(__FILE__).'/lib/kwsaddon.php');
	}

    if(!class_exists('KWSGFWebToLeadAddon')) {
    	require_once(plugin_dir_path(__FILE__).'/web-to-lead.addon.php');
	}

    if(class_exists('KWSGFWebToLeadAddon')) {
        $KWSGFWebToLeadAddon = new KWSGFWebToLeadAddon;
        $KWSGFWebToLeadAddon->add_custom_hooks();
    } else {
    	// For users running less than 1.7, show them a compatibility message.
    	add_action('admin_notices', 'KWSGFWebToLeadAddon_compatibility', 20);
    }
}

/**
 * Show a compatibility message suggesting an upgrade.
 */
function KWSGFWebToLeadAddon_compatibility() {
    $message = sprintf('%sGravity Forms Salesforce Web-to-Lead Add-On%s Requires version %s of Gravity Forms or better. If you have Gravity Forms, please upgrade to the latest Gravity Forms or purchase a license if you don\'t already have one. %sGet Gravity Forms<em> - starting at $39</em>%s', '<h3>', '</h3><p style="text-align:left;">', '1.7', '</p><p style="text-align:left;"><a href="http://katz.si/gravityforms" target="_blank" class="button button-secondary button-large button-hero">', '</a></p>');
    echo '<div class="updated">'.$message.'</div>';
}