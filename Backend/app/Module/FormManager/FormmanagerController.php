<?php

namespace App\Module\FormManager;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class FormmanagerController
{
    public function __construct(private FormmanagerServices $service)
    {
    }

    /**
     * Legacy booking endpoint.
     * Keeps returning the existing HTML fragment contract consumed by booking.js.
     */
    public function getForms(string $section): Response
    {
        $this->ensureAllowedSection($section);

        return response(
            $this->service->renderForms($section),
            200,
            [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * Homepage dropdown endpoint: returns only IDs and labels,
     * not the potentially large HTML documents.
     */
    public function getFormList(string $section): JsonResponse
    {
        $this->ensureAllowedSection($section);

        return response()->json(
            $this->service->getFormMetadataBySection($section),
            200,
            [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /** Return the complete stored HTML document without wrappers or JSON encoding. */
    public function getFormDocument(string $section, int $formId): Response
    {
        $this->ensureAllowedSection($section);

        $htmlDocument = $this->service->getFormDocument($section, $formId);

        abort_if(
            $htmlDocument === null || trim($htmlDocument) === '',
            404,
            'Form not found.'
        );

        return response(
            $htmlDocument,
            200,
            [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
                'Content-Security-Policy' => "frame-ancestors 'self'",
            ]
        );
    }

    private function ensureAllowedSection(string $section): void
    {
        abort_unless(
            in_array($section, [
                'MBA_IN_INDIA',
                'UG_COLLEGE_TEST',
                'STUDY_ABROAD',
            ], true),
            404
        );
    }
}
