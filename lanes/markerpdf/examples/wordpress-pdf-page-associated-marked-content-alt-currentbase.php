<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="page-af-alt"/></wp-export>';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$content = 'BT /F1 12 Tf '
    . '/Figure << /MCID 0 /Alt (Alt text for source-associated figure) >> BDC '
    . '72 720 Td (Noisy figure glyphs) Tj EMC ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R /PageLabels << /Nums [0 << /P (af-alt-) /S /D /St 9 >>] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 9 /Contents 4 0 R /AF [10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (page-source.xml) /Desc (Page source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602215000Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Figure /Figure >> /ParentTree 31 0 R /K [40 0 R] >>\nendobj\n"
    . "31 0 obj\n<< /Nums [9 [40 0 R]] >>\nendobj\n"
    . "40 0 obj\n<< /Type /StructElem /S /Figure /Pg 3 0 R /T (Figure structure row) /Alt (Structure review alt stays metadata) /K 0 >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

if (count($pageReviews) !== 1 || $lines !== ['Alt text for source-associated figure']) {
    throw new RuntimeException('Expected one page review row and one marked-content Alt paragraph.');
}

$pageReview = $pageReviews[0];
$rows = $pageReview['structure_marked_content'] ?? [];
$files = $rows[0]['page_associated_files'] ?? [];
if (($pageReview['page_associated_files'][0]['filename'] ?? null) !== 'page-source.xml'
    || ($rows[0]['page_associated_file_count'] ?? null) !== 1
    || ($files[0]['checksum_matches'] ?? null) !== true
    || array_key_exists('content', $files[0] ?? [])
) {
    throw new RuntimeException('Expected page-associated FileSpec review metadata on the marked-content row without payload exposure.');
}
if (str_contains($plainText, '<wp-export>')
    || str_contains($plainText, 'Noisy figure glyphs')
    || str_contains($plainText, 'page-source.xml')
    || str_contains($plainText, 'Structure review alt stays metadata')
) {
    throw new RuntimeException('Expected associated-file payload and review metadata to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-page-associated-marked-content-alt-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-page-associated-files-marked-content-review',
    'native_boundary' => 'page /AF FileSpec rows are attached to page StructTree MCID review rows while BDC /Alt supplies visible WordPress text',
    'source_truth' => [
        'upstream_marker_pdf_extract_text_dictionary_output',
        'pdf_2_associated_files_object_relationships',
        'marked_content_alt_actualtext_replacement_boundary',
    ],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'page_label' => $pageReview['page_label'] ?? null,
    'struct_parents' => $pageReview['struct_parents'] ?? null,
    'visible_lines' => $lines,
    'page_associated_files' => array_map(static fn (array $file): array => [
        'filename' => $file['filename'] ?? null,
        'relationship' => $file['relationship'] ?? null,
        'mime_type' => $file['mime_type'] ?? null,
        'checksum_matches' => $file['checksum_matches'] ?? null,
    ], $pageReview['page_associated_files'] ?? []),
    'marked_content_page_associated_file_count' => $rows[0]['page_associated_file_count'] ?? null,
    'visible_text_excludes_payload_and_review_metadata' => !str_contains($plainText, '<wp-export>')
        && !str_contains($plainText, 'Noisy figure glyphs')
        && !str_contains($plainText, 'page-source.xml')
        && !str_contains($plainText, 'Structure review alt stays metadata'),
]) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:page-associated-file-marked-content-review ' . $htmlJson([
    'pnum' => $pageReview['pnum'],
    'page_number' => $pageReview['page_number'] ?? null,
    'page_label' => $pageReview['page_label'] ?? null,
    'page_object' => $pageReview['page_object'],
    'structure_marked_content' => array_map(static fn (array $row): array => [
        'struct_object' => $row['struct_object'] ?? null,
        'raw_role' => $row['raw_role'] ?? null,
        'role' => $row['role'] ?? null,
        'mcid' => $row['mcid'] ?? null,
        'title' => $row['title'] ?? null,
        'alternate_text' => $row['alternate_text'] ?? null,
        'page_associated_file_count' => $row['page_associated_file_count'] ?? null,
        'page_associated_files' => array_map(static fn (array $file): array => [
            'filename' => $file['filename'] ?? null,
            'relationship' => $file['relationship'] ?? null,
            'checksum_matches' => $file['checksum_matches'] ?? null,
            'content_sha256' => $file['content_sha256'] ?? null,
        ], $row['page_associated_files'] ?? []),
        'review_only' => $row['review_only'] ?? null,
        'visible_text_source' => $row['visible_text_source'] ?? null,
    ], $rows),
]) . " -->\n";
