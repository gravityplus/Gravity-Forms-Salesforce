<?php

ob_start();

//change this to your path
$path = '/Users/zackkatz/Sites/wordpress-develop/tests/phpunit/includes/bootstrap.php';

if (file_exists($path)) {
    $GLOBALS['wp_tests_options'] = array(
        'active_plugins' => array(
        	'gravityforms/gravityforms.php',
        	'gravityformslogging/logging.php',
        	'gravity-forms-salesforce/web-to-lead.php',
        	'gravity-forms-salesforce/salesforce.php',
        	'gravity-forms-salesforce/salesforce-api.php',
        )
    );
    require_once $path;
} else {
    exit("Couldn't find wordpress-tests/bootstrap.php\n");
}