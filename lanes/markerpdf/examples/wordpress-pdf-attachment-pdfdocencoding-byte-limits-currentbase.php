<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$lowPayload = '<wp-export><post id="pdfdoc-low-byte-source-smoke"/></wp-export>';
$upperPayload = '<wp-export><post id="pdfdoc-upper-bound-source-smoke"/></wp-export>';
$stalePayload = '<wp-export><post id="pdfdoc-stale-out-of-range-smoke"/></wp-export>';
$content = 'BT /F1 12 Tf 72 720 Td (Visible PDFDoc Byte Limits Smoke) Tj ET';

$lowNameHex = strtoupper(bin2hex(chr(0x18)));
$lowChecksum = md5($lowPayload);
$upperChecksum = md5($upperPayload);
$staleChecksum = md5($stalePayload);

$fileSpec = static function (
    int $fileSpecObject,
    int $streamObject,
    string $filename,
    string $description,
    string $payload,
    string $checksum
): string {
    return "{$fileSpecObject} 0 obj\n"
        . "<< /Type /Filespec /F ({$filename}) /Desc ({$description}) /AFRelationship /Data /EF << /F {$streamObject} 0 R >> >>\n"
        . "endobj\n"
        . "{$streamObject} 0 obj\n"
        . "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($payload) . " >>\n"
        . "stream\n{$payload}\nendstream\nendobj\n";
};

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Limits [<{$lowNameHex}> (z-report.xml)] /Names [<{$lowNameHex}> 10 0 R (z-report.xml) 12 0 R (zzzz-stale.xml) 14 0 R] >>\nendobj\n"
    . $fileSpec(10, 11, 'breve-source.xml', 'PDFDoc low raw-byte WordPress source', $lowPayload, $lowChecksum)
    . $fileSpec(12, 13, 'z-report.xml', 'Upper-bound WordPress source attachment', $upperPayload, $upperChecksum)
    . $fileSpec(14, 15, 'zzzz-stale.xml', 'Out-of-range stale attachment', $stalePayload, $staleChecksum)
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

if (
    ($summary['attachment_count'] ?? null) !== 2
    || ($summary['filenames'] ?? []) !== ['breve-source.xml', 'z-report.xml']
    || array_column($summary['attachments'] ?? [], 'name_key') !== ["\u{02D8}", 'z-report.xml']
    || array_column($files, 'name') !== ["\u{02D8}", 'z-report.xml']
    || $plainText !== 'Visible PDFDoc Byte Limits Smoke'
    || !is_string($summaryJson)
    || !is_string($filesJson)
    || str_contains($summaryJson, $lowPayload)
    || str_contains($summaryJson, $upperPayload)
    || str_contains($summaryJson, $stalePayload)
    || str_contains($summaryJson, 'zzzz-stale.xml')
    || str_contains($filesJson, $stalePayload)
    || str_contains($filesJson, $staleChecksum)
) {
    throw new RuntimeException('Expected EmbeddedFiles name-tree byte limits to include only raw-byte-bounded attachments.');
}

echo '<!-- markerpdf-pdf-attachment-pdfdocencoding-byte-limits-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-embeddedfiles-name-tree-parser',
    'native_boundary' => 'EmbeddedFiles name-tree Limits use raw PDF string bytes before decoded PDFDocEncoding text is reviewed',
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'decoded_name_keys' => array_column($summary['attachments'], 'name_key'),
    'stale_out_of_byte_limits_excluded' => !str_contains($summaryJson, 'zzzz-stale.xml')
        && !str_contains($filesJson, $stalePayload),
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $summary['attachments'][0] ?? []),
    'visible_text_excludes_attachment_payloads' => !str_contains($plainText, '<wp-export>'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($summary['attachments'] as $attachment) {
    echo '<li data-marker-attachment-source="' . htmlspecialchars((string) $attachment['source'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-attachment-name-key="' . htmlspecialchars((string) $attachment['name_key'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
