<?php

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

function extractModsViewData($html) {
    $callNeedle = 'new ModsView(';
    $callPos = strpos($html, $callNeedle);
    if ($callPos === false) {
        return null;
    }

    $objectStart = strpos($html, '{', $callPos);
    if ($objectStart === false) {
        return null;
    }

    $length = strlen($html);
    $depth = 0;
    $inString = false;
    $stringQuote = '';
    $escape = false;
    $objectEnd = -1;

    for ($i = $objectStart; $i < $length; $i++) {
        $ch = $html[$i];

        if ($inString) {
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($ch === '\\') {
                $escape = true;
                continue;
            }
            if ($ch === $stringQuote) {
                $inString = false;
                $stringQuote = '';
            }
            continue;
        }

        if ($ch === '"' || $ch === '\'') {
            $inString = true;
            $stringQuote = $ch;
            continue;
        }

        if ($ch === '{') {
            $depth++;
            continue;
        }
        if ($ch === '}') {
            $depth--;
            if ($depth === 0) {
                $objectEnd = $i;
                break;
            }
        }
    }

    if ($objectEnd === -1) {
        return null;
    }

    $jsonPayload = substr($html, $objectStart, $objectEnd - $objectStart + 1);
    $decoded = json_decode($jsonPayload, true);
    if (!is_array($decoded)) {
        return null;
    }
    return $decoded;
}


function extractMods(array $downloadedMods, string $modType)
{
    $itemMods = [];
    if (!isset($downloadedMods[$modType]) || !is_array($downloadedMods[$modType])) {
        return $itemMods;
    }

    $modDomainId = (int)$downloadedMods['opt']['ModDomainsID'];
    foreach ($downloadedMods[$modType] as $mod) {
        if (!is_array($mod)) {
            continue;
        }
        if (!isset($mod['Name'], $mod['ModGenerationTypeID'], $mod['Level'], $mod['str'])) {
            continue;
        }

        $id = sprintf(
            "%s_%s_%s_%s_%s_%s",
            $modDomainId,
            $mod['Name'],
            $mod['ModGenerationTypeID'],
            implode('_', $mod['ModFamilyList']),
            $downloadedMods['opt']['ItemClassesCode'],
            $downloadedMods['baseitem']['tags'] ?? ''
        );
        $id = str_replace(' ', '-', $id);

        $value = preg_replace('/<br><span class="secondary">(.*?)<\/span>/', '', $mod['str']);
        $value = str_replace(["<span class='mod-value'>", "</span>", "<span class=\"ndash\">"], '', $value);
        $value = str_replace(["&ndash;", "—", "–"], '-', $value);
        $value = str_replace(["<br>", "<br/>"], '<br>', $value);

        $mods = explode('<br>', $value);

        $attributes = [];
        if ($modDomainId == 5) {
            $attributePatterns = [
                'more_currency' => '/^([+-]?\d+)%\s+more Currency found in Area$/i',
                'rarity' => '/^([+-]?\d+)%\s+increased Rarity of Items found in this Area$/i',
                'quantity' => '/^([+-]?\d+)%\s+increased Quantity of Items found in this Area$/i',
                'pack_size' => '/^([+-]?\d+)%\s+increased Pack size$/i',
                'more_maps' => '/^([+-]?\d+)%\s+more Maps found in Area$/i',
                'more_scarabs' => '/^([+-]?\d+)%\s+more Scarabs found in Area$/i',
            ];

            $filteredMods = [];
            foreach ($mods as $modLine) {
                $modLine = trim($modLine);
                if ($modLine === '') {
                    continue;
                }

                $matchedAttribute = false;
                foreach ($attributePatterns as $attributeKey => $pattern) {
                    if (preg_match($pattern, $modLine, $matches)) {
                        $attributes[$attributeKey] = (int)$matches[1];
                        $matchedAttribute = true;
                        break;
                    }
                }

                if (!$matchedAttribute) {
                    $filteredMods[] = $modLine;
                }
            }

            $mods = $filteredMods;
        }

        $itemMod = [
            'ID' => $id,
            'Name' => $mod['Name'],
            'Code' => implode('_', $mod['ModFamilyList']),
            'Level' => (int)$mod['Level'],
            'ModDomainsID' => $modDomainId,
            'ModGenerationTypeID' => (int)$mod['ModGenerationTypeID'],
            'Mods' => $mods,
            'Base' => $downloadedMods['opt']['ItemClassesCode'],
            'BaseId' => $downloadedMods['opt']['ItemClassesID'],
            'BaseTag' => $downloadedMods['baseitem']['tags'] ?? null,
        ];

        if (!empty($attributes)) {
            $itemMod['Attributes'] = $attributes;
        }

        $itemMods[] = $itemMod;
    }

    return $itemMods;
}

