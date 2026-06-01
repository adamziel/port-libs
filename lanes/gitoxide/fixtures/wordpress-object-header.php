<?php

declare(strict_types=1);

$blockBlobBody = "<!-- wp:paragraph -->\n"
    . "<p>Native PHP loose object parsing for a WordPress block export.</p>\n"
    . "<!-- /wp:paragraph -->\n";
$looseHeader = 'blob ' . strlen($blockBlobBody) . "\0";
$positiveSizeLooseHeader = 'blob +' . strlen($blockBlobBody) . "\0";
$zeroPaddedSizeLooseHeader = 'blob ' . str_pad((string) strlen($blockBlobBody), 5, '0', STR_PAD_LEFT) . "\0";
$lfSizeLooseHeader = 'blob ' . strlen($blockBlobBody) . "\n\0";
$emptyBlobBody = '';
$negativeZeroSizeLooseHeader = "blob -0\0";
$canonicalEmptyBlobHeader = "blob 0\0";
$lateSameStreamBody = str_repeat('WordPress late-overrun block body ', 4);
$allocationLimitBytes = 128;
$oversizedLooseHeader = "blob 4096\0";
$truncatedHeaderStorage = "blob 100\0" . str_repeat('A', 100);

return [
    'blockBlobBody' => $blockBlobBody,
    'emptyBlobBody' => $emptyBlobBody,
    'expectedLooseHeader' => $looseHeader,
    'positiveSizeLooseHeader' => $positiveSizeLooseHeader,
    'positiveSizeLooseHeaderOid' => hash('sha1', $positiveSizeLooseHeader . $blockBlobBody),
    'zeroPaddedSizeLooseHeader' => $zeroPaddedSizeLooseHeader,
    'zeroPaddedSizeLooseHeaderOid' => hash('sha1', $zeroPaddedSizeLooseHeader . $blockBlobBody),
    'lfSizeLooseHeader' => $lfSizeLooseHeader,
    'lfSizeLooseHeaderOid' => hash('sha1', $lfSizeLooseHeader . $blockBlobBody),
    'negativeZeroSizeLooseHeader' => $negativeZeroSizeLooseHeader,
    'negativeZeroSizeLooseHeaderOid' => hash('sha1', $negativeZeroSizeLooseHeader . $emptyBlobBody),
    'emptyBlobOid' => hash('sha1', $canonicalEmptyBlobHeader . $emptyBlobBody),
    'lateSameStreamBody' => $lateSameStreamBody,
    'lateSameStreamOid' => hash('sha1', 'blob ' . strlen($lateSameStreamBody) . "\0" . $lateSameStreamBody),
    'truncatedHeaderStorage' => $truncatedHeaderStorage,
    'truncatedHeaderOid' => str_repeat('7', 40),
    'expectedBlobOid' => hash('sha1', $looseHeader . $blockBlobBody),
    'expectedBlobSha256' => hash('sha256', $looseHeader . $blockBlobBody),
    'allocationLimitBytes' => $allocationLimitBytes,
    'oversizedLooseHeader' => $oversizedLooseHeader,
    'oversizedLooseObjectOid' => str_repeat('a', 40),
    'allocationLimitMessage' => "Loose object declared size 4096 exceeds allocation limit {$allocationLimitBytes} bytes",
    'wordpressUse' => 'A WordPress import or deployment tool can decode canonical, upstream-accepted signed-size, and zero-padded loose object headers for block-content blobs, reject LF-tailed loose-object size headers before trusting advertised sizes, reject oversized loose-object declarations before allocating them, reject truncated first-window compressed headers before trusting advertised sizes, and ignore trailing compressed bytes or late same-stream overrun bytes after the declared object body without invoking git cat-file.',
];
