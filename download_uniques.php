<?php

function loadEnvValue($path, $key) {
    if (!file_exists($path)) {
        throw new RuntimeException("Env file not found: {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new RuntimeException("Failed to read env file: {$path}");
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $envKey = trim($parts[0]);
        if ($envKey !== $key) {
            continue;
        }

        $value = trim($parts[1]);
        if (
            strlen($value) >= 2 &&
            (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === '\'' && substr($value, -1) === '\''))
        ) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }

    throw new RuntimeException("Missing {$key} in {$path}");
}

function fetchUniques($jwtAuth) {
    if ($jwtAuth === '') {
        throw new RuntimeException('JWT_AUTH is empty in .env.local');
    }

    $url = 'https://poeladder.com/api/v1/users/vchizi/uniques?ladderIdentifier=SSF_Mirage&status=all';
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", [
                'Accept: application/json',
                'Jwt-Auth: ' . $jwtAuth,
                'User-Agent: poedb-parser/1.0',
            ]),
            'ignore_errors' => true,
            'timeout' => 30,
        ],
    ];

    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        throw new RuntimeException('Request to poeladder failed');
    }

    $statusCode = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $statusCode = (int)$m[1];
    }
    if ($statusCode < 200 || $statusCode >= 300) {
        throw new RuntimeException("Poeladder request failed with HTTP {$statusCode}");
    }

    $json = json_decode($response, true);
    if (!is_array($json) || !isset($json['uniques']) || !is_array($json['uniques'])) {
        throw new RuntimeException('Invalid response JSON: missing uniques array');
    }

    return $json['uniques'];
}

function calculateSlotsFromDimensions($dimensions) {
    if (!is_string($dimensions) || !preg_match('/^(\d+)x(\d+)$/', $dimensions, $m)) {
        return 1;
    }

    return (int)$m[1] * (int)$m[2];
}

function enrichUniquesWithSlots($uniques) {
    $enriched = [];

    foreach ($uniques as $unique) {
        if (!is_array($unique)) {
            continue;
        }

        $unique['slots'] = calculateSlotsFromDimensions($unique['dimensions'] ?? null);
        $enriched[] = $unique;
    }

    return $enriched;
}

function saveUniques($uniques, $path) {
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("Failed to create directory: {$dir}");
    }

    $result = file_put_contents(
        $path,
        json_encode($uniques, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
    );
    if ($result === false) {
        throw new RuntimeException("Failed to write {$path}");
    }
}

$jwtAuth = loadEnvValue('.env.local', 'JWT_AUTH');
$uniques = fetchUniques($jwtAuth);
$enrichedUniques = enrichUniquesWithSlots($uniques);
saveUniques($enrichedUniques, 'data/uniques.json');

echo 'Saved uniques: ' . count($enrichedUniques) . PHP_EOL;
