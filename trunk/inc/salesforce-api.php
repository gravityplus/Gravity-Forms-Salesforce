<?php
/**
 * Gravity Forms Salesforce API Add-On
 *
 * Loaded by the salesforce.php core plugin file.
 *
 */

use OAuth\OAuth2\Service\Salesforce;
use OAuth\Common\Http\Client\CurlClient;
use OAuth\Common\Storage\WordPressMemory as WordPressMemory;
use OAuth\Common\Consumer\Credentials;
use OAuth\Common\Http\Uri\Uri;

register_activation_hook( KWS_GF_Salesforce::$file, array("GFSalesforce", "add_permissions"));
register_activation_hook( KWS_GF_Salesforce::$file, array("GFSalesforce", "force_refresh_transients"));
register_deactivation_hook( KWS_GF_Salesforce::$file, array("GFSalesforce", "force_refresh_transients"));

class GFSalesforce {
	const GF_SF_SANDBOX = false;

	public static $instance;
	public static $foreign_keys = array();
	public $result = array();

	private static $name = "Salesforce: API";
	private static $api = '';
	private static $path = "gravity-forms-salesforce/salesforce-api.php";
	private static $url = "http://formplugin.com";
	private static $slug = "gravity-forms-salesforce";
	private static $version;
	private static $min_gravityforms_version = "1.3.9";
	private static $cache_time = 86400; // 24 hours

	/**
	 * Default settings
	 * @var array
	 */
	private static $settings = array(
		'notify' => false,
		"notifyemail" => '',
		'cache_time' => 86400,
	);

	function __construct() {

		self::$version = KWS_GF_Salesforce::version;

		add_action('init', array(&$this, 'init'));

		// New fields at entries export
		add_filter( 'gform_export_fields', array( 'GFSalesforce', 'export_entries_add_fields' ), 10, 1 );
		add_filter( 'gform_export_field_value', array( 'GFSalesforce', 'export_entries_add_values' ), 999, 4);

	}

	/**
	 * Singleton instance of our class
	 *
	 * @since  3.1
	 * @return GFSalesforce
	 */
	public static function Instance()
	{
		if (self::$instance === null) {
			self::$instance = new GFSalesforce();

			self::log_debug(__METHOD__ . ': init singleton instance');
		} else {
			self::log_debug(__METHOD__ . ': refresh singleton instance');
		}

		return self::$instance;
	}

	/**
	 * Plugin starting point. Will load appropriate files
	 * @return void
	 */
	public function init(){
		global $pagenow;

		if($pagenow == 'plugins.php' || defined('RG_CURRENT_PAGE') && RG_CURRENT_PAGE == "plugins.php"){

			add_filter('plugin_action_links', array('GFSalesforce', 'settings_link'), 10, 2 );

		}

		if(!self::is_gravityforms_supported()){
		   return;
		}

		self::$settings = get_option( "gf_salesforce_settings", self::$settings );

		self::include_files();

		if(is_admin()){

			// Process the OAuth chain
			$this->processSalesforceResponse();

			//creates a new Settings page on Gravity Forms' settings screen
			if(self::has_access("gravityforms_salesforce")){
				RGForms::add_settings_page( array(
					'name' => "sf-loader-api",
					'tab_label' => 'Salesforce: API',
					'handler' => array("GFSalesforce", "settings_page"),
					'icon_path' => self::get_base_url() . "/assets/images/salesforce-128.png",
				), array("GFSalesforce", "settings_page"), self::get_base_url() . "/assets/images/salesforce-128.png");
			}

			self::refresh_transients();

		}

		// Since 3.0 - add feed status to form array
		add_filter( 'gform_pre_render', array('GFSalesforce', 'add_feed_status_to_form'), 10, 2 );

		// since 2.6.0 - send entry to Salesforce if updated in the admin
		add_action( 'gform_after_update_entry', array( 'GFSalesforce', 'manual_export' ), 10, 2);
		add_action( 'admin_init', array( 'GFSalesforce', 'manual_export' ), 10, 2);

		//integrating with Members plugin
		if(function_exists('members_get_capabilities'))
			add_filter('members_get_capabilities', array("GFSalesforce", "members_get_capabilities"));

		//creates the subnav left menu
		add_filter("gform_addon_navigation", array('GFSalesforce', 'create_menu'));

		if(self::is_salesforce_page()){

			//enqueueing sack for AJAX requests
			wp_enqueue_script(array("sack"));

			// since 2.5.2
			add_action( 'admin_enqueue_scripts', array( 'GFSalesforce', 'add_custom_script') );

			//loading Gravity Forms tooltips
			require_once(GFCommon::get_base_path() . "/tooltips.php");
			add_filter('gform_tooltips', array('GFSalesforce', 'tooltips'));

			//runs the setup when version changes
			self::setup();

		 } else if(in_array(RG_CURRENT_PAGE, array("admin-ajax.php"))){

			add_action('wp_ajax_rg_update_feed_active', array('GFSalesforce', 'update_feed_active'));
			add_action('wp_ajax_gf_select_salesforce_form', array('GFSalesforce', 'select_salesforce_form'));
			//since 2.5.2
			add_action('wp_ajax_get_options_as_fields', array( 'GFSalesforce', 'get_options_as_fields'));

			add_action('wp_ajax_rg_update_feed_sort', array('GFSalesforce', 'update_feed_sort'));
			add_action('wp_ajax_nopriv_rg_update_feed_sort', array('GFSalesforce', 'update_feed_sort'));

		} else{
			 //handling post submission.
			add_action("gform_after_submission", array('GFSalesforce', 'export'), 10, 2);
		}

		add_filter("gform_logging_supported", array('GFSalesforce', "set_logging_supported"));

		add_action( 'gform_entry_info', array('GFSalesforce', 'entry_info_send_to_salesforce_checkbox'), 99, 2);
		add_filter( 'gform_entrydetail_update_button', array('GFSalesforce', 'entry_info_send_to_salesforce_button'), 999, 1);

	}

	/**
	 * Modify the form array to add slug feed activity to the form itself.
	 *
	 * Adds [feed-{$slug}] key to the form
	 * @param  array      $form Form array
	 * @param  boolean      $ajax Is ajax or not
	 */
	static function add_feed_status_to_form($form, $ajax) {

		if(self::has_feed($form['id'])) {
			$form['feed-'.self::$slug] = true;
		}

		return $form;
	}

	static function set_logging_supported($plugins) {
		$plugins[self::$slug] = self::$name;
		return $plugins;
	}

	static function force_refresh_transients() {
		self::refresh_transients(true);
	}

	static private function refresh_transients($force = false)
	{
		global $wpdb;

		if($force || (isset($_GET['refresh']) && current_user_can('manage_options') && $_GET['refresh'] === 'transients')) {
			$wpdb->query("DELETE FROM {$wpdb->options} WHERE `option_name` LIKE '%_transient_sfgf_%' OR `option_name` LIKE '%_transient_timeout_sfgf_%'");
		}
	}

	/**
	 * Get the files needed to process OAuth, Gravity Forms
	 *
	 * @return void
	 */
	private static function include_files() {

		//loading data class
		require_once(self::get_base_path() . "inc/data.php");
		require_once(self::get_base_path() . "inc/edit-form.php");

		if ( !class_exists('OAuth\Common\autoloader') ) {
			include_once KWS_GF_Salesforce::$plugin_dir_path.'lib/PHPoAuthLib/src/OAuth/bootstrap.php';
		}

		if ( !class_exists('OAuth\ServiceFactory') ) {
			include_once KWS_GF_Salesforce::$plugin_dir_path.'lib/PHPoAuthLib/src/OAuth/ServiceFactory.php';
		}

		if ( !class_exists('WordpressMemory') ) {
			include_once KWS_GF_Salesforce::$plugin_dir_path.'lib/WordPressMemory.php';
		}
	}

	/**
	 * Handle the OAuth response from Salesforce
	 *
	 * @return void
	 */
	public function processSalesforceResponse() {
		if (!empty($_GET['code']) && !empty($_GET['display']) && $_GET['display'] === 'page') {

			$settings = self::$settings;

			$salesforceService = self::getSalesforceService();

			// The token's not the same, nor may the endpoint be.
			delete_transient('salesforce_endpoint_token');

			try {
				self::log_debug( sprintf('processSalesforceResponse: Received access code (%s). Now fetching Access Token.', esc_attr($_GET['code']) ) );

				// This was a callback request from Salesforce, get the token
				$salesforceService->requestAccessToken($_GET['code']);

				unset($settings['error']);

			} catch (Exception $e) {

				self::log_error('processSalesforceResponse: Clearing token, since there was an error.');

				self::clearToken();

				$settings['error'] = self::processErrorMessage($e->getMessage());

				self::log_error('processSalesforceResponse: '.$settings['error'] .' [Raw error message: '. $e->getMessage() .']' );
			}

			update_option("gf_salesforce_settings", $settings);
			self::$settings = $settings;

			// Whether the response worked or not
			wp_redirect( self::link_to_settings( false ) );

			exit();
		}
	}

	static public function processErrorMessage($value='') {

		switch(true) {
			case strpos($value, 'invalid_grant'):
				$message = "This authorization request has expired, or (less likely) the IP is restricted or invalid login hours.";
				break;
			case strpos($value, 'redirect_uri_mismatch'):
				$message = "The redirect_uri is a mismatch with remote access application definition. See `getSalesforceService()` method in the code.";
				break;
			case strpos($value, 'inactive_user'):
				$message = "The user is inactive";
				break;
			case strpos($value, 'inactive_org'):
				$message = "The organization is locked, closed, or suspended";
				break;
			case strpos($value, 'invalid_client_id'):
				$message = "client ID invalid";
				break;
			case 'invalid_request':
				$message = "HTTPS required or must use HTTP POST or secret type not supported";
				break;
			case strpos($value, 'invalid_client_credentials'):
			case strpos($value, 'invalid_client'):
				$message = "Client consumer secret invalid";
				break;
		}

		return $message;
	}

	/**
	 * Get Salesforce OAuth service.
	 *
	 * Uses the https://katz.co callback URL because of the developer token being used. If you want to use your own, filter
	 * the credentials using the filters provided.
	 *
	 * @filter `gf_salesforce_service_credentials` `OAuth\Common\Consumer\Credentials` object.
	 * @filter `gf_salesforce_service_client` `OAuth\Common\Http\Client` object. Default: `CurlClient`
	 * @filter `gf_salesforce_service_storage` `OAuth\Common\Storage` object. Default: `WordPressMemory`
	 * @filter  `gf_salesforce_service_scopes` Modify the access the plugin has. Needs `api` and `refresh_token`.
	 * @filter  `gf_salesforce_service_is_sandbox` Modify whether to use the Sandbox. Returns `true` or `false`.
	 * @return OAuth\OAuth2\Service\Salesforce
	 */
	static private function getSalesforceService() {

		$credentials = new Credentials(
			'3MVG9y6x0357HledYGqazOFNBPUUAVtD9yRYNGk0TAknMvch_rM8aZxzmtd6T7lcBTB4mPQMJ9VZsBqF8kfBX', // Consumer Token
			'7911901779233377323', // Consumer Secret,
			'https://katz.co/oauth/' // Callback URL
		);

		$client = new CurlClient;
		$storage = new WordPressMemory;

		// We want API access for the plugin, also the ability to refresh the token.
		// {@link http://www.salesforce.com/us/developer/docs/api_rest/Content/intro_understanding_refresh_token_oauth.htm}
		$scopes = array('api', 'refresh_token');

		// Modify your service here.
		$credentials = apply_filters( 'gf_salesforce_service_credentials', $credentials );
		$client = apply_filters( 'gf_salesforce_service_client', $client );
		$storage = apply_filters( 'gf_salesforce_service_storage', $storage );
		$scopes = apply_filters( 'gf_salesforce_service_scopes', $scopes );

		$salesforceService = new Salesforce($credentials, $client, $storage, $scopes);

		// Is this a sandbox connection?
		$salesforceService->setSandbox( (bool) apply_filters( 'gf_salesforce_service_is_sandbox', self::GF_SF_SANDBOX ) );

		return $salesforceService;
	}

	static function getEndpoint($auth = '') {

		// If there's a cached endpoint, use it.
		if( $cached = get_transient('salesforce_endpoint_token') ) {
			return $cached;
		}

		if(empty($auth)) {
			$auth = self::getAccessToken();
		}

		if(empty($auth)) {
			self::log_error('getEndpoint(): No OAuth token available');
			return false;
		}

		$id = self::getTokenParam('id');
		$url = add_query_arg(array('oauth_token' => $auth), $id );

		$request = wp_remote_get( $url, array( 'sslverify' => false, 'timeout' => 60 ));

		if(!is_wp_error( $request ) && (int)$request['response']['code'] === 200) {
			$response = $request['body'];
			$response = json_decode($response);
			if(is_object($response)) {

				$enterprise = apply_filters('gf_salesforce_enterprise', false);

				// Set the version to the SOAP API version (SforceBaseClient->version)
				if($enterprise) {
					$output = str_replace('{version}', '27.0', $response->urls->enterprise);
				} else {
					$output = str_replace('{version}', '27.0', $response->urls->partner);
				}

				// Cache the endpoint for a week
				set_transient('salesforce_endpoint_token', $output, WEEK_IN_SECONDS);

				return $output;
			}
		} else if(is_wp_error( $request )) {
			self::log_debug( sprintf( "Could not get the endpoint. Here is the error data: %s\nand the request was made to:%s", print_r($request->get_error_messages(), true), $url ) );
		} else {
			self::log_debug( sprintf( "Could not get the endpoint. Here is the response: %s\nand the request was made to %s", print_r($request, true), $url) );
		}

		return false;
	}

