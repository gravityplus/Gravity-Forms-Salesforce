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

	class KWSGFDaddyAnalyticsAddon extends KWSGFAddOn2_1 {
		protected $_version = "2.7.0";
		protected $_min_gravityforms_version = "1.7";
		protected $_slug = "daddy-aanlytics-web-to-lead";
		protected $_path = "gravity-forms-salesforce/lib/daddy_analytics.addon.php";
		protected $_full_path = __FILE__;
		protected $_title = "Gravity Forms: Salesforce Web-to-Lead & Daddy Analytics Add-On";
		protected $_short_title = "Salesforce: Daddy Analytics";
		protected $_service_name = "Daddy Analytics";
		protected $_custom_field_placeholder = "Daddy Analytics Site ID";

		/**
		 * Add the logo for the Feeds page
		 * @return string IMG HTML tag
		 */
		public function get_service_icon() {
			return '<img src="'.plugins_url( 'images/daddy_analytics_icon_50.png', dirname(__FILE__) ).'" class="alignleft" style="margin:0 10px 10px 0" />';
		}

		public function get_service_favicon_path() {
			return plugins_url( 'images/daddy_analytics_icon_16.png', dirname(__FILE__) );
		}

		/**
		 * @inheritDoc
		 */
		public function plugin_settings_fields() {
			return array(
				array(
					"title"  => sprintf(__("%s Configuration", 'gravity-forms-salesforce'), $this->get_service_name()),
					"fields" => array(
						array(
							'type'    => 'html',
							'class'		=> 'error',
							'value'    => $this->invalid_api_message(),
							'dependency' => array(&$this, 'is_invalid_api'),
						),
						array(
							'type'    => 'html',
							'class'		=> 'updated',
							'value'    => $this->valid_api_message(),
							'dependency' => array(&$this, 'is_valid_api'),
						),
						array(
							"name"    => "daddy_analytics_site_id",
							"label"   => __("Daddy Analytics Site ID", "gravity-forms-salesforce"),
							"type"    => "text",
							"class"   => "medium code",
							"tooltip" => sprintf(__("TODO", 'gravity-forms-salesforce'), 'goes after %s')
						),
						array(
							"name"    => "daddy_analytics_token",
							"label"   => __("Daddy Analytics Token", "gravity-forms-salesforce"),
							"type"    => "text",
							"class"   => "medium code",
							"tooltip" => sprintf(__("TODO", 'gravity-forms-salesforce'), 'goes after %s')
						),
						array(
							"name"    => "daddy_analytics_webtolead_url_id",
							"label"   => __("Daddy Analytics Web to Lead URL ID", "gravity-forms-salesforce"),
							"type"    => "text",
							"class"   => "medium code",
							"tooltip" => sprintf(__("TODO", 'gravity-forms-salesforce'), 'goes after %s')
						),
						array(
							'name' => 'Save',
							'type' => 'save',
							'messages' => array(
								'success' => 'Settings updated successfully.',
								'error' => 'Settings failed to update successfully.'
							)
						)
					)
				)
			);
		}

		protected function valid_api_message() {
			return '<span class="gf_keystatus_valid_text">'.sprintf(__("%s Active: Your Daddy Analytics configuration is valid.", 'gravity-forms-salesforce'), '<i class="fa fa-check gf_keystatus_valid"></i>').'</span>';
		}

		protected function invalid_api_message() {

			$tofind = sprintf(__('Please login to your Daddy Analytics account to obtain your account information.', 'gravity-forms-salesforce'));
			if(!$this->get_addon_setting('daddy_analytics_site_id')) {
				$message = __("Please complete all fields for your Daddy Analytics account.", 'gravity-forms-salesforce');
			} else {
				$message = '<span class="gf_keystatus_invalid_text">'.sprintf(__("%s Invalid field values - Please confirm your settings.", 'gravity-forms-salesforce'), '<i class="fa fa-times gf_keystatus_invalid"></i>').'</span>';
			}

			return '<h4>'.$message.'</h4>'.$tofind;
		}

		/**
		 * Is the API valid?
		 * Stores validity in `$_service_api_valid` and re-checks if the form has been submitted.
		 * @return boolean True: Org ID is valid; False: Org ID is invalid.
		 */
		public function is_valid_api() {

			// If the form hasn't just been saved, then use the stored value.
			// Otherwise, we should check again if the settings are valid.
			if(!is_null($this->_service_api_valid) && empty($_POST['gform-settings-save'])) {
				return $this->_service_api_valid;
			}

			//@TODO: Find way to validate Daddy Analytics config options
			//check for non-empty values in all 3 fields
			$flag  = $this->get_addon_setting('daddy_analytics_site_id');
			if(!empty($flag)) $flag  = $this->get_addon_setting('daddy_analytics_site_id');
			if(!empty($flag)) $flag  = $this->get_addon_setting('daddy_analytics_site_id');
			$this->_service_api_valid = (!empty($flag));
			return $this->_service_api_valid;

		}

		public function build_daddy_analytics_javascript(){

			$da_token = $this->get_addon_setting('daddy_analytics_token');
			$da_url = $this->get_addon_setting('daddy_analytics_webtolead_url_id');
			$da_site = $this->get_addon_setting('daddy_analytics_site_id');

			$output = "\n\n".'<!-- Begin Daddy Analytics code provided by Salesforce to Lead Plugin-->
			<script src="//cdn.daddyanalytics.com/w2/daddy.js" type="text/javascript"></script>
			<script type="text/javascript">
			var da_data = daddy_init(\'{ "da_token" : "'.esc_attr($da_token).'", "da_url" : "'.esc_attr($da_url).'" }\');
			var clicky_custom = {session: {DaddyAnalytics: da_data}};
			</script>
			<script src="//hello.staticstuff.net/w/__stats.js" type="text/javascript"></script>
			<script type="text/javascript">try{ clicky.init( "'.esc_attr($da_site).'" ); }catch(e){}</script>'."<!-- End Daddy Analytics code provided by Salesforce to Lead Plugin-->\n\n";
			echo $output;
			return $output;
		}
	}

}