<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$packXrefRows = static function (array $ranges, array $entries): string {
    $rows = '';
    foreach ($ranges as $range) {
        [$first, $count] = $range;
        for ($index = 0; $index < $count; $index++) {
            $objectNumber = $first + $index;
            $entry = $entries[$objectNumber] ?? ['type' => 0, 'field2' => 0, 'field3' => 255];
            $rows .= chr((int) $entry['type'])
                . pack('N', (int) $entry['field2'])
                . chr((int) $entry['field3']);
        }
    }

    return $rows;
};

$pdf = "%PDF-1.7\n";
$previousOffsets = [];
$addPreviousObject = static function (int $objectNumber, string $body) use (&$pdf, &$previousOffsets): void {
    $previousOffsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$previousText = 'BT /F1 12 Tf 72 720 Td (Stale previous page review text) Tj ET';
$addPreviousObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
$addPreviousObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addPreviousObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R /PieceInfo << /WPImport << /LastModified (D:20260604090000Z) /Private << /Template (stale-prev-page) /NeedsReview false >> >> >> >>');
$addPreviousObject(4, "<< /Length " . strlen($previousText) . " >>\nstream\n{$previousText}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n0 5\n"
    . "0000000000 65535 f \n"
    . sprintf("%010d 00000 n \n", $previousOffsets[1])
    . sprintf("%010d 00000 n \n", $previousOffsets[2])
    . sprintf("%010d 00000 n \n", $previousOffsets[3])
    . sprintf("%010d 00000 n \n", $previousOffsets[4])
    . "trailer\n<< /Size 21 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$currentOffsets = [];
$addCurrentObject = static function (int $objectNumber, string $body) use (&$pdf, &$currentOffsets): void {
    $currentOffsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$currentText = 'BT /F1 12 Tf 72 720 Td (Current page property review text) Tj ET';
$sourcePayload = '<wp-export><post id="xref-current-page"/></wp-export>';
$decoyPayload = '<wp-export><post id="post-xref-decoy"/></wp-export>';
$addCurrentObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R /PieceInfo << /WPImport << /LastModified (D:20260605130334Z) /Private << /Template (current-xref-page) /NeedsReview true /BatchId (xref-prev-current) >> >> >> /AF [10 0 R] >>');
$addCurrentObject(4, "<< /Length " . strlen($currentText) . " >>\nstream\n{$currentText}\nendstream");
$addCurrentObject(10, '<< /Type /Filespec /F (current-source.xml) /Desc (Current source export) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$addCurrentObject(11, "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream");

$currentXrefOffset = strlen($pdf);
$currentXrefRows = $packXrefRows([[3, 2], [10, 2]], [
    3 => ['type' => 1, 'field2' => $currentOffsets[3], 'field3' => 0],
    4 => ['type' => 1, 'field2' => $currentOffsets[4], 'field3' => 0],
    10 => ['type' => 1, 'field2' => $currentOffsets[10], 'field3' => 0],
    11 => ['type' => 1, 'field2' => $currentOffsets[11], 'field3' => 0],
]);
$currentCompressed = gzcompress($currentXrefRows);
if (!is_string($currentCompressed)) {
    throw new RuntimeException('Unable to compress current page-property xref stream.');
}
$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 22 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [3 2 10 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentCompressed) . " >>\n"
    . "stream\n{$currentCompressed}\nendstream\nendobj\n"
    . "3 0 obj\n"
    . "<< /Type /Page /Parent 2 0 R /Contents 4 0 R /PieceInfo << /WPImport << /LastModified (D:20260605000000Z) /Private << /Template (post-xref-decoy-page) /NeedsReview false >> >> >> /AF [10 0 R] >>\n"
    . "endobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (decoy-source.xml) /Desc (Post-xref decoy source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($decoyPayload) . " >> /Length " . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$pages = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedPages = json_encode($pages, JSON_UNESCAPED_SLASHES);
$firstPage = $pages[0] ?? [];
$associated = $firstPage['page_associated_files'][0] ?? [];

echo '<!-- markerpdf-page-property-xref-prev-chain-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-page-property-xref-prev-chain-currentbase',
    'source' => 'native-pdf-xref-stream-prev-chain-page-property-selection',
    'current_page_review_selected' => ($firstPage['piece_info']['WPImport']['private']['Template'] ?? null) === 'current-xref-page',
    'current_associated_file_selected' => ($associated['filename'] ?? null) === 'current-source.xml',
    'associated_payload_review_only' => !array_key_exists('content', $associated),
    'post_xref_decoy_excluded' => is_string($encodedPages)
        && !str_contains($encodedPages, 'post-xref-decoy-page')
        && !str_contains($encodedPages, 'decoy-source.xml'),
    'previous_page_review_excluded' => is_string($encodedPages) && !str_contains($encodedPages, 'stale-prev-page'),
    'current_text_selected' => str_contains($plainText, 'Current page property review text'),
    'stale_text_excluded' => !str_contains($plainText, 'Stale previous page review text'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
