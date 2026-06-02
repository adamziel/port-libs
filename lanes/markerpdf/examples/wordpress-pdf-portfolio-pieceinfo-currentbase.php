<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="1908"/></wp-export>';
$privatePayload = 'BT /F1 12 Tf 72 720 Td (Portfolio Current Private Leak) Tj ET';
$staleSourcePayload = '<wp-export><post id="stale-portfolio"/></wp-export>';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$privateStream = gzcompress($privatePayload);
if (!is_string($privateStream)) {
    throw new RuntimeException('Unable to compress Portfolio PieceInfo current-base smoke stream.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Current Portfolio PieceInfo Body) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale Portfolio PieceInfo Body) Tj ET';
$pdf = "%PDF-2.0\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /PieceInfo << /WPPortfolio << /LastModified (D:20260602190800Z) /Private << /Workflow (current portfolio review) /Batch 40 >> >> >> /Names << /EmbeddedFiles 6 0 R >> >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(5, '<< /Type /Collection /View /T /D (current-source.xml) /Schema << /Subject << /Subtype /S /N (Subject) /O 1 >> /Priority << /Subtype /N /N (Priority) /O 2 >> /Bytes << /Subtype /Size /N (Bytes) /O 3 >> >> /Sort << /S [/Priority /Subject] /A [true false] >> >>');
$addObject(6, '<< /Names [(current-source.xml) 10 0 R] >>');
$addObject(10, '<< /Type /Filespec /F (legacy-current-source.xml) /UF (current-source.xml) /Desc (Current WordPress portfolio source) /AFRelationship /Source /CI 30 0 R /PieceInfo 31 0 R /EF << /F 11 0 R >> >>');
$addObject(11, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($sourcePayload) . ' /CheckSum <' . $sourceChecksum . "> >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream");
$addObject(30, '<< /Subject (Current WordPress Export) /Priority << /Type /CollectionSubitem /D 2 /P (P) >> /Bytes ' . strlen($sourcePayload) . ' >>');
$addObject(31, '<< /WPImporter << /LastModified (D:20260602190840Z) /Private << /ManifestId (current-portfolio-1908) /PrivateStream 33 0 R >> >> >>');
$addObject(33, '<< /Type /Metadata /Subtype /application#2Fjson /Filter /FlateDecode /Length ' . strlen($privateStream) . " >>\nstream\n{$privateStream}\nendstream");

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber < 41; $objectNumber++) {
    if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 40)) {
        $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
        continue;
    }

    $rows .= pack('CNn', 1, $objectNumber === 40 ? $xrefOffset : $offsets[$objectNumber], 0);
}
$compressedXref = gzcompress($rows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress Portfolio PieceInfo current-base xref stream.');
}

$pdf .= "40 0 obj\n"
    . '<< /Type /XRef /Size 41 /Root 1 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /PieceInfo << /WPPortfolio << /LastModified (D:20260602199900Z) /Private << /Workflow (stale portfolio review) /Batch 99 >> >> >> /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Collection /View /D /D (stale-source.xml) >>\nendobj\n"
    . "6 0 obj\n<< /Names [(stale-source.xml) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (stale-source.xml) /Desc (Stale WordPress portfolio source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($staleSourcePayload) . " >>\nstream\n{$staleSourcePayload}\nendstream\nendobj\n";

$attachments = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$attachment = $attachments[0] ?? [];
$encoded = json_encode($attachments, JSON_UNESCAPED_SLASHES);

if (count($attachments) !== 1 || ($attachment['filename'] ?? null) !== 'current-source.xml') {
    throw new RuntimeException('Expected current xref-selected Portfolio attachment.');
}
if (($attachment['piece_info']['WPImporter']['private']['ManifestId'] ?? null) !== 'current-portfolio-1908') {
    throw new RuntimeException('Expected current FileSpec PieceInfo manifest metadata.');
}
if (!is_string($encoded) || str_contains($encoded, 'stale-source.xml') || str_contains($encoded, $staleSourcePayload)) {
    throw new RuntimeException('Expected stale Portfolio attachment metadata to stay excluded.');
}
if ($plainText !== 'Current Portfolio PieceInfo Body' || str_contains($plainText, 'Private Leak')) {
    throw new RuntimeException('Expected current visible text without PieceInfo private stream text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-portfolio-pieceinfo-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-embeddedfiles-current-xref-portfolio-parser',
    'native_boundary' => 'current xref-selected catalog /Collection, EmbeddedFiles name tree, FileSpec /CI, and /PieceInfo review before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => count($attachments),
    'filename' => $attachment['filename'] ?? null,
    'portfolio_view' => $attachment['portfolio']['view'] ?? null,
    'default_document' => $attachment['portfolio']['default_document'] ?? null,
    'portfolio_priority_display' => $attachment['portfolio_field_values']['Priority']['display_value'] ?? null,
    'pieceinfo_manifest' => $attachment['piece_info']['WPImporter']['private']['ManifestId'] ?? null,
    'catalog_pieceinfo_workflow' => $attachment['catalog_piece_info']['WPPortfolio']['private']['Workflow'] ?? null,
    'stale_duplicates_excluded' => is_string($encoded) && !str_contains($encoded, 'stale-source.xml'),
    'attachment_payload_text_excluded' => !str_contains($plainText, '<wp-export>'),
    'pieceinfo_private_stream_text_excluded' => !str_contains($plainText, 'Private Leak'),
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:portfolio-current-attachment ' . $htmlJson([
    'filename' => $attachment['filename'] ?? null,
    'description' => $attachment['description'] ?? null,
    'relationship' => $attachment['relationship'] ?? null,
    'mime_type' => $attachment['mime_type'] ?? null,
    'portfolio_item' => $attachment['portfolio_item'] ?? [],
    'portfolio_field_values' => $attachment['portfolio_field_values'] ?? [],
    'piece_info' => $attachment['piece_info'] ?? [],
]) . " -->\n";
