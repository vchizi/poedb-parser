<?php

declare(strict_types=1);

$sources = [
    'SearingExarch' => 'https://poedb.tw/us/The_Searing_Exarch#TheSearingExarchImplicit',
    'EaterOfWorlds' => 'https://poedb.tw/us/The_Eater_of_Worlds#TheEaterofWorldsImplicit',
];

$divClass = [
    'SearingExarch' => 'TheSearingExarchImplicit',
    'EaterOfWorlds' => 'TheEaterofWorldsImplicit',
];

$implicitMods = [];

function parseTierBaseChance(DOMDocument $dom, DOMElement $cell): ?array
{
    $html = $dom->saveHTML($cell);
    if (!is_string($html) || $html === '') {
        return null;
    }

    // Parse sequences like: <i>no_tier_6_eldritch_implicit</i> 0
    if (!preg_match_all('/<i>\s*([^<]+?)\s*<\/i>\s*([0-9]+)/i', $html, $matches, PREG_SET_ORDER)) {
        return null;
    }

    $tier = -1;
    $base = 'default';
    $chance = 0;

    foreach ($matches as $m) {
        $key = trim($m[1]);
        $value = (int)$m[2];

        if ($tier === -1 && preg_match('/no_tier_(\d+)_/', $key, $tierMatch)) {
            $tier = (int)$tierMatch[1];
            continue;
        }

        if ($key === 'default') {
            continue;
        }

        // Use first concrete base bucket; this matches current poedb table shape.
        if ($base === 'default') {
            $base = $key;
            $chance = $value;
        }
    }

    return [
        'tier' => $tier,
        'base' => $base,
        'chance' => $chance,
    ];
}

foreach ($sources as $source => $url) {
    echo "Processing $source from $url\n";

    $html = file_get_contents($url);
    if ($html === false) {
        $localHtmlPath = $divClass[$source] . ".html";
        if (file_exists($localHtmlPath)) {
            $html = file_get_contents($localHtmlPath);
            echo "Using local fallback: {$localHtmlPath}\n";
        }
    }
    // file_put_contents($divClass[$source] . ".html", $html);
    if ($html === false) {
        echo "Failed to fetch $url\n";
        continue;
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $div = $xpath->query("//div[@id='$divClass[$source]']")->item(0);

    if (!$div) {
        echo "Div with id '$divClass[$source]' not found.\n";
        continue;
    }

    $table = $div->getElementsByTagName('table')->item(0);
    if (!$table) {
        echo "No <table> found inside div '$divClass[$source]'.\n";
        continue;
    }

    $rows = $table->getElementsByTagName('tr');
    foreach ($rows as $row) {
        $tds = $row->getElementsByTagName('td');
        if ($tds->length < 3) {
            continue; // Skip malformed rows
        }

        // Get item level from first <td>
        $itemLevel = trim($tds->item(0)->textContent);

        // Get the implicitMod text from second <td>
        $secondTd = $tds->item(1);
        $implicitModSpan = $secondTd->getElementsByTagName('span');
        $implicitText = '';
        foreach ($implicitModSpan as $span) {
            if (str_contains($span->getAttribute('class'), 'implicitMod')) {
                $implicitText = trim($span->textContent);
                break;
            }
        }
        if ($implicitText === '') {
            continue;
        }

        $thirdTd = $tds->item(2);
        if (!$thirdTd instanceof DOMElement) {
            continue;
        }

        $parsed = parseTierBaseChance($dom, $thirdTd);
        if ($parsed === null) {
            continue;
        }

        $implicitMods[$source][] = [
            "Level" => (int)$itemLevel,
            "Mod" => $value = str_replace("\u{2013}", '-', $implicitText),
            "Base" => $parsed['base'],
            "Tier" => $parsed['tier'],
            "Chance" => $parsed['chance'],
        ];
    }
}

echo "Downloaded \n";
file_put_contents('data/eldritch_implicit.json', json_encode($implicitMods, JSON_PRETTY_PRINT));
