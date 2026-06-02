<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="44"/></wp-export>';
$previewPayload = 'BT /F1 12 Tf 72 720 Td (Associated Transition Payload Leak) Tj ET';
$pageText = 'BT /F1 12 Tf 72 720 Td (Associated Transition Review) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels << /Nums [0 << /P (deck-) /S /D /St 7 >>] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Dur 12 /Trans 5 0 R /AA << /O 6 0 R /C << /S /URI /URI (javascript:alert\\(1\\)) /Next 7 0 R >> >> /AF [10 0 R << /Type /Filespec /UF (preview.pdf) /Desc (Rendered slide preview) /AFRelationship /Alternative /EF << /UF 15 0 R >> >>] >>\nendobj\n"
    . "5 0 obj\n<< /S /Fly /D 0.75 /Dm /V /M /I /Di 270 /SS 0.8 /B false >>\nendobj\n"
    . "6 0 obj\n<< /S /URI /URI (https://example.com/deck-notes) >>\nendobj\n"
    . "7 0 obj\n<< /S /GoToR /F (appendix.pdf) /D (Slide 8) /NewWindow true >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Original WordPress export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Length " . strlen($previewPayload) . " >>\nstream\n{$previewPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));

if (count($pageReviews) !== 1) {
    throw new RuntimeException('Expected one page review row.');
}

$pageReview = $pageReviews[0];
$pagePresentation = $pageReview['page_presentation'] ?? null;
if (!is_array($pagePresentation)) {
    throw new RuntimeException('Expected page presentation metadata on the page review row.');
}
if (count($pageReview['page_associated_files'] ?? []) !== 2) {
    throw new RuntimeException('Expected page Source and Alternative associated files.');
}
if (($pagePresentation['transition']['style'] ?? null) !== 'Fly') {
    throw new RuntimeException('Expected indirect Fly transition metadata.');
}
if (array_column($pagePresentation['actions'] ?? [], 'safety') !== ['review-uri', 'blocked-unsafe-uri', 'remote-document-review']) {
    throw new RuntimeException('Expected review-only URI, unsafe URI, and remote GoTo action metadata.');
}
if (str_contains($plainText, '<wp-export>') || str_contains($plainText, 'Associated Transition Payload Leak')) {
    throw new RuntimeException('Expected page associated-file payloads to stay out of visible text.');
}
if (str_contains($plainText, 'javascript:alert') || str_contains($plainText, 'appendix.pdf')) {
    throw new RuntimeException('Expected page action operands to stay out of visible text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-pdf-page-associated-transition-action-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-page-associated-transition-action-review-parser',
    'native_boundary' => 'page /AF Filespec rows composed with page /Dur /Trans /AA review metadata before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions_on_import' => false,
    'page_review_count' => count($pageReviews),
    'page_label' => $pagePresentation['page_label'] ?? null,
    'page_associated_relationships' => array_column($pageReview['page_associated_files'] ?? [], 'relationship'),
    'transition_style' => $pagePresentation['transition']['style'] ?? null,
    'action_types' => array_column($pagePresentation['actions'] ?? [], 'action_type'),
    'action_safety' => array_column($pagePresentation['actions'] ?? [], 'safety'),
    'all_actions_review_only' => count(array_filter(
        $pagePresentation['actions'] ?? [],
        static fn (array $action): bool => ($action['executes_on_import'] ?? true) === false
    )) === count($pagePresentation['actions'] ?? []),
    'excluded_associated_payload_text' => !str_contains($plainText, '<wp-export>')
        && !str_contains($plainText, 'Associated Transition Payload Leak'),
    'excluded_action_operand_text' => !str_contains($plainText, 'javascript:alert')
        && !str_contains($plainText, 'appendix.pdf'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:page-associated-transition-action-review ' . $htmlJson([
    'pnum' => $pageReview['pnum'],
    'page_object' => $pageReview['page_object'],
    'page_associated_files' => array_map(static fn (array $file): array => [
        'filename' => $file['filename'] ?? null,
        'relationship' => $file['relationship'] ?? null,
        'mime_type' => $file['mime_type'] ?? null,
        'size' => $file['size'] ?? null,
        'declared_size' => $file['declared_size'] ?? null,
        'content_sha256' => $file['content_sha256'] ?? null,
    ], $pageReview['page_associated_files'] ?? []),
    'page_presentation' => $pagePresentation,
]) . " -->\n";
