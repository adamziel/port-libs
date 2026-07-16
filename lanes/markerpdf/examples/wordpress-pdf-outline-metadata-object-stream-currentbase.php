<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$introContent = 'BT /F1 12 Tf 72 720 Td (Compressed outline WordPress intro body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (Compressed outline WordPress appendix body) Tj ET';
$members = [
    5 => '<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>',
    6 => '<< /Title (Compressed WordPress Outline Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R /C [0 .2 .4] /F 2 >>',
    7 => '<< /Title (Compressed WordPress Outline Appendix) /Parent 5 0 R /Prev 6 0 R /A 8 0 R >>',
    8 => '<< /S /GoTo /D [4 0 R /XYZ 144 null 0] /Next 9 0 R >>',
    9 => '<< /S /URI /URI (https://example.com/compressed-wordpress-outline-review) >>',
];

$headerParts = [];
$objectData = '';
foreach ($members as $objectNumber => $body) {
    $headerParts[] = $objectNumber . ' ' . strlen($objectData);
    $objectData .= $body . "\n";
}

$header = implode(' ', $headerParts);
$objectStreamPayload = $header . "\n" . $objectData;
$compressedObjectStream = gzcompress($objectStreamPayload);
if (!is_string($compressedObjectStream)) {
    throw new RuntimeException('Unable to compress outline object stream smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>');
$addObject(4, '<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>');
$addObject(20, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
$addObject(30, "<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream");
$addObject(31, "<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream");

$xrefOffset = strlen($pdf);
$xrefRows = '';
for ($objectNumber = 0; $objectNumber <= 40; $objectNumber++) {
    if ($objectNumber === 0) {
        $xrefRows .= $xrefRow(0, 0);
        continue;
    }

    if ($objectNumber >= 5 && $objectNumber <= 9) {
        $xrefRows .= $xrefRow(2, 20, $objectNumber - 5);
        continue;
    }

    if ($objectNumber === 40) {
        $xrefRows .= $xrefRow(1, $xrefOffset);
        continue;
    }

    $xrefRows .= isset($offsets[$objectNumber])
        ? $xrefRow(1, $offsets[$objectNumber])
        : $xrefRow(0, 0);
}

$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress outline object-stream xref smoke fixture.');
}

$pdf .= "40 0 obj\n"
    . '<< /Type /XRef /Size 41 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
$toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

$titles = array_column($navigation['outline'] ?? [], 'title');
if ($titles !== ['Compressed WordPress Outline Chapter', 'Compressed WordPress Outline Appendix']) {
    throw new RuntimeException('Expected compressed outline rows in WordPress navigation review metadata.');
}

if (
    array_column($navigation['outline_action_review_actions'] ?? [], 'action_type') !== ['GoTo', 'URI']
    || str_contains($plainText, 'Compressed WordPress Outline Chapter')
    || str_contains($plainText, 'compressed-wordpress-outline-review')
    || !is_string($encodedNavigation)
    || !str_contains($encodedNavigation, 'https://example.com/compressed-wordpress-outline-review')
) {
    throw new RuntimeException('Expected compressed outline actions to stay review-only and out of visible body text.');
}

echo '<!-- markerpdf-outline-metadata-object-stream-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-outline-metadata-object-stream-currentbase',
    'support_component' => 'native-pdf-outline-object-stream-review',
    'native_boundary' => 'PDF 1.5 object-stream outline dictionaries resolve into WordPress navigation metadata without body-text leakage',
    'outline_root_object' => $metadata['document_outline']['outline_root_object'] ?? null,
    'outline_titles' => $titles,
    'toc_view_modes' => array_column($toc, 'view_mode'),
    'outline_action_types' => array_column($navigation['outline_action_review_actions'] ?? [], 'action_type'),
    'outline_action_safeties' => array_column($navigation['outline_action_review_actions'] ?? [], 'safety'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Compressed WordPress Outline Chapter')
        && !str_contains($plainText, 'compressed-wordpress-outline-review'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:navigation -->\n<nav aria-label=\"Compressed PDF outline review\"><ul>\n";
foreach ($navigation['outline'] ?? [] as $item) {
    echo '<li data-marker-outline-object="' . (int) ($item['outline_object'] ?? 0)
        . '" data-marker-outline-page="' . (int) (($item['page'] ?? 0) + 1)
        . '" data-marker-outline-view="' . htmlspecialchars((string) ($item['view_mode'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n\n";

foreach (explode("\n", $plainText) as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
