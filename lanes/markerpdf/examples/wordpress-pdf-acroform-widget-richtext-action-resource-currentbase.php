<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible widget rich text review body) Tj ET';
$richText = '<body xmlns="http://www.w3.org/1999/xhtml"><p style="font-weight:bold">Widget <i>rich</i> text stays metadata</p></body>';
$defaultStyle = 'font: 10pt "ReviewSans"; color:#003366; text-align:left';
$widgetValidateScript = "event.rc = false; app.alert('validation blocked');";
$compressedScript = gzcompress($widgetValidateScript);
if (!is_string($compressedScript)) {
    throw new RuntimeException('Unable to compress widget rich text action fixture.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) /DR 30 0 R >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.rich_widget) /Ff 33554432 /V (Plain widget value) /DV (Draft widget value) /RV ({$richText}) /DS ({$defaultStyle}) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 360 664] /P 3 0 R /F 4 /DA (/Review 10 Tf 0.2 0.3 0.4 rg) /AA << /V 20 0 R >> >>\nendobj\n"
    . "20 0 obj\n<< /S /JavaScript /JS 21 0 R >>\nendobj\n"
    . "21 0 obj\n<< /Length " . strlen($compressedScript) . " /Filter /FlateDecode >>\nstream\n{$compressedScript}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Font << /Helv 31 0 R /Review 32 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "32 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ReviewSans /Encoding /WinAnsiEncoding /FontDescriptor 33 0 R >>\nendobj\n"
    . "33 0 obj\n<< /Type /FontDescriptor /FontName /ReviewSans /Flags 32 /FontWeight 700 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = [];
foreach ($form['fields'] as $field) {
    $fields[$field['name']] = $field;
}

$field = $fields['article.rich_widget'] ?? null;
if (!is_array($field)) {
    throw new RuntimeException('Expected rich widget AcroForm field review row.');
}
$widget = $field['widgets'][0] ?? null;
$richReview = is_array($field['rich_text_review'] ?? null) ? $field['rich_text_review'] : null;
$widgetAppearance = is_array($widget['default_appearance'] ?? null) ? $widget['default_appearance'] : null;
$widgetAction = is_array(($widget['actions'] ?? [])[0] ?? null) ? $widget['actions'][0] : null;
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

if ($richReview === null || $widgetAppearance === null || $widgetAction === null) {
    throw new RuntimeException('Expected rich text, widget appearance, and action review metadata.');
}
if (
    str_contains($visibleText, 'Widget rich text stays metadata')
    || str_contains($visibleText, 'validation blocked')
    || str_contains($visibleText, 'ReviewSans')
) {
    throw new RuntimeException('Review-only AcroForm widget payload leaked into visible text.');
}

echo '<!-- markerpdf:pdf-acroform-widget-richtext-action-resource-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-widget-richtext-action-resource-review',
    'native_boundary' => 'AcroForm /DS rich-text default style, widget /DA resource resolution, and widget /AA validate actions are review metadata only before WordPress import',
    'visible_text' => $visibleText,
    'field_name' => $field['name'] ?? null,
    'plain_value' => $field['value'] ?? null,
    'rich_text_sha256' => $richReview['rich_text_sha256'] ?? null,
    'rich_text_plain_preview' => $richReview['rich_text_plain_preview'] ?? null,
    'default_style_sha256' => $richReview['default_style_sha256'] ?? null,
    'default_style_preview' => $richReview['default_style_preview'] ?? null,
    'widget_font' => $widgetAppearance['font_resource'] ?? null,
    'widget_font_base' => $widgetAppearance['font_resource_base_font'] ?? null,
    'widget_font_weight' => $widgetAppearance['font_weight'] ?? null,
    'widget_source_object' => $widgetAppearance['source_object'] ?? null,
    'validate_action_object' => $widgetAction['action_object'] ?? null,
    'validate_script_sha256' => $widgetAction['script_sha256'] ?? null,
    'rich_text_payload_excluded_from_visible_text' => true,
    'default_style_exposed_as_css' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars('article.rich_widget imports plain /V "' . (string) ($field['value'] ?? '') . '"; /RV and /DS are review-only.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('Widget /DA resolves /Review to ' . (string) ($widgetAppearance['font_resource_base_font'] ?? 'unresolved') . ' from AcroForm /DR.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('Widget validate JavaScript is hashed for review and never executed.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