	static private function getToken() {
		$Storage = new WordPressMemory;
		if($Storage->hasAccessToken('Salesforce')) {
			return $Storage->retrieveAccessToken('Salesforce');
		}
		return false;
	}

	static private function clearToken() {
		$Storage = new WordPressMemory;
		self::log_debug('Token was cleared.');
		$Storage->clearToken('Salesforce');

		//Delete the refresh token if exists
		delete_option( 'gf_salesforce_refreshtoken' );
		self::log_debug('Refresh Token cleared');
	}

	/**
	 * Generate a refresh token from the original OAuth token
	 *
	 * @link  http://help.salesforce.com/HTViewHelpDoc?id=remoteaccess_oauth_refresh_token_flow.htm
	 * @link  http://www.salesforce.com/us/developer/docs/api_rest/Content/intro_understanding_refresh_token_oauth.htm
	 * @return OAuth\Common\Token      New, refreshed token
	 */
	static private function refreshToken() {

		$Token = self::getToken();

		$salesforceService = self::getSalesforceService();

		self::log_debug('refreshToken(): Refreshing access token.');

		try {

			$Token = $salesforceService->refreshAccessToken($Token);

		} catch(Exception $e) {

			self::log_error('refreshToken(): Refreshing access token failed. Here is the error type: '. get_class($e) );

			return null;
		}

		self::log_debug( sprintf('refreshToken(): Access token refreshed: %s', $Token->getAccessToken() ) );

		return $Token;
	}

	static private function getRefreshToken() {
		if($TokenClass = self::getToken()) {
			return $TokenClass->getRefreshToken();
		}
		return false;
	}

	static private function getAccessToken() {
		if($TokenClass = self::getToken()) {
			return $TokenClass->getAccessToken();
		}
		return false;
	}

	static private function getTokenParams() {
		if($TokenClass = self::getToken()) {
			return $params = $TokenClass->getExtraParams();
		}
		return false;
	}

	static private function getTokenParam($key = '') {
		if(!empty($key) && $params = self::getTokenParams()) {
			if(array_key_exists($key, $params)) {
				return $params[$key];
			}
		}
		return false;
	}


	//Returns true if the current page is one of Gravity Forms pages. Returns false if not
	public static function is_gravity_page($page = array()){
		if(!class_exists('RGForms')) { return false; }
		$current_page = trim(strtolower(RGForms::get("page")));
		if(empty($page)) {
			$gf_pages = array("gf_edit_forms","gf_new_form","gf_entries","gf_settings","gf_export","gf_help");
		} else {
			$gf_pages = is_array($page) ? $page : array($page);
		}

		return in_array($current_page, $gf_pages);
	}

	public static function update_feed_active(){
		check_ajax_referer('rg_update_feed_active','rg_update_feed_active');
		$id = $_POST["feed_id"];
		$feed = GFSalesforceData::get_feed($id);
		GFSalesforceData::update_feed($id, $feed["form_id"], $_POST["is_active"], $feed["meta"]);
	}

	/**
	 * Update the feed sort order
	 *
	 * @since  3.1
	 * @return void
	 */
	public static function update_feed_sort(){
		if( empty( $_POST['sort'] ) || !isset( $_POST['nonce'] ))
				// ! wp_verify_nonce( $_POST['nonce'], 'rg_update_feed_sort' ) )
		{
			exit(false);
		}

		GFSalesforceData::update_feed_order($_POST['sort']);
	}

	//--------------   Automatic upgrade ---------------------------------------------------

	public static function settings_link( $links, $file ) {
		static $this_plugin;
		if( ! $this_plugin ) $this_plugin = self::get_base_url();
		if ( $file == $this_plugin ) {
			$settings_link = '<a href="' . admin_url( 'admin.php?page=gf_salesforce' ) . '" title="' . __('Select the Gravity Form you would like to integrate with Salesforce. Contacts generated by this form will be automatically added to your Salesforce account.', 'gravity-forms-salesforce') . '">' . __('Feeds', 'gravity-forms-salesforce') . '</a>';
			array_unshift( $links, $settings_link ); // before other links
			$settings_link = '<a href="' . self::link_to_settings() . '" title="' . __('Configure your Salesforce settings.', 'gravity-forms-salesforce') . '">' . __('Settings', 'gravity-forms-salesforce') . '</a>';
			array_unshift( $links, $settings_link ); // before other links
		}
		return $links;
	}


	//Returns true if the current page is an Feed pages. Returns false if not
	private static function is_salesforce_page(){
		global $plugin_page; $current_page = '';
		$salesforce_pages = array("gf_salesforce");

		if(isset($_GET['page'])) {
			$current_page = trim(strtolower($_GET["page"]));
		}

		return (in_array($plugin_page, $salesforce_pages) || in_array($current_page, $salesforce_pages));
	}


	//Creates or updates database tables. Will only run when version changes
	private static function setup(){

		if(get_option("gf_salesforce_version") != self::$version)
			GFSalesforceData::update_table();

		update_option("gf_salesforce_version", self::$version);
	}

	//Adds feed tooltips to the list of tooltips
	public static function tooltips($tooltips){
		$salesforce_tooltips = array(
			"salesforce_contact_list" => "<h6>" . __("Salesforce Object", "gravity-forms-salesforce") . "</h6>" . __("Select the Salesforce object you would like to add your contacts to.", "gravity-forms-salesforce"),
			"salesforce_gravity_form" => "<h6>" . __("Gravity Form", "gravity-forms-salesforce") . "</h6>" . __("Select the Gravity Form you would like to integrate with Salesforce. Contacts generated by this form will be automatically added to your Salesforce account.", "gravity-forms-salesforce"),
			"salesforce_map_fields" => "<h6>" . __("Map Standard Fields", "gravity-forms-salesforce") . "</h6>" . __("Associate your Salesforce fields to the appropriate Gravity Form fields by selecting. <a href='http://www.salesforce.com/us/developer/docs/api/Content/field_types.htm'>Learn about the Field Types</a> in Salesforce.", "gravity-forms-salesforce"),
			"salesforce_optin_condition" => "<h6>" . __("Opt-In Condition", "gravity-forms-salesforce") . "</h6>" . __("When the opt-in condition is enabled, form submissions will only be exported to Salesforce when the condition is met. When disabled all form submissions will be exported.", "gravity-forms-salesforce"),
			"salesforce_manual_export" => "<h6>" . __("Manual Export", "gravity-forms-salesforce") . "</h6>" . __("If you don't want all entries sent to Salesforce, but only specific, approved entries, check this box. To send an entry to Salesforce, you go to Entries, choose the entry you would like to send to Salesforce, and then click the 'Send to Salesforce' button.", "gravity-forms-salesforce"),
		);
		return array_merge($tooltips, $salesforce_tooltips);
	}

	//Creates Salesforce left nav menu under Forms
	public static function create_menu($menus){

		// Adding submenu if user has access
		$permission = self::has_access("gravityforms_salesforce");
		if(!empty($permission))
			$menus[] = array("name" => "gf_salesforce", "label" => __("Salesforce", "gravity-forms-salesforce"), "callback" =>  array("GFSalesforce", "salesforce_page"), "permission" => $permission);

		return $menus;
	}

	/**
	 * Is there a setting to email when there's an error? If so, returns email.
	 *
	 * @return mixed     If setting exists: Email; if not: false.
	 */
	public static function is_notify_on_error() {
		$email = trim(rtrim(self::$settings['notifyemail']));
		if(!empty($email) && is_email($email)) {
			return $email;
		} else {
			return false;
		}
	}

	public static function settings_page(){


		if(isset($_POST["uninstall"])){
			check_admin_referer("uninstall", "gf_salesforce_uninstall");
			self::uninstall();
			?>
			<div class="updated fade" style="padding:20px;"><?php esc_attr( sprintf( __("Gravity Forms Salesforce Add-On has been successfully uninstalled. It can be re-activated from the %splugins page%s.", "gravity-forms-salesforce"), "<a href='plugins.php'>","</a>") ); ?></div>
			<?php
			return;
		}
		else if(isset($_POST["gf_salesforce_submit"])){
			check_admin_referer("update", "gf_salesforce_update");

			// If the new transient time is less than the old, we can assume they want it cleared out.
			if(floatval($_POST["gf_salesforce_cache_time"]) < floatval(self::$settings['cache_time'])) {
				self::refresh_transients(true);
			}
			$settings = array(
				"notifyemail" => trim(rtrim(esc_html($_POST["gf_salesforce_notifyemail"]))),
				'cache_time' => floatval($_POST["gf_salesforce_cache_time"])
			);
			update_option("gf_salesforce_settings", $settings);
		}
		else{
			$settings = get_option("gf_salesforce_settings");
		}

		// If user revoked access manually, clear the token.
		if(wp_verify_nonce(@$_GET['cleartoken'], 'cleartoken')) {
			self::clearToken();
			$token = false;
			unset($_GET['cleartoken']);
		} else {
			$token = self::getAccessToken();
		}

		$settings = wp_parse_args( $settings, self::$settings );

		$api = self::get_api($settings);

		$message = '';

		if($api && $token && self::api_is_valid($api)){
			$message = sprintf(__("Valid configuration. Now go %sconfigure form integration with Salesforce%s!", "gravity-forms-salesforce"), '<a href="'.admin_url('admin.php?page=gf_salesforce').'">', '</a>');
			$class = "updated valid_credentials";
			$valid = true;
		} else if(!$api) {
			$message = sprintf(__('%sYour Salesforce connection isn&#8217;t working properly. Please log in again below.%s For more information, please install the %sGravity Forms Logging Tool%s and try again.%s', 'gravity-forms-salesforce'), '<h3 style="margin-top:1em;">', '</h3><p>', "<a href='http://www.gravityhelp.com/downloads/#Gravity_Forms_Logging_Tool' target='_blank'>", '</a>', '</p>');
			$valid = false;
			$class = "error invalid_credentials";
		} else {
			$valid = false;
		}

		if($message) {
			$message = str_replace('Api', 'API', $message);
			?>
			<div id="message" class="inline <?php echo $class ?> widefat"><?php echo wpautop($message); ?></div>
			<?php
		}
		?>
		<form method="post" action="<?php echo remove_query_arg('refresh'); ?>">
			<?php wp_nonce_field("update", "gf_salesforce_update") ?>
			<h3><span><img class="alignleft" style="margin:0 10px 10px 0" alt="" src="<?php echo self::get_base_url() . "/assets/images/salesforce-256x256.png"; ?>" width="84" /> <?php esc_html_e("Salesforce Account Information", "gravity-forms-salesforce") ?></span></h3>
			<h4 class="gf_settings_subgroup_title"><?php esc_html_e('Salesforce API', 'gravity-forms-salesforce'); ?></h4>
			<table class="form-table">
				<tr>
					<th scope="row"><label><?php esc_html_e("Salesforce Account", "gravity-forms-salesforce"); ?></label> </th>
					<td>
					<?php

					$auth_button_output = '';

					// Is there an OAuth token stored?
					if(!empty($valid)) {
						$params = self::getTokenParams();
						$auth_button_output .= '<span class="gf_keystatus_valid_text">';
						$auth_button_output .= '<i class="fa fa-check gf_keystatus_valid"></i> ';
						$auth_button_output .= sprintf(__('Authorized connection to <code>%s</code> on %s'), str_replace('https://', '', $params['instance_url']), date_i18n('F d, Y H:i:m \G\M\T', substr($params['issued_at'], 0, 10)));
						$auth_button_output .= '</span>';
						$url = wp_nonce_url(add_query_arg(array('cleartoken' => true)), 'cleartoken', 'cleartoken');
						$auth_button_output .= "<p><a href='{$url}' class='button button-secondary'>Revoke Access</a></p>";
					}
					// Was there an error?
					else {

						if(!empty($settings['error'])) {
							$auth_button_output .= '<div class="error inline clear">'.wpautop( $settings['error'] ).'</div>';
						}

						// If not, we need to authorize.
						$salesforceService = self::getSalesforceService();

						$url = $salesforceService->getAuthorizationUri(array(
							'grant_type' => 'authorization_code',
							'response_type' => 'code',
							'display' => 'page',
							'scope' => 'api refresh_token',
							'state' => self::link_to_settings( false )
						));

						$auth_button_output .= sprintf("<p><a href='%s' class='button button-primary button-hero'>%s</a></p>", $url, __('Login with Salesforce!', 'gravity-forms-salesforce'));
					}

					echo $auth_button_output;

					?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="gf_salesforce_notifyemail"><?php esc_html_e("Notify by Email on Errors", "gravity-forms-salesforce"); ?></label></th>
					<td>
						<input type="text" id="gf_salesforce_notifyemail" name="gf_salesforce_notifyemail" size="30" value="<?php echo empty($settings["notifyemail"]) ? '' : esc_attr($settings["notifyemail"]); ?>"/>
						<span class="howto"><?php esc_html_e('An email will be sent to this email address if an entry is not properly added to Salesforce. Leave blank to disable.', 'gravity-forms-salesforce'); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="gf_salesforce_cache_time"><?php esc_html_e("Remote Cache Time", "gravity-forms-salesforce"); ?></label><span class="howto"><?php esc_html_e("This is an advanced setting. You likely won't need to change this.", "gravity-forms-salesforce"); ?></span></th>
					<td>

						<select name="gf_salesforce_cache_time" id="gf_salesforce_cache_time">
							<option value="60" <?php selected($settings["cache_time"] == '60', true); ?>><?php esc_html_e('One Minute (for testing only!)', 'gravity-forms-salesforce'); ?></option>
							<option value="3600" <?php selected($settings["cache_time"] == '3600', true); ?>><?php esc_html_e('One Hour', 'gravity-forms-salesforce'); ?></option>
							<option value="21600" <?php selected($settings["cache_time"] == '21600', true); ?>><?php esc_html_e('Six Hours', 'gravity-forms-salesforce'); ?></option>
							<option value="43200" <?php selected($settings["cache_time"] == '43200', true); ?>><?php esc_html_e('12 Hours', 'gravity-forms-salesforce'); ?></option>
							<option value="86400" <?php selected($settings["cache_time"] == '86400', true); ?>><?php esc_html_e('1 Day', 'gravity-forms-salesforce'); ?></option>
							<option value="172800" <?php selected($settings["cache_time"] == '172800', true); ?>><?php esc_html_e('2 Days', 'gravity-forms-salesforce'); ?></option>
							<option value="259200" <?php selected($settings["cache_time"] == '259200', true); ?>><?php esc_html_e('3 Days', 'gravity-forms-salesforce'); ?></option>
							<option value="432000" <?php selected($settings["cache_time"] == '432000', true); ?>><?php esc_html_e('5 Days', 'gravity-forms-salesforce'); ?></option>
							<option value="604800" <?php selected(empty($settings["cache_time"]) || $settings["cache_time"] == '604800', true); ?>><?php esc_html_e('1 Week', 'gravity-forms-salesforce'); ?></option>
						</select>
						<span class="howto"><?php esc_html_e('How long should form and field data be stored? This affects how often remote picklists will be checked for the Live Remote Field Mapping feature.', 'gravity-forms-salesforce'); ?></span>
						<span class="howto"><?php printf( esc_html__("%sRefresh now%s.", "gravity-forms-salesforce"), '<a href="'.add_query_arg('refresh', 'transients').'">','</a>'); ?></span>
					</td>
				</tr>
				<tr>
					<td colspan="2" ><input type="submit" name="gf_salesforce_submit" class="button-primary" value="<?php esc_attr_e("Save Settings", "gravity-forms-salesforce") ?>" /></td>
				</tr>
			</table>
			<div>

			</div>
		</form>

		<form action="" method="post">
			<?php wp_nonce_field("uninstall", "gf_salesforce_uninstall") ?>
			<?php if(GFCommon::current_user_can_any("gravityforms_salesforce_uninstall")){ ?>
				<div class="hr-divider"></div>

				<h3><?php esc_html_e("Uninstall Salesforce Add-On", "gravity-forms-salesforce") ?></h3>
				<div class="delete-alert"><?php esc_html_e("Warning! This operation deletes ALL Salesforce Feeds.", "gravity-forms-salesforce") ?>
					<?php
					$uninstall_button = '<input type="submit" name="uninstall" value="' . __("Uninstall Salesforce Add-On", "gravity-forms-salesforce") . '" class="button" onclick="return confirm(\'' . __("Warning! ALL Salesforce Feeds will be deleted. This cannot be undone. \'OK\' to delete, \'Cancel\' to stop", "gravity-forms-salesforce") . '\');"/>';
					echo apply_filters("gform_salesforce_uninstall_button", $uninstall_button);
					?>
				</div>
			<?php } ?>
		</form>
		<?php
	}

