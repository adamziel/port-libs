<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$branchContent = "BT /Fbranch 12 Tf 72 720 Td (Branch inherited resource text) Tj ET\n"
    . "q /BranchForm Do Q";
$blockedContent = "BT /Fbranch 12 Tf 72 720 Td (Malformed page raw text) Tj ET\n"
    . "q /BranchForm Do Q";
$branchForm = 'BT /Fbranch 12 Tf 12 24 Td (Branch inherited form text) Tj ET';
$rootForm = 'BT /Froot 12 Tf 12 24 Td (Root resource form leak) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [10 0 R] /Count 2 /Resources 30 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R 4 0 R] /Count 2 /Resources 20 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 10 0 R /Resources << /Font << /Fbranch 7 0 R >> /XObject << /BranchForm 8 0 R >> >> 99 0 R /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($branchContent) . " >>\nstream\n{$branchContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($blockedContent) . " >>\nstream\n{$blockedContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($branchForm) . " >>\nstream\n{$branchForm}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj\n"
    . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($rootForm) . " >>\nstream\n{$rootForm}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Font << /Fbranch 7 0 R >> /XObject << /BranchForm 8 0 R >> >>\nendobj\n"
    . "30 0 obj\n<< /Font << /Froot 9 0 R >> /XObject << /RootForm 11 0 R >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$firstResources = $boundary[0]['resources'] ?? [];
$secondResources = $boundary[1]['resources'] ?? [];

if (($firstResources['resource_lookup_objects'] ?? null) !== [3, 10]) {
    throw new RuntimeException('Expected inherited page resource lookup lineage through the branch page-tree node.');
}

if (($secondResources['resource_lookup_objects'] ?? null) !== [4]
    || ($secondResources['status'] ?? null) !== 'unresolved_or_malformed'
) {
    throw new RuntimeException('Expected malformed page resources to record a one-object lookup boundary.');
}

if (str_contains($plainText, 'Root resource form leak')) {
    throw new RuntimeException('Expected root resource decoys to stay out of imported WordPress paragraphs.');
}

echo '<!-- markerpdf-page-resource-lookup-lineage-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-lookup-lineage-currentbase',
    'native_boundary' => 'page /Resources inheritance review records inspected page-tree objects without merging ancestor resource dictionaries',
    'inherited_page_resource_lookup_objects' => $firstResources['resource_lookup_objects'] ?? [],
    'inherited_page_resource_owner_object' => $firstResources['resource_owner_object'] ?? null,
    'malformed_page_resource_lookup_objects' => $secondResources['resource_lookup_objects'] ?? [],
    'malformed_page_resource_status' => $secondResources['status'] ?? null,
    'root_resource_decoy_excluded' => !str_contains($plainText, 'Root resource form leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
