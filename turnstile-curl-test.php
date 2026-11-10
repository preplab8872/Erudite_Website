<?php

$ch = curl_init(
    "https://challenges.cloudflare.com/turnstile/v0/api.js"
);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$response = curl_exec($ch);

$info = curl_getinfo($ch);

if ($response === false) {
    echo "cURL ERROR: " . curl_error($ch);
    curl_close($ch);
    exit;
}

$headerSize = $info['header_size'];
$headers = substr($response, 0, $headerSize);

echo "HTTP STATUS: " . $info['http_code'] . PHP_EOL;
echo "EFFECTIVE URL: " . $info['url'] . PHP_EOL;
echo "REDIRECT URL: " . $info['redirect_url'] . PHP_EOL;
echo "SSL VERIFY RESULT: " . $info['ssl_verify_result'] . PHP_EOL;
echo "RESPONSE HEADERS:" . PHP_EOL;
echo $headers;

curl_close($ch);