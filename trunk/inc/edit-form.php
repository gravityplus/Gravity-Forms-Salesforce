<?php

class GFSalesforce_FieldMapping {

    function __construct() {

        if(is_admin()) {

            if(self::is_gravity_page('gf_edit_forms')) {
                add_filter('gform_tooltips', array(&$this, 'tooltips')); //Filter to add a new tooltip
                add_action( "gform_editor_js", array(&$this, "editor_js")); // Now we execute some javascript technicalitites for the field to load correctly
                add_action("gform_field_standard_settings", array(&$this,"use_as_entry_link_settings"), 10, 2);
                add_action('admin_head', array(&$this, 'admin_head'));
            } else if(defined('RG_CURRENT_PAGE') && in_array(RG_CURRENT_PAGE, array("admin-ajax.php"))) {
                // Get the list of fields available for the object type
                add_action('wp_ajax_select_salesforce_object', array(&$this, 'select_salesforce_object_type'));
            }
        }
        add_filter("gform_admin_pre_render", array(&$this, 'override_form'), 10);
        add_filter("gform_pre_render", array(&$this, 'override_form'), 10, 2);
    }

/**
 * Replace the Gravity Forms form choices with remotely-pulled Salesforce picklist options.
 * @param  array $form The Gravity Forms array object
 * @param  [type] $ajax [description]
 * @return array       Modified GF array object
 */
    function override_form($form, $ajax = null) {

        if( empty( $form ) ) {
            return;
        }

        foreach($form['fields'] as $field) {

            // If the field has mapping enabled, and the object and field are defined, replace it
            if(
               !empty($field['salesforceMapEnabled']) &&
               !empty($field['salesforceMapObject']) &&
               !empty($field['salesforceMapField']) &&
               !empty($field['salesforceMapType']) && $field['salesforceMapType'] === 'live'
               ) {
                $remote_field = GFSalesforce::getField($field['salesforceMapObject'], $field['salesforceMapField']);

                if($remote_field && !is_wp_error($remote_field) && !empty($remote_field['picklistValues']) && is_array($remote_field['picklistValues'])) {
                    $field = self::apply_picklist_to_field($remote_field['picklistValues'], $field);
                }
            }
        }
        return $form;
    }

    private function apply_picklist_to_field($picklistValues, $field) {
        $choices = $inputs = array();

        $i = 0;
        foreach ($picklistValues as $key => $value) {
            if(empty($value->active)) { continue; }
            $i++;
            $choices[] = array(
                'text' => $value->label,
                'value' => $value->value,
                'isSelected' => floatval(!empty($value->defaultValue)),
                'price' => '',
            );

            $inputs[] = array(
                'id' => $field['id'].'.'.$i,
                'label' => $value->label,
            );
        }

        if(!empty($choices)) { $field['choices'] = $choices; }
        switch($field['type']) {
            case 'select':
            case 'multiselect':
                $field['inputs'] = '';
                break;
            case 'radio':
            case 'checkboxes':
                if(!empty($inputs)) { $field['inputs'] = $inputs; }
                break;
        }
        return $field;
    }

    function admin_head() {
        ?>
        <style type="text/css">
            td.salesforce_field_cell {
                border-bottom: 1px solid #ccc!important;
                vertical-align:top;
                padding: 4px;
            }
            #salesforce_map_ui {
                display: none;
                clear: both;
                padding-left: .25em;
            }