	/**
	 * Display the list or edit page based on the current view
	 * @return void
	 */
	public static function salesforce_page(){
		$view = isset($_GET["view"]) ? $_GET["view"] : '';
		if($view == "edit") {
			self::edit_page($_GET["id"]);
		} else {
			self::list_page();
		}
	}

	//Displays the Salesforce feeds list page
	private static function list_page(){

		if(isset($_POST["action"]) && $_POST["action"] == "delete"){
			check_admin_referer("list_action", "gf_salesforce_list");

			$id = absint($_POST["action_argument"]);
			GFSalesforceData::delete_feed($id);
			?>
			<div class="updated fade" style="margin:10px 0;"><p><?php esc_html_e("Feed deleted.", "gravity-forms-salesforce") ?></p></div>
			<?php
		}
		else if (!empty($_POST["bulk_action"])){
			check_admin_referer("list_action", "gf_salesforce_list");
			$selected_feeds = $_POST["feed"];
			if(is_array($selected_feeds)){
				foreach($selected_feeds as $feed_id)
					GFSalesforceData::delete_feed($feed_id);
			}
			?>
			<div class="updated fade" style="margin:10px 0;"><p><?php esc_html_e("Feeds deleted.", "gravity-forms-salesforce") ?></p></div>
			<?php
		}

		$api = self::get_api();
		?>
		<style type="text/css">
			.user-list tr {
				cursor: move;
			}
			.user-list tr td a {
				cursor: pointer;
			}
			.user-list tr:nth-child(even) {
				background-color: #f5f5f5;
			}
		</style>
		<div class="wrap">
			<img alt="<?php esc_attr_e("Salesforce.com Feeds", "gravity-forms-salesforce") ?>" src="<?php echo self::get_base_url()?>/assets/images/salesforce-256x256.png" style="float:left; margin:0 7px 10px 0;" width="64" />
			<h2><?php esc_html_e("Salesforce.com Feeds", "gravity-forms-salesforce"); ?>
			<a class="button add-new-h2" href="admin.php?page=gf_salesforce&amp;view=edit&amp;id=0"><?php esc_html_e("Add New", "gravity-forms-salesforce") ?></a>
			</h2>

			<?php
				if(!self::api_is_valid($api)){
			?>
			<div class="error" id="message" style="margin-top:20px;">
				<h3><?php esc_html_e('Salesforce Error', "gravity-forms-salesforce");?></h3>
				<p><?php echo empty($api) ? sprintf( __("To get started, please configure your %sSalesforce Settings%s.", "gravity-forms-salesforce"), '<a href="'.self::link_to_settings().'">', "</a>") : $api; ?></p>
			</div>
			<?php
				} else {
			?>
			<div class="updated" id="message" style="margin-top:20px;">
				<p><?php printf( esc_html__('Do you like this free plugin? %sPlease review it on WordPress.org%s!', 'gravity-forms-salesforce'), '<a href="http://katz.si/gfsfrate">', '</a>') ; ?></p>
			</div>
			<?php } ?>
			<div class="clear"></div>
			<ul class="subsubsub" style="margin-top:0;">
				<li><a href="<?php echo self::link_to_settings(); ?>">Salesforce Settings</a> |</li>
				<li><a href="<?php echo admin_url('admin.php?page=gf_salesforce'); ?>" class="current">Salesforce Feeds</a></li>
			</ul>

			<form id="feed_form" method="post">
				<?php wp_nonce_field('list_action', 'gf_salesforce_list') ?>
				<input type="hidden" id="action" name="action"/>
				<input type="hidden" id="action_argument" name="action_argument"/>

				<div class="tablenav">
					<div class="alignleft actions" style="padding:8px 0 7px; 0">
						<label class="hidden" for="bulk_action"><?php esc_html_e("Bulk action", "gravity-forms-salesforce") ?></label>
						<select name="bulk_action" id="bulk_action">
							<option value=''> <?php esc_html_e("Bulk action", "gravity-forms-salesforce") ?> </option>
							<option value='delete'><?php esc_html_e("Delete", "gravity-forms-salesforce") ?></option>
						</select>
						<?php
						echo '<input type="submit" class="button" value="' . __("Apply", "gravity-forms-salesforce") . '" onclick="if( jQuery(\'#bulk_action\').val() == \'delete\' && !confirm(\'' . __("Delete selected feeds? ", "gravity-forms-salesforce") . __("\'Cancel\' to stop, \'OK\' to delete.", "gravity-forms-salesforce") .'\')) { return false; } return true;"/>';
						?>
					</div>
				</div>
				<table class="widefat fixed sort" cellspacing="0">
					<thead>
						<tr>
							<th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th>
							<th scope="col" id="active" class="manage-column check-column"></th>
							<th scope="col" class="manage-column"><?php esc_html_e("Form", "gravity-forms-salesforce") ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e("Salesforce Object", "gravity-forms-salesforce") ?></th>
						</tr>
					</thead>

					<tfoot>
						<tr>
							<th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th>
							<th scope="col" id="active" class="manage-column check-column"></th>
							<th scope="col" class="manage-column"><?php esc_html_e("Form", "gravity-forms-salesforce") ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e("Salesforce Object", "gravity-forms-salesforce") ?></th>
						</tr>
					</tfoot>

					<tbody class="list:user user-list">
						<?php

						$settings = GFSalesforceData::get_feeds();
						if(is_array($settings) && !empty($settings)){

							foreach($settings as $setting){
								?>
								<tr class='author-self status-inherit' data-id="<?php echo $setting['id'] ?>">
									<th scope="row" class="check-column"><input type="checkbox" name="feed[]" value="<?php echo $setting["id"] ?>"/></th>
									<td><img src="<?php echo self::get_base_url() ?>/assets/images/active<?php echo intval($setting["is_active"]) ?>.png" alt="<?php echo $setting["is_active"] ? __("Active", "gravity-forms-salesforce") : __("Inactive", "gravity-forms-salesforce");?>" title="<?php echo $setting["is_active"] ? __("Active", "gravity-forms-salesforce") : __("Inactive", "gravity-forms-salesforce");?>" onclick="ToggleActive(this); " /></td>
									<td class="column-title">
										<a href="admin.php?page=gf_salesforce&amp;view=edit&amp;id=<?php echo $setting["id"] ?>" title="<?php esc_attr_e("Edit", "gravity-forms-salesforce") ?>"><?php echo esc_html( $setting["form_title"] ); ?></a>
										<div class="row-actions">
											<span class="edit">
											<a title="Edit this setting" href="admin.php?page=gf_salesforce&amp;view=edit&amp;id=<?php echo $setting["id"] ?>" title="<?php esc_attr_e("Edit", "gravity-forms-salesforce") ?>"><?php esc_html_e("Edit", "gravity-forms-salesforce") ?></a>
											|
											</span>

											<span class="edit">
											<a title="<?php esc_html_e("Delete", "gravity-forms-salesforce") ?>" href="javascript: if(confirm('<?php echo esc_js(__("Delete this feed? 'Cancel' to stop, 'OK' to delete.", "gravity-forms-salesforce")); ?>')){ DeleteSetting(<?php echo $setting["id"] ?>);}"><?php esc_html_e("Delete", "gravity-forms-salesforce")?></a>
											|
											</span>

											<span class="edit">
											<a title="<?php esc_html_e("Edit Form", "gravity-forms-salesforce") ?>" href="<?php echo add_query_arg(array('page' => 'gf_edit_forms', 'id' => $setting['form_id']), admin_url('admin.php')); ?>"><?php esc_html_e("Edit Form", "gravity-forms-salesforce")?></a>
											|
											</span>

											<span class="edit">
											<a title="<?php esc_html_e("Preview Form", "gravity-forms-salesforce") ?>" href="<?php echo add_query_arg(array('gf_page' => 'preview', 'id' => $setting['form_id']), site_url()); ?>"><?php esc_html_e("Preview Form", "gravity-forms-salesforce")?></a>
											</span>
										</div>
									</td>
									<td class="column-name" style="width:40%"><p><?php echo esc_html($setting["meta"]["contact_object_name"]); ?></p></td>
								</tr>
								<?php
							}
						}
						else {
							if(self::api_is_valid($api)){
								?>
								<tr>
									<td colspan="4" style="padding:20px;">
										<?php printf( esc_html__("You don't have any Salesforce feeds configured. Let's go %screate one%s!", "gravity-forms-salesforce"), '<a href="'.admin_url('admin.php?page=gf_salesforce&view=edit&id=0').'">', "</a>"); ?>
									</td>
								</tr>
								<?php
							}
							else{
								?>
								<tr>
									<td colspan="4" style="padding:20px;">
										<?php printf( esc_html__("To get started, please configure your %sSalesforce Settings%s.", "gravity-forms-salesforce"), '<a href="'.self::link_to_settings().'">', "</a>"); ?>
									</td>
								</tr>
								<?php
							}
						}
						?>
					</tbody>
				</table>
			</form>
		</div>
		<script type="text/javascript">
			function DeleteSetting(id){
				jQuery("#action_argument").val(id);
				jQuery("#action").val("delete");
				jQuery("#feed_form")[0].submit();
			}
			function ToggleActive(img) {
				var feed_id;
				var is_active = img.src.indexOf("active1.png") >=0
				var $img = jQuery(img);

				if(is_active){
					img.src = img.src.replace("active1.png", "active0.png");
					$img.attr('title','<?php _e("Inactive", "gravity-forms-salesforce") ?>').attr('alt', '<?php _e("Inactive", "gravity-forms-salesforce") ?>');
				}
				else{
					img.src = img.src.replace("active0.png", "active1.png");
					$img.attr('title','<?php _e("Active", "gravity-forms-salesforce") ?>').attr('alt', '<?php _e("Active", "gravity-forms-salesforce") ?>');
				}

				if(feed_id = $img.closest('tr').attr('data-id')) {
					var mysack = new sack("<?php echo admin_url("admin-ajax.php")?>" );
					mysack.execute = 1;
					mysack.method = 'POST';
					mysack.setVar( "action", "rg_update_feed_active" );
					mysack.setVar( "rg_update_feed_active", "<?php echo wp_create_nonce("rg_update_feed_active") ?>" );
					mysack.setVar( "feed_id", feed_id );
					mysack.setVar( "is_active", is_active ? 0 : 1 );
					mysack.encVar( "cookie", document.cookie, false );
					mysack.onError = function() { alert('<?php _e("Ajax error while updating feed", "gravity-forms-salesforce" ) ?>' )};
					mysack.runAJAX();
					return true;
				}

				return false;
			}
		</script>
		<?php
	}

