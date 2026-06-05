<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$pageText = 'BT /F1 12 Tf /ChapterTitle << /MCID 0 >> BDC 72 720 Td (WordPress visible tagged outline section) Tj EMC ET';
$payload = '<wp-export><post id="outline-se-wordpress"/></wp-export>';
$checksum = strtoupper(hash('md5', $payload));

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /MarkInfo << /Marked true >> /StructTreeRoot 50 0 R /Outlines 40 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 7 0 R >> >> /Contents 30 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Outlines /First 41 0 R /Last 41 0 R /Count 1 >>\nendobj\n"
    . "41 0 obj\n<< /Title (WordPress outline SE review) /Parent 40 0 R /Dest [3 0 R /FitH 720] /SE 60 0 R /F 2 >>\nendobj\n"
    . "50 0 obj\n<< /Type /StructTreeRoot /RoleMap << /ChapterTitle /H1 >> /ParentTree 55 0 R /K [60 0 R] >>\nendobj\n"
    . "55 0 obj\n<< /Nums [0 [60 0 R]] >>\nendobj\n"
    . "60 0 obj\n<< /Type /StructElem /S /ChapterTitle /P 50 0 R /Pg 3 0 R /Lang (en-GB) /T (WordPress outline structure title) /ID (wp-outline-se-1) /Alt (Accessible WordPress outline summary) /AF [70 0 R] /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
    . "70 0 obj\n<< /Type /Filespec /F (outline-source.xml) /Desc (WordPress outline source payload) /AFRelationship /Source /EF << /F 71 0 R >> >>\nendobj\n"
    . "71 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$item = $outline['items'][0] ?? [];
$structure = $item['structure_element'] ?? [];
$file = $structure['associated_files'][0] ?? [];
$navigationItem = $navigation['outline'][0] ?? [];
$navigationStructure = $navigationItem['structure_element'] ?? [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($structure['source'] ?? null) !== 'outline_item_structure_element') {
    throw new RuntimeException('Expected outline /SE structure-element metadata.');
}
if (($structure['role'] ?? null) !== 'H1' || ($structure['mcids'] ?? []) !== [0]) {
    throw new RuntimeException('Expected mapped H1 role and MCID summary.');
}
if (($file['filename'] ?? null) !== 'outline-source.xml' || ($file['checksum_matches'] ?? null) !== true) {
    throw new RuntimeException('Expected structure-element associated FileSpec checksum metadata.');
}
if (!is_string($encoded) || str_contains($encoded, $payload)) {
    throw new RuntimeException('Expected associated FileSpec payload bytes to stay review-only.');
}
if (($navigationStructure['source'] ?? null) !== 'outline_item_structure_element') {
    throw new RuntimeException('Expected navigation review to carry outline /SE structure metadata.');
}
if (($navigationItem['structure_element_role'] ?? null) !== 'H1' || ($navigationItem['structure_element_mcids'] ?? []) !== [0]) {
    throw new RuntimeException('Expected navigation review to carry mapped /SE role and MCID metadata.');
}
if (!is_string($navigationEncoded) || str_contains($navigationEncoded, $payload)) {
    throw new RuntimeException('Expected navigation review metadata to omit associated FileSpec payload bytes.');
}
if (str_contains($plainText, 'WordPress outline SE review')
    || str_contains($plainText, 'WordPress outline structure title')
    || str_contains($plainText, 'Accessible WordPress outline summary')
    || str_contains($plainText, '<wp-export>')
) {
    throw new RuntimeException('Expected outline /SE metadata and payload to stay out of visible text.');
}

echo '<!-- markerpdf-outline-structure-element-metadata-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-structure-element-metadata-currentbase',
    'support_component' => 'native-pdf-outline-structure-element-review',
    'native_boundary' => 'outline item /SE is review-only structure metadata with associated-file hashes, not WordPress paragraph text',
    'outline_title' => $item['title'] ?? null,
    'outline_page_number' => $item['page_number'] ?? null,
    'structure_element_object' => $structure['object'] ?? null,
    'structure_role' => $structure['role'] ?? null,
    'structure_mcids' => $structure['mcids'] ?? [],
    'associated_filename' => $file['filename'] ?? null,
    'associated_checksum_matches' => $file['checksum_matches'] ?? null,
    'navigation_outline_structure_role' => $navigationItem['structure_element_role'] ?? null,
    'navigation_outline_structure_mcids' => $navigationItem['structure_element_mcids'] ?? [],
    'payload_content_omitted' => is_string($encoded) && !str_contains($encoded, $payload),
    'navigation_payload_content_omitted' => is_string($navigationEncoded) && !str_contains($navigationEncoded, $payload),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'WordPress outline SE review')
        && !str_contains($plainText, 'Accessible WordPress outline summary'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";

echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline structure review\"><ul>\n";
echo '<li data-marker-outline-structure-role="' . htmlspecialchars((string) ($structure['role'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '" data-marker-outline-associated-file="' . htmlspecialchars((string) ($file['filename'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
