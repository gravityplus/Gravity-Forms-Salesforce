<?php
/*
Plugin Name: Gravity Forms Salesforce API Add-On
Plugin URI: http://www.seodenver.com/salesforce/
Description: Integrates <a href="http://formplugin.com?r=salesforce">Gravity Forms</a> with Salesforce allowing form submissions to be automatically sent to your Salesforce account
Version: 2.4
Author: Katz Web Services, Inc.
Author URI: http://www.katzwebservices.com

------------------------------------------------------------------------
Copyright 2013 Katz Web Services, Inc.

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

add_action('init',  array('GFSalesforce', 'init'));
register_activation_hook( __FILE__, array("GFSalesforce", "add_permissions"));
register_activation_hook( __FILE__, array("GFSalesforce", "force_refresh_transients"));
register_deactivation_hook( __FILE__, array("GFSalesforce", "force_refresh_transients"));

class GFSalesforce {

    private static $name = "Gravity Forms Salesforce Add-On";
    private static $api = '';
    private static $path = "gravity-forms-salesforce/salesforce-api.php";
    private static $url = "http://formplugin.com";
    private static $slug = "gravity-forms-salesforce";
    private static $version = "2.4";
    private static $min_gravityforms_version = "1.3.9";
    private static $is_debug = NULL;
    private static $cache_time = 86400; // 24 hours
    private static $settings = array(
                "username" => '',
                "password" => '',
                'securitytoken' => '',
                "debug" => false,
                'notify' => false,
                "notifyemail" => '',
                'cache_time' => 86400,
            );

    //Plugin starting point. Will load appropriate files
    public static function init(){
        global $pagenow;
        require_once(self::get_base_path() . "/edit-form.php");
        if($pagenow === 'plugins.php' && is_admin()) {
            add_action("admin_notices", array('GFSalesforce', 'is_gravity_forms_installed'), 10);
        }

        if(self::is_gravity_forms_installed(false, false) === 0){
            add_action('after_plugin_row_' . self::$path, array('GFSalesforce', 'plugin_row') );
           return;
        }

        if($pagenow == 'plugins.php' || defined('RG_CURRENT_PAGE') && RG_CURRENT_PAGE == "plugins.php"){
            //loading translations
            load_plugin_textdomain('gravity-forms-salesforce', FALSE, '/gravity-forms-salesforce/languages' );

            add_filter('plugin_action_links', array('GFSalesforce', 'settings_link'), 10, 2 );

        }

        if(!self::is_gravityforms_supported()){
           return;
        }

        self::$settings = get_option("gf_salesforce_settings");

        if(is_admin()){
            //loading translations
            load_plugin_textdomain('gravity-forms-salesforce', FALSE, '/gravity-forms-salesforce/languages' );

            //creates a new Settings page on Gravity Forms' settings screen
            if(self::has_access("gravityforms_salesforce")){
                RGForms::add_settings_page("Salesforce", array("GFSalesforce", "settings_page"), self::get_base_url() . "/images/salesforce-50x50.png");
            }

            self::refresh_transients();
        }

        //integrating with Members plugin
        if(function_exists('members_get_capabilities'))
            add_filter('members_get_capabilities', array("GFSalesforce", "members_get_capabilities"));

        //creates the subnav left menu
        add_filter("gform_addon_navigation", array('GFSalesforce', 'create_menu'));

        if(self::is_salesforce_page()){

            //enqueueing sack for AJAX requests
            wp_enqueue_script(array("sack"));

            //loading data lib
            require_once(self::get_base_path() . "/data.php");

            //loading Gravity Forms tooltips
            require_once(GFCommon::get_base_path() . "/tooltips.php");
            add_filter('gform_tooltips', array('GFSalesforce', 'tooltips'));

            //runs the setup when version changes
            self::setup();

         } else if(in_array(RG_CURRENT_PAGE, array("admin-ajax.php"))){

            //loading data class
            require_once(self::get_base_path() . "/data.php");

            add_action('wp_ajax_rg_update_feed_active', array('GFSalesforce', 'update_feed_active'));
            add_action('wp_ajax_gf_select_salesforce_form', array('GFSalesforce', 'select_salesforce_form'));

        }
        else{
             //handling post submission.
            add_action("gform_after_submission", array('GFSalesforce', 'export'), 10, 2);
        }
        add_action('gform_entry_info', array('GFSalesforce', 'entry_info_link_to_salesforce'), 10, 2);
    }

    static function force_refresh_transients() {
        global $wpdb;
        self::refresh_transients(true);
    }

    static private function refresh_transients($force = false)
    {
        global $wpdb;

        if($force || (isset($_GET['refresh']) && current_user_can('administrator') && $_GET['refresh'] === 'transients')) {
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE `option_name` LIKE '%_transient_sfgf_%' OR`option_name` LIKE '%_transient_timeout_sfgf_%'");
        }
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

    public static function is_gravity_forms_installed($asd = '', $echo = true) {
        global $pagenow, $page, $showed_is_gravity_forms_installed; $message = '';

        $installed = 0;
        $name = self::$name;
        if(!class_exists('RGForms')) {
            if(file_exists(WP_PLUGIN_DIR.'/gravityforms/gravityforms.php')) {
                $installed = 1;
                $message .= __(sprintf('%sGravity Forms is installed but not active. %sActivate Gravity Forms%s to use the %s plugin.%s', '<p>', '<strong><a href="'.wp_nonce_url(admin_url('plugins.php?action=activate&plugin=gravityforms/gravityforms.php'), 'activate-plugin_gravityforms/gravityforms.php').'">', '</a></strong>', $name,'</p>'), 'gravity-forms-salesforce');
            } else {
                $message .= <<<EOD
<p><a href="http://katz.si/gravityforms?con=banner" title="Gravity Forms Contact Form Plugin for WordPress"><img src="http://gravityforms.s3.amazonaws.com/banners/728x90.gif" alt="Gravity Forms Plugin for WordPress" width="728" height="90" style="border:none;" /></a></p>
        <h3><a href="http://katz.si/gravityforms" target="_blank">Gravity Forms</a> is required for the $name</h3>
        <p>You do not have the Gravity Forms plugin installed. <a href="http://katz.si/gravityforms">Get Gravity Forms</a> today.</p>
EOD;
            }

            if(!empty($message) && $echo && is_admin() && did_action( 'admin_notices' )) {
                if(empty($showed_is_gravity_forms_installed)) {
                    echo '<div id="message" class="updated">'.$message.'</div>';
                    $showed_is_gravity_forms_installed = true;
                }
            }
        } else {
            return true;
        }
        return $installed;
    }

    public static function plugin_row(){
        if(!self::is_gravityforms_supported()){
            $message = sprintf(__("%sGravity Forms%s is required. %sPurchase it today!%s"), "<a href='http://katz.si/gravityforms'>", "</a>", "<a href='http://katz.si/gravityforms'>", "</a>");
            self::display_plugin_message($message, true);
        }
    }

    public static function display_plugin_message($message, $is_error = false){
        $style = '';
        if($is_error)
            $style = 'style="background-color: #ffebe8;"';

        echo '</tr><tr class="plugin-update-tr"><td colspan="5" class="plugin-update"><div class="update-message" ' . $style . '>' . $message . '</div></td>';
    }

    public static function update_feed_active(){
        check_ajax_referer('rg_update_feed_active','rg_update_feed_active');
        $id = $_POST["feed_id"];
        $feed = GFSalesforceData::get_feed($id);
        GFSalesforceData::update_feed($id, $feed["form_id"], $_POST["is_active"], $feed["meta"]);
    }

    //--------------   Automatic upgrade ---------------------------------------------------

    function settings_link( $links, $file ) {
        static $this_plugin;
        if( ! $this_plugin ) $this_plugin = self::get_base_url();
        if ( $file == $this_plugin ) {
            $settings_link = '<a href="' . admin_url( 'admin.php?page=gf_salesforce' ) . '" title="' . __('Select the Gravity Form you would like to integrate with Salesforce. Contacts generated by this form will be automatically added to your Salesforce account.', 'gravity-forms-salesforce') . '">' . __('Feeds', 'gravity-forms-salesforce') . '</a>';
            array_unshift( $links, $settings_link ); // before other links
            $settings_link = '<a href="' . admin_url( 'admin.php?page=gf_settings&addon=Salesforce' ) . '" title="' . __('Configure your Salesforce settings.', 'gravity-forms-salesforce') . '">' . __('Settings', 'gravity-forms-salesforce') . '</a>';
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

    public static function is_debug() {
        if(is_null(self::$is_debug)) {
            self::$is_debug = !empty(self::$settings['debug']) && current_user_can('manage_options');
        }
        return self::$is_debug;
    }

    public static function is_notify_on_error() {
        $settings['notifyemail'] = trim(rtrim(self::$settings['notifyemail']));
        if(!empty($settings['notifyemail']) && is_email($settings['notifyemail'])) {
            return $settings['notifyemail'];
        } else {
            return false;
        }
    }

    public static function settings_page(){


        if(isset($_POST["uninstall"])){
            check_admin_referer("uninstall", "gf_salesforce_uninstall");
            self::uninstall();
            ?>
            <div class="updated fade" style="padding:20px;"><?php _e(sprintf("Gravity Forms Salesforce Add-On has been successfully uninstalled. It can be re-activated from the %splugins page%s.", "<a href='plugins.php'>","</a>"), "gravity-forms-salesforce")?></div>
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
                "username" => stripslashes($_POST["gf_salesforce_username"]),
                "password" => stripslashes($_POST["gf_salesforce_password"]),
                "securitytoken" => stripslashes($_POST["gf_salesforce_securitytoken"]),
                "debug" => isset($_POST["gf_salesforce_debug"]),
                "notifyemail" => trim(rtrim(esc_html($_POST["gf_salesforce_notifyemail"]))),
                'cache_time' => floatval($_POST["gf_salesforce_cache_time"])
            );
            update_option("gf_salesforce_settings", $settings);
        }
        else{
            $settings = get_option("gf_salesforce_settings");
        }

        $settings = wp_parse_args($settings, array(
                "username" => '',
                "password" => '',
                'securitytoken' => '',
                "debug" => false,
                'notify' => false,
                "notifyemail" => '',
                'cache_time' => 86400,
            ));

        $api = self::get_api($settings);


        $message = '';

        if(!empty($settings["username"]) && !empty($settings["password"]) && self::api_is_valid($api)){
            $message = sprintf(__("Valid configuration. Now go %sconfigure form integration with Salesforce%s!", "gravity-forms-salesforce"), '<a href="'.admin_url('admin.php?page=gf_salesforce').'">', '</a>');
            $class = "updated valid_credentials";
            $valid = true;
        } else if(!empty($settings["username"]) || !empty($settings["password"])){
            $message = is_string($api) ? $api : '';
            $valid = false;
            $class = "error invalid_credentials";
        } else if (empty($settings["username"]) && empty($settings["password"])) {
            $message = '';
            $valid = false;
            $class = 'updated notice';
        }

        if($message) {
            $message = str_replace('Api', 'API', $message);
            ?>
            <div id="message" class="<?php echo $class ?>"><?php echo wpautop($message); ?></div>
            <?php
        }
        ?>
        <form method="post" action="<?php echo remove_query_arg('refresh'); ?>">
            <?php wp_nonce_field("update", "gf_salesforce_update") ?>
            <?php if(!$valid)  { ?>
            <div class="delete-alert alert_gray">
                <h4><?php _e(sprintf('If you have issues with these steps, please %scontact Salesforce%s by calling (877) 820-7837 in the US or (919) 957-6150.', '<a href="http://www.salesforce.com/contact">', '</a>'), "gravity-forms-salesforce"); ?></h4>
                <h3><?php _e('How to set up integration:', "gravity-forms-salesforce"); ?></h3>
                <ol class="ol-decimal" style="margin-top:1em;">
                    <li style="list-style:decimal outside;"><?php echo sprintf(__('If you don\'t have your security token, %sfollow this link to Reset Your Security Token%s', "gravity-forms-salesforce"), '<a href="https://na9.salesforce.com/_ui/system/security/ResetApiTokenEdit" target="_blank">', '</a>', "gravity-forms-salesforce") ?></li>
                    <li style="list-style:decimal outside;"><?php _e('Come back to this settings page and enter your Security Token, Salesforce.com Username and Password.', "gravity-forms-salesforce") ?></li>
                    <li style="list-style:decimal outside;"><?php _e('Save these settings, and you should be done!', "gravity-forms-salesforce") ?></li>
                </ol>
            </div>
            <?php } ?>
            <h3><?php _e("Salesforce Account Information", "gravity-forms-salesforce") ?></h3>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="gf_salesforce_securitytoken"><?php _e("Security Token", "gravity-forms-salesforce"); ?></label> </th>
                    <td><input type="text" class="code" id="gf_salesforce_securitytoken" name="gf_salesforce_securitytoken" size="40" value="<?php echo !empty($settings["securitytoken"]) ? esc_attr($settings["securitytoken"]) : ''; ?>"/></td>
                </tr>
                <tr>
                    <th scope="row"><label for="gf_salesforce_username"><?php _e("Salesforce Username", "gravity-forms-salesforce"); ?></label> </th>
                    <td><input type="text" id="gf_salesforce_username" name="gf_salesforce_username" size="30" value="<?php echo empty($settings["username"]) ? '' : esc_attr($settings["username"]); ?>"/></td>
                </tr>
                <tr>
                    <th scope="row"><label for="gf_salesforce_password"><?php _e("Salesforce Password", "gravity-forms-salesforce"); ?></label> </th>
                    <td><input type="password" class="code" id="gf_salesforce_password" name="gf_salesforce_password" size="40" value="<?php echo !empty($settings["password"]) ? esc_attr($settings["password"]) : ''; ?>"/></td>
                </tr>
                <tr>
                    <th scope="row"><label for="gf_salesforce_debug"><?php _e("Debug Form Submissions for Administrators", "gravity-forms-salesforce"); ?></label> </th>
                    <td><input type="checkbox" id="gf_salesforce_debug" name="gf_salesforce_debug" size="40" value="1" <?php checked($settings["debug"], true); ?>/></td>
                </tr>
                <tr>
                    <th scope="row"><label for="gf_salesforce_notifyemail"><?php _e("Notify by Email on Errors", "gravity-forms-salesforce"); ?></label></th>
                    <td>
                        <input type="text" id="gf_salesforce_notifyemail" name="gf_salesforce_notifyemail" size="30" value="<?php echo empty($settings["notifyemail"]) ? '' : esc_attr($settings["notifyemail"]); ?>"/>
                        <span class="howto"><?php _e('An email will be sent to this email address if an entry is not properly added to Salesforce. Leave blank to disable.', 'gravity-forms-salesforce'); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gf_salesforce_cache_time"><?php _e("Remote Cache Time", "gravity-forms-salesforce"); ?></label><span class="howto"><?php _e("This is an advanced setting. You likely won't need to change this.", "gravity-forms-salesforce"); ?></span></th>
                    <td>

                        <select name="gf_salesforce_cache_time" id="gf_salesforce_cache_time">
                            <option value="60" <?php selected($settings["cache_time"] == '60', true); ?>><?php _e('One Minute (for testing only!)', 'gravity-forms-salesforce'); ?></option>
                            <option value="3600" <?php selected($settings["cache_time"] == '3600', true); ?>><?php _e('One Hour', 'gravity-forms-salesforce'); ?></option>
                            <option value="21600" <?php selected($settings["cache_time"] == '21600', true); ?>><?php _e('Six Hours', 'gravity-forms-salesforce'); ?></option>
                            <option value="43200" <?php selected($settings["cache_time"] == '43200', true); ?>><?php _e('12 Hours', 'gravity-forms-salesforce'); ?></option>
                            <option value="86400" <?php selected($settings["cache_time"] == '86400', true); ?>><?php _e('1 Day', 'gravity-forms-salesforce'); ?></option>
                            <option value="172800" <?php selected($settings["cache_time"] == '172800', true); ?>><?php _e('2 Days', 'gravity-forms-salesforce'); ?></option>
                            <option value="259200" <?php selected($settings["cache_time"] == '259200', true); ?>><?php _e('3 Days', 'gravity-forms-salesforce'); ?></option>
                            <option value="432000" <?php selected($settings["cache_time"] == '432000', true); ?>><?php _e('5 Days', 'gravity-forms-salesforce'); ?></option>
                            <option value="604800" <?php selected(empty($settings["cache_time"]) || $settings["cache_time"] == '604800', true); ?>><?php _e('1 Week', 'gravity-forms-salesforce'); ?></option>
                        </select>
                        <span class="howto"><?php _e('How long should form and field data be stored? This affects how often remote picklists will be checked for the Live Remote Field Mapping feature.', 'gravity-forms-salesforce'); ?></span>
                        <span class="howto"><?php _e(sprintf("%sRefresh now%s.", '<a href="'.add_query_arg('refresh', 'transients').'">','</a>'), "gravity-forms-salesforce"); ?></span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" ><input type="submit" name="gf_salesforce_submit" class="button-primary" value="<?php _e("Save Settings", "gravity-forms-salesforce") ?>" /></td>
                </tr>
            </table>
            <div>

            </div>
        </form>

        <form action="" method="post">
            <?php wp_nonce_field("uninstall", "gf_salesforce_uninstall") ?>
            <?php if(GFCommon::current_user_can_any("gravityforms_salesforce_uninstall")){ ?>
                <div class="hr-divider"></div>

                <h3><?php _e("Uninstall Salesforce Add-On", "gravity-forms-salesforce") ?></h3>
                <div class="delete-alert"><?php _e("Warning! This operation deletes ALL Salesforce Feeds.", "gravity-forms-salesforce") ?>
                    <?php
                    $uninstall_button = '<input type="submit" name="uninstall" value="' . __("Uninstall Salesforce Add-On", "gravity-forms-salesforce") . '" class="button" onclick="return confirm(\'' . __("Warning! ALL Salesforce Feeds will be deleted. This cannot be undone. \'OK\' to delete, \'Cancel\' to stop", "gravity-forms-salesforce") . '\');"/>';
                    echo apply_filters("gform_salesforce_uninstall_button", $uninstall_button);
                    ?>
                </div>
            <?php } ?>
        </form>
        <?php
    }

    public static function salesforce_page(){
        $view = isset($_GET["view"]) ? $_GET["view"] : '';
        if($view == "edit")
            self::edit_page($_GET["id"]);
        else
            self::list_page();
    }

    //Displays the Salesforce feeds list page
    private static function list_page(){
        if(!self::is_gravityforms_supported()){
            die(__(sprintf("The Salesforce Add-On requires Gravity Forms %s. Upgrade automatically on the %sPlugin page%s.", self::$min_gravityforms_version, "<a href='plugins.php'>", "</a>"), "gravity-forms-salesforce"));
        }

        if(isset($_POST["action"]) && $_POST["action"] == "delete"){
            check_admin_referer("list_action", "gf_salesforce_list");

            $id = absint($_POST["action_argument"]);
            GFSalesforceData::delete_feed($id);
            ?>
            <div class="updated fade" style="padding:6px"><?php _e("Feed deleted.", "gravity-forms-salesforce") ?></div>
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
            <div class="updated fade" style="padding:6px"><?php _e("Feeds deleted.", "gravity-forms-salesforce") ?></div>
            <?php
        }

        $api = self::get_api();
        ?>
        <div class="wrap">
            <img alt="<?php _e("Salesforce.com Feeds", "gravity-forms-salesforce") ?>" src="<?php echo self::get_base_url()?>/images/salesforce-50x50.png" style="float:left; margin:0 7px 0 0;" width="50" height="50" />
            <h2><?php _e("Salesforce.com Feeds", "gravity-forms-salesforce"); ?>
            <a class="button add-new-h2" href="admin.php?page=gf_salesforce&view=edit&id=0"><?php _e("Add New", "gravity-forms-salesforce") ?></a>
            </h2>

            <?php
                if(!self::api_is_valid($api)){
            ?>
            <div class="error" id="message" style="margin-top:20px;">
                <h3><?php _e('Salesforce Error', "gravity-forms-salesforce");?></h3>
                <p><?php echo empty($api) ? __(sprintf("To get started, please configure your %sSalesforce Settings%s.", '<a href="admin.php?page=gf_settings&addon=Salesforce">', "</a>"), "gravity-forms-salesforce") : $api; ?></p>
            </div>
            <?php
                } else {
            ?>
            <div class="updated" id="message" style="margin-top:20px;">
                <p><?php _e('Do you like this free plugin? <a href="http://katz.si/gfsfrate">Please review it on WordPress.org</a>! <small class="description alignright">Note: You must be logged in to WordPress.org to leave a review!</small>', 'gravity-forms-salesforce'); ?></p>
            </div>
            <?php } ?>
            <div class="clear"></div>
            <ul class="subsubsub" style="margin-top:0;">
                <li><a href="<?php echo admin_url('admin.php?page=gf_settings&addon=Salesforce'); ?>">Salesforce Settings</a> |</li>
                <li><a href="<?php echo admin_url('admin.php?page=gf_salesforce'); ?>" class="current">Salesforce Feeds</a></li>
            </ul>

            <form id="feed_form" method="post">
                <?php wp_nonce_field('list_action', 'gf_salesforce_list') ?>
                <input type="hidden" id="action" name="action"/>
                <input type="hidden" id="action_argument" name="action_argument"/>

                <div class="tablenav">
                    <div class="alignleft actions" style="padding:8px 0 7px; 0">
                        <label class="hidden" for="bulk_action"><?php _e("Bulk action", "gravity-forms-salesforce") ?></label>
                        <select name="bulk_action" id="bulk_action">
                            <option value=''> <?php _e("Bulk action", "gravity-forms-salesforce") ?> </option>
                            <option value='delete'><?php _e("Delete", "gravity-forms-salesforce") ?></option>
                        </select>
                        <?php
                        echo '<input type="submit" class="button" value="' . __("Apply", "gravity-forms-salesforce") . '" onclick="if( jQuery(\'#bulk_action\').val() == \'delete\' && !confirm(\'' . __("Delete selected feeds? ", "gravity-forms-salesforce") . __("\'Cancel\' to stop, \'OK\' to delete.", "gravity-forms-salesforce") .'\')) { return false; } return true;"/>';
                        ?>
                    </div>
                </div>
                <table class="widefat fixed" cellspacing="0">
                    <thead>
                        <tr>
                            <th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th>
                            <th scope="col" id="active" class="manage-column check-column"></th>
                            <th scope="col" class="manage-column"><?php _e("Form", "gravity-forms-salesforce") ?></th>
                            <th scope="col" class="manage-column"><?php _e("Salesforce Object", "gravity-forms-salesforce") ?></th>
                        </tr>
                    </thead>

                    <tfoot>
                        <tr>
                            <th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th>
                            <th scope="col" id="active" class="manage-column check-column"></th>
                            <th scope="col" class="manage-column"><?php _e("Form", "gravity-forms-salesforce") ?></th>
                            <th scope="col" class="manage-column"><?php _e("Salesforce Object", "gravity-forms-salesforce") ?></th>
                        </tr>
                    </tfoot>

                    <tbody class="list:user user-list">
                        <?php

                        $settings = GFSalesforceData::get_feeds();
                        if(is_array($settings) && !empty($settings)){

                            foreach($settings as $setting){
                                ?>
                                <tr class='author-self status-inherit' valign="top">
                                    <th scope="row" class="check-column"><input type="checkbox" name="feed[]" value="<?php echo $setting["id"] ?>"/></th>
                                    <td><img src="<?php echo self::get_base_url() ?>/images/active<?php echo intval($setting["is_active"]) ?>.png" alt="<?php echo $setting["is_active"] ? __("Active", "gravity-forms-salesforce") : __("Inactive", "gravity-forms-salesforce");?>" title="<?php echo $setting["is_active"] ? __("Active", "gravity-forms-salesforce") : __("Inactive", "gravity-forms-salesforce");?>" onclick="ToggleActive(this, <?php echo $setting['id'] ?>); " /></td>
                                    <td class="column-title">
                                        <a href="admin.php?page=gf_salesforce&view=edit&id=<?php echo $setting["id"] ?>" title="<?php _e("Edit", "gravity-forms-salesforce") ?>"><?php echo $setting["form_title"] ?></a>
                                        <div class="row-actions">
                                            <span class="edit">
                                            <a title="Edit this setting" href="admin.php?page=gf_salesforce&view=edit&id=<?php echo $setting["id"] ?>" title="<?php _e("Edit", "gravity-forms-salesforce") ?>"><?php _e("Edit", "gravity-forms-salesforce") ?></a>
                                            |
                                            </span>

                                            <span class="edit">
                                            <a title="<?php _e("Delete", "gravity-forms-salesforce") ?>" href="javascript: if(confirm('<?php _e("Delete this feed? ", "gravity-forms-salesforce") ?> <?php _e("\'Cancel\' to stop, \'OK\' to delete.", "gravity-forms-salesforce") ?>')){ DeleteSetting(<?php echo $setting["id"] ?>);}"><?php _e("Delete", "gravity-forms-salesforce")?></a>
                                            |
                                            </span>

                                            <span class="edit">
                                            <a title="<?php _e("Edit Form", "gravity-forms-salesforce") ?>" href="<?php echo add_query_arg(array('page' => 'gf_edit_forms', 'id' => $setting['form_id']), admin_url('admin.php')); ?>"><?php _e("Edit Form", "gravity-forms-salesforce")?></a>
                                            |
                                            </span>

                                            <span class="edit">
                                            <a title="<?php _e("Preview Form", "gravity-forms-salesforce") ?>" href="<?php echo add_query_arg(array('gf_page' => 'preview', 'id' => $setting['form_id']), site_url()); ?>"><?php _e("Preview Form", "gravity-forms-salesforce")?></a>
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
                                        <?php _e(sprintf("You don't have any Salesforce feeds configured. Let's go %screate one%s!", '<a href="'.admin_url('admin.php?page=gf_salesforce&view=edit&id=0').'">', "</a>"), "gravity-forms-salesforce"); ?>
                                    </td>
                                </tr>
                                <?php
                            }
                            else{
                                ?>
                                <tr>
                                    <td colspan="4" style="padding:20px;">
                                        <?php _e(sprintf("To get started, please configure your %sSalesforce Settings%s.", '<a href="admin.php?page=gf_settings&addon=Salesforce">', "</a>"), "gravity-forms-salesforce"); ?>
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
            function ToggleActive(img, feed_id){
                var is_active = img.src.indexOf("active1.png") >=0
                if(is_active){
                    img.src = img.src.replace("active1.png", "active0.png");
                    jQuery(img).attr('title','<?php _e("Inactive", "gravity-forms-salesforce") ?>').attr('alt', '<?php _e("Inactive", "gravity-forms-salesforce") ?>');
                }
                else{
                    img.src = img.src.replace("active0.png", "active1.png");
                    jQuery(img).attr('title','<?php _e("Active", "gravity-forms-salesforce") ?>').attr('alt', '<?php _e("Active", "gravity-forms-salesforce") ?>');
                }

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
        </script>
        <?php
    }

    static private function api_is_valid($api) {
        if($api === false || is_string($api) || !empty($api->lastError)) {
            return false;
        }
        if(is_a($api, 'SforcePartnerClient') && method_exists($api, 'getLastResponseHeaders') && preg_match('/200\sOK/ism', $api->getLastResponseHeaders())) {
            return true;
        }
        return false;
    }

    public static function get_api($settings = array()){

        // If it's already set, use it.
        if(!empty(self::$api)) { return self::$api; }

        if(!is_array($settings) || empty($settings)) {
            $settings = self::$settings;
            if(!is_array($settings) || empty($settings)) {
                $settings = get_option("gf_salesforce_settings");
            }
        }

        // If the settings aren't set...return false
        if(!is_array($settings) || empty($settings)) {
            return false;
        }

        extract($settings);

        $libpath = plugin_dir_path(__FILE__).'Force.com-Toolkit-for-PHP/soapclient/';

        if(!class_exists("SforcePartnerClient")) {
            require_once $libpath.'SforcePartnerClient.php';
        }

        try {
            //This is instantiating the service used for the sfdc api
            $mySforceConnection = new SforcePartnerClient();

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
                apply_filters('gf_salesforce_wsdl', $libpath.'partner.wsdl.xml'),
                apply_filters('gf_salesforce_proxy', NULL),
                apply_filters('gf_salesforce_soap_options', array())
            );

            $mylogin = $mySforceConnection->login($username,$password.$securitytoken);

            $mySforceConnection = apply_filters('gf_salesforce_connection', $mySforceConnection);

            self::$api = $mySforceConnection;

            return $mySforceConnection;

        } catch(Exception $e) {
            return isset($e->faultstring) ? $e->faultstring : false;
        }
    }

    public function r($content, $die = false) {
        echo '<pre>';
        print_r($content);
        echo '</pre>';
        if($die) { die(); }
        return;
    }

    public function getField($objectType = 'account', $field_name = '') {

        // Cache the field to save lookups.
        // Sha1 is to ensure length is correct.
        $field = get_site_transient('sfgf_'.sha1('lists_'.$objectType.'_'.$field_name));
        if($field && !is_wp_error($field) && !(current_user_can('administrator') && (isset($_REQUEST['refresh']) || isset($_REQUEST['cache'])))) { return $field; }

        $fields = self::getFields($objectType);

        foreach($fields as $field) {
            if($field['tag'] === $field_name) {
                set_site_transient('sfgf_'.sha1('lists_'.$objectType.'_'.$field_name), $field, self::$settings['cache_time']);
                return $field;
            }
        }
    }

    public function getFields($objectType = 'account', $type = null) {
        $lists = maybe_unserialize(get_site_transient('sfgf_lists_fields_'.$objectType));
        if($lists && !empty($lists) && is_array($lists) && (!isset($_REQUEST['refresh']) || (isset($_REQUEST['refresh']) && $_REQUEST['refresh'] !== 'lists'))) {
            foreach($lists as $key => $list) {
                // If you only want one type of field, and it's not that type, keep going
                if(!empty($type)) {
                    if(
                        (is_string($type) && $list['type'] !== $type) ||
                        (is_array($type) && !in_array($list['type'], $type))
                    ) {
                        unset($lists[$key]);
                    }
                }
            }
            return $lists;
        }

        $api = self::get_api();

        if(!self::api_is_valid($api)) { return false; }

        $accountdescribe = $api->describeSObject($objectType);

        if(!is_object($accountdescribe) || !isset($accountdescribe->fields)) { return false; }

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
            if(!empty($type)) {
                if(
                    (is_string($type) && $Field->type !== $type) ||
                    (is_array($type) && !in_array($Field->type, $type))
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

    public function getObjectTypes() {

        $lists = get_site_transient('sfgf_objects');

        if($lists && (!isset($_REQUEST['refresh']) || (isset($_REQUEST['refresh']) && $_REQUEST['refresh'] !== 'lists'))) {
            return $lists;
        }

        $api = self::get_api();

        if(!self::api_is_valid($api)) { return false; }

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
            return false;
        }

    }

    private static function edit_page(){
        ?>
        <style type="text/css">
            label span.howto { cursor: default; }
            .salesforce_col_heading{padding-bottom:2px; border-bottom: 1px solid #ccc; font-weight:bold; width:50%;}
            #salesforce_field_list table { width: 400px; border-collapse: collapse; margin-top: 1em; }
            .salesforce_field_cell {padding: 6px 17px 0 0; margin-right:15px;}
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
            <img alt="<?php _e("Salesforce Feeds", "gravity-forms-salesforce") ?>" src="<?php echo self::get_base_url()?>/images/salesforce-50x50.png" style="float:left; margin:15px 7px 0 0;"/>
            <h2><?php _e("Salesforce Feeds", "gravity-forms-salesforce"); ?></h2>
            <ul class="subsubsub">
                <li><a href="<?php echo admin_url('admin.php?page=gf_settings&addon=Salesforce'); ?>">Salesforce Settings</a> |</li>
                <li><a href="<?php echo admin_url('admin.php?page=gf_salesforce'); ?>">Salesforce Feeds</a></li>
            </ul>
        <div class="clear"></div>
        <?php
        //getting Salesforce API

        $api = self::get_api();

        //ensures valid credentials were entered in the settings page
        if(!self::api_is_valid($api)) {
            ?>
            <div class="error" id="message" style="margin-top:20px;"><?php echo wpautop(sprintf(__("We are unable to login to Salesforce with the provided username and API key. Please make sure they are valid in the %sSettings Page%s", "gravity-forms-salesforce"), "<a href='?page=gf_settings&addon=Salesforce'>", "</a>")); ?></div>
            <?php
            return;
        }

        //getting setting id (0 when creating a new one)
        $id = !empty($_POST["salesforce_setting_id"]) ? $_POST["salesforce_setting_id"] : absint($_GET["id"]);
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

            $merge_vars = (array)self::getFields($_POST['gf_salesforce_list']);

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
        if(!function_exists('gform_tooltip')) {
            require_once(GFCommon::get_base_path() . "/tooltips.php");
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
                    echo __("Could not load Salesforce contact lists. <br/>Error: ", "gravity-forms-salesforce");
                    echo isset($api->errorMessage) ? $api->errorMessage : '';
                } else {
?>
                    <select id="gf_salesforce_list" name="gf_salesforce_list" onchange="SelectList(jQuery(this).val()); SelectForm(jQuery(this).val(), jQuery('#gf_salesforce_form').val());">
                        <option value=""><?php _e("Select a Salesforce Object", "gravity-forms-madmimi"); ?></option>
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
            <div id="salesforce_form_container" valign="top" class="margin_vertical_10" <?php echo empty($config["meta"]["contact_object_name"]) ? "style='display:none;'" : "" ?>>
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
                    $selected = absint($form->id) == $config["form_id"] ? "selected='selected'" : "";
                    ?>
                    <option value="<?php echo absint($form->id) ?>"  <?php echo $selected ?>><?php echo esc_html($form->title) ?></option>
                    <?php
                }
                ?>
                </select>
                &nbsp;&nbsp;
                <img src="<?php echo GFSalesforce::get_base_url() ?>/images/loading.gif" id="salesforce_wait" style="display: none;"/>
            </div>
            <div class="clear"></div>
            <div id="salesforce_field_group" valign="top" <?php echo empty($config["meta"]["contact_object_name"]) || empty($config["form_id"]) ? "style='display:none;'" : "" ?>>
                <div id="salesforce_field_container" valign="top" class="margin_vertical_10" >
                    <h2><?php _e('3. Map form fields to Salesforce fields.', "gravity-forms-salesforce"); ?></h2>
                    <h3 class="description"><?php _e('About field mapping:', "gravity-forms-salesforce"); ?></h2>
                    <label for="salesforce_fields" class="left_header"><?php _e("Standard Fields", "gravity-forms-salesforce"); ?> <?php gform_tooltip("salesforce_map_fields") ?></label>
                    <div id="salesforce_field_list">
                    <?php
                    if(!empty($config["form_id"])){

                        //getting list of all Salesforce merge variables for the selected contact list
                        if(empty($merge_vars))
                            $merge_vars = self::getFields($config['meta']['contact_object_name']);

                        //getting field map UI
                        echo self::get_field_mapping($config, $config["form_id"], $merge_vars);

                        //getting list of selection fields to be used by the optin
                        $form_meta = RGFormsModel::get_form_meta($config["form_id"]);
                        $selection_fields = GFCommon::get_selection_fields($form_meta, $config["meta"]["optin_field_id"]);
                    }
                    ?>
                    </div>
                    <div class="clear"></div>
                </div>

                <div id="salesforce_optin_container" valign="top" class="margin_vertical_10">
                    <label for="salesforce_optin" class="left_header"><?php _e("Opt-In Condition", "gravity-forms-salesforce"); ?> <?php gform_tooltip("salesforce_optin_condition") ?></label>
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

                <div id="salesforce_submit_container" class="margin_vertical_10">
                    <input type="submit" name="gf_salesforce_submit" value="<?php echo empty($id) ? __("Save Feed", "gravity-forms-salesforce") : __("Update Feed", "gravity-forms-salesforce"); ?>" class="button-primary"/>
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

            /*
jQuery(document).ready(function() {

                SelectList(jQuery('#gf_salesforce_list').val());

                SelectForm(jQuery('#gf_salesforce_list').val(), jQuery('#gf_salesforce_form').val());

            });

*/
            function SelectList(listId){
                if(listId){
                    jQuery("#salesforce_form_container").slideDown();
                   // jQuery("#gf_salesforce_form").val("");
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

                jQuery("#salesforce_wait").show();
                jQuery("#salesforce_field_group").slideUp();

                var mysack = new sack("<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin-ajax.php" );
                mysack.execute = 1;
                mysack.method = 'POST';
                mysack.setVar( "action", "gf_select_salesforce_form" );
                mysack.setVar( "gf_select_salesforce_form", "<?php echo wp_create_nonce("gf_select_salesforce_form") ?>" );
                mysack.setVar( "objectType", listId);
                mysack.setVar( "form_id", formId);
                mysack.encVar( "cookie", document.cookie, false );
                mysack.onError = function() {jQuery("#salesforce_wait").hide(); alert('<?php _e("Ajax error while selecting a form", "gravity-forms-salesforce") ?>' )};
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
                jQuery("#salesforce_wait").hide();
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
            die("EndSelectForm();");
        }

        $form_id =  intval($_POST["form_id"]);

        $setting_id =  0;

        //getting list of all Salesforce merge variables for the selected contact list
        $merge_vars = @self::getFields($_POST['objectType']);;

        if(empty($merge_vars)) {
            echo sprintf("alert('There was an error retrieving fields for the %s Object');", esc_js($_POST['objectType']));
            die(" EndSelectForm();");
        }

        //getting configuration
        $config = GFSalesforceData::get_feed($setting_id);

        //getting field map UI
        $str = self::get_field_mapping($config, $form_id, $merge_vars);

        //fields meta
        $form = RGFormsModel::get_form_meta($form_id);

        die("EndSelectForm('" .str_replace("'", "\'", $str). "', " . GFCommon::json_encode($form) . ");");
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
            $required = $var["req"] === true ? "<span class='gfield_required' title='This field is required.'>(Required)</span>" : "";
            $error_class = $var["req"] === true && empty($selected_field) && !empty($_POST["gf_salesforce_submit"]) ? " feeds_validation_error" : "";
            $field_desc = '';
            if(self::show_field_type_desc($var['type'])) {
                $field_desc = '<div>Type: '.$var["type"].'</div>';
            }
            if(!empty($var["length"])) { $field_desc .= '<div>Max Length: '.$var["length"].'</div>'; }
            $row = "<tr class='$error_class'><td class='salesforce_field_cell'><label for='salesforce_map_field_{$var['tag']}'>" . stripslashes( $var["name"] )  . " $required</label><small class='description' style='display:block'>{$field_desc}</small></td><td class='salesforce_field_cell'>" . self::get_mapped_field_list($var["tag"], $selected_field, $form_fields) . "</td></tr>";

            $str .= $row;

        } // End foreach merge var.

        $str .= "</tbody></table>";

        return $str;
    }

    private function getNewTag($tag, $used = array()) {
        if(isset($used[$tag])) {
            $i = 1;
            while($i < 1000) {
                if(!isset($used[$tag.'_'.$i])) {
                    return $tag.'_'.$i;
                }
                $i++;
            }
        }
        return $tag;
    }

    public static function get_form_fields($form_id){
        $form = RGFormsModel::get_form_meta($form_id);
        $fields = array();

        //Adding default fields
        array_push($form["fields"],array("id" => "date_created" , "label" => __("Entry Date", "gravity-forms-salesforce")));
        array_push($form["fields"],array("id" => "ip" , "label" => __("User IP", "gravity-forms-salesforce")));
        array_push($form["fields"],array("id" => "source_url" , "label" => __("Source Url", "gravity-forms-salesforce")));

        if(is_array($form["fields"])){
            foreach($form["fields"] as $field){
                if(isset($field["inputs"]) && is_array($field["inputs"]) && $field['type'] !== 'checkbox' && $field['type'] !== 'select'){

                    //If this is an address field, add full name to the list
                    if(RGFormsModel::get_input_type($field) == "address")
                        $fields[] =  array($field["id"], GFCommon::get_label($field) . " (" . __("Full" , "gravity-forms-salesforce") . ")");

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
        $str = "<select name='$field_name' id='$field_name'><option value=''>" . __("", "gravity-forms-salesforce") . "</option>";
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

    public static function export($entry, $form){
        //Login to Salesforce
        $api = self::get_api();

        if(!self::api_is_valid($api) || !preg_match('/200\sOK/ism', $api->getLastResponseHeaders())) {
            do_action('gf_salesforce_error', 'export', $api);
            return;
        }

        //loading data class
        require_once(self::get_base_path() . "/data.php");

        //getting all active feeds
        $feeds = GFSalesforceData::get_feed_by_form($form["id"], true);

        foreach($feeds as $feed){
            //only export if user has opted in
            if(self::is_optin($form, $feed)) {
                self::export_feed($entry, $form, $feed, $api);
            }
        }
    }

    public static function export_feed($entry, $form, $feed, $api){

        if(empty($feed["meta"]["contact_object_name"])) {
            return false;
        }

        $contactId = self::create($entry, $form, $feed, $api);

        return $contactId;
    }

    private function create($entry, $form, $feed, $api) {

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

                    $merge_vars[$var_tag] = $value;

            } else {

                    // This is for checkboxes
                    $elements = array();
                    foreach($entry as $key => $value) {
                        if(floor($key) == floor($field_id) && !empty($value)) {
                            $elements[] = htmlspecialchars($value);
                        }
                    }
                    $merge_vars[$var_tag] = implode(';',array_map('htmlspecialchars', $elements));
                }
            }
            
            $merge_vars[ $var_tag ] = apply_filters( 'gf_salesforce_mapped_value_' . $var_tag, $merge_vars[ $var_tag ], $field, $var_tag, $form, $entry );
            $merge_vars[ $var_tag ] = apply_filters( 'gf_salesforce_mapped_value', $merge_vars[ $var_tag ], $field, $var_tag, $form, $entry );

        }

        $merge_vars = apply_filters( 'gf_salesforce_create_data', $merge_vars, $form, $entry );

        // Make sure the charset is UTF-8 for Salesforce.
        $merge_vars = array_map(array('GFSalesforce', '_convert_to_utf_8'), $merge_vars);

        // Don't send merge_vars that are empty. It can cause problems with Salesforce strict typing.  For example,
        // if the form has a text field where a number should go, but that number isn't always required, when it's
        // not supplied, we don't want to send <var></var> to Salesforce. It might choke because it expects a Double
        // data type, not an empty string
        $merge_vars = array_filter($merge_vars, array('GFSalesforce', '_remove_empty_fields'));

        $account = new SObject();

        $account->fields = $merge_vars;

        // Object type
        $account->type = $feed['meta']['contact_object_name'];

        try {
            $result = $api->create(array($account));
            $api_exception = '';
        } catch (Exception $e) {
            $api_exception = "
                Message: "  . $e->getMessage() .
                "\nFaultstring: " . $e->faultstring .
                "\nFile: " . $e->getFile() .
                "\nLine: " . $e->getLine() .
                "\nArgs: ". serialize($merge_vars) .
                "\nTrace: " . serialize($e->getTrace());
        }

        $debug = '';
        if(self::is_debug()) {
            $debug = '<pre>'.print_r(array(
                    'Form Entry Data' => $entry,
                    'Form Meta Data' => $form,
                    'Salesforce Feed Meta Data' => $feed,
                    'Salesforce Posted Merge Data' => $merge_vars,
                    'Posted Data ($_POST)' => $_POST,
                    'result' => $result[0],
                    '$api' => $api,
                    '$api_exception' => $api_exception,
                ), true).'</pre>';
        }

        if  (isset($result[0]) && !empty($result[0]->success)) {
            if(self::is_debug()) {
                echo '<h2>Success</h2>'.'<h3>This is only visible to administrators. To disable this code from being displayed, uncheck the "Debug Form Submissions for Administrators" box in the <a href="'.admin_url('admin.php?page=gf_settings&addon=Salesforce').'">Gravity Forms Salesforce</a> settings.</h3>'.$debug;
            }
            gform_update_meta($entry['id'], 'salesforce_id', $result[0]->id);
            self::add_note($entry["id"], sprintf(__('Successfully added to Salesforce with ID #%s . View entry at %s', 'gravity-forms-salesforce'), $result[0]->id, 'https://na9.salesforce.com/'.$result[0]->id));
            return $result[0]->id;
        } else {

            $errors = $result[0]->errors[0];

            if(self::is_debug()) {
                echo '<h2>Error</h2>'.$debug;
                '<h3>This is only visible to administrators. To disable this code from being displayed, uncheck the "Debug Form Submissions for Administrators" box in the <a href="'.admin_url('admin.php?page=gf_settings&addon=Salesforce').'">Gravity Forms Salesforce</a> settings.</h3>';
                echo '<h2>Errors</h2><pre>'.print_r($errors, true).'</pre>';
            }

            if($email = self::is_notify_on_error()) {
                $message = sprintf(apply_filters('gravityforms_salesforce_notify_on_error_message', __("<h3>Error Adding To Salesforce</h3><p>There was an error when attempting to add <a href='%s'>Entry #%s</a> from the form \"%s\"</p>", 'gravity-forms-salesforce'), $errors, $entry, $form), admin_url('admin.php?page=gf_entries&view=entry&id='.$entry['form_id'].'&lid='.$entry['id']), $entry['id'], $form['title']);
                $headers = "Content-type: text/html; charset=" . get_option('blog_charset') . "\r\n";
                wp_mail($email,__('Error adding to Salesforce', 'gravity-forms-salesforce'), $message, $headers);
            }

            self::add_note($entry["id"], sprintf(__('Errors when adding to Salesforce: %s', 'gravity-forms-salesforce'), $errors->message.$api_exception));

            return false;
        }
    }

    function _remove_empty_fields($merge_var) {
        return (
            (function_exists('mb_strlen') && mb_strlen($merge_var) > 0) ||
            !function_exists('mb_strlen') && strlen($merge_var) > 0
        );
    }

    function _convert_to_utf_8($string) {

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


    function entry_info_link_to_salesforce($form_id, $lead) {
        $salesforce_id = gform_get_meta($lead['id'], 'salesforce_id');
        if(!empty($salesforce_id)) {
            echo sprintf(__('Salesforce ID: <a href="https://na9.salesforce.com/'.$salesforce_id.'">%s</a><br /><br />', 'gravity-forms-salesforce'), $salesforce_id);
        }
    }

    private function add_note($id, $note) {

        if(!apply_filters('gravityforms_salesforce_add_notes_to_entries', true)) { return; }

        RGFormsModel::add_note($id, 0, __('Gravity Forms Salesforce Add-on'), $note);
    }

    public static function uninstall(){

        //loading data lib
        require_once(self::get_base_path() . "/data.php");

        if(!GFSalesforce::has_access("gravityforms_salesforce_uninstall"))
            die(__("You don't have adequate permission to uninstall Salesforce Add-On.", "gravity-forms-salesforce"));

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

    public static function is_optin($form, $settings){
        $config = $settings["meta"];
        $operator = $config["optin_operator"];

        $field = RGFormsModel::get_field($form, $config["optin_field_id"]);
        $field_value = RGFormsModel::get_field_value($field, array());
        $is_value_match = is_array($field_value) ? in_array($config["optin_value"], $field_value) : $field_value == $config["optin_value"];

        return  !$config["optin_enabled"] || empty($field) || ($operator == "is" && $is_value_match) || ($operator == "isnot" && !$is_value_match);
    }


    private static function is_gravityforms_installed(){
        return class_exists("RGForms");
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
        return plugins_url(null, __FILE__);
    }

    //Returns the physical path of the plugin's root folder
    static protected function get_base_path(){
        $folder = basename(dirname(__FILE__));
        return WP_PLUGIN_DIR . "/" . $folder;
    }


}

