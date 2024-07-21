<?php

if (!file_exists("all_mods.json")) {
    echo "Download all mods first! \n";die;
}

$itemMods = json_decode(file_get_contents('all_mods.json'), true);

$simplifiedMods = [];
foreach($itemMods as $modMain) {
    foreach($modMain["Mods"] as $mod) {
        $mod = preg_replace('/\d+\.\d+|(\(-?\d+\.?\d?--?\d+\.?\d?\))|\d+(\(\d+-\d+\))|\d+/', '#', $mod);
        if (array_key_exists($mod . (string)$modMain["ModDomainsID"], $simplifiedMods)) {
            continue;
        }

        $simplifiedMods[$mod . (string)$modMain["ModDomainsID"]] = [
            'ModDomainsID' => $modMain["ModDomainsID"],
            'ModGenerationTypeID' => $modMain["ModGenerationTypeID"],
            'Mod' => $mod
        ];
    }
}

file_put_contents('simplified_mods.json', json_encode(array_values($simplifiedMods)));