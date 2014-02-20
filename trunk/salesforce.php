<?php
/*
Plugin Name: Gravity Forms Salesforce - Web to Lead (**OLD VERSION**)
Description: This version is provided for backward compatibility only. Please use the new plugin, named "Gravity Forms Salesforce - Web-to-Lead Add-On". This version will be removed in the future.
Version: 2.6.3.4
Requires at least: 3.3
Author: Katz Web Services, Inc.
Author URI: http://www.katzwebservices.com

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

add_action('init',  array('GFSalesforceWebToLead', 'init'));

class GFSalesforceWebToLead {

    private static $name = "Gravity Forms Salesforce Add-On";
    private static $path = "gravity-forms-salesforce/salesforce.php";
    private static $url = "http://www.gravityforms.com";
    private static $slug = "gravity-forms-salesforce";
    private static $version = "2.6.3.4";
    private static $min_gravityforms_version = "1.3.9";

    //Plugin starting point. Will load appropriate files
    public static function init(){
        global $pagenow;

        if($pagenow === 'plugins.php' && is_admin()) {
            add_action("admin_notices", array('GFSalesforceWebToLead', 'is_gravity_forms_installed'), 10);
        }

        if(self::is_gravity_forms_installed(false, false) === 0){
            add_action('after_plugin_row_' . self::$path, array('GFSalesforceWebToLead', 'plugin_row') );
           return;
        }

        if(!self::is_gravityforms_supported()){
           return;
        }

        // The newer add-on is being used.
        if(class_exists('KWSGFWebToLeadAddon')) {
            add_action("admin_notices", array('GFSalesforceWebToLead', 'newer_version_installed'), 10);
        }

        if(is_admin()){

            //creates a new Settings page on Gravity Forms' settings screen
            if(self::has_access("gravityforms_salesforce")){
                RGForms::add_settings_page("Salesforce Web to Lead", array("GFSalesforceWebToLead", "settings_page"), self::get_base_url() . "/images/salesforce-50x50.png");
            }
        }

        //creates the subnav left menu
        add_filter("gform_addon_navigation", array('GFSalesforceWebToLead', 'create_menu'));

        if(self::is_salesforce_page()){

            //enqueueing sack for AJAX requests
            wp_enqueue_script(array("sack"));
            wp_enqueue_style('gravityforms-admin', GFCommon::get_base_url().'/css/admin.css');
         } else if(in_array(RG_CURRENT_PAGE, array("admin-ajax.php"))){

            add_action('wp_ajax_rg_update_feed_active', array('GFSalesforceWebToLead', 'update_feed_active'));
            add_action('wp_ajax_gf_select_salesforce_form', array('GFSalesforceWebToLead', 'select_salesforce_form'));
        } elseif(in_array(RG_CURRENT_PAGE, array('admin.php'))) {
            add_action('admin_head', array('GFSalesforceWebToLead', 'show_salesforce_status'));
        } else {
            add_action("gform_pre_submission", array('GFSalesforceWebToLead', 'push'), 10, 2); //handling post submission.
        }

        #add_action("gform_field_advanced_settings", array('GFSalesforceWebToLead',"add_salesforce_editor_field"), 10, 2); // For future use

        add_action("gform_editor_js", array('GFSalesforceWebToLead', 'add_form_option_js'), 10);
        add_action("gform_properties_settings", array('GFSalesforceWebToLead', 'add_form_option_js'), 100);


        add_filter('gform_tooltips', array('GFSalesforceWebToLead', 'add_form_option_tooltip'));

        add_filter("gform_confirmation", array('GFSalesforceWebToLead', 'confirmation_error'));
    }

    function newer_version_installed() {
        $message = __('<h3>You are running two versions of the Gravity Forms Salesforce Web-to-Lead Add-on at the same time.</h3><p>Please convert your existing form settings into Feeds then disable the old version of the plugin (look for "**OLD VERSION**" in the plugin name).</p><h4>To convert your old Salesforce form settings:</h4><ol class="ol-decimal"><li>Go to <a href="'.admin_url('admin.php?page=gf_edit_forms').'">Forms</a></li><li>Click on the name of the form you want to link with Salesforce</li><li>Hover over "Form Settings" and click on the "Salesforce: Web to Lead" link</li><li>Follow the instructions to create a Feed.</li></ol><p>Once you do this, <strong>disable the old version of the plugin on the Plugins page</strong> and you will no longer see this message.</p>', 'gravity-forms-salesforce');
        echo '<style>.ol-decimal li {list-style: decimal outside; }</style><div id="message" class="updated"><p>'.$message.'</p></div>';
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

    public function add_salesforce_editor_field($position, $form_id) {
        /* For future use */
    }

    public static function confirmation_error($confirmation, $form = '', $lead = '', $ajax ='' ) {

        if(current_user_can('administrator') && !empty($_REQUEST['salesforceErrorMessage'])) {
            $confirmation .= sprintf(__('%sThe entry was not added to Salesforce because %sboth first and last names are required%s, and were not detected. %sYou are only being shown this because you are an administrator. Other users will not see this message.%s%s', 'gravity-forms-salesforce'), '<div class="error" style="text-align:center; color:#790000; font-size:14px; line-height:1.5em; margin-bottom:16px;background-color:#FFDFDF; margin-bottom:6px!important; padding:6px 6px 4px 6px!important; border:1px dotted #C89797">', '<strong>', '</strong>', '<em>', '</em>', '</div>');
        }
        return $confirmation;
    }

    public static function add_form_option_tooltip($tooltips) {
        $tooltips["form_salesforce"] = "<h6>" . __("Enable Salesforce Integration", "gravity-forms-salesforce") . "</h6>" . __("Check this box to integrate this form with Salesforce. When an user submits the form, the data will be added to Salesforce.", "gravity-forms-salesforce");
        return $tooltips;
    }

    public static function show_salesforce_status() {
        global $pagenow;

        if(isset($_REQUEST['page']) && $_REQUEST['page'] == 'gf_edit_forms' && !isset($_REQUEST['id'])) {
            $activeforms = array();
            $forms = RGFormsModel::get_forms();
            if(!is_array($forms)) { return; }
            foreach($forms as $form) {
                $form = RGFormsModel::get_form_meta($form->id);
                if(is_array($form) && !empty($form['enableSalesforce'])) {
                    $activeforms[] = $form['id'];
                }
            }

            if(!empty($activeforms)) {

?>
<style type="text/css">
    td a.row-title span.salesforce_enabled {
        position: absolute;
        background: url('<?php echo WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)); ?>/images/salesforce-16x16.png') right top no-repeat;
        height: 16px;
        width: 16px;
        margin-left: 10px;
    }
</style>
<script type="text/javascript">
    jQuery(document).ready(function($) {
        SFWebtoLeadForms = [<?php echo implode(',', $activeforms); ?>];
        $('table tbody.user-list tr').each(function() {
            value = $('th.check-column input', $(this)).val();
            if($.inArray(parseInt(value), SFWebtoLeadForms) > -1) {
                $('td a.row-title', $(this)).append('<span class="salesforce_enabled" title="<?php _e('Salesforce integration is enabled for this Form', "gravity-forms-salesforce"); ?>"></span>');
            }
        });
    });
</script>
<?php
            }
        }
    }

    public static function add_form_option_js($location = 100) {
        if($location !== 100) { return; }

        ob_start();
            gform_tooltip("form_salesforce");
            $tooltip = ob_get_contents();
        ob_end_clean();
        $tooltip = trim(rtrim($tooltip)).' ';
    ?>
<style type="text/css">
    #gform_title .salesforce,
    #gform_enable_salesforce_label {
        float:right;
        background: url('<?php echo WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)); ?>/images/salesforce-16x16.png') right top no-repeat;
        height: 16px;
        width: 16px;
        cursor: help;
    }
    #gform_enable_salesforce_label {
        float: none;
        width: auto;
        background-position: left top;
        padding-left: 18px;
        cursor:default;
    }
