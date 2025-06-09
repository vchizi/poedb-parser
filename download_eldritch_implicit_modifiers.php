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
foreach ($sources as $source => $url) {
    echo "Processing $source from $url\n";

//    $html = file_get_contents($url);
    $html = file_get_contents($divClass[$source] . ".html");
//    file_put_contents($divClass[$source] . ".html", $html);
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
            if ($span->getAttribute('class') === 'implicitMod') {
                $implicitText = trim($span->textContent);
                break;
            }
        }

        // Get values from third <td>, exploded by <br>
        $thirdTd = $tds->item(2);
        $thirdTdHtml = '';
        foreach ($thirdTd->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE || $node->nodeName === 'br') {
                $thirdTdHtml .= $dom->saveHTML($node);
            }
        }

        $tags = array_filter(array_map('trim', explode('<br>', $thirdTdHtml)));

        [$tier, $ignore] = explode(' ', $tags[0]);
        [$base, $chance] = explode(' ', $tags[1]);

        $implicitMods[$source][] = [
            "Level" => (int)$itemLevel,
            "Mod" => $value = str_replace("\u{2013}", '-', $implicitText),
            "Base" => $base,
            "Tier" => (int)(preg_match('/no_tier_(\d+)_/', $tier, $m) ? $m[1] : -1),
        ];
    }
}

echo "Downloaded \n";
file_put_contents('eldritch_implicit.json', json_encode($implicitMods));
