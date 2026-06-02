<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceXml = '<wp-export><post id="137"/></wp-export>';
$previewPayload = 'BT /F1 12 Tf 72 720 Td (Associated EF Payload Leak) Tj ET';
$verifiedPrivate = '{"piece":"verified","checksum":"current"}';
$stalePrivate = 'BT /F1 12 Tf 72 720 Td (PieceInfo Private Checksum Leak) Tj ET';
$compressedVerifiedPrivate = gzcompress($verifiedPrivate);
if (!is_string($compressedVerifiedPrivate)) {
    throw new RuntimeException('Unable to compress PieceInfo checksum fixture.');
}

$sourceChecksum = strtoupper(hash('md5', $sourceXml));
$verifiedPrivateChecksum = strtoupper(hash('md5', $verifiedPrivate));
$stalePrivateChecksum = str_repeat('7f', 16);
$pageContent = 'BT /F1 12 Tf 72 720 Td (Associated PieceInfo Review) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AF [10 0 R << /Type /Filespec /F (preview.pdf) /Desc (Generated preview) /AFRelationship /Alternative /PieceInfo << /WPPreview << /LastModified (D:20260602102200Z) /Private 41 0 R >> >> /EF << /F 21 0 R >> >>] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Original WordPress export) /AFRelationship /Source /PieceInfo 30 0 R /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourceXml) . " /CheckSum <{$sourceChecksum}> >> /Length " . strlen($sourceXml) . " >>\nstream\n{$sourceXml}\nendstream\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Length " . strlen($previewPayload) . " >>\nstream\n{$previewPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /WPImport << /LastModified (D:20260602102100Z) /Private 31 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /Metadata /Subtype /application#2Fjson /Filter /FlateDecode /Params << /Size " . strlen($verifiedPrivate) . " /CheckSum <{$verifiedPrivateChecksum}> /ModDate (D:20260602102130Z) >> /Length " . strlen($compressedVerifiedPrivate) . " >>\nstream\n{$compressedVerifiedPrivate}\nendstream\nendobj\n"
    . "41 0 obj\n<< /Type /Metadata /Subtype /text#2Fplain /Params << /Size " . strlen($stalePrivate) . " /CheckSum <{$stalePrivateChecksum}> /CreationDate (D:20260602102000Z) >> /Length " . strlen($stalePrivate) . " >>\nstream\n{$stalePrivate}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$attachments = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
if (count($attachments) !== 2) {
    throw new RuntimeException('Expected two catalog-associated attachments.');
}

$source = $attachments[0];
$preview = $attachments[1];
$sourcePrivate = $source['piece_info']['WPImport']['private_stream'] ?? null;
$previewPrivate = $preview['piece_info']['WPPreview']['private_stream'] ?? null;
if (!is_array($sourcePrivate) || !is_array($previewPrivate)) {
    throw new RuntimeException('Expected PieceInfo private stream metadata on both associated files.');
}
if (($sourcePrivate['checksum_matches'] ?? null) !== true || ($previewPrivate['checksum_matches'] ?? null) !== false) {
    throw new RuntimeException('Expected verified and stale PieceInfo private checksum states.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-pdf-associated-pieceinfo-checksum-smoke ' . $htmlJson([
    'support_component' => 'native-pdf-associated-pieceinfo-checksum-parser',
    'native_boundary' => 'catalog /AF Filespec /PieceInfo /Private stream /Params /CheckSum review before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => count($attachments),
    'relationships' => array_map(
        static fn (array $attachment): ?string => $attachment['relationship'] ?? null,
        $attachments
    ),
    'attachment_checksum_matches' => array_map(
        static fn (array $attachment): ?bool => $attachment['checksum_matches'] ?? null,
        $attachments
    ),
    'piece_info_private_checksum_matches' => [
        $sourcePrivate['checksum_matches'] ?? null,
        $previewPrivate['checksum_matches'] ?? null,
    ],
    'piece_info_private_objects' => [
        $sourcePrivate['object'] ?? null,
        $previewPrivate['object'] ?? null,
    ],
    'excluded_attachment_payload_text' => !str_contains($plainText, '<wp-export>') && !str_contains($plainText, 'Associated EF Payload Leak'),
    'excluded_pieceinfo_private_stream_text' => !str_contains($plainText, 'PieceInfo Private Checksum Leak') && !str_contains($plainText, 'verified'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($attachments as $attachment) {
    echo '<!-- wp:file {"href":"media/' . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
    echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</a></div>\n";
    echo "<!-- /wp:file -->\n\n";
    echo '<!-- markerpdf:associated-pieceinfo-file ' . $htmlJson([
        'name' => $attachment['name'],
        'filename' => $attachment['filename'],
        'description' => $attachment['description'] ?? null,
        'relationship' => $attachment['relationship'] ?? null,
        'mime_type' => $attachment['mime_type'] ?? null,
        'checksum_matches' => $attachment['checksum_matches'] ?? null,
        'piece_info' => $attachment['piece_info'] ?? [],
    ]) . " -->\n\n";
}
