<?php

namespace App\Module\FormManager;

use Illuminate\Support\Facades\DB;

class FormmanagerRepo
{
    /** Lightweight metadata query retained for callers that only need labels. */
    public function getFormMetadataBySection(string $section): array
    {
        return DB::connection('crm')
            ->table('forms')
            ->select(['id', 'form_name'])
            ->where('section', $section)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /** Full records retained for the existing booking-page output. */
    public function getFormsBySection(string $section): array
    {
        return DB::connection('crm')
            ->table('forms')
            ->select(['id', 'section', 'form_name', 'forms'])
            ->where('section', $section)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * Fetch every form managed by this module in one CRM query.
     * The rows are the source of truth used to reconcile all three storage folders.
     */
    public function getAllManagedForms(): array
    {
        return DB::connection('crm')
            ->table('forms')
            ->select(['id', 'section', 'form_name', 'forms'])
            ->whereIn('section', [
                'MBA_IN_INDIA',
                'UG_COLLEGE_TEST',
                'STUDY_ABROAD',
            ])
            ->orderBy('section')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * Lightweight records for homepage course cards.
     * The image BLOB and stored HTML are excluded; images use a separate endpoint.
     */
    public function getHomepageCourseMetadata(): array
    {
        return DB::connection('crm')
            ->table('forms')
            ->select([
                'id',
                'section',
                'form_name',
                'price',
                'title',
                'descriptin',
            ])
            ->selectRaw(
                'CASE WHEN image IS NOT NULL AND LENGTH(image) > 0 THEN 1 ELSE 0 END AS has_image'
            )
            ->whereIn('section', [
                'MBA_IN_INDIA',
                'UG_COLLEGE_TEST',
                'STUDY_ABROAD',
            ])
            ->orderBy('id')
            ->get()
            ->all();
    }

    /** Retrieve one form image without loading its HTML document. */
    public function getFormImageByIdAndSection(string $section, int $formId): ?string
    {
        $row = DB::connection('crm')
            ->table('forms')
            ->where('section', $section)
            ->where('id', $formId)
            ->value('image');

        if (!is_string($row) || $row === '') {
            return null;
        }

        return $row;
    }
}
