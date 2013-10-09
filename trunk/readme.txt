=== Gravity Forms Salesforce Add-on ===
Tags: gravity forms, forms, gravity, form, crm, gravity form, salesforce, salesforce plugin, form, forms, gravity, gravity form, gravity forms, secure form, simplemodal contact form, wp contact form, widget, sales force, customer, contact, contacts, address, addresses, address book
Requires at least: 3.3
Tested up to: 3.6.1
Stable tag: trunk
Contributors: katzwebdesign,katzwebservices
Donate link:https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=zackkatz%40gmail%2ecom&item_name=Gravity%20Forms%20Salesforce%20Addon&no_shipping=0&no_note=1&tax=0&currency_code=USD&lc=US&bn=PP%2dDonationsBF&charset=UTF%2d8

Integrate the remarkable Gravity Forms plugin with Salesforce.

== Description ==

### This is *the* best WordPress Salesforce plugin.

### Integrate Your Forms with Salesforce
Add one setting, check a box when configuring your forms, and all your form entries will be added to Salesforce from now on. <strong>Integrating with Salesforce has never been so simple.</strong>

###Gravity Forms + Salesforce = A Powerful Combination

This free Salesforce Add-On for Gravity Forms adds contacts into Salesforce automatically, making customer relationship management simple. The setup process takes less than three minutes, and your contact form will be linked with Salesforce.

#### Now with Custom Field support! ####
<a href="http://wordpress.org/extend/plugins/gravity-forms-salesforce/faq/">Read the FAQ</a> for information on how to integrate with Custom Fields.

#### Using the API
If you have the following Salesforce Editions, you can use the included API Add-on:

* Enterprise Edition
* Unlimited Edition
* Developer Edition

If you use the following Editions, you will use the included Web to Lead Add-on:

- Personal Edition
- Group Edition
- Professional Edition<br />*Note: You can also purchase API access for a Professional Edition.*

#### Other Gravity Forms Add-ons:

* <a href="http://wordpress.org/extend/plugins/gravity-forms-highrise/">Gravity Forms Highrise Add-on</a> - Integrate Gravity Forms with Highrise, a CRM
* <a href="http://wordpress.org/extend/plugins/gravity-forms-addons/">Gravity Forms Directory & Addons</a> - Turn Gravity Forms into a WordPress Directory plugin.
* <a href="http://wordpress.org/extend/plugins/gravity-forms-constant-contact/">Gravity Forms + Constant Contact</a> - If you use Constant Contact and Gravity Forms, this plugin is for you.
* <a href="http://wordpress.org/extend/plugins/gravity-forms-exacttarget/">Gravity Forms ExactTarget Add-on</a> - Integrate Gravity Forms with ExactTarget

If you have questions, comments, or issues with this plugin, <strong>please leave your feedback on the <a href="http://wordpress.org/tags/gravity-forms-salesforce?forum_id=10">Plugin Support Forum</a></strong>.

== Screenshots ==

1. Configure Salesforce field mapping. Set up custom objects.
1. Integrate with more than one form.
3. Salesforce.com settings page

== Installation ==

1. Upload plugin files to your plugins folder, or install using WordPress' built-in Add New Plugin installer
1. Activate the plugin
1. Go to the plugin settings page (under Forms > Salesforce)
1. Enter the information requested by the plugin.
1. Click Save Settings.
1. If the settings are correct, it will say so.
1. Follow on-screen instructions for integrating with Salesforce.

== Frequently Asked Questions ==

= Web to Lead: My input values are being cut off in Salesforce =
If you are submitting to a "Multi PickList" field in Salesforce, the values need to be separated with ';' instead of ','. Add a filter to your `functions.php` file:

`
add_filter('gf_salesforce_implode_glue', 'change_salesforce_implode_glue');

/**
 * Change the way the data is submitted to salesforce to force submission as multi picklist values.
 * @param  string $glue  ',' or ';'
 * @param  array $field The field to modify the glue for
 * @return string        ',' or ';'
 */
function change_salesforce_implode_glue($glue, $field) {

	// Change this to the Salesforce API Name of the field that's not being passed properly.
	$name_of_sf_field = 'ExampleMultiSelectPicklist__c';

	// If the field being checked is the Salesforce field that is being truncated, return ';'
	if($field['inputName'] === $name_of_sf_field || $field['adminLabel'] === $name_of_sf_field) {
		return ';';
	}

	// Otherwise, return default.
	return $glue;
}

`

