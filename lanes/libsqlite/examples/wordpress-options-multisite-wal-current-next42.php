<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWordPressMultisiteOptionsWalPlan;

$pageSize = 512;
$salt1 = 0x42004200;
$salt2 = 0x20260527;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('sqlite header multisite before import')
    . $page('wp_options current siteurl before import')
    . $page('wp_2_options current siteurl before import')
    . $page('wp_3_options current home before import')
    . $page('wp_sitemeta current site_name before import');

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 42, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([[2, 0, 'draft network siteurl before import'], [3, 5, 'committed blog 2 siteurl before import']] as [$pageNumber, $commit, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$plan = SQLiteWordPressMultisiteOptionsWalPlan::currentNext(
    SQLiteWal::parse($walBytes, $pageSize, true),
    $databaseBytes,
    'wp-content/database/multisite.sqlite',
    [
        ['scope' => 'network', 'option_id' => 1, 'option_name' => 'site_name', 'option_value' => 'Old Network', 'autoload' => 'yes'],
        ['scope' => 'network', 'option_id' => 2, 'option_name' => 'active_sitewide_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
        ['scope' => 'blog', 'blog_id' => 2, 'option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example/site-two', 'autoload' => 'yes'],
        ['scope' => 'blog', 'blog_id' => 2, 'option_id' => 2, 'option_name' => 'blog_public', 'option_value' => '1', 'autoload' => 'no'],
        ['scope' => 'blog', 'blog_id' => 3, 'option_id' => 1, 'option_name' => 'home', 'option_value' => 'https://old.example/site-three', 'autoload' => 'yes'],
    ],
    [
        ['scope' => 'network', 'option_name' => 'active_sitewide_plugins', 'option_value' => 'a:1:{s:19:"akismet/akismet.php";b:1;}', 'autoload' => 'yes'],
        ['scope' => 'blog', 'blog_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://new.example/site-two', 'autoload' => 'yes'],
        ['scope' => 'blog', 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'option_value' => 'a:1:{s:4:"post";s:12:"index.php?p=";}', 'autoload' => 'no'],
        ['scope' => 'blog', 'blog_id' => 3, 'option_name' => 'siteurl', 'option_value' => 'https://new.example/site-three', 'autoload' => 'yes'],
        ['scope' => 'network', 'option_name' => 'registration', 'option_value' => 'none', 'autoload' => 'no'],
    ],
    range(2, 12),
);

echo json_encode([
    'status' => $plan['status'],
    'tables' => $plan['tables'],
    'inserted' => $plan['inserted_keys'],
    'updated' => $plan['updated_keys'],
    'last_commit_frame' => $plan['append']['last_commit_frame'],
    'database_page_count' => $plan['database_page_count'],
    'next_reader_sources' => $plan['next_reader_sources'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
