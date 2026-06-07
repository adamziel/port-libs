<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageReviewXrefPrevChainCompressedPrevCurrentBasePdf = static function (): array {
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);
    $objectStream = static function (array $members): array {
        $headerPairs = [];
        $memberIndexes = [];
        $objectData = '';
        foreach ($members as $objectNumber => $body) {
            $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
            $memberIndexes[$objectNumber] = count($memberIndexes);
            $objectData .= $body . "\n";
        }

        $header = implode(' ', $headerPairs);
        $plain = $header . "\n" . $objectData;
        $compressed = gzcompress($plain);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress page-review compressed Prev helper object stream.');
        }

        return [
            'first' => strlen($header) + 1,
            'indexes' => $memberIndexes,
            'content' => $compressed,
            'count' => count($members),
        ];
    };

    $stalePayload = '<wp-export><post id="stale-compressed-prev-page-review"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-compressed-prev-page-review"/></wp-export>';
    $currentChecksum = hash('md5', $currentPayload);
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale compressed Prev page review text) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current compressed Prev page review text) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation . ':' . count($offsets)] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) >>');
    $stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /PieceInfo << /WPImporter << /Private << /BatchId (stale-compressed-prev-page-review) /NeedsReview false >> >> >> /AF [10 0 R] /Contents 4 0 R >>');
    $staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-compressed-prev.xml) /Desc (Stale compressed Prev page review source) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $staleEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 12\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($staleCatalogOffset)
        . $xrefTableRow($stalePagesOffset)
        . $xrefTableRow($stalePageOffset)
        . $xrefTableRow($staleContentOffset)
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($staleFileSpecOffset)
        . $xrefTableRow($staleEmbeddedFileOffset)
        . "trailer\n<< /Size 12 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /PieceInfo << /WPImporter << /LastModified (D:20260607044613Z) /Private << /BatchId (current-compressed-prev-page-review) /NeedsReview true /Reviewer (editor) >> >> >> /AF [10 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(10, 0, '<< /Type /Filespec /F (current-compressed-prev.xml) /Desc (Current compressed Prev page review source) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . '> >> /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $prevHelperStream = $objectStream([30 => (string) $previousXrefOffset]);
    $prevHelperCarrierOffset = $addObject(90, 0, '<< /Type /ObjStm /N ' . $prevHelperStream['count'] . ' /First ' . $prevHelperStream['first'] . ' /Filter /FlateDecode /Length ' . strlen($prevHelperStream['content']) . " >>\nstream\n{$prevHelperStream['content']}\nendstream");

    $currentRows = ''
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(1, 0, 0)
        . $xrefStreamRow(2, 90, $prevHelperStream['indexes'][30])
        . $xrefStreamRow(1, $prevHelperCarrierOffset, 0);
    $compressedRows = gzcompress($currentRows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress page-review compressed Prev xref rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 91 /Root 1 0 R /Prev 30 0 R /Index [1 4 10 2 30 1 90 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n"
        . "30 0 obj\n999999\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return [$pdf, $currentPayload, $stalePayload, $previousXrefOffset, $currentXrefOffset, $prevHelperCarrierOffset];
};

return [
    'repairs page review metadata when xref-stream Prev is a compressed numeric helper' => static function (
        TestRunner $t
    ) use ($pageReviewXrefPrevChainCompressedPrevCurrentBasePdf): void {
        [$pdf, $currentPayload, $stalePayload, $previousXrefOffset, $currentXrefOffset, $prevHelperCarrierOffset] = $pageReviewXrefPrevChainCompressedPrevCurrentBasePdf();

        $reviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedReviews = json_encode($reviews, JSON_UNESCAPED_SLASHES) ?: '';

        $t->same(1, count($reviews), 'page review follows compressed /Prev helper before repairing zero-offset current rows');
        $review = $reviews[0];
        $t->same(3, $review['page_object']);
        $t->same('current-compressed-prev-page-review', $review['piece_info']['WPImporter']['private']['BatchId'] ?? null);
        $t->same(true, $review['piece_info']['WPImporter']['private']['NeedsReview'] ?? null);
        $t->same('D:20260607044613Z', $review['piece_info']['WPImporter']['last_modified'] ?? null);
        $t->same(1, count($review['page_associated_files'] ?? []));
        $file = $review['page_associated_files'][0];
        $t->same('current-compressed-prev.xml', $file['filename'] ?? null);
        $t->same('Current compressed Prev page review source', $file['description'] ?? null);
        $t->same('Source', $file['relationship'] ?? null);
        $t->same(strlen($currentPayload), $file['declared_size'] ?? null);
        $t->same(hash('md5', $currentPayload), $file['checksum'] ?? null);
        $t->same(hash('md5', $currentPayload), $file['computed_checksum'] ?? null);
        $t->same(true, $file['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('content', $file));

        $t->true(str_contains($plainText, 'Current compressed Prev page review text'));
        $t->true(!str_contains($plainText, 'Stale compressed Prev page review text'));
        $t->true(str_contains($pdf, '/Prev 30 0 R'));
        $t->true(str_contains($pdf, '/Type /ObjStm'));
        $t->true($previousXrefOffset < $prevHelperCarrierOffset);
        $t->true($prevHelperCarrierOffset < $currentXrefOffset);
        $t->true(str_contains($pdf, "30 0 obj\n999999\nendobj"));
        $t->true(!str_contains($encodedReviews, 'stale-compressed-prev-page-review'));
        $t->true(!str_contains($encodedReviews, 'stale-compressed-prev.xml'));
        $t->true(!str_contains($encodedReviews, $stalePayload));
        $t->true(!str_contains($encodedReviews, $currentPayload));
    },
];
