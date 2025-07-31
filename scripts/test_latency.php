<?php
date_default_timezone_set('Europe/Prague');

$url = 'https://dev.qarta.cz/';
$dataFile = __DIR__ . '/../latency_data.csv';
$templateFile = __DIR__ . '/template.html';
$outputHtml = __DIR__ . '/../index.html';

// Latency test
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

$entry = [
    date('Y-m-d H:i:s'),
    $info['http_code'] ?? 0,
    round(($info['starttransfer_time'] ?? 0) * 1000),
    round(($info['total_time'] ?? 0) * 1000),
];

// Přidání záznamu (append)
$handle = fopen($dataFile, 'a');
fputcsv($handle, $entry);
fclose($handle);

// Načti všechna data pro generování HTML
$data = [];
if (($handle = fopen($dataFile, 'r')) !== false) {
    while (($row = fgetcsv($handle)) !== false) {
        $data[] = $row;
    }
    fclose($handle);
}

// Generuj HTML
$template = file_get_contents($templateFile);
$rowsHtml = '';
foreach (array_reverse($data) as $row) {
    $datetime = htmlspecialchars($row[0]);
    $http_code = (int)$row[1];
    $ttfb_ms = (int)$row[2];
    $total_time_ms = (int)$row[3];
    $rowsHtml .= sprintf(
        "<tr><td>%s</td><td>%d</td><td class='%s'>%d</td><td class='%s'>%d</td></tr>\n",
        $datetime,
        $http_code,
        $ttfb_ms > 1000 ? 'bad' : 'ok',
        $ttfb_ms,
        $total_time_ms > 3000 ? 'bad' : 'ok',
        $total_time_ms
    );
}

$html = str_replace('<!--DATA-->', $rowsHtml, $template);
file_put_contents($outputHtml, $html);
