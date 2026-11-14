<?php

namespace App\Module\ResourceManager;

use Illuminate\Support\Facades\DB;

class ResourceManagerRepo
{
    /**
     * Discover sections from either resource table.
     * A section may exist with videos, documents, or both.
     */
    public function getSections(): array
    {
        $connection = DB::connection('crm');

        $videoSections = $connection
            ->table('video_resources')
            ->select(['section', 'section_description'])
            ->whereNotNull('section')
            ->whereRaw("TRIM(section) <> ''")
            ->get();

        $documentSections = $connection
            ->table('document_resources')
            ->select(['section', 'section_description'])
            ->whereNotNull('section')
            ->whereRaw("TRIM(section) <> ''")
            ->get();

        $sections = [];

        foreach ([$videoSections, $documentSections] as $records) {
            foreach ($records as $record) {
                $name = trim((string) $record->section);
                if ($name === '') {
                    continue;
                }

                if (!isset($sections[$name])) {
                    $sections[$name] = [
                        'section' => $name,
                        'section_description' => trim((string) $record->section_description),
                    ];
                    continue;
                }

                // Keep a non-empty description if another row has one.
                if (
                    $sections[$name]['section_description'] === '' &&
                    trim((string) $record->section_description) !== ''
                ) {
                    $sections[$name]['section_description'] =
                        trim((string) $record->section_description);
                }
            }
        }

        uasort($sections, static function (array $left, array $right): int {
            return strnatcasecmp($left['section'], $right['section']);
        });

        return array_values($sections);
    }

    /** Retrieve actual video rows from video_resources only. */
    public function getVideoResourcesBySection(string $section): array
    {
        return DB::connection('crm')
            ->table('video_resources')
            ->select([
                'id',
                'title',
                'title_description',
                'section',
                'section_description',
                'youtube_link',
                'created_at',
                'updated_at',
            ])
            ->where('section', $section)
            ->whereNotNull('youtube_link')
            ->whereRaw("TRIM(youtube_link) <> ''")
            ->where('title', '<>', '__ERUDITE_EMPTY_RESOURCE_SECTION__')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /** Retrieve document metadata from document_resources only. BLOB is excluded. */
    public function getDocumentResourcesBySection(string $section): array
    {
        return DB::connection('crm')
            ->table('document_resources')
            ->select([
                'id',
                'title',
                'title_description',
                'section',
                'section_description',
                'created_at',
                'updated_at',
            ])
            ->selectRaw(
                'CASE WHEN document IS NOT NULL AND OCTET_LENGTH(document) > 0 THEN 1 ELSE 0 END AS has_document'
            )
            ->where('section', $section)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /** Retrieve one BLOB from document_resources only. */
    public function getDocumentByIdAndSection(string $section, int $documentId): ?object
    {
        $document = DB::connection('crm')
            ->table('document_resources')
            ->select(['id', 'title', 'document'])
            ->where('section', $section)
            ->where('id', $documentId)
            ->first();

        if (
            $document === null ||
            !is_string($document->document) ||
            $document->document === ''
        ) {
            return null;
        }

        return $document;
    }
}
