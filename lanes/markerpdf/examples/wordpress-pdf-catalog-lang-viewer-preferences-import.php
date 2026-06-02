<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$lang = strtoupper(bin2hex("\xfe\xff\x00f\x00r\x00-\x00C\x00A"));
$content = 'BT /F1 12 Tf 72 720 Td (Catalog Language Import) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang <{$lang}> /PageLayout /TwoPageRight /PageMode /UseOutlines /ViewerPreferences 7 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "7 0 obj\n<< /DisplayDocTitle true /HideToolbar true /Direction /R2L /PrintScaling /None /Duplex /DuplexFlipLongEdge /PrintPageRange [1 2] /NumCopies 2 >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$viewerPreferences = $metadata['viewer_preferences'] ?? [];

echo '<!-- markerpdf-catalog-lang-viewer-preferences-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF Catalog /Lang, /PageLayout, /PageMode, and /ViewerPreferences review metadata before WordPress rendering',
    'source' => $metadata['source'],
    'language' => $metadata['language'] ?? null,
    'page_layout' => $metadata['page_layout'] ?? null,
    'page_mode' => $metadata['page_mode'] ?? null,
    'viewer_preferences' => $viewerPreferences,
    'catalog_language_used_as_ocr_override' => array_key_exists('languages', $metadata),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p lang="' . htmlspecialchars((string) ($metadata['language'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:catalog-review ' . htmlspecialchars(json_encode([
    'page_layout' => $metadata['page_layout'] ?? null,
    'page_mode' => $metadata['page_mode'] ?? null,
    'display_doc_title' => $viewerPreferences['display_doc_title'] ?? null,
    'print_scaling' => $viewerPreferences['print_scaling'] ?? null,
    'duplex' => $viewerPreferences['duplex'] ?? null,
    'print_page_range' => $viewerPreferences['print_page_range'] ?? [],
    'num_copies' => $viewerPreferences['num_copies'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
