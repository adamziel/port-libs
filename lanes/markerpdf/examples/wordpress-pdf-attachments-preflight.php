<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$csvPayload = "Title,Status\nDraft,Ready\n";
$notesPayload = "Needs alt text\n";
$compressedCsv = gzcompress($csvPayload);
$csvChecksum = md5($csvPayload);
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names 7 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [4 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [72 700 90 718] /Contents (Reviewer notes) /FS 8 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Filespec /F (review-notes.csv) /Desc (WordPress import rows) /EF << /F 6 0 R >> >>\nendobj\n"
    . "6 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter /FlateDecode /Params << /Size " . strlen($csvPayload) . " /ModDate (D:20260603082617Z) /CheckSum <{$csvChecksum}> >> /Length " . strlen($compressedCsv) . " >>\n"
    . "stream\n{$compressedCsv}\nendstream\nendobj\n"
    . "7 0 obj\n<< /EmbeddedFiles << /Names [(review-notes.csv) 5 0 R] >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Filespec /F (review-note.txt) /Desc (Editorial handoff) /EF << /F 9 0 R >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Params << /Size " . strlen($notesPayload) . " >> /Length " . strlen($notesPayload) . " >>\n"
    . "stream\n{$notesPayload}\nendstream\nendobj\n"
    . "%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);

echo "<!-- markerpdf-pdf-attachments-smoke " . htmlspecialchars(json_encode([
    'native_boundary' => 'PDF EmbeddedFiles name tree and FileAttachment annotation attachment preflight',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => $summary['attachment_count'],
    'total_bytes' => $summary['total_bytes'],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($summary['attachments'] as $attachment) {
    $label = $attachment['filename']
        . ' (' . $attachment['source'] . ', ' . $attachment['content_type'] . ', ' . $attachment['byte_length'] . ' bytes)';
    echo '<li data-marker-attachment-sha256="'
        . htmlspecialchars($attachment['sha256'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