= How do I modify the Soap, Proxy, WSDL and connection settings? =

* `gf_salesforce_wsdl` - Path to the WSDL (string)
* `gf_salesforce_proxy` - Proxy settings as an object with properties host, port (integer, not a string), login and password (object, ideally a `ProxySettings` object)
* `gf_salesforce_soap_options` Additional options to send to the SoapClient constructor. (array) See <a href="http://php.net/manual/en/soapclient.soapclient.php">http://php.net/manual/en/soapclient.soapclient.php</a>
* `gf_salesforce_connection` - Modify the `SforcePartnerClient` object before it's returned.

See the FAQ item above for an example of using a filter.

= Do I need both plugins activated? =
No, you only need one, and the __API plugin is recommended__: the Web to Lead plugin is no longer being actively developed. __If you are using Web to Lead, you don't need the API plugin activated. If you are using the API plugin, you don't need the Web to Lead activated.__

= What are the server requirements? =
Your server must support the following:

* PHP 5.x
* SOAP Enabled
* SSL Enabled
* cURL Enabled
* OpenSSL Enabled

= How do I configure the API plugin? =

### How to set up integration:

1. In WordPress admin, go to Forms > Salesforce > Salesforce Settings
2. If you don't have your security token, <a href="https://na9.salesforce.com/_ui/system/security/ResetApiTokenEdit">follow this link to Reset Your Security Token</a>
3. Come back to this settings page and enter your Security Token, Salesforce.com Username and Password.
4. Save the settings, and you should be done!

= How do I set a custom Lead Source? (Web to Lead) =
This feature support was added in version 1.1.1. `gf_salesforce_lead_source` is the filter.

Add the following to your theme's `functions.php` file. Modify as you see fit:

`
add_filter('gf_salesforce_lead_source', 'make_my_own_lead_source', 1, 3);

function make_my_own_lead_source($lead_source, $form_meta, $data) {
    // $lead_source - What was about to be used (normally Gravity Forms Form Title)
    // $form_meta - Gravity Forms form details
    // $data - The data passed to Salesforce

    return $lead_source; // Return something else if you want to.
}
`

= Can I use Salesforce Custom Fields? (Web to Lead) =

You can. When you are trying to map a custom field, you need to set either the "Admin Label" for the input (in the Advanced tab of each field in the  Gravity Forms form editor) or the Parameter Name (in Advanced tab, visible after checking "Allow field to be populated dynamically") to be the API Name of the Custom Field as shown in Salesforce. For example, a Custom Field with a Field Label "Web Source" could have an API Name of `SFGA__Web_Source__c`.

You can find your Custom Fields under [Your Name] &rarr; Setup &rarr; Leads &rarr; Fields, then at the bottom of the page, there's a list of "Lead Custom Fields & Relationships". This is where you will find the "API Name" to use in the Admin Label or Parameter Name.

= What's the license for this plugin? =
This plugin is released under a GPL license.

== Changelog ==

= 2.4.1 =
* Added more filters: `gf_salesforce_mapped_field_name`, `gf_salesforce_mapped_value`, `gf_salesforce_mapped_value_{$field_name}`. Thanks, @sc0ttkclark!

= 2.4 =
* Added filters to modify connection details. See the FAQ item "How do I modify the Soap, Proxy, WSDL and connection settings?"
* Updated to latest Salesforce PHP Toolkit library

= 2.3 & 2.3.1 & 2.3.2 =
* API
	* __Now fully supports custom objects!__
	* Fixed error with endless spinning when choosing "Select the form to tap into"
	* Fixed a few PHP notices
	* Now supports line breaks in submitted content
* Web to Lead
	* Fixed <a href="http://wordpress.org/support/topic/form-editing-broken-with-saleforce-web-to-lead">issue</a> on Form Settings page caused by Gravity Forms 1.7.7 update.
	* Now properly handles data with `'` and `"` - no longer adds slashes

= 2.2.7 =
* Updated Web to Lead
	- Fixed Lists input type
	- Fixed issue where checkboxes and multiselects were being added as Array

= 2.2.6 =
* Updated Web to Lead to work with Gravity Forms 1.7+ form settings screens

