<?php

function fetchHTML($url) {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0'
        ]
    ];
    $context = stream_context_create($opts);
    return @file_get_contents($url, false, $context);
}

function extractDisenchantValues($html) {
    $doc = new DOMDocument();
    $prevLibxmlState = libxml_use_internal_errors(true);
    $doc->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($prevLibxmlState);

    $xpath = new DOMXPath($doc);
    $rows = $xpath->query("//div[@id='Disenchant']//tbody/tr");
    $values = [];

    foreach ($rows as $row) {
        $nameNode = $xpath->query("./td[1]", $row)->item(0);
        $valNode = $xpath->query("./td[2]", $row)->item(0);
        if (!$nameNode || !$valNode) {
            continue;
        }

        $name = trim(preg_replace('/\s+/', ' ', $nameNode->textContent));
        $dustVal = trim($valNode->textContent);
        if ($name === '' || $dustVal === '') {
            continue;
        }

        $values[$name] = number_format((float)$dustVal, 2, '.', '');
    }

    return $values;
}

function loadUniqueSlotsByName($uniquesJsonPath) {
    if (!file_exists($uniquesJsonPath)) {
        return [];
    }

    $uniques = json_decode(file_get_contents($uniquesJsonPath), true);
    if (!is_array($uniques)) {
        return [];
    }

    $slotsByName = [];
    foreach ($uniques as $unique) {
        if (!is_array($unique) || !isset($unique['name']) || !is_string($unique['name']) || $unique['name'] === '') {
            continue;
        }
        if (isset($slotsByName[$unique['name']])) {
            continue;
        }

        $slots = '1';
        if (isset($unique['slots']) && is_numeric($unique['slots'])) {
            $slots = (string)(int)$unique['slots'];
        }

        $slotsByName[$unique['name']] = $slots;
    }

    return $slotsByName;
}

function resolveSlotsByName($name, $slotsByName) {
    return $slotsByName[$name] ?? '1';
}

function updateDustJson($dustValues, $dustJsonPath, $slotsByName) {
    if (!file_exists($dustJsonPath)) {
        $current = [];
    } else {
        $current = json_decode(file_get_contents($dustJsonPath), true);
        if (!is_array($current)) {
            throw new RuntimeException("Invalid JSON in {$dustJsonPath}");
        }
    }

    $byName = [];
    foreach ($current as $i => $record) {
        if (isset($record['name']) && is_string($record['name'])) {
            $byName[$record['name']] = $i;
        }
    }

    $updatedCount = 0;
    $newCount = 0;
    $skippedCount = 0;

    foreach ($dustValues as $name => $dustVal) {
        if (array_key_exists($name, $byName)) {
            $current[$byName[$name]]['dustVal'] = $dustVal;
            if (!isset($current[$byName[$name]]['slots']) || $current[$byName[$name]]['slots'] === '') {
                $current[$byName[$name]]['slots'] = resolveSlotsByName($name, $slotsByName);
            }
            $updatedCount++;
            continue;
        }

        if (!isset($slotsByName[$name])) {
            $skippedCount++;
            continue;
        }

        $current[] = [
            'name' => $name,
            'dustVal' => $dustVal,
            'slots' => resolveSlotsByName($name, $slotsByName),
        ];
        $newCount++;
    }

    file_put_contents(
        $dustJsonPath,
        json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
    );

    return [
        'updated' => $updatedCount,
        'new' => $newCount,
        'skipped' => $skippedCount,
        'total' => count($current),
    ];
}

$html = fetchHTML('https://poedb.tw/us/Kingsmarch#Disenchant');
if ($html === false || $html === '') {
    throw new RuntimeException('Failed to download Kingsmarch page and no local Kingsmarch.html fallback found');
}

$dustValues = extractDisenchantValues($html);
if (empty($dustValues)) {
    throw new RuntimeException('No disenchant values found in downloaded HTML');
}

$slotsByName = loadUniqueSlotsByName('data/uniques.json');
$result = updateDustJson($dustValues, 'data/dust.json', $slotsByName);
echo "Updated: {$result['updated']}, New: {$result['new']}, Skipped: {$result['skipped']}, Total: {$result['total']}" . PHP_EOL;
