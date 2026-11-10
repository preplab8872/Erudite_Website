<?php

namespace App\Module\FormManager;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class FormmanagerStorageService
{
    private const SECTIONS = [
        'MBA_IN_INDIA',
        'UG_COLLEGE_TEST',
        'STUDY_ABROAD',
    ];

    private FilesystemAdapter $disk;

    public function __construct()
    {
        // Private, application-managed storage. The physical root is:
        // storage/app/private/form-pages
        $this->disk = Storage::build([
            'driver' => 'local',
            'root' => storage_path('app/private/form-pages'),
            'visibility' => 'private',
            'throw' => true,
        ]);
    }

    /**
     * Reconcile all three section folders against one CRM snapshot.
     *
     * - Missing physical files are created.
     * - Changed LONGTEXT content overwrites the existing generated file.
     * - Files whose records no longer exist in CRM are deleted.
     * - The CRM database is never modified.
     *
     * Cleanup is deliberately performed only after all current rows have been
     * validated and written, so a bad row cannot trigger a partial purge.
     */
    public function synchronizeAll(array $databaseForms): void
    {
        $expectedFiles = array_fill_keys(self::SECTIONS, []);
        $seenNames = array_fill_keys(self::SECTIONS, []);

        // Ensure all three permanent section folders exist, even when empty.
        foreach (self::SECTIONS as $section) {
            $this->disk->makeDirectory($section);
        }

        // First materialise every row from the CRM snapshot.
        foreach ($databaseForms as $form) {
            $section = (string) ($form->section ?? '');
            $formName = (string) ($form->form_name ?? '');
            $html = (string) ($form->forms ?? '');

            $this->assertAllowedSection($section);
            $fileName = $this->makeFileName($formName);

            if (trim($html) === '') {
                throw new RuntimeException(
                    "Form record {$form->id} ({$formName}) has an empty HTML document."
                );
            }

            // Windows filesystems are commonly case-insensitive. Detect names
            // that would collide on a Windows development machine or Hostinger.
            $nameKey = mb_strtolower($fileName, 'UTF-8');
            if (isset($seenNames[$section][$nameKey])) {
                throw new RuntimeException(
                    "Duplicate form_name detected in {$section}: {$formName}."
                );
            }

            $seenNames[$section][$nameKey] = true;
            $relativePath = $section . '/' . $fileName;

            $this->writeIfChanged($relativePath, $html);
            $expectedFiles[$section][$nameKey] = true;
        }

        // Only after the full current CRM snapshot has been materialised,
        // remove stale generated HTML files that no longer have a DB row.
        foreach (self::SECTIONS as $section) {
            foreach ($this->disk->files($section) as $storedPath) {
                if (strtolower(pathinfo($storedPath, PATHINFO_EXTENSION)) !== 'html') {
                    continue;
                }

                $storedName = mb_strtolower(basename($storedPath), 'UTF-8');

                if (!isset($expectedFiles[$section][$storedName])) {
                    $this->disk->delete($storedPath);
                }
            }
        }
    }

    /** Read one generated HTML document after section reconciliation. */
    public function getHtmlDocument(string $section, string $formName): ?string
    {
        $this->assertAllowedSection($section);
        $relativePath = $section . '/' . $this->makeFileName($formName);

        if (!$this->disk->exists($relativePath)) {
            return null;
        }

        return $this->disk->get($relativePath);
    }

    /**
     * Store/rebuild one HTML file. synchronizeAll() uses this method whenever
     * a source document is new or its database content has changed.
     */
    public function storeHtmlDocument(
        string $section,
        string $formName,
        string $html
    ): string {
        $this->assertAllowedSection($section);
        $fileName = $this->makeFileName($formName);

        if (trim($html) === '') {
            throw new InvalidArgumentException('The HTML document cannot be empty.');
        }

        $this->disk->makeDirectory($section);
        $relativePath = $section . '/' . $fileName;
        $this->writeIfChanged($relativePath, $html);

        return $relativePath;
    }

    /** Delete one generated file. Does not modify its CRM database row. */
    public function deleteHtmlDocument(string $section, string $formName): bool
    {
        $this->assertAllowedSection($section);
        $relativePath = $section . '/' . $this->makeFileName($formName);

        if (!$this->disk->exists($relativePath)) {
            return false;
        }

        return $this->disk->delete($relativePath);
    }

    private function writeIfChanged(string $relativePath, string $html): void
    {
        if ($this->disk->exists($relativePath)) {
            $existingHtml = $this->disk->get($relativePath);

            $existingHash = hash('sha256', $existingHtml);
            $incomingHash = hash('sha256', $html);

            if (hash_equals($existingHash, $incomingHash)) {
                return;
            }
        }

        // put() replaces an existing file, so the same form_name automatically
        // receives the new HTML when the external app changes its DB content.
        $written = $this->disk->put($relativePath, $html);

        if ($written === false) {
            throw new RuntimeException(
                "Unable to write generated form document: {$relativePath}"
            );
        }
    }

    private function assertAllowedSection(string $section): void
    {
        if (!in_array($section, self::SECTIONS, true)) {
            throw new InvalidArgumentException('Invalid form section.');
        }
    }

    /**
     * form_name is used as the filename, with an .html extension added when
     * it is not already present. Path separators and reserved filename chars
     * are rejected to prevent traversal outside the section directory.
     */
    private function makeFileName(string $formName): string
    {
        $formName = trim($formName);

        if (
            $formName === '' ||
            $formName === '.' ||
            $formName === '..' ||
            str_contains($formName, '/') ||
            str_contains($formName, '\\') ||
            preg_match('/[<>:"|?*\x00-\x1F]/u', $formName) !== 0 ||
            str_ends_with($formName, '.') ||
            str_ends_with($formName, ' ')
        ) {
            throw new InvalidArgumentException(
                'form_name contains characters that cannot be used safely as a filename.'
            );
        }

        if (strlen($formName) > 180) {
            throw new InvalidArgumentException('form_name is too long for a filename.');
        }

        if (preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\..*)?$/i', $formName) === 1) {
            throw new InvalidArgumentException('form_name is a reserved filename.');
        }

        return preg_match('/\.html$/i', $formName) === 1
            ? $formName
            : $formName . '.html';
    }
}
