<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$attachmentPayload = '<wp-export><post id="901"/></wp-export>';
$privatePayload = 'BT /F1 12 Tf 72 720 Td (Indirect App PieceInfo Leak) Tj ET';
$privateChecksum = strtoupper(hash('md5', $privatePayload));

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AF [10 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Original source packet) /AFRelationship /Source /PieceInfo << /WPImport 30 0 R >> /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($attachmentPayload) . " >> /Length " . strlen($attachmentPayload) . " >>\nstream\n{$attachmentPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /LastModified (D:20260602121925Z) /Private 31 0 R >>\nendobj\n"
    . "31 0 obj\n<< /Type /Metadata /Subtype /text#2Fplain /Params << /Size " . strlen($privatePayload) . " /CheckSum <{$privateChecksum}> >> /Length " . strlen($privatePayload) . " >>\nstream\n{$privatePayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$attachments = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
if (count($attachments) !== 1) {
    throw new RuntimeException('Expected one catalog-associated attachment.');
}

$attachment = $attachments[0];
$privateStream = $attachment['piece_info']['WPImport']['private_stream'] ?? null;
if (!is_array($privateStream)) {
    throw new RuntimeException('Expected indirect PieceInfo private-stream metadata.');
}

if ($plainText !== '') {
    throw new RuntimeException('Expected PieceInfo private stream bytes to stay out of visible text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-pdf-associated-pieceinfo-indirect-boundary-smoke ' . $htmlJson([
    'support_component' => 'native-pdf-associated-pieceinfo-indirect-private-stream-boundary',
    'native_boundary' => 'catalog /AF Filespec /PieceInfo application dictionary /Private stream review before fallback text extraction',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => count($attachments),
    'relationship' => $attachment['relationship'] ?? null,
    'piece_info_applications' => array_keys($attachment['piece_info'] ?? []),
    'piece_info_private_object' => $privateStream['object'] ?? null,
    'piece_info_private_checksum_matches' => $privateStream['checksum_matches'] ?? null,
    'excluded_attachment_payload_text' => !str_contains($plainText, '<wp-export>'),
    'excluded_pieceinfo_private_stream_text' => !str_contains($plainText, 'Indirect App PieceInfo Leak'),
    'visible_text_empty' => $plainText === '',
]) . " -->\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n\n";

echo '<!-- markerpdf:associated-pieceinfo-indirect-file ' . $htmlJson([
    'name' => $attachment['name'],
    'filename' => $attachment['filename'],
    'description' => $attachment['description'] ?? null,
    'relationship' => $attachment['relationship'] ?? null,
    'mime_type' => $attachment['mime_type'] ?? null,
    'piece_info' => $attachment['piece_info'] ?? [],
]) . " -->\n";
