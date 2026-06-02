<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfJavaScriptActionInspector;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Imported body stays clean) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /JavaScript 6 0 R >> /OpenAction 8 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [9 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(wp-import-review) 7 0 R] >>\nendobj\n"
    . "7 0 obj\n<< /S /JavaScript /JS (app.alert\\('Document script disabled for import review'\\)) >>\nendobj\n"
    . "8 0 obj\n<< /S /JavaScript /JS (openActionReview\\(\\)) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 200 718] /A << /S /JavaScript /JS (annotationClick\\(\\)) >> >>\nendobj\n"
    . "%%EOF";

$review = (new PdfJavaScriptActionInspector())->reviewDocumentActions($pdf);
$paragraph = (new PdfTextExtractor())->extractPlainText($pdf);

echo '<!-- markerpdf-pdf-javascript-action-safety ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-javascript-action-inspector',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_javascript' => $review['executes_javascript'],
    'has_javascript' => $review['has_javascript'],
    'javascript_action_count' => $review['action_count'],
    'action_sources' => array_column($review['actions'], 'source'),
    'script_hashes' => array_column($review['actions'], 'script_sha256'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
