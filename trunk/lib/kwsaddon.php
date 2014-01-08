<?php
/*

KWS Gravity Forms Add-On
Version: 2.0

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

if (class_exists("GFForms") && !class_exists('KWSGFAddOn2')) {
    GFForms::include_feed_addon_framework();

    /**
     * Extend the GFFeedAddOn with lots of default functionality for KWS GF addons.
     */
    abstract class KWSGFAddOn2 extends GFFeedAddOn {

        protected $_version = "2.0";
        protected $_min_gravityforms_version = "1.7";
        protected $_slug = "kwsaddon";
        protected $_path = "kwsaddon/kwsaddon.php";
        protected $_full_path = __FILE__;
        protected $_title = "Gravity Forms KWS Add-On";
        protected $_short_title = "KWS Add-On";

        protected $_capabilities_uninstall = array('administrator', 'manage_options');
        protected $_capabilities_plugin_page = array('administrator', 'manage_options');
        protected $_capabilities_form_settings = array('administrator', 'manage_options');
        protected $_capabilities_settings_page = array('administrator', 'manage_options');

        /**
         * The name of the service exporting to; eg: Constant Contact
         * @var string
         */
        protected $_service_name = NULL;

        /**
         * HTML `<img>` tag with full path to service icon
         * @var string
         */
        protected $_service_icon = NULL;

        protected $_service_api_valid = NULL;

        protected $_service_api = NULL;

        protected $_custom_field_placeholder = 'Field Name';


        public function add_custom_hooks() {
            global $pagenow;

            if($pagenow === 'plugins.php') {
                add_action('admin_notices', array(&$this, 'gf_installation_notice'), 10);
            }

            // Add support for logging
            add_filter("gform_logging_supported", array(&$this, 'gf_logging_filter'));
        }

        /**
         * Add a setting for whether to log logs for this addon
         * @param  array $plugins Array of existing plugins that have logging settings
         * @return array          Modified plugins array
         */
        public function gf_logging_filter($plugins) {
            $plugins[$this->_slug] = $this->get_short_title();
            return $plugins;
        }

        /**
         * Show a notice if GF isn't activated.
         * @param  boolean $echo True: print; False: return
         * @return string        HTML output of notice.
         */
        public function gf_installation_notice($echo = true) {
            global $pagenow, $page; $message = '';

            $installed = 0;

            $name = $this->get_short_title();
            if(!class_exists('RGForms')) {
                if(file_exists(WP_PLUGIN_DIR.'/gravityforms/gravityforms.php')) {
                    $installed = 1;

                    $message .= __(sprintf('%sGravity Forms is installed but not active. %sActivate Gravity Forms%s to use the %s plugin.%s', '<p>', '<strong><a href="'.wp_nonce_url(admin_url('plugins.php?action=activate&plugin=gravityforms/gravityforms.php'), 'activate-plugin_gravityforms/gravityforms.php').'">', '</a></strong>', $name,'</p>'), 'gravity-forms-madmimi');

                } else {
                    $message .= <<<EOD
    <p><a href="http://katz.si/gravityforms?con=banner" title="Gravity Forms Contact Form Plugin for WordPress"><img src="http://gravityforms.s3.amazonaws.com/banners/728x90.gif" alt="Gravity Forms Plugin for WordPress" width="728" height="90" style="border:none;" /></a></p>
            <h3><a href="http://katz.si/gravityforms" target="_blank">Gravity Forms</a> is required for the $name</h3>
            <p>You do not have the Gravity Forms plugin installed. <a href="http://katz.si/gravityforms">Get Gravity Forms</a> today.</p>
EOD;
                }

                if(!empty($message) && $echo) {
                    echo '<div id="message" class="updated">'.$message.'</div>';
                }
            } else {
                return true;
            }
            return $installed;
        }

        protected function invalid_api_message() {
            return __("Invalid username and/or API key. Please try another combination.", "kwsaddon");
        }

        protected function valid_api_message() {
            return __("Your configuration is working!", "kwsaddon");
        }

        protected function get_service_signup_message() {

            // The ad is stored locally for 30 days as a transient. See if it exists.
            $cache = function_exists('get_site_transient') ? get_site_transient($this->_slug.'_remote_ad') : get_transient($this->_slug.'_remote_ad');

            // If it exists, use that (so we save some request time), unless ?cache is set.
            if(!empty($cache) && !isset($_REQUEST['cache'])) { return $cache; }

            // Get the advertisement remotely. An encrypted site identifier, the language of the site, and the version of the cf7 plugin will be sent to katz.co
            $response = wp_remote_post('http://katz.co/ads/', array('timeout' => 45,'body' => array('siteid' => sha1(site_url()), 'language' => get_bloginfo('language'), 'plugin' => 'gf_'.$this->_slug, 'version' => $this->_version)));

            // If it was a successful request, process it.
            if(!is_wp_error($response) && !empty($response)) {

                $body = str_replace('{path}', plugins_url( '', $this->_full_path ), $response['body']);

                // Basically, remove <script>, <iframe> and <object> tags for security reasons
                $body = strip_tags(trim(rtrim($body)), '<b><strong><em><i><span><u><ul><li><ol><div><attr><cite><a><style><blockquote><q><p><form><br><meta><option><textarea><input><select><pre><code><s><del><small><table><tbody><tr><th><td><tfoot><thead><u><dl><dd><dt><col><colgroup><fieldset><address><button><aside><article><legend><label><source><kbd><tbody><hr><noscript><link><h1><h2><h3><h4><h5><h6><img>');

                // If the result is empty, cache it for 8 hours. Otherwise, cache it for 30 days.
                $cache_time = empty($response['body']) ? floatval(60*60*8) : floatval(60*60*30);

                if(function_exists('set_site_transient')) {
                    set_site_transient($this->_slug.'_remote_ad', $body, $cache_time);
                } else {
                    set_transient($this->_slug.'_remote_ad', $body, $cache_time);
                }

                // Return the results.
                return $body;
            }
        }

        public function get_api() {
            return $this->_service_api;
        }

        public function get_slug() {
            return $this->_slug;
        }

        /**
         * Test the API is working or not by showing if lists are retrivable.
         * @return boolean Working or not
         */
        public function is_valid_api() {
            if(!is_null($this->_service_api_valid)) {
                return $this->_service_api_valid;
            }

            $this->_service_api_valid = $this->get_service_lists();

            return $this->_service_api_valid;
        }

        /**
         * Is the addon configured properly? Check the API.
         * @return boolean [description]
         */
        public function is_invalid_api() {
            return !$this->is_valid_api();
        }

        /**
         * Is the addon not yet configured at all?
         * @return boolean [description]
         */
        public function is_not_configured() {
            $settings = $this->get_plugin_settings();
            return $settings === false;
        }

        public function get_service_name() {
            return !empty($this->_service_name) ? $this->_service_name : $this->get_short_title();
        }

        /**
         * This is here to fix a bug in GF 1.7 that hard-coded MailChimp as the service.
         * @param  array  $field Field array
         * @param  boolean $echo  Echo the output
         * @return string         HTML output for feed condition
         */
        public function settings_feed_condition( $field, $echo = true ) {

            // Get the output from the default Feed Add-on
            $html = parent::settings_feed_condition( $field, false );

            // Replace MailChimp
            $html = str_replace('MailChimp', $this->get_service_name(), $html);

            if($echo) {
                echo $html;
            }

            return $html;

        }

        public function get_service_icon() {
            return $this->_service_icon;
        }

        /**
         * Returns a specific setting based on key
         * @param  string $key Name of setting to return
         * @return mixed|null      Value of setting at key {$key}. If not set, returns null.
         */
        protected function get_plugin_setting($key) {

            $settings = $this->get_plugin_settings();

            return isset($settings[$key]) ? $settings[$key] : NULL;
        }

        public function feed_settings_fields() {

            return array(
                array(
                    "title" => sprintf("Map your fields for export to %s.", $this->get_service_name()),
                    "fields" => array(
                        array(
                            'label' => 'Feed Name',
                            'name' => 'name',
                            'type' => 'text',
                            'value' => '',
                            'placeholder' => __('Feed Name (optional)', 'kwsaddon'),
                        ),
                        array(
                            'type' => 'checkbox',
                            'label' => 'Choose Your Lists',
                            'tooltip' => sprintf("<h6>" . __("%s List", "gravity-forms-madmimi") . "</h6>" . __("Select the %s list you would like to add your contacts to.", "kwsaddon"), $this->get_service_name(), $this->get_service_name()),
                            'name' => 'lists',
                            'validation_callback' => array( $this, 'validate_service_lists' ),
                            'choices' => $this->feed_settings_service_lists()
                        ),
                        array(
                            'type' => 'field_map',
                            'label' => 'Map your fields.',
                            'tooltip' => "<h6>" . __("Map Fields", "gravity-forms-madmimi") . "</h6>" . sprintf(__("Associate your %s merge variables to the appropriate Gravity Form fields by selecting.", "kwsaddon"), $this->get_service_name()),
                            'name' => null,
                            'field_map' => $this->map_custom_fields($this->feed_settings_fields_field_map())
                        ),
                        array(
                            'type' => 'custom_fields',
                            'label' => __("Custom Fields", 'kwsaddon'),
                            'name' => null
                        ),
                        array(
                            'label' => 'Opt-in Condition',
                            'type' => 'feed_condition',
                            'tooltip' => sprintf("<h6>" . __("Opt-In Condition", "gravity-forms-madmimi") . "</h6>" . __("When the opt-in condition is enabled, form submissions will only be exported to %s when the condition is met. When disabled all form submissions will be exported.", "kwsaddon"), $this->get_service_name()),
                        )
                    )
                )
            );
        }

        public function map_custom_fields($fields) {

            // Get all the fields for the Feed
            $all_fields = $this->get_current_settings();

            // Unset fields that we know aren't mapped
            unset($all_fields['name'], $all_fields['type'], $all_fields['feed_condition_conditional_logic'], $all_fields['feed_condition_conditional_logic_object']);

            // Unset all mapped fields we know are there
            foreach ($fields as $field) {
                unset($all_fields['_'.$field['name']]);
            }

            if(!empty($all_fields)) {
                // All the fields that remain are custom
                foreach ($all_fields as $key => $value) {
                    $name = esc_html( ltrim($key, '_') );
                    $fields[] = array(
                        'name' => $name,
                        'label' => sprintf(__('Custom Field: <span class="custom_field_name">%s</span>'), $name),
                        'class' => 'custom_field',
                    );
                }
            }

            return $fields;
        }

        /**
         * Add Custom Field functionality for Feed configuration
         */
        protected function settings_custom_fields() {
            ?>

            <style>
                .delete_custom_field {
                    cursor:pointer;
                    position: absolute;
                    margin-left:-1.5em;
                    margin-top:.4em;
                }
            </style>

            <script>

            jQuery(document).ready(function($) {

                function KWSFormAddListItem(element){
                    var table = jQuery('.settings-field-map-table');
                    var $clone = jQuery('tr:has(select):last-child', table).clone();
                    var $html = KWSGetInput();

                    $clone
                        // Make the drop-down and input values empty
                        .find("input[type=text],select")
                            .val(null)
                            .attr('name', null)
                            .attr('id', null)
                        .end() // Go back to $clone
                        .find('td:first-child')
                            .html($html) // Add the name input
                            .prepend(KWSGetDeleteImage())
                        .end() // Go back to $clone
                        .appendTo(table); // Add $clone to the table

                    return false;
                }

                function KWSGetDeleteImage() {
                    return $('<img class="delete_list_item delete_custom_field" src="<?php echo GFCommon::get_base_url() . "/images/remove.png"; ?>" style="cursor:pointer;" />');
                }

                /**
                 * Get an input with a value
                 * @param {string} value The value of the text box
                 */
                function KWSGetInput(value) {

                    return $('<input/>', {
                        class: 'kws_custom_field widefat code',
                        type: 'text',
                        style: 'padding:.5em .2em;',
                        placeholder: '<?php echo str_replace("'", "\'", esc_js( $this->_custom_field_placeholder )); ?>',
                        value: value
                    });

                }

                /**
                 * Convert text values ("Custom Field: Example") back to inputs ("<input value="Example">) on form load.
                 */
                jQuery('.settings-field-map-table tr td:has(span.custom_field_name)').each(function() {

                    $(this).html(KWSGetInput($(this).find('span.custom_field_name').text()));
                });

                /**
                 * When the value of the input is changed, change the name of the select
                 * associated with it.
                 */
                jQuery('body').on('blur keyup', 'input.kws_custom_field', function(e) {
                    $(this).closest('tr').find('select')
                        .attr('name', '_gaddon_setting__'+$(this).val());
                });

                /**
                 * When the button is clicked to add a custom field, add one.
                 */
                jQuery('body').on('click', '#kws_add_custom_field', function(e) {
                    e.preventDefault();
                    KWSFormAddListItem(this);
                });

                /**
                 * When the delete button is clicked, remove the custom field row
                 */
                jQuery('body').on('click', '.delete_custom_field', function(e) {
                    $(this).closest('tr').fadeOut(function() { $(this).remove(); });
                });

                jQuery('select.custom_field')
                    .closest('tr')
                    .find('td:first-child')
                        .addClass('gfield_list_icons')
                        .prepend(KWSGetDeleteImage());
            });

            </script>


            <a href="#" class="button submit button-large button-secondary" id="kws_add_custom_field"><?php _e('Add a custom field', 'kwsaddon'); ?></a>

            <?php
        }

        /**
         * Make sure there's at least one list selected.
         * @param  array $field         Field value
         * @param  array $field_setting Field setting for the feed
         */
        function validate_service_lists($field, $field_setting) {

            // Remove disabled lists from the settings array.
            // Active lists have 1 as their value; inactive have 0.
            foreach ($field_setting as $key => $value) {
                if(empty($value)) { unset($field_setting[$key]); }
            }

            // If there are still active lists, this is valid.
            if(empty($field_setting)) {
                $this->set_field_error( $field, sprintf(__('You must choose at least one %s list to receive contacts.', 'kwsaddon'), $this->get_service_name() ));
            }
        }

        /**
         * Get the active lists for a GF Form Feed
         * @param  array $feed Form Feed array
         * @param boolean $populate Whether to populate with the list name or not. key = ID, value = name
         * @return array       Array of list IDs
         */
        protected function feed_get_active_lists($feed, $populate = false) {
            $feed_meta = $feed['meta'];

            $lists = rgar( $feed_meta, "lists" );

            if(empty($lists)) { return array(); }

            foreach ($lists as $key => $list) {
                if(empty($list)) { unset($lists[$key]); }
            }

            $active_lists = array_keys($lists);

            if($populate) {
                // Get all the lists
                $all_lists = $this->get_service_lists();

                // Populate the list names for active lists
                $output_lists = array();
                foreach($active_lists as $active_list_id) {
                    $output_lists[$active_list_id] = esc_html( $all_lists[$active_list_id]['name'] );
                }
            } else {
                $output_lists = $active_lists;
            }

            return $output_lists;
        }

        /**
         * Generate the list of lists to choose from.
         * @return [type] [description]
         */
        protected function feed_settings_service_lists() {
            return $this->get_service_lists();
        }

        /**
         * Return an array of all available lists for the service.
         * @return array
         */
        protected function get_service_lists() {
            return array();
        }

        /**
         * Modify the columns shown in the Form Feeds view
         *
         * Form Feeds view in Forms > Form > Form Settings > {Addon Name}
         *
         * @return [type] [description]
         */
        function feed_list_columns() {
            return array(
                'feed_name' => __('Feed Name', 'kwsaddon'),
                'lists' => __('Connected Lists', 'kwsaddon'), // if you want to show the lists that the form is connected to.
            );
        }

        /**
         * Generate the feed name for the Form Feeds view
         *
         * Under Forms > Form > Form Settings > {Addon Name}
         *
         * @param  array $item Feed data
         * @return string       HTML output of name of form with link to edit the feed.
         */
        function get_column_value_feed_name($item) {
            $name = rgar($item['meta'], "name");
            $feed_id  = rgar($item, "id");
            $name     = !empty($name) ? $name : sprintf(__("Untitled Feed (#%s)", "gravityforms"), $feed_id);
            $edit_url = add_query_arg(array("fid" => $feed_id));
            return sprintf('<a href="%s" title="Edit the configuration for the feed &ldquo;%s&rdquo;">%s</a>', $edit_url, esc_html( $name ), $name);
        }

        /**
         * Generate the feed lists for the Form Feeds view
         *
         * Under Forms > Form > Form Settings > {Addon Name}
         *
         * @param  array $item Feed data
         * @return string       HTML output of name of form with link to edit the feed.
         */
        function get_column_value_lists($item) {

            // Get an array of active list IDs for the current form
            $active_lists = $this->feed_get_active_lists($item, true);

            if(empty($active_lists)) {
                return __('No Lists Connected.');
            }

            return '<ul class="ul-square"><li style="list-style:square;">'.implode('</li><li style="list-style:square;">', $active_lists).'</li></ul>';
        }

        public function feed_list_title(){
            $title = parent::feed_list_title();
            return $this->get_service_icon().$title;
        }

        protected function settings_password($field, $echo = true) {

            // Once Rocketgenius implements this, this wont be necessary.
            if(method_exists('GFAddOn', 'settings_password') && is_callable(array('GFAddOn', 'settings_password'))) {
                $output = parent::settings_password($field, false);
            } else {
                $output = parent::settings_text($field, false);

                $output = str_replace('type="text"', 'type="password" autocomplete="off"', $output);
            }

            if($echo) { echo $output; }

            return $output;
        }

        protected function single_setting_row_html($field) {

            if(empty($field['value'])) { return; }

            echo '<tr><td colspan="2">'.$field['value'].'</td></tr>';
        }

        /**
         * If you want a menu item under "Forms", uncomment below.
         */
        /*public function plugin_page() {
            require_once(GFCommon::get_base_path() . "/tooltips.php");
            parent::plugin_settings_page();
        }*/

        /**
         * Generate array of fields to be mapped.
         *
         * @return array Array of fields to be mapped.
         */
        protected function feed_settings_fields_field_map() {
            return array(
                array(
                    "label"   => "First Name",
                    "type"    => "text",
                    "name"    => "first_name",
                ),
                array(
                    "label"   => "Last Name",
                    "type"    => "text",
                    "name"    => "last_name",
                )
            );
        }

        /**
         * Generate the plugin settings.
         * @return [type] [description]
         */
        public function plugin_settings_fields() {
            return array(
                array(
                    "title"  => sprintf(__("%s Add-On Settings", 'kwsaddon'), $this->get_service_name()),
                    array(
                        'type'    => 'html',
                        'value'    => 'Welcome! If you have any questions, please',
                        'dependency' => array(&$this, 'is_not_configured'),
                    ),
                    "fields" => array(
                        array(
                            "name"    => "textbox",
                            "tooltip" => "This is the tooltip",
                            "label"   => "This is the label",
                            "type"    => "text",
                            "class"   => "small",
                            'dependency' => array(&$this, 'is_configured'),
                        ),
                        array(
                            "name"    => "textbox",
                            "tooltip" => "This is the tooltip",
                            "label"   => "This is the label",
                            "type"    => "text",
                            "class"   => "small",
                            'dependency' => array(&$this, 'is_not_configured'),
                        )
                    )
                )
            );
        }

        /**
         * Export the entry on submit.
         * @param  array $feed  Feed array
         * @param  array $entry Entry array
         * @param  array $form  Form array
         */
        public function process_feed( $feed, $entry, $form ) {

            // The settings are only normally available on the admin page.
            // We want it available everywhere.
            $this->set_settings( $feed['meta'] );

            return;
        }

        protected function get_merge_vars_from_entry($feed, $entry, $form) {

            $merge_vars = array();
            foreach($feed["meta"] as $var_tag => $field_id){

                if(empty($field_id) || strpos($var_tag, 'feed_condition') !== false) { continue; }

                $var_tag = str_replace('field_map__', '', $var_tag);

                $field = RGFormsModel::get_field($form, $field_id);

                $value = RGFormsModel::get_lead_field_value($entry, $field);

                // If the value is multi-part, like a name or address, there will be an array
                // returned. In that case, we check the array for the key of the field id.
                if(is_array($value)) {

                    if(array_key_exists($field_id, $value)) {
                        $merge_vars[$var_tag] = $value[$field_id];
                    }

                    // The key wasn't mapped. Keep going.
                    continue;

                } else {

                    // The value can be an array, serialized.
                    $value = maybe_unserialize( $value );

                    $merge_vars[$var_tag] = $value;

                }

            }

            return $merge_vars;
        }

        /**
         * Get a setting from the addon settings
         * @param  string $key Name of the key to retrieve from the settings array
         * @return mixed|NULL      If set, return the setting. Otherwise, NULL.
         */
        public function get_addon_setting($key = '') {

            $settings = maybe_unserialize(get_option(sprintf('gravityformsaddon_%s_settings', $this->_slug)));

            return isset($settings[$key]) ? $settings[$key] : NULL;
        }

        /**
         * @todo - use this for GF Directory. It has great filtering capabilities.
         */
        public function _get_results_page_config() {
            return array(
                "title" => "Directory Listings",
                "capabilities" => array("gravityforms_quiz_results"),
                "callbacks" => array(
                    "fields" => array($this, "results_fields"),
                    "calculation" => array($this, "results_calculation"),
                    "markup" => array($this, "results_markup"),
                )
            );
        }

    }
}