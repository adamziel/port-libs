<?php

declare(strict_types=1);

$blockBlobBody = "<!-- wp:paragraph -->\n"
    . "<p>Native PHP loose object parsing for a WordPress block export.</p>\n"
    . "<!-- /wp:paragraph -->\n";
$looseHeader = 'blob ' . strlen($blockBlobBody) . "\0";
$positiveSizeLooseHeader = 'blob +' . strlen($blockBlobBody) . "\0";
$allocationLimitBytes = 128;
$oversizedLooseHeader = "blob 4096\0";

return [
    'blockBlobBody' => $blockBlobBody,
    'expectedLooseHeader' => $looseHeader,
    'positiveSizeLooseHeader' => $positiveSizeLooseHeader,
    'positiveSizeLooseHeaderOid' => hash('sha1', $positiveSizeLooseHeader . $blockBlobBody),
    'expectedBlobOid' => hash('sha1', $looseHeader . $blockBlobBody),
    'expectedBlobSha256' => hash('sha256', $looseHeader . $blockBlobBody),
    'allocationLimitBytes' => $allocationLimitBytes,
    'oversizedLooseHeader' => $oversizedLooseHeader,
    'oversizedLooseObjectOid' => str_repeat('a', 40),
    'allocationLimitMessage' => "Loose object declared size 4096 exceeds allocation limit {$allocationLimitBytes} bytes",
    'wordpressUse' => 'A WordPress import or deployment tool can decode canonical and upstream-accepted positive-size loose object headers for block-content blobs and reject oversized loose-object declarations before allocating them, without invoking git cat-file.',
];
