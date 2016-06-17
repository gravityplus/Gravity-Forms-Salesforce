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

	class KWSGFWebToLeadAddon extends KWSGFAddOn2_2 {
		protected $_version;
		protected $_min_gravityforms_version = "1.7";
		protected $_slug = "sf-web-to-lead";
		protected $_path = "gravity-forms-salesforce/inc/web-to-lead.php";
		protected $_full_path = __FILE__;
		protected $_title = "Gravity Forms Salesforce Web-to-Lead Add-On";
		protected $_short_title = "Salesforce:  Web-to-Lead"; // Two spaces make it above DA
		protected $_service_name = "Salesforce";
		protected $_custom_field_placeholder = "Salesforce API ID";

		function __construct() {

			$this->_version = KWS_GF_Salesforce::version;

			add_action('kwsgfwebtoleadaddon_log_debug', array( $this, 'log_debug' ));
			add_action('kwsgfwebtoleadaddon_log_error', array( $this, 'log_error' ));

			parent::__construct();

		}

		/**
		 * Add the logo for the Feeds page
		 * @return string IMG HTML tag
		 */
		public function get_service_icon() {
			return '<img src="'.plugins_url( 'assets/images/salesforce-256x256.png', KWS_GF_Salesforce::$file ).'" class="alignleft" width="84" style="margin:0 10px 10px 0" />';
		}

		public function get_service_favicon_path() {
			return plugins_url( 'assets/images/salesforce-16x16.png', KWS_GF_Salesforce::$file );
		}

		/**
		 * Columns to show on the list of Feeds.
		 * @return array Array of columns
		 */
		function feed_list_columns() {
			$lists = parent::feed_list_columns();
			unset($lists['lists']);
			return $lists;
		}

		/**
		 * @inheritDoc
		 */
		public function plugin_settings_fields() {
			return array(
				array(
					"title"  => sprintf(__("%s Account Information", 'gravity-forms-salesforce'), $this->get_service_name()),
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
							"name"    => "org_id",
							"label"   => __("Salesforce Org. ID", "gravity-forms-salesforce"),
							"type"    => "text",
							"class"   => "medium code",
							"tooltip" => sprintf(__("To find your Salesforce.com Organization ID, in your Salesforce.com account, go to [Your Name] &raquo; Setup &raquo; Company Profile (near the bottom of the left sidebar) &raquo; Company Information. It will look like %s", 'gravity-forms-salesforce'), '<code>00AB0000000Z9kR</code>')
						),
						array(
							"name"    => "date_format",
							"label"   => __("Date Format", "gravity-forms-salesforce"),
							"type"    => "radio",
							"horizontal"    => true,
							"default_value" => $this->get_default_date_format(),
							/**
							 * Modify the options for date formats available in the settings
							 * @since 3.1.2
							 */
							'choices' => apply_filters('gravityforms/salesforce/date_format_choices', array(
								array(
									'value' => 'm/d/Y',
									'label' => 'mm/dd/yyyy',
								),
								array(
									'value' => 'd/m/Y',
									'label' => 'dd/mm/yyyy',
								),
							)),
							'tooltip' => "<h6>" . __("Date Format", "gravity-forms-salesforce") . "</h6>" . __("The format dates are stored for your Salesforce Organization. If you are in the United States of America, the date format will be mm/dd/yyyy. Most of the rest of the world disagrees.", 'gravity-forms-salesforce'),
						),
						array(
							"name"    => "debug",
							"label"   => __("Receive Salesforce Debugging Emails <p class='howto'><span>For full plugin logs, install the <a href='http://gravityhelp.com/downloads/#Gravity_Forms_Logging_Tool' target='_blank'>Gravity Forms Logging Tool</a>.</span></p>", "gravity-forms-salesforce"),
							"type"    => "checkbox",
							"class"   => "checkbox",
							'dependency' => array(&$this, 'is_valid_api'),
							"choices" => array(
								array(
									'label' => __('Enable Salesforce debugging emails. The site administrator will be emailed by Salesforce with import status for each form submission.', 'gravity-forms-salesforce'),
									'value' => 'debug_email',
									'name' => 'debug_email'
								)
							)
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
			return '<span class="gf_keystatus_valid_text">'.sprintf(__("%s Success: Your Org ID. is properly configured.", 'gravity-forms-salesforce'), '<i class="fa fa-check gf_keystatus_valid"></i>').'</span>';
		}

		protected function invalid_api_message() {

			$oid = $this->get_plugin_setting('org_id');
			$tofind = sprintf(__('To find your Salesforce.com Organization ID, in your Salesforce.com account, go to:%1$s %2$s[Your Name] &raquo; Setup &raquo; Company Profile%3$s (near the bottom of the left sidebar) %2$s&raquo; Company Information%3$s (it is buried in the table of information). It will look like %4$s.', 'gravity-forms-salesforce'), '<br />', "<span class='code'>", '</span>', '<code>00AB0000000Z9kR</code>');
			if(empty($oid)) {
				$message = __("Please enter your Salesforce Organization ID.", 'gravity-forms-salesforce');
			} else {
				$message = '<span class="gf_keystatus_invalid_text">'.sprintf(__("%s Invalid Org ID. - Please confirm your setting. Try re-saving the form.", 'gravity-forms-salesforce'), '<i class="fa fa-times gf_keystatus_invalid"></i>').'</span>';
			}

			return '<h4>'.$message.'</h4>'.$tofind;
		}

		/**
		 * We don't need lists. Take the standard fields from KWSAddon and remove the lists option.
		 * @return array Array of fields for the feed settings.
		 */
		public function feed_settings_fields() {
			return array(
				array(
					"title" => sprintf(__("Map your fields for export to %s.", 'gravity-forms-salesforce'), $this->get_service_name()),
					"fields" => array(
						array(
							'label' => __('Feed Name', 'gravity-forms-salesforce'),
							'name' => 'name',
							'type' => 'text',
							'value' => '',
							'placeholder' => __('Feed Name (optional)', 'gravity-forms-salesforce'),
						),
						array(
							'type' => 'select',
							'label' => __('Pick an export type.', 'gravity-forms-salesforce'),
							'name' => 'type',
							'default_value' => 'Lead',
							'choices' => array(
								array(
									'name' => 'lead',
									'value' => 'Lead',
									'label' => __('Lead', 'gravity-forms-salesforce')
								),
								array(
									'name' => 'case',
									'value' => 'Case',
									'label' => __('Case', 'gravity-forms-salesforce')
								)
							),
							'tooltip' => sprintf("<h6>" . __("Object to Create in Salesforce", "gravity-forms-salesforce") . "</h6>" . __("When the form is exported to Salesforce, do you want to have the entry become a Lead or a Case?", 'gravity-forms-salesforce')),
						),
						array(
							'type' => 'field_map',
							'label' => __('Map your fields.', 'gravity-forms-salesforce'),
							'tooltip' => "<h6>" . __("Map Fields", "gravity-forms-salesforce") . "</h6>" . sprintf(__("Associate your %s merge variables to the appropriate Gravity Form fields by selecting.", 'gravity-forms-salesforce'), $this->get_service_name()),
							'name' => null,
							'field_map' => $this->map_custom_fields($this->feed_settings_fields_field_map())
						),
						array(
							'type' => 'custom_fields',
							'label' => __("Custom Fields", 'gravity-forms-salesforce'),
							'tooltip' => sprintf(__("If you don&#8217;t see the Salesforce field you want to send data to, add a custom field. You can map any Salesforce Field using the Salesforce Field \"API Name,\" which look like %sCustom_Field_Example__c%s.", 'gravity-forms-salesforce'), '<code>', '</code>'),
							'name' => null
						),
						array(
							'label' => 'Opt-in Condition',
							'name' => 'feed_condition',
							'type' => 'feed_condition',
							'tooltip' => sprintf("<h6>" . __("Opt-In Condition", "gravity-forms-salesforce") . "</h6>" . __("When the opt-in condition is enabled, form submissions will only be exported to %s when the condition is met. When disabled all form submissions will be exported.", 'gravity-forms-salesforce'), $this->get_service_name()),
						)
					)
				)
			);

			return $fields;
		}

		/**
		 * Set up the feed forms.
		 * @return array Array of feed fields
		 */
		protected function feed_settings_fields_field_map() {

			$fields = array(
				array(
					'name' => 'email',
					'required' => true,
					'label' => __("Email"),
					'error_message' => __("You must set an Email Address", 'gravity-forms-salesforce')
				),
				array(
					'name' => 'salutation',
					'required' => false,
					'label' => __("Salutation", 'gravity-forms-salesforce')
				),
				array(
					'name' => 'first_name',
					'required' => false,
					'label' => __("Name (First)", 'gravity-forms-salesforce')
				),
				array(
					'name' => 'last_name',
					'required' => false,
					'label' => __("Name (Last)", 'gravity-forms-salesforce')
				),
				array(
					'name' => 'title',
					'required' => false,
					'label' => __("Title", 'gravity-forms-salesforce')
				),
				array(
					'name' => 'company',
					'required' => false,
					'label' => __("Company", 'gravity-forms-salesforce')
				),
				array(
					'name' => 'phone',
					'required' => false,
					'label' => __("Phone", 'gravity-forms-salesforce')
				),
				array(
					'name' => 'mobile',
					'required' => false,
					'label' => __("Mobile", 'gravity-forms-salesforce')
				),
				array(
					'name' => 'fax',
					'required' => false,
					'label' => __("Fax", 'gravity-forms-salesforce')
				),
				array(
					'name' => 'street',
					'required' => false,
					'label' => __("Address (Street Address)", 'gravity-forms-salesforce')
				),
				array(
					'name' => 'street2',
					'required' => false,
					'label' => __("Address (Address Line 2)", 'gravity-forms-salesforce')
				),
				array(
					'name' => 'city',
					'required' => false,
					'label' => __("Address (City)", 'gravity-forms-salesforce')
				),
				array(
					'name' => 'state',
					'required' => false,
					'label' => __("Address (State / Province)", 'gravity-forms-salesforce')
				),
				array(
					'name' => 'country',
					'required' => false,
					'label' => __("Address (Country)", 'gravity-forms-salesforce')
				),
				array(
					'name' => 'zip',
					'required' => false,
					'label' => __("Address (Zip / Postal Code)", 'gravity-forms-salesforce')
				),
				array(
					'name' => 'URL',
					'required' => false,
					'label' => __('Website', 'gravity-forms-salesforce')
				),
				array(
					'name' => 'twitter',
					'required' => false,
					'label' => __('Twitter Username', 'gravity-forms-salesforce')
				),
				array(
					'name' => 'message',
					'required' => false,
					'label' => __('Message', 'gravity-forms-salesforce')
				),
				array(
					'name' => 'lead_source',
					'required' => false,
					'label' => apply_filters('gf_salesforce_lead_source_label', __('Lead Source', 'gravity-forms-salesforce')),
				),
				array(
					'name' => 'description',
					'required' => false,
					'label' => __('Message', 'gravity-forms-salesforce')
				),
				array(
					'name' => 'industry',
					'required' => false,
					'label' => __('Industry', 'gravity-forms-salesforce')
				),
				array(
					'name' => 'rating',
					'required' => false,
					'label' => __('Rating', 'gravity-forms-salesforce')
				),
				array(
					'name' => 'revenue',
					'required' => false,
					'label' => __('Annual Revenue', 'gravity-forms-salesforce')
				),
				array(
					'name' => 'employees',
					'required' => false,
					'label' => __('# of Employees', 'gravity-forms-salesforce')
				),
				array(
					'name' => 'Campaign_ID',
					'required' => false,
					'label' => __('Campaign ID', 'gravity-forms-salesforce') . '<div style="max-width:350px;"><span class="description">' . sprintf(__('To associate an entry with a Campaign, you must also set the "Member Status". %sRead the Salesforce help article.%s', 'gravity-forms-salesforce'), '<a href="http://help.salesforce.com/apex/HTViewSolution?id=000006417" target="_blank">', '</a>') . '</span></div>'
				),
				array(
					'name' => 'member_status',
					'required' => false,
					'label' => __('Member Status', 'gravity-forms-salesforce')
				),
				array(
					'name' => 'emailOptOut',
					'required' => false,
					'label' => __('Opt Out of Emails', 'gravity-forms-salesforce')
				),
				array(
					'name' => 'faxOptOut',
					'required' => false,
					'label' => __('Opt Out of Faxes', 'gravity-forms-salesforce')
				),
				array(
					'name' => 'doNotCall',
					'required' => false,
					'label' => __('Do Not Call', 'gravity-forms-salesforce')
				),
				array(
					'name' => 'retURL',
					'required' => false,
					'label' => __('Return URL', 'gravity-forms-salesforce')
				),
				array(
					'name' => 'Priority',
					'required' => false,
					'label' => __('Priority', 'gravity-forms-salesforce')
				),
				array(
					'name' => 'recordType',
					'required' => false,
					'label' => __('Lead Record Type ID'),
					'desc' => __("The ID of the record type that should be created. You can get the ID by going to setup->customize -> lead -> record types"),
					'help' => __("Make sure that the profile assigned to the owner of the leads has the particular record type you're trying to assign visible to that profile. You can add it as follows: Setup -> manage users -> profiles -> edit profileName."),
				)
			);

			return $fields;

		}

		/**
		 * Get the default date format by guessing where you are.
		 * @since 3.1.2
		 * @return string d/m/Y or m/d/Y
		 */
		private function get_default_date_format() {

			switch( get_locale() ) {

				case 'en_US':
				case 'en_CA':
					$format = 'm/d/Y';
					break;

				default:
					$format = 'd/m/Y';
			}

			return $format;
		}

		/**
		 * Get the formatted date based on locale.
		 *
		 * @since 3.1.2
		 *
		 * @param string $value
		 * @param string $key
		 * @param array $additional_info Associative array with form, entry, field, feed data
		 *
		 */
		private function get_date_format( $value = '', $key = '', $additional_info = array() ) {

			/**
			 * Add a datetime instead of date.
			 * @link https://gist.github.com/zackkatz/ae3924779157261b80e3 See example
			 * @param boolean $use_datetime Whether to use DateTime instead of Date (default: false, except for KWS testing)
			 * @param string $key The Field Name (or API Name if a custom field) used in Salesforce
			 * @param array $additional_info Associative array with form, entry, field, feed data
			 */
			$use_datetime = apply_filters( 'gf_salesforce_use_datetime', ($key === 'KWS__Date_and_Time__c'), $key, $additional_info );

			$date_format = $this->get_addon_setting( 'date_format' );

			if( ! $date_format ) {
				$date_format = $this->get_default_date_format();
			}

			/**
			 * Modify how date formats are added
			 * @since 3.1.2
			 */
			$date_format = apply_filters('gravityforms/salesforce/date_format', $date_format );

			/**
			 * Modify how datetime formats are added
			 * @since 3.1.2
			 */
			$datetime_format = apply_filters('gravityforms/salesforce/datetime_format', 'Y-m-d H:i:s');

			/**
			 * Format the date in Salesforce-recognized format.
			 * These formats are US-style, even though Salesforce recommends `Y-m-d\'\T\'H:i:s`
			 * For some reason, that didn't work.
			 *
			 * @link https://success.salesforce.com/answers?id=90630000000gl7rAAA
			 */
			if( $use_datetime ) {
				$value = apply_filters( 'gf_salesforce_format_datetime', date( $datetime_format, strtotime( $value ) ), $key, $value, $additional_info );
			} else {
				$value = apply_filters( 'gf_salesforce_format_date', date( $date_format, strtotime( $value ) ), $key, $value, $additional_info );
			}

			return $value;
		}

		/**
		 * Export the entry on submit.
		 *
		 * @filter gf_salesforce_implode_glue Change how array values are passed to Salesforce. By default they're sent using `;` (semicolons) to separate the items. You may want to convert that to using `,` (commas) instead. The filter passes the existing "glue" and the name of the input (for example, `Multiple_Picklist__c`)
		 * @param  array $feed  Feed array
		 * @param  array $entry Entry array
		 * @param  array $form  Form array
		 */
		public function process_feed( $feed, $entry, $form ) {

			// The settings are only normally available on the admin page.
			// We want it available everywhere.
			$this->set_settings( $feed['meta'] );

			self::log_debug( sprintf("Opt-in condition met; adding entry {$entry["id"]} to %s", $this->_service_name) );

			try {

				foreach ($feed['meta'] as $key => $value) {
					// The field names have a trailing underscore for some reason.
					$trimmed_key = ltrim($key, '_');
					$feed['meta'][ $trimmed_key ] = $value;
					unset( $feed['meta'][$key] );
				}

				$temp_merge_vars = $this->get_merge_vars_from_entry($feed, $entry, $form);

				self::log_debug( sprintf("Temp Merge Vars: %s", print_r( $temp_merge_vars, true )) );

				$merge_vars = array();
				foreach($temp_merge_vars as $key => $value) {

					// Get the field ID for the current value
					$field_id = $feed['meta'][$key];

					// We need to specially format some data going to Salesforce
					// If it's a field ID, get the field data
					if(is_numeric($field_id) && !empty($value)) {

						$field = RGFormsModel::get_field($form, $field_id);
						$field_type = RGFormsModel::get_input_type($field);

						// Right now, we only have special cases for dates.
						switch ($field_type) {
							case 'date':

								$value = $this->get_date_format( $value, $key, compact('form', 'entry', 'field', 'feed') );

								break;
						}
					}

					if(is_array($value)) {

						// Filter the implode glue
						$glue = apply_filters('gf_salesforce_implode_glue', ';', $key);

						$value = implode($glue, $value);

						// Get rid of empty array values that would result in
						// List Item 1;;List Item 2 - that causes weird things to happen in
						// Salesforce
						$value = preg_replace('/'.preg_quote($glue).'+/', $glue, $data[$label]);

						unset($glue);

					} else {
						$value = GFCommon::trim_all($value);
					}

					// If the value is empty, don't send it.
					if(empty($value) && $value !== '0') {
						unset($merge_vars[$key]);
					} else {
						// Add the value to the data being sent
						$merge_vars[$key] = GFCommon::replace_variables($value, $form, $entry, false, false, false );
					}
				}

				// Process Boolean opt-out fields
				if(isset($merge_vars['emailOptOut'])) {
					$merge_vars['emailOptOut'] = !empty($merge_vars['emailOptOut']);
				}
				if(isset($merge_vars['faxOptOut'])) {
					$merge_vars['faxOptOut'] = !empty($merge_vars['faxOptOut']);
				}
				if(isset($merge_vars['doNotCall'])) {
					$merge_vars['doNotCall'] = !empty($merge_vars['doNotCall']);
				}

				// Add Address Line 2 to the street address
				if(!empty($merge_vars['street2'])) {
					$merge_vars['street'] .= "\n".$merge_vars['street2'];
				}

				// You can tap into the data and filter it.
				$merge_vars = apply_filters( 'gf_salesforce_push_data', $merge_vars, $form, $entry );

				// Remove any empty items
				$merge_vars = array_filter( $merge_vars );

				$return = $this->send_request($merge_vars);

				// If it returns false, there was an error.
				if(empty($return)) {
					self::log_error( sprintf("There was an error adding {$entry['id']} to Salesforce. Here's what was sent: %s", print_r($merge_vars, true)));
					return false;
				} else {
					// Otherwise, it was a success.
					self::log_debug( sprintf("Entry {$entry['id']} was added to Salesforce. Here's the available data:\n%s", print_r($return, true)));
					return true;
				}

			} catch(Exception $e) {
				// Otherwise, it was a success.
				self::log_error( sprintf("Error: %s", $e->getMessage()) );
			}

			return;
		}

		/**
		 * Is the API valid?
		 *
		 * Stores validity in `$_service_api_valid` and re-checks if the form has been submitted.
		 *
		 * @return boolean True: Org ID is valid; False: Org ID is invalid.
		 */
		public function is_valid_api() {

			// If the form hasn't just been saved, then use the stored value.
			// Otherwise, we should check again if the settings are valid.
			if(!is_null($this->_service_api_valid) && !$this->is_save_postback() ) {
				return $this->_service_api_valid;
			}

			// Transient key. Don't check over and over if not necessary.
			$key = 'oidv'.sha1($this->get_plugin_setting('org_id'));

			$cached = get_transient( $key );

			// If there was a response from the cache, and it's not a newly saved form.
			if($cached !== false && !$this->is_save_postback() ) {

				self::log_debug(sprintf('Org ID. was cached with key %s and value %s', $key, (boolean)!empty($cached)));

				$valid = $cached;

			} else {

				$valid = $this->send_request(array(), true);

				// Set the cache
				set_transient( $key, $valid, DAY_IN_SECONDS );
			}

			$this->_service_api_valid = $valid;

			return $this->_service_api_valid;
		}

		/**
		 * Send data to Salesforce using wp_remote_post()
		 *
		 * @filter gf_salesforce_salesforce_debug_email Disable debug emails (even if you have debugging enabled) by returning false.
		 * @filter gf_salesforce_salesforce_debug_email_address Modify the email address Salesforce sends debug information to
		 * @param  array  $post  Data to send to Salesforce
		 * @param  boolean $test Is this just testing the OID configuration and not actually sendinghelpful data?
		 * @return array|false         If the Salesforce server returns a non-standard code, an empty array is returned. If there is an error, `false` is returned. Otherwise, the `wp_remote_request` results array is returned.
		 */
		public function send_request($post, $test = false) {
			global $wp_version;

			// Get submission type: Lead or Case
			$type = $this->get_setting('type', 'Lead');

			// Web-to-Lead uses `oid` and Web to Case uses `orgid`
			switch($type) {
				case 'Case':
					$post['orgid'] = $this->get_plugin_setting('org_id');
					break;
				default:
				case 'Lead':
					$post['oid'] = $this->get_plugin_setting('org_id');
			}

			// We need an Org ID to post to Salesforce successfully.
			if(empty($post['oid']) && empty($post['orgid'])) {
				self::log_error( __("No Salesforce Org. ID was specified.", 'gravity-forms-salesforce') );
				return NULL;
			}

			// Debug is 0 by default.
			$post['debug'] = 0;

			// Is this a live request?
			if(!$test) {

				$post['debug_email'] = isset($post['debug_email']) ? $post['debug_email'] : $this->get_plugin_setting('debug_email');

				if( !empty($post['debug_email']) ) {

					// Don't want to pass this to Salesforce.
					unset($post['debug_email']);

					// Enable debug.
					$post['debug'] = 1;

					// The default debugging email is the WordPress admin email address,
					// unless overridden by passed args.
					$post['debugEmail'] = isset($post['debugEmail']) ? $post['debugEmail'] : get_option( 'admin_email' );

					// Salesforce will send debug emails to this email address.
					$post['debugEmail'] = apply_filters( 'gf_salesforce_salesforce_debug_email_address', $post['debugEmail']);

					// If the filter passes an invalid email address, then don't use it.
					$post['debugEmail'] = is_email( $post['debugEmail'] ) ? $post['debugEmail'] : NULL;

				}
			}
			unset($post['debugEmail']);

			// Redirect back to current page.
			$post['retURL'] = add_query_arg(array());

			// Set SSL verify to false because of server issues.
			$args = array(
				'body'      => $post,
				'headers'   => array(
					'user-agent' => 'Gravity Forms Salesforce Add-on plugin - WordPress/'.$wp_version.'; '.get_bloginfo('url'),
				),
				'sslverify' => false,
				'timeout'	=> MINUTE_IN_SECONDS
			);

			$args = apply_filters( 'gf_salesforce_request_args', $args, $test );

			// Use test/www subdomain based on whether this is a test or live
			$sub = apply_filters( 'gf_salesforce_request_subdomain', ($test ? 'test' : 'www'), $test );

			// Use (test|www) subdomain and WebTo(Lead|Case) based on setting
			$url = apply_filters( 'gf_salesforce_request_url', sprintf('https://%s.salesforce.com/servlet/servlet.WebTo%s?encoding=UTF-8', $sub, $type), $args);

			self::log_debug( sprintf("This is the data sent to %s (at %s:\n%s)", $this->_service_name, $url, print_r($args, true)) );

			// POST the data to Salesforce
			$result = wp_remote_post($url, $args);

			return $this->handle_response( $result );
		}

		/**
		 * Determine whether the response was valid or not.
		 * @param $result
		 *
		 * @return array|null NULL if there's an error. Array if
		 */
		private function handle_response( $result ) {

			// There was an error
			if(is_wp_error( $result )) {
				self::log_error( sprintf("There was an error adding the entry to Salesforce: %s", $result->get_error_message()));
				return array();
			}

			// Find out what the response code is
			$code = wp_remote_retrieve_response_code( $result );

			// Salesforce should ALWAYS return 200, even if there's an error.
			// Otherwise, their server may be down.
			if( intval( $code ) !== 200) {
				self::log_error( sprintf("The Salesforce server may be down, since it should always return '200'. The code it returned was: %s", $code));
				return array();
			}
			// If `is-processed` isn't set, then there's no error.
			elseif(!isset($result['headers']['is-processed'])) {
				self::log_debug("The `is-processed` header isn't set. This means there were no errors adding the entry.");
				return $result;
			}
			// If `is-processed` is "true", then there's no error.
			else if ($result['headers']['is-processed'] === "true") {
				self::log_debug("The `is-processed` header is set to 'true'. This means there were no errors adding the entry.");
				return $result;
			}
			// But if there's the word "Exception", there's an error.
			else if(strpos($result['headers']['is-processed'], 'Exception')) {
				self::log_error(sprintf(__('The `is-processed` header shows an Exception: %s. This means there was an error adding the entry.', 'gravity-forms-salesforce'), $result['headers']['is-processed']));
				return NULL;
			}

			// Don't know how you get here, but if you do, here's an array
			return array();
		}

	}

}