<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$csvPayload = "Title,Status\nDraft,Ready\n";
$notesPayload = "Needs alt text\n";
$stalePayload = "Title,Status\nStale,Ignore\n";
$sourcePayload = '<wp-export><post id="catalog-af"/></wp-export>';
$compressedCsv = gzcompress($csvPayload);
$csvChecksum = md5($csvPayload);
$staleChecksum = md5($stalePayload);
$sourceChecksum = md5($sourcePayload);
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names 7 0 R /AF [13 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [4 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [72 700 90 718] /Contents (Reviewer notes) /FS 8 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Filespec /F (review-notes.csv) /Desc (WordPress import rows) /AFRelationship /Data /EF << /F 6 0 R >> >>\nendobj\n"
    . "6 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter /FlateDecode /Params << /Size " . strlen($csvPayload) . " /ModDate (D:20260603082617Z) /CheckSum <{$csvChecksum}> >> /Length " . strlen($compressedCsv) . " >>\n"
    . "stream\n{$compressedCsv}\nendstream\nendobj\n"
    . "7 0 obj\n<< /EmbeddedFiles 10 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Filespec /F (review-note.txt) /Desc (Editorial handoff) /EF << /F 9 0 R >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Params << /Size " . strlen($notesPayload) . " >> /Length " . strlen($notesPayload) . " >>\n"
    . "stream\n{$notesPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Limits [(review-notes.csv) (review-notes.csv)] /Names [(review-notes.csv) 5 0 R (zz-stale.csv) 11 0 R] >>\nendobj\n"
    . "11 0 obj\n<< /Type /Filespec /F (zz-stale.csv) /Desc (Stale out-of-limits import rows) /AFRelationship /Data /EF << /F 12 0 R >> >>\nendobj\n"
    . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
    . "stream\n{$stalePayload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /Filespec /F (legacy-source.xml) /UF (source.xml) /Desc (Original WordPress export) /AFRelationship /Source /EF << /F 14 0 R /UF 14 0 R >> >>\nendobj\n"
    . "14 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260604174658Z) >> /Length " . strlen($sourcePayload) . " >>\n"
    . "stream\n{$sourcePayload}\nendstream\nendobj\n"
    . "%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$csvAttachment = $summary['attachments'][0] ?? null;
if (!is_array($csvAttachment) || ($csvAttachment['relationship'] ?? null) !== 'Data' || ($csvAttachment['checksum_matches'] ?? null) !== true) {
    throw new RuntimeException('Expected checksum-matched Data relationship attachment review metadata.');
}
$catalogAttachment = $summary['attachments'][1] ?? null;
if (!is_array($catalogAttachment)
    || ($catalogAttachment['source'] ?? null) !== 'catalog-associated-file'
    || ($catalogAttachment['relationship'] ?? null) !== 'Source'
    || ($catalogAttachment['checksum_matches'] ?? null) !== true
) {
    throw new RuntimeException('Expected checksum-matched catalog associated source attachment review metadata.');
}
if (str_contains(json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'zz-stale.csv')) {
    throw new RuntimeException('Expected out-of-limits EmbeddedFiles name-tree rows to be pruned.');
}

echo "<!-- markerpdf-pdf-attachments-smoke " . htmlspecialchars(json_encode([
    'native_boundary' => 'PDF EmbeddedFiles name tree, catalog AF, and FileAttachment annotation preflight',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => $summary['attachment_count'],
    'total_bytes' => $summary['total_bytes'],
    'filenames' => $summary['filenames'],
    'pruned_out_of_limits_name_tree_entry' => true,
    'catalog_associated_file_preflight' => ($catalogAttachment['associated_file'] ?? false) === true,
    'relationship_roles' => array_values(array_filter(array_map(
        static fn (array $attachment): ?string => $attachment['relationship_role'] ?? null,
        $summary['attachments']
    ))),
    'checksum_matches' => array_values(array_filter(array_map(
        static fn (array $attachment): ?bool => $attachment['checksum_matches'] ?? null,
        $summary['attachments']
    ), static fn (?bool $match): bool => $match !== null)),
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($summary['attachments'] as $attachment) {
    $label = $attachment['filename']
        . ' (' . $attachment['source'] . ', ' . ($attachment['relationship'] ?? 'unassociated') . ', ' . $attachment['content_type'] . ', ' . $attachment['byte_length'] . ' bytes)';
    echo '<li data-marker-attachment-sha256="'
        . htmlspecialchars($attachment['sha256'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
