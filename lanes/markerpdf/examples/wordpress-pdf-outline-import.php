<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 7 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Outlines /First 8 0 R /Last 10 0 R /Count 3 >>\nendobj\n"
    . "8 0 obj\n<< /Title (Migration Runbook) /Parent 7 0 R /Dest [3 0 R /Fit] /First 9 0 R /Last 9 0 R /Next 10 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Title (Content Checks) /Parent 8 0 R /Dest [4 0 R /XYZ null null null] >>\nendobj\n"
    . "10 0 obj\n<< /Title (Media Cleanup) /Parent 7 0 R /A << /S /GoTo /D [4 0 R /FitH 720] >> >>\nendobj\n"
    . "11 0 obj\n<< /Title (WP PDF Import) /Author (Data Liberation Team) /Keywords (outline metadata) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 11 0 R >>\n%%EOF";

$metadata = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
$toc = $metadata['pdf_toc'];

echo '<!-- markerpdf-pdf-outline-metadata-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF catalog /Outlines plus trailer /Info metadata before WordPress TOC import',
    'document_info' => $metadata['document_info'],
    'pages' => $metadata['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($toc as $item) {
    $title = htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<li data-marker-outline-level="' . $item['level'] . '" data-marker-outline-page="' . $item['page'] . '">'
        . $title
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
