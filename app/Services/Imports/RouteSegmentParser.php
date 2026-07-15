<?php

namespace App\Services\Imports;

class RouteSegmentParser
{
    public function parse($rawRoute, array $activeCodes, array $parkingCodes = [])
    {
        $rawRoute = $this->normalizeSeparators($rawRoute);

        if ($rawRoute === '') {
            return [
                'codes' => [],
                'warnings' => ['Rute kendaraan kosong'],
            ];
        }

        $knownCodes = array_values(array_unique(array_map('strtoupper', $activeCodes)));
        usort($knownCodes, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        $routeWithoutParkingCodes = $this->removeParkingCodes($rawRoute, $parkingCodes);
        preg_match_all('/[A-Z]{1,3}\d{1,2}/i', $routeWithoutParkingCodes, $matches);
        $tokens = array_values(array_unique(array_map('strtoupper', $matches[0])));

        $codes = [];
        $warnings = [];

        foreach ($tokens as $token) {
            if (in_array($token, $knownCodes, true)) {
                $codes[] = $token;
            } elseif (!$this->looksLikeParkingCode($token)) {
                $warnings[] = 'Rute mengandung token tidak dikenal: ' . $token;
            }
        }

        if ($codes === []) {
            $warnings[] = 'Rute tidak mengandung kode segmen resmi';
        }

        $remainingText = $this->extractRemainingFreeText($routeWithoutParkingCodes, $knownCodes);
        if ($remainingText !== '') {
            $warnings[] = 'Rute mengandung teks bebas yang perlu review: ' . $remainingText;
        }

        if ($this->containsInstructionText($rawRoute)) {
            $warnings[] = 'Rute mengandung catatan teks yang perlu review';
        }

        return [
            'codes' => $codes,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function looksLikeParkingCode($token)
    {
        return preg_match('/^P\d+$/i', $token) === 1;
    }

    private function normalizeSeparators($rawRoute)
    {
        $rawRoute = trim((string) $rawRoute);

        return preg_replace('/\s+-\s+/', ' → ', $rawRoute);
    }

    private function removeParkingCodes($rawRoute, array $parkingCodes)
    {
        $route = $rawRoute;
        $parkingCodes = array_filter(array_map('trim', $parkingCodes));
        usort($parkingCodes, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        foreach ($parkingCodes as $parkingCode) {
            $route = preg_replace('/(?<![A-Z0-9-])' . preg_quote($parkingCode, '/') . '(?![A-Z0-9-])/i', ' ', $route);
        }

        return preg_replace('/\b[A-Z0-9]+(?:-[A-Z0-9]+){2,}\b/i', ' ', $route);
    }

    private function extractRemainingFreeText($rawRoute, array $knownCodes)
    {
        $remaining = $rawRoute;

        foreach ($knownCodes as $code) {
            $remaining = preg_replace('/' . preg_quote($code, '/') . '/i', ' ', $remaining);
        }

        $remaining = str_replace(['->', 'â†’', 'Ã¢â€ â€™'], ' ', $remaining);
        $remaining = preg_replace('/[A-Z]{1,3}\d{1,2}/i', ' ', $remaining);
        $remaining = preg_replace('/[\-\x{2192}\x{27A1}\x{2794}>\/,.;:_()\[\]{}]+/u', ' ', $remaining);
        $remaining = preg_replace('/\s+/u', ' ', $remaining);

        return trim($remaining);
    }

    private function containsInstructionText($rawRoute)
    {
        return strpos($rawRoute, 'Ã¯Â¼Ë†') !== false
            || strpos($rawRoute, '(') !== false
            || stripos($rawRoute, 'sesuai') !== false
            || stripos($rawRoute, 'Ã©Â¢â€ Ã¥Â¯Â¼') !== false;
    }
}
