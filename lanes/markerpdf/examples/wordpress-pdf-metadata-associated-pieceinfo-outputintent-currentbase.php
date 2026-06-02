<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rootProfile = 'Associated PieceInfo smoke root PDF/A profile bytes';
$pieceProfile = 'Associated PieceInfo smoke attachment PDF/A profile bytes';
$sourcePayload = '<wp-export><post id="associated-pieceinfo-outputintent-smoke"/></wp-export>';
$privatePayload = 'BT /F1 12 Tf 72 720 Td (Associated PieceInfo Smoke Private Leak) Tj ET';
$staleProfile = 'Stale associated PieceInfo smoke attachment PDF/A profile bytes';
$staleSourcePayload = '<wp-export><post id="stale-associated-pieceinfo-outputintent-smoke"/></wp-export>';
$stalePrivatePayload = 'BT /F1 12 Tf 72 720 Td (Stale Associated PieceInfo Smoke Private Leak) Tj ET';

$rootProfileStream = gzcompress($rootProfile);
$pieceProfileStream = gzcompress($pieceProfile);
$privateStream = gzcompress($privatePayload);
$staleProfileStream = gzcompress($staleProfile);
$stalePrivateStream = gzcompress($stalePrivatePayload);
if (
    !is_string($rootProfileStream)
    || !is_string($pieceProfileStream)
    || !is_string($privateStream)
    || !is_string($staleProfileStream)
    || !is_string($stalePrivateStream)
) {
    throw new RuntimeException('Unable to compress associated PieceInfo OutputIntent smoke streams.');
}

$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$privateChecksum = strtoupper(hash('md5', $privatePayload));
$content = 'BT /F1 12 Tf 72 720 Td (Current Associated PieceInfo OutputIntent Smoke Body) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale Associated PieceInfo OutputIntent Smoke Body) Tj ET';

$pdf = "%PDF-2.0\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /OutputIntents [9 0 R] /AF [10 0 R] >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(7, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($rootProfileStream) . " >>\nstream\n{$rootProfileStream}\nendstream");
$addObject(8, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($pieceProfileStream) . " >>\nstream\n{$pieceProfileStream}\nendstream");
$addObject(9, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Associated PieceInfo Smoke Root PDF/A) /Info (Smoke root PDF/A) /DestOutputProfile 7 0 R >>');
$addObject(10, '<< /Type /Filespec /F (legacy-associated-piece-smoke.xml) /UF (associated-piece-smoke.xml) /Desc (Associated PieceInfo smoke WordPress source) /AFRelationship /Source /PieceInfo << /WPImport << /LastModified (D:20260602224600Z) /Private << /ManifestId (associated-piece-outputintent-smoke) /OutputIntents [13 0 R] /PrivateStream 16 0 R >> >> >> /EF << /F 11 0 R >> >>');
$addObject(11, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($sourcePayload) . ' /CheckSum <' . $sourceChecksum . "> /ModDate (D:20260602224610Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream");
$addObject(13, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Associated PieceInfo Smoke Attachment PDF/A) /Info (Smoke PieceInfo private attachment PDF/A) /DestOutputProfile 8 0 R >>');
$addObject(16, '<< /Type /Metadata /Subtype /text#2Fplain /Filter /FlateDecode /Params << /Size ' . strlen($privatePayload) . ' /CheckSum <' . $privateChecksum . "> >> /Length " . strlen($privateStream) . " >>\nstream\n{$privateStream}\nendstream");

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber < 31; $objectNumber++) {
    if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 30)) {
        $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
        continue;
    }

    $rows .= pack('CNn', 1, $objectNumber === 30 ? $xrefOffset : $offsets[$objectNumber], 0);
}

$compressedXref = gzcompress($rows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress associated PieceInfo OutputIntent smoke xref stream.');
}

$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /OutputIntents [9 0 R] /AF [10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
    . "8 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($staleProfileStream) . " >>\nstream\n{$staleProfileStream}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (stale-associated-piece-smoke.xml) /Desc (Stale associated PieceInfo smoke source) /AFRelationship /Source /PieceInfo << /WPImport << /LastModified (D:20260602230000Z) /Private << /ManifestId (stale-associated-piece-outputintent-smoke) /OutputIntents [13 0 R] /PrivateStream 16 0 R >> >> >> /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($staleSourcePayload) . " >>\nstream\n{$staleSourcePayload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Stale Associated PieceInfo Smoke Attachment PDF/A) /Info (Stale smoke piece intent) /DestOutputProfile 8 0 R >>\nendobj\n"
    . "16 0 obj\n<< /Type /Metadata /Subtype /text#2Fplain /Filter /FlateDecode /Length " . strlen($stalePrivateStream) . " >>\nstream\n{$stalePrivateStream}\nendstream\nendobj\n";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$summary = $metadata['pdfa_associated_files'] ?? [];
$entry = $summary['entries'][0] ?? [];

if (($metadata['pdfa']['output_condition_identifiers'] ?? []) !== ['Associated PieceInfo Smoke Root PDF/A']) {
    throw new RuntimeException('Expected current root PDF/A OutputIntent metadata.');
}
if (($summary['attachment_output_condition_identifiers'] ?? []) !== ['Associated PieceInfo Smoke Attachment PDF/A']) {
    throw new RuntimeException('Expected PieceInfo-local attachment PDF/A OutputIntent summary.');
}
if (($entry['piece_info_private_streams']['streams'][0]['checksum_matches'] ?? null) !== true) {
    throw new RuntimeException('Expected PieceInfo private-stream checksum review metadata.');
}
if (!is_string($encoded) || str_contains($encoded, $sourcePayload) || str_contains($encoded, $privatePayload) || str_contains($encoded, $pieceProfile) || str_contains($encoded, $staleProfile)) {
    throw new RuntimeException('Expected payload and ICC bytes to stay out of review metadata JSON.');
}
if (str_contains($plainText, '<wp-export>') || str_contains($plainText, 'Associated PieceInfo Smoke Private Leak') || str_contains($plainText, 'Stale Associated PieceInfo OutputIntent Smoke Body')) {
    throw new RuntimeException('Expected attachment and stale bytes to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-metadata-associated-pieceinfo-outputintent-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-associated-pieceinfo-outputintent-review',
    'native_boundary' => 'current xref-selected catalog /AF FileSpec /PieceInfo private OutputIntents stay associated-file review metadata before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'root_pdfa_identifiers' => $metadata['pdfa']['output_condition_identifiers'] ?? [],
    'associated_summary_source' => $summary['source'] ?? null,
    'associated_filenames' => $summary['filenames'] ?? [],
    'associated_relationships' => $summary['relationships'] ?? [],
    'piece_info_pdfa_identifiers' => $entry['piece_info_pdfa_output_intents']['output_condition_identifiers'] ?? [],
    'piece_info_private_stream_checksum_matches' => $entry['piece_info_private_streams']['streams'][0]['checksum_matches'] ?? null,
    'payload_content_omitted' => !array_key_exists('content', $entry['payload'] ?? []),
    'stale_duplicates_excluded' => is_string($encoded) && !str_contains($encoded, 'Stale Associated PieceInfo'),
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- wp:file {"href":"media/associated-piece-smoke.xml"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/associated-piece-smoke.xml">associated-piece-smoke.xml</a></div>' . "\n";
echo "<!-- /wp:file -->\n";
