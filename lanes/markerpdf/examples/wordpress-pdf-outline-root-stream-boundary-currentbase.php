<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline root stream visible body) Tj ET';
$rootPayload = 'BT /F1 12 Tf 72 720 Td (WordPress stream root payload must stay hidden) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Length " . strlen($rootPayload) . " >>\nstream\n{$rootPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Rejected Stream Root Outline) /Parent 5 0 R /Dest [3 0 R /FitH 720] /A 12 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /JavaScript /JS (app.alert\\('wordpress stream root outline action leak'\\)) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$lightweight = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);
$encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

if (array_key_exists('document_outline', $metadata) || array_key_exists('document_outline', $metadata['catalog'] ?? [])) {
    throw new RuntimeException('Stream-backed outline roots must not become document outline metadata.');
}
if ($toc !== [] || ($navigation['outline'] ?? []) !== [] || ($lightweight['pdf_toc'] ?? []) !== []) {
    throw new RuntimeException('Stream-backed outline roots must not become TOC or navigation rows.');
}
if (!is_string($encodedMetadata)
    || !is_string($encodedNavigation)
    || !is_string($encodedLightweight)
    || str_contains($encodedMetadata, 'WordPress Rejected Stream Root Outline')
    || str_contains($encodedNavigation, 'WordPress Rejected Stream Root Outline')
    || str_contains($encodedLightweight, 'WordPress Rejected Stream Root Outline')
    || str_contains($encodedNavigation, 'wordpress stream root outline action leak')
) {
    throw new RuntimeException('Rejected stream-root outline payload leaked into review metadata.');
}
if ($plainText !== 'WordPress outline root stream visible body'
    || str_contains($plainText, 'WordPress Rejected Stream Root Outline')
    || str_contains($plainText, $rootPayload)
) {
    throw new RuntimeException('Visible WordPress text must stay page-content-only for stream-root outline PDFs.');
}

echo '<!-- markerpdf-outline-root-stream-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-root-stream-boundary-currentbase',
    'support_component' => 'native-pdf-outline-single-dictionary-root-boundary',
    'native_boundary' => 'catalog /Outlines references must resolve to single top-level dictionaries, not stream carriers',
    'page_mode_preserved' => ($metadata['page_mode'] ?? null) === 'UseOutlines',
    'document_outline_absent' => !array_key_exists('document_outline', $metadata),
    'toc_empty' => $toc === [],
    'navigation_outline_empty' => ($navigation['outline'] ?? []) === [],
    'lightweight_toc_empty' => ($lightweight['pdf_toc'] ?? []) === [],
    'stream_root_payload_excluded' => is_string($encodedMetadata)
        && !str_contains($encodedMetadata, $rootPayload)
        && !str_contains($encodedNavigation, $rootPayload)
        && !str_contains($plainText, $rootPayload),
    'stream_root_action_excluded' => is_string($encodedNavigation)
        && !str_contains($encodedNavigation, 'wordpress stream root outline action leak'),
    'visible_text_imported' => $plainText === 'WordPress outline root stream visible body',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
