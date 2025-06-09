<?php

// Path to your CSV file
//https://gist.github.com/alserom/22bdd4106806cbd4f85a5cb8c4345c08
//https://www.reddit.com/r/pathofexile/comments/1fecnzu/uniques_dust_per_chaos_spreadsheet/
$csvFile = './poe-dust.csv';

// Open the CSV file
if (($handle = fopen($csvFile, 'r')) !== false) {
    // Get the headers
    $headers = fgetcsv($handle);

    // Initialize an array to hold the parsed data
    $data = [];

    // Loop through each row of the CSV
    while (($row = fgetcsv($handle)) !== false) {
        // Combine the header with the row data
        $item = array_combine($headers, $row);

        // Add this item to the data array
        $data[] = $item;
    }

    // Close the CSV file
    fclose($handle);

    // Convert the data array to JSON
    file_put_contents('dust.json', json_encode($data, JSON_PRETTY_PRINT));
} else {
    echo "Unable to open the file.";
}
