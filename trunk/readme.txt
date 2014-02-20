=== Gravity Forms Salesforce Add-on ===
Tags: gravity forms, forms, gravity, form, crm, gravity form, salesforce, salesforce plugin, form, forms, gravity, gravity form, gravity forms, secure form, simplemodal contact form, wp contact form, widget, sales force, customer, contact, contacts, address, addresses, address book, web to lead, web to case, web-to-lead, web-to-case, cases, leads, lead
Requires at least: 3.3
Tested up to: 3.8.1
Stable tag: trunk
Contributors: katzwebdesign,katzwebservices
Donate link:https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=zackkatz%40gmail%2ecom&item_name=Gravity%20Forms%20Salesforce%20Addon&no_shipping=0&no_note=1&tax=0&currency_code=USD&lc=US&bn=PP%2dDonationsBF&charset=UTF%2d8
License: GPLv2 or later

This is the most powerful Salesforce integration available for WordPress.

== Description ==

### This is *the* best WordPress Salesforce plugin.

### Integrate Your Forms with Salesforce
Add one setting, check a box when configuring your forms, and all your form entries will be added to Salesforce from now on. <strong>Integrating with Salesforce has never been so simple.</strong>

###Gravity Forms + Salesforce = A Powerful Combination

This free Salesforce Add-On for Gravity Forms adds contacts into Salesforce automatically, making customer relationship management simple. The setup process takes a few minutes, and your contact form will be linked with Salesforce.

#### Now with Custom Field support! ####
<a href="http://wordpress.org/extend/plugins/gravity-forms-salesforce/faq/">Read the FAQ</a> for information on how to integrate with Custom Fields.

#### Using the API
If you have the following Salesforce Editions, you can use the included API Add-on:

* Enterprise Edition
* Unlimited Edition
* Developer Edition

If you use the following Editions, you will use the included Web-to-Lead Add-on:

- Personal Edition
- Group Edition
- Professional Edition<br />*Note: You can also purchase API access for a Professional Edition.*

### Web to Case
When using the Web-to-Lead Add-on, you can choose to have form entries sent to Salesforce as Cases (like when using the Web-to-Case form) instead of as Leads.

If you have questions, comments, or issues with this plugin, <strong>please leave your feedback on the <a href="https://github.com/katzwebservices/Gravity-Forms-Salesforce/issues?state=open">Plugin Support Forum</a></strong>.

== Screenshots ==

