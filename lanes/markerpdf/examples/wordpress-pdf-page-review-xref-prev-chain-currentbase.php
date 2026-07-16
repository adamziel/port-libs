<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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

$rows = ''
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, $offsets['5:0:4'], 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0);
$compressedRows = gzcompress($rows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress current xref stream rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [1 5 10 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
$pageReview = $pageReviews[0] ?? [];
$pageFile = $pageReview['page_associated_files'][0] ?? [];
$encodedReview = json_encode($pageReviews, JSON_UNESCAPED_SLASHES) ?: '';

$currentSelected = $lines === ['Current page review Prev page']
    && ($pageReview['piece_info']['WPImporter']['private']['BatchId'] ?? null) === 'current-prev-page-review'
    && ($pageFile['filename'] ?? null) === 'current-page-review.xml'
    && ($pageFile['checksum_matches'] ?? null) === true;
$staleExcluded = !str_contains($plainText, 'Stale page review Prev page')
    && !str_contains($encodedReview, 'stale-page-review.xml')
    && !str_contains($encodedReview, 'stale-prev-page-review')
    && !str_contains($encodedReview, $stalePayload)
    && !str_contains($encodedReview, $currentPayload);

if (!$currentSelected || !$staleExcluded) {
    throw new RuntimeException('Expected page review metadata to use current xref Prev chain objects and hide payload bytes.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-page-review-xref-prev-chain-currentbase ' . $htmlJson([
    'source' => 'native-pdf-xref-prev-chain-page-review',
    'support_component' => 'native-pdf-page-property-xref-prev-repair',
    'native_boundary' => 'damaged same-generation current xref rows repair page PieceInfo and page associated-file review metadata before WordPress rendering',
    'paragraphs' => $lines,
    'page_review_count' => count($pageReviews),
    'pieceinfo_batch_id' => $pageReview['piece_info']['WPImporter']['private']['BatchId'] ?? null,
    'associated_filename' => $pageFile['filename'] ?? null,
    'associated_checksum_matches' => $pageFile['checksum_matches'] ?? null,
    'associated_payload_content_omitted' => is_array($pageFile) && !array_key_exists('content', $pageFile),
    'previous_xref_offset' => $previousXrefOffset,
    'current_xref_offset' => $currentXrefOffset,
    'current_import_kept' => $currentSelected,
    'stale_prev_review_excluded' => $staleExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:page-review ' . $htmlJson([
    'pnum' => $pageReview['pnum'] ?? null,
    'page_object' => $pageReview['page_object'] ?? null,
    'piece_info' => $pageReview['piece_info'] ?? [],
    'page_associated_files' => array_map(static fn (array $file): array => [
        'name' => $file['name'] ?? null,
        'filename' => $file['filename'] ?? null,
        'description' => $file['description'] ?? null,
        'relationship' => $file['relationship'] ?? null,
        'mime_type' => $file['mime_type'] ?? null,
        'size' => $file['size'] ?? null,
        'declared_size' => $file['declared_size'] ?? null,
        'content_sha256' => $file['content_sha256'] ?? null,
        'checksum_matches' => $file['checksum_matches'] ?? null,
    ], $pageReview['page_associated_files'] ?? []),
]) . " -->\n";
