<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm root stream boundary body) Tj ET';
$acroFormStreamPayload = 'BT /F1 12 Tf 72 680 Td (AcroForm root stream payload leak) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) /Length " . strlen($acroFormStreamPayload) . " >>\nstream\n{$acroFormStreamPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (streamroot.leak) /TU (Stream root field label) /TM (stream-root-export) /V (Stream root field value must not surface) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

$streamRootRejected = ($form['need_appearances'] ?? true) === false
    && ($form['fields'] ?? []) === []
    && ($form['calculation_order'] ?? []) === []
    && ($form['calculation_order_review'] ?? []) === [];
$fieldExcluded = is_string($encoded)
    && !str_contains($encoded, 'streamroot.leak')
    && !str_contains($encoded, 'Stream root field value must not surface');
$payloadExcluded = !str_contains($visibleText, 'AcroForm root stream payload leak');
$visibleContentPreserved = str_contains($visibleText, 'Visible AcroForm root stream boundary body');

if (!$streamRootRejected || !$fieldExcluded || !$payloadExcluded || !$visibleContentPreserved) {
    throw new RuntimeException('AcroForm root stream boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-acroform-fields-root-stream-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-catalog-acroform-root-stream-boundary',
    'native_boundary' => 'Catalog /AcroForm references must resolve to plain dictionaries, not stream carriers with field-looking dictionaries or payload text',
    'field_count' => count($form['fields']),
    'need_appearances' => $form['need_appearances'],
    'acroform_root_stream_rejected' => $streamRootRejected,
    'stream_root_field_excluded' => $fieldExcluded,
    'root_stream_payload_excluded' => $payloadExcluded,
    'visible_content_preserved' => $visibleContentPreserved,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($visibleText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
