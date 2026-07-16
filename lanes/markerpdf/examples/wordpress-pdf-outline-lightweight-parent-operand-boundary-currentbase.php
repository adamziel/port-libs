<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$pageText = 'BT /F1 12 Tf 72 720 Td (WordPress lightweight parent operand body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Malformed WordPress Parent Operand Chapter) /Parent 5 0 R 9 0 R /Dest [3 0 R /FitH 720] /A 12 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Type /Outlines /First 10 0 R /Last 10 0 R /Count 1 >>\nendobj\n"
    . "10 0 obj\n<< /Title (Trailing WordPress Parent Operand Decoy) /Parent 9 0 R /Dest [3 0 R /Fit] >>\nendobj\n"
    . "12 0 obj\n<< /S /URI /URI (https://example.com/wordpress-malformed-parent-outline) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Title (WordPress Parent Operand Info) /Author (Current Outline Metadata Team) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 40 0 R >>\n%%EOF";

$textExtractor = new PdfTextExtractor();
$outlineExtractor = new PdfOutlineExtractor();
$lightweight = $textExtractor->extractOutlineMetadata($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($lightweight['pdf_toc'] ?? []) !== []) {
    throw new RuntimeException('Expected malformed /Parent operand to stay out of lightweight pdf_toc metadata.');
}
if ($toc !== [] || ($navigation['outline'] ?? []) !== []) {
    throw new RuntimeException('Expected malformed /Parent operand to stay out of navigation review metadata.');
}
if (($metadata['document_outline']['titles'] ?? []) !== []) {
    throw new RuntimeException('Expected document outline metadata to reject malformed /Parent operand rows.');
}
if (($lightweight['document_info']['title'] ?? null) !== 'WordPress Parent Operand Info') {
    throw new RuntimeException('Expected current trailer Info metadata to remain available.');
}
if (!is_string($encodedLightweight) || str_contains($encodedLightweight, 'Malformed WordPress Parent Operand Chapter')) {
    throw new RuntimeException('Expected malformed outline title to stay out of lightweight metadata.');
}
if (
    !is_string($encodedMetadata)
    || !is_string($encodedNavigation)
    || str_contains($encodedMetadata, 'wordpress-malformed-parent-outline')
    || str_contains($encodedNavigation, 'wordpress-malformed-parent-outline')
) {
    throw new RuntimeException('Expected malformed outline action target to stay out of review metadata.');
}
if (
    str_contains($plainText, 'Malformed WordPress Parent Operand Chapter')
    || str_contains($plainText, 'Trailing WordPress Parent Operand Decoy')
) {
    throw new RuntimeException('Expected outline titles to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-lightweight-parent-operand-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-lightweight-parent-operand-boundary-currentbase',
    'support_component' => 'native-pdf-lightweight-outline-parent-boundary',
    'native_boundary' => 'outline /Parent references with trailing top-level operands are rejected before WordPress TOC and navigation promotion',
    'pages' => $lightweight['pages'],
    'document_info_title' => $lightweight['document_info']['title'] ?? null,
    'pdf_toc_titles' => array_column($lightweight['pdf_toc'] ?? [], 'title'),
    'navigation_outline_count' => count($navigation['outline'] ?? []),
    'document_outline_item_count' => $metadata['document_outline']['item_count'] ?? null,
    'malformed_parent_operand_rejected' => ($lightweight['pdf_toc'] ?? []) === []
        && $toc === []
        && ($navigation['outline'] ?? []) === []
        && ($metadata['document_outline']['titles'] ?? []) === [],
    'trailing_parent_decoy_excluded' => is_string($encodedLightweight)
        && !str_contains($encodedLightweight, 'Trailing WordPress Parent Operand Decoy'),
    'malformed_action_excluded' => is_string($encodedNavigation)
        && !str_contains($encodedNavigation, 'wordpress-malformed-parent-outline'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'Malformed WordPress Parent Operand Chapter')
        && !str_contains($plainText, 'Trailing WordPress Parent Operand Decoy'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul></ul></nav>\n<!-- /wp:navigation -->\n";
