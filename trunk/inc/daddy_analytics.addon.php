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

	class KWSGFDaddyAnalyticsAddon extends KWSGFAddOn2_2 {
		protected $_version;
		protected $_min_gravityforms_version = "1.7";
		protected $_slug = "sf-loader-daddy-analytics";
		protected $_path = "gravity-forms-salesforce/inc/daddy_analytics.addon.php";
		protected $_full_path = __FILE__;
		protected $_title = "Gravity Forms: Salesforce Daddy Analytics Add-On";
		protected $_short_title = "Salesforce: Daddy Analytics";
		protected $_service_name = "Daddy Analytics";
		protected $_custom_field_placeholder = "Daddy Analytics Site ID";

		protected $_support_logging = false;
		protected $_has_form_settings_page = false;

		var $token;
		var $url;
		var $site_id;

		function __construct() {

			$this->_version = KWS_GF_Salesforce::version;

			parent::__construct();

			$this->token = $this->get_plugin_setting('daddy_analytics_token');
			$this->url = $this->get_plugin_setting('daddy_analytics_webtolead_url_id');
			$this->token_api_name = $this->get_plugin_setting('daddy_analytics_token_api_name');
			$this->url_api_name = $this->get_plugin_setting('daddy_analytics_webtolead_url_api_name');
			$this->site_id = $this->get_plugin_setting('daddy_analytics_site_id');

			$this->initialize();
		}

		/**
		 * Add hooks
		 *
		 * @since 3.1.2
		 */
		function initialize() {

			add_filter('gf_salesforce_loader_fields', array(&$this, 'add_field_to_salesforce_loader'));
			add_filter( 'gf_salesforce_lead_source_label', array(&$this, 'modify_lead_source_label'));


			// Add fields to form
			add_filter( 'gform_submit_button', array(&$this, 'add_fields_html'), 10, 2);

			// API Add-on
			add_filter( 'gf_salesforce_create_data', array(&$this, 'filter_api_merge_vars'), 10, 5);

			// Web-to-Lead Add-on
			add_filter( 'gf_salesforce_push_data', array(&$this, 'filter_web_to_lead_merge_vars'), 10, 3);

			// Add tracking script
			add_action('gform_preview_footer', array(&$this, 'daddy_analytics_javascript'));
			add_action('wp_footer', array(&$this, 'daddy_analytics_javascript'));
		}

		/**
		 * Add the Daddy Analytics URL and token to the Web-to-Lead data
		 *
		 * @param array $merge_vars
		 * @param array $form GF Form
		 * @param array $entry GF Entry
		 *
		 * @return array Modified $merge_vars
		 */
		function filter_web_to_lead_merge_vars($merge_vars, $form, $entry) {

			do_action('kwsgfwebtoleadaddon_log_debug', 'DA::filter_web_to_lead_merge_vars() - Starting adding DA data to merge vars.');

			// Get the $_POST data for the inserted fields
			$submitted_token = rgpost(esc_attr($this->token), true);
			$submitted_url = rgpost(esc_attr($this->url), true);

			// Add the data to be pushed.
			$merge_vars[esc_attr($this->url)] = $submitted_url;
			$merge_vars[esc_attr($this->token)] = $submitted_token;

			do_action('kwsgfwebtoleadaddon_log_debug', "DA::filter_api_merge_vars() - Added DA data to merge vars. Token: {$submitted_token} and Url: {$submitted_url}");

			return $merge_vars;
		}

		/**
		 * Add the Daddy Analytics URL and token to the Salesforce API data
		 *
		 * @param array $merge_vars
		 * @param array $form GF Form
		 * @param array $entry GF Entry
		 * @param array $feed Gravity Forms GFFeedAddon array
		 * @param SforcePartnerClient|SforceEnterpriseClient $api API object, as fetched from GFSalesforce::get_api()
		 *
		 * @return array Modified $merge_vars
		 */
		function filter_api_merge_vars($merge_vars, $form, $entry, $feed, $api ) {

			if(class_exists('GFSalesforce')) {
				GFSalesforce::log_debug('DA::filter_api_merge_vars() - Starting adding DA data to merge vars.');
			}

			// Daddy Analytics only works on Leads. If the object type isn't a lead,
			// return the original data.
			if(isset($feed['meta']) && isset($feed['meta']['contact_object_name'])) {
				if($feed['meta']['contact_object_name'] !== 'Lead') {
					GFSalesforce::log_debug('DA::filter_api_merge_vars() - Not a Lead object.');
					return $merge_vars;
				}
			}

			// Get the $_POST data for the inserted fields
			$submitted_token = rgpost(esc_attr($this->token), true);
			$submitted_url = rgpost(esc_attr($this->url), true);

			// Use defaults if not set yet, somehow.
			$url_api_name = empty($this->url_api_name) ? 'DaddyAnalytics__DA_Web_to_Lead_URL__c' : $this->url_api_name;
			$token_api_name = empty($this->token_api_name) ? 'DaddyAnalytics__DA_Token__c' : $this->token_api_name;

			// Add the data to be pushed.
			$merge_vars[esc_attr($url_api_name)] = $submitted_url;
			$merge_vars[esc_attr($token_api_name)] = $submitted_token;

			if(class_exists('GFSalesforce')) {
				if(empty($this->url_api_name)) {
					GFSalesforce::log_error("DA::filter_api_merge_vars() - Daddy Analytics Web-to-Lead API Name is not set.");
				}

				if(empty($this->token_api_name)) {
					GFSalesforce::log_error("DA::filter_api_merge_vars() - Daddy Analytics Token API Name is not set.");
				}

				GFSalesforce::log_debug("DA::filter_api_merge_vars() - Added DA data to merge vars. Token: {$submitted_token} and Url: {$submitted_url}");
			}

			return $merge_vars;
		}

		/**
		 * Add the hidden fields to forms with Salesforce integrations.
		 * @param  string      $html Submit button input
		 * @param  array      $form GF form array
		 */
		function add_fields_html( $html, $form ) {

			// If the form's been modified by KWSAddon::add_feed_status_to_form() or GFSalesforce::add_feed_status_to_form()
			// then it qualifies for DA integration
			if(!empty($form['feed-gravity-forms-salesforce']) || !empty($form['feed-sf-web-to-lead'])) {
				$html .= '<!-- Begin Daddy Analytics fields -->';
				$html .= '<input type="hidden" name="'.esc_attr($this->token).'" value="" />';
				$html .= '<input type="hidden" name="'.esc_attr($this->url).'" value="" />';
				$html .= '<!-- End Daddy Analytics fields -->';
			}

			return $html;
		}

		/**
		 * Add the logo for the Feeds page
		 * @return string IMG HTML tag
		 */
		public function get_service_icon() {
			return '<img src="'.plugins_url( 'assets/images/daddy_analytics/icon_50.png', KWS_GF_Salesforce::$file ).'" class="alignleft" style="margin:0 10px 10px 0" />';
		}

		public function get_service_favicon_path() {
			return plugins_url( 'assets/images/daddy_analytics/icon_16.png', KWS_GF_Salesforce::$file );
		}

		/**
		 * @inheritDoc
		 */
		public function plugin_settings_fields() {

			$fields = array();

			$fields[] = array(
				"title"  => sprintf(__("%s Configuration", 'gravity-forms-salesforce'), $this->get_service_name()),
				"description" => $this->get_ad_text(),
				"class" => 'clear',
				"fields" => array(
					array(
						"name"    => "daddy_analytics_token",
						"label"   => __("Token", "gravity-forms-salesforce"),
						"type"    => "text",
						"class"   => "medium code",
					),
					array(
						"name"    => "daddy_analytics_webtolead_url_id",
						"label"   => __("Web to Lead URL ID", "gravity-forms-salesforce"),
						"type"    => "text",
						"class"   => "medium code",
					),
					array(
						"name"    => "daddy_analytics_site_id",
						"label"   => __("Site ID", "gravity-forms-salesforce"),
						"type"    => "text",
						"class"   => "medium code",
					),
					array(
						"name"    => "add_js",
						"label"   => sprintf(__("Add Javascript? %sDisable you want to add tracking code yourself.%s", "gravity-forms-salesforce"), '<span class="howto">', '</span>'),
						"type"    => "checkbox",
						"dependency" => array(&$this, 'using_da'),
						"choices" => array(
							array(
								"name" => 'yes',
								"label" => 'Add tracking script to the site footer?',
								"value" => 1,
								'default_value' => 1,
							)
						),
					),
					array(
						'name' => 'Save',
						'type' => 'save',
						'messages' => array(
							'success' => __('Settings updated successfully.', 'gravity-forms-salesforce'),
							'error' => __('Settings failed to update successfully.', 'gravity-forms-salesforce')
						)
					)
				)
			);

			// If you need to define custom API names, you can do so here.
			// Sometimes Salesforce adds prefixes.
			if( apply_filters( 'gf_salesforce_custom_da_api_names', false ) ) {
				$fields[] = array(
					"title"  => __("Salesforce API Name Configuration", 'gravity-forms-salesforce'),
					"description" => wpautop(sprintf(__('Make sure the API names below match what is in Salesforce on your %sLead Fields%s page.', 'gravity-forms-salesforce'), '<a href="https://na11.salesforce.com/p/setup/layout/LayoutFieldList?setupid=LeadFields&amp;type=Lead">', '</a>')).wpautop(sprintf('<img src="%s" width="658" height="133" style="max-width:100%%;" alt="Salesforce API Fields" />', plugins_url( 'assets/images/daddy_analytics/Salesforce_API_Fields.png', KWS_GF_Salesforce::$file ))),
					"style" => 'padding-top: 0;',
					'fields' => array(
						array(
							"name"    => "daddy_analytics_webtolead_url_api_name",
							"label"   => __("Web-to-Lead URL API Name", "gravity-forms-salesforce"),
							"type"    => "text",
							"default_value" => 'DaddyAnalytics__DA_Web_to_Lead_URL__c',
							"class"   => "large code",
						),
						array(
							"name"    => "daddy_analytics_token_api_name",
							"label"   => __("Daddy Analytics Token API Name", "gravity-forms-salesforce"),
							"type"    => "text",
							"default_value" => 'DaddyAnalytics__DA_Token__c',
							"class"   => "large code",
						),
						array(
							'name' => 'Save',
							'type' => 'save',
							'messages' => array(
								'success' => __('Settings updated successfully.', 'gravity-forms-salesforce'),
								'error' => __('Settings failed to update successfully.', 'gravity-forms-salesforce')
							)
						)
					)
				);
			}

			return $fields;
		}

		/**
		 * @return string
		 */
		private function get_ad_term(){
			global $plugin_page;

			if($plugin_page === 'gf_settings'){
				$term = 'settings';
			}else{
				$term = 'form';
			}

			return $term;
		}

		private function get_ad_link( $content, $medium, $url = 'http://daddyanalytics.com/', $term = '', $source = 'G_forms', $campaign = 'KWS_GF_Salesforce' ){

			if( !$term ) {
				$term = $this->get_ad_term();
			}

			$link = $url . '?utm_source=%s&utm_medium=%s&utm_campaign=%s&utm_term=%s&utm_content=%s';

			return sprintf( $link, $source, $medium, $campaign, $term, $content  );

		}

		function get_ad_code( $type, $force = false, $id = null, $num = null){

			if( defined( 'KWSGFSF_HIDE_ADS' ) && SFWP2L_HIDE_ADS == true ){
				return; // hide ads due to constant
			}elseif( defined( 'KWSGFSF_HIDE_ADS' ) && SFWP2L_HIDE_ADS == false ){
				// show ads anyways
			}else{
				if( $this->using_da() && empty($force) )
					return; // hide ads as they've signed up
			}

			$assets_path = 'assets/images/daddy_analytics/';

			$ads = array(

				'banner-main' => array(
					array(
						'url'      => 'https://breadwinnerhq.com?utm_campaign=GF_BW_2&utm_source=G_forms&utm_medium=banner&utm_term=connect+salesforce+xero',
						'content'  => $assets_path . 'Breadwinner-connect-salesforce-xero.png'
					),
					array(
						'url'      => 'https://breadwinnerhq.com?utm_campaign=GF_BW_2&utm_source=G_forms&utm_medium=banner&utm_term=create+invoices+in+xero',
						'content'  => $assets_path . 'Breadwinner-create-invoices-in-xero.png'
					),
					array(
						'url'      => 'https://breadwinnerhq.com?utm_campaign=GF_BW_2&utm_source=G_forms&utm_medium=banner&utm_term=never+miss+again',
						'content'  => $assets_path . 'Breadwinner-never-miss-again.png'
					),
					array(
						'url'     => 'https://daddydanalytics.com?utm_campaign=GF_DA_2&utm_source=G_forms&utm_medium=banner&utm_term=track+google+adwords',
					    'content' => $assets_path . 'Track-Google-Adwords_banner.png'
					),
					array(
						'url'     => 'https://daddydanalytics.com?utm_campaign=GF_DA_2&utm_source=G_forms&utm_medium=banner&utm_term=track+lead+source',
					    'content' => $assets_path . 'Track-Lead-Source_banner.png'
					),
				),
				'text' => array(
					array(
						'id'      => 'da1_7',
						'content' => sprintf( __( 'Daddy Analytics allows you to track your leads from their original source, such as AdWords, Google Organic, Social Media, or other blogs. With that information you can get your true marketing ROI, as each Opportunity is attributed to the marketing activity that brought in the Lead. %sWatch a video of Daddy Analytics%s', 'gravity-forms-salesforce' ), '<p class="submit"><a class="button button-secondary" href="%link1%" target="_blank">', '</a></p>' )
					),
					array(
						'id'      => 'da1_8',
						'cta'     => 'Sign up Now',
					    'content' => sprintf( __( 'Daddy Analytics allows you to track your leads from their original source, such as AdWords, Google Organic, Social Media, or other blogs. With that information you can get your true marketing ROI, as each Opportunity is attributed to the marketing activity that brought in the Lead. %sSign up for a free trial of Daddy Analytics%s', 'gravity-forms-salesforce' ), '<p class="submit"><a class="button button-secondary" href="%link2%" target="_blank">', '</a></p>' )
					),
				),

			);


			$num = mt_rand( 1, count( $ads[ $type ] ) ) - 1;

			return $ads[ $type ][ $num ];
		}

		/**
		 * Get a banner ad for Daddy Analytics
		 * @param  boolean     $force Force showing, even if DA is configured?
		 * @return string             HTML of ad
		 */
		function get_ad_banner($force = false) {

			$banner = '';

			if( $ad = $this->get_ad_code('banner-main', $force) ){

				$link = $ad['url'];

				// Margin-top is to make the transparency look better
				$banner = '<div><div class="hr-divider"></div>';
				$banner .= '<a href="'.$link.'" target="_blank"><img src="'.plugins_url( $ad['content'], KWS_GF_Salesforce::$file ).'" width="586" height="147" style="';
				$banner .= 'width:auto; height:auto; max-width:100%; max-height:180px;'; // Scale down when window is smaller; don't get too big when wide window.
				$banner .= '" /></a></div>';
			}

			return $banner;
		}

		/**
		 * Get a text ad for Daddy Analytics
		 * @param  boolean     $force Force showing, even if DA is configured?
		 * @return string             HTML of ad
		 */
		function get_ad_text($force = false) {

			if( $ad = $this->get_ad_code('text', $force) ){

				$link1 = $this->get_ad_link( $ad['id'], 'text', 'https://daddydanalytics.com' );
				$link2 = $this->get_ad_link( $ad['id'], 'text', 'https://daddyanalytics.com' );

				$content = str_replace( array('%link1%','%link2%'), array($link1,$link2), $ad['content'] );

			}else{
				$content = '<div class="updated inline widefat">'.wpautop(sprintf(__('Thank you for using %sDaddy Analytics%s!', 'gravity-forms-salesforce'), '<a href="http://daddyanalytics.com/" target="_blank">', '</a>' )).'</div>';
			}

			return wpautop( $content );
		}

		/**
		 * Add DA helper text to the Lead Source label, if DA is enabled.
		 *
		 * @param  string      $label Existing label
		 * @return string             Modified label, if using DA. Unmodified if not.
		 */
		function modify_lead_source_label($label = '') {

			if($this->using_da()) {
				$label .= '<span class="howto">'.sprintf(__('%sLeave the Lead Source field blank%s for Daddy Analytics. Daddy Analytics will populate the Lead Source field with the web source of the Lead (such as Organic - Google, Paid - Bing, or Google Adwords).', 'gravity-forms-salesforce'), '<strong>', '</strong>').'</div>';
			}

			return $label;

		}

		function add_field_to_salesforce_loader($fields) {

			if($this->using_da()) { return $fields; }

			$banner = $this->get_ad_banner();

			$content = $this->get_ad_text();

			$da_field = array(
				'type'  => 'html',
				'id'	=> 'daddyanalytics_ad',
				'title' => __('Daddy Analytics', 'gravity-forms-salesforce'),
				'value' => $banner.$content,
			);

			// Insert the field before the "save" button field
			$fields[] = $da_field;

			return $fields;
		}

		function using_da(){
			return !empty($this->token) && !empty($this->url) && !empty($this->site_id);
		}

		protected function valid_api_message() {
			return '<span class="gf_keystatus_valid_text">'.sprintf(__("%s Active: Your Daddy Analytics configuration is valid.", 'gravity-forms-salesforce'), '<i class="fa fa-check gf_keystatus_valid"></i>').'</span>';
		}

		protected function invalid_api_message() {

			$tofind = sprintf(__('Please login to your Daddy Analytics account to obtain your account information.', 'gravity-forms-salesforce'));
			if(empty($this->site_id)) {
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

			$this->_service_api_valid = $this->using_da();

			return $this->_service_api_valid;

		}

		/**
		 * Generate the javascript code for DA
		 *
		 * @param  boolean     $echo True: echo the javascript; False: just return the JS
		 * @return $output HTML output
		 */
		public function daddy_analytics_javascript($echo = true){

			// If DA isn't configured, don't output anything.
			if(!$this->using_da()) { return; }

			$protocol = is_ssl() ? 'https://' : 'http://';
			$site_id = esc_attr($this->site_id);
			$token = esc_attr($this->token);
			$url = esc_attr($this->url);

$output = <<<EOD

	<!-- Begin Daddy Analytics code provided by Gravity Forms Salesforce Plugin -->
	<script src="{$protocol}cdn.daddyanalytics.com/w2/daddy.js" type="text/javascript"></script>
	<script type="text/javascript">
		var da_data = daddy_init('{ "da_token" : "{$token}", "da_url" : "{$url}" }');
		var clicky_custom = {session: {DaddyAnalytics: da_data}};
	</script>
	<script src="{$protocol}hello.staticstuff.net/w/__stats.js" type="text/javascript"></script>
	<script type="text/javascript">try{ clicky.init( "{$site_id}" ); }catch(e){}</script>
	<!-- End Daddy Analytics code provided by Gravity Forms Salesforce Plugin -->

EOD;

			echo $output;
			return $output;
		}
	}

}