</style>

<script type="text/javascript">
    jQuery(document).ready(function($) {

        $('#gform_settings_tab_2 .gforms_form_settings, #gform_tab_container_1').append("<li><input type='checkbox' id='gform_enable_salesforce' /> <label for='gform_enable_salesforce' id='gform_enable_salesforce_label'><?php _e("Enable Salesforce integration", "gravity-forms-salesforce") ?> <?php echo $tooltip; ?></label></li>");

        $("#gform_enable_salesforce").prop("checked", form.enableSalesforce ? true : false);

        $(document).on('click change load ready', "#gform_enable_salesforce", function(e) {

            var checked = $(this).is(":checked")

            form.enableSalesforce = checked;

            if(checked) {
                $("#gform_title").append('<span class="salesforce" title="<?php _e("Salesforce integration is enabled.", "gravity-forms-salesforce") ?>"></span>');
            } else {
                $("#gform_title .salesforce").remove();
            }

        }).trigger('ready');

        // This is necessary because we're dynamically adding the tooltip via JS
        jQuery( ".tooltip_form_salesforce" ).tooltip({
            show: 500,
            hide: 1000,
            content: function () {
                return jQuery(this).prop('title');
            }
        });

    });
</script><?php
    }

    //Returns true if the current page is an Feed pages. Returns false if not
    private static function is_salesforce_page(){
        if(empty($_GET["page"])) { return false; }
        $current_page = trim(strtolower($_GET["page"]));
        $salesforce_pages = array("gf_salesforce_webtolead");

        return in_array($current_page, $salesforce_pages);
    }

    //Creates Salesforce left nav menu under Forms
    public static function create_menu($menus){

        // Adding submenu if user has access
        $permission = self::has_access("gravityforms_salesforce");
        if(!empty($permission))
            $menus[] = array("name" => "gf_salesforce_webtolead", "label" => __("Salesforce Web to Lead", "gravityformssalesforce"), "callback" =>  array("GFSalesforceWebToLead", "salesforce_page"), "permission" => $permission);

        return $menus;
    }

    public static function settings_page(){
        $message = $validimage = false; global $plugin_page;
#       if(isset($_GET['addon'])) { return; }
        if(!empty($_POST["uninstall"])){
            check_admin_referer("uninstall", "gf_salesforce_uninstall");
            self::uninstall();

            ?>
            <div class="updated fade" style="padding:20px;"><?php _e(sprintf("Gravity Forms Salesforce Add-On have been successfully uninstalled. It can be re-activated from the %splugins page%s.", "<a href='plugins.php'>","</a>"), "gravityformssalesforce")?></div>
            <?php
            return;
        }
        else if(!empty($_POST["gf_salesforce_submit"])){
            check_admin_referer("update", "gf_salesforce_update");
            $settings = stripslashes($_POST["gf_salesforce_org_id"]);
            update_option("gf_salesforce_oid", $settings);
        }
        else{
            $settings = get_option("gf_salesforce_oid");
        }

        $api = self::test_api(true);

        if(is_array($api) && empty($api)){
            $message = "<p>".__('Salesforce.com is temporarily unavailable. Please try again in a few minutes.',"gravityformssalesforce")."</p>";
            $class = "error";
            $validimage = '';
            $valid = true;
        } elseif($api) {
            $class = "updated";
            $validimage = '<img src="'.self::get_base_url().'/images/tick.png"/>';
            $valid = true;
        } elseif(!empty($settings)){
            $message = "<p>".__('Invalid Salesforce Organization ID.', "gravityformssalesforce")."</p>";
            $class = "error";
            $valid = false;
            $validimage = '<img src="'.self::get_base_url().'/images/cross.png"/>';
        }

        ?>
        <style type="text/css">
            .ul-square li { list-style: square!important; }
            .ol-decimal li { list-style: decimal!important; }
        </style>
        <div class="wrap">
        <img alt="<?php _e("Salesforce.com Feeds", "gravity-forms-salesforce") ?>" src="<?php echo self::get_base_url()?>/images/salesforce-50x50.png" style="float:left; margin:0 7px 0 0;" width="50" height="50" />
        <?php
            if($plugin_page !== 'gf_settings') {

                echo '<h2>'.__('Salesforce.com Web to Lead Configuration',"gravityformssalesforce").'</h2>';
            }
            if($message) {
                echo "<div class='fade below-h2 {$class}'>".wpautop($message)."</div>";
            } ?>

        <form method="post" action="" style="margin: 30px 0 30px; clear:both;">
            <?php wp_nonce_field("update", "gf_salesforce_update") ?>
            <h3><?php _e("Salesforce Account Information", "gravityformssalesforce") ?></h3>
            <p style="text-align: left;">
                <?php _e(sprintf("If you don't have a Salesforce account, you can %ssign up for one here%s", "<a href='http://www.salesforce.com' target='_blank'>" , "</a>"), "gravityformssalesforce") ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="gf_salesforce_org_id"><?php _e("Salesforce Org. ID", "gravityformssalesforce"); ?></label> </th>
                    <td><input type="text" size="75" id="gf_salesforce_org_id" class="code pre" style="font-size:1.1em; margin-right:.5em;" name="gf_salesforce_org_id" value="<?php echo esc_attr($settings) ?>"/> <?php echo $validimage; ?>
                    <?php echo '<small style="display:block;">'.__('To find your Salesforce.com Organization ID, in your Salesforce.com account, go to [Your Name] &raquo; Setup &raquo; Company Profile (near the bottom of the left sidebar) &raquo; Company Information','gravityformssalesforce').'</small>';?></td>
                </tr>
                <tr>
                    <td colspan="2" ><input type="submit" name="gf_salesforce_submit" class="submit button-primary" value="<?php _e("Save Settings", "gravityformssalesforce") ?>" /></td>
                </tr>

            </table>
        </form>

    <?php if(isset($valid) && $valid) { ?>
        <div class="hr-divider"></div>

        <h3>Usage Instructions</h3>

        <div class="delete-alert alert_gray">
            <h4>To integrate a form with Salesforce:</h4>
            <ol class="ol-decimal">
                <li>Edit the form you would like to integrate (choose from the <a href="<?php _e(admin_url('admin.php?page=gf_edit_forms')); ?>">Edit Forms page</a>).</li>
                <li>Click "Form Settings"</li>
                <li>Click the "Advanced" tab</li>
                <li><strong>Check the box "Enable Salesforce integration"</strong></li>
                <li>Save the form</li>
            </ol>
        </div>

        <h4><?php _e('Custom Fields', "gravityformssalesforce"); ?></h4>
        <?php echo wpautop(sprintf(__('When you are trying to map a custom field, you need to set either the "Admin Label" for the input (in the Advanced tab of each field in the  Gravity Forms form editor) or the Parameter Name (in Advanced tab, visible after checking "Allow field to be populated dynamically") to be the API Name of the Custom Field as shown in Salesforce. For example, a Custom Field with a Field Label "Web Source" could have an API Name of `SFGA__Web_Source__c`.

You can find your Custom Fields under [Your Name] &rarr; Setup &rarr; Leads &rarr; Fields, then at the bottom of the page, there&rsquo;s a list of "Lead Custom Fields & Relationships". This is where you will find the "API Name" to use in the Admin Label or Parameter Name.

For more information on custom fields, %sread this Salesforce.com Help Article%s', "gravityformssalesforce"), '<a href="https://help.salesforce.com/apex/htviewhelpdoc?id=customize_customfields.htm&language=en" target="_blank">', '</a>')); ?>

        <h4><?php _e('Form Fields', "gravityformssalesforce"); ?></h4>
        <p><?php _e('Fields will be automatically mapped by Salesforce using the default Gravity Forms labels.', "gravityformssalesforce"); ?></p>
        <p><?php _e('If you have issues with data being mapped, make sure to use the following keywords in the label to match and send data to Salesforce.', "gravityformssalesforce"); ?></p>

        <ul class="ul-square">
            <li><?php _e(sprintf('%sname%s (use to auto-split names into First Name and Last Name fields)', '<code>', '</code>'), "gravityformssalesforce"); ?></li>
            <li><?php _e(sprintf('%sfirst name%s', '<code>', '</code>'), "gravityformssalesforce"); ?></li>
            <li><?php _e(sprintf('%slast name%s', '<code>', '</code>'), "gravityformssalesforce"); ?></li>
            <li><?php _e(sprintf('%scompany%s', '<code>', '</code>'), "gravityformssalesforce"); ?></li>
            <li><?php _e(sprintf('%semail%s', '<code>', '</code>'), "gravityformssalesforce"); ?></li>
            <li><?php _e(sprintf('%sphone%s', '<code>', '</code>'), "gravityformssalesforce"); ?></li>
            <li><?php _e(sprintf('%scity%s', '<code>', '</code>'), "gravityformssalesforce"); ?></li>
            <li><?php _e(sprintf('%scountry%s', '<code>', '</code>'), "gravityformssalesforce"); ?></li>
            <li><?php _e(sprintf('%szip%s', '<code>', '</code>'), "gravityformssalesforce"); ?></li>
            <li><?php _e(sprintf('%ssubject%s', '<code>', '</code>'), "gravityformssalesforce"); ?></li>
            <li><?php _e(sprintf('%sdescription%s, %squestion%s, %smessage%s, or %scomments%s for Description', '<code>', '</code>','<code>', '</code>','<code>', '</code>','<code>', '</code>'), "gravityformssalesforce"); ?></li>
        </ul>

        <form action="" method="post">
            <?php wp_nonce_field("uninstall", "gf_salesforce_uninstall") ?>
            <?php if(GFCommon::current_user_can_any("gravityforms_salesforce_uninstall")){ ?>
                <div class="hr-divider"></div>

                <h3><?php _e("Uninstall Salesforce Add-On", "gravityformssalesforce") ?></h3>
                <div class="delete-alert alert_red">
                    <h3><?php _e('Warning', 'gravityformssalesforce'); ?></h3>
                    <p><?php _e("This operation deletes ALL Salesforce Feeds. ", "gravityformssalesforce") ?></p>
                    <?php
                    $uninstall_button = '<input type="submit" name="uninstall" value="' . __("Uninstall Salesforce Add-On", "gravityformssalesforce") . '" class="button" onclick="return confirm(\'' . __("Warning! ALL Salesforce Feeds will be deleted. This cannot be undone. \'OK\' to delete, \'Cancel\' to stop", "gravityformssalesforce") . '\');"/>';
                    echo apply_filters("gform_salesforce_uninstall_button", $uninstall_button);
                    ?>
                </div>
            <?php } ?>
        </form>
        <?php } // end if($api) ?>
        </div>
        <?php
    }

    public static function salesforce_page(){
        if(isset($_GET["view"]) && $_GET["view"] == "edit") {
            self::edit_page($_GET["id"]);
        } else {
            self::settings_page();
        }
    }

    private static function test_api($debug = false){
        $api = false;

        return self::send_request(array(), $debug);

    }

    public static function send_request($post, $debug = false) {
        global $wp_version;

        $post['oid']    = get_option("gf_salesforce_oid");
        $post['debug']  = $debug;

        if(empty($post['oid'])) { return false; }

        // Set SSL verify to false because of server issues.
        $args = array(
            'body'      => $post,
            'headers'   => array(
                'user-agent' => 'Gravity Forms Salesforce Add-on plugin - WordPress/'.$wp_version.'; '.get_bloginfo('url'),
            ),
            'sslverify' => false,
        );

        $args = apply_filters( 'gf_salesforce_request_args', $args, $debug );

        $sub = $debug ? 'test' : 'www';

        $sub = apply_filters( 'gf_salesforce_request_subdomain', $sub, $debug );

        $result = wp_remote_post('https://'.$sub.'.salesforce.com/servlet/servlet.WebToLead?encoding=UTF-8', $args);

        $code = wp_remote_retrieve_response_code($result);
        if((int)$code !== 200) { // Server may be down.
            return array();
        } elseif(!isset($result['headers']['is-processed'])) { // For a valid debug test
            return $result;
        } else if ($result['headers']['is-processed'] === "true") { // For a valid request
            return $result;
        } elseif(strpos($result['headers']['is-processed'], 'Exception')) { // For an invalid request
            return false;
        }

        return $result;
    }

    public static function push($form_meta, $entry = array()){
        global $wp_version;

        if(!isset($form_meta['enableSalesforce']) || empty($form_meta['enableSalesforce'])) { return; }

        $defaults = array(
            'first_name'    => array('label' => 'First name'),
            'last_name'     => array('label' => 'Last name'),
            'company'       => array('label' => 'Company'),
            'salutation'    => array('label' => 'Salutation'),
            'URL'           => array('label' => 'Website'),
            'email'         => array('label' => 'Email'),
            'phone'         => array('label' => 'Phone'),
            'mobile'        => array('label' => 'Mobile'),
            'fax'           => array('label' => 'Fax'),
            'description'   => array('label' => 'Message'),
            'title'         => array('label' => 'Title'),
            'street'        => array('label' => 'Street'),
            'city'          => array('label' => 'City'),
            'state'         => array('label' => 'State'),
            'country'       => array('label' => 'Country'),
            'zip'           => array('label' => 'ZIP'),
            'lead_source'   => array('label' => 'Lead Source'),
            'industry'      => array('label' => 'Industry'),
            'rating'        => array('label' => 'Rating'),
            'revenue'       => array('label' => 'Annual Revenue'),
            'employees'     => array('label' => 'Employees'),
            'Campaign_ID'   => array('label' => 'Campaign ID'),
            'member_status' => array('label' => 'Member Status'),
            'emailOptOut'   => array('label' => 'Opt Out of Emails'),
            'faxOptOut'     => array('label' => 'Opt Out of Faxes'),
            'doNotCall'     => array('label' => 'Do Not Call'),
            'retURL'        => array('label' => 'Return URL'),
        );

        $data = array();


        //displaying all submitted fields
        foreach($form_meta["fields"] as $fieldKey => $field){

            if($field['type'] == 'section') {
                continue;
            }

            if( is_array(@$field["inputs"]) || is_array(@$field["choices"]) || $field['type'] === 'list'){
                $valuearray = array();
                $fieldtemp = array();
                $multi_input = true;

                // set multi-input array to loop through
                if ( is_array($field["inputs"])) {
                    $fieldtemp = $field["inputs"];

                }
                else {
                    $fieldtemp = isset($_POST["input_" . $field["id"]]) ? $_POST["input_" . $field["id"]] : '';
                    $multi_input = false;
                    $label = self::getLabel($field["label"], $field);
                }

               //handling multi-input fields such as name and address or choices
               foreach((array)$fieldtemp as $inputKey => $input){
                   //set the value and label
                   if ($multi_input == true) {
                       // inputs is an array
                       $value = trim(rtrim(stripslashes(@$_POST["input_" . str_replace('.', '_', $input["id"])])));
                       $label = self::getLabel($input["label"], $field, $input);
                   } else {
                       // choices is an array
                       $value = $input;
                   }

                   if(!$label) { $label = self::getLabel($field['label'], $field, $input); }
                   if ($label == 'BothNames' && !empty($value)) {
                        $names = explode(" ", $value);
                        $names[0] = trim(rtrim($names[0]));
                        $names[1] = trim(rtrim($names[1]));
                        if(!empty($names[0])) {
                            $data['first_name'] = $names[0];
                        }
                        if(!empty($names[1])) {
                            $data['last_name'] = $names[1];
                        }
                   } else if ($label == 'description') {
                        $message = 'true';
                       $data['description'] .= "\n".$value."\n";
                   } else if($label == 'street') {
                        $data['street'] = isset($data['street']) ? $data['street'].$value."\n" : $value."\n";
                   } else if (trim(strtolower($label)) == 'salesforce' ) {
                        $salesforce = $value;
                   } else {
                        if(!empty($field['inputName']) && (apply_filters('gf_salesforce_use_inputname', true) === true)) {
                            $valuearray["{$field['inputName']}"][] = (is_array($input) && isset($input["label"])) ? $input["label"] : $input;
                        } elseif(!empty($field['adminLabel']) && (apply_filters('gf_salesforce_use_adminlabel', true) === true)) {
                            $valuearray["{$field['adminLabel']}"][] = $value;
                        } elseif((!empty($data["{$label}"]) && !empty($value) && $value !== '0') || empty($data["{$label}"]) && array_key_exists("{$label}", $defaults)) {
                            $data[$label] = $value;
                        }
                   }
               }

               // after looping through multi-input fields set the value
               if(isset($valuearray["{$field['adminLabel']}"])) {
                    $data[$label] = implode(apply_filters('gf_salesforce_implode_glue', ';', $field), $valuearray["{$field['adminLabel']}"]);
                    $data[$label] = preg_replace('/;+/', ';', $data[$label]); // Get rid of empty values
               } elseif(isset($valuearray["{$field['inputName']}"])) {
                    $data[$label] = implode(apply_filters('gf_salesforce_implode_glue', ', ', $field), $valuearray["{$field['inputName']}"]);
                    $data[$label] = str_replace(', ,', ',', $data[$label]); // Get rid of empty values
                }

           } else if ( 'survey' == $field[ 'type' ] && 'likert' == $field[ 'inputType' ] ) {

                // handling likert field values for mapping properly
                $value = trim( stripslashes( @$_POST[ "input_" . $field[ "id" ] ] ) );

                foreach ( $field[ 'choices' ] as $choice ) {
                    if ( $value == $choice[ 'value' ] ) {
                        $value = $choice[ 'text' ];
                        break;
                    }
                }

                $label = self::getLabel($field["label"], $field);

                $data[ $label ] = $value;

            } else {
               //handling single-input fields such as text and paragraph (textarea)
               $value = trim(rtrim(stripslashes(@$_POST["input_" . $field["id"]])));
               $label = self::getLabel($field["label"], $field);

               if ($label == 'BothNames' && !empty($value)) {
                    $names = explode(" ", $value);
                    $names[0] = trim(rtrim($names[0]));
                    $names[1] = trim(rtrim($names[1]));
                    if(!empty($names[0])) {
                        $data['first_name'] = $names[0];
                    }
                    if(!empty($names[1])) {
                        $data['last_name'] = $names[1];
                    }
               } else if ($label == 'description') {
                    $message = 'true';
                   $data['description'] = empty($data['description']) ? $value."\n" : $data['description']."\n".$value."\n";
               } else if($label == 'street') {
                    $data['street'] = isset($data['street']) ? $data['street'].$value."\n" : $value."\n";
               } else if (trim(strtolower($label)) == 'salesforce' ) {
                    $salesforce = $value;
               } else {
                    $field_name = null;

                    if(!empty($field['inputName']) && (apply_filters('gf_salesforce_use_inputname', true) === true)) {
                        $field_name = $field[ 'inputName' ];
                    } elseif(!empty($field['adminLabel']) && (apply_filters('gf_salesforce_use_adminlabel', true) === true)) {
                        $field_name = $field[ 'adminLabel' ];
                    } elseif((!empty($data["{$label}"]) && !empty($value) && $value !== '0') || empty($data["{$label}"]) && (array_key_exists("{$label}", $defaults) || apply_filters('gf_salesforce_use_custom_fields', true) === true)) {
                        $field_name = $label;
                    }

                    $field_name = apply_filters( 'gf_salesforce_mapped_field_name', $field_name, $field, $form_meta, $entry );

                    $value = apply_filters( 'gf_salesforce_mapped_value_' . $field_name, $value, $field, $field_name, $form_meta, $entry );
                    $value = apply_filters( 'gf_salesforce_mapped_value', $value, $field, $field_name, $form_meta, $entry );

                    if ( null !== $field_name ) {
                        $data["{$field_name}"] = $value;
                   }
               }
           }
       }

        $data['description'] = isset($data['description']) ? trim(rtrim($data['description'])) : '';
        $data['street'] = isset($data['street']) ? trim(rtrim($data['street'])) : '';
        $data['emailOptOut'] = !empty($data['emailOptOut']);
        $data['faxOptOut'] = !empty($data['faxOptOut']);
        $data['doNotCall'] = !empty($data['doNotCall']);

        $post = $data;

        $lead_source = isset($form_meta['title']) ? $form_meta['title'] : 'Gravity Forms Form';
        $data['lead_source'] = apply_filters('gf_salesforce_lead_source', $lead_source, $form_meta, $data);
        $data['debug']          = 0;
        $data = array_map('stripslashes', $data);

        // You can tap into the data and filter it.
        $data = apply_filters( 'gf_salesforce_push_data', $data, $form_meta, $entry );

        $result = self::send_request($data);

        if($result && !empty($result)) {
            return true;
        } else {
            return false;
        }
    }

    public static function getLabel($temp, $field = '', $input = false){
        $label = false;

        if($input && isset($input['id'])) {
            $id = $input['id'];
        } else {
            $id = $field['id'];
        }

        $type = $field['type'];

        switch($type) {

            case 'name':
                if($field['nameFormat'] == 'simple') {
                    $label = 'BothNames';
                } else {
                    if(strpos($id, '.2')) {
                        $label = 'salutation'; // 'Prefix'
                    } else if(strpos($id, '.3')) {
                        $label = 'first_name';
                    } else if(strpos($id, '.6')) {
                        $label = 'last_name';
                    } else if(strpos($id, '.8')) {
                        $label = 'suffix'; // Suffix
                    }
                }
                break;
            case 'address':
                if(strpos($id, '.1') || strpos($id, '.2')) {
                    $label = 'street'; // 'Prefix'
                } else if(strpos($id, '.3')) {
                    $label = 'city';
                } else if(strpos($id, '.4')) {
                    $label = 'state'; // Suffix
                } else if(strpos($id, '.5')) {
                    $label = 'zip'; // Suffix
                } else if(strpos($id, '.6')) {
                    $label = 'country'; // Suffix
                }
                break;
            case 'email':
                $label = 'email';
                break;
        }

        if($label) {
            return $label;
        }

        $the_label = strtolower($temp);

        if(!empty($field['inputName']) && (apply_filters('gf_salesforce_use_inputname', true) === true)) {
            $label = $field['inputName'];
        } elseif(!empty($field['adminLabel']) && (apply_filters('gf_salesforce_use_adminlabel', true) === true)) {
            $label = $field['adminLabel'];
        }

        if(!apply_filters('gf_salesforce_autolabel', true) || !empty($label)) { return $label; }

        if ($type == 'name' && ($the_label === "first name" || $the_label === "first")) {
            $label = 'first_name';
        } else if ($type == 'name' && ($the_label === "last name" || $the_label === "last")) {
            $label = 'last_name';
        } elseif($the_label == 'prefix' || $the_label == 'salutation' || $the_label === 'prefix' || $the_label === 'salutation') {
            $label = 'salutation';
        } else if ( $the_label === 'both names') {
            $label = 'BothNames';
        } else if ($the_label === "company") {
            $label = 'company';
        } else if ($the_label == 'member_status') {
            $label = 'member_status';
        } else if ( $the_label === "emailoptout" ) {
            $label = 'emailOptOut';
        } else if ( $the_label === "faxoptout") {
            $label = 'faxOptOut';
        } else if ( $the_label === "donotcall") {
            $label = 'doNotCall';
        } else if ( $the_label === "email" || $the_label === "e-mail" || $type == 'email') {
            $label = 'email';
        } else if ( strpos( $the_label,"mobile") !== false || strpos( $the_label,"cell") !== false ) {
            $label = 'mobile';
        } else if ( strpos( $the_label,"fax") !== false) {
            $label = 'fax';
        } else if ( strpos( $the_label,"phone") !== false ) {
            $label = 'phone';
        } else if ( strpos( $the_label,"city") !== false ) {
            $label = 'city';
        } else if ( strpos( $the_label,"country") !== false ) {
            $label = 'country';
        } else if ( strpos( $the_label,"state") !== false ) {
            $label = 'state';
        } else if ( strpos( $the_label,"zip") !== false ) {
            $label = 'zip';
        } else if ( strpos( $the_label,"street") !== false || strpos( $the_label,"address") !== false ) {
            $label = 'street';
        } else if ( strpos( $the_label,"website") !== false || strpos( $the_label,"web site") !== false || strpos( $the_label,"web") !== false ||  strpos( $the_label,"url") !== false) {
            $label = 'URL';
        } else if ( strpos( $the_label,"source") !== false ) {
            $label = 'lead_source';
        } else if ( strpos( $the_label,"rating") !== false ) {
            $label = 'rating';
        } else if ( strpos( $the_label,"industry") !== false ) {
            $label = 'industry';
        } else if ( strpos( $the_label,"revenue") !== false ) {
            $label = 'revenue';
        } else if ( strpos( $the_label,"employees") !== false ) {
            $label = 'employees';
        } else if ( strpos( $the_label,"campaign") !== false ) {
            $label = 'Campaign_ID';
        } else if ( strpos( $the_label,"salesforce") !== false ) {
            $label = 'salesforce';
        } else if ( strpos( $the_label,"title") !== false ) {
            $label = 'title';
        } else if ( strpos( $the_label,"question") !== false || strpos( $the_label,"message") !== false || strpos( $the_label,"comments") !== false || strpos( $the_label,"description") !== false ) {
            $label = 'description';
        } elseif(!empty($field['label']) && (apply_filters('gf_salesforce_use_label', true) === true)) {
            $label = $field['label'];
        } else {
            $label = false;
        }

        return $label;
    }

    public static function disable_salesforce(){
        delete_option("gf_salesforce_oid");
    }

    public static function uninstall(){

        if(!GFSalesforceWebToLead::has_access("gravityforms_salesforce_uninstall"))
            (__("You don't have adequate permission to uninstall Salesforce Add-On.", "gravityformssalesforce"));

        //removing options
        delete_option("gf_salesforce_oid");

        //Deactivating plugin
        $plugin = "gravityformssalesforce/salesforce.php";
        deactivate_plugins($plugin);
        update_option('recently_activated', array($plugin => time()) + (array)get_option('recently_activated'));
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
    protected static function get_base_url(){
        return plugins_url(null, __FILE__);
    }

    //Returns the physical path of the plugin's root folder
    protected static function get_base_path(){
        $folder = basename(dirname(__FILE__));
        return WP_PLUGIN_DIR . "/" . $folder;
    }


}