	static private function api_is_valid($api) {

		if($api === false || is_string($api) || !empty($api->lastError)) {
			self::log_error('api_is_valid(): $api is string or has an error: '.print_r($api, true));
			return false;
		} elseif( !is_a($api, 'SforcePartnerClient') && !is_a($api, 'SforceEnterpriseClient')) {
			self::log_error('api_is_valid(): $api is not SforcePartnerClient or SforceEnterpriseClient: '.print_r($api, true));
			return false;
		} elseif(!method_exists($api, 'getLastResponseHeaders') || (!is_null($api->getLastResponseHeaders()) && !preg_match('/200\sOK/ism', $api->getLastResponseHeaders())) ) {
			self::log_error('api_is_valid(): !method_exists(getLastResponseHeaders) || 200 OK $api->getLastResponseHeaders() '.print_r($api, true));
			return false;
		}

		self::log_debug('api_is_valid(): valid api');

		return true;
	}

	public static function get_api($settings = array(), $after_refresh = false){

		// If it's already set, use it.
		if(!empty(self::$api)) {
			self::log_debug("get_api(): API connection already set.");
			return self::$api;
		}

		if(!is_array($settings) || empty($settings)) {
			$settings = self::$settings;
			if(!is_array($settings) || empty($settings)) {
				$settings = get_option("gf_salesforce_settings");
			}
		}

		// If the settings aren't set...return false
		if(!is_array($settings) || empty($settings)) {
			self::log_error("get_api(): Settings not set, so we can't get the Salesforce connection.");
			return false;
		}

		$libpath = plugin_dir_path(KWS_GF_Salesforce::$file).'lib/Force.com-Toolkit-for-PHP/soapclient/';

		$enterprise = apply_filters('gf_salesforce_enterprise', false, $settings);

		try {
			//This is instantiating the service used for the sfdc api
			if($enterprise) {
				if(!class_exists("SforceEnterpriseClient")) {
					require_once $libpath.'SforceEnterpriseClient.php';
				}

				$mySforceConnection = new SforceEnterpriseClient();
				$_wsdl = 'enterprise.wsdl.xml';
			}
			else {
				if(!class_exists("SforcePartnerClient")) {
					require_once $libpath.'SforcePartnerClient.php';
				}

				$mySforceConnection = new SforcePartnerClient();
				$_wsdl = 'partner.wsdl.xml';
			}

			if(self::GF_SF_SANDBOX && file_exists($libpath . 'sandbox.wsdl.xml')) {
				$_wsdl = 'sandbox.wsdl.xml';
			}

			/**
			* Create a connection using SforceBaseClient::createConnection().
			*
			* @param string $wsdl   Salesforce.com Partner WSDL
			* @param object $proxy  (optional) proxy settings with properties host, port,
			*                       login and password
			* @param array $soap_options (optional) Additional options to send to the
			*                       SoapClient constructor. @see
			*                       http://php.net/manual/en/soapclient.soapclient.php
			*/
			$mySforceConnection->createConnection(
				apply_filters('gf_salesforce_wsdl', $libpath.$_wsdl),
				apply_filters('gf_salesforce_proxy', NULL),
				apply_filters('gf_salesforce_soap_options', array())
			);

			/**
			 * Set access using OAuth instead of Basic
			 * @link  https://developer.salesforce.com/blogs/developer-relations/2011/03/oauth-and-the-soap-api.html
			 */
			$token = self::getAccessToken();

			if( empty( $token ) ) {
				self::log_debug("get_api(): The access token is empty - app is not yet authenticated");
				return false;
			}

			$mySforceConnection->setSessionHeader($token);
			$mySforceConnection->setEndpoint(self::getEndpoint($token));

			$mySforceConnection = apply_filters('gf_salesforce_connection', $mySforceConnection);

			//let's force some action through the connection to make sure is up and running!
			$timestamp = $mySforceConnection->getServerTimestamp();

			self::$api = $mySforceConnection;

			return $mySforceConnection;

		} catch( Exception $e ) {

			self::log_error( sprintf("get_api(): There was an error getting the connection to Salesforce. The error message was: %s\nHere's the exception: %s", $e->getMessage(),  print_r( $e, true) ) );

			// The token has expired. We need to refresh the token.
			if( isset($e->faultcode) && in_array( $e->faultcode, array( 'sf:INVALID_SESSION_ID', 'UNKNOWN_EXCEPTION' ) ) && !$after_refresh ) {

				try{

					self::log_error("get_api(): The access token has expired; fetching a refresh token.");

					// Refresh the token
					$refreshToken = self::refreshToken();

					// If the token fetch failed, return false.
					if(empty($refreshToken)) {

						self::log_error('get_api(): The refreshToken call has failed. This may be due to your Salesforce Instance "Refresh Token Policy". If it is not set to "Refresh token is valid until revoked" (the default), that can cause problems.');

						self::log_error('See http://www.salesforce.com/us/developer/docs/packagingGuide/Content/connected_app_manage_edit.htm#oauth_policies for more information.');

						return false;
					}

				} catch(Exception $e) {

					self::log_error( sprintf("get_api(): There was an error refreshing the access token to Salesforce. The error message was: %s\nHere's the exception: %s", $e->getMessage(),  print_r($e, true)));

					return false;
				}

				self::log_error("get_api(): The refresh token has been fetched; now trying get_api() again.");

				// And try one more time.
				return self::get_api($settings, true);

			}

			self::log_error( sprintf( "get_api(): Token was not refreshed for this error: %s ", $e->getMessage() ));

			return isset($e->faultstring) ? $e->faultstring : false;
		}
	}

	/**
	 * Writes an error message to the Gravity Forms log. Requires the Gravity Forms logging Add-On.
	 */
	static public function log_error($message){
		if (class_exists("GFLogging")) {
			GFLogging::include_logger();
			GFLogging::log_message(self::$slug, $message, KLogger::ERROR);
		}
	}

	/**
	 * Writes an error message to the Gravity Forms log. Requires the Gravity Forms logging Add-On.
	 */
	static public function log_debug($message){
		if (class_exists("GFLogging")) {
			GFLogging::include_logger();
			GFLogging::log_message(self::$slug, $message, KLogger::DEBUG);
		} else {
			error_log ( $message );
		}
	}

	public static function r($content, $die = false) {
		echo '<pre>';
		print_r($content);
		echo '</pre>';
		if($die) { exit(); }
		return;
	}

	public static function getField($objectType = 'account', $field_name = '') {

		// Cache the field to save lookups.
		// Sha1 is to ensure length is correct.
		$field = get_site_transient('sfgf_'.sha1('lists_'.$objectType.'_'.$field_name));
		if($field && !is_wp_error($field) && !(current_user_can('manage_options') && (isset($_REQUEST['refresh']) || isset($_REQUEST['cache'])))) { return $field; }

		$fields = self::getFieldsForObject($objectType);

		foreach($fields as $field) {
			if($field['tag'] === $field_name) {
				set_site_transient('sfgf_'.sha1('lists_'.$objectType.'_'.$field_name), $field, self::$settings['cache_time']);
				return $field;
			}
		}
	}

	/**
	 * Get an array of fields for an object type
	 * @param  string      $objectType Type of object, for example "Lead" or "Contact"
	 * @param  string      $fieldType  The type of field you're trying to return, for example: "picklist" or "string"
	 * @return array|false                  Array if fields exist; false if failed to fetch.
	 */
	public static function getFieldsForObject($objectType = 'account', $fieldType = null) {

		// This is passed by $_POST; let's just make sure it's sanitized
		$objectType = esc_attr( $objectType );

		$lists = maybe_unserialize(get_site_transient('sfgf_lists_fields_'.$objectType));
		if($lists && !empty($lists) && is_array($lists) && (!isset($_REQUEST['refresh']) || (isset($_REQUEST['refresh']) && $_REQUEST['refresh'] !== 'lists'))) {
			self::log_debug('getFields: fields have been cached.');
			foreach($lists as $key => $list) {
				// If you only want one type of field, and it's not that type, keep going
				if(!empty($fieldType)) {
					if(
						(is_string($fieldType) && $list['type'] !== $fieldType) ||
						(is_array($fieldType) && !in_array($list['type'], $fieldType))
					) {
						unset($lists[$key]);
					}
				}
			}
			return $lists;

		} else if(!empty($_REQUEST['refresh']) && $_REQUEST['refresh'] === 'lists') {

			// We don't want to fetch multiple times, so we unset the refresh parameter.
			unset($_REQUEST['refresh']);

		}

		$api = self::get_api();

		if( !self::api_is_valid($api)) {
			self::log_error('Can\'t get fields because API isn\'t valid.');
			return false;
		}

		self::log_debug('getFields: fields are being fetched for $objectType '.$objectType);

		$accountdescribe = $api->describeSObject($objectType);

		if(!is_object($accountdescribe) || !isset($accountdescribe->fields)) {

			self::log_debug('getFields: There was an error with the describeSObject call: '.print_r($accountdescribe, true));

			return false;
		}

		$lists = $field_details = array();
		foreach($accountdescribe->fields as $Field) {

			if(!is_object($Field)) { continue; }

			$field_details = array(
				'name' => esc_js($Field->label),
				'req' => (!empty($Field->createable) && empty($Field->nillable) && empty($Field->defaultedOnCreate)),
				'tag' => esc_js($Field->name),
				'type' => $Field->type,
				'length' => $Field->length,
				'picklistValues' => isset($Field->picklistValues) ? $Field->picklistValues : null
			);

			$all_lists[] = $field_details;

			// If you only want one type of field, and it's not that type, keep going
			if(!empty($fieldType)) {
				if(
					(is_string($fieldType) && $Field->type !== $fieldType) ||
					(is_array($fieldType) && !in_array($Field->type, $fieldType))
				) {
					continue;
				}
			}

			$lists[] = $field_details;
		}

		asort($lists);

		set_site_transient('sfgf_lists_fields_'.$objectType, $all_lists, self::$settings['cache_time']);

		return $lists;
	}

	/**
	 * Get a list of available objects from Salesforce
	 * @return array      array of object types with `sObject->name` as the key
	 */
	public static function getObjectTypes() {

		$lists = get_site_transient('sfgf_objects');

		if($lists && (!isset($_REQUEST['refresh']) || (isset($_REQUEST['refresh']) && $_REQUEST['refresh'] !== 'lists'))) {
			return $lists;
		}

		$api = self::get_api();

		if(!self::api_is_valid($api)) {
			self::log_error('getObjectTypes(): API is invalid. '.print_r($api, true));
			return false;
		}

		try {
			$objects = $api->describeGlobal();

			if(empty($objects) || !is_object($objects) || !isset($objects->sobjects)) { return false; }

			$lists = array();
			foreach ($objects->sobjects as $object) {
				if(!is_object($object) || empty($object->createable)) { continue; }
				$lists[$object->name] = esc_html( $object->label );
			}

			asort($lists);

			set_site_transient('sfgf_objects', $lists, self::$settings['cache_time']);

			return $lists;

		} catch (Exception $e) {
			self::log_error( sprintf( "getObjectTypes():\n\nException While Getting Object Types\n Here's the error:\n%s", $e->getMessage() ) );
			return false;
		}

	}

	public static function link_to_settings( $escaped = true ) {

		$url = admin_url('admin.php?page=gf_settings&subview=sf-loader-api');

		return $escaped ? esc_attr( $url ) : $url;
	}

