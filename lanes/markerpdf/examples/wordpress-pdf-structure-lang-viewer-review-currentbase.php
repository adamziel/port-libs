<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf '
    . '/DocSection << /MCID 0 >> BDC 72 720 Td (Visible heading glyphs) Tj EMC '
    . '/Lead << /MCID 1 >> BDC 72 704 Td (Visible body glyphs) Tj EMC ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /PageLayout /SinglePage /PageMode /UseOutlines /ViewerPreferences 7 0 R /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "7 0 obj\n<< /DisplayDocTitle true /Direction /L2R /PrintScaling /None >>\nendobj\n"
    . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap 24 0 R /Namespaces [25 0 R] /K [21 0 R 22 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Type /StructElem /S /Doc#53ection /Pg 3 0 R /Lang (fr-CA) /T (Resume Section) /ID (sec-1) /C [/chapter /featured] /R 2 /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
    . "22 0 obj\n<< /Type /StructElem /S /Lead /Pg 3 0 R /NS 25 0 R /Alt (Accessible abstract summary) /ActualText (Expanded actual text should stay review) /E (Content Management System) /K 1 >>\nendobj\n"
    . "24 0 obj\n<< /Doc#53ection /Sect /Lead /P >>\nendobj\n"
    . "25 0 obj\n<< /Type /Namespace /NS (https://example.test/wp-structure) /RoleMap << /Lead /P >> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$structure = $metadata['structure_tree'] ?? [];
$elements = $structure['elements'] ?? [];

echo '<!-- markerpdf-structure-lang-viewer-review-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'Catalog /Lang and /ViewerPreferences plus /StructTreeRoot structure-element language and alternate-text review metadata',
    'catalog_language' => $metadata['language'] ?? null,
    'page_mode' => $metadata['page_mode'] ?? null,
    'viewer_direction' => $metadata['viewer_preferences']['direction'] ?? null,
    'structure_languages' => $structure['languages'] ?? [],
    'roles' => array_column($elements, 'role'),
    'review_only' => ($structure['review_only'] ?? false) === true,
    'structure_visible_text_source' => $structure['visible_text_source'] ?? null,
    'review_text_leaked_to_paragraphs' => str_contains($plainText, 'Accessible abstract summary')
        || str_contains($plainText, 'Expanded actual text should stay review')
        || str_contains($plainText, 'Content Management System'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p lang="' . htmlspecialchars((string) ($metadata['language'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . nl2br(htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false)
    . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($elements as $element) {
    echo '<!-- markerpdf:structure-element-review ' . htmlspecialchars(json_encode([
        'object' => $element['object'] ?? null,
        'role' => $element['role'] ?? null,
        'raw_role' => $element['raw_role'] ?? null,
        'language' => $element['language'] ?? null,
        'language_inherited' => $element['language_inherited'] ?? null,
        'page_number' => $element['page_number'] ?? null,
        'mcids' => $element['mcids'] ?? [],
        'title' => $element['title'] ?? null,
        'id' => $element['id'] ?? null,
        'alternate_text' => $element['alternate_text'] ?? null,
        'actual_text' => $element['actual_text'] ?? null,
        'expansion_text' => $element['expansion_text'] ?? null,
        'classes' => $element['classes'] ?? [],
    ], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
}