1. Configure Salesforce field mapping. Set up custom objects.
1. Integrate with more than one form.
3. Salesforce.com settings page
4. Web-To-Lead: View of Form Feeds. You can have multiple feeds for each form.
5. Web-To-Lead: View of the Web-To-Lead Settings screen.
6. Web-To-Lead: You can easily configure the field mapping for export to Salesforce using Feeds.
7. Web-To-Lead: The plugin integrates with the [Gravity Forms Logging Tool](http://gravityhelp.com/downloads/#Gravity_Forms_Logging_Tool) to log all activity and/or errors.
8. Web-To-Lead: Specify custom fields and use the Salesforce API ID to send additional data to Salesforce.

== Installation ==

1. Upload plugin files to your plugins folder, or install using WordPress' built-in Add New Plugin installer
1. Activate the plugin
1. Go to the plugin settings page (under Forms > Settings > Salesforce)
1. Enter the information requested by the plugin.
1. Click Save Settings.
1. If the settings are correct, it will say so.
1. Follow on-screen instructions for integrating with Salesforce.

__If you are using the Web-To-Lead Add-on__ you must have Web-To-Lead enabled. [Read how to enable Web-to-Lead on Salesforce.com](https://help.salesforce.com/apex/HTViewHelpDoc?id=setting_up_web-to-lead.htm).

== Frequently Asked Questions ==

= Web-to-Lead: I'm not seeing any errors, but the entry didn't get added to Salesforce! =
Please check the box "Enable Salesforce debugging emails" in the Web-to-Lead settings page. Salesforce will send you an email with a reason the lead or case wasn't added.

= Web-to-Lead: How do I convert my existing form configuration to Feeds? =
See "Web-to-Lead: How do I create a Feed" below.

= Web-to-Lead: How do I create a Feed? =

__To create a feed:__

1. Go to "Forms" in your admin
1. Click on the name of the form you want to link with Salesforce
1. Hover over "Form Settings" and click on the "Salesforce: Web-to-Lead" link
1. Follow the instructions to create a Feed.

= Web-to-Lead: How do I modify the debug email address? =
The Salesforce debugging emails are sent to the website administrator by default. To modify this, add a filter to `gf_salesforce_salesforce_debug_email_address` that returns an email address.

= Web-to-Lead: How do I add an entry to a Campaign? =
To associate a Web-to-Lead with a Campaign, you must also set the "Member Status". [Read the Salesforce help article](http://help.salesforce.com/apex/HTViewSolution?id=000006417)

* Add two fields to your form:
	1. "Campaign ID" - The "Campaign ID" form field will be a hidden field. Add a hidden field, click the "Advanced" tab, and add the ID of the Campaign you want to add the entry to (it will look something like `902A0000000aBC1`). *For Advanced Users: Alternately, you can make this form a Radio Button field with different Campaign IDs values for different options.*
	2. "Member Status" - Your campaign has different statuses for campaign members, such as "Sent" and "Responded". You can either create a hidden field with a pre-determined value, or a Radio Button field with different status options. The values should match up with your Salesforce Campaign Status options.
* Save the form
* Go to Form Settings > Salesforce: Web-to-Lead
* Create a Feed or edit an existing Feed (see "Web-to-Lead: How do I create a Feed?" above for more information)
* Find the "Campaign ID" row and select the Campaign ID field you just created
* Find the "Member Status" row and select the Status field you just created
* Save/Update the Feed.
* There you go!

= Web-to-Lead: My input values are being cut off in Salesforce =
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
No, you only need one, and the __API plugin is recommended__: the Web-to-Lead plugin is no longer being actively developed. __If you are using Web-to-Lead, you don't need the API plugin activated. If you are using the API plugin, you don't need the Web-to-Lead activated.__

= What are the server requirements? =
Your server must support the following:

* PHP 5.x
* SOAP Enabled
* SSL Enabled
* cURL Enabled
* OpenSSL Enabled

= I have Salesforce Enterprise Edition, not Partner Edition =
Add the following to the bottom of your theme's `functions.php` file, before `?>`, if it exists:

`add_filter('gf_salesforce_enterprise', '__return_true');`

= How do I configure the API plugin? =

### How to set up integration:

1. In WordPress admin, go to Forms > Salesforce > Salesforce Settings
2. If you don't have your security token, <a href="https://na9.salesforce.com/_ui/system/security/ResetApiTokenEdit">follow this link to Reset Your Security Token</a>
3. Come back to this settings page and enter your Security Token, Salesforce.com Username and Password.
4. Save the settings, and you should be done!

= How do I set a custom Lead Source? (Web-to-Lead) =
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

= My Assignment Rule is not triggered.  How do I fix this? =

`
add_action('gf_salesforce_connection', 'gf_salesforce_set_default_assignement_rule');

function gf_salesforce_set_default_assignement_rule($client) {
    //  Two Options for Setting Assignment Rule
    //    1.  Pass in AssignmentRule ID and "false" to use a specific assignment rule.
    //    2.  Pass in null and true to trigger the DEFAULT AssignementRule
    $header = new \AssignmentRuleHeader(null, true);

    $client->setAssignmentRuleHeader($header);

    return $client;
}
`

= Can I use Salesforce Custom Fields? (Web-to-Lead) =

You can. When you are trying to map a custom field, you need to set either the "Admin Label" for the input (in the Advanced tab of each field in the  Gravity Forms form editor) or the Parameter Name (in Advanced tab, visible after checking "Allow field to be populated dynamically") to be the API Name of the Custom Field as shown in Salesforce. For example, a Custom Field with a Field Label "Web Source" could have an API Name of `SFGA__Web_Source__c`.

You can find your Custom Fields under [Your Name] &rarr; Setup &rarr; Leads &rarr; Fields, then at the bottom of the page, there's a list of "Lead Custom Fields & Relationships". This is where you will find the "API Name" to use in the Admin Label or Parameter Name.

__If that doesn't work__
If the fields are not submitting properly still, you may need to try a different approach: under "Lead Custom Fields & Relationships", click on the name of the field. The URL of the page you go to will be formatted like this: `https://na123.salesforce.com/12AB0000003CDe4?setupid=LeadFields`. You want to copy the part of the URL that looks similar to <strong><code>12AB0000003CDe4</code></strong>. Use that value instead of the API Name.

= I need to send a "Date/Time" field, not a "Date" field. How do I do that? (Web-to-Lead) =
Salesforce makes this a little difficult, sorry!

You need to add the following to your theme's `functions.php` file:

`
add_filter('gf_salesforce_use_datetime', 'filter_the_gf_salesforce_datetime', 10, 3);

/**
 * Modify whether to use Date/Time format instead of Date based on the field key.
 * @param  boolean $use_datetime Whether to use Date/Time.
 * @param  string  $key          Key of field.
 * @param  array   $vars         Array of relevant data: 'form', 'entry', 'field', 'feed'
 * @return boolean               True: Use "Date/Time" format; False: use "Date" format
 */
function filter_the_gf_salesforce_datetime($use_datetime = false, $key = '', $vars = array()) {

	// CHANGE THE NAME BELOW to the field name you want to use Date/Time for!
	if($key === 'MY__Custom_DateTime_Key') { return true; }

	// If it's not a match, use default (Date)
	return $use_datetime;
}
`

= I know I have SOAP enabled and the API plugin says I don't. =
Add this to the bottom of your theme's `functions.php` file to force loading even if a `SOAPClient` class does not exist:

`add_filter( 'gf_salesforce_soap_is_available', '__return_true');`

= What's the license for this plugin? =
This plugin is released under a GPL license.

== Changelog ==

= 2.6.3.4 (February 20, 2014) =
* Added: new hook "gf_salesforce_show_manual_export_button" to disable "send to salesforce" button/checkbox

= 2.6.3 to 2.6.3.3 (February 14, 2014) =
* Web-to-Lead: Re-added the functionality to show the "Salesforce enabled" icon in the forms list that indicate active feeds are enabled for that form.
	- Integrated that method into the KWSAddon class
* Web-to-Lead: HotFix: Check for correct Add-on class name (`KWSGFAddOn2_1`)
* Web-to-Lead: Fix: show multiple "Salesforce enabled" icons in the forms page

= 2.6.2 (February 11, 2014) =
* API Version: Added a check to make sure server supports SOAP
* API Version: Added a filter to override the SOAP check. Use `add_filter( 'gf_salesforce_soap_is_available', '__return_true');` to force loading even if a `SOAPClient` class does not exist.

= 2.6.1.1 (February 6, 2014) =
* Added: Add a new filter `gf_salesforce_format_date` to change date format for Date field type before exporting to Salesforce

= 2.6 & 2.6.1 (January 23, 2014) =
* Added: Manual export of leads - a new setting in the Form settings configuration that prevents all entries from being sent to Salesforce; only manually-approved entries may be sent.
* Added: `$feed` and `$api` variables into the `gf_salesforce_create_data` filter, so that additional things can be done in `$merge_vars` based on the feed options, and $api can be further tweaked (setAssignmentRuleHeader) based on the feed object name. Thanks, [@sc0ttkclark](https://github.com/sc0ttkclark)!
* Fixed: PHP static method warnings
* Fixed: Supports paths outside of standard WP plugin directory structure

= 2.5.3 =
* Fixed: Minor PHP static method warning
* Fixed: Dates now export properly in API and new Web-to-Lead Addon

= 2.5.2.1 =
* Fixed: Minor PHP static method warning

= 2.5.2 =
* Added "Upsert" functionality - if an object (Lead or Contact for example) already exists in Salesforce, update it rather than creating another object. Configure this setting at the bottom of existing Feeds.

= 2.5 & 2.5.1 on January 7, 2014 =
* Web-to-Lead: Completely re-wrote the add-on to provide full Feed capability. See the FAQ to learn how to set up the new feeds: "Web-to-Lead: How do I convert my existing form configuration to Feeds?" (Requires Gravity Forms 1.7+)
* Web-to-Lead: Added integration with the <a href="http://gravityhelp.com/downloads/#Gravity_Forms_Logging_Tool">Gravity Forms Logging Tool</a>
* Web-to-Lead: Added option for Salesforce debugging emails, which are very helpful!
* API: Added a filter `gf_salesforce_enterprise` to use the Enterprise Client instead of the Partner client. Thanks, [@sc0ttkclark](https://github.com/sc0ttkclark)

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

= 2.6.3.4 (February 20, 2014) =
* Added: new hook "gf_salesforce_show_manual_export_button" to disable "send to salesforce" button/checkbox

= 2.6.3 to 2.6.3.3 (February 14, 2014) =
* Web-to-Lead: Re-added the functionality to show the "Salesforce enabled" icon in the forms list that indicate active feeds are enabled for that form.
	- Integrated that method into the KWSAddon class
* Web-to-Lead: HotFix: Check for correct Add-on class name (`KWSGFAddOn2_1`)
* Web-to-Lead: Fix: show multiple "Salesforce enabled" icons in the forms page

= 2.6.2 (February 11, 2014) =
* API Version: Added a check to make sure server supports SOAP
* API Version: Added a filter to override the SOAP check. Use `add_filter( 'gf_salesforce_soap_is_available', '__return_true');` to force loading even if a `SOAPClient` class does not exist.

= 2.6.1.1 (February 6, 2014) =
* Added: Add a new filter `gf_salesforce_format_date` to change date format for Date field type before exporting to Salesforce

= 2.6 & 2.6.1 (January 23, 2014) =
* Added: Manual export of leads - a new setting in the Form settings configuration that prevents all entries from being sent to Salesforce; only manually-approved entries may be sent.
* Added: `$feed` and `$api` variables into the `gf_salesforce_create_data` filter, so that additional things can be done in `$merge_vars` based on the feed options, and $api can be further tweaked (setAssignmentRuleHeader) based on the feed object name. Thanks, [@sc0ttkclark](https://github.com/sc0ttkclark)!
* Fixed: PHP static method warnings
* Fixed: Supports paths outside of standard WP plugin directory structure

= 2.5.3 =
* Fixed: Minor PHP static method warning
* Fixed: Dates now export properly in API and new Web-to-Lead Addon

= 2.5.2.1 =
* Fixed: Minor PHP static method warning

= 2.5.2 =
* Added "Upsert" functionality - if an object (Lead or Contact for example) already exists in Salesforce, update it rather than creating another object. Configure this setting at the bottom of existing Feeds.

= 2.5 & 2.5.1 on January 7, 2014 =
* Web-to-Lead: Completely re-wrote the add-on to provide full Feed capability. See the FAQ to learn how to set up the new feeds: "Web-to-Lead: How do I convert my existing form configuration to Feeds?" (Requires Gravity Forms 1.7+)
* Web-to-Lead: Added integration with the <a href="http://gravityhelp.com/downloads/#Gravity_Forms_Logging_Tool">Gravity Forms Logging Tool</a>
* Web-to-Lead: Added option for Salesforce debugging emails, which are very helpful!
* API: Added a filter `gf_salesforce_enterprise` to use the Enterprise Client instead of the Partner client. Thanks, [@sc0ttkclark](https://github.com/sc0ttkclark)

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