<?php

namespace App\Module\ResourceManager;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResourceManagerController
{
    public function __construct(
        private ResourceManagerServices $service
    ) {
    }

    /** GET /api/resources/sections */
    public function getSections(): JsonResponse
    {
        $sections = $this->service->getSections();

        return response()->json([
            'success' => true,
            'table' => 'resource_sections',
            'source_tables' => ['video_resources', 'document_resources'],
            'count' => count($sections),
            'data' => $sections,
        ], 200);
    }

    /** GET /api/resources/video-resources/{section} */
    public function getVideoResourcesBySection(string $section): JsonResponse
    {
        $payload = $this->service->getVideoResourcesBySection($section);

        if ($payload === null) {
            $payload = [
                'success' => false,
                'table' => 'video_resources',
                'section' => trim($section),
                'section_description' => '',
                'count' => 0,
                'data' => [],
            ];

            return response()->json($payload, 404);
        }

        // Rebuild the fixed top-level schema deliberately, so no branch can
        // accidentally omit table/section/count/data for an empty result.
        return response()->json([
            'success' => (bool) ($payload['success'] ?? true),
            'table' => 'video_resources',
            'section' => (string) ($payload['section'] ?? trim($section)),
            'section_description' => (string) ($payload['section_description'] ?? ''),
            'count' => (int) ($payload['count'] ?? 0),
            'data' => is_array($payload['data'] ?? null) ? $payload['data'] : [],
        ], 200);
    }

    /** GET /api/resources/document-resources/{section} */
    public function getDocumentResourcesBySection(string $section): JsonResponse
    {
        $payload = $this->service->getDocumentResourcesBySection($section);

        if ($payload === null) {
            $payload = [
                'success' => false,
                'table' => 'document_resources',
                'section' => trim($section),
                'section_description' => '',
                'count' => 0,
                'data' => [],
            ];

            return response()->json($payload, 404);
        }

        return response()->json([
            'success' => (bool) ($payload['success'] ?? true),
            'table' => 'document_resources',
            'section' => (string) ($payload['section'] ?? trim($section)),
            'section_description' => (string) ($payload['section_description'] ?? ''),
            'count' => (int) ($payload['count'] ?? 0),
            'data' => is_array($payload['data'] ?? null) ? $payload['data'] : [],
        ], 200);
    }

    /** GET /api/resources/document-resources/{section}/{documentId}/download */
    public function downloadDocument(string $section, int $documentId): Response
    {
        $document = $this->service->getDocument($section, $documentId);

        if ($document === null) {
            return response()->json([
                'success' => false,
                'table' => 'document_resources',
                'section' => trim($section),
                'id' => $documentId,
                'message' => 'Document not found or its BLOB is empty.',
            ], 404);
        }

        $content = $document->document;
        $mimeType = 'application/octet-stream';
        $extension = 'bin';

        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo->buffer($content);

            $allowedTypes = [
                'application/pdf' => 'pdf',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.ms-excel' => 'xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'application/vnd.ms-powerpoint' => 'ppt',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
                'text/plain' => 'txt',
                'text/rtf' => 'rtf',
                'application/rtf' => 'rtf',
                'application/zip' => 'zip',
            ];

            if (isset($allowedTypes[$detectedMime])) {
                $mimeType = $detectedMime;
                $extension = $allowedTypes[$detectedMime];
            }
        }

        $filename = Str::slug((string) ($document->title ?: 'resource-document'));
        if ($filename === '') {
            $filename = 'resource-document';
        }
        $filename .= '.' . $extension;

        return response($content, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
