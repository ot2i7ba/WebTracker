<?php
/**
 * Simple Bookmarklet Web-Link-Tracker
 * Script to store URLs submitted via bookmarklet
 *
 * @copyright (c) 2023 ot2i7ba
 * https://github.com/ot2i7ba/
 * @license MIT License
 */

session_start();

// Set your secret value here
define('MAGIC_WORD', '<YOUR_SECRET_VALUE>');

// Set the base path here
define('BASE_PATH', '/base/path/here/favorites/');

// Set the rate limit count here
define('RATE_LIMIT_COUNT', 10);

// Allowed files
$allowedFiles = ['favorites.json', 'intruder.json', 'proxy.json'];

// Function to check if the requested filename is allowed
function isAllowedFilename($filename)
{
    global $allowedFiles;
    return in_array($filename, $allowedFiles);
}

// Function to check if the user has reached the rate limit
function isRateLimited()
{
    if (!isset($_SESSION['rate_limit_timestamp'])) {
        $_SESSION['rate_limit_timestamp'] = time();
        $_SESSION['rate_limit_count'] = 0;
    }

    $currentTime = time();
    $timeDiff = $currentTime - $_SESSION['rate_limit_timestamp'];

    // Reset the rate limit counter after 120 seconds
    if ($timeDiff > 120) {
        $_SESSION['rate_limit_timestamp'] = $currentTime;
        $_SESSION['rate_limit_count'] = 0;
    }

    // Check if the rate limit count has been reached
    if ($_SESSION['rate_limit_count'] >= RATE_LIMIT_COUNT) {
        return true;
    }

    $_SESSION['rate_limit_count']++;
    return false;
}

// Get and sanitize user input
$secret = filter_input(INPUT_GET, 'secret', FILTER_SANITIZE_STRING);
$file = filter_input(INPUT_GET, 'file', FILTER_SANITIZE_STRING);

// Function to log failed access attempts
function logFailedAttempt($secret, $file)
{
    $log_file = 'proxy.json';

    // Check if the log file exists, create it if not
    if (!file_exists($log_file)) {
        file_put_contents($log_file, json_encode([]));
        chmod($log_file, 0600);
    }

    $timestamp = date('Y-m-d H:i:s');
    $session_id = session_id();
    $remote_ip = $_SERVER['REMOTE_ADDR'];
    $log_entry = [
        'timestamp' => $timestamp,
        'session_id' => $session_id,
        'remote_ip' => $remote_ip,
        'secret' => $secret,
        'file' => $file
    ];

    // Read the existing log entries
    $log_entries = json_decode(file_get_contents($log_file), true);
    if (!is_array($log_entries)) {
        $log_entries = [];
    }

    // Append the new log entry
    $log_entries[] = $log_entry;

    // Write the updated log entries back to the file
    file_put_contents($log_file, json_encode($log_entries, JSON_PRETTY_PRINT));
}

// Function to handle errors and log attempts
function handleError($statusCode, $message, $secret = '', $file = '')
{
    logFailedAttempt($secret, $file);
    http_response_code($statusCode);
    echo $message;
    exit;
}

// Check if the request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    handleError(405, "Method Not Allowed", $secret, $file);
}

// Check if the rate limit has been reached
if (isRateLimited()) {
    handleError(429, "Rate limit exceeded. Please wait a minute before trying again.", $secret, $file);
}

// Check if the secret value is valid
if ($secret !== MAGIC_WORD) {
    handleError(403, "Access denied.", $secret, $file);
}

// Check if the requested file is allowed
if (!isAllowedFilename($file)) {
    handleError(403, "Access denied.", $secret, $file);
}

$filePath = realpath(BASE_PATH . basename($file));

// Check if the file exists, is readable, and is within the base path
if ($filePath === false || strpos($filePath, BASE_PATH) !== 0 || !is_readable($filePath)) {
    handleError(404, "File not found.", $secret, $file);
}

// If everything is valid, do not log the attempt
// Clear unnecessary headers and set headers for file download
header_remove('X-Powered-By');
header_remove('Server');
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Expires: 0');
header('Cache-Control: private, no-cache, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

// Clean output buffer and read the file
ob_end_clean();
readfile($filePath);
exit;
?>
