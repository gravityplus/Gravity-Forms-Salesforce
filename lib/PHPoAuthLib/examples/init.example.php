<?php

/**
 * This file sets up the information needed to test the examples in different environments.
 *
 * PHP version 5.4
 *
 * @author     David Desberg <david@daviddesberg.com>
 * @author     Pieter Hordijk <info@pieterhordijk.com>
 * @copyright  Copyright (c) 2012 The authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 */

/**
 * @var array A list of all the credentials to be used by the different services in the examples
 */
$servicesCredentials = array(
    'amazon' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'bitbucket' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'bitly' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'box' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'dailymotion' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'dropbox' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'etsy' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'facebook' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'fitbit' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'flickr' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'foursquare' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'github' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'google' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'instagram' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'linkedin' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'mailchimp' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'microsoft' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'paypal' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'pocket' => array(
        'key'       => '',
    ),
    'reddit' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'runkeeper' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'scoopit' => array(
        'key'       => '',
        'secret'    => ''
    ),
    'soundcloud' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'tumblr' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'twitter' => array(
        'key'       => '',
        'secret'    => '',
    ),
    'yahoo' => array(
        'key'       => '',
        'secret'    => ''
    ),
    'yammer' => array(
        'key'       => '',
        'secret'    => ''
    ),
);

/** @var $serviceFactory \OAuth\ServiceFactory An OAuth service factory. */
$serviceFactory = new \OAuth\ServiceFactory();
