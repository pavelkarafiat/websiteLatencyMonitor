<?php
date_default_timezone_set('Europe/Prague');

$urls = [
    'https://test.qarta.cz/',
    'https://test.qarta.cz/kontakt',
    'https://qarta-speed.tomaskorinek.com',
    'https://qarta-speed.tomaskorinek.com/kontakt',
];

$dataDir = __DIR__ . '/../latency_logs';
$templateFile = __DIR__ . '/template.html';
$outputHtml = __DIR__ . '/../index.html';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$allData = [];

foreach ($urls as $index => $url) {
    // Statický název souboru podle pořadí URL
    $fileName = "url" . ($index + 1) . ".csv";
    $dataFile = $dataDir . '/' . $fileName;

    // Načti existující data
    $data = [];
    if (file_exists($dataFile) && ($handle = fopen($dataFile, 'r')) !== false) {
        while (($row = fgetcsv($handle)) !== false) {
            $data[] = $row;
        }
        fclose($handle);
    }

    // Vytvoř nový záznam
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    $ttfb = round(($info['starttransfer_time'] ?? 0) * 1000);
    $entry = [
        date('Y-m-d H:i:s'),
        $ttfb,
    ];

    // Přidej nový záznam do paměti
    $data[] = $entry;

    // Případné omezení na posledních N záznamů
    $maxRows = 100;
    $data = array_slice($data, -$maxRows);

    // Ulož zpět do CSV
    $handle = fopen($dataFile, 'w');
    foreach ($data as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);

    // Zaznamenej pro HTML výstup
    $allData[$url] = array_reverse($data); // pro HTML: nejnovější nahoře
}



// Generuj HTML
$template = file_get_contents($templateFile);
$tablesHtml = '';

foreach ($allData as $url => $rows) {
    $rowsHtml = '';
    foreach ($rows as $row) {
        $datetime = htmlspecialchars($row[0]);
        $ttfb_ms = (int)$row[1];
        $rowsHtml .= sprintf(
            "<tr><td>%s</td><td class='%s'>%d</td></tr>\n",
            $datetime,
            $ttfb_ms > 1000 ? 'bad' : 'ok',
            $ttfb_ms
        );
    }

    $tablesHtml .= "<h2>$url</h2>\n";
    $tablesHtml .= "<table>\n<thead><tr><th>Čas</th><th>TTFB (ms)</th></tr></thead>\n<tbody>\n";
    $tablesHtml .= $rowsHtml;
    $tablesHtml .= "</tbody>\n</table>\n\n";
}

$html = str_replace('<!--TABLES-->', $tablesHtml, $template);
file_put_contents($outputHtml, $html);
