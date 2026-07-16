<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="72"/></wp-export>';
$previewPayload = '{"preview":"edited-after-checksum"}';
$pageText = 'BT /F1 12 Tf 72 720 Td (Page Attachment Checksum Review) Tj ET';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$staleChecksum = str_repeat('0a', 16);

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true /UserProperties true /Suspects false >> /StructTreeRoot 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /PieceInfo << /WPImport << /LastModified (D:20260602162900Z) /Private << /BatchId (page-72) /NeedsReview true >> >> >> /AF [10 0 R 12 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Original WordPress export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /CreationDate (D:20260602162800Z) /ModDate (D:20260602162930Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Filespec /UF (preview.json) /Desc (Generated page preview) /AFRelationship /Alternative /EF << /UF 13 0 R >> >>\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($previewPayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($previewPayload) . " >>\nstream\n{$previewPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /StructTreeRoot /K 21 0 R >>\nendobj\n"
    . "21 0 obj\n<< /Type /StructElem /S /Figure /T (Attachment figure) /Pg 3 0 R /A 22 0 R /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
    . "22 0 obj\n<< /O /UserProperties /P [<< /N (WP Block) /V (core/file) /F (File block) >> << /N (Needs Attachment Review) /V true /H true >>] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));

if (count($pageReviews) !== 1) {
    throw new RuntimeException('Expected one page review row.');
}

$pageReview = $pageReviews[0];
$associatedFiles = $pageReview['page_associated_files'] ?? [];
if (count($associatedFiles) !== 2) {
    throw new RuntimeException('Expected Source and Alternative page-associated files.');
}
if (($pageReview['mark_info']['user_properties'] ?? null) !== true) {
    throw new RuntimeException('Expected MarkInfo UserProperties review flag.');
}
if (($pageReview['piece_info']['WPImport']['private']['BatchId'] ?? null) !== 'page-72') {
    throw new RuntimeException('Expected page PieceInfo import batch metadata.');
}
if (array_column($pageReview['user_properties'] ?? [], 'name') !== ['WP Block', 'Needs Attachment Review']) {
    throw new RuntimeException('Expected tagged-PDF UserProperties metadata.');
}
if (($associatedFiles[0]['checksum_matches'] ?? null) !== true || ($associatedFiles[1]['checksum_matches'] ?? null) !== false) {
    throw new RuntimeException('Expected verified Source checksum and stale Alternative checksum states.');
}
if (array_key_exists('content', $associatedFiles[0]) || array_key_exists('content', $associatedFiles[1])) {
    throw new RuntimeException('Expected page associated-file rows to omit raw payload content.');
}
if (str_contains($plainText, '<wp-export>') || str_contains($plainText, 'edited-after-checksum')) {
    throw new RuntimeException('Expected page associated-file payloads to stay out of visible text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-pdf-page-associated-markinfo-userproperties-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-page-associated-markinfo-userproperties-review-parser',
    'native_boundary' => 'page /AF embedded-file Params checksums composed with catalog /MarkInfo, page /PieceInfo, and tagged PDF /UserProperties before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'page_review_count' => count($pageReviews),
    'mark_info' => $pageReview['mark_info'] ?? [],
    'piece_info_apps' => array_keys($pageReview['piece_info'] ?? []),
    'user_property_names' => array_column($pageReview['user_properties'] ?? [], 'name'),
    'page_associated_relationships' => array_column($associatedFiles, 'relationship'),
    'page_associated_checksum_matches' => array_map(
        static fn (array $file): ?bool => $file['checksum_matches'] ?? null,
        $associatedFiles
    ),
    'page_associated_checksum_algorithms' => array_map(
        static fn (array $file): ?string => $file['checksum_algorithm'] ?? null,
        $associatedFiles
    ),
    'raw_associated_content_exposed' => array_key_exists('content', $associatedFiles[0])
        || array_key_exists('content', $associatedFiles[1]),
    'excluded_page_associated_payload_text' => !str_contains($plainText, '<wp-export>')
        && !str_contains($plainText, 'edited-after-checksum'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:page-associated-markinfo-userproperties-review ' . $htmlJson([
    'pnum' => $pageReview['pnum'],
    'page_object' => $pageReview['page_object'],
    'mark_info' => $pageReview['mark_info'] ?? [],
    'piece_info' => $pageReview['piece_info'] ?? [],
    'user_properties' => $pageReview['user_properties'] ?? [],
    'page_associated_files' => array_map(static fn (array $file): array => [
        'filename' => $file['filename'] ?? null,
        'relationship' => $file['relationship'] ?? null,
        'mime_type' => $file['mime_type'] ?? null,
        'size' => $file['size'] ?? null,
        'declared_size' => $file['declared_size'] ?? null,
        'content_sha256' => $file['content_sha256'] ?? null,
        'checksum' => $file['checksum'] ?? null,
        'checksum_algorithm' => $file['checksum_algorithm'] ?? null,
        'computed_checksum' => $file['computed_checksum'] ?? null,
        'checksum_matches' => $file['checksum_matches'] ?? null,
        'created_at' => $file['created_at'] ?? null,
        'modified_at' => $file['modified_at'] ?? null,
    ], $associatedFiles),
]) . " -->\n";