= 2.2.5 =
* Fixed Web to Lead picklist functionality. Thanks, <a href="http://d3vit.com/how-to-fix-gravity-forms-salesforce-plugin-picklist-multi-select/">Ryan Allen</a>!

= 2.2.4.3 =
* Fixed issue that should never have happened, but did: the "Gravity Forms Not Installed" message showed up for an user on the front-end of their site and prevented them from logging in.
* Fixed admin PHP Notice when gravity forms is not activated

= 2.2.4.2 =
* Fixed one more issue with "Array" as submitted value when using select dropdown lists and Salesforce Field Mapping. *Note: after updating the plugin, you may need to re-save your affected forms.*

= 2.2.4.1 =
* Fixed issue introduced in 2.2.4 that prevented options from being editable when Salesforce Field Mapping was not enabled.

= 2.2.4 =
* Fixed issue with selecting Live Field Mapping where object information wouldn't load if there was an apostophe in the field name or description.
* Improved Live Field Mapping display: disabled fields stay looking disabled on form save.
* Fixed issue where Live Field Mapping would not send form data properly. This was caused by the plugin wrongly assigning "inputs" to the fields, causing the field IDs not to match upon submit.
* Fixed version number on Gravity Forms Salesforce Web to Lead Add-On so that it won't always seem like there's an update waiting.

= 2.2.3 =
* Fixed issue where Web to Lead Form Editor would no longer load if Salesforce API plugin was enabled and not configured. <a href="http://wordpress.org/support/topic/since-v-222-upgrade-cant-edit-any-forms">As reported here</a> and <a href="http://wordpress.org/support/topic/php-fatal-error-with-invalid-credentials">here</a>.
* Fixed issue where Salesforce Fields disappeared when mapping fields using the Salesforce API plugin, as <a href="http://wordpress.org/support/topic/patch222-fix-issue-with-fields-disappearing-when-trying-to-wire-api-up">reported here</a>. Thanks to @gmcinnes for the fix.
* Improved XML validation by escaping XML characters `< ' " & >` and also removing UTF-8 "control characters". Should solve the <a href="http://wordpress.org/support/topic/cleaning-utf-8-for-soap-submission">issue reported here</a> in regards to "PHP Fatal error: Uncaught SoapFault exception: [soapenv:Client] An invalid XML character (Unicode: 0xb) was found in the element content of the document."

= 2.2.2 =
* Added Edit Form and Preview Form links to Salesforce.com Feeds list
* Fixed issues with array processing with new `_remove_empty_fields` and `_convert_to_utf_8` methods

= 2.2.1 =
* API: Made live updating optional with radio button
* API: Added cache time dropdown
* API: Refreshes transients when changing cache time
* Web to Lead: Fixed issue where Salesforce icon wouldn't show up on load in Edit Form screen
* Web to Lead: Salesforce icon now shows properly on Edit Forms screen

= 2.2 =
* Added Salesforce picklist value mapping with Checkbox, Radio, Drop Down, and Multi Select fields. This feature dynamically pulls in fields from Salesforce so you don't need to re-create them. The downside? You need to modify default fields, field order in Salesforce.
* Added fixes provided by <a href="http://wordpress.org/support/profile/gmcinnes">gmcinnes</a>: <a href="http://wordpress.org/support/topic/patch-make-sure-new-error-email-functionality-catches-exceptions">Make sure new error email functionality catches exceptions</a>, <a href="http://wordpress.org/support/topic/patch211-dont-send-empty-fields-to-salesforce">Don't send empty fields to Salesforce</a>, and <a href="http://wordpress.org/support/topic/patch211-make-sure-zeros-send-to-salesforce">Make sure to send zeros to Salesforce</a>

= 2.1.1 =
* Fixed issue <a href="http://wordpress.org/support/topic/plugin-gravity-forms-salesforce-add-on-no-settings-for-api-version">reported here</a> with fatal error

= 2.1 =
* Fixed: Added support for multiselect fields other fields with multiple responses
* Added: Entries now get assigned a Salesforce ID that link directly to the Salesforce contact or object (API plugin only)
* Added: Notes are now added to Entries with the success or error messages from exporting to Salesforce (API plugin only)
* Added: You can have Salesforce export errors emailed to you when they occur (API plugin only)

= 2.0.2 =
* Fixed issue with HTML encoding to <a href="http://wordpress.org/support/topic/submitting">fix SOAP fatal error</a>. Thanks, gmcinnes!

= 2.0.1 =
* Renamed the plugin files so that you wouldn't need to re-activate.

= 2.0 =
* Added API plugin. A complete rewrite; switched to SOAP API. Will require re-configuring the plugin.
* Renamed 1.x plugin "Gravity Forms Salesforce Web to Lead Add-On"

= 1.1.3 =
* Fixed issue with latest Gravity Forms preventing Salesforce checkbox from showing up - thanks <a href="http://msmprojects.com/">Michael Manley</a>!

= 1.1.2 =
* Fixed issue where entered Salesforce field mapping labels were being overwritten by auto-labeling.
    - Added filter `gf_salesforce_autolabel` to turn off auto-labeling by adding `add_filter('gf_salesforce_autolabel', '__return_false');` to your theme's functions.php file.
    - Made auto-labeling much less aggressive: now only matches exact matches for First Name, Company, etc.
* Added support for checkboxes and other multiple-item fields using `implode()` PHP functionality: lists will be converted to comma-separated values.

= 1.1.1 =
* Fixes issue where all forms get submitted to Salesforce, not only enabled forms (<a href="http://www.seodenver.com/forums/topic/all-forms-posting-to-saleforce/">reported on support forum</a>).
* Added a new filter to modify the lead source `gf_salesforce_lead_source`, <a href="http://wordpress.org/support/topic/657400" rel="nofollow">as requested here</a>. Passes three arguments: `$lead_source`, `$gf_form_meta`, `$salesforce_data`.

= 1.1 =
* Added support for Custom Fields (view the FAQ here, or the Gravity Forms Salesforce Add-on Settings page for this plugin)
* Improved authentication check in the settings page - no longer creates a blank lead.
* Fixed some PHP notices

= 1.0 =
* Launch!

== Upgrade Notice ==

= 2.4.1 =
* Added more filters: `gf_salesforce_mapped_field_name`, `gf_salesforce_mapped_value`, `gf_salesforce_mapped_value_{$field_name}`. Thanks, @sc0ttkclark!

= 2.4 =
* Added filters to modify connection details. See the FAQ item "How do I modify the Soap, Proxy, WSDL and connection settings?"
* Updated to latest Salesforce PHP Toolkit library

= 2.3 & 2.3.1 & 2.3.2 =
* API
	* __Now fully supports custom objects!__
	* Fixed error with endless spinning when choosing "Select the form to tap into"
	* Fixed a few PHP notices
	* Now supports line breaks in submitted content
* Web to Lead
	* Fixed <a href="http://wordpress.org/support/topic/form-editing-broken-with-saleforce-web-to-lead">issue</a> on Form Settings page caused by Gravity Forms 1.7.7 update.
	* Now properly handles data with `'` and `"` - no longer adds slashes

= 2.2.7 =
* Updated Web to Lead
	- Fixed Lists input type
	- Fixed issue where checkboxes and multiselects were being added as Array

= 2.2.6 =
* Updated Web to Lead to work with Gravity Forms 1.7+ form settings screens

= 2.2.5 =
* Fixed Web to Lead picklist functionality. Thanks, <a href="http://d3vit.com/how-to-fix-gravity-forms-salesforce-plugin-picklist-multi-select/">Ryan Allen</a>!

= 2.2.4.3 =
* Fixed issue that should never have happened, but did: the "Gravity Forms Not Installed" message showed up for an user on the front-end of their site and prevented them from logging in.
* Fixed admin PHP Notice when gravity forms is not activated

= 2.2.4.2 =
* Fixed one more issue with "Array" as submitted value when using select dropdown lists and Salesforce Field Mapping. *Note: after updating the plugin, you may need to re-save your affected forms.*

= 2.2.4.1 =
* Fixed issue introduced in 2.2.4 that prevented options from being editable when Salesforce Field Mapping was not enabled.

= 2.2.4 =
* Fixed issue with selecting Live Field Mapping where object information wouldn't load if there was an apostophe in the field name or description.
* Improved Live Field Mapping display: disabled fields stay looking disabled on form save.
* Fixed issue where Live Field Mapping would not send form data properly. This was caused by the plugin wrongly assigning "inputs" to the fields, causing the field IDs not to match upon submit.
* Fixed version number on Gravity Forms Salesforce Web to Lead Add-On so that it won't always seem like there's an update waiting.

