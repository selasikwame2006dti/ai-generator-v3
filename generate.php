<?php
// This is a PHP backend for the AI Image Generator, handling POST requests to Hugging Face API.

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Update to 'https://your-app-name.vercel.app' after deployment
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Read the raw POST data and decode JSON
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields: prompt, model, and secret_token
if (!isset($input['prompt']) || !isset($input['model']) || !isset($input['secret_token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing prompt, model, or secret_token']);
    exit;
}

// Security: Validate secret token
$expected_token = '8f4b3c9d-2e5a-4f7b-9c1d-3e6f8a2b7c4e';
if ($input['secret_token'] !== $expected_token) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid secret token']);
    exit;
}

// Security: Check referer to ensure request comes from your site
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
if (strpos($referer, 'localhost') === false && strpos($referer, 'your-app-name.vercel.app') === false) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid referer']);
    exit;
}

// Get Hugging Face API token from environment variable
$api_token = getenv('HF_TOKEN');
if (!$api_token) {
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: API token missing.']);
    exit;
}

try {
    // Construct Hugging Face API URL
    $api_url = 'https://api-inference.huggingface.co/models/' . urlencode($input['model']);

    // Prepare cURL request
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['inputs' => $input['prompt']]));

    // Execute request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Handle non-200 responses
    if ($http_code !== 200) {
        http_response_code($http_code);
        echo json_encode(['error' => 'Hugging Face API request failed', 'details' => $response]);
        exit;
    }

    // Convert binary response to base64
    $base64 = base64_encode($response);

    // Return JSON with image data URL
    echo json_encode(['image' => 'data:image/png;base64,' . $base64]);

} catch (Exception $e) {
    error_log('Backend server error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Backend server crashed', 'details' => $e->getMessage()]);
}

