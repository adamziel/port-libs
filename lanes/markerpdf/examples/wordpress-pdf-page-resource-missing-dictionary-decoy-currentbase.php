<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Page without resource dictionary raw text) Tj ET q /DecoyForm Do Q';
$form = 'BT /F1 12 Tf 12 24 Td (Top-level page XObject decoy leak) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Font << /F1 5 0 R >> /XObject << /DecoyForm 6 0 R >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources << /Font << /F1 5 0 R >> >> /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);

if ($lines !== ['Page without resource dictionary raw text']) {
    throw new RuntimeException('Expected top-level page resource decoys to stay out of WordPress text.');
}

if ($boundary !== []) {
    throw new RuntimeException('Expected no page-resource metadata when /Resources is omitted.');
}

echo '<!-- markerpdf-page-resource-missing-dictionary-decoy-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-missing-dictionary-decoy-currentbase',
    'native_boundary' => 'page objects without /Resources do not promote top-level /Font or /XObject keys into resource dictionaries',
    'resources_omitted' => true,
    'resource_metadata_absent' => $boundary === [],
    'raw_searchable_text_preserved' => $lines === ['Page without resource dictionary raw text'],
    'top_level_xobject_decoy_excluded' => !str_contains($plainText, 'Top-level page XObject decoy leak'),
    'resource_name_text_excluded' => !str_contains($plainText, 'DecoyForm'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
