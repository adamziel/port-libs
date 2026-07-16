<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$visibleText = 'BT /F1 12 Tf 72 720 Td (Visible page property boundary text) Tj ET';
$hiddenText = 'BT /F1 12 Tf 72 680 Td (Offset boundary hidden page leak) Tj ET';
$sourcePayload = '<wp-export><post id="page-property-boundary"/></wp-export>';
$currentPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R '
    . '/PieceInfo << /WPImport << /LastModified (D:20260607082214Z) /Private << /BatchId (current-object-stream-page-review) /NeedsReview true >> >> >> '
    . '/AF [11 0 R] '
    . '/Note (literal decoy << /Type /Page /Parent 2 0 R /Contents 10 0 R /PieceInfo << /WPImport << /LastModified (D:20260607000000Z) /Private << /BatchId (offset-decoy-page-review) /NeedsReview false >> >> >> /AF [11 0 R] >> still literal) >>';
$decoyOffset = strpos($currentPage, '<< /Type /Page /Parent 2 0 R /Contents 10 0 R');
if ($decoyOffset === false) {
    throw new RuntimeException('Unable to locate page-property object-stream decoy offset.');
}

$objectData = $currentPage . "\n";
$header = '3 0 30 ' . $decoyOffset;
$objectStream = gzcompress($header . "\n" . $objectData);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress page-property object stream smoke fixture.');
}

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true /UserProperties false /Suspects false >> >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R 30 0 R] /Count 2 >>');
$addObject(4, "<< /Length " . strlen($visibleText) . " >>\nstream\n{$visibleText}\nendstream");
$addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(10, "<< /Length " . strlen($hiddenText) . " >>\nstream\n{$hiddenText}\nendstream");
$addObject(11, '<< /Type /Filespec /F (source.xml) /Desc (Original WordPress export) /AFRelationship /Source /EF << /F 12 0 R >> >>');
$addObject(12, "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream");
$addObject(20, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

$xrefRows = ''
    . $xrefRow(1, $offsets[1])
    . $xrefRow(1, $offsets[2])
    . $xrefRow(2, 20, 0)
    . $xrefRow(1, $offsets[4])
    . $xrefRow(1, $offsets[5])
    . $xrefRow(1, $offsets[10])
    . $xrefRow(1, $offsets[11])
    . $xrefRow(1, $offsets[12])
    . $xrefRow(1, $offsets[20])
    . $xrefRow(2, 20, 1);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress page-property xref stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "40 0 obj\n"
    . '<< /Type /XRef /Size 41 /Root 1 0 R /Index [1 5 10 3 20 1 30 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$propertyExtractor = new PdfPagePropertyExtractor();
$textExtractor = new PdfTextExtractor();
$pages = $propertyExtractor->extractPageReviewMetadata($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$lines = $textExtractor->extractTextLines($pdf);
$encodedPages = json_encode($pages, JSON_UNESCAPED_SLASHES) ?: '';
$page = $pages[0] ?? [];

$smoke = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'page review object-stream offsets must start at top-level member tokens before WordPress metadata import',
    'current_page_review_selected' => ($page['page_object'] ?? null) === 3
        && (($page['piece_info']['WPImport']['private']['BatchId'] ?? null) === 'current-object-stream-page-review'),
    'source_associated_file_preserved' => (($page['page_associated_files'][0]['filename'] ?? null) === 'source.xml')
        && (($page['page_associated_files'][0]['relationship'] ?? null) === 'Source'),
    'visible_text_preserved' => str_contains($plainText, 'Visible page property boundary text'),
    'decoy_page_review_excluded' => !str_contains($encodedPages, 'offset-decoy-page-review')
        && !str_contains($encodedPages, 'D:20260607000000Z'),
    'hidden_decoy_text_excluded' => !str_contains($plainText, 'Offset boundary hidden page leak'),
    'review_row_count' => count($pages),
];

foreach ([
    'current_page_review_selected',
    'source_associated_file_preserved',
    'visible_text_preserved',
    'decoy_page_review_excluded',
    'hidden_decoy_text_excluded',
] as $requiredFlag) {
    if (($smoke[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Page-property object-stream offset smoke failed: ' . $requiredFlag);
    }
}

echo '<!-- markerpdf-page-property-object-stream-offset-boundary-currentbase ' . htmlspecialchars(json_encode($smoke, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
