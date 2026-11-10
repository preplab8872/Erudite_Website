<?php

use App\Http\Controllers\SecurityCheckController;
use App\Http\Middleware\RiskCheckMiddleware;
use Illuminate\Support\Facades\Route;

// Security routes stay outside RiskCheckMiddleware.
Route::get('/security-check', [
    SecurityCheckController::class,
    'show',
])->name('security.check')->middleware('throttle:30,1');

Route::get('/security-check/context', [
    SecurityCheckController::class,
    'context',
])->name('security.context')->middleware('throttle:30,1');

Route::post('/security-check/consent', [
    SecurityCheckController::class,
    'saveConsent',
])->name('security.consent')->middleware('throttle:10,1');

Route::post('/security-check/evaluate', [
    SecurityCheckController::class,
    'evaluate',
])->name('security.evaluate')->middleware('throttle:12,1');


// Existing frontend directory.
$frontendDirectory = realpath(base_path('../erudite_homepage'));

$serveFrontendFile = static function (string $filename) use ($frontendDirectory) {
    if ($frontendDirectory === false) {
        abort(404);
    }

    $filePath = realpath(
        $frontendDirectory . DIRECTORY_SEPARATOR . $filename
    );

    $allowedPrefix = rtrim($frontendDirectory, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR;

    if (
        $filePath === false
        || !is_file($filePath)
        || !str_starts_with($filePath, $allowedPrefix)
    ) {
        abort(404);
    }

    $contentType = match (strtolower(pathinfo($filePath, PATHINFO_EXTENSION))) {
        'html' => 'text/html; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        default => 'application/octet-stream',
    };

    return response()->file($filePath, [
        'Content-Type' => $contentType,
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]);
};


// Protect actual website pages.
Route::middleware([RiskCheckMiddleware::class])->group(function () use (
    $frontendDirectory,
    $serveFrontendFile
): void {
    Route::get('/', static function () use ($serveFrontendFile) {
        return $serveFrontendFile('index.html');
    });

    if ($frontendDirectory !== false) {
        foreach (
            glob($frontendDirectory . DIRECTORY_SEPARATOR . '*.html') ?: []
            as $pagePath
        ) {
            $fileName = basename($pagePath);

            Route::get('/' . $fileName, static function () use (
                $serveFrontendFile,
                $fileName
            ) {
                return $serveFrontendFile($fileName);
            });
        }
    }

    Route::redirect('/login.html', '/booking.html', 301);
});


// CSS and JS remain publicly accessible.
foreach (['style.css', 'script.js'] as $assetName) {
    Route::get('/' . $assetName, static function () use (
        $serveFrontendFile,
        $assetName
    ) {
        return $serveFrontendFile($assetName);
    });
}