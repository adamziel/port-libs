<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pngSubPredictorEncode = static function (string $bytes, int $columns): string {
    if ($columns < 1 || strlen($bytes) % $columns !== 0) {
        throw new RuntimeException('PNG predictor smoke rows must be fixed width.');
    }

    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
        $row = substr($bytes, $offset, $columns);
        $encoded .= "\x01";
        for ($index = 0; $index < $columns; $index++) {
            $left = $index > 0 ? ord($row[$index - 1]) : 0;
            $encoded .= chr((ord($row[$index]) - $left) & 0xff);
        }
    }

    return $encoded;
};

$payload = '<wp-export><post id="predictor-attachment">ready</post></wp-export>';
$invalidPayload = 'INVALID_PREDICTOR_ATTACHMENT_SHOULD_NOT_COUNT';
$columns = strlen($payload);
$encodedPayload = $pngSubPredictorEncode($payload, $columns);
$compressedPayload = gzcompress($encodedPayload);
$invalidCompressedPayload = gzcompress($invalidPayload);
if (!is_string($compressedPayload) || !is_string($invalidCompressedPayload)) {
    throw new RuntimeException('Unable to compress predictor attachment smoke fixtures.');
}

$hexPayload = strtoupper(bin2hex($compressedPayload)) . '>';
$invalidHexPayload = strtoupper(bin2hex($invalidCompressedPayload)) . '>';
$checksum = md5($payload);
$invalidChecksum = md5($invalidPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Predictor Attachment Review) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(predictor-source.xml) 10 0 R (invalid-predictor.bin) 12 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (predictor-source.xml) /Desc (Predictor filtered WordPress source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Filter [ null /ASCIIHexDecode /FlateDecode ] /DecodeParms [ 99 0 R null << /Predictor 12 /Columns {$columns} /Colors 1 /BitsPerComponent 8 /EarlyChange 1 >> ] /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($hexPayload) . " >>\nstream\n{$hexPayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Filespec /F (invalid-predictor.bin) /Desc (Invalid predictor source) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Foctet-stream /Filter [ /ASCIIHexDecode /FlateDecode ] /DecodeParms [ null << /Predictor 99 /Columns 1 >> ] /Params << /Size " . strlen($invalidPayload) . " /CheckSum <{$invalidChecksum}> >> /Length " . strlen($invalidHexPayload) . " >>\nstream\n{$invalidHexPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? null;
$embeddedFile = $files[0] ?? null;

if (!is_array($attachment)
    || !is_array($embeddedFile)
    || ($summary['attachment_count'] ?? null) !== 1
    || ($attachment['filename'] ?? null) !== 'predictor-source.xml'
    || ($attachment['relationship_role'] ?? null) !== 'original_source'
    || ($attachment['byte_length'] ?? null) !== strlen($payload)
    || ($attachment['checksum_matches'] ?? null) !== true
    || array_key_exists('bytes', $attachment)
    || ($embeddedFile['content'] ?? null) !== $payload
    || ($embeddedFile['checksum_matches'] ?? null) !== true
    || str_contains($summaryJson, $payload)
    || str_contains($summaryJson, $invalidPayload)
    || str_contains($summaryJson, 'invalid-predictor.bin')
    || str_contains($filesJson, $invalidPayload)
    || str_contains($filesJson, 'invalid-predictor.bin')
    || $plainText !== 'Predictor Attachment Review'
) {
    throw new RuntimeException('Expected predictor-decoded attachment review metadata without payload leakage.');
}

echo '<!-- markerpdf-pdf-attachment-stream-filter-predictor ' . htmlspecialchars(json_encode([
    'native_boundary' => 'EmbeddedFile Flate DecodeParms predictor stream filter stack with null filter slots',
    'attachment_count' => $summary['attachment_count'],
    'filename' => $attachment['filename'],
    'filters' => $attachment['filters'],
    'byte_length' => $attachment['byte_length'],
    'checksum_matches' => $attachment['checksum_matches'],
    'flate_predictor_decodeparms_applied' => ($embeddedFile['content'] ?? null) === $payload,
    'null_filter_decodeparms_slot_ignored' => ($embeddedFile['content'] ?? null) === $payload,
    'invalid_predictor_fail_closed' => !str_contains($summaryJson, 'invalid-predictor.bin'),
    'payload_bytes_omitted_from_summary' => !str_contains($summaryJson, $payload),
    'visible_text' => $plainText,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li data-marker-attachment-filter-stack="'
    . htmlspecialchars(implode(',', $attachment['filters']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '">'
    . htmlspecialchars(
        $attachment['filename'] . ' decoded with Flate DecodeParms predictors for WordPress attachment review',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    )
    . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
