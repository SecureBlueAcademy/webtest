<?php
$cfg = require __DIR__ . '/config.php';
date_default_timezone_set($cfg['timezone']);

function loadIndicators(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $rows = [];
    if (($handle = fopen($path, 'r')) !== false) {
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return [];
        }
        while (($data = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = $data[$i] ?? '';
            }
            $rows[] = $row;
        }
        fclose($handle);
    }
    return $rows;
}

function getCategories(): array
{
    return [
        'Hashes' => ['md5', 'sha1', 'sha256', 'hash'],
        'Domains' => ['domain'],
        'URLs' => ['url', 'uri'],
        'IP Addresses' => ['ip', 'ipv4', 'ipv6'],
        'Emails' => ['email'],
        'File Names' => ['filename', 'file name'],
        'Registry Keys' => ['registry', 'regkey'],
        'Behavior Signatures' => ['behavior', 'signature']
    ];
}

function mapCategory(string $indicatorType): string
{
    $type = strtolower(trim($indicatorType));
    foreach (getCategories() as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($type, $keyword) !== false) {
                return $category;
            }
        }
    }
    return 'Other';
}

function summarizeIndicators(array $indicators): array
{
    $summary = [
        'total' => count($indicators),
        'byType' => [],
        'byFamily' => [],
        'byTags' => [],
        'confidence' => [],
        'recent' => []
    ];

    foreach ($indicators as $ioc) {
        $type = $ioc['Indicator Type'] ?? 'Unknown';
        $family = $ioc['Threat Family'] ?? 'Unassigned';
        $conf = strtolower($ioc['Confidence'] ?? '');
        $tags = array_filter(array_map('trim', explode(',', $ioc['Tags'] ?? '')));
        $lastSeen = $ioc['Last Seen'] ?? '';

        $summary['byType'][$type] = ($summary['byType'][$type] ?? 0) + 1;
        $summary['byFamily'][$family] = ($summary['byFamily'][$family] ?? 0) + 1;
        $summary['confidence'][$conf] = ($summary['confidence'][$conf] ?? 0) + 1;

        foreach ($tags as $tag) {
            $summary['byTags'][$tag] = ($summary['byTags'][$tag] ?? 0) + 1;
        }

        if (!empty($lastSeen)) {
            $summary['recent'][] = $ioc;
        }
    }

    usort($summary['recent'], function ($a, $b) {
        return strtotime($b['Last Seen'] ?? '') <=> strtotime($a['Last Seen'] ?? '');
    });
    $summary['recent'] = array_slice($summary['recent'], 0, 5);

    arsort($summary['byType']);
    arsort($summary['byFamily']);
    arsort($summary['byTags']);
    arsort($summary['confidence']);

    return $summary;
}

function filterIndicators(array $indicators, array $filters): array
{
    return array_values(array_filter($indicators, function ($ioc) use ($filters) {
        $search = strtolower($filters['q'] ?? '');
        $typeFilter = strtolower($filters['type'] ?? '');
        $tagFilter = strtolower($filters['tag'] ?? '');
        $categoryFilter = $filters['category'] ?? '';
        $statusFilter = strtolower($filters['status'] ?? '');

        if ($search) {
            $haystack = strtolower(implode(' ', $ioc));
            if (strpos($haystack, $search) === false) {
                return false;
            }
        }
        if ($typeFilter && strtolower($ioc['Indicator Type'] ?? '') !== $typeFilter) {
            return false;
        }
        if ($tagFilter) {
            $tags = array_map('trim', explode(',', strtolower($ioc['Tags'] ?? '')));
            if (!in_array($tagFilter, $tags, true)) {
                return false;
            }
        }
        if ($statusFilter && strtolower($ioc['Status'] ?? '') !== $statusFilter) {
            return false;
        }
        if ($categoryFilter) {
            $category = mapCategory($ioc['Indicator Type'] ?? '');
            if ($category !== $categoryFilter) {
                return false;
            }
        }
        return true;
    }));
}

function uniqueValues(array $indicators, string $key): array
{
    $values = [];
    foreach ($indicators as $ioc) {
        if (!empty($ioc[$key])) {
            $values[] = $ioc[$key];
        }
    }
    return array_values(array_unique($values));
}
