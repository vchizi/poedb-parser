<?php

$scripts = [
    'download_uniques.php',
    'download_dust.php',
    'download_divination_cards.php',
    'download_eldritch_implicit_modifiers.php',
    'download_items_mods.php',
    'download_maps.php',
];

$passed = 0;
$failed = 0;
$phpBin = PHP_BINARY;

foreach ($scripts as $script) {
    if (!file_exists($script)) {
        $failed++;
        echo "MISSING: {$script}" . PHP_EOL;
        echo str_repeat('-', 40) . PHP_EOL;
        continue;
    }

    echo "Running {$script}..." . PHP_EOL;
    passthru(escapeshellarg($phpBin) . ' ' . escapeshellarg($script), $exitCode);

    if ($exitCode === 0) {
        $passed++;
        echo "OK: {$script}" . PHP_EOL;
    } else {
        $failed++;
        echo "FAILED ({$exitCode}): {$script}" . PHP_EOL;
    }

    echo str_repeat('-', 40) . PHP_EOL;
}

echo "Finished. Passed: {$passed}, Failed: {$failed}, Total: " . count($scripts) . PHP_EOL;
exit($failed > 0 ? 1 : 0);
