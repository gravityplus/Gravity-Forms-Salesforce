<?php

class Web_To_Lead_Test extends PHPUnit_Framework_TestCase {

	var $instance;
	var $setting_slug;
	var $settings = array(
		'org_id' => NULL,
		'debug'  => true,
		'debug_email' => true
	);

	var $enterpriseID = '00DG0000000jahN';
	var $partnerID = '00DG0000000jahY';

	protected function setUp() {
		parent::setUp();

		require_once(plugin_dir_path(__FILE__).'../../gravityforms/gravityforms.php');
		require_once(plugin_dir_path(__FILE__).'../web-to-lead.php');
		require_once(plugin_dir_path(__FILE__).'../lib/kwsaddon.php');
		require_once(plugin_dir_path(__FILE__).'../web-to-lead.addon.php');

		$WebToLeadAddon = new KWSGFWebToLeadAddon;
		$WebToLeadAddon->add_custom_hooks();
		$this->setting_slug = sprintf('gravityformsaddon_%s_settings', $WebToLeadAddon->get_slug());

		// Set the default options
		update_option( $this->setting_slug, $this->settings);
	}

	function test_get_addon_setting() {

		$WebToLeadAddon = new KWSGFWebToLeadAddon;

		// Default Setting
		$this->assertNull($WebToLeadAddon->get_addon_setting('org_id'));

		// Empty Org ID
		update_option( $this->setting_slug, array('org_id' => 'EXAMPLE') );

		$this->assertEquals('EXAMPLE', $WebToLeadAddon->get_addon_setting('org_id'));

	}

	function test_process_feed() {
		// For now, we're going to have to hard-code the form data

		$merge_vars = array(
			'email' => 'unittest@example.com',
			'employees' => 4,
			'first_name' => 'Unit',
			'last_name' => 'Test',

			// Campaign ID
			'Campaign_ID' => '701G0000000eEA5',

			// Campaign Status
			'member_status' => 'Sent',

			// Custom fields
			'Multiple_Picklist__c' => array(
				'Picklist 1',
				'Picklist 2',
				'Picklist 3',
			),
		);

		$WebToLeadAddon = new KWSGFWebToLeadAddon;

		update_option( $this->setting_slug, array('org_id' => $this->partnerID) );

		$return = $WebToLeadAddon->send_request($merge_vars, true);

		print_r($return);
	}

	function test_send_request() {

		// Force refresh of validity
		$_POST['gform-settings-save'] = true;

		$WebToLeadAddon = new KWSGFWebToLeadAddon;

	// Empty Org ID
		update_option($this->setting_slug, array('org_id' => NULL));

		// There's no Org ID, so it should be NULL
		$this->assertNull($WebToLeadAddon->is_valid_api());


	// Invalid Org ID
		update_option($this->setting_slug, array('org_id' => 'asdasdasd'));

		// There's an Org ID, but it's wrong.
		$this->assertFalse($WebToLeadAddon->is_valid_api());

	// Valid Partner Edition ID
		update_option( $this->setting_slug, array('org_id' => $this->partnerID) );
		$result = $WebToLeadAddon->is_valid_api();

		$this->assertTrue(is_array($result));
		$this->assertEquals($result['body'], '');
		$this->assertEquals($result['response']['code'], 200);
		$this->assertTrue((!isset($result['headers']['is-processed']) || $result['headers']['is-processed'] === 'true'));

	// Valid Enterprise Edition ID
		update_option( $this->setting_slug, array('org_id' => $this->enterpriseID) );
		$result = $WebToLeadAddon->is_valid_api();

		$this->assertTrue(is_array($result));
		$this->assertEquals($result['body'], '');
		$this->assertEquals($result['response']['code'], 200);
		$this->assertTrue((!isset($result['headers']['is-processed']) || $result['headers']['is-processed'] === 'true'));

	}

}