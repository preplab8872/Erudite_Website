<?php

$ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => 'secret=&response=',
    CURLOPT_TIMEOUT => 15,
]);

$response = curl_exec($ch);

echo "cURL error number: " . curl_errno($ch) . PHP_EOL;
echo "cURL error: " . curl_error($ch) . PHP_EOL;
echo "HTTP status: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . PHP_EOL;

curl_close($ch);
