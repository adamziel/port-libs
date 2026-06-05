<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfMetadataExtractor.php';
require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Current metadata offset-boundary page) Tj ET';
$fakeCatalog = '<< /Type /Catalog /Pages 2 0 R /Lang (zz-ZZ) >>';
$carrierBody = '<< /Type /Catalog /Pages 2 0 R /Note (' . $fakeCatalog . ') >>';
$objectData = $carrierBody . "\n";
$badOffset = strpos($objectData, $fakeCatalog);
if ($badOffset === false) {
    throw new RuntimeException('Unable to locate fake catalog inside object-stream literal string.');
}

$header = '12 0 1 ' . $badOffset;
$objectStream = gzcompress($header . "\n" . $objectData);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress metadata object-stream offset smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$addObject(4, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
$addObject(6, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

$xrefRows = '';
for ($objectNumber = 0; $objectNumber < 9; $objectNumber++) {
    if ($objectNumber === 0) {
        $xrefRows .= $xrefRow(0, 0);
        continue;
    }

    if ($objectNumber === 1) {
        $xrefRows .= $xrefRow(2, 6, 1);
        continue;
    }

    if ($objectNumber === 6) {
        $xrefRows .= $xrefRow(1, $offsets[6]);
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
    throw new RuntimeException('Unable to compress metadata object-stream xref smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "8 0 obj\n"
    . '<< /Type /XRef /Size 9 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$review = $textExtractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$catalogEntry = $entries[1] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

$smoke = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'xref-selected object-stream metadata members must start on token boundaries, not inside compressed literal strings',
    'uses_current_visible_page' => str_contains($plainText, 'Current metadata offset-boundary page'),
    'rejects_literal_offset_catalog_metadata' => !isset($metadata['language']) && !isset($metadata['catalog']['language']),
    'excludes_fake_catalog_language' => is_string($encodedMetadata) && !str_contains($encodedMetadata, 'zz-ZZ'),
    'invalid_member_offset_rejection_count' => $review['invalid_member_offset_rejection_count'] ?? null,
    'selection_policy' => $catalogEntry['selection_policy'] ?? null,
    'member_offset_token_boundary' => $catalogEntry['member_offset_token_boundary'] ?? null,
    'page_count' => $textExtractor->extractOutlineMetadata($pdf)['pages'],
];

foreach ([
    'uses_current_visible_page',
    'rejects_literal_offset_catalog_metadata',
    'excludes_fake_catalog_language',
] as $requiredFlag) {
    if (($smoke[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Metadata object-stream offset-boundary smoke failed: ' . $requiredFlag);
    }
}

if (($smoke['selection_policy'] ?? null) !== 'invalid_object_stream_member_offset') {
    throw new RuntimeException('Expected invalid object-stream metadata member offset review.');
}

echo '<!-- markerpdf-xref-object-stream-metadata-offset-boundary-currentbase-smoke ' . htmlspecialchars(json_encode(
    $smoke,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
