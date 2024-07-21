<?php

if (!file_exists("all_mods.json")) {
    echo "Download all mods first! \n";die;
}

$itemMods = json_decode(file_get_contents('all_mods.json'), true);

$simplifiedModsPrefixes = [];
$simplifiedModsSuffixes = [];
foreach($itemMods as $modMain) {
    foreach($modMain["Mods"] as $mod) {
        if ($modMain["ModDomainsID"] != 5) {
            continue;
        }

        $mod = preg_replace('/\d+\.\d+|(\(-?\d+\.?\d?--?\d+\.?\d?\))|\d+(\(\d+-\d+\))|\d+/', '#', $mod);
        if (array_key_exists($mod, $simplifiedModsPrefixes) || array_key_exists($mod, $simplifiedModsSuffixes)) {
            continue;
        }

        if ($modMain['ModGenerationTypeID'] == 1) {
            $simplifiedModsPrefixes[$mod] = $mod;
        } else {
            $simplifiedModsSuffixes[$mod] = $mod;
        }
    }
}

file_put_contents('map_p.txt', implode("\n", array_values($simplifiedModsPrefixes)));
file_put_contents('map_s.txt', implode("\n", array_values($simplifiedModsSuffixes)));