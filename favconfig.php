<?php
/**
 * Simple Bookmarklet Web-Link-Tracker
 * Script to store URLs submitted via bookmarklet
 *
 * @copyright (c) 2023 ot2i7ba
 * https://github.com/ot2i7ba/
 * @license MIT License
 */

// favconfig.php
return [
    // Secret Values
    'secrets' => [
        'SECRET_VALUE' => '<YOUR_SECRET_VALUE>',
    ],

    // Limits and Settings
    'limits' => [
        'MAX_LINKS_PER_PAGE' => 25,
        'MAX_TITLE_LENGTH' => 200,
        'MAX_DAYS_TO_KEEP' => 365,
        'DAYS_BEFORE_DELETION' => 7,
        'MAX_REQUESTS_PER_MINUTE' => 50,
    ],

    // Email Configuration
    'email' => [
        'EMAIL_ADDRESS' => '<YOUR_EMAIL>',
        'FROM_ADDRESS' => '<YOUR_EMAIL>',
    ],

    // File Paths
    'paths' => [
        'favorites' => 'favorites.json',
        'lock' => 'favorites.lock',
        'intruder' => 'intruder.json',
        'blacklist' => 'blacklist.txt',
    ],
];