= 2.2.3 =
* Fixed issue where Web to Lead Form Editor would no longer load if Salesforce API plugin was enabled and not configured. <a href="http://wordpress.org/support/topic/since-v-222-upgrade-cant-edit-any-forms">As reported here</a> and <a href="http://wordpress.org/support/topic/php-fatal-error-with-invalid-credentials">here</a>.
* Fixed issue where Salesforce Fields disappeared when mapping fields using the Salesforce API plugin, as <a href="http://wordpress.org/support/topic/patch222-fix-issue-with-fields-disappearing-when-trying-to-wire-api-up">reported here</a>. Thanks to @gmcinnes for the fix.
* Improved XML validation by escaping XML characters `< ' " & >` and also removing UTF-8 "control characters". Should solve the <a href="http://wordpress.org/support/topic/cleaning-utf-8-for-soap-submission">issue reported here</a> in regards to "PHP Fatal error: Uncaught SoapFault exception: [soapenv:Client] An invalid XML character (Unicode: 0xb) was found in the element content of the document."

= 2.2.2 =
* Added Edit Form and Preview Form links to Salesforce.com Feeds list
* Fixed issues with array processing with new `_remove_empty_fields` and `_convert_to_utf_8` methods

= 2.2.1 =
* Made live updating optional with radio button
* Added cache time dropdown
* Refreshes transients when changing cache time

= 2.2 =
* Added Salesforce picklist value mapping with Checkbox, Radio, Drop Down, and Multi Select fields. This feature dynamically pulls in fields from Salesforce so you don't need to re-create them. The downside? You need to modify default fields, field order in Salesforce.
* Added fixes provided by <a href="http://wordpress.org/support/profile/gmcinnes">gmcinnes</a>: <a href="http://wordpress.org/support/topic/patch-make-sure-new-error-email-functionality-catches-exceptions">Make sure new error email functionality catches exceptions</a>, <a href="http://wordpress.org/support/topic/patch211-dont-send-empty-fields-to-salesforce">Don't send empty fields to Salesforce</a>, and <a href="http://wordpress.org/support/topic/patch211-make-sure-zeros-send-to-salesforce">Make sure to send zeros to Salesforce</a>

= 2.1.1 =
* Fixed issue <a href="http://wordpress.org/support/topic/plugin-gravity-forms-salesforce-add-on-no-settings-for-api-version">reported here</a> with fatal error

= 2.1 =
* Added: Entries now get assigned a Salesforce ID that link directly to the Salesforce contact or object (API plugin only)
* Added: Notes are now added to Entries with the success or error messages from exporting to Salesforce (API plugin only)
* Added: You can have Salesforce export errors emailed to you when they occur (API plugin only)

= 2.0.2 =
* Fixed issue with HTML encoding to <a href="http://wordpress.org/support/topic/submitting">fix SOAP fatal error</a>. Thanks, gmcinnes!

= 2.0 =
* Complete rewrite; switched to SOAP API. Will require re-configuring the plugin.

= 1.1.3 =
* Fixed issue with latest Gravity Forms preventing Salesforce checkbox from showing up - thanks <a href="http://msmprojects.com/">Michael Manley</a>!

= 1.1.2 =
* Fixed issue where entered Salesforce field mapping labels were being overwritten by auto-labeling.
    - Added filter `gf_salesforce_autolabel` to turn off auto-labeling by adding `add_filter('gf_salesforce_autolabel', '__return_false');` to your theme's functions.php file.
    - Made auto-labeling much less aggressive: now only matches exact matches for First Name, Company, etc.
* Added support for checkboxes and other multiple-item fields using `implode()` PHP functionality: lists will be converted to comma-separated values.

= 1.1.1 =
* Fixes issue where all forms get submitted to Salesforce, not only enabled forms (<a href="http://www.seodenver.com/forums/topic/all-forms-posting-to-saleforce/">reported on support forum</a>).
* Added a new filter to modify the lead source `gf_salesforce_lead_source`, <a href="http://wordpress.org/support/topic/657400" rel="nofollow">as requested here</a>. Passes three arguments: `$lead_source`, `$gf_form_meta`, `$salesforce_data`.

= 1.1 =
* Added support for Custom Fields (view the FAQ here, or the Gravity Forms Salesforce Add-on Settings page for this plugin)
* Improved authentication check in the settings page - no longer creates a blank lead.
* Fixed some PHP notices

= 1.0 =
* Launch!