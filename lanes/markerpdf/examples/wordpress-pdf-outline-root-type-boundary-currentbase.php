<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$pageContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline root type boundary page body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 3 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Page Root Spoofed Outline) /Parent 3 0 R /Dest /SpoofedTarget /A 12 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToR /F (wordpress-spoofed-outline-root.pdf) /D (spoofed-target) >>\nendobj\n"
    . "20 0 obj\n<< /Names [(SpoofedTarget) [3 0 R /Fit]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (array_key_exists('document_outline', $metadata)) {
    throw new RuntimeException('Expected typed non-Outlines catalog root to be excluded from document outline metadata.');
}
if ($toc !== [] || ($navigation['outline'] ?? []) !== [] || ($navigation['outline_action_review_actions'] ?? []) !== [] || $remoteActions !== []) {
    throw new RuntimeException('Expected typed non-Outlines catalog root to be excluded from TOC, navigation, and action review.');
}
if (($metadata['document_destinations']['names'] ?? []) !== ['SpoofedTarget']) {
    throw new RuntimeException('Expected catalog destination name tree to remain reviewable.');
}
if (!is_string($metadataEncoded) || str_contains($metadataEncoded, 'WordPress Page Root Spoofed Outline') || str_contains($metadataEncoded, 'wordpress-spoofed-outline-root.pdf')) {
    throw new RuntimeException('Expected spoofed outline title and remote action payload to stay out of metadata.');
}
if (!is_string($navigationEncoded) || str_contains($navigationEncoded, 'WordPress Page Root Spoofed Outline') || str_contains($navigationEncoded, 'wordpress-spoofed-outline-root.pdf')) {
    throw new RuntimeException('Expected spoofed outline title and remote action payload to stay out of navigation review.');
}
if (str_contains($plainText, 'WordPress Page Root Spoofed Outline') || str_contains($plainText, 'wordpress-spoofed-outline-root.pdf')) {
    throw new RuntimeException('Expected spoofed outline metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-root-type-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-root-type-boundary-currentbase',
    'support_component' => 'native-pdf-catalog-outline-root-review',
    'native_boundary' => 'catalog /Outlines dictionaries with explicit non-Outlines /Type fail closed before TOC/navigation/action review',
    'page_mode' => $metadata['page_mode'] ?? null,
    'destination_names' => $metadata['document_destinations']['names'] ?? [],
    'document_outline_present' => array_key_exists('document_outline', $metadata),
    'toc_count' => count($toc),
    'navigation_outline_count' => count($navigation['outline'] ?? []),
    'outline_action_review_count' => count($navigation['outline_action_review_actions'] ?? []),
    'remote_action_count' => count($remoteActions),
    'spoofed_outline_excluded' => is_string($metadataEncoded)
        && !str_contains($metadataEncoded, 'WordPress Page Root Spoofed Outline')
        && is_string($navigationEncoded)
        && !str_contains($navigationEncoded, 'WordPress Page Root Spoofed Outline'),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'WordPress Page Root Spoofed Outline')
        && !str_contains($plainText, 'wordpress-spoofed-outline-root.pdf'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
