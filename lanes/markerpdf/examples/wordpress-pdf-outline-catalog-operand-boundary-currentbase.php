<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$pageText = 'BT /F1 12 Tf 72 720 Td (WordPress catalog outline operand boundary body) Tj ET';
$hiddenPayload = 'BT /F1 12 Tf 72 720 Td (WordPress ambiguous outline payload must stay hidden) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R 8 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Ambiguous Outline Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 9 0 R /A 12 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Outlines /First 11 0 R /Last 11 0 R /Count 1 >>\nendobj\n"
    . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Length " . strlen($hiddenPayload) . " >>\nstream\n{$hiddenPayload}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Title (WordPress Trailing Outline Operand) /Parent 8 0 R /Dest [3 0 R /Fit] >>\nendobj\n"
    . "12 0 obj\n<< /S /URI /URI (https://example.com/wordpress-ambiguous-outline) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$lightweight = $textExtractor->extractOutlineMetadata($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$review = $metadata['document_outline_boundary_review'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);
$encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

if (($review['status'] ?? null) !== 'rejected_malformed_catalog_outlines_operand') {
    throw new RuntimeException('Expected malformed catalog /Outlines operand review.');
}
if (array_key_exists('document_outline', $metadata) || $toc !== [] || ($lightweight['pdf_toc'] ?? []) !== []) {
    throw new RuntimeException('Expected ambiguous catalog /Outlines operands to suppress outline imports.');
}
if (($navigation['outline'] ?? []) !== [] || ($navigation['outline_action_review_actions'] ?? []) !== []) {
    throw new RuntimeException('Expected ambiguous catalog outline actions to stay out of navigation review.');
}
foreach ([$encodedMetadata, $encodedNavigation, $encodedLightweight] as $encoded) {
    if (!is_string($encoded)
        || str_contains($encoded, 'WordPress Ambiguous Outline Chapter')
        || str_contains($encoded, 'WordPress Trailing Outline Operand')
        || str_contains($encoded, 'wordpress-ambiguous-outline')
        || str_contains($encoded, $hiddenPayload)
    ) {
        throw new RuntimeException('Expected ambiguous outline metadata and action payloads to stay redacted.');
    }
}
if ($plainText !== 'WordPress catalog outline operand boundary body'
    || str_contains($plainText, 'WordPress Ambiguous Outline Chapter')
    || str_contains($plainText, 'WordPress Trailing Outline Operand')
    || str_contains($plainText, 'WordPress ambiguous outline payload must stay hidden')
) {
    throw new RuntimeException('Expected only page content to become visible WordPress text.');
}

echo '<!-- markerpdf-outline-catalog-operand-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-catalog-operand-boundary-currentbase',
    'support_component' => 'native-pdf-catalog-outline-operand-boundary',
    'native_boundary' => 'catalog /Outlines must be a single top-level PDF value before bookmark metadata can be imported',
    'review_status' => $review['status'] ?? null,
    'outlines_operand_count' => $review['outlines_operand_count'] ?? null,
    'selected_outline_root_object' => $review['selected_outline_root_object'] ?? null,
    'trailing_reference_object_numbers' => $review['trailing_reference_object_numbers'] ?? [],
    'toc_suppressed' => $toc === [],
    'lightweight_toc_suppressed' => ($lightweight['pdf_toc'] ?? []) === [],
    'navigation_actions_suppressed' => ($navigation['outline_action_review_actions'] ?? []) === [],
    'payload_redacted' => is_string($encodedMetadata) && !str_contains($encodedMetadata, $hiddenPayload),
    'visible_text_imported' => $plainText === 'WordPress catalog outline operand boundary body',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