$items = [
    'Weapons' => [
        'Claws' => 'https://poedb.tw/us/Claws#ModifiersCalc',
        'Daggers' => 'https://poedb.tw/us/Daggers#ModifiersCalc',
        'One Hand Swords' => 'https://poedb.tw/us/One_Hand_Swords#ModifiersCalc',
        'One_Hand_Axes' => 'https://poedb.tw/us/One_Hand_Axes#ModifiersCalc',
        'One_Hand_Maces' => 'https://poedb.tw/us/One_Hand_Maces#ModifiersCalc',
        'Sceptres' => 'https://poedb.tw/us/Sceptres#ModifiersCalc',
        'Rune_Daggers' => 'https://poedb.tw/us/Rune_Daggers#ModifiersCalc',
        'Thrusting_One_Hand_Swords' => 'https://poedb.tw/us/Thrusting_One_Hand_Swords#ModifiersCalc',
        'Bows' => 'https://poedb.tw/us/Bows#ModifiersCalc',
        'Staves' => 'https://poedb.tw/us/Staves#ModifiersCalc',
        'Two_Hand_Swords' => 'https://poedb.tw/us/Two_Hand_Swords#ModifiersCalc',
        'Two_Hand_Axes' => 'https://poedb.tw/us/Two_Hand_Axes#ModifiersCalc',
        'Two_Hand_Maces' => 'https://poedb.tw/us/Two_Hand_Maces#ModifiersCalc',
        'Warstaves' => 'https://poedb.tw/us/Warstaves#ModifiersCalc',
    ],
    'Jewellery' => [
        'Amulets' => 'https://poedb.tw/us/Amulets#ModifiersCalc',
        'Rings' => 'https://poedb.tw/us/Rings#ModifiersCalc',
        'Belts' => 'https://poedb.tw/us/Belts#ModifiersCalc',
        'Trinkets' => 'https://poedb.tw/us/Trinkets#ModifiersCalc',
    ],
    'Gloves' => [
        'Gloves(str)' => 'https://poedb.tw/us/Gloves_str#ModifiersCalc',
        'Gloves(dex)' => 'https://poedb.tw/us/Gloves_dex#ModifiersCalc',
        'Gloves(int)' => 'https://poedb.tw/us/Gloves_int#ModifiersCalc',
        'Gloves(str_dex)' => 'https://poedb.tw/us/Gloves_str_dex#ModifiersCalc',
        'Gloves(str_int)' => 'https://poedb.tw/us/Gloves_str_int#ModifiersCalc',
        'Gloves(dex_int)' => 'https://poedb.tw/us/Gloves_dex_int#ModifiersCalc',
    ],
    'Boots' => [
        'Boots(str)' => 'https://poedb.tw/us/Boots_str#ModifiersCalc',
        'Boots(dex)' => 'https://poedb.tw/us/Boots_dex#ModifiersCalc',
        'Boots(int)' => 'https://poedb.tw/us/Boots_int#ModifiersCalc',
        'Boots(str_dex)' => 'https://poedb.tw/us/Boots_str_dex#ModifiersCalc',
        'Boots(str_int)' => 'https://poedb.tw/us/Boots_str_int#ModifiersCalc',
        'Boots(dex_int)' => 'https://poedb.tw/us/Boots_dex_int#ModifiersCalc',
    ],
    'BodyArmours' => [
        'Body Armours(str)' => 'https://poedb.tw/us/Body_Armours_str#ModifiersCalc',
        'Body Armours(dex)' => 'https://poedb.tw/us/Body_Armours_dex#ModifiersCalc',
        'Body Armours(int)' => 'https://poedb.tw/us/Body_Armours_int#ModifiersCalc',
        'Body Armours(str_dex)' => 'https://poedb.tw/us/Body_Armours_str_dex#ModifiersCalc',
        'Body Armours(str_int)' => 'https://poedb.tw/us/Body_Armours_str_int#ModifiersCalc',
        'Body Armours(dex_int)' => 'https://poedb.tw/us/Body_Armours_dex_int#ModifiersCalc',
        'Body Armours(str_dex_int)' => 'https://poedb.tw/us/Body_Armours_str_dex_int#ModifiersCalc',
    ],
    'Helmets' => [
        'Helmets(str)' => 'https://poedb.tw/us/Helmets_str#ModifiersCalc',
        'Helmets(dex)' => 'https://poedb.tw/us/Helmets_dex#ModifiersCalc',
        'Helmets(int)' => 'https://poedb.tw/us/Helmets_int#ModifiersCalc',
        'Helmets(str_dex)' => 'https://poedb.tw/us/Helmets_str_dex#ModifiersCalc',
        'Helmets(str_int)' => 'https://poedb.tw/us/Helmets_str_int#ModifiersCalc',
        'Helmets(dex_int)' => 'https://poedb.tw/us/Helmets_dex_int#ModifiersCalc',
    ],
    'Offhand' => [
        'Quivers' => 'https://poedb.tw/us/Quivers#ModifiersCalc',
        'Shields(str)' => 'https://poedb.tw/us/Shields_str#ModifiersCalc',
        'Shields(dex)' => 'https://poedb.tw/us/Shields_dex#ModifiersCalc',
        'Shields(int)' => 'https://poedb.tw/us/Shields_int#ModifiersCalc',
        'Shields(str_dex)' => 'https://poedb.tw/us/Shields_str_dex#ModifiersCalc',
        'Shields(str_int)' => 'https://poedb.tw/us/Shields_str_int#ModifiersCalc',
        'Shields(dex_int)' => 'https://poedb.tw/us/Shields_dex_int#ModifiersCalc',
    ],
    'Jewels' => [
        'Crimson Jewel' => 'https://poedb.tw/us/Crimson_Jewel#ModifiersCalc',
        'Viridian Jewel' => 'https://poedb.tw/us/Viridian_Jewel#ModifiersCalc',
        'Cobalt Jewel' => 'https://poedb.tw/us/Cobalt_Jewel#ModifiersCalc',
        'Prismatic Jewel' => 'https://poedb.tw/us/Prismatic_Jewel#ModifiersCalc',
        'Murderous Eye Jewel' => 'https://poedb.tw/us/Murderous_Eye_Jewel#ModifiersCalc',
        'Searching Eye Jewel' => 'https://poedb.tw/us/Searching_Eye_Jewel#ModifiersCalc',
        'Hypnotic Eye Jewel' => 'https://poedb.tw/us/Hypnotic_Eye_Jewel#ModifiersCalc',
        'Ghastly Eye Jewel' => 'https://poedb.tw/us/Ghastly_Eye_Jewel#ModifiersCalc',
        'Axe and Sword Damage' => 'https://poedb.tw/us/Large_Cluster_Jewel_affliction_axe_and_sword_damage',
        'Mace and Staff Damage' => 'https://poedb.tw/us/Large_Cluster_Jewel_affliction_mace_and_staff_damage',
        'Dagger and Claw Damage' => 'https://poedb.tw/us/Large_Cluster_Jewel_affliction_dagger_and_claw_damage',
        'Bow Damage' => 'https://poedb.tw/us/Large_Cluster_Jewel_affliction_bow_damage',
        'Wand Damage' => 'https://poedb.tw/us/Large_Cluster_Jewel_affliction_wand_damage',
        'Two Handed Melee Weapons Damage' => 'https://poedb.tw/us/Large_Cluster_Jewel_affliction_damage_with_two_handed_melee_weapons',
        'Attack Damage while Dual Wielding' => 'https://poedb.tw/us/Large_Cluster_Jewel_affliction_attack_damage_while_dual_wielding_',
        'Attack Damage while holding a Shield' => 'https://poedb.tw/us/Large_Cluster_Jewel_affliction_attack_damage_while_holding_a_shield',
        'Attack Damage' => 'https://poedb.tw/us/Large_Cluster_Jewel_affliction_attack_damage_',
        'Spell Damage' => 'https://poedb.tw/us/Large_Cluster_Jewel_affliction_spell_damage',
        'Elemental Damage' => 'https://poedb.tw/us/Large_Cluster_Jewel_affliction_elemental_damage',
        'Physical Damage' => 'https://poedb.tw/us/Large_Cluster_Jewel_affliction_physical_damage',
        'Fire Damage' => 'https://poedb.tw/us/Large_Cluster_Jewel_affliction_fire_damage',
        'Lightning Damage' => 'https://poedb.tw/us/Large_Cluster_Jewel_affliction_lightning_damage',
        'Cold Damage' => 'https://poedb.tw/us/Large_Cluster_Jewel_affliction_cold_damage',
        'Chaos Damage' => 'https://poedb.tw/us/Large_Cluster_Jewel_affliction_chaos_damage',
        'Minion Damage' => 'https://poedb.tw/us/Large_Cluster_Jewel_affliction_minion_damage',
        'Increased Fire Damage over Time' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_fire_damage_over_time_multiplier',
        'Increased Chaos Damage over Time' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_chaos_damage_over_time_multiplier',
        'Increased Physical Damage over Time' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_physical_damage_over_time_multiplier',
        'Increased Cold Damage over Time' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_cold_damage_over_time_multiplier',
        'Increased Damage over Time' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_damage_over_time_multiplier',
        'Increased Effect of Non-Damaging Ailments' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_effect_of_non-damaging_ailments',
        'Increased Damage while affected by a Herald' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_damage_while_you_have_a_herald',
        'Minions deal increased Damage while you are affected by a Herald' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_minion_damage_while_you_have_a_herald',
        'Exerted Attacks deal increased Damage' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_warcry_buff_effect',
        'Increased Critical Strike Chance' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_critical_chance',
        'Minions have increased maximum Life' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_minion_life',
        'Increased Area Damage' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_area_damage',
        'Increased Projectile Damage' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_projectile_damage',
        'Increased Trap Damage and increased Mine Damage' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_trap_and_mine_damage',
        'Increased Totem Damage' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_totem_damage',
        'Increased Brand Damage' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_brand_damage',
        'Channelling Skills deal increased Damage' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_channelling_skill_damage',
        'Increased Flask Effect Duration' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_flask_duration',
        'Increased Life Recovery from Flasks and increased Mana Recovery from Flasks' => 'https://poedb.tw/us/Medium_Cluster_Jewel_affliction_life_and_mana_recovery_from_flasks',
        'Increased maximum Life' => 'https://poedb.tw/us/Small_Cluster_Jewel_affliction_maximum_life',
        'Increased maximum Energy Shield' => 'https://poedb.tw/us/Small_Cluster_Jewel_affliction_maximum_energy_shield',
        'Increased maximum Mana' => 'https://poedb.tw/us/Small_Cluster_Jewel_affliction_maximum_mana',
        'Increased Armour' => 'https://poedb.tw/us/Small_Cluster_Jewel_affliction_armour',
        'Increased Evasion Rating' => 'https://poedb.tw/us/Small_Cluster_Jewel_affliction_evasion',
        'Chance to Block Attack Damage' => 'https://poedb.tw/us/Small_Cluster_Jewel_affliction_chance_to_block',
        'Chance to Block Spell Damage' => 'https://poedb.tw/us/Small_Cluster_Jewel_affliction_chance_to_block',
        'To Fire Resistance' => 'https://poedb.tw/us/Small_Cluster_Jewel_affliction_fire_resistance',
        'To Cold Resistance' => 'https://poedb.tw/us/Small_Cluster_Jewel_affliction_cold_resistance',
        'To Lightning Resistance' => 'https://poedb.tw/us/Small_Cluster_Jewel_affliction_lightning_resistance',
        'To Chaos Resistance' => 'https://poedb.tw/us/Small_Cluster_Jewel_affliction_chaos_resistance',
        'Chance to Suppress Spell Damage' => 'https://poedb.tw/us/Small_Cluster_Jewel_affliction_chance_to_dodge_attacks',
        'Increased Mana Reservation Efficiency of Skills' => 'https://poedb.tw/us/Small_Cluster_Jewel_affliction_reservation_efficiency_small',
        'Increased Effect of your Curses' => 'https://poedb.tw/us/Small_Cluster_Jewel_affliction_curse_effect_small',
    ],
    'Flasks' => [
        'Life Flasks' => 'https://poedb.tw/us/Life_Flasks#ModifiersCalc',
        'Mana Flasks' => 'https://poedb.tw/us/Mana_Flasks#ModifiersCalc',
        'Utility Flasks' => 'https://poedb.tw/us/Utility_Flasks#ModifiersCalc',
        'Tinctures' => 'https://poedb.tw/us/Tinctures#ModifiersCalc',
    ],
    'Special' => [
        'Unset Ring' => 'https://poedb.tw/us/Unset_Ring#ModifiersCalc',
        'Iron Flask' => 'https://poedb.tw/us/Iron_Flask#ModifiersCalc',
        'Bone Ring' => 'https://poedb.tw/us/Bone_Ring#ModifiersCalc',
        'Convoking Wand' => 'https://poedb.tw/us/Convoking_Wand#ModifiersCalc',
        'Bone Spirit Shield' => 'https://poedb.tw/us/Bone_Spirit_Shield#ModifiersCalc',
        'Runic Crown' => 'https://poedb.tw/us/Runic_Crown#ModifiersCalc',
        'Runic Sabatons' => 'https://poedb.tw/us/Runic_Sabatons#ModifiersCalc',
        'Runic Gauntlets' => 'https://poedb.tw/us/Runic_Gauntlets#ModifiersCalc',
        'Silver Flask' => 'https://poedb.tw/us/Silver_Flask#ModifiersCalc',
    ],
    'Maps' => [
        'Contracts' => 'https://poedb.tw/us/Contracts#ModifiersCalc',
        'Blueprints' => 'https://poedb.tw/us/Blueprints#ModifiersCalc',
        'Maven\'s Invitation' => 'https://poedb.tw/us/Mavens_Invitation%3A_The_Feared#ModifiersCalc',
        'Maps(Low)' => 'https://poedb.tw/us/Maps_low_tier',
        'Maps(Mid)' => 'https://poedb.tw/us/Maps_mid_tier',
        'Maps(Top)' => 'https://poedb.tw/us/Maps_top_tier',
        'Maps(T17)' => 'https://poedb.tw/us/Maps_uber_tier',
    ],
    'Heist Tools' => [
        'Obsidian Sharpening Stone' => 'https://poedb.tw/us/Obsidian_Sharpening_Stone#ModifiersCalc',
        'Precise Arrowhead' => 'https://poedb.tw/us/Precise_Arrowhead#ModifiersCalc',
        'Burst Band' => 'https://poedb.tw/us/Burst_Band#ModifiersCalc',
        'Master Lockpick' => 'https://poedb.tw/us/Master_Lockpick#ModifiersCalc',
        'Steel Bracers' => 'https://poedb.tw/us/Steel_Bracers#ModifiersCalc',
        'Thaumaturgical Sensing Charm' => 'https://poedb.tw/us/Thaumaturgical_Sensing_Charm#ModifiersCalc',
        'Thaumetic Flashpowder' => 'https://poedb.tw/us/Thaumetic_Flashpowder#ModifiersCalc',
        'Thaumaturgical Ward' => 'https://poedb.tw/us/Thaumaturgical_Ward#ModifiersCalc',
        'Grandmaster Keyring' => 'https://poedb.tw/us/Grandmaster_Keyring#ModifiersCalc',
        'Silkweave Sole' => 'https://poedb.tw/us/Silkweave_Sole#ModifiersCalc',
        'Regicide Disguise Kit' => 'https://poedb.tw/us/Regicide_Disguise_Kit#ModifiersCalc',
        'Thaumetic Blowtorch' => 'https://poedb.tw/us/Thaumetic_Blowtorch#ModifiersCalc',
        'Whisper-woven Cloak' => 'https://poedb.tw/us/Whisper-woven_Cloak#ModifiersCalc',
        'Foliate Brooch' => 'https://poedb.tw/us/Foliate_Brooch#ModifiersCalc',
    ],
];
$modTypes = ['normal', 'elder', 'shaper', 'crusader', 'redeemer', 'hunter', 'warlord'/*, 'searing', 'eater'*/];
$modsByType = [];
foreach ($modTypes as $modType) {
    $modsByType[$modType] = [];
}