	private static function edit_page(){

		if(!function_exists('gform_tooltip')) {
			require_once(GFCommon::get_base_path() . "/tooltips.php");
		}

		?>
		<style type="text/css">
			label span.howto { cursor: default; }
			.salesforce_col_heading{padding-bottom:2px; border-bottom: 1px solid #ccc; font-weight:bold; width:50%;}
			#salesforce_field_list table { width: 60%; min-width: 500px; border-collapse: collapse; margin-top: 1em; }
			.salesforce_field_cell { padding: 6px 17px 0 0; margin-right:15px; min-width: 150px;}
			.gfield_required{color:red;}

			.feeds_validation_error{ background-color:#FFDFDF;}
			.feeds_validation_error td{ margin-top:4px; margin-bottom:6px; padding-top:6px; padding-bottom:6px; border-top:1px dotted #C89797; border-bottom:1px dotted #C89797}

			.left_header{float:left; width:200px; padding-right: 20px;}
			#salesforce_field_list .left_header { margin-top: 1em; }
			.margin_vertical_10{margin: 20px 0;}
			#gf_salesforce_list { margin-left:220px; padding-top: 1px }
			#salesforce_doubleoptin_warning{padding-left: 5px; padding-bottom:4px; font-size: 10px;}
		</style>
		<script type="text/javascript">
			var form = Array();
		</script>
		<div class="wrap">
			<img alt="<?php _e("Salesforce Feeds", "gravity-forms-salesforce") ?>" src="<?php echo self::get_base_url()?>/assets/images/salesforce-256x256.png" style="float:left; margin:15px 7px 10px 0;" width="84"/>
			<h2><?php _e("Salesforce Feeds", "gravity-forms-salesforce"); ?></h2>
			<ul class="subsubsub">
				<li><a href="<?php echo self::link_to_settings(); ?>">Salesforce Settings</a> |</li>
				<li><a href="<?php echo admin_url('admin.php?page=gf_salesforce'); ?>">Salesforce Feeds</a></li>
			</ul>
		<div class="clear"></div>
		<?php
		//getting Salesforce API

		$api = self::get_api();

		//ensures valid credentials were entered in the settings page
		if(!self::api_is_valid($api)) {
			?>
			<div class="error" id="message" style="margin-top:20px;"><?php echo wpautop(sprintf(__("We are unable to login to Salesforce with the provided username and API key. Please make sure they are valid in the %sSettings Page%s", "gravity-forms-salesforce"), "<a href='".self::link_to_settings()."'>", "</a>")); ?></div>
			<?php
			return;
		}

		//getting setting id (0 when creating a new one)
		$id = !empty($_POST["salesforce_setting_id"]) ? absint($_POST["salesforce_setting_id"]) : absint($_GET["id"]);
		$config = empty($id) ? array("meta" => array(), "is_active" => true) : GFSalesforceData::get_feed($id);

		//getting merge vars
		$merge_vars = array();

		//updating meta information
		if(isset($_POST["gf_salesforce_submit"])){
			$objectType = $list_names = array();
			$list = stripslashes($_POST["gf_salesforce_list"]);
			$config["meta"]["contact_object_name"] = $list;
			$config["form_id"] = absint($_POST["gf_salesforce_form"]);

			$is_valid = true;

			$merge_vars = (array)self::getFieldsForObject($_POST['gf_salesforce_list']);

			$field_map = array();

			foreach($merge_vars as $var){
				$field_name = "salesforce_map_field_" . $var['tag'];
				$mapped_field = isset($_POST[$field_name]) ? stripslashes($_POST[$field_name]) : '';
				if(!empty($mapped_field)){
					$field_map[$var['tag']] = $mapped_field;
				}
				else{
					unset($field_map[$var['tag']]);
					if(!empty($var["req"])) {
						$is_valid = false;
					}
				}
				unset($_POST["{$field_name}"]);
			}


			$config["meta"]["field_map"] = $field_map;
			$config["meta"]["optin_enabled"] = !empty($_POST["salesforce_optin_enable"]) ? true : false;
			$config["meta"]["optin_field_id"] = $config["meta"]["optin_enabled"] ? isset($_POST["salesforce_optin_field_id"]) ? $_POST["salesforce_optin_field_id"] : '' : "";
			$config["meta"]["optin_operator"] = $config["meta"]["optin_enabled"] ? isset($_POST["salesforce_optin_operator"]) ? $_POST["salesforce_optin_operator"] : '' : "";
			$config["meta"]["optin_value"] = $config["meta"]["optin_enabled"] ? $_POST["salesforce_optin_value"] : "";

			$config['meta']['manual_export'] = !empty($_POST['salesforce_manual_export']) ? true : false; // since 2.6.0
			$config["meta"]["primary_field"] = !empty( $_POST['salesforce_primary_field'] ) ? $_POST['salesforce_primary_field'] : ''; // since 2.5.2

			if($is_valid){
				$id = GFSalesforceData::update_feed($id, $config["form_id"], $config["is_active"], $config["meta"]);
				?>
				<div id="message" class="updated fade" style="margin-top:10px;"><p><?php echo sprintf(__("Feed Updated. %sback to list%s", "gravity-forms-salesforce"), "<a href='?page=gf_salesforce'>", "</a>") ?></p>
					<input type="hidden" name="salesforce_setting_id" value="<?php echo $id ?>"/>
				</div>
				<?php
			}
			else{
				?>
				<div class="error" style="padding:6px"><?php echo __("Feed could not be updated. Please enter all required information below.", "gravity-forms-salesforce") ?></div>
				<?php
			}
		}
?>
		<form method="post" action="<?php echo remove_query_arg('refresh'); ?>">
			<input type="hidden" name="salesforce_setting_id" value="<?php echo $id ?>"/>
			<div class="margin_vertical_10">
				<h2><?php _e('1. Select the Object to create when a form is submitted.', "gravity-forms-salesforce"); ?></h2>
				<label for="gf_salesforce_list" class="left_header"><?php _e("Salesforce Object", "gravity-forms-salesforce"); ?> <?php gform_tooltip("salesforce_contact_list") ?> <span class="howto"><?php _e(sprintf("%sRefresh objects & fields%s", '<a href="'.add_query_arg('refresh', 'lists').'">','</a>'), "gravity-forms-salesforce"); ?></span></label>

<?php
				//getting all contact lists
				$lists = self::getObjectTypes();

				if(!$lists) {
					echo __("Could not load Salesforce contact lists.", "gravity-forms-salesforce");
					echo isset($api->errorMessage) ? ' <br/>'.$api->errorMessage : '';
				} else {
?>
					<select id="gf_salesforce_list" name="gf_salesforce_list" onchange="SelectList(jQuery(this).val()); SelectForm(jQuery(this).val(), jQuery('#gf_salesforce_form').val());">
						<option value=""><?php _e("Select a Salesforce Object", "gravity-forms-salesforce"); ?></option>
					<?php
					foreach ($lists as $name => $label){
						?>
						<option value="<?php echo esc_html($name) ?>" <?php selected(isset($config["meta"]["contact_object_name"]) && ($name === $config["meta"]["contact_object_name"])); ?>><?php echo esc_html($label) ?></option>
						<?php
					}
					?>
				  </select>

				  <script type="text/javascript">
					if(jQuery('#lists_loading').length && jQuery('#gf_salesforce_list').length) {
						jQuery('#lists_loading').fadeOut(function() { jQuery('#gf_salesforce_list').fadeIn(); });
					 } else if(jQuery('#gf_salesforce_list').length) {
						jQuery('#gf_salesforce_list').show();
					 }
				 </script>
				<?php
				}
				?>
				<div class="clear"></div>
			</div>
			<?php flush(); ?>
			<div id="salesforce_form_container" class="margin_vertical_10" <?php echo empty($config["meta"]["contact_object_name"]) ? "style='display:none;'" : "" ?>>
				<h2><?php _e('2. Select the form to tap into.', "gravity-forms-salesforce"); ?></h2>
				<?php
				$forms = RGFormsModel::get_forms();

				if(isset($config["form_id"])) {
					foreach($forms as $form) {
						if($form->id == $config["form_id"]) {
							echo '<h3 style="margin:0; padding:0 0 1em 1.75em; font-weight:normal;">'.sprintf(__('(Currently linked with %s)', "gravity-forms-salesforce"), $form->title).'</h3>';
						}
					}
				}
				?>
				<label for="gf_salesforce_form" class="left_header"><?php _e("Gravity Form", "gravity-forms-salesforce"); ?> <?php gform_tooltip("salesforce_gravity_form") ?></label>

				<select id="gf_salesforce_form" name="gf_salesforce_form" onchange="SelectForm(jQuery('#gf_salesforce_list').val(), jQuery(this).val());">
				<option value=""><?php _e("Select a form", "gravity-forms-salesforce"); ?> </option>
				<?php

				foreach($forms as $form){
					if(isset($config["form_id"]) && absint($form->id) == $config["form_id"]) {
						$selected = "selected='selected'";
					} else {
						$selected = "";
					}

					?>
					<option value="<?php echo absint($form->id) ?>"  <?php echo $selected ?>><?php echo esc_html($form->title) ?></option>
					<?php
				}
				?>
				</select><span class="spinner salesforce_wait" style="display: none; position: absolute;"></span>
			</div>
			<div class="clear"></div>
			<div id="salesforce_field_group" <?php echo empty($config["meta"]["contact_object_name"]) || empty($config["form_id"]) ? "style='display:none;'" : "" ?>>
				<div id="salesforce_field_container" class="margin_vertical_10" >
					<h2><?php _e('3. Map form fields to Salesforce fields.', "gravity-forms-salesforce"); ?></h2>
					<h3 class="description"><?php _e('About field mapping:', "gravity-forms-salesforce"); ?></h3>
					<label for="salesforce_fields" class="left_header"><?php _e("Standard Fields", "gravity-forms-salesforce"); ?> <?php gform_tooltip("salesforce_map_fields") ?></label>
					<div id="salesforce_field_list">
					<?php
					if(!empty($config["form_id"])){

						//getting list of all Salesforce merge variables for the selected contact list
						if(empty($merge_vars))
							$merge_vars = self::getFieldsForObject($config['meta']['contact_object_name']);

						//getting field map UI
						echo self::get_field_mapping($config, $config["form_id"], $merge_vars );

						//getting list of selection fields to be used by the optin
						$form_meta = RGFormsModel::get_form_meta($config["form_id"]);
						$selection_fields = GFCommon::get_selection_fields($form_meta, $config["meta"]["optin_field_id"]);
					}
					?>
					</div>
					<div class="clear"></div>
				</div>

				<?php /** Manual Export - Bypass automatic export (since 2.6.0) */ ?>
				<div id="salesforce_manual_export_container" class="margin_vertical_10">
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php esc_html_e('Disable Automatic Export', "gravity-forms-salesforce"); ?> <?php gform_tooltip("salesforce_manual_export"); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><span><?php _e('Disable Automatic Export', "gravity-forms-salesforce"); ?></span></legend>
									<label for="salesforce_manual_export">
										<input name="salesforce_manual_export" type="checkbox" id="salesforce_manual_export" value="1" <?php echo !empty( $config['meta']['manual_export'] ) ? 'checked="checked"' : ''; ?>>
										<?php esc_html_e( 'Entries will be sent to Salesforce when updated in the admin', "gravity-forms-salesforce"); ?>
									</label>
								</fieldset>
							</td>
						</tr>
					</table>
				</div>

				<div id="salesforce_optin_container" class="margin_vertical_10">
					<label for="salesforce_optin" class="left_header"><?php _e("Opt-In Condition", "gravity-forms-salesforce"); ?> <?php gform_tooltip("salesforce_optin_condition"); ?></label>
					<div id="salesforce_optin">
						<table>
							<tr>
								<td>
									<input type="checkbox" id="salesforce_optin_enable" name="salesforce_optin_enable" value="1" onclick="if(this.checked){jQuery('#salesforce_optin_condition_field_container').show('slow');} else{jQuery('#salesforce_optin_condition_field_container').hide('slow');}" <?php echo !empty($config["meta"]["optin_enabled"]) ? "checked='checked'" : ""?>/>
									<label for="salesforce_optin_enable"><?php _e("Enable", "gravity-forms-salesforce"); ?></label>
								</td>
							</tr>
							<tr>
								<td>
									<div id="salesforce_optin_condition_field_container" <?php echo empty($config["meta"]["optin_enabled"]) ? "style='display:none'" : ""?>>
										<div id="salesforce_optin_condition_fields" <?php echo empty($selection_fields) ? "style='display:none'" : ""?>>
											<?php _e("Export to Salesforce if ", "gravity-forms-salesforce") ?>

											<select id="salesforce_optin_field_id" name="salesforce_optin_field_id" class='optin_select' onchange='jQuery("#salesforce_optin_value").html(GetFieldValues(jQuery(this).val(), "", 20));'><?php echo $selection_fields ?></select>
											<select id="salesforce_optin_operator" name="salesforce_optin_operator" />
												<option value="is" <?php echo (isset($config["meta"]["optin_operator"]) && $config["meta"]["optin_operator"] == "is") ? "selected='selected'" : "" ?>><?php _e("is", "gravity-forms-salesforce") ?></option>
												<option value="isnot" <?php echo (isset($config["meta"]["optin_operator"]) && $config["meta"]["optin_operator"] == "isnot") ? "selected='selected'" : "" ?>><?php _e("is not", "gravity-forms-salesforce") ?></option>
											</select>
											<select id="salesforce_optin_value" name="salesforce_optin_value" class='optin_select'>
											</select>

										</div>
										<div id="salesforce_optin_condition_message" <?php echo !empty($selection_fields) ? "style='display:none'" : ""?>>
											<?php _e("To create an Opt-In condition, your form must have a drop down, checkbox or multiple choice field.", "gravityform") ?>
										</div>
									</div>
								</td>
							</tr>
						</table>
					</div>

					<script type="text/javascript">
						<?php
						if(!empty($config["form_id"])){
							?>
							//creating Javascript form object
							form = <?php echo GFCommon::json_encode($form_meta)?> ;

							//initializing drop downs
							jQuery(document).ready(function(){
								var selectedField = "<?php echo str_replace('"', '\"', $config["meta"]["optin_field_id"])?>";
								var selectedValue = "<?php echo str_replace('"', '\"', $config["meta"]["optin_value"])?>";
								SetOptin(selectedField, selectedValue);
							});
						<?php
						}
						?>
					</script>
				</div>

				<?php /** define the field to be used as primary key when exporting entry to salesforce, thus avoiding duplicate entries (since 2.5.2) */ ?>
				<?php if( !empty( $config['meta']['contact_object_name'] ) ) :
					$current_primary_field = !empty( $config['meta']['primary_field'] ) ? $config['meta']['primary_field'] : ''; ?>
					<div id="salesforce_optin_container" class="margin_vertical_10">
						<h2><?php _e('4. Choose a "Primary Key" field.', "gravity-forms-salesforce"); ?></h2>
						<h3 class="description"><?php _e('What field should be used to update existing objects?', "gravity-forms-salesforce"); ?></h3>
						<label for="salesforce_primary_field" class="left_header"><?php esc_html_e( 'Update Field', 'gravity-forms-salesforce' ); ?></label>
						<table>
							<tr valign="top">
								<td scope="row"></td>
								<td>
									<select id="salesforce_primary_field" name="salesforce_primary_field">
										<?php echo self::render_options_as_fields( $config['meta']['contact_object_name'], $current_primary_field ); ?>
									</select>
									<span class="howto"><?php esc_html_e('If you want to update a pre-existing object, define what should be used as an unique identifier ("Primary Key"). For example, this may be an email address, Lead ID, or address.', 'gravity-forms-salesforce'); ?></span>
								</td>
							</tr>
						</table>
					</div>
				<?php endif; ?>

				<div class="button-controls submit">
					<input type="submit" name="gf_salesforce_submit" value="<?php echo empty($id) ? __("Save Feed", "gravity-forms-salesforce") : __("Update Feed", "gravity-forms-salesforce"); ?>" class="button button-primary button-hero"/>
				</div>
			</div>
		</form>
		</div>

<script type="text/javascript">
	jQuery(document).ready(function($) {
			$('#gf_salesforce_list').live('load change', function() {
				$('#lists_loading').hide();
			});
			$("#gf_salesforce_list input").bind('click change', function() {

				if($("#gf_salesforce_list input:checked").length > 0) {
					SelectList(1);
				} else {
					SelectList(false);
					jQuery("#gf_salesforce_form").val("");
				}
			});

	<?php if(isset($_REQUEST['id'])) { ?>
		$('#salesforce_field_list').live('load', function() {
			$('.salesforce_field_cell select').each(function() {
				var $select = $(this);
				var label = $.trim($('label[for='+$(this).prop('name')+']').text());

				label = label.replace(' *', '');

				if($select.val() === '') {
					$('option', $select).each(function() {

						if($(this).text() === label) {
							$(this).prop('selected', true);
						}
					});
				}
			});
		});
	<?php } ?>
	});
		</script>
		<script type="text/javascript">

			function SelectList(listId){
				if(listId){
					jQuery("#salesforce_form_container").slideDown();
				}
				else{
					jQuery("#salesforce_form_container").slideUp();
					EndSelectForm("");
				}
			}

			function SelectForm(listId, formId){

				if(!formId){
					jQuery("#salesforce_field_group").slideUp();
					return;
				}

				jQuery(".salesforce_wait").css({
					'display': 'inline-block',
				});
				jQuery("#salesforce_field_group").slideUp();

				var mysack = new sack("<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin-ajax.php" );
				mysack.execute = 1;
				mysack.method = 'POST';
				mysack.setVar( "action", "gf_select_salesforce_form" );
				mysack.setVar( "gf_select_salesforce_form", "<?php echo wp_create_nonce("gf_select_salesforce_form") ?>" );
				mysack.setVar( "objectType", listId);
				mysack.setVar( "form_id", formId);
				mysack.encVar( "cookie", document.cookie, false );
				mysack.onError = function() {jQuery(".salesforce_wait").hide(); alert('<?php echo esc_js(__("Ajax error while selecting a form", "gravity-forms-salesforce")); ?>' )};
				mysack.runAJAX();
				return true;
			}

			function SetOptin(selectedField, selectedValue){

				//load form fields
				jQuery("#salesforce_optin_field_id").html(GetSelectableFields(selectedField, 20));
				var optinConditionField = jQuery("#salesforce_optin_field_id").val();

				if(optinConditionField){
					jQuery("#salesforce_optin_condition_message").hide();
					jQuery("#salesforce_optin_condition_fields").show();
					jQuery("#salesforce_optin_value").html(GetFieldValues(optinConditionField, selectedValue, 20));
				}
				else{
					jQuery("#salesforce_optin_condition_message").show();
					jQuery("#salesforce_optin_condition_fields").hide();
				}
			}

			function EndSelectForm(fieldList, form_meta){
				//setting global form object
				form = form_meta;

				if(fieldList){

					SetOptin("","");

					jQuery("#salesforce_field_list").html(fieldList);
					jQuery("#salesforce_field_group").slideDown();
					jQuery('#salesforce_field_list').trigger('load');
				}
				else{
					jQuery("#salesforce_field_group").slideUp();
					jQuery("#salesforce_field_list").html("");
				}
				jQuery(".salesforce_wait").hide();
			}

			function GetFieldValues(fieldId, selectedValue, labelMaxCharacters){
				if(!fieldId)
					return "";

				var str = "";
				var field = GetFieldById(fieldId);
				if(!field || !field.choices)
					return "";

				var isAnySelected = false;

				for(var i=0; i<field.choices.length; i++){
					var fieldValue = field.choices[i].value ? field.choices[i].value : field.choices[i].text;
					var isSelected = fieldValue == selectedValue;
					var selected = isSelected ? "selected='selected'" : "";
					if(isSelected)
						isAnySelected = true;

					str += "<option value='" + fieldValue.replace("'", "&#039;") + "' " + selected + ">" + TruncateMiddle(field.choices[i].text, labelMaxCharacters) + "</option>";
				}

				if(!isAnySelected && selectedValue){
					str += "<option value='" + selectedValue.replace("'", "&#039;") + "' selected='selected'>" + TruncateMiddle(selectedValue, labelMaxCharacters) + "</option>";
				}

				return str;
			}

			function GetFieldById(fieldId){
				for(var i=0; i<form.fields.length; i++){
					if(form.fields[i].id == fieldId)
						return form.fields[i];
				}
				return null;
			}

			function TruncateMiddle(text, maxCharacters){
				if(text.length <= maxCharacters)
					return text;
				var middle = parseInt(maxCharacters / 2);
				return text.substr(0, middle) + "..." + text.substr(text.length - middle, middle);
			}

			function GetSelectableFields(selectedFieldId, labelMaxCharacters){
				var str = "";
				var inputType;
				for(var i=0; i<form.fields.length; i++){
					fieldLabel = form.fields[i].adminLabel ? form.fields[i].adminLabel : form.fields[i].label;
					inputType = form.fields[i].inputType ? form.fields[i].inputType : form.fields[i].type;
					if(inputType == "checkbox" || inputType == "radio" || inputType == "select"){
						var selected = form.fields[i].id == selectedFieldId ? "selected='selected'" : "";
						str += "<option value='" + form.fields[i].id + "' " + selected + ">" + TruncateMiddle(fieldLabel, labelMaxCharacters) + "</option>";
					}
				}
				return str;
			}

		</script>

		<?php

	}


	/**
	 * Render html select options where values are the fields of a specific salesforce object
	 *
	 * @access public
	 * @static
	 * @since 2.5.2
	 * @param mixed $object
	 * @param string $current (default: '')
	 * @return string html
	 */
	public static function render_options_as_fields( $object, $current = '' ) {
		if( empty( $object ) ) {
			return '';
		}

		$fields = self::getFieldsForObject( $object );
		self::sorter($fields, 'name');

		$output = '<option value="" '. selected( $current, '', false ) .'>'. esc_html__( 'None', 'gravity-forms-salesforce' ) .'</option>';
		if( !empty( $fields ) ) {
			foreach( $fields as $field ) {
				$output .= '<option value="'. esc_attr( $field['tag'] ) .'" '. selected( $current , $field['tag'], false ) .'>'.esc_html( $field['name'] ) .'</option>';
			}
		}

		return $output;

	}


	/**
	 * Ajax support function. Retrieve html for select options for a specific Salesforce object
	 *
	 * @access public
	 * @static
	 * @return string html
	 * @since 2.5.2
	 */
	public static function get_options_as_fields() {

		if( empty( $_POST['sf_object'] ) || ! isset( $_POST['nonce'] ) ||
			! wp_verify_nonce( $_POST['nonce'], 'gf_salesforce_edit_feed' ) )
		{
			exit(false);
		}

		echo self::render_options_as_fields( $_POST['sf_object'] );

		exit();
	}


	/**
	 * Enqueue custom scripts at salesforce settings page.
	 *
	 * @access public
	 * @static
	 * @param mixed $hook
	 * @return void
	 * @since 2.5.2
	 */
	public static function add_custom_script( $hook ) {

		if( !in_array( $hook , array( 'forms_page_gf_salesforce' ) ) ) {
			return;
		}

		wp_register_script( 'gf_salesforce_edit_feed', plugin_dir_url( KWS_GF_Salesforce::$file ) . 'assets/js/edit-feed.js', array( 'jquery' ) );

		wp_enqueue_script( 'gf_salesforce_edit_feed');

		wp_localize_script('gf_salesforce_edit_feed', 'ajax_object', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'gf_salesforce_edit_feed' ) ) );

	}


	public static function add_permissions(){
		global $wp_roles;
		$wp_roles->add_cap("administrator", "gravityforms_salesforce");
		$wp_roles->add_cap("administrator", "gravityforms_salesforce_uninstall");
	}

	//Target of Member plugin filter. Provides the plugin with Gravity Forms lists of capabilities
	public static function members_get_capabilities( $caps ) {
		return array_merge($caps, array("gravityforms_salesforce", "gravityforms_salesforce_uninstall"));
	}

	public static function disable_salesforce(){
		delete_option("gf_salesforce_settings");
	}

	public static function select_salesforce_form(){
		check_ajax_referer("gf_select_salesforce_form", "gf_select_salesforce_form");

		$api = self::get_api();

		if(!self::api_is_valid($api) || !isset($_POST["objectType"])) {
			exit("EndSelectForm();");
		}

		$form_id =  intval($_POST["form_id"]);

		$setting_id =  0;

		//getting list of all Salesforce merge variables for the selected contact list
		$merge_vars = @self::getFieldsForObject($_POST['objectType']);;

		if(empty($merge_vars)) {
			echo sprintf("alert('There was an error retrieving fields for the %s Object');", esc_js($_POST['objectType']));
			exit(" EndSelectForm();");
		}

		//getting configuration
		$config = GFSalesforceData::get_feed($setting_id);

		//getting field map UI
		$str = self::get_field_mapping($config, $form_id, $merge_vars);

		//fields meta
		$form = RGFormsModel::get_form_meta($form_id);

		exit("EndSelectForm('" .str_replace("'", "\'", $str). "', " . GFCommon::json_encode($form) . ");");
	}

	private static function show_field_type_desc($field = '') {
		$types = array(
			'anyType',
			'calculated',
			'combobox',
			'currency',
			'DataCategoryGroupReference',
			'email',
			'encryptedstring',
			'ID',
			'masterrecord',
			'multipicklist',
			'percent',
			'phone',
			'picklist',
			'reference',
			'textarea',
			'url',
		);
		return in_array($field, $types);
	}

	private static function get_field_mapping($config = array(), $form_id, $merge_vars){

		$usedFields = array();
		$str = $custom = $standard = '';

		//getting list of all fields for the selected form
		$form_fields = self::get_form_fields($form_id);

		$str = "<table cellpadding='0' cellspacing='0'><thead><tr><th scope='col' class='salesforce_col_heading'>" . __("List Fields", "gravity-forms-salesforce") . "</th><th scope='col' class='salesforce_col_heading'>" . __("Form Fields", "gravity-forms-salesforce") . "</th></tr></thead><tbody>";

		foreach((array)$merge_vars as $var){

			if($var['type'] === 'reference' && empty($var['req']) && apply_filters('gf_salesforce_skip_reference_types', true)) { continue; }

			$selected_field = isset($config["meta"]["field_map"][$var["tag"]]) ? $config["meta"]["field_map"][$var["tag"]] : false;
			$required = $var["req"] === true ? "<span class='gfield_required' title='This field is required.'>".__('(Required)', 'gravity-forms-salesforce')."</span>" : "";
			$error_class = $var["req"] === true && empty($selected_field) && !empty($_POST["gf_salesforce_submit"]) ? " feeds_validation_error" : "";
			$field_desc = '';
			if(self::show_field_type_desc($var['type'])) {
				$field_desc = '<div>'.sprintf( __('Type: %s', 'gravity-forms-salesforce'), esc_attr( $var["type"] ) ) .'</div>';
			}
			if(!empty($var["length"])) { $field_desc .= '<div>' . sprintf( __('Max Length: %s', 'gravity-forms-salesforce' ), esc_attr( $var["length"] ) ).'</div>'; }

			// Add Daddy Analytics notice
			if($var['tag'] === 'LeadSource') {
				$field_desc = apply_filters('gf_salesforce_lead_source_label', $field_desc );
			}

			$row = "<tr class='$error_class'><td class='salesforce_field_cell'><label for='salesforce_map_field_{$var['tag']}'>" . stripslashes( $var["name"] )  . " $required</label><small class='description' style='display:block'>{$field_desc}</small></td><td class='salesforce_field_cell'>" . self::get_mapped_field_list($var["tag"], $selected_field, $form_fields) . "</td></tr>";

			$str .= $row;

		} // End foreach merge var.

		$str .= "</tbody></table>";

		return $str;
	}

	public static function get_form_fields($form_id){
		$form = RGFormsModel::get_form_meta($form_id);
		$fields = array();

		//Adding default fields
		array_push($form['fields'],array("id" => "date_created" , "label" => __("Entry Date", "gravity-forms-salesforce")));
		array_push($form['fields'],array("id" => "ip" , "label" => __("User IP", "gravity-forms-salesforce")));
		array_push($form['fields'],array("id" => "source_url" , "label" => __("Source Url", "gravity-forms-salesforce")));
		array_push($form['fields'],array("id" => "form_title" , "label" => __("Form Title", "gravity-forms-salesforce")));

		if(is_array($form['fields'])){
			self::sorter($form['fields'], 'label');

			// push other form field options onto stack as "Primary Keys"
			if($form_id) {
				if($feeds = GFSalesforceData::get_feed_by_form($form_id, true)) {
					foreach($feeds as $feed) {
						$label = self::primary_key_label($feed);
						$options = array(
							"id" => self::primary_key_id($feed),
							"label" => __($label, "gravity-forms-salesforce")
						);

						array_unshift($form['fields'], $options);
					}
				}
			}

			foreach($form['fields'] as $field){
				if(isset($field["inputs"]) && is_array($field["inputs"]) && $field['type'] !== 'checkbox' && $field['type'] !== 'select'){

					//If this is an address field, add full name to the list
					if(RGFormsModel::get_input_type($field) == "address") {
						$fields[] =  array($field["id"], GFCommon::get_label($field) . " (" . _x("Full" , 'Full field label', "gravity-forms-salesforce") . ")");
					}

					foreach($field["inputs"] as $input)
						$fields[] =  array($input["id"], GFCommon::get_label($field, $input["id"]));
				}
				else if(empty($field["displayOnly"])){
					$fields[] =  array($field["id"], GFCommon::get_label($field));
				}
			}
		}
		return $fields;
	}

	private static function get_address($entry, $field_id){
		$street_value = str_replace("  ", " ", trim($entry[$field_id . ".1"]));
		$street2_value = str_replace("  ", " ", trim($entry[$field_id . ".2"]));
		$city_value = str_replace("  ", " ", trim($entry[$field_id . ".3"]));
		$state_value = str_replace("  ", " ", trim($entry[$field_id . ".4"]));
		$zip_value = trim($entry[$field_id . ".5"]);
		$country_value = GFCommon::get_country_code(trim($entry[$field_id . ".6"]));

		$address = $street_value;
		$address .= !empty($address) && !empty($street2_value) ? "  $street2_value" : $street2_value;
		$address .= !empty($address) && (!empty($city_value) || !empty($state_value)) ? "  $city_value" : $city_value;
		$address .= !empty($address) && !empty($city_value) && !empty($state_value) ? "  $state_value" : $state_value;
		$address .= !empty($address) && !empty($zip_value) ? "  $zip_value" : $zip_value;
		$address .= !empty($address) && !empty($country_value) ? "  $country_value" : $country_value;

		return $address;
	}

	public static function get_mapped_field_list($variable_name, $selected_field, $fields){
		$field_name = "salesforce_map_field_" . $variable_name;
		$str = "<select name='$field_name' id='$field_name'><option value=''></option>";
		foreach($fields as $field){
			$field_id = $field[0];
			$field_label = $field[1];
			$str .= "<option value='" . $field_id . "' ". selected(($field_id == $selected_field), true, false) . ">" . $field_label . "</option>";
		}
		$str .= "</select>";
		return $str;
	}

	public static function get_mapped_field_checkbox($variable_name, $selected_field, $field){
		$field_name = "salesforce_map_field_" . $variable_name;
		$field_id = $field[0];
		$str =  "<input name='$field_name' id='$field_name' type='checkbox' value='$field_id'";
		$selected = $field_id == $selected_field ? " checked='checked'" : false;
		if($selected) {
			$str .= $selected;
		}

		$str .= " />";
		return $str;
	}

	/**
	 * Called when entry is manually updated in the Single Entry view of Gravity Forms.
	 *
	 * This method is called by both `admin_init` and `gforms_after_update_entry`.
	 *
	 * @todo  Convert to using GFCommon::send_notification() instead
	 * @since 2.6.0
	 * @access public
	 * @static
	 * @param array $form GF Form array
	 * @param int $entry_id Entry ID
	 * @return void
	 */
	public static function manual_export( $form, $entry_id = NULL ) {
		global $plugin_page;

		// Is this the Gravity Forms entries page?
		if(false === (self::is_gravity_page('gf_entries') && rgget("view") == 'entry' && (rgget('lid') || !rgblank(rgget('pos'))))) {
			return;
		}

		// Both admin_init and gforms_after_update_entry will have this set
		if( empty( $_POST['gforms_save_entry'] ) || empty( $_POST['action'] ) ) { return; }

		// Different checks since admin_init runs in both cases but we need to wait for entry update
		$current_hook = current_filter();
		if( $current_hook == 'admin_init' && empty( $_POST['send_to_salesforce'] ) ) { return; }
		if( $current_hook == 'gform_after_update_entry' && empty( $_POST['update_to_salesforce'] ) ) { return; }

		// Verify authenticity of request
		check_admin_referer('gforms_save_entry', 'gforms_save_entry');

		// For admin_init hook, get the entry ID from the URL
		if(empty($entry_id)) {
			$entry_id = rgget('lid');
			$form_id = rgget('id');

			// fetch alternative entry id: look for gf list details when using pagination
			if(empty($entry_id)) {
				$position = rgget('pos');
				$paging = array('offset' => $position, 'page_size' => 1);

				$entries = GFAPI::get_entries($form_id, array(), null, $paging);

				if(!empty($entries)) {
					// pluck first entry to use id from, should always only be one
					$entry = array_shift($entries);
					$entry_id = $entry['id'];
				} else {
					self::log_error(__METHOD__ . ': Could not locate GF entry.');
					return;
				}
			}

			$form = RGFormsModel::get_form_meta($form_id);
		}

		// Fetch entry (use new GF API from version 1.8)
		if( class_exists( 'GFAPI' ) && !empty( $entry_id ) ) {
			$entry = GFAPI::get_entry( $entry_id );
		} elseif( class_exists( 'RGFormsModel' ) && !empty( $entry_id ) ) {
			$entry = RGFormsModel::get_lead( $entry_id );
		} else {
			self::log_error('manual_export(): Expected Gravity Forms classes did not exist. Have to leave now.');
			return;
		}

		// Export the entry
		self::export($entry, $form, true);

		// Don't send twice.
		unset($_POST['update_to_salesforce']);
		unset($_POST['send_to_salesforce']);
	}

	public static function export($entry, $form, $manual_export = false){
		//Login to Salesforce
		$api = self::get_api();

		if(!self::api_is_valid($api)) {
			self::log_error('export(): Invalid API. '.print_r($api, true));
			do_action('gf_salesforce_error', 'export', $api);
			return;
		}

		// getting all active feeds
		$feeds = GFSalesforceData::get_feed_by_form($form["id"], true);

		foreach($feeds as $feed){
			// If feed has Manual Export enabled, stop export - since 2.6.0
			// If it's manual, though, go head.
			if( !empty( $feed['meta']['manual_export'] ) && !$manual_export) {
				self::log_debug('export(): Not processing export for feed, since manual export is enabled in feed settings.');
				return;
			}

			// Manual opt-ins have different logic.
			$manual_opt_in = ( $manual_export && self::is_optin_ok( $entry, $feed ) );
			$auto_opt_in = ( !$manual_export && self::is_optin($form, $feed) );

			// only export if user has opted in
			if( $auto_opt_in || $manual_opt_in ) {
				self::export_feed($entry, $form, $feed);
			}
		}
	}

	public static function export_feed($entry, $form, $feed){
		if(empty($feed['meta']['contact_object_name'])) {
			self::log_error(__METHOD__ . ': There was no Object type defined in the feed (like Contact or Lead)');
			return false;
		}

		self::log_debug(sprintf('%s: Starting export for entry #%s to Salesforce...', __METHOD__, $entry['id']));

		$contactId = self::create($entry, $form, $feed);

		if(!empty($contactId)) {
			self::log_debug(sprintf('%1$s: %2$s created/updated. Salesforce %2$s: %3$s',
										__METHOD__, $feed['meta']['contact_object_name'], $contactId));
		} else {
			self::log_debug(sprintf('%s: %s failed to be created/updated',
										__METHOD__, $feed['meta']['contact_object_name']));
		}

		return $contactId;
	}

	/**
	 * During export, create an export array based on the feed mappings.
	 * @param  array      $entry Entry array
	 * @param  array      $form  Form array
	 * @param  array      $feed  Feed array
	 * @return [type]             [description]
	 */
	private static function process_merge_vars($entry, $form, $feed) {

		self::log_debug('process_merge_vars(): Starting...');

		$merge_vars = array();

		foreach($feed["meta"]["field_map"] as $var_tag => $field_id){

			$field = RGFormsModel::get_field($form, $field_id);
			$input_type = RGFormsModel::get_input_type($field);

			if($var_tag == 'address_full') {
				$merge_vars[$var_tag] = self::get_address($entry, $field_id);
			} else if($var_tag  == 'country') {
				$merge_vars[$var_tag] = empty($entry[$field_id]) ? '' : GFCommon::get_country_code(trim($entry[$field_id]));
			}
			// If, for example an user enters 0 in a text field type expecting a number
			else if(isset($entry[$field_id]) && $entry[$field_id] === "0") {
				$merge_vars[$var_tag] = "0";
			} else if($var_tag != "email") {
				if(!empty($entry[$field_id]) && !($entry[$field_id] == "0")) {
					switch($input_type) {
						// Thanks to Scott Kingsley Clark
						// http://wordpress.org/support/topic/likert-field-compatibility-with-survey-add-on
						case 'likert':
							$value = $entry[$field_id];

							foreach($field['choices'] as $choice ) {
								if($value === $choice['value']) {
									$value = $choice['text'];
									break;
								}
							}

							$value = htmlspecialchars($value);

							break;
						case 'multiselect':
							// If there are commas in the value, this makes it so it can be comma exploded.
							// Values cannot contain semicolons: http://boards.developerforce.com/t5/NET-Development/Salesforce-API-inserting-values-into-multiselect-fields-using/td-p/125910
							foreach($field['choices'] as $choice) {
								$entry[$field_id] = str_replace($choice, str_replace(',', '&#44;', $choice), $entry[$field_id]);
							}
							// Break into an array
							$elements = explode(",",$entry[$field_id]);

							// We decode first so that the commas are commas again, then
							// implode the array to be picklist format for SF
							$value = implode(';', array_map('html_entity_decode', array_map('htmlspecialchars', $elements)));
							break;
						default:
							$value = htmlspecialchars($entry[$field_id]);
					}

					$merge_vars[$var_tag] = GFCommon::replace_variables($value, $form, $entry, false, false, false );

			} else if(array_key_exists($field_id, self::$foreign_keys)) {
				$merge_vars[$var_tag] = self::$foreign_keys[$field_id];
			} else {

					// This is for checkboxes
					$elements = array();
					foreach($entry as $key => $value) {
						if(floor($key) == floor($field_id) && !empty($value)) {
							$elements[] = htmlspecialchars($value);
						}
					}

					$value = implode(';',array_map('htmlspecialchars', $elements));

					$merge_vars[$var_tag] = GFCommon::replace_variables($value, $form, $entry, false, false, false );
				}
			}

			$merge_vars[ $var_tag ] = apply_filters( 'gf_salesforce_mapped_value_' . $var_tag, $merge_vars[ $var_tag ], $field, $var_tag, $form, $entry );
			$merge_vars[ $var_tag ] = apply_filters( 'gf_salesforce_mapped_value', $merge_vars[ $var_tag ], $field, $var_tag, $form, $entry );

		}

		self::log_debug('process_merge_vars(): Completed.');

		return $merge_vars;

	}

	private static function create($entry, $form, $feed) {

		self::log_debug(__METHOD__ . ': Starting the create process...');

		$api = self::get_api();

		$token = self::getToken();

		// There was no token. This is all wrong.
		if(empty($token)) {
			self::log_error(__METHOD__ . ': There was no OAuth token. It was likely revoked. Aborting.');
			return false;
		}

		if(!isset($feed['is_active']) || $feed['is_active'] == 0) {
			self::log_error(sprintf('%s: Feed `%s` is not active.', __METHOD__, $feed['meta']['contact_object_name']));
			return false;
		}

		$merge_vars = self::process_merge_vars($entry, $form, $feed);

		$merge_vars = apply_filters( 'gf_salesforce_create_data', $merge_vars, $form, $entry, $feed, $api );

		// Make sure the charset is UTF-8 for Salesforce.
		$merge_vars = array_map(array('GFSalesforce', '_convert_to_utf_8'), $merge_vars);

		// Don't send merge_vars that are empty. It can cause problems with Salesforce strict typing.  For example,
		// if the form has a text field where a number should go, but that number isn't always required, when it's
		// not supplied, we don't want to send <var></var> to Salesforce. It might choke because it expects a Double
		// data type, not an empty string
		$merge_vars = array_filter($merge_vars, array('GFSalesforce', '_remove_empty_fields'));

		// We create the object to insert/upsert into Salesforce
		$Account = new SObject();

		// The fields to use are the merge vars
		$Account->fields = $merge_vars;

		// Set the type of object
		$Account->type = $feed['meta']['contact_object_name'];

		$foreign_key_label = self::primary_key_id($feed);

		try {
			if( !(self::$instance instanceof GFSalesforce) ) {

				self::$instance = self::Instance();

			}

			// If the primary field has been set, use that to upsert instead of create.
			// @since 2.5.2, to avoid duplicates at Salesforce
			if( !empty( $feed['meta']['primary_field'] ) ) {

				self::log_debug(sprintf('%s: Upserting using primary field of `%s`',
											__METHOD__, $feed['meta']['primary_field']));

				if(empty(self::$instance->result->id)) {

					// old upsert
					// https://www.salesforce.com/us/developer/docs/api/Content/sforce_api_calls_upsert.htm
					self::log_debug(__METHOD__ . ': Upserting');
					$result = $api->upsert( $feed['meta']['primary_field'], array($Account) );

				} else {

					self::log_debug(sprintf('%s: Creating with previous id %s', __METHOD__, self::$instance->result->id));
					$Account->fields[$feed['meta']['primary_field']] = self::$instance->result->id;
					$result = $api->create( array($Account) );

				}

			} else {

				self::log_debug(__METHOD__ . ': Creating, not upserting');
				$result = $api->create( array($Account) );

			}

			$api_exception = '';

			self::log_debug(sprintf('%s: $Account object: %s', __METHOD__, print_r($Account, true)));

		} catch (Exception $e) {

			self::log_error(sprintf("%s:\n\nException While Exporting Entry\nThere was an error exporting Entry #%s for Form #%s. Here's the error:\n%s",
										__METHOD__ , $entry['id'], $form['id'], $e->getMessage()));

			$api_exception = "
				Exception Message: "  . $e->getMessage() .
				"\nFaultstring: " . $e->faultstring .
				"\nFile: " . $e->getFile() .
				"\nLine: " . $e->getLine() .
				"\nArgs: " . serialize($merge_vars) .
				"\nTrace: " . serialize($e->getTrace());

			gform_update_meta( $entry['id'], 'salesforce_api_result', 'Error: ' . $e->getMessage() );
		}

		if (isset($result) && count($result) == 1 && !empty($result[0])) {
			self::$instance->result = $result = $result[0];
		}

		if (isset($result->success) && !empty($result->success)) {

			$result_id = $result->id;
			self::$foreign_keys[$foreign_key_label] = $result_id;

			gform_update_meta( $entry['id'], 'salesforce_id', $result_id );
			gform_update_meta( $entry['id'], 'salesforce_api_result', 'success' );

			$success_note = sprintf(__('Successfully added/updated to Salesforce (%s) with ID #%s. View entry at %s', 'gravity-forms-salesforce'),
										$Account->type, $result_id, self::getTokenParam('instance_url').'/'.$result_id);

			self::log_debug(__METHOD__ . ': '.$success_note);
			self::add_note($entry["id"], $success_note);

			self::admin_screen_message( __( 'Entry added/updated in Salesforce.', 'gravity-forms-salesforce' ), 'updated');

			/**
			 * @since 3.1.2
			 */
			do_action( 'gravityforms_salesforce_object_added_updated', $Account, $feed, $result_id );

			return $result_id;

		} else {
			if(isset($result->errors[0])) {
				$errors = $result->errors[0];
			}

			if(isset($errors)) {

				self::log_error(sprintf('%s: There was an error exporting Entry #%s for Form #%s. Salesforce responded with:',
											__METHOD__, $entry['id'], $form['id']) ."\n".print_r($errors, true));

				if($email = self::is_notify_on_error()) {

					$error_heading = __('Error adding to Salesforce', 'gravity-forms-salesforce');
					// Create the email message to send
					$message = sprintf(apply_filters('gravityforms_salesforce_notify_on_error_message', '<h3>'.$error_heading.'</h3>'.wpautop(__("There was an error when attempting to add %sEntry #%s from the form \"%s\"", 'gravity-forms-salesforce')), $errors, $entry, $form), '<a href="'.admin_url('admin.php?page=gf_entries&view=entry&id='.$entry['form_id'].'&lid='.$entry['id']).'">', $entry['id'].'</a>', $form['title']);

					// Send as HTML
					$headers = "Content-type: text/html; charset=" . get_option('blog_charset') . "\r\n";

					// Send email
					$sent = wp_mail($email, $error_heading, $message, $headers);

					if(!$sent) {
						self::log_error(__METHOD__ . ': There was an error sending the error email. This really isn\'t your day, is it?');
					}
				}

				self::add_note($entry["id"],
						sprintf(__('Errors when adding to Salesforce (%s): %s', 'gravity-forms-salesforce'),
									$Account->type, $errors->message.$api_exception));

			}

			self::admin_screen_message( __( 'Errors when adding to Salesforce. Entry not sent! Check the Entry Notes below for more details.', 'gravity-forms-salesforce' ), 'error');

			return false;
		}
	}

	static function _remove_empty_fields($merge_var) {
		return (
			(function_exists('mb_strlen') && mb_strlen($merge_var) > 0) ||
			!function_exists('mb_strlen') && strlen($merge_var) > 0
		);
	}

	static function _convert_to_utf_8($string) {

		if(function_exists('mb_convert_encoding') && !seems_utf8($string)) {
			$string = mb_convert_encoding($string, "UTF-8");
		}

		// Salesforce can't handle newlines in SOAP; we encode them instead.
		$string = str_replace("\n", '&#x0a;', $string);
		$string = str_replace("\r", '&#x0d;', $string);
		$string = str_replace("\t", '&#09;', $string);

		// Remove control characters (like page break, etc.)
		$string = preg_replace('/[[:cntrl:]]+/', '', $string);

		// Escape XML characters like `< ' " & >`
		$string = esc_attr($string);

		return $string;
	}

	/**
	 * Print a link to the Salesforce entry ID in the Entry actions box
	 * @param  int $form_id ID of the form
	 * @param  array $lead    GF Entry array
	 */
	static function entry_info_link_to_salesforce($form_id, $lead) {
		$salesforce_id = gform_get_meta($lead['id'], 'salesforce_id');
		if(!empty($salesforce_id)) {
			echo sprintf(__('Salesforce ID: %s', 'gravity-forms-salesforce'), '<a href="'.self::getTokenParam('instance_url').'/'.$salesforce_id.'">'.$salesforce_id.'</a><br /><br />');
		}
	}

	/**
	 * Whether to show the Entry "Send to Salesforce" button or not
	 *
	 * If the entry's form has been mapped to Salesforce feed, show the Send to Salesforce button. Otherwise, don't.
	 *
	 * @return boolean True: Show the button; False: don't show the button.
	 */
	private static function show_send_to_salesforce_button() {

		$form_id = rgget('id');

		return self::has_feed($form_id);
	}

	/**
	 * Does the current form have a feed assigned to it?
	 * @param  INT      $form_id Form ID
	 * @return boolean
	 */
	static function has_feed($form_id) {

		$feeds = GFSalesforceData::get_feed_by_form( $form_id , true);

		return !empty($feeds);
	}

	/**
	 * Add button to entry info - option to send entry to Salesforce
	 *
	 * @since 2.6.1
	 * @access public
	 * @static
	 * @param int $form_id
	 * @param array $lead
	 * @return string
	 */
	public static function entry_info_send_to_salesforce_button( $button = '' ) {

		// If this entry's form isn't connected to salesforce, don't show the button
		if(!self::show_send_to_salesforce_button() || !apply_filters( 'gf_salesforce_show_manual_export_button', true ) ) { return $button; }

		// Is this the view or the edit screen?
		$mode = empty($_POST["screen_mode"]) ? "view" : $_POST["screen_mode"];

		if($mode === 'view') {
			$button_html = '
				<input type="hidden" name="send_to_salesforce" id="send_to_salesforce" value="" />
				<input type="submit" class="button button-large button-secondary alignright" style="margin-left:5px;" value="%s" title="%s" onclick="jQuery(\'#send_to_salesforce\').val(\'1\'); jQuery(\'#action\').val(\'send_to_salesforce\')" />
			';
			$button .= sprintf( $button_html, esc_html__('Send to Salesforce', 'gravity-forms-salesforce'), esc_html__('Create or update this entry in Salesforce. The fields will be mapped according to the form feed settings.', 'gravity-forms-salesforce'));
		}

		return $button;
	}

	/**
	 * Add checkbox to entry info - option to send entry to salesforce
	 *
	 * @since 2.6.1
	 * @access public
	 * @static
	 * @param int $form_id
	 * @param array $lead
	 * @return void
	 */
	public static function entry_info_send_to_salesforce_checkbox( $form_id, $lead ) {

		// If this entry's form isn't connected to salesforce, don't show the checkbox
		if(!self::show_send_to_salesforce_button() ) { return; }

		// If this is not the Edit screen, get outta here.
		if(empty($_POST["screen_mode"]) || $_POST["screen_mode"] === 'view') { return; }

		if( apply_filters( 'gf_salesforce_show_manual_export_button', true ) ) {
			printf('<input type="checkbox" name="update_to_salesforce" id="update_to_salesforce" value="1" /><label for="update_to_salesforce" title="%s">%s</label><br /><br />', esc_html__('Create or update this entry in Salesforce. The fields will be mapped according to the form feed settings.', 'gravity-forms-salesforce'), esc_html__('Send to Salesforce', 'gravity-forms-salesforce'));
		} else {
			echo '<input type="hidden" name="update_to_salesforce" id="update_to_salesforce" value="1" />';
		}
	}


	/**
	 * admin_screen_message function.
	 *
	 * @since 2.6.1
	 * @access public
	 * @static
	 * @param string $message
	 * @param string $level
	 * @return void
	 */
	public static function admin_screen_message( $message, $level = 'updated') {
		if( is_admin() ) {
			echo '<div class="'. esc_attr( $level ) .' fade" style="padding:6px;">';
			echo esc_html( $message );
			echo '</div>';
		}
	}


	private static function add_note($id, $note) {

		if(!apply_filters('gravityforms_salesforce_add_notes_to_entries', true)) { return; }

		RGFormsModel::add_note($id, 0, __('Gravity Forms Salesforce Add-on'), $note);
	}

	/**
	 * Remove the plugin settings on uninstall.
	 */
	public static function uninstall(){

		if(!GFSalesforce::has_access("gravityforms_salesforce_uninstall"))
			exit(__("You don't have adequate permission to uninstall Salesforce Add-On.", "gravity-forms-salesforce"));

		//droping all tables
		GFSalesforceData::drop_tables();

		//removing options
		delete_option("gf_salesforce_settings");
		delete_option("gf_salesforce_version");

		//Deactivating plugin
		$plugin = "gravity-forms-salesforce/salesforce.php";
		deactivate_plugins($plugin);
		update_option('recently_activated', array($plugin => time()) + (array)get_option('recently_activated'));
	}



	/**
	 * Opt-in logic: returns true if entry is OK to be exported
	 *
	 * @return boolean
	 */
	public static function is_optin($form, $settings){
		$config = $settings["meta"];
		$operator = $config["optin_operator"];

		$field = RGFormsModel::get_field($form, $config["optin_field_id"]);
		$field_value = RGFormsModel::get_field_value($field, array());
		$is_value_match = is_array($field_value) ? in_array($config["optin_value"], $field_value) : $field_value == $config["optin_value"];

		return  !$config["optin_enabled"] || empty($field) || ($operator == "is" && $is_value_match) || ($operator == "isnot" && !$is_value_match);
	}


	/**
	 * Alternative is_optin function to use when entry is manually updated,
	 * returns true if entry is OK to be exported
	 *
	 * @access public
	 * @static
	 * @param array $entry
	 * @param array $settings
	 * @return boolean
	 */
	public static function is_optin_ok( $entry, $settings ){
		if( empty( $settings['meta']['optin_enabled'] ) ) {
			return true;
		}

		$operator = $settings['meta']['optin_operator'];

		foreach( $entry as $key => $value ) {
			if( floor( $key ) == $settings['meta']['optin_field_id'] ) {
				$field_value[] = empty( $value ) ? '' : $value;
			}
		}

		$is_value_match = is_array( $field_value ) ? in_array( $settings['meta']['optin_value'], $field_value) : false;

		return ( $operator == "is" && $is_value_match ) || ( $operator == "isnot" && !$is_value_match );
	}

	private static function is_gravityforms_supported(){
		if(class_exists("GFCommon")){
			$is_correct_version = version_compare(GFCommon::$version, self::$min_gravityforms_version, ">=");
			return $is_correct_version;
		}
		else{
			return false;
		}
	}

	protected static function has_access($required_permission){
		$has_members_plugin = function_exists('members_get_capabilities');
		$has_access = $has_members_plugin ? current_user_can($required_permission) : current_user_can("level_7");
		if($has_access)
			return $has_members_plugin ? $required_permission : "level_7";
		else
			return false;
	}

	//Returns the url of the plugin's root folder
	static public function get_base_url(){
		return plugins_url(null, KWS_GF_Salesforce::$file);
	}

	//Returns the physical path of the plugin's root folder
	static protected function get_base_path(){
		return plugin_dir_path(KWS_GF_Salesforce::$file);
	}

	/**
	 * Export Entries
	 */

	/**
	 * Add meta fields to entries export screen
	 * @param  $form object
	 * @return object
	 */
	public static function export_entries_add_fields( $form ) {
		$form['fields'][] = array('id' => 'salesforce_id' , 'label' => __( 'Salesforce ID', 'gravity-forms-salesforce' ) );
		$form['fields'][] = array('id' => 'salesforce_api_result' , 'label' => __( 'Salesforce API result', 'gravity-forms-salesforce' ) );
		return $form;
	}

	/**
	 * Populate meta field values when exporting entries
	 * @param  string $value    Value of the field being exported
	 * @param  int $form_id     ID of the current form.
	 * @param  int $field_id    ID of the current field.
	 * @param  object $lead     The current entry.
	 * @return string
	 */
	public static function export_entries_add_values( $value, $form_id, $field_id, $lead ) {
		switch( $field_id ) {
			case 'salesforce_id':
				$value = gform_get_meta( $lead['id'], 'salesforce_id' );
				break;
			case 'salesforce_api_result':
				$value = gform_get_meta( $lead['id'], 'salesforce_api_result' );
				break;
		}

		return $value;
	}

	/**
	 * Create primary key id
	 * @since  3.1
	 * @param Feed
	 * @return String
	 */
	public static function primary_key_id($feed)
	{
		$id = strtolower(self::primary_key_label($feed));

		return preg_replace('/\W+/', '_', $id);
	}

	/**
	 * Create primary key label
	 * @since  3.1
	 * @param Feed
	 * @return String
	 */
	public static function primary_key_label($feed)
	{
		if(!empty($feed['meta']['contact_object_name'])) {
			$label = sprintf( _x('%s (Primary Key)', 'Label for the Primary Key selector in the Edit Feed form', 'gravity-forms-salesforce'), $feed['meta']['contact_object_name']);

			return $label;
		}

		return false;
	}

	/**
	 * Try and sort array based on column name
	 * @since  3.1
	 * @param Array
	 * @return Void
	 */
	public static function sorter(&$array, $column = null)
	{
		if(!is_array($column)) {
			$props = array($column => true);
		} else {
			$props = $column;
		}

		usort($array, function($a, $b) use ($props) {
			foreach($props as $prop => $ascending)
			{
				if($a[$prop] != $b[$prop])
				{
					if($ascending)
						return $a[$prop] > $b[$prop] ? 1 : -1;
					else
						return $b[$prop] > $a[$prop] ? 1 : -1;
				}
			}
			return -1; //if all props equal
		});
	}
}

new GFSalesforce;
