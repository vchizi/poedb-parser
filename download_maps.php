<?php

declare(strict_types=1);

function fetchHtml(string $url): string|false
{
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0',
                'Accept: text/html',
            ]),
            'timeout' => 30,
        ],
    ];

    return @file_get_contents($url, false, stream_context_create($opts));
}

function normalizeText(string $text): string
{
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function extractTier(string $text): ?int
{
    if (preg_match('/\bT\s*([1-9]|1[0-6])\b/i', $text, $m)) {
        return (int)$m[1];
    }

    if (preg_match('/\bTier\s*([1-9]|1[0-6])\b/i', $text, $m)) {
        return (int)$m[1];
    }

    if (preg_match('/^\s*([1-9]|1[0-6])\s*$/', $text, $m)) {
        return (int)$m[1];
    }

    return null;
}

function extractMapNamesFromNode(DOMNode $node, DOMXPath $xpath): array
{
    $names = [];

    foreach ($xpath->query('.//a', $node) as $a) {
        $name = normalizeText($a->textContent ?? '');
        if ($name !== '' && preg_match('/\bMap$/', $name)) {
            $names[] = $name;
        }
    }

    if (empty($names)) {
        $text = normalizeText($node->textContent ?? '');
        if ($text !== '' && preg_match_all('/([A-Z][A-Za-z\'\-\s]+?\bMap)\b/u', $text, $m)) {
            foreach ($m[1] as $name) {
                $names[] = normalizeText($name);
            }
        }
    }

    return array_values(array_unique($names));
}

function appendUnique(array &$target, string $value): void
{
    static $ignoredMaps = [
        'Chambers of Impurity Map' => true,
        'Courtyard of Wasting Map' => true,
        'Forge of the Phoenix Map' => true,
        'Lair of the Hydra Map' => true,
        'Maze of the Minotaur Map' => true,
        'Pit of the Chimera Map' => true,
        'Theatre of Lies Map' => true,
        'Vaal Temple Map' => true,
    ];

    if (isset($ignoredMaps[$value])) {
        return;
    }

    if (!in_array($value, $target, true)) {
        $target[] = $value;
    }
}

function parseMapsByTier(string $html): array
{
    $mapsByTier = [];
    for ($i = 1; $i <= 16; $i++) {
        $mapsByTier[$i] = [];
    }

    $dom = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $xpath = new DOMXPath($dom);
    $table = $xpath->query('//div[@id="MapsList"]//table')->item(0);
    if (!$table instanceof DOMElement) {
        return $mapsByTier;
    }

    foreach ($xpath->query('.//tbody/tr', $table) as $tr) {
        if (!$tr instanceof DOMElement) {
            continue;
        }

        // Skip unique maps rows.
        $rowClass = ' ' . normalizeText($tr->getAttribute('class')) . ' ';
        if (strpos($rowClass, ' UniqueItems ') !== false) {
            continue;
        }
        if ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " UniqueItems ")]', $tr)->length > 0) {
            continue;
        }

        $cells = $xpath->query('./td', $tr);
        if ($cells->length < 5) {
            continue;
        }

        $nameCell = $cells->item(3);
        $tierCell = $cells->item(4);
        if (!$nameCell instanceof DOMNode || !$tierCell instanceof DOMNode) {
            continue;
        }

        $nameAnchor = $xpath->query('.//a', $nameCell)->item(0);
        $name = $nameAnchor instanceof DOMNode
            ? normalizeText($nameAnchor->textContent ?? '')
            : normalizeText($nameCell->textContent ?? '');
        if ($name === '') {
            continue;
        }

        if (!preg_match('/\b([1-9]|1[0-6])\b/', normalizeText($tierCell->textContent ?? ''), $m)) {
            continue;
        }

        $tier = (int)$m[1];
        appendUnique($mapsByTier[$tier], $name);
    }

    return $mapsByTier;
}

function formatAsJson(array $mapsByTier): string
{
    $payload = [];
    for ($tier = 1; $tier <= 16; $tier++) {
        $maps = array_values($mapsByTier[$tier] ?? []);
        sort($maps, SORT_NATURAL | SORT_FLAG_CASE);
        $payload['T' . $tier] = $maps;
    }

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        throw new RuntimeException('Failed to encode maps JSON');
    }

    return $json . PHP_EOL;
}

function parseMapsByTierFromListFile(string $path): array
{
    $mapsByTier = [];
    for ($i = 1; $i <= 16; $i++) {
        $mapsByTier[$i] = [];
    }

    if (!file_exists($path)) {
        return $mapsByTier;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $mapsByTier;
    }

    foreach ($lines as $line) {
        if (!preg_match('/^\s*(\d{1,2})\s*:\s*(.+?)\s*$/', $line, $m)) {
            continue;
        }
        $tier = (int)$m[1];
        $name = normalizeText($m[2]);
        if ($tier < 1 || $tier > 16 || $name === '') {
            continue;
        }
        appendUnique($mapsByTier[$tier], $name);
    }

    return $mapsByTier;
}

$url = 'https://poedb.tw/us/Maps#MapsList';
$outputPath = 'data/maps_by_tier.json';

$html = fetchHtml($url);
if (!is_string($html) || $html === '') {
    throw new RuntimeException("Failed to download map page from {$url}");
}

$mapsByTier = parseMapsByTier($html);

$nonEmptyTiers = 0;
foreach ($mapsByTier as $maps) {
    if (!empty($maps)) {
        $nonEmptyTiers++;
    }
}

if ($nonEmptyTiers < 16) {
    throw new RuntimeException("Parsed only {$nonEmptyTiers}/16 map tiers");
}

if (!is_dir('data')) {
    mkdir('data', 0777, true);
}

file_put_contents($outputPath, formatAsJson($mapsByTier));
echo "Saved {$outputPath}" . PHP_EOL;
