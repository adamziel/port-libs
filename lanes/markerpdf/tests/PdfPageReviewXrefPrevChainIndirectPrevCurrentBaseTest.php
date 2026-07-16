<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'repairs page review metadata through indirect xref Prev current update rows' => static function (
        TestRunner $t
    ): void {
    $xrefStreamRow = static function (int $type, int $fieldTwo, int $fieldThree): string {
        return chr($type) . pack('N', $fieldTwo) . chr($fieldThree);
    };

    $stalePayload = 'STALE_PAGE_REVIEW_BYTES';
    $currentPayload = 'CURRENT_PAGE_REVIEW_BYTES';
    $currentChecksum = hash('md5', $currentPayload);

    $header = "%PDF-1.7\n";
    $body = $header;

    $baseOffsets = [];
    $writeBaseObject = static function (int $objectNumber, string $objectBody) use (&$body, &$baseOffsets): void {
        $baseOffsets[$objectNumber] = strlen($body);
        $body .= sprintf("%d 0 obj\n%s\nendobj\n", $objectNumber, $objectBody);
    };

    $writeBaseObject(1, "<< /Type /Catalog /Pages 2 0 R >>");
    $writeBaseObject(2, "<< /Type /Pages /Kids [3 0 R] /Count 1 >>");
    $writeBaseObject(3, "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Contents 4 0 R /PieceInfo << /WPReview << /Private << /BatchId (stale-review) /NeedsReview false >> >> >> /AF [10 0 R] >>");
    $writeBaseObject(4, "<< /Length 50 >>\nstream\nBT /F1 12 Tf 36 72 Td (stale review text) Tj ET\nendstream");
    $writeBaseObject(10, "<< /Type /Filespec /F (stale-review.txt) /UF (stale-review.txt) /EF << /F 11 0 R >> /Desc (stale page review attachment) /AFRelationship /Data >>");
    $writeBaseObject(11, "<< /Length " . strlen($stalePayload) . " /Subtype /text#2Fplain >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($body);
    $body .= "xref\n0 12\n";
    $body .= "0000000000 65535 f \n";
    for ($objectNumber = 1; $objectNumber <= 11; $objectNumber++) {
        $offset = $baseOffsets[$objectNumber] ?? 0;
        $body .= sprintf("%010d 00000 %s \n", $offset, $offset > 0 ? 'n' : 'f');
    }
    $body .= "trailer\n<< /Size 12 /Root 1 0 R >>\nstartxref\n" . $previousXrefOffset . "\n%%EOF\n";

    $currentOffsets = [];
    $writeCurrentObject = static function (int $objectNumber, string $objectBody) use (&$body, &$currentOffsets): void {
        $currentOffsets[$objectNumber] = strlen($body);
        $body .= sprintf("%d 0 obj\n%s\nendobj\n", $objectNumber, $objectBody);
    };

    $writeCurrentObject(1, "<< /Type /Catalog /Pages 2 0 R >>");
    $writeCurrentObject(2, "<< /Type /Pages /Kids [3 0 R] /Count 1 >>");
    $writeCurrentObject(3, "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Contents 4 0 R /PieceInfo << /WPReview << /Private << /BatchId (current-indirect-prev-page-review) /NeedsReview true /Reviewer (editor) >> >> >> /AF [10 0 R] >>");
    $writeCurrentObject(4, "<< /Length 52 >>\nstream\nBT /F1 12 Tf 36 72 Td (current review text) Tj ET\nendstream");
    $writeCurrentObject(10, "<< /Type /Filespec /F (current-review.txt) /UF (current-review.txt) /EF << /F 11 0 R >> /Desc (current page review attachment) /AFRelationship /Data >>");
    $writeCurrentObject(11, "<< /Length " . strlen($currentPayload) . " /Subtype /text#2Fplain /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> >> >>\nstream\n{$currentPayload}\nendstream");

    $prevHelperOffset = strlen($body);
    $body .= "30 0 obj\n" . $previousXrefOffset . "\nendobj\n";

    $currentRows = '';
    foreach ([1, 2, 3, 4, 10, 11] as $_objectNumber) {
        $currentRows .= $xrefStreamRow(1, 0, 0);
    }
    $currentRows .= $xrefStreamRow(1, $prevHelperOffset, 0);

    $encodedRows = gzcompress($currentRows);
    if (! is_string($encodedRows)) {
        throw new RuntimeException('Unable to compress xref stream rows.');
    }

    $currentXrefOffset = strlen($body);
    $body .= "40 0 obj\n";
    $body .= "<< /Type /XRef /Size 41 /Root 1 0 R /Prev 30 0 R /Index [1 4 10 2 30 1] /W [1 4 1] /Filter /FlateDecode /Length " . strlen($encodedRows) . " >>\n";
    $body .= "stream\n" . $encodedRows . "\nendstream\nendobj\n";
    $body .= "startxref\n" . $currentXrefOffset . "\n%%EOF\n";

    $t->true(str_contains($body, '/Prev 30 0 R'), 'fixture stores current xref /Prev as an indirect helper');
    $t->true($previousXrefOffset < $prevHelperOffset, 'indirect /Prev helper object is appended after previous xref');
    $t->true($prevHelperOffset < $currentXrefOffset, 'indirect /Prev helper object is available before current xref stream');

    $reviewRows = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($body);
    $t->same(1, count($reviewRows), 'page review extraction follows indirect /Prev before repairing current xref rows');

    $review = $reviewRows[0];
    $t->same(3, $review['page_object'], 'current page object survives zero-offset current xref rows');
    $t->same('current-indirect-prev-page-review', $review['piece_info']['WPReview']['private']['BatchId'] ?? null, 'current page PieceInfo wins over stale base review data');
    $t->same(true, $review['piece_info']['WPReview']['private']['NeedsReview'] ?? null, 'current page PieceInfo boolean value is preserved');
    $t->same('current-review.txt', $review['page_associated_files'][0]['filename'] ?? null, 'current page-associated file wins over stale attachment');
    $t->same('Data', $review['page_associated_files'][0]['relationship'] ?? null, 'current page-associated file relationship is preserved');
    $t->same($currentChecksum, $review['page_associated_files'][0]['checksum'] ?? null, 'current embedded file checksum is resolved from current update');
    $t->same(strlen($currentPayload), $review['page_associated_files'][0]['size'] ?? null, 'current embedded file stream length is preserved without importing payload bytes');

    $encodedReview = json_encode($reviewRows, JSON_THROW_ON_ERROR);
    $t->true(! str_contains($encodedReview, 'stale-review'), 'stale base page-review PieceInfo is not selected');
    $t->true(! str_contains($encodedReview, 'stale-review.txt'), 'stale base page-associated file is not selected');
    $t->true(! str_contains($encodedReview, 'STALE_PAGE_REVIEW_BYTES'), 'stale embedded payload bytes stay hidden');
    $t->true(! str_contains($encodedReview, 'CURRENT_PAGE_REVIEW_BYTES'), 'current embedded payload bytes stay hidden');

    $plainText = (new PdfTextExtractor())->extractPlainText($body);
    $t->true(str_contains($plainText, 'current review text'), 'text extraction also follows the indirect /Prev helper');
    $t->true(! str_contains($plainText, 'stale review text'), 'text extraction suppresses stale base page content');
    },
];
