<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rootProfile = 'Current name-tree root ICC bytes';
$fileProfile = 'Current name-tree FileSpec ICC bytes';
$sourcePayload = '<wp-export><post id="192222"/></wp-export>';
$previewPayload = 'preview-bytes';
$privatePayload = 'BT /F1 12 Tf 72 720 Td (NameTree PieceInfo Private Leak) Tj ET';
$staleSourcePayload = '<wp-export><post id="stale-nametree-pieceinfo"/></wp-export>';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));

$rootProfileStream = gzcompress($rootProfile);
$fileProfileStream = gzcompress($fileProfile);
$privateStream = gzcompress($privatePayload);
if (!is_string($rootProfileStream) || !is_string($fileProfileStream) || !is_string($privateStream)) {
    throw new RuntimeException('Unable to compress name-tree PieceInfo smoke streams.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Current NameTree PieceInfo Body) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale NameTree PieceInfo Body) Tj ET';
$pdf = "%PDF-2.0\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /OutputIntents [9 0 R] /Names << /EmbeddedFiles 20 0 R >> >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(7, 0, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($rootProfileStream) . " >>\nstream\n{$rootProfileStream}\nendstream");
$addObject(8, 0, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($fileProfileStream) . " >>\nstream\n{$fileProfileStream}\nendstream");
$addObject(9, 0, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Current NameTree Root sRGB) /Info (Current name-tree root PDF/A) /DestOutputProfile 7 0 R >>');
$addObject(10, 0, '<< /Type /Filespec /F (legacy-migrate-source.xml) /UF (migrate-source.xml) /Desc (Current name-tree WordPress export) /AFRelationship /Source /Lang (en-US) /OutputIntents [13 0 R] /PieceInfo << /WPNameTree << /LastModified (D:20260602192222Z) /Private << /ManifestId (nt-piece-192222) /OutputIntents [13 0 R] /PrivateStream 16 0 R >> >> >> /EF << /F 11 0 R >> >>');
$addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($sourcePayload) . ' /CheckSum <' . $sourceChecksum . "> /ModDate (D:20260602192300Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream");
$addObject(13, 0, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Current NameTree FileSpec sRGB) /Info (Current attachment-local PDF/A) /DestOutputProfile 8 0 R >>');
$addObject(16, 0, '<< /Type /Metadata /Subtype /text#2Fplain /Filter /FlateDecode /Length ' . strlen($privateStream) . " >>\nstream\n{$privateStream}\nendstream");
$addObject(20, 0, '<< /Kids [21 0 R 22 0 R] >>');
$addObject(21, 0, '<< /Limits [(a) (mzz)] /Names [(migrate-source.xml) 10 0 R (z-out-of-limits.xml) 50 0 R] >>');
$addObject(22, 0, '<< /Limits [(n) (z)] /Names [(review-preview.pdf) 42 0 R] >>');
$addObject(42, 0, '<< /Type /Filespec /F (review-preview.pdf) /Desc (Current name-tree preview) /AFRelationship /Alternative /EF << /F 43 0 R >> >>');
$addObject(43, 0, '<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Length ' . strlen($previewPayload) . " >>\nstream\n{$previewPayload}\nendstream");
$addObject(50, 0, '<< /Type /Filespec /F (z-out-of-limits.xml) /Desc (Stale out-of-limits source) /AFRelationship /Source /EF << /F 51 0 R >> >>');
$addObject(51, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($staleSourcePayload) . " >>\nstream\n{$staleSourcePayload}\nendstream");
$addObject(60, 0, '<< /Title (Current NameTree PieceInfo Title) /Author (Current NameTree Author) /Producer (Current NameTree Producer) >>');

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber < 91; $objectNumber++) {
    if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 90)) {
        $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
        continue;
    }

    $rows .= pack('CNn', 1, $objectNumber === 90 ? $xrefOffset : $offsets[$objectNumber], 0);
}
$compressedXref = gzcompress($rows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress name-tree PieceInfo smoke xref stream.');
}

$pdf .= "90 0 obj\n"
    . '<< /Type /XRef /Size 91 /Root 1 0 R /Info 60 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /OutputIntents [9 0 R] /Names << /EmbeddedFiles 70 0 R >> >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (stale-piece-source.xml) /Desc (Stale NameTree PieceInfo source) /AFRelationship /Source /OutputIntents [13 0 R] /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($staleSourcePayload) . " >>\nstream\n{$staleSourcePayload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Stale NameTree FileSpec sRGB) /Info (Stale attachment-local PDF/A) /DestOutputProfile 8 0 R >>\nendobj\n"
    . "70 0 obj\n<< /Names [(stale-detached.xml) 10 0 R] >>\nendobj\n";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$embeddedFiles = $metadata['embedded_files'] ?? [];
$source = $embeddedFiles[0] ?? [];
$pieceInfo = $source['piece_info']['WPNameTree'] ?? [];
$provenance = $source['provenance_review'] ?? [];

if (($metadata['title'] ?? null) !== 'Current NameTree PieceInfo Title') {
    throw new RuntimeException('Expected current xref-selected Info title.');
}
if (($source['name_tree_name'] ?? null) !== 'migrate-source.xml') {
    throw new RuntimeException('Expected current name-tree FileSpec row.');
}
if (($pieceInfo['private']['ManifestId'] ?? null) !== 'nt-piece-192222') {
    throw new RuntimeException('Expected name-tree FileSpec PieceInfo review metadata.');
}
if (($provenance['pdfa_output_intents']['output_condition_identifiers'] ?? []) !== ['Current NameTree FileSpec sRGB']) {
    throw new RuntimeException('Expected FileSpec-local OutputIntent provenance.');
}
if ($plainText !== 'Current NameTree PieceInfo Body') {
    throw new RuntimeException('Expected current visible page text only.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, $sourcePayload)
    || str_contains($encoded, $privatePayload)
    || str_contains($encoded, 'z-out-of-limits.xml')
    || str_contains($encoded, 'stale-detached.xml')
    || str_contains($encoded, 'Stale NameTree')
    || str_contains($plainText, '<wp-export>')
    || str_contains($plainText, 'NameTree PieceInfo Private Leak')
) {
    throw new RuntimeException('Expected name-tree payloads, private PieceInfo streams, and stale rows to stay review-only.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-metadata-nametree-pieceinfo-outputintent-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-name-tree-filespec-pieceinfo-outputintent-review',
    'native_boundary' => 'current xref-selected catalog /Names /EmbeddedFiles FileSpec /PieceInfo and FileSpec-local /OutputIntents stay review-only before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'pdfa_identifiers' => $metadata['pdfa']['output_condition_identifiers'] ?? [],
    'embedded_name_tree_files' => array_column($embeddedFiles, 'name_tree_name'),
    'pieceinfo_manifest' => $pieceInfo['private']['ManifestId'] ?? null,
    'pieceinfo_outputintent_identifier' => $pieceInfo['private']['OutputIntents'][0]['OutputConditionIdentifier'] ?? null,
    'provenance_outputintent_identifiers' => $provenance['pdfa_output_intents']['output_condition_identifiers'] ?? [],
    'payload_content_omitted' => !array_key_exists('content', $source),
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:name-tree-pieceinfo-review ' . $htmlJson([
    'filename' => $source['filename'] ?? null,
    'relationship' => $source['relationship'] ?? null,
    'last_modified' => $pieceInfo['last_modified'] ?? null,
    'private' => $pieceInfo['private'] ?? [],
    'provenance_review' => $provenance,
]) . " -->\n";
