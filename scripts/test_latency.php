<?php
date_default_timezone_set('Europe/Prague');

$urls = [
    'https://dev.qarta.cz/',
    'https://dev.qarta.cz/kontakt',
    'https://qarta-speed.tomaskorinek.com/',
];

$dataDir = __DIR__ . '/../latency_logs';
$templateFile = __DIR__ . '/template.html';
$outputHtml = __DIR__ . '/../index.html';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$allData = [];

foreach ($urls as $url) {
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

    // Lepší generování názvu souboru (host + path)
    $parsedUrl = parse_url($url);
    $host = $parsedUrl['host'] ?? 'unknown';
    $path = rtrim($parsedUrl['path'] ?? '', '/');
    if ($path === '') {
    $path = '_root';
    }
    $fileName = preg_replace('/[^a-z0-9]+/i', '_', $host . $path) . '.csv';
    $dataFile = $dataDir . '/' . $fileName;

    // Přidání záznamu
    $handle = fopen($dataFile, 'a');
    fputcsv($handle, $entry);
    fclose($handle);

    // Načti data pro tabulku
    $data = [];
    if (($handle = fopen($dataFile, 'r')) !== false) {
        while (($row = fgetcsv($handle)) !== false) {
            $data[] = $row;
        }
        fclose($handle);
    }

    $allData[$url] = array_reverse($data); // nejnovější nahoře
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
