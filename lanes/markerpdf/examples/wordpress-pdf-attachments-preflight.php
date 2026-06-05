<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$csvPayload = "Title,Status\nDraft,Ready\n";
$notesPayload = "Needs alt text\n";
$stalePayload = "Title,Status\nStale,Ignore\n";
$postEofPayload = "Title,Status\nPost EOF Stale,Ignore\n";
$sourcePayload = '<wp-export><post id="catalog-af"/></wp-export>';
$pagePayload = '<wp-page><attachment role="page-associated"/></wp-page>';
$relatedPayload = '{"manifest":"catalog-related"}';
$compressedCsv = gzcompress($csvPayload);
$csvChecksum = md5($csvPayload);
$staleChecksum = md5($stalePayload);
$postEofChecksum = md5($postEofPayload);
$sourceChecksum = md5($sourcePayload);
$pageChecksum = md5($pagePayload);
$relatedChecksum = md5($relatedPayload);
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names 7 0 R /AF [13 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /AF [16 0 R] /Annots [4 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [72 700 90 718] /Contents (Reviewer notes) /FS 8 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Filespec /F (review-notes.csv) /Desc (WordPress import rows) /AFRelationship /Data /EF << /F 6 0 R >> >>\nendobj\n"
    . "6 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter /FlateDecode /Params << /Size " . strlen($csvPayload) . " /ModDate (D:20260603082617Z) /CheckSum <{$csvChecksum}> >> /Length " . strlen($compressedCsv) . " >>\n"
    . "stream\n{$compressedCsv}\nendstream\nendobj\n"
    . "7 0 obj\n<< /EmbeddedFiles 10 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Filespec /F (review-note.txt) /Desc (Editorial handoff) /EF << /F 9 0 R >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Params << /Size " . strlen($notesPayload) . " >> /Length " . strlen($notesPayload) . " >>\n"
    . "stream\n{$notesPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Limits [(review-notes.csv) (review-notes.csv)] /Names 18 0 R >>\nendobj\n"
    . "11 0 obj\n<< /Type /Filespec /F (zz-stale.csv) /Desc (Stale out-of-limits import rows) /AFRelationship /Data /EF << /F 12 0 R >> >>\nendobj\n"
    . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
    . "stream\n{$stalePayload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /Filespec /F (legacy-source.xml) /UF (source.xml) /Desc (Original WordPress export) /AFRelationship /Source /EF << /F 14 0 R /UF 14 0 R >> /RF << /F [(catalog-related.json) 15 0 R] >> >>\nendobj\n"
    . "14 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260604174658Z) >> /Length " . strlen($sourcePayload) . " >>\n"
    . "stream\n{$sourcePayload}\nendstream\nendobj\n"
    . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($relatedPayload) . " /CheckSum <{$relatedChecksum}> >> /Length " . strlen($relatedPayload) . " >>\n"
    . "stream\n{$relatedPayload}\nendstream\nendobj\n"
    . "16 0 obj\n<< /Type /Filespec /F (page-source.xml) /Desc (Page-associated source export) /AFRelationship /Source /EF << /F 17 0 R >> >>\nendobj\n"
    . "17 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pagePayload) . " /CheckSum <{$pageChecksum}> /ModDate (D:20260604210100Z) >> /Length " . strlen($pagePayload) . " >>\n"
    . "stream\n{$pagePayload}\nendstream\nendobj\n"
    . "18 0 obj\n[(review-notes.csv) 5 0 R (zz-stale.csv) 11 0 R]\nendobj\n"
    . "%%EOF\n"
    . "5 0 obj\n<< /Type /Filespec /F (post-eof-stale.csv) /Desc (Post EOF stale import rows) /AFRelationship /Alternative /EF << /F 6 0 R >> >>\nendobj\n"
    . "6 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($postEofPayload) . " /CheckSum <{$postEofChecksum}> >> /Length " . strlen($postEofPayload) . " >>\n"
    . "stream\n{$postEofPayload}\nendstream\nendobj\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$csvAttachment = $summary['attachments'][0] ?? null;
if (!is_array($csvAttachment)
    || ($csvAttachment['filename'] ?? null) !== 'review-notes.csv'
    || ($csvAttachment['relationship'] ?? null) !== 'Data'
    || ($csvAttachment['checksum_matches'] ?? null) !== true
) {
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
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
if (str_contains($summaryJson, 'post-eof-stale.csv') || str_contains($summaryJson, 'Post EOF Stale')) {
    throw new RuntimeException('Expected post-EOF stale FileSpec objects to be ignored by attachment preflight.');
}
if (($catalogAttachment['related_file_count'] ?? null) !== 1
    || ($catalogAttachment['related_files'][0]['checksum_matches'] ?? null) !== true
    || ($catalogAttachment['related_files'][0]['related_filename'] ?? null) !== 'catalog-related.json'
    || str_contains($summaryJson, $relatedPayload)
) {
    throw new RuntimeException('Expected named related files to be summarized without exposing related payload bytes.');
}
$pageAttachment = $summary['attachments'][2] ?? null;
if (!is_array($pageAttachment)
    || ($pageAttachment['source'] ?? null) !== 'page-associated-file'
    || ($pageAttachment['page_associated_file'] ?? null) !== true
    || ($pageAttachment['page_number'] ?? null) !== 1
    || ($pageAttachment['relationship'] ?? null) !== 'Source'
    || ($pageAttachment['checksum_matches'] ?? null) !== true
    || str_contains($summaryJson, $pagePayload)
) {
    throw new RuntimeException('Expected page associated source attachment review metadata without payload bytes.');
}

echo "<!-- markerpdf-pdf-attachments-smoke " . htmlspecialchars(json_encode([
    'native_boundary' => 'PDF EmbeddedFiles name tree, catalog AF, page AF, and FileAttachment annotation preflight',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => $summary['attachment_count'],
    'total_bytes' => $summary['total_bytes'],
    'filenames' => $summary['filenames'],
    'pruned_out_of_limits_name_tree_entry' => true,
    'indirect_embeddedfiles_names_array_preflight' => ($csvAttachment['source'] ?? null) === 'embedded-files-name-tree',
    'terminal_eof_bounds_attachment_scan' => !str_contains($summaryJson, 'post-eof-stale.csv'),
    'catalog_associated_file_preflight' => ($catalogAttachment['associated_file'] ?? false) === true,
    'page_associated_file_preflight' => ($pageAttachment['page_associated_file'] ?? false) === true,
    'related_file_preflight' => ($catalogAttachment['related_file_count'] ?? 0) === 1,
    'related_file_name_pair_preflight' => ($catalogAttachment['related_files'][0]['related_filename'] ?? null) === 'catalog-related.json',
    'related_file_payload_omitted' => !str_contains($summaryJson, $relatedPayload),
    'page_associated_file_payload_omitted' => !str_contains($summaryJson, $pagePayload),
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
