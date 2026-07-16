<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$introContent = 'BT /F1 12 Tf 72 720 Td (NUL outline intro body) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 700 Td (NUL outline appendix body) Tj ET';

$members = [
    5 => '<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>',
    6 => '<< /Title (NUL Outline Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R >>',
    7 => '<< /Title (NUL Outline Appendix) /Parent 5 0 R /Prev 6 0 R /A 8 0 R >>',
    8 => '<< /S /GoTo /D [4 0 R /XYZ 144 null 0] /Next 9 0 R >>',
    9 => '<< /S /URI /URI (https://example.com/nul-outline-review) >>',
];

$objectData = '';
$headerParts = [];
$memberIndexes = [];
$memberIndex = 0;
foreach ($members as $objectNumber => $body) {
    if ($objectData !== '') {
        $objectData .= "\0";
    }
    $headerParts[] = $objectNumber . "\0" . strlen($objectData);
    $memberIndexes[$objectNumber] = $memberIndex++;
    $objectData .= $body;
}

$header = implode("\0", $headerParts) . "\0";
$compressedObjectStream = gzcompress($header . $objectData);
if (!is_string($compressedObjectStream)) {
    throw new RuntimeException('Unable to compress NUL-whitespace outline object stream smoke.');
}

$pdf = "%PDF-1.7\n";
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
$addObject(20, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . strlen($header) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
$addObject(30, "<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream");
$addObject(31, "<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream");

$xrefOffset = strlen($pdf);
$xrefRows = '';
for ($objectNumber = 0; $objectNumber <= 40; $objectNumber++) {
    if ($objectNumber === 0) {
        $xrefRows .= $xrefRow(0, 0);
        continue;
    }
    if (isset($memberIndexes[$objectNumber])) {
        $xrefRows .= $xrefRow(2, 20, $memberIndexes[$objectNumber]);
        continue;
    }
    if ($objectNumber === 40) {
        $xrefRows .= $xrefRow(1, $xrefOffset);
        continue;
    }
    if (isset($offsets[$objectNumber])) {
        $xrefRows .= $xrefRow(1, $offsets[$objectNumber]);
        continue;
    }

    $xrefRows .= $xrefRow(0, 0);
}

$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress NUL-whitespace outline xref stream smoke.');
}

$pdf .= "40 0 obj\n"
    . '<< /Type /XRef /Size 41 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$titles = array_column($toc, 'title');
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

if (($metadata['document_outline']['source'] ?? null) !== 'catalog_outlines') {
    throw new RuntimeException('Expected NUL-whitespace object-stream outline metadata.');
}

if ($titles !== ['NUL Outline Chapter', 'NUL Outline Appendix']) {
    throw new RuntimeException('Expected NUL-whitespace object-stream TOC titles.');
}

if (!str_contains($encodedNavigation, 'https://example.com/nul-outline-review')) {
    throw new RuntimeException('Expected NUL-whitespace object-stream action review metadata.');
}

foreach (['NUL Outline Chapter', 'NUL Outline Appendix', 'nul-outline-review'] as $hidden) {
    if (str_contains($plainText, $hidden)) {
        throw new RuntimeException('Expected outline metadata to stay out of visible WordPress text: ' . $hidden);
    }
}

echo '<!-- markerpdf-xref-object-stream-outline-null-whitespace-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'xref-selected PDF object streams treat NUL bytes as PDF whitespace in compressed outline headers and member boundaries',
    'support_component' => 'native-pdf-outline-object-stream-parser',
    'outline_count' => count($toc),
    'outline_titles' => $titles,
    'document_outline_source' => $metadata['document_outline']['source'] ?? null,
    'visible_text_excludes_outline_metadata' => true,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($toc as $item) {
    $title = htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<li data-marker-outline-page="' . (int) $item['page'] . '" data-marker-outline-view="' . htmlspecialchars($item['view_mode'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . $title
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
