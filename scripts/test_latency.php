<?php
date_default_timezone_set('Europe/Prague');

// ==== KONFIGURACE ====
$url = 'https://dev.qarta.cz/';
$dataFile = __DIR__ . '/../latency_data.json';
$templateFile = __DIR__ . '/template.html';
$outputHtml = __DIR__ . '/../index.html';

// ==== LATENCY TEST ====
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
    'timestamp' => time(),
    'datetime' => date('Y-m-d H:i:s'),
    'http_code' => $info['http_code'] ?? 0,
    'ttfb_ms' => round(($info['starttransfer_time'] ?? 0) * 1000),
    'total_time_ms' => round(($info['total_time'] ?? 0) * 1000),
];

// ==== NAČTI & ULOŽ DATA ====
$data = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
$data[] = $entry;

// Odstraní záznamy starší než 24h
$cutoff = time() - 24 * 60 * 60;
$data = array_filter($data, fn($e) => $e['timestamp'] >= $cutoff);

file_put_contents($dataFile, json_encode(array_values($data), JSON_PRETTY_PRINT));

// ==== GENERUJ HTML ====
$template = file_get_contents($templateFile);
$rows = '';
foreach (array_reverse($data) as $row) {
    $rows .= sprintf(
        "<tr><td>%s</td><td>%d</td><td class='%s'>%d</td><td class='%s'>%d</td></tr>\n",
        htmlspecialchars($row['datetime']),
        $row['http_code'],
        $row['ttfb_ms'] > 1000 ? 'bad' : 'ok',
        $row['ttfb_ms'],
        $row['total_time_ms'] > 3000 ? 'bad' : 'ok',
        $row['total_time_ms']
    );
}

$html = str_replace('<!--DATA-->', $rows, $template);
file_put_contents($outputHtml, $html);
