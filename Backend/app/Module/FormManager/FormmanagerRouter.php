<?php

namespace App\Module\FormManager;

use App\Http\Middleware\RiskCheckMiddleware;
use Illuminate\Support\Facades\Route;

class FormmanagerRouter
{
    public static function register(): void
    {
        Route::middleware([
            'web',
            RiskCheckMiddleware::class,
        ])->group(function (): void {

            // Homepage cards: one lightweight metadata response for all sections.
            Route::get(
                '/forms/homepage',
                [FormmanagerController::class, 'getHomepage']
            )->name('forms.homepage');

            // Existing booking contract: HTML fragment response.
            Route::get(
                '/forms/{section}',
                [FormmanagerController::class, 'getForms']
            )->whereIn('section', [
                'MBA_IN_INDIA',
                'UG_COLLEGE_TEST',
                'STUDY_ABROAD',
            ]);

            // Existing homepage dropdown contract: lightweight JSON metadata.
            Route::get(
                '/forms/{section}/list',
                [FormmanagerController::class, 'getFormList']
            )->whereIn('section', [
                'MBA_IN_INDIA',
                'UG_COLLEGE_TEST',
                'STUDY_ABROAD',
            ]);

            // Existing homepage document contract: original HTML from forms.forms.
            Route::get(
                '/forms/{section}/{formId}/document',
                [FormmanagerController::class, 'getFormDocument']
            )->whereNumber('formId')
                ->whereIn('section', [
                    'MBA_IN_INDIA',
                    'UG_COLLEGE_TEST',
                    'STUDY_ABROAD',
                ]);

            // Serve the image BLOB for one form; the named route builds image_url.
            Route::get(
                '/forms/{section}/{formId}/image',
                [FormmanagerController::class, 'getFormImage']
            )->whereNumber('formId')
                ->whereIn('section', [
                    'MBA_IN_INDIA',
                    'UG_COLLEGE_TEST',
                    'STUDY_ABROAD',
                ])
                ->name('forms.image');
        });
    }
}
