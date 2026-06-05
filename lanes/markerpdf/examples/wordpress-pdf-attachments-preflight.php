<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$csvPayload = "Title,Status\nDraft,Ready\n";
$notesPayload = "Needs alt text\n";
$stalePayload = "Title,Status\nStale,Ignore\n";
$postEofPayload = "Title,Status\nPost EOF Stale,Ignore\n";
$duplicateNamePayload = "Title,Status\nDuplicate Key Stale,Ignore\n";
$sourcePayload = '<wp-export><post id="catalog-af"/></wp-export>';
$pagePayload = '<wp-page><attachment role="page-associated"/></wp-page>';
$directPayload = '<wp-export><post id="direct-filespec-mirror"/></wp-export>';
$sharedPayload = '<wp-export><post id="associated-filespec-mirror"/></wp-export>';
$pathPayload = '<wp-export><post id="path-filename-review"/></wp-export>';
$relatedPayload = '{"manifest":"catalog-related"}';
$compressedCsv = gzcompress($csvPayload);
$csvChecksum = md5($csvPayload);
$staleChecksum = md5($stalePayload);
$postEofChecksum = md5($postEofPayload);
$duplicateNameChecksum = md5($duplicateNamePayload);
$sourceChecksum = md5($sourcePayload);
$pageChecksum = md5($pagePayload);
$directChecksum = md5($directPayload);
$directPermanentIdHex = '00112233445566778899AABBCCDDEEFF';
$directChangingIdHex = strtoupper(bin2hex('direct-filespec-mirror-v2'));
$directFileSpec = '<< /Type /Filespec /FS /URL /F (https://example.test/direct-source.xml) /UF (direct-source.xml) /Desc (Direct FileSpec mirror export) /AFRelationship /Source /ID [<' . $directPermanentIdHex . '> <' . $directChangingIdHex . '>] /V true /EF << /UF 21 0 R >> >>';
$sharedChecksum = md5($sharedPayload);
$pathChecksum = md5($pathPayload);
$relatedChecksum = md5($relatedPayload);
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names 7 0 R /AF [13 0 R {$directFileSpec} 22 0 R 25 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /AF [16 0 R {$directFileSpec} 22 0 R] /Annots [4 0 R 19 0 R 20 0 R 24 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [72 700 90 718] /Contents (Reviewer notes) /FS 8 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Filespec /F (review-notes.csv) /Desc (WordPress import rows) /AFRelationship /Data /EF << /F 6 0 R >> >>\nendobj\n"
    . "6 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter /FlateDecode /Params << /Size " . strlen($csvPayload) . " /ModDate (D:20260603082617Z) /CheckSum <{$csvChecksum}> >> /Length " . strlen($compressedCsv) . " >>\n"
    . "stream\n{$compressedCsv}\nendstream\nendobj\n"
    . "7 0 obj\n<< /EmbeddedFiles 10 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Filespec /F (review-note.txt) /Desc (Editorial handoff) /EF << /F 9 0 R >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Params << /Size " . strlen($notesPayload) . " >> /Length " . strlen($notesPayload) . " >>\n"
    . "stream\n{$notesPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Limits [(direct-source.xml) (review-notes.csv)] /Names 18 0 R >>\nendobj\n"
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
    . "18 0 obj\n[(review-notes.csv) 5 0 R (review-notes.csv) 27 0 R (direct-source.xml) {$directFileSpec} (zz-stale.csv) 11 0 R]\nendobj\n"
    . "19 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [144 660 168 684] /Contents (Mirrored attachment review note) /FS 5 0 R >>\nendobj\n"
    . "20 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [180 640 202 662] /Contents (Direct FileSpec mirrored note) /FS {$directFileSpec} >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($directPayload) . " /CheckSum <{$directChecksum}> /ModDate (D:20260605025837Z) >> /Length " . strlen($directPayload) . " >>\n"
    . "stream\n{$directPayload}\nendstream\nendobj\n"
    . "22 0 obj\n<< /Type /Filespec /F (shared-source.xml) /Desc (Shared associated source export) /AFRelationship /Source /EF << /F 23 0 R >> >>\nendobj\n"
    . "23 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sharedPayload) . " /CheckSum <{$sharedChecksum}> /ModDate (D:20260605051156Z) >> /Length " . strlen($sharedPayload) . " >>\n"
    . "stream\n{$sharedPayload}\nendstream\nendobj\n"
    . "24 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [210 620 232 642] /Contents (Shared associated FileSpec note) /FS 22 0 R >>\nendobj\n"
    . "25 0 obj\n<< /Type /Filespec /F (C:\\\\Users\\\\Editor\\\\Downloads\\\\path-source.xml) /UF (../exports/path-source.xml) /Desc (Path-shaped source export) /AFRelationship /Source /EF << /UF 26 0 R >> >>\nendobj\n"
    . "26 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pathPayload) . " /CheckSum <{$pathChecksum}> /ModDate (D:20260605073720Z) >> /Length " . strlen($pathPayload) . " >>\n"
    . "stream\n{$pathPayload}\nendstream\nendobj\n"
    . "27 0 obj\n<< /Type /Filespec /F (duplicate-key-stale.csv) /Desc (Duplicate name-tree key stale rows) /AFRelationship /Alternative /EF << /F 28 0 R >> >>\nendobj\n"
    . "28 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($duplicateNamePayload) . " /CheckSum <{$duplicateNameChecksum}> >> /Length " . strlen($duplicateNamePayload) . " >>\n"
    . "stream\n{$duplicateNamePayload}\nendstream\nendobj\n"
    . "%%EOF\n"
    . "5 0 obj\n<< /Type /Filespec /F (post-eof-stale.csv) /Desc (Post EOF stale import rows) /AFRelationship /Alternative /EF << /F 6 0 R >> >>\nendobj\n"
    . "6 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($postEofPayload) . " /CheckSum <{$postEofChecksum}> >> /Length " . strlen($postEofPayload) . " >>\n"
    . "stream\n{$postEofPayload}\nendstream\nendobj\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$kidsNameTreePayload = "Title,Status\nIndirect Kids Attachment,Ready\n";
$kidsPagePayload = '<wp-page><attachment role="indirect-kids"/></wp-page>';
$kidsStalePayload = "Title,Status\nIndirect Kids Stale,Ignore\n";
$kidsNameTreeChecksum = md5($kidsNameTreePayload);
$kidsPageChecksum = md5($kidsPagePayload);
$kidsStaleChecksum = md5($kidsStalePayload);
$indirectKidsPdf = "%PDF-2.0\n";
$indirectKidsOffsets = [];
$addIndirectKidsObject = static function (int $objectNumber, string $body) use (&$indirectKidsPdf, &$indirectKidsOffsets): void {
    $indirectKidsOffsets[$objectNumber] = strlen($indirectKidsPdf);
    $indirectKidsPdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};
$addIndirectKidsObject(1, '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>');
$addIndirectKidsObject(2, '<< /Type /Pages /Kids 30 0 R /Count 1 >>');
$addIndirectKidsObject(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /AF [20 0 R] >>');
$addIndirectKidsObject(6, '<< /Limits [(kids-current.csv) (kids-current.csv)] /Kids 7 0 R >>');
$addIndirectKidsObject(7, '[8 0 R]');
$addIndirectKidsObject(8, '<< /Limits [(kids-current.csv) (kids-current.csv)] /Names [(kids-current.csv) 10 0 R (zz-kids-stale.csv) 12 0 R] >>');
$addIndirectKidsObject(10, '<< /Type /Filespec /F (kids-current.csv) /Desc (Current indirect Kids rows) /AFRelationship /Data /EF << /F 11 0 R >> >>');
$addIndirectKidsObject(11, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($kidsNameTreePayload) . " /CheckSum <{$kidsNameTreeChecksum}> /ModDate (D:20260605054817Z) >> /Length " . strlen($kidsNameTreePayload) . " >>\nstream\n{$kidsNameTreePayload}\nendstream");
$addIndirectKidsObject(12, '<< /Type /Filespec /F (zz-kids-stale.csv) /Desc (Stale indirect Kids rows) /AFRelationship /Alternative /EF << /F 13 0 R >> >>');
$addIndirectKidsObject(13, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($kidsStalePayload) . " /CheckSum <{$kidsStaleChecksum}> >> /Length " . strlen($kidsStalePayload) . " >>\nstream\n{$kidsStalePayload}\nendstream");
$addIndirectKidsObject(20, '<< /Type /Filespec /F (page-kids-source.xml) /Desc (Page source through indirect Kids) /AFRelationship /Source /EF << /F 21 0 R >> >>');
$addIndirectKidsObject(21, "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($kidsPagePayload) . " /CheckSum <{$kidsPageChecksum}> /ModDate (D:20260605054818Z) >> /Length " . strlen($kidsPagePayload) . " >>\nstream\n{$kidsPagePayload}\nendstream");
$addIndirectKidsObject(30, '[3 0 R]');
$indirectKidsXrefOffset = strlen($indirectKidsPdf);
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$indirectKidsPdf .= "xref\n0 31\n" . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 30; $objectNumber++) {
    $indirectKidsPdf .= isset($indirectKidsOffsets[$objectNumber])
        ? $xrefRow($indirectKidsOffsets[$objectNumber])
        : $xrefRow(0, 0, 'f');
}
$indirectKidsPdf .= "trailer\n<< /Size 31 /Root 1 0 R >>\nstartxref\n{$indirectKidsXrefOffset}\n%%EOF\n";
$indirectKidsSummary = (new PdfAttachmentExtractor())->attachmentSummary($indirectKidsPdf);
$indirectKidsJson = json_encode($indirectKidsSummary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$findAttachment = static function (array $summary, string $filename): ?array {
    foreach ($summary['attachments'] ?? [] as $attachment) {
        if (is_array($attachment) && ($attachment['filename'] ?? null) === $filename) {
            return $attachment;
        }
    }

    return null;
};
$csvAttachment = $summary['attachments'][0] ?? null;
if (!is_array($csvAttachment)
    || ($csvAttachment['filename'] ?? null) !== 'review-notes.csv'
    || ($csvAttachment['relationship'] ?? null) !== 'Data'
    || ($csvAttachment['checksum_matches'] ?? null) !== true
    || ($csvAttachment['file_attachment_annotation'] ?? null) !== true
    || ($csvAttachment['file_attachment_annotation_source'] ?? null) !== 'page_annotation'
    || ($csvAttachment['annotation_object_id'] ?? null) !== 19
    || ($csvAttachment['annotation_contents'] ?? null) !== 'Mirrored attachment review note'
) {
    throw new RuntimeException('Expected checksum-matched Data relationship attachment review metadata with mirrored FileAttachment annotation state.');
}
$catalogAttachment = $findAttachment($summary, 'source.xml');
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
if (
    str_contains($summaryJson, 'duplicate-key-stale.csv')
    || str_contains($summaryJson, 'Duplicate Key Stale')
    || str_contains($summaryJson, $duplicateNamePayload)
) {
    throw new RuntimeException('Expected duplicate EmbeddedFiles name-tree key rows to keep the first FileSpec only.');
}
$directAttachment = $findAttachment($summary, 'direct-source.xml');
if (!is_array($directAttachment)
    || ($directAttachment['source'] ?? null) !== 'embedded-files-name-tree'
    || ($directAttachment['file_spec_object_id'] ?? null) !== null
    || ($directAttachment['stream_object_id'] ?? null) !== 21
    || ($directAttachment['ef_key'] ?? null) !== 'UF'
    || ($directAttachment['filename_source'] ?? null) !== 'UF'
    || ($directAttachment['file_system'] ?? null) !== 'URL'
    || ($directAttachment['file_system_status'] ?? null) !== 'external_url_file_system_review_only'
    || ($directAttachment['volatile'] ?? null) !== true
    || ($directAttachment['volatile_status'] ?? null) !== 'volatile_file_spec_review'
    || ($directAttachment['file_identifier']['permanent_id_hex'] ?? null) !== strtolower($directPermanentIdHex)
    || ($directAttachment['file_identifier']['changing_id_hex'] ?? null) !== strtolower($directChangingIdHex)
    || ($directAttachment['file_identifier']['identifier_status'] ?? null) !== 'complete_file_identifier_pair'
    || ($directAttachment['associated_file_source'] ?? null) !== 'catalog_af'
    || ($directAttachment['page_associated_file_source'] ?? null) !== 'page_af'
    || ($directAttachment['file_attachment_annotation_source'] ?? null) !== 'page_annotation'
    || ($directAttachment['annotation_object_id'] ?? null) !== 20
    || ($directAttachment['checksum_matches'] ?? null) !== true
    || str_contains($summaryJson, $directPayload)
) {
    throw new RuntimeException('Expected direct FileSpec mirrors to merge without exposing attachment payload bytes.');
}
$sharedAttachment = $findAttachment($summary, 'shared-source.xml');
if (!is_array($sharedAttachment)
    || ($sharedAttachment['source'] ?? null) !== 'catalog-associated-file'
    || ($sharedAttachment['associated_file_source'] ?? null) !== null
    || ($sharedAttachment['page_associated_file_source'] ?? null) !== 'page_af'
    || ($sharedAttachment['file_attachment_annotation_source'] ?? null) !== 'page_annotation'
    || ($sharedAttachment['annotation_object_id'] ?? null) !== 24
    || ($sharedAttachment['checksum_matches'] ?? null) !== true
    || str_contains($summaryJson, $sharedPayload)
) {
    throw new RuntimeException('Expected associated FileSpec mirrors without EmbeddedFiles name-tree rows to dedupe without payload bytes.');
}
if (($catalogAttachment['related_file_count'] ?? null) !== 1
    || ($catalogAttachment['related_files'][0]['checksum_matches'] ?? null) !== true
    || ($catalogAttachment['related_files'][0]['related_filename'] ?? null) !== 'catalog-related.json'
    || str_contains($summaryJson, $relatedPayload)
) {
    throw new RuntimeException('Expected named related files to be summarized without exposing related payload bytes.');
}
$pageAttachment = $findAttachment($summary, 'page-source.xml');
$pathAttachment = $findAttachment($summary, '../exports/path-source.xml');
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
if (!is_array($pathAttachment)
    || ($pathAttachment['source'] ?? null) !== 'catalog-associated-file'
    || ($pathAttachment['filename_leaf'] ?? null) !== 'path-source.xml'
    || ($pathAttachment['filename_storage_name'] ?? null) !== 'path-source.xml'
    || ($pathAttachment['filename_path_status'] ?? null) !== 'relative_path_segments_review_only'
    || ($pathAttachment['filename_has_path_segments'] ?? null) !== true
    || ($pathAttachment['filename_contains_parent_segment'] ?? null) !== true
    || ($pathAttachment['checksum_matches'] ?? null) !== true
    || str_contains($summaryJson, $pathPayload)
) {
    throw new RuntimeException('Expected path-shaped FileSpec filenames to expose basename-safe review metadata without payload bytes.');
}
$indirectKidsNameTreeAttachment = $findAttachment($indirectKidsSummary, 'kids-current.csv');
$indirectKidsPageAttachment = $findAttachment($indirectKidsSummary, 'page-kids-source.xml');
if (($indirectKidsSummary['attachment_count'] ?? null) !== 2
    || !is_array($indirectKidsNameTreeAttachment)
    || ($indirectKidsNameTreeAttachment['source'] ?? null) !== 'embedded-files-name-tree'
    || ($indirectKidsNameTreeAttachment['checksum_matches'] ?? null) !== true
    || !is_array($indirectKidsPageAttachment)
    || ($indirectKidsPageAttachment['source'] ?? null) !== 'page-associated-file'
    || ($indirectKidsPageAttachment['page_number'] ?? null) !== 1
    || ($indirectKidsPageAttachment['checksum_matches'] ?? null) !== true
    || str_contains($indirectKidsJson, 'zz-kids-stale.csv')
    || str_contains($indirectKidsJson, $kidsNameTreePayload)
    || str_contains($indirectKidsJson, $kidsPagePayload)
) {
    throw new RuntimeException('Expected indirect EmbeddedFiles and page-tree Kids arrays to resolve without stale rows or payload bytes.');
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
    'indirect_embeddedfiles_kids_array_preflight' => ($indirectKidsNameTreeAttachment['source'] ?? null) === 'embedded-files-name-tree',
    'indirect_page_tree_kids_array_preflight' => ($indirectKidsPageAttachment['source'] ?? null) === 'page-associated-file',
    'indirect_kids_attachment_count' => $indirectKidsSummary['attachment_count'],
    'indirect_kids_payload_omitted' => !str_contains($indirectKidsJson, $kidsNameTreePayload)
        && !str_contains($indirectKidsJson, $kidsPagePayload),
    'indirect_kids_stale_name_tree_entry_pruned' => !str_contains($indirectKidsJson, 'zz-kids-stale.csv'),
    'duplicate_name_tree_key_pruned' => !str_contains($summaryJson, 'duplicate-key-stale.csv')
        && !str_contains($summaryJson, $duplicateNamePayload),
    'file_attachment_annotation_mirror_preflight' => ($csvAttachment['file_attachment_annotation'] ?? false) === true,
    'file_attachment_annotation_duplicate_payload_omitted' => $summary['attachment_count'] === 7
        && substr_count($summaryJson, 'review-notes.csv') >= 1
        && !str_contains($summaryJson, $csvPayload),
    'terminal_eof_bounds_attachment_scan' => !str_contains($summaryJson, 'post-eof-stale.csv'),
    'direct_filespec_mirror_deduped' => ($directAttachment['associated_file_source'] ?? null) === 'catalog_af'
        && ($directAttachment['page_associated_file_source'] ?? null) === 'page_af'
        && ($directAttachment['file_attachment_annotation_source'] ?? null) === 'page_annotation'
        && $summary['attachment_count'] === 7,
    'direct_filespec_payload_omitted' => !str_contains($summaryJson, $directPayload),
    'direct_filespec_file_system_review' => ($directAttachment['file_system'] ?? null) === 'URL'
        && ($directAttachment['file_system_status'] ?? null) === 'external_url_file_system_review_only',
    'direct_filespec_identifier_review' => ($directAttachment['file_identifier']['identifier_status'] ?? null) === 'complete_file_identifier_pair'
        && ($directAttachment['file_identifier']['permanent_id_hex'] ?? null) === strtolower($directPermanentIdHex)
        && ($directAttachment['file_identifier']['changing_id_hex'] ?? null) === strtolower($directChangingIdHex),
    'direct_filespec_volatile_review' => ($directAttachment['volatile'] ?? null) === true
        && ($directAttachment['volatile_status'] ?? null) === 'volatile_file_spec_review',
    'associated_filespec_without_name_tree_deduped' => ($sharedAttachment['source'] ?? null) === 'catalog-associated-file'
        && ($sharedAttachment['page_associated_file_source'] ?? null) === 'page_af'
        && ($sharedAttachment['file_attachment_annotation_source'] ?? null) === 'page_annotation'
        && $summary['attachment_count'] === 7,
    'associated_filespec_without_name_tree_payload_omitted' => !str_contains($summaryJson, $sharedPayload),
    'catalog_associated_file_preflight' => ($catalogAttachment['associated_file'] ?? false) === true,
    'page_associated_file_preflight' => ($pageAttachment['page_associated_file'] ?? false) === true,
    'related_file_preflight' => ($catalogAttachment['related_file_count'] ?? 0) === 1,
    'related_file_name_pair_preflight' => ($catalogAttachment['related_files'][0]['related_filename'] ?? null) === 'catalog-related.json',
    'related_file_payload_omitted' => !str_contains($summaryJson, $relatedPayload),
    'page_associated_file_payload_omitted' => !str_contains($summaryJson, $pagePayload),
    'filename_path_review' => ($pathAttachment['filename_path_status'] ?? null) === 'relative_path_segments_review_only'
        && ($pathAttachment['filename_contains_parent_segment'] ?? null) === true,
    'filename_storage_name_review' => ($pathAttachment['filename_storage_name'] ?? null) === 'path-source.xml',
    'filename_path_payload_omitted' => !str_contains($summaryJson, $pathPayload),
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
    $label = ($attachment['filename_storage_name'] ?? $attachment['filename'])
        . ' (' . $attachment['source'] . ', ' . ($attachment['relationship'] ?? 'unassociated') . ', ' . $attachment['content_type'] . ', ' . $attachment['byte_length'] . ' bytes)';
    echo '<li data-marker-attachment-sha256="'
        . htmlspecialchars($attachment['sha256'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
