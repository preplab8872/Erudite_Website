<?php

namespace App\Module\ResourceManager;

class ResourceManagerServices
{
    public function __construct(
        private ResourceManagerRepo $repo
    ) {
    }

    /** Shared section catalogue sourced from the two separate tables. */
    public function getSections(): array
    {
        return $this->repo->getSections();
    }

    /**
     * Fixed JSON payload for video_resources.
     * Existing section + no matching video => count 0 and data [].
     * Unknown section => null; controller returns the same schema with success false.
     */
    public function getVideoResourcesBySection(string $section): ?array
    {
        $sectionInfo = $this->findSection($section);
        if ($sectionInfo === null) {
            return null;
        }

        $rows = $this->repo->getVideoResourcesBySection($sectionInfo['section']);
        $data = [];

        foreach ($rows as $row) {
            $data[] = [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'title_description' => (string) $row->title_description,
                'section' => (string) $row->section,
                'section_description' => (string) $row->section_description,
                'youtube_link' => (string) $row->youtube_link,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        return [
            'success' => true,
            'table' => 'video_resources',
            'section' => $sectionInfo['section'],
            'section_description' => $sectionInfo['section_description'],
            'count' => count($data),
            'data' => $data,
        ];
    }

    /**
     * Fixed JSON payload for document_resources.
     * Existing section + no matching document => count 0 and data [].
     * Metadata rows without a BLOB remain listed with has_document false and URL null.
     */
    public function getDocumentResourcesBySection(string $section): ?array
    {
        $sectionInfo = $this->findSection($section);
        if ($sectionInfo === null) {
            return null;
        }

        $rows = $this->repo->getDocumentResourcesBySection($sectionInfo['section']);
        $data = [];

        foreach ($rows as $row) {
            $hasDocument = (bool) $row->has_document;

            $data[] = [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'title_description' => (string) $row->title_description,
                'section' => (string) $row->section,
                'section_description' => (string) $row->section_description,
                'has_document' => $hasDocument,
                'download_url' => $hasDocument
                    ? route('resources.document.download', [
                        'section' => $sectionInfo['section'],
                        'documentId' => (int) $row->id,
                    ])
                    : null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        return [
            'success' => true,
            'table' => 'document_resources',
            'section' => $sectionInfo['section'],
            'section_description' => $sectionInfo['section_description'],
            'count' => count($data),
            'data' => $data,
        ];
    }

    /** Fetch an actual document BLOB for download. */
    public function getDocument(string $section, int $documentId): ?object
    {
        return $this->repo->getDocumentByIdAndSection($section, $documentId);
    }

    private function findSection(string $section): ?array
    {
        $requested = trim($section);

        foreach ($this->repo->getSections() as $record) {
            if (strcasecmp($record['section'], $requested) === 0) {
                return $record;
            }
        }

        return null;
    }
}
