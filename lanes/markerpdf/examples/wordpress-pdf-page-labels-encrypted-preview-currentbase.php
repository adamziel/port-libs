<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Encrypted page label preview leak) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 612 792] /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "10 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Nums [0 << /P (Secret-) /S /D /St 9 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Filter /Standard /V 1 /R 2 /P -44 /O <00010203> /U <04050607> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 30 0 R >>\n%%EOF\n";

$extractor = new PdfTextExtractor();
$preview = new MarkerAppPreview();
$security = (new PdfSecurityPreflight())->analyze($pdf);
$summary = $preview->openPdfSummary($pdf);
$previewLabels = $preview->pageLabels($pdf);

if (!$extractor->isEncrypted($pdf) || $extractor->extractPageLabels($pdf) !== []) {
    throw new RuntimeException('Expected encrypted PageLabels to stay blocked in text extraction.');
}

if ($previewLabels !== ['1'] || ($summary['pages'][0]['page_label'] ?? null) !== '1') {
    throw new RuntimeException('Expected preview metadata to use physical labels for encrypted PDFs.');
}

if (str_contains(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '', 'Secret-9')) {
    throw new RuntimeException('Expected encrypted catalog PageLabels to stay out of preview metadata.');
}

echo '<!-- markerpdf-page-labels-encrypted-preview-boundary ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'encrypted catalog /PageLabels remain blocked from marker_app preview metadata until decryption is available',
    'encrypted' => $security['encrypted'] ?? null,
    'content_extraction_allowed' => $security['content_extraction_allowed'] ?? null,
    'text_extraction_policy' => $security['text_extraction_policy'] ?? null,
    'text_extractor_page_labels' => $extractor->extractPageLabels($pdf),
    'preview_page_labels' => $previewLabels,
    'catalog_label_leaked_to_preview' => in_array('Secret-9', $previewLabels, true),
    'raw_encrypted_text_exposed' => str_contains($extractor->extractPlainText($pdf), 'Encrypted page label preview leak'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Import Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";
echo '<!-- wp:paragraph -->' . "\n";
echo '<p>PDF page 1 requires decryption before text or catalog page labels can be imported.</p>' . "\n";
echo "<!-- /wp:paragraph -->\n";
