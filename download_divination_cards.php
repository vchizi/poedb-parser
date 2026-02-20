<?php

function fetchHtmlContent($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ]);

    $html = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    }

    curl_close($ch);
    return $html;
}

function extractDivinationCards($html) {
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $cardsContainer = $xpath->query("//*[@id='DivinationCardsItem']");
    if ($cardsContainer->length === 0) {
        echo "Error: ID 'DivinationCardsItem' not found in the HTML.";
        file_put_contents('error_log.txt', "ID 'DivinationCardsItem' not found in the fetched HTML.\n");
        return [];
    }
    // file_put_contents('downloaded_page.html', $dom->saveHTML($xpath->query(".//div/div", $cardsContainer->item(0))->item(0)));die;

    $cardsList = [];
    $cards = $xpath->query(".//div/div/div", $cardsContainer->item(0));
    foreach ($cards as $card) {

        $divCard = trim($xpath->query(".//a[contains(@class, 'divination')]", $card)[0]?->textContent);
        $item = trim($xpath->query(".//span[contains(@class, 'uniqueitem')]", $card)[0]?->textContent);

        if (empty($divCard) || empty($item) || isset($cardsList[$divCard])) {
            continue;
        }

        $cardsList[$divCard] = $item;
    }

    return $cardsList;
}

$url = 'https://poedb.tw/us/Divination_Cards#DivinationCardsItem';
$htmlContent = fetchHtmlContent($url);
$cardsList = extractDivinationCards($htmlContent);
file_put_contents('data/divination_cards.json', json_encode($cardsList, JSON_PRETTY_PRINT));

echo "Divination cards extracted successfully. \n";
