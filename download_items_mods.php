<?php
//console.log(JSON.stringify(items_backup.normal))
function fetchHTML($url) {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0'
        ]
    ];
    $context = stream_context_create($opts);
    return file_get_contents($url, false, $context);
}

function extractItemsBackup($html) {
    $items_backup = null;
    $pattern = '/items_backup\s*=\s*({.*?});/s';

    preg_match($pattern, $html, $matches);

    if (!empty($matches[1])) {
        $json = $matches[1];

        $items_backup = json_decode($json, true);
    }

    return $items_backup['normal'];
}

$items = json_decode(file_get_contents("items.json"), true);

$itemMods = [];
foreach($items as $category => $subCategories) {
    foreach($subCategories as $subCategory => $url) {
        echo "Starting subcategory: $subCategory \n";
        $html = fetchHTML($url);
        $downloadedMods = extractItemsBackup($html);

        foreach($downloadedMods as $mod) {
            if (isset($itemMods[$mod['ID']])) {
                continue;
            }

            $value = preg_replace('/<br><span class=\'secondary\'>(.*?)<\/span>/', '', $mod["str"]);
            $value = str_replace(["<span class='mod-value'>", "</span>"], '', $value);
            $value = str_replace("&ndash;", '-', $value);
            $value = str_replace(["<br>", "<br/>"], '<br>', $value);

            $itemMods[$mod['ID']] = [
                "ID" => $mod["ID"],
                "ModTypeID" => $mod["ModTypeID"],
                "Name"  => $mod["Name"],
                "Code" => $mod["Code"],
                "Level" => (int)$mod["Level"],
                "ModDomainsID" => (int)$mod["ModDomainsID"],
                "ModGenerationTypeID" => (int)$mod["ModGenerationTypeID"],
                "Mods" => explode("<br>", $value),
            ];
        }
        echo "Ending subcategory: $subCategory \n";
    }
}

echo "Downloaded \n";
file_put_contents('all_mods.json', json_encode($itemMods));
