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
     * Synchronise generated section files with the CRM table, then return
     * metadata needed by the three existing homepage navigation dropdowns.
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

    /**
     * Return all eligible homepage course cards in a single metadata query.
     * Records without a title, description, price, or stored image are omitted.
     */
    public function getHomepage(): array
    {
        $forms = $this->repo->getHomepageCourseMetadata();
        $tabs = [];

        foreach ($forms as $form) {
            $title = trim((string) ($form->title ?? ''));
            $description = trim((string) ($form->descriptin ?? ''));
            $hasImage = (int) ($form->has_image ?? 0) === 1;

            if (
                $title === '' ||
                $description === '' ||
                $form->price === null ||
                !$hasImage
            ) {
                continue;
            }

            $tabs[] = [
                'id' => (int) $form->id,
                'section' => (string) $form->section,
                'title' => $title,
                // The database column is historically spelled descriptin.
                'description' => $description,
                'price' => (int) $form->price,
                'image_url' => route('forms.image', [
                    'section' => (string) $form->section,
                    'formId' => (int) $form->id,
                ]),
            ];
        }

        return $tabs;
    }

    /** Retrieve an image BLOB for the dedicated image-response endpoint. */
    public function getFormImage(string $section, int $formId): ?string
    {
        return $this->repo->getFormImageByIdAndSection($section, $formId);
    }
}
