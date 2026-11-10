<?php
namespace App\Http\Middleware\Services;
use Illuminate\Http\Request;

class DeviceFingerprintService
{
    /**
     * Collect the current device fingerprint.
     * User-Agent is obtained from the HTTP request.
     */
    public function collect(Request $request): array
    {
        $input = $request->input('device_fingerprint', []);

        if (!is_array($input)) {
            $input = [];
        }

        $fingerprint = [
            'user_agent' => $this->clean($request->userAgent(), 512),
            'canvas_hash' => $this->clean($input['canvas_hash'] ?? null, 128),
            'screen' => $this->clean($input['screen'] ?? null, 100),
            'timezone' => $this->clean($input['timezone'] ?? null, 100),
            'language' => $this->clean($input['language'] ?? null, 50),
        ];

        return array_filter(
            $fingerprint,
            fn ($value) => $value !== null
        );
    }

    /**
     * Check whether the current request contains enough fields to make a
     * meaningful comparison with the saved fingerprint. Normal protected-page
     * GET requests do not contain the JavaScript fingerprint POST fields, so
     * User-Agent alone must be treated as insufficient data, not a mismatch.
     */
    public function hasSufficientComparableData(
        array $previous,
        array $current
    ): bool {
        $weights = [
            'user_agent' => 30,
            'canvas_hash' => 35,
            'screen' => 15,
            'timezone' => 10,
            'language' => 10,
        ];

        $totalWeight = 0;

        foreach ($weights as $field => $weight) {
            $old = $previous[$field] ?? null;
            $new = $current[$field] ?? null;

            if (
                is_string($old) && $old !== ''
                && is_string($new) && $new !== ''
            ) {
                $totalWeight += $weight;
            }
        }

        return $totalWeight >= 50;
    }

    /**
     * Return fingerprint match points: 25, 15, 7 or 0.
     */
    public function calculateMatchScore(
        array $previous,
        array $current
    ): int {
        $weights = [
            'user_agent' => 30,
            'canvas_hash' => 35,
            'screen' => 15,
            'timezone' => 10,
            'language' => 10,
        ];

        $totalWeight = 0;
        $matchedWeight = 0;

        foreach ($weights as $field => $weight) {
            $old = $previous[$field] ?? null;
            $new = $current[$field] ?? null;

            if (!is_string($old) || !is_string($new) || $old === '' || $new === '') {
                continue;
            }

            $totalWeight += $weight;

            if (hash_equals($old, $new)) {
                $matchedWeight += $weight;
            }
        }

        // Insufficient comparable fingerprint data.
        if ($totalWeight < 50) {
            return 0;
        }

        $matchPercentage = ($matchedWeight / $totalWeight) * 100;

        if ($matchPercentage >= 85) {
            return 25;
        }

        if ($matchPercentage >= 50) {
            return 15;
        }

        if ($matchPercentage >= 30) {
            return 7;
        }

        return 0;
    }

    private function clean(mixed $value, int $maxLength): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return substr($value, 0, $maxLength);
    }
}