            #salesforce_field_group ul {
                max-height:200px; overflow-y:auto;
                margin: 0;
                -moz-column-count: 2;
                -moz-column-gap: 10px;
                -webkit-column-count: 2;
                -webkit-column-gap: 10px;
                column-count: 2;
                column-gap: 10px;
            }
        </style>
       <?php
    }

    function select_salesforce_object_type() {
        check_ajax_referer("select_salesforce_object", "select_salesforce_object");

        $form_id =  intval($_POST["form_id"]);

        //fields meta
        $form = RGFormsModel::get_form_meta($form_id);

        //getting list of all Salesforce merge variables for the selected contact list

        try {
            $fields = GFSalesforce::getFieldsForObject(esc_html($_POST['objectType']), array('picklist', 'multipicklist'));
            $str = self::get_field_mapping($form_id, $fields);
        } catch(Exception $e) {
            $str = sprintf('<div id="salesforce_field_group"><strong>%s</strong> %s</div>', __('Error:', 'gravity-forms-salesforce'), $e->getMessage());
            $str = str_replace(array("\n", "\t", "\r"), '', str_replace("'", "\'", $str));
        }

        die("EndSelectForm('{$str}');");
    }

    private static function get_picklist_ul($field) {
        if(empty($field['picklistValues'])) { return ''; }
        $str = '<ul class="ul-square">';
        foreach($field['picklistValues'] as $value) {
            if(empty($value->active)) { continue; }
            $default = !empty($value->defaultValue) ?  '<strong class="default"> '.esc_html__('(Default)', "gravity-forms-salesforce").'</strong>' : '';
            $str .= '<li style="margin:0; padding:0;" data-default="'.floatval(!empty($value->defaultValue)).'" data-value="'.htmlentities($value->value).'" data-label="'.htmlentities($value->label).'">'.htmlentities($value->label).$default.'</li>';
        }
        $str .= '</ul>';
        return $str;
    }

    private static function get_field_mapping($form_id, $fields) {

        $usedFields = array();
        $str = $custom = $standard = '';

        //getting list of all fields for the selected form

        $str .= "
        <div id='salesforce_field_group'>
            <table cellpadding='0' cellspacing='0' class='form-table'>
                <thead class='screen-reader-text'>
                    <tr>
                        <th scope='col' class='salesforce_col_heading'>" . __("Pickist Field", "gravity-forms-salesforce") . "</th>
                        <th scope='col' class='salesforce_col_heading'>" . __("Form Fields", "gravity-forms-salesforce") . "</th>
                    </tr>
                </thead>
                <tbody>";

        // Don't show fields with empty picklist options
        foreach ($fields as $key => $field) {
            if(empty($field['picklistValues'])) {
                unset($fields[$key]);
            }
        }

        if(!empty($fields)) {
            foreach($fields as $field){

                $field_desc = '';
                $row = "
                    <tr>
                        <td class='salesforce_field_cell' style='text-align:center; width:2em'>
                            <label for='salesforce_map_field_{$field['tag']}'>
                                <input value='{$field['tag']}' type='radio' name='salesforce_map_field' id='salesforce_map_field_{$field['tag']}' />
                            </label>
                        </td>
                        <td class='salesforce_field_cell'>
                            <label for='salesforce_map_field_{$field['tag']}'><strong>" . stripslashes( $field['name'] )  . "</strong>
                            <span class='description' style='display:block'>Field Choices:</span>
                            ".self::get_picklist_ul($field)."
                            </label>
                        </td>
                    </tr>";

                $str .= $row;

            } // End foreach merge var.
        } else {
            $str .= '<tr>
                        <td class="salesforce_field_cell" style="vertical-align:top; padding-right:.5em;">
                            This object has no Pick List or Multi Pick List fields.
                        </td>
                    </tr>';
        }

        $str .= "
                </tbody>
            </table>
        </div>";

        $str = str_replace(array("\n", "\t", "\r"), '', str_replace("'", "\'", $str));

        return $str;
    }

    function editor_js() {
    ?>

    <script type='text/javascript'>

        jQuery(document).ready(function($) {

            // Show the Salesforce settings only on applicable fields
            var enableSalesforceForFields = ['textarea', 'select', 'checkbox', 'radio', 'multiselect'];
            for (var i=0,len=enableSalesforceForFields.length; i<len; i++) {
                fieldSettings[enableSalesforceForFields[i]] += ", .salesforce_setting";
            }

            // When the field starts to show in the form editor, run this function
            $(document).bind("gform_load_field_settings", function(event, field, form){

                // Reset the fields
                $('#salesforce_map_enabled', event.target).val('');
                $("#salesforce_object_list", event.target).val('');
                $('#salesforce_map_enabled, input[name=salesforce_map_field], input[name=salesforce_map_type]', event.target).attr('checked', false);

                if(field["salesforceMapEnabled"] == true) {

                    UpdateFieldChoices(field.type);
                    LoadFieldChoices(field);
                    SFDisableChoices();

                    $("#salesforce_map_enabled", event.target).attr("checked", true);

                    if(field['salesforceMapType']) {
                        $("input#salesforce_map_type_"+field['salesforceMapType'], event.target).attr("checked", true);
                    } else {
                        $("input#salesforce_map_type_live", event.target).attr("checked", true);
                    }

                    // Set the <select> value, trigger loading of fields
                    $("#salesforce_object_list", event.target).val(field["salesforceMapObject"]);

                } else {
                    field.salesforceMapEnabled = false;
                    field.salesforceMapObject = false;
                    field.salesforceMapField = false;
                    field.salesforceMapType = false;
                }

                $("#salesforce_map_enabled, #salesforce_map_type_live, #salesforce_object_list", event.target).trigger('change');
            });

            $('#salesforce_map_enabled').live('click change', function () {
                var checked = $(this).is(':checked');
                SetFieldProperty('salesforceMapEnabled', checked);
                if(checked === true) {
                    $('#salesforce_map_ui').show();
                } else {
                    $('#salesforce_map_ui').hide();
                }
                SFDisableChoices();
            });

            $('input[name=salesforce_map_type]').live('click change', function () {

                SFDisableChoices();

                // If you can continue to edit, don't disable the fields!
                SetFieldProperty('salesforceMapType', $(this).val());
            });

            $('#salesforce_object_list').live('change', function () {
                SetFieldProperty('salesforceMapObject', $(this).val());
            });

            $('input[name=salesforce_map_field]').live('change click', function () {

                // Update the selected field
                SetFieldProperty('salesforceMapField', $(this).val());

                // Get the current field
                var field = GetSelectedField();
                var inputType = GetInputType(field);

                // We add the Object choices in the list to the field choices.
                field["choices"] = new Array();

                $("label[for=salesforce_map_field_"+$(this).val()+'] ul li').each(function() {
                    choice = new Choice();
                        choice.text = $(this).data('label');
                        choice.value = $(this).data('value');
                        choice.isSelected = $(this).data('default') * 1;

                    field["choices"].push(choice);
                });

                // We update the field choices in the field display
                UpdateFieldChoices(field.type);
                LoadFieldChoices(field);

                // We disable editing of the choices
                SFDisableChoices();
            });

        });

        // We disable editing of the choices and remove
        function SFDisableChoices() {

            <?php if(!apply_filters('disable_salesforce_choices', true)) { echo 'return;'; } ?>

            var field = GetSelectedField();

            // If it's not yet set, or if you're just populating choices one-time, no disabling.
            if(!field.salesforceMapEnabled || jQuery('input[name=salesforce_map_type]:checked').length === 0 || jQuery('input[name=salesforce_map_type]:checked').val() === 'once') {
                // Enable modifying the choices.
                jQuery('#field_choices input').attr('disabled', false);

                // Show sorting, add, and remove choices images
                jQuery('.add_field_choice, .delete_field_choice, .field-choice-handle, .choices_setting input.button').show();
                jQuery('#field_choice_values_enabled').parent('div').show();
            } else {
                // Disable modifying the choices.
                jQuery('#field_choices input').attr('disabled', true);

                // Hide sorting, add, and remove choices images
                jQuery('.add_field_choice, .delete_field_choice, .field-choice-handle,  .choices_setting input.button').hide();
                jQuery('#field_choice_values_enabled').parent('div').hide();
            }
        }

        function SelectForm(listId, formId){

            if(!formId || !listId){
                jQuery("#salesforce_field_group").slideUp();
                return;
            }

            jQuery(".salesforce_wait").css({
                'display': 'inline-block'
            });
            jQuery("#salesforce_field_group").slideUp();

            var mysack = new sack("<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin-ajax.php" );
            mysack.execute = 1;
            mysack.method = 'POST';
            mysack.setVar( "action", "select_salesforce_object" );
            mysack.setVar( "select_salesforce_object", "<?php echo wp_create_nonce("select_salesforce_object") ?>" );
            mysack.setVar( "objectType", listId);
            mysack.setVar( "form_id", formId);
            mysack.encVar( "cookie", document.cookie, false );
            mysack.onError = function() {
                jQuery(".salesforce_wait").hide();
                alert('<?php _e("Ajax error selecting the Salesforce object.", "gravity-forms-salesforce") ?>' );
            };
            mysack.onCompletion = function() {
                field = GetSelectedField();
                if(field['salesforceMapField']) {
                    jQuery('#salesforce_map_field_' + field['salesforceMapField'], jQuery('#field_'+field['id'])).attr('checked', true);
                }
            }
            mysack.runAJAX();
            return true;
        }

        function SelectList(listId){
            if(listId){
                jQuery("#salesforce_form_container").slideDown();
                jQuery("input[name=salesforce_map_field]").attr('checked', false);
            }
            else{
                jQuery("#salesforce_form_container").slideUp();
                EndSelectForm("");
            }
        }

        function EndSelectForm(fieldList){
            //setting global form object
            //form = form_meta;

            if(fieldList){
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

    </script>
    <?php
    }

    public function tooltips($tooltips){
        $tooltips['salesforce_map_values_live'] = __(sprintf('%sUpdate from Salesforce%sIf you update a picklist in Salesforce, the modifications will be added to your form without having to edit the field Choices. You will not be able to edit the Choices in Gravity Forms; they will be updated only in Salesforce. The order of Choices as well as the default value is determined by the Salesforce picklist field settings.', '<h6>', '</h6>'), 'gravity-forms-salesforce');
        $tooltips['salesforce_map_values_once'] = __(sprintf('%sUpdate from Salesforce%s The field Choices will be editable and will not be updated live.', '<h6>', '</h6>'), 'gravity-forms-salesforce');
        return $tooltips;
    }

    public function use_as_entry_link_settings($position, $form_id){
        #$form = get_form($form_id);
        $form = RGFormsModel::get_form_meta($form_id);

        //create settings on position 50 (right after Admin Label)
        if($position === -1){ ?>
            <li class="use_as_entry_link salesforce_setting field_setting">

            <label for="salesforce_map_enabled">
                <input type="checkbox" id="salesforce_map_enabled" name="salesforce_map_enabled" value="1" /> <?php _e("Enable Salesforce Field Mapping?", "gravity-forms-salesforce"); ?>
                <img alt="<?php esc_attr_e("Enable Salesforce.com Mapping", "gravity-forms-salesforce") ?>" src="<?php echo GFSalesforce::get_base_url()?>/assets/images/salesforce-50x50.png" style="margin:0 7px 0 0;" width="20" height="20" />
            </label>

            <div id="salesforce_map_ui">

                <label for="salesforce_map_type_live">
                    <input type="radio" id="salesforce_map_type_live" name="salesforce_map_type" value="live" /> <?php _e("Live Remote Field Mapping?", "gravity-forms-salesforce"); gform_tooltip("salesforce_map_values_live"); ?>
                    <span class="howto" style="padding-left:1.25em;"><?php _e("Field Choices will be synced with Salesforce picklist values.", "gravity-forms-salesforce"); ?></span>
                </label>
                <label for="salesforce_map_type_once">
                    <input type="radio" id="salesforce_map_type_once" name="salesforce_map_type" value="once" /> <?php _e("Only Populate Choices", "gravity-forms-salesforce"); gform_tooltip("salesforce_map_values_once") ?>
                    <span class="howto" style="padding-left:1.25em;"><?php _e("Field Choices will not be updated live and are editable.", "gravity-forms-salesforce"); ?></span>
                </label>

                <label for="salesforce_object_list" class=" inline"><?php _e("Choose Object: ", "gravity-forms-salesforce"); ?>
                <?php

            $lists = GFSalesforce::getObjectTypes();

            if(!$lists) {
                echo wpautop(__("Could not load Salesforce objects.\nError: ", "gravity-forms-salesforce"));
                echo isset($api->errorMessage) ? $api->errorMessage : '';
            } else { ?>
                <select id="salesforce_object_list" name="salesforce_object_type" onchange="SelectList(jQuery(this).val()); SelectForm(jQuery(this).val(), <?php echo $form_id; ?>);">
                    <option value=""><?php _e("Select a Salesforce Object", "gravity-forms-madmimi"); ?></option>
                <?php
                foreach ($lists as $list){
                    ?>
                    <option value="<?php echo esc_html($list) ?>"><?php echo esc_html($list) ?></option>
                    <?php
                }
                ?>
            </select></label><span class="spinner salesforce_wait" style="display: none; position: absolute;"></span>
            <div id="salesforce_field_list"></div>
        </div>
            <?php
            }
        ?>
            </li>
            <?php
        } // End if $position === 500
    }

    //Returns true if the current page is one of Gravity Forms pages. Returns false if not
    private static function is_gravity_page($page = array()){
        return GFSalesforce::is_gravity_page($page);
    }

}

new GFSalesforce_FieldMapping;
