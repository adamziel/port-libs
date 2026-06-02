<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Page Property Review) Tj ET';
$sourceXml = '<wp-export><post id="20"/></wp-export>';
$previewText = 'BT /F1 12 Tf 72 720 Td (Page Associated Payload Leak) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true /UserProperties true /Suspects true >> /StructTreeRoot 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /PieceInfo << /WPImporter << /LastModified (D:20260602071200Z) /Private << /Template (landing) /BatchId (wp-page-7) /NeedsReview true >> >> >> /AF [10 0 R << /Type /Filespec /UF (preview.pdf) /Desc (Rendered preview) /AFRelationship /Alternative /EF << /UF 15 0 R >> >>] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Original WordPress export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourceXml) . " >> /Length " . strlen($sourceXml) . " >>\nstream\n{$sourceXml}\nendstream\nendobj\n"
    . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Length " . strlen($previewText) . " >>\nstream\n{$previewText}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /StructTreeRoot /K 21 0 R >>\nendobj\n"
    . "21 0 obj\n<< /Type /StructElem /S /Figure /T (Hero image) /Pg 3 0 R /A 22 0 R /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
    . "22 0 obj\n<< /O /UserProperties /P [<< /N (WP Block) /V (core/image) /F (Image block) >> << /N (Supplier) /V (Migration App) /H true >>] >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));

if (count($pageReviews) !== 1) {
    throw new RuntimeException('Expected one page review metadata row.');
}

$pageReview = $pageReviews[0];
if (($pageReview['piece_info']['WPImporter']['private']['BatchId'] ?? null) !== 'wp-page-7') {
    throw new RuntimeException('Expected page PieceInfo private batch metadata.');
}
if (($pageReview['user_properties'][0]['value'] ?? null) !== 'core/image') {
    throw new RuntimeException('Expected tagged-PDF UserProperties review metadata.');
}
if (($pageReview['mark_info']['suspects'] ?? null) !== true) {
    throw new RuntimeException('Expected catalog MarkInfo suspect flag review metadata.');
}
if (count($pageReview['page_associated_files'] ?? []) !== 2) {
    throw new RuntimeException('Expected page-associated Source and Alternative files.');
}
if (str_contains($plainText, 'wp-export') || str_contains($plainText, 'Page Associated Payload Leak')) {
    throw new RuntimeException('Expected page associated-file payloads to stay out of visible text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-pdf-page-pieceinfo-userproperties-smoke ' . $htmlJson([
    'support_component' => 'native-pdf-page-pieceinfo-userproperties-parser',
    'native_boundary' => 'page /PieceInfo plus tagged PDF /UserProperties review metadata before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'page_review_count' => count($pageReviews),
    'mark_info' => $pageReview['mark_info'] ?? [],
    'piece_info_apps' => array_keys($pageReview['piece_info'] ?? []),
    'user_property_names' => array_map(
        static fn (array $property): string => (string) ($property['name'] ?? ''),
        $pageReview['user_properties'] ?? []
    ),
    'hidden_user_property_count' => count(array_filter(
        $pageReview['user_properties'] ?? [],
        static fn (array $property): bool => ($property['hidden'] ?? false) === true
    )),
    'page_associated_relationships' => array_map(
        static fn (array $file): ?string => $file['relationship'] ?? null,
        $pageReview['page_associated_files'] ?? []
    ),
    'excluded_page_associated_payload_text' => !str_contains($plainText, 'wp-export')
        && !str_contains($plainText, 'Page Associated Payload Leak'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:page-review ' . $htmlJson([
    'pnum' => $pageReview['pnum'],
    'page_object' => $pageReview['page_object'],
    'mark_info' => $pageReview['mark_info'] ?? [],
    'piece_info' => $pageReview['piece_info'] ?? [],
    'page_associated_files' => array_map(static fn (array $file): array => [
        'name' => $file['name'] ?? null,
        'filename' => $file['filename'] ?? null,
        'description' => $file['description'] ?? null,
        'relationship' => $file['relationship'] ?? null,
        'mime_type' => $file['mime_type'] ?? null,
        'size' => $file['size'] ?? null,
        'declared_size' => $file['declared_size'] ?? null,
        'content_sha256' => $file['content_sha256'] ?? null,
    ], $pageReview['page_associated_files'] ?? []),
    'user_properties' => $pageReview['user_properties'] ?? [],
]) . " -->\n";
