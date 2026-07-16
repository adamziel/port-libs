<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline root traversal review visible body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R 9 0 R /Last 6 0 R /Count 1 11 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress suppressed outline chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /A 12 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Type /Outlines /First 10 0 R /Last 10 0 R /Count 1 >>\nendobj\n"
    . "10 0 obj\n<< /Title (WordPress hidden first operand decoy) /Parent 9 0 R /Dest [3 0 R /Fit] >>\nendobj\n"
    . "11 0 obj\n<< /Type /Outlines /First 13 0 R /Last 13 0 R /Count 1 >>\nendobj\n"
    . "12 0 obj\n<< /S /URI /URI (https://example.com/wordpress-outline-root-traversal-review) >>\nendobj\n"
    . "13 0 obj\n<< /Title (WordPress hidden count operand decoy) /Parent 11 0 R /Dest [3 0 R /Fit] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$review = $outline['outline_root_traversal_operand_boundary_review'] ?? [];
$navigationReview = $navigation['outline_root_review']['outline_root_traversal_operand_boundary_review'] ?? [];
$encodedReview = json_encode([$review, $navigationReview], JSON_UNESCAPED_SLASHES);

if (($outline['outline_root_traversal_operand_boundary_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected two malformed outline root traversal operand reviews.');
}
if (($outline['outline_root_traversal_operand_boundary_keys'] ?? null) !== ['First', 'Count']) {
    throw new RuntimeException('Expected malformed /First and /Count root traversal keys.');
}
if (($review['trailing_reference_object_numbers'] ?? null) !== [9, 11]) {
    throw new RuntimeException('Expected root traversal trailing references to remain review-only metadata.');
}
if (($navigationReview['source'] ?? null) !== 'outline_root_traversal_operand_boundary') {
    throw new RuntimeException('Expected navigation review to carry outline root traversal boundary metadata.');
}
if (($navigation['outline'] ?? []) !== [] || ($navigation['outline_action_review_actions'] ?? []) !== []) {
    throw new RuntimeException('Expected malformed root traversal operands to suppress outline navigation promotion.');
}
if (!is_string($encodedReview)
    || str_contains($encodedReview, 'WordPress suppressed outline chapter')
    || str_contains($encodedReview, 'WordPress hidden first operand decoy')
    || str_contains($encodedReview, 'WordPress hidden count operand decoy')
    || str_contains($encodedReview, 'wordpress-outline-root-traversal-review')
) {
    throw new RuntimeException('Expected root traversal review to omit hidden titles and action payload text.');
}
if (str_contains($plainText, 'WordPress suppressed outline chapter')
    || str_contains($plainText, 'WordPress hidden first operand decoy')
    || str_contains($plainText, 'WordPress hidden count operand decoy')
    || str_contains($plainText, 'wordpress-outline-root-traversal-review')
) {
    throw new RuntimeException('Expected visible WordPress text to exclude malformed outline root operands.');
}

echo '<!-- markerpdf-outline-root-traversal-review-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-root-traversal-review-currentbase',
    'support_component' => 'native-pdf-outline-root-traversal-review',
    'native_boundary' => 'outline root /First and /Count trailing operands are review-only and suppress TOC promotion',
    'boundary_count' => $outline['outline_root_traversal_operand_boundary_count'] ?? null,
    'boundary_keys' => $outline['outline_root_traversal_operand_boundary_keys'] ?? [],
    'boundary_statuses' => $outline['outline_root_traversal_operand_boundary_statuses'] ?? [],
    'trailing_references' => $review['trailing_reference_object_numbers'] ?? [],
    'navigation_carries_root_review' => ($navigationReview['source'] ?? null) === 'outline_root_traversal_operand_boundary',
    'outline_rows_promoted' => count($navigation['outline'] ?? []),
    'action_rows_promoted' => count($navigation['outline_action_review_actions'] ?? []),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'WordPress suppressed outline chapter')
        && !str_contains($plainText, 'WordPress hidden count operand decoy'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";

echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline root traversal review\"><ul>\n";
echo '<li data-marker-outline-root-boundary="' . htmlspecialchars(implode(',', $outline['outline_root_traversal_operand_boundary_keys'] ?? []), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '" data-marker-outline-root-promoted="false">'
    . 'Outline root traversal requires review</li>' . "\n";
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
