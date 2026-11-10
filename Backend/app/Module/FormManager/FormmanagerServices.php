<?php

namespace App\Module\FormManager;

class FormmanagerServices
{
    public function __construct(
        private FormmanagerRepo $repo,
        private FormmanagerStorageService $storage
    ) {
    }

    /**
     * Synchronise all generated section files with the CRM table, then return
     * the metadata needed by the three homepage dropdowns.
     */
    public function getFormMetadataBySection(string $section): array
    {
        $forms = $this->repo->getAllManagedForms();

        // Every list request reconciles all three section folders, not just
        // the section currently being opened.
        $this->storage->synchronizeAll($forms);

        $metadata = [];

        foreach ($forms as $form) {
            if ((string) $form->section !== $section) {
                continue;
            }

            $metadata[] = [
                'id' => (int) $form->id,
                'form_name' => (string) $form->form_name,
            ];
        }

        return $metadata;
    }

    /**
     * Original booking-page output format. Keep its existing HTML fragment
     * contract separate from the homepage full-document viewer.
     */
    public function renderForms(string $section): string
    {
        $forms = $this->repo->getAllManagedForms();
        $this->storage->synchronizeAll($forms);
        $html = '';

        foreach ($forms as $form) {
            if ((string) $form->section !== $section) {
                continue;
            }

            $html .= '<article class="booking-form-entry" data-form-id="' . (int) $form->id . '">';
            $html .= '<h3>' . e((string) $form->form_name) . '</h3>';
            $html .= '<div class="booking-form-content">' . (string) $form->forms . '</div>';
            $html .= '</article>';
        }

        return $html;
    }

    /**
     * Reconcile storage with the current CRM snapshot, then read the generated
     * physical file for the requested form. CRM remains read-only.
     */
    public function getFormDocument(string $section, int $formId): ?string
    {
        $forms = $this->repo->getAllManagedForms();
        $this->storage->synchronizeAll($forms);

        foreach ($forms as $form) {
            if (
                (string) $form->section === $section &&
                (int) $form->id === $formId
            ) {
                return $this->storage->getHtmlDocument(
                    $section,
                    (string) $form->form_name
                );
            }
        }

        return null;
    }
}
