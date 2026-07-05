<?php

namespace App\Services\Imports;

class RouteSegmentParser
{
    public function parse($rawRoute, array $activeCodes)
    {
        $rawRoute = trim((string) $rawRoute);

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

        $routeWithoutParkingCodes = preg_replace('/[A-Z]{2,}-[A-Z0-9]+-P\d+/i', ' ', $rawRoute);
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

    private function containsInstructionText($rawRoute)
    {
        return strpos($rawRoute, 'ï¼ˆ') !== false
            || strpos($rawRoute, '(') !== false
            || stripos($rawRoute, 'sesuai') !== false
            || stripos($rawRoute, 'é¢†å¯¼') !== false;
    }
}
