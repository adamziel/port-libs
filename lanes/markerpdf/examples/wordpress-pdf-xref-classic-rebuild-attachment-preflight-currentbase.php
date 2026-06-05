<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$stalePayload = '<wp-export><post id="stale-classic-attachment"/></wp-export>';
$currentPayload = '<wp-export><post id="current-classic-attachment"/></wp-export>';
$currentChecksum = strtoupper(hash('md5', $currentPayload));

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>');
$addObject(2, '<< /Type /Pages /Kids [] /Count 0 >>');
$addObject(6, '<< /Names [(stale-source.xml) 10 0 R] >>');
$addObject(10, '<< /Type /Filespec /F (stale-source.xml) /Desc (Stale classic rebuild attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$addObject(11, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 12\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1])
    . $xrefRow($offsets[2])
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets[6])
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets[10])
    . $xrefRow($offsets[11])
    . "trailer\n<< /Size 32 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Names << /EmbeddedFiles 26 0 R >> >>');
$addObject(21, '<< /Type /Pages /Kids [] /Count 0 >>');
$addObject(26, '<< /Names [(current-source.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (current-source.xml) /Desc (Current classic rebuild attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$pdf .= "xref\n"
    . "20 12\n"
    . $xrefRow($offsets[20])
    . $xrefRow($offsets[21])
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets[26])
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets[30])
    . $xrefRow($offsets[31])
    . "trailer\n<< /Size 32 /Root 20 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$attachment = $summary['attachments'][0] ?? [];
$encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '';

echo '<!-- markerpdf-xref-classic-rebuild-attachment-preflight-currentbase-smoke ' . htmlspecialchars(json_encode([
    'native_boundary' => 'attachment preflight rebuilds stale classic startxref to the latest top-level classic trailer root',
    'imports_current_attachment' => ($attachment['filename'] ?? null) === 'current-source.xml',
    'current_attachment_source' => $attachment['source'] ?? null,
    'current_attachment_checksum_matches' => ($attachment['checksum_matches'] ?? null) === true,
    'current_attachment_bytes_omitted' => !array_key_exists('bytes', $attachment),
    'current_attachment_declared_size_matches' => ($attachment['declared_size_matches'] ?? null) === true,
    'excludes_stale_attachment' => !str_contains($encodedSummary, 'stale-source.xml')
        && !str_contains($encodedSummary, 'stale-classic-attachment'),
    'attachment_count' => $summary['attachment_count'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

if (($attachment['filename'] ?? null) !== null) {
    $filename = (string) $attachment['filename'];
    echo '<!-- wp:file {"href":"media/' . htmlspecialchars($filename, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
    echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars($filename, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars($filename, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</a></div>\n";
    echo "<!-- /wp:file -->\n";
}
