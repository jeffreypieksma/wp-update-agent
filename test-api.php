#!/usr/bin/env php
<?php
/**
 * WP Update Agent - CLI Test Script
 * 
 * This script allows you to test the REST API from the command line.
 * 
 * Usage:
 *   php test-api.php <site_url> <secret> <action> [params...]
 * 
 * Examples:
 *   php test-api.php https://mysite.local "my-secret-key" plugin_list
 *   php test-api.php https://mysite.local "my-secret-key" update_plugin slug=akismet
 *   php test-api.php https://mysite.local "my-secret-key" smtp_test to=test@example.com
 *   php test-api.php https://mysite.local "my-secret-key" install_plugin_slug slug=hello-dolly activate=true
 */

// Check arguments
if ($argc < 4) {
    echo "WP Update Agent - CLI Test Script\n";
    echo "==================================\n\n";
    echo "Usage: php test-api.php <site_url> <secret> <action> [params...]\n\n";
    echo "Examples:\n";
    echo "  php test-api.php https://mysite.local \"my-secret-key\" plugin_list\n";
    echo "  php test-api.php https://mysite.local \"my-secret-key\" update_plugin slug=akismet\n";
    echo "  php test-api.php https://mysite.local \"my-secret-key\" smtp_test to=test@example.com\n";
    echo "  php test-api.php https://mysite.local \"my-secret-key\" core_check\n";
    echo "  php test-api.php https://mysite.local \"my-secret-key\" system_status\n\n";
    echo "Available actions:\n";
    echo "  Plugin:  plugin_list, update_plugin, update_all_plugins, install_plugin_slug,\n";
    echo "           install_plugin_zip, activate_plugin, deactivate_plugin\n";
    echo "  Core:    core_check, core_update, language_update, system_status\n";
    echo "  SMTP:    smtp_test, smtp_info\n";
    exit(1);
}

$site_url = rtrim($argv[1], '/');
$secret = $argv[2];
$action = $argv[3];

// Parse additional parameters
$params = array('action' => $action);
for ($i = 4; $i < $argc; $i++) {
    if (strpos($argv[$i], '=') !== false) {
        list($key, $value) = explode('=', $argv[$i], 2);
        // Convert string booleans
        if ($value === 'true') $value = true;
        if ($value === 'false') $value = false;
        $params[$key] = $value;
    }
}

// Build request
$timestamp = time();
$body = json_encode($params);
$payload = $timestamp . '.' . $body;
$signature = hash_hmac('sha256', $payload, $secret);

$endpoint = $site_url . '/wp-json/agent/v1/execute';

echo "WP Update Agent - API Test\n";
echo "==========================\n";
echo "Endpoint: $endpoint\n";
echo "Action: $action\n";
echo "Timestamp: $timestamp\n";
echo "Body: $body\n";
echo "Signature: $signature\n";
echo "\n";

// Make request
$ch = curl_init();
curl_setopt_array($ch, array(
    CURLOPT_URL => $endpoint,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'X-Agent-Timestamp: ' . $timestamp,
        'X-Agent-Signature: ' . $signature,
    ),
    CURLOPT_SSL_VERIFYPEER => false, // For local development
    CURLOPT_TIMEOUT => 120,
));

echo "Sending request...\n\n";

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

if ($error) {
    echo "CURL Error: $error\n";
    exit(1);
}

echo "HTTP Status: $http_code\n";
echo "Response:\n";
echo "=========\n";

// Pretty print JSON
$json = json_decode($response);
if ($json !== null) {
    echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} else {
    echo $response;
}

echo "\n";

// Exit with error code if request failed
if ($http_code >= 400) {
    exit(1);
}
