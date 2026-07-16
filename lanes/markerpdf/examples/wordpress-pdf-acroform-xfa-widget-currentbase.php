<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xdpXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xdp:xdp xmlns:xdp="http://ns.adobe.com/xdp/" xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/">
  <xfa:template xmlns:xfa="http://www.xfa.org/schema/xfa-template/3.3/">
    <xfa:subform name="article">
      <xfa:field name="article.title"><xfa:caption><xfa:value><xfa:text>Article title</xfa:text></xfa:value></xfa:caption></xfa:field>
      <xfa:field name="consent.email"><xfa:caption><xfa:value><xfa:text>Email consent</xfa:text></xfa:value></xfa:caption></xfa:field>
    </xfa:subform>
  </xfa:template>
  <xfa:datasets>
    <xfa:data>
      <article><title>XFA dynamic title must not import</title></article>
      <consent><email>XFA checked value stays metadata</email></consent>
    </xfa:data>
  </xfa:datasets>
</xdp:xdp>
XML;

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible XFA widget current-base body) Tj ET';
$titleAppearance = 'BT /F1 10 Tf 0 0 Td (Title widget appearance review only) Tj ET';
$checkedAppearance = 'BT /F1 10 Tf 0 0 Td (Checked widget appearance review only) Tj ET';
$offAppearance = 'BT /F1 10 Tf 0 0 Td (Off widget appearance review only) Tj ET';

$compressedXfa = gzcompress($xdpXml);
$compressedTitle = gzcompress($titleAppearance);
$compressedChecked = gzcompress($checkedAppearance);
$compressedOff = gzcompress($offAppearance);
if (!is_string($compressedXfa) || !is_string($compressedTitle) || !is_string($compressedChecked) || !is_string($compressedOff)) {
    throw new RuntimeException('Unable to compress AcroForm XFA widget current-base example.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 11 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R] /NeedAppearances true /XFA 30 0 R >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.title) /V (Static AcroForm title) /DV (Draft AcroForm title) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 360 664] /P 3 0 R /F 4 /AS /Current /AP << /N 20 0 R >> >>\nendobj\n"
    . "9 0 obj\n<< /FT /Btn /T (consent.email) /V /Yes /DV /Off /Kids [11 0 R] >>\nendobj\n"
    . "11 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 120 624] /P 3 0 R /F 4 /AS /Yes /AP << /N << /Yes 21 0 R /Off 22 0 R >> >> >>\nendobj\n"
    . "20 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 24] /Resources << /Font << /F1 40 0 R >> >> /Length " . strlen($compressedTitle) . " /Filter /FlateDecode >>\nstream\n{$compressedTitle}\nendstream\nendobj\n"
    . "21 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Resources << /Font << /F1 40 0 R >> >> /Length " . strlen($compressedChecked) . " /Filter /FlateDecode >>\nstream\n{$compressedChecked}\nendstream\nendobj\n"
    . "22 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Resources << /Font << /F1 40 0 R >> >> /Length " . strlen($compressedOff) . " /Filter /FlateDecode >>\nstream\n{$compressedOff}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($compressedXfa) . " /Filter /FlateDecode >>\nstream\n{$compressedXfa}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = [];
foreach ($form['fields'] as $field) {
    $fields[$field['name']] = $field;
}

$title = $fields['article.title'] ?? null;
$checkbox = $fields['consent.email'] ?? null;
if (!is_array($title) || !is_array($checkbox)) {
    throw new RuntimeException('Expected AcroForm title and consent fields.');
}

$titleReview = is_array($title['xfa_widget_review'] ?? null) ? $title['xfa_widget_review'] : null;
$checkboxReview = is_array($checkbox['xfa_widget_review'] ?? null) ? $checkbox['xfa_widget_review'] : null;
if ($titleReview === null || $checkboxReview === null) {
    throw new RuntimeException('Expected XFA widget current-base review rows.');
}

$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
if (
    ($titleReview['current'] ?? null) !== 'Static AcroForm title'
    || ($checkboxReview['current'] ?? null) !== 'Yes'
    || ($titleReview['xfa_value_used_for_current_value'] ?? true) !== false
    || ($checkboxReview['xfa_value_used_for_widget_state'] ?? true) !== false
    || str_contains($visibleText, 'XFA dynamic title must not import')
    || str_contains($visibleText, 'XFA checked value stays metadata')
) {
    throw new RuntimeException('XFA widget current-base boundary failed.');
}

echo '<!-- markerpdf:pdf-acroform-xfa-widget-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-xfa-widget-currentbase-review',
    'native_boundary' => 'AcroForm /V, /DV, and widget /AS stay current-base authoritative while /XFA datasets are review metadata only before WordPress import',
    'visible_text' => $visibleText,
    'title_current' => $titleReview['current'] ?? null,
    'title_default' => $titleReview['default'] ?? null,
    'title_xfa_value_preview' => $titleReview['matched_data_value_previews'][0] ?? null,
    'checkbox_current' => $checkboxReview['current'] ?? null,
    'checkbox_state_source' => $checkboxReview['state_source'] ?? null,
    'checkbox_widget_state' => $checkboxReview['primary_widget_appearance_state'] ?? null,
    'checkbox_xfa_value_preview' => $checkboxReview['matched_data_value_previews'][0] ?? null,
    'acroform_current_value_authoritative' => true,
    'xfa_value_used_for_current_value' => false,
    'xfa_value_used_for_widget_state' => false,
    'xfa_payload_text_exposed' => false,
    'appearance_value_used_for_import' => false,
    'executes_xfa_javascript' => false,
    'executes_form_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars('article.title imports AcroForm /V "' . (string) ($titleReview['current'] ?? '') . '" while the matching XFA dataset value stays review-only.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('consent.email keeps widget /AS ' . (string) ($checkboxReview['primary_widget_appearance_state'] ?? 'unknown') . ' and field /V ' . (string) ($checkboxReview['current'] ?? 'unknown') . ' as the checkbox state.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