//$rawOutputDir = 'tmp_data';
//if (!is_dir($rawOutputDir)) {
//    mkdir($rawOutputDir, 0777, true);
//}

foreach ($items as $category => $subCategories) {
    if (!is_array($subCategories)) {
        continue;
    }

    foreach ($subCategories as $subCategory => $url) {
        echo "Starting subcategory: {$subCategory}\n";

        $html = fetchHTML($url);
        if ($html === false || $html === '') {
            fwrite(STDERR, "Failed to fetch HTML for {$subCategory}: {$url}\n");
            continue;
        }

        $downloadedMods = extractModsViewData($html);
        if (
            !is_array($downloadedMods)
            || !isset($downloadedMods['opt']['ModDomainsID'])
            || !isset($downloadedMods['normal'])
            || !is_array($downloadedMods['normal'])
        ) {
            fwrite(STDERR, "Failed to parse ModsView payload for {$subCategory}: {$url}\n");
            continue;
        }

//        $rawModDomainId = (string)($downloadedMods['opt']['ModDomainsID'] ?? 'unknown');
//        $rawItemClassesCode = (string)($downloadedMods['opt']['ItemClassesCode'] ?? 'unknown');
//        $rawItemClassesId = (string)($downloadedMods['opt']['ItemClassesID'] ?? 'unknown');
//        $rawBaseitemTags = (string)($downloadedMods['baseitem']['tags'] ?? 'unknown');
//        $rawFileId = "{$rawModDomainId}_{$rawItemClassesCode}_{$rawItemClassesId}_{$rawBaseitemTags}";
//        $safeRawFileName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $rawFileId);
//        $rawOutputFile = "{$rawOutputDir}/{$safeRawFileName}.json";
//        file_put_contents(
//            $rawOutputFile,
//            json_encode($downloadedMods, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
//        );
//        echo "Saved raw to {$rawOutputFile}\n";

        foreach ($modTypes as $modType) {
            $extractedMods = extractMods($downloadedMods, $modType);
            $modsByType[$modType] = array_merge(
                $modsByType[$modType],
                $extractedMods
            );
        }

        echo "Ending subcategory: {$subCategory}\n";
    }
}

echo "Downloaded\n";

$payload = $modsByType;
foreach ($payload as $modType => $modsList) {
    $payload[$modType] = array_values($modsList);
}
$outputFile = 'data/all_mods.json';
file_put_contents(
    $outputFile,
    json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
);
echo "Saved to {$outputFile}\n";
