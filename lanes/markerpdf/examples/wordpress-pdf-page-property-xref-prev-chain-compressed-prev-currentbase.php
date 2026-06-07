<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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
        throw new RuntimeException('Unable to compress page-property compressed Prev helper smoke object stream.');
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
    throw new RuntimeException('Unable to compress page-property compressed Prev xref rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 91 /Root 1 0 R /Prev 30 0 R /Index [1 4 10 2 30 1 90 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "30 0 obj\n999999\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$reviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReviews = json_encode($reviews, JSON_UNESCAPED_SLASHES) ?: '';
$firstReview = $reviews[0] ?? [];
$associatedFile = $firstReview['page_associated_files'][0] ?? [];

echo '<!-- markerpdf-page-property-xref-prev-chain-compressed-prev-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-page-property-xref-prev-chain-compressed-prev-currentbase',
    'source' => 'native-pdf-xref-stream-prev-chain-compressed-helper-page-review-selection',
    'current_page_review_selected' => ($firstReview['piece_info']['WPImporter']['private']['BatchId'] ?? null) === 'current-compressed-prev-page-review',
    'current_associated_file_selected' => ($associatedFile['filename'] ?? null) === 'current-compressed-prev.xml',
    'associated_payload_review_only' => is_array($associatedFile) && !array_key_exists('content', $associatedFile),
    'compressed_prev_helper_selected' => str_contains($pdf, '/Prev 30 0 R') && $previousXrefOffset < $prevHelperCarrierOffset && $prevHelperCarrierOffset < $currentXrefOffset,
    'stale_page_review_excluded' => !str_contains($encodedReviews, 'stale-compressed-prev-page-review'),
    'stale_associated_file_excluded' => !str_contains($encodedReviews, 'stale-compressed-prev.xml'),
    'current_text_selected' => str_contains($plainText, 'Current compressed Prev page review text'),
    'stale_text_excluded' => !str_contains($plainText, 'Stale compressed Prev page review text'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
