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

            // Existing booking contract: HTML fragment response.
            Route::get(
                '/forms/{section}',
                [FormmanagerController::class, 'getForms']
            )->whereIn('section', [
                'MBA_IN_INDIA',
                'UG_COLLEGE_TEST',
                'STUDY_ABROAD',
            ]);

            // Homepage dropdown contract: lightweight JSON metadata.
            Route::get(
                '/forms/{section}/list',
                [FormmanagerController::class, 'getFormList']
            )->whereIn('section', [
                'MBA_IN_INDIA',
                'UG_COLLEGE_TEST',
                'STUDY_ABROAD',
            ]);

            // Homepage document contract: original HTML from forms.forms.
            Route::get(
                '/forms/{section}/{formId}/document',
                [FormmanagerController::class, 'getFormDocument']
            )->whereNumber('formId')
                ->whereIn('section', [
                    'MBA_IN_INDIA',
                    'UG_COLLEGE_TEST',
                    'STUDY_ABROAD',
                ]);
        });
    }
}
