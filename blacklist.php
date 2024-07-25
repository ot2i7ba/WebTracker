<?php
/**
 * Simple Bookmarklet Web-Link-Tracker
 * Script to store URLs submitted via bookmarklet
 *
 * @copyright (c) 2023 ot2i7ba
 * https://github.com/ot2i7ba/
 * @license MIT License
 */

// Ensure this file cannot be accessed directly
if (!defined('IN_APP')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not permitted');
}

// Return an array of blacklisted domains
return [
    'google.com',
    'google.de',
];
