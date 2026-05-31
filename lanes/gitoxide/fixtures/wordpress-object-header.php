<?php

declare(strict_types=1);

$blockBlobBody = "<!-- wp:paragraph -->\n"
    . "<p>Native PHP loose object parsing for a WordPress block export.</p>\n"
    . "<!-- /wp:paragraph -->\n";
$looseHeader = 'blob ' . strlen($blockBlobBody) . "\0";
$positiveSizeLooseHeader = 'blob +' . strlen($blockBlobBody) . "\0";

return [
    'blockBlobBody' => $blockBlobBody,
    'expectedLooseHeader' => $looseHeader,
    'positiveSizeLooseHeader' => $positiveSizeLooseHeader,
    'positiveSizeLooseHeaderOid' => hash('sha1', $positiveSizeLooseHeader . $blockBlobBody),
    'expectedBlobOid' => hash('sha1', $looseHeader . $blockBlobBody),
    'expectedBlobSha256' => hash('sha256', $looseHeader . $blockBlobBody),
    'wordpressUse' => 'A WordPress import or deployment tool can decode canonical and upstream-accepted positive-size loose object headers for block-content blobs without invoking git cat-file.',
];
