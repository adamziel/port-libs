<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageReviewXrefPrevChainCurrentBasePdf = static function (): array {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale page review Prev page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current page review Prev page) Tj ET';
    $stalePayload = '<wp-export><post id="stale-page-review-prev"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-page-review-prev"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation . ':' . count($offsets)] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /PieceInfo << /WPImporter << /Private << /BatchId (stale-prev-page-review) >> >> >> /AF [10 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(10, 0, '<< /Type /Filespec /F (stale-page-review.xml) /Desc (Stale page review source) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 12\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:0:0'])
        . $xrefTableRow($offsets['2:0:1'])
        . $xrefTableRow($offsets['3:0:2'])
        . $xrefTableRow($offsets['4:0:3'])
        . $xrefTableRow($offsets['5:0:4'])
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($offsets['10:0:5'])
        . $xrefTableRow($offsets['11:0:6'])
        . "trailer\n<< /Size 12 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /PieceInfo << /WPImporter << /LastModified (D:20260605141717Z) /Private << /BatchId (current-prev-page-review) /NeedsReview true >> >> >> /AF [10 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(10, 0, '<< /Type /Filespec /F (current-page-review.xml) /Desc (Current page review source) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . '> /ModDate (D:20260605141717Z) >> /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentRows = ''
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(1, $offsets['5:0:4'], 0)
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(1, 0, 0);
    $compressedRows = gzcompress($currentRows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress page review xref Prev chain rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [1 5 10 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return [$pdf, $currentPayload, $stalePayload, $previousXrefOffset, $currentXrefOffset];
};

return [
    'repairs page review metadata through xref Prev current update rows' => static function (
        TestRunner $t
    ) use ($pageReviewXrefPrevChainCurrentBasePdf): void {
        [$pdf, $currentPayload, $stalePayload, $previousXrefOffset, $currentXrefOffset] = $pageReviewXrefPrevChainCurrentBasePdf();
        $pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $textExtractor = new PdfTextExtractor();
        $plainText = $textExtractor->extractPlainText($pdf);
        $encodedReviews = json_encode($pageReviews, JSON_UNESCAPED_SLASHES);

        $t->same(['Current page review Prev page'], $textExtractor->extractTextLines($pdf));
        $t->same('Current page review Prev page', $plainText);
        $t->same(1, count($pageReviews));

        $page = $pageReviews[0];
        $t->same(0, $page['pnum']);
        $t->same(3, $page['page_object']);
        $t->same('current-prev-page-review', $page['piece_info']['WPImporter']['private']['BatchId']);
        $t->same(true, $page['piece_info']['WPImporter']['private']['NeedsReview']);
        $t->same('D:20260605141717Z', $page['piece_info']['WPImporter']['last_modified']);
        $t->same(1, count($page['page_associated_files']));

        $file = $page['page_associated_files'][0];
        $t->same('page_associated_files', $file['source']);
        $t->same('current-page-review.xml', $file['filename']);
        $t->same('Current page review source', $file['description']);
        $t->same('Source', $file['relationship']);
        $t->same('text/xml', $file['mime_type']);
        $t->same(10, $file['file_spec_object']);
        $t->same(11, $file['embedded_file_object']);
        $t->same(strlen($currentPayload), $file['size']);
        $t->same(strlen($currentPayload), $file['declared_size']);
        $t->same(hash('sha256', $currentPayload), $file['content_sha256']);
        $t->same(hash('md5', $currentPayload), $file['checksum']);
        $t->same(hash('md5', $currentPayload), $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);
        $t->same('D:20260605141717Z', $file['modified_at']);
        $t->same(false, array_key_exists('content', $file));

        $t->true($previousXrefOffset < $currentXrefOffset);
        $t->true(str_contains($pdf, '/Prev '));
        $t->true(str_contains($pdf, '/Type /XRef'));
        $t->true(is_string($encodedReviews) && !str_contains($encodedReviews, 'stale-prev-page-review'));
        $t->true(is_string($encodedReviews) && !str_contains($encodedReviews, 'stale-page-review.xml'));
        $t->true(is_string($encodedReviews) && !str_contains($encodedReviews, $stalePayload));
        $t->true(is_string($encodedReviews) && !str_contains($encodedReviews, $currentPayload));
        $t->true(!str_contains($plainText, 'Stale page review Prev page'));
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
