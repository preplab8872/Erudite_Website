<?php

namespace App\Module\ResourceManager;

use App\Http\Middleware\RiskCheckMiddleware;
use Illuminate\Support\Facades\Route;

class ResourceManagerRouter
{
    public static function register(): void
    {
        Route::middleware([
            'web',
            RiskCheckMiddleware::class,
            'throttle:60,1',
        ])->group(function (): void {

            // Shared catalogue: section names are discovered from both tables.
            Route::get(
                '/resources/sections',
                [ResourceManagerController::class, 'getSections']
            )->name('resources.sections.index');

            // Fixed JSON contract backed only by video_resources.
            Route::get(
                '/resources/video-resources/{section}',
                [ResourceManagerController::class, 'getVideoResourcesBySection']
            )->name('resources.video.index');

            // Fixed JSON contract backed only by document_resources.
            Route::get(
                '/resources/document-resources/{section}',
                [ResourceManagerController::class, 'getDocumentResourcesBySection']
            )->name('resources.document.index');

            // Binary download backed only by document_resources.
            Route::get(
                '/resources/document-resources/{section}/{documentId}/download',
                [ResourceManagerController::class, 'downloadDocument']
            )
                ->whereNumber('documentId')
                ->name('resources.document.download');
        });
    }
}
