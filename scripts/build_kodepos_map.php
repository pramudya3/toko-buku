<?php

/**
 * Proses dataset kodepos (sooluh/kodepos) → CSV mapping per kecamatan.
 * Satu kali jalan, output: database/data/districts_kodepos.csv
 */
$memory = ini_set('memory_limit', '512M');

if ($memory === false) {
    fwrite(STDERR, "gagal set memory_limit\n");
    exit(1);
}

function norm(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/^(kabupaten|kota)\s+/', '', $s);

    return preg_replace('/[^a-z0-9]/', '', $s) ?? '';
}

$source = $argv[1] ?? '/tmp/kodepos.json';
$target = $argv[2] ?? __DIR__.'/../database/data/districts_kodepos.csv';

$raw = file_get_contents($source);

if ($raw === false) {
    fwrite(STDERR, "gagal baca {$source}\n");
    exit(1);
}

$data = json_decode($raw, true);

if (! is_array($data)) {
    fwrite(STDERR, "gagal parse JSON\n");
    exit(1);
}

$map = [];

foreach ($data as $row) {
    $key = norm($row['province']).'|'.norm($row['regency']).'|'.norm($row['district']);

    if (! isset($map[$key]) || (int) $row['code'] < $map[$key]) {
        $map[$key] = (int) $row['code'];
    }
}

$out = fopen($target, 'w');

if ($out === false) {
    fwrite(STDERR, "gagal buka {$target}\n");
    exit(1);
}

foreach ($map as $key => $code) {
    fputcsv($out, [$key, $code]);
}

fclose($out);

fwrite(STDOUT, 'kecamatan unik: '.count($map).' → '.$target."\n");
