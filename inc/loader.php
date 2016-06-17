<?php
/**
 * Copyright 2014 Katz Web Services, Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

//------------------------------------------
if (class_exists("GFForms")) {

	class KWS_GF_Salesforce_Loader extends GFAddOn {

		protected $_version;
		protected $_min_gravityforms_version = "1.7";
		protected $_slug = "sf-a-loader";
		protected $_path = "gravity-forms-salesforce/inc/loader.php";
		protected $_full_path = __FILE__;
		protected $_title = "Gravity Forms: Salesforce";
		protected $_short_title = "Salesforce Add-On";
		protected $_service_name = "Salesforce";
		protected $_custom_field_placeholder = "";
		protected $_support_logging = false;

		function __construct() {

			$this->set_settings_on_activation();

			$this->_version = KWS_GF_Salesforce::version;

			parent::__construct();

			$plugins = $this->get_addon_setting('salesforce_integration');

			// If plugins are set, load'em up.
			if($plugins !== NULL) {

				// Load the API plugin
				if($plugins === 'api' || is_array( $plugins ) && !empty($plugins['api'])) {
					if(false === $this->is_incompatible_with_api() && !class_exists('GFSalesforce')) {
						require_once(KWS_GF_Salesforce::$plugin_dir_path.'inc/salesforce-api.php');
					}
				}

				// Load the Web-to-Lead plugin - if the only plugin active or one of two
				if( $plugins === 'web2lead' || is_array( $plugins ) && !empty($plugins['web2lead'])) {
					if(!class_exists('KWSGFWebToLeadAddon') && KWS_GF_Salesforce::supports_addon_api() ) {
					    require_once(KWS_GF_Salesforce::$plugin_dir_path.'inc/web-to-lead.php');
					    new KWSGFWebToLeadAddon;
					}
				}

			}

			// Add Daddy Analytics whether using Web-to-Lead or API
			if(!class_exists('KWSGFDaddyAnalyticsAddon') && KWS_GF_Salesforce::supports_addon_api()) {
			    require_once(KWS_GF_Salesforce::$plugin_dir_path.'inc/daddy_analytics.addon.php');
			    new KWSGFDaddyAnalyticsAddon;
			}

		}

		/**
		 * Check whether the server can handle this addon.
		 *
		 * @filter gf_salesforce_soap_is_available Boolean; force SOAP being available. This may be necessary if you use an alternative SOAP library.
		 * @link  https://developer.salesforce.com/page/Force.com_Toolkit_for_PHP See the requirements for the library
		 * @return boolean|string False: the site is compatible. This is good. String: The HTML of the error notice.
		 */
		public function is_incompatible_with_api() {

			$errors = array();

			// PHP 5.3 check
			if(!$this->is_php_53_or_higher()) {
				$errors['PHP Version 5.3 or Higher'] = wpautop( '<strong>'.__("As of version 2.7, the Gravity Forms Salesforce API Add-On requires PHP 5.3 or above. Please contact your hosting provider support and ask them to upgrade your server.", 'gravity-forms-salesforce').'</strong>

					'.sprintf(__('If this is not possible, you may %sdownload the last version of the plugin%s that did not require PHP 5.3.', 'gravity-forms-salesforce'), '<a href="http://downloads.wordpress.org/plugin/gravity-forms-salesforce.2.6.4.1.zip">', '</a>'));
			}

			// SOAP check
			// You can override this check by adding a filter on `gf_salesforce_soap_is_available` with `__return_true`
			if(!class_exists("SOAPClient") && !apply_filters( 'gf_salesforce_soap_is_available', false )) {
				$errors['SOAP Support'] = wpautop(__('The API Add-On requires SOAP support. Please contact your hosting provider and ask them to enable SOAP on your server.', 'gravity-forms-salesforce'));
			}

			// cURL check
			if(!function_exists('curl_version')) {
				$errors['cURL Support'] = wpautop(__('The API Add-On requires cURL. Please contact your hosting provider and ask them to enable cURL on your server.', 'gravity-forms-salesforce'));
			}

			// no openssl extension loaded.
			if (!extension_loaded('openssl')) {
			    $errors['OpenSSL Extension'] = wpautop(__('The API Add-On requires server support for the OpenSSL extension.', 'gravity-forms-salesforce'));
			}

			// We're good: not incompatible
			if(empty($errors)) {
				return false;
			}

			$output = '<div class="delete-alert alert_gray">';
			$output .= '<h2>'.__('The Salesforce: API Add-On could not be loaded.', 'gravity-forms-salesforce').'</h2>';
			$output .= '<p><strong>'.__('Please contact your hosting provider and ask them to enable support for the following on your server:', 'gravity-forms-salesforce').'</strong></p>';

			foreach ($errors as $key => $value) {
				$output .= '<h4>'.$key.'</h4> '.wpautop( $value );
			}

			$output .= wpautop(__('You can use the Salesforce Web-to-Lead Add-on; it doesn&#8217;t have the same requirements.', 'gravity-forms-salesforce'));

			$output .= '</div>';

			return $output;
		}

		/**
		 * Is the current PHP version higher than 5.3?
		 * @return boolean     True: yes; false: no.
		 */
		function is_php_53_or_higher() {
		    return version_compare(PHP_VERSION, '5.3.0') >= 0;
		}

		/**
		 * Is the current PHP version higher than 5.3?
		 * @return boolean     True: yes; false: no.
		 */
		function not_php_53_or_higher() {
		    return !$this->is_php_53_or_higher();
		}

		/**
		 * Disable logging
		 * @param  array      $plugins Existing GF plugins that support logging.
		 */
		function set_logging_supported($plugins) { return $plugins; }

		/**
		 * Set settings based on previous version plugins being active or not.
		 * @uses  GFAddon::update_plugin_settings()
		 * @return void
		 */
		function set_settings_on_activation() {

			// Get the current plugin settings
			$settings = $this->get_plugin_settings();

			// If the settings are already set, we don't need to set defaults.
			if(!empty($settings)) { return; }

			// There's one setting: salesforce_integration, and it's empty.
			$settings = array('salesforce_integration' => array());

			if(!function_exists('is_plugin_active')) {
				include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}

			// API used to be active
			if(is_plugin_active( 'gravity-forms-salesforce/salesforce-api.php' )) {
				$settings['salesforce_integration']['api'] = 1;
			}

			// Web to lead used to be active, too
			if(is_plugin_active( 'gravity-forms-salesforce/web-to-lead.php' )) {
				$settings['salesforce_integration']['web2lead'] = 1;
			}

			// Web to lead used to be active...
			if($old_web_to_lead = get_option( 'gf_salesforce_oid' )) {
				$settings['salesforce_integration']['web2lead'] = 1;
			}

			// If there's only one allowed...
			if(false === apply_filters( 'gf_salesforce_allow_both_addons', false ) && !empty($settings['salesforce_integration'])) {
				if(!empty($settings['salesforce_integration']['api'])) {
					$settings['salesforce_integration'] = 'api';
				} elseif(!empty($settings['salesforce_integration']['web2lead'])) {
					$settings['salesforce_integration'] = 'web2lead';
				}
			}

			if(!empty($settings['salesforce_integration'])) {
				$this->update_plugin_settings($settings);
			}

		}

		/**
		 * Add the logo for the plugin settings page
		 * @return string IMG HTML tag
		 */
		public function plugin_settings_icon() {
		    return '<img src="'.plugins_url( 'assets/images/salesforce-256x256.png', KWS_GF_Salesforce::$file ).'" width="84" class="alignleft" style="margin:0 10px 10px 0" />';
		}

		/**
		* Gravity Forms would only check if the `gform-settings-save` field has been saved.
		* We need to be more vigilant than that.
		*/
		public function is_save_postback(){
			return !rgempty("gform-settings-save") && !rgempty('_gaddon_setting_salesforce_integration');
		}

		/**
		 * Get a setting from the addon settings before otherwise available.
		 *
		 * @see  KWSGFAddOn2_2::get_addon_setting()
		 * @see  GFAddon::maybe_save_plugin_settings()
		 * @param  string $key Name of the key to retrieve from the settings array
		 * @return mixed|NULL      If set, return the setting. Otherwise, NULL.
		 */
		public function get_addon_setting($key = '') {

		    if( $this->is_save_postback() ) {

		        // This is taken from GFAddon::maybe_save_plugin_settings()
		        // and is used to fetch settings from saved configuration before
		        // they're saved in the GFAddon flow.
		        $posted_settings = $this->get_posted_settings();
		        $sections = $this->plugin_settings_fields();
		        $is_valid = $this->validate_settings( $sections, $posted_settings );

		        // If the saved settings are valid, use them as the value.
		        if($is_valid) { $settings = $posted_settings; }

		    } else {
		        $settings = $this->get_plugin_settings();
		    }

		    return isset($settings[$key]) ? $settings[$key] : NULL;
		}

		/**
		 * Add the logo for the Feeds page
		 * @return string IMG HTML tag
		 */
		public function get_service_icon() {
			return '<img src="'.plugins_url( 'assets/images/salesforce-256x256.png', KWS_GF_Salesforce::$file ).'" width="84" class="alignleft" style="margin:0 10px 10px 0" />';
		}

		/**
		 * @inheritDoc
		 */
		public function plugin_settings_fields() {

			// If you want to allow both addons to be active at the same time, use:
			// `add_filter('gf_salesforce_allow_both_addons', '__return_true');`
			$type = (apply_filters( 'gf_salesforce_allow_both_addons', false ) ? 'checkbox' : 'radio');

			return array(
				array(
					"title"  => __("Salesforce Configuration", 'gravity-forms-salesforce'),
					"description" => $this->plugin_description(),
					"fields" => apply_filters('gf_salesforce_loader_fields', array(
						array(
							'type'    => 'html',
							'class'		=> 'updated',
							'value'    => $this->is_incompatible_with_api(),
							'dependency' => array(&$this, 'is_incompatible_with_api'),
						),
						array(
							"name"    => "salesforce_integration",
							"label"   => sprintf(__("%sIntegration Method%s %sChoose the type of connection to Salesforce.", "gravity-forms-salesforce"), '<strong>', '</strong>', '<span class="howto">', '</span>'),
							"type"    => $type,
							#"horizontal" => true,
							"default_value" => 'web2lead',
							"choices" => $this->get_plugin_choices($type),
						),
						array(
							'name' => 'Save',
							'type' => 'save',
							'messages' => array(
								'success' => __('Settings updated successfully.', 'gravity-forms-salesforce'),
								'error' => __('Settings failed to update successfully.', 'gravity-forms-salesforce')
							)
						),
						array(
							'type' => 'html',
							'value' => wpautop(__("Note: for detailed plugin debug and error logs, please install the <a href='http://gravityhelp.com/downloads/#Gravity_Forms_Logging_Tool' target='_blank'>Gravity Forms Logging Tool</a>.", 'gravity-forms-salesforce')),
						),
					)) // End fields
				)
			);
		}

		/**
		 * Add a description for the addon
		 * @return string      HTML of description
		 */
		function plugin_description() {
			return '<style>.gform_tab_content .push-alert-red { display:none!important; }</style>';;
		}

		function get_plugin_choices($type = 'radio') {

			$choices = array();
			$choices[] = array(
					"label" => "Web-to-Lead or Web-to-Case",
					"value"  => "web2lead",
					"name"	=> ($type === 'radio' ? 'web2lead' : 'salesforce_integration[web2lead]'),
					"default_value" => 1,
					"tooltip" => '<h6>Web-to-Lead or Web-to-Case</h6>
						<p>Web-to-Lead is available to all Salesforce Editions. If you aren&#8217;t sure if your Salesforce Edition supports the API, you should use Web-to-Lead.</p>

						<h4>Editions that don&#8217;t support the Salesforce API:</h4>
						<ul class="ul-disc">
						<li style="list-style:disc;">Personal Edition</li>
						<li style="list-style:disc;">Group Edition</li>
						<li style="list-style:disc;">Professional Edition<br /><em>Note: You can also purchase API access for a Professional Edition.</em></li>
						</ul>'
			);

			if(false === $this->is_incompatible_with_api()) {
				$choices[] = array(
						"label" => "API",
						"value"  => "api",
						"name"	=> ($type === 'radio' ? 'api' : 'salesforce_integration[api]'),
						"tooltip" => '<h6>Using the API</h6>
							<p>The API is more powerful than Web-to-Lead; you can create different object types, as well as other advanced features.</p>
							<p>If you have the following Salesforce Editions, you can use the included API Add-on:</p>

							<ul class="ul-disc">
							<li style="list-style:disc;">Enterprise Edition</li>
							<li style="list-style:disc;">Unlimited Edition</li>
							<li style="list-style:disc;">Developer Edition</li>
							<li style="list-style:disc;">Professional Edition - <em>Requires API Upgrade</em></li>
							</ul>',
				);
			}


			return $choices;
		}

		/**
		 * Show a notice if SOAP isn't available
		 */
		public static function is_soap_installed() {
			echo sprintf('<div id="message" class="error"><h3>%s</h3>%s</div>', __('The Salesforce: API Add-On could not be loaded.', 'gravity-forms-salesforce'), wpautop(__('The Gravity Form Salesforce: API Add-On requires that your server support SOAP. Please contact your hosting provider and ask them to enable SOAP on your server.

				You can also use the Salesforce Web-to-Lead Add-on; this doesn&#8217;t require SOAP.', 'gravity-forms-salesforce')));
		}

		/**
		 * Display the HTML field type
		 * @param  array      $field   Add-On settings fields
		 * @return void
		 */
		protected function single_setting_row_html($field) {

		    if(empty($field['value'])) { return; }

		    echo '<tr><td colspan="2">'.$field['value'].'</td></tr>';
		}
	}

	new KWS_GF_Salesforce_Loader;
}