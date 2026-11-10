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

    /** One document, constrained by both section and primary key. */
    public function getFormByIdAndSection(string $section, int $formId): ?object
    {
        return DB::connection('crm')
            ->table('forms')
            ->select(['id', 'section', 'form_name', 'forms'])
            ->where('section', $section)
            ->where('id', $formId)
            ->first();
    }
}
