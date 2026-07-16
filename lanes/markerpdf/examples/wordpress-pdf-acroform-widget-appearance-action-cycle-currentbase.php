<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible form review body) Tj ET';
$appearance = 'BT /FApp 9 Tf 0 0 Td (Widget appearance stays metadata) Tj ET';
$script = "app.alert('widget cycle review only');";
$compressedScript = gzcompress($script);
if (!is_string($compressedScript)) {
    throw new RuntimeException('Unable to compress widget action cycle script.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.action) /V (Final widget field value) /DV (Draft widget field value) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 340 664] /P 3 0 R /F 4 /AS /Ready /AP << /N << /Ready 30 0 R /Off 31 0 R >> >> /A 20 0 R >>\nendobj\n"
    . "20 0 obj\n<< /S /URI /URI (https://example.test/review) /Next [21 0 R 22 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /S /JavaScript /JS 40 0 R /Next 23 0 R >>\nendobj\n"
    . "22 0 obj\n<< /S /Launch /F (helper.exe) /Next 20 0 R >>\nendobj\n"
    . "23 0 obj\n<< /S /Hide /T [8 0 R] /H true /Next 21 0 R >>\nendobj\n"
    . "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 24] /Resources << /Font << /FApp 32 0 R >> >> /Length " . strlen($appearance) . " >>\nstream\n{$appearance}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "32 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "40 0 obj\n<< /Length " . strlen($compressedScript) . " /Filter /FlateDecode >>\nstream\n{$compressedScript}\nendstream\nendobj\n"
    . "%%EOF";

$fields = (new PdfAcroFormExtractor())->extractFields($pdf);
$field = $fields[0] ?? null;
if (!is_array($field)) {
    throw new RuntimeException('Expected one AcroForm field.');
}

$widget = $field['widgets'][0] ?? null;
if (!is_array($widget)) {
    throw new RuntimeException('Expected one AcroForm widget.');
}

$actions = is_array($widget['actions'] ?? null) ? $widget['actions'] : [];
$actionReview = is_array($widget['action_review'] ?? null) ? $widget['action_review'] : [];
$appearanceReview = is_array($widget['normal_appearance'] ?? null) ? $widget['normal_appearance'] : [];
$selectedAppearance = is_array($appearanceReview['selected_appearance'] ?? null) ? $appearanceReview['selected_appearance'] : [];
$text = (new PdfTextExtractor())->extractPlainText($pdf);

if (($actionReview['cycle_edges_blocked'] ?? 0) !== 2 || count($actions) !== 4) {
    throw new RuntimeException('Expected bounded cyclic widget action review metadata.');
}
if (str_contains($text, 'widget cycle review') || str_contains($text, 'helper.exe')) {
    throw new RuntimeException('Action payload leaked into visible text.');
}

echo '<!-- markerpdf:pdf-acroform-widget-appearance-action-cycle-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-widget-appearance-action-cycle',
    'native_boundary' => 'AcroForm widget cyclic /A /Next chains are review metadata only; selected /AP /N appearance does not replace field /V',
    'field_name' => $field['name'] ?? null,
    'field_value' => $field['value'] ?? null,
    'visible_text' => $text,
    'appearance_state' => $widget['appearance_state'] ?? null,
    'selected_appearance_object' => $selectedAppearance['object'] ?? null,
    'selected_appearance_imports_visible_text' => $selectedAppearance['imports_visible_text'] ?? null,
    'action_types' => array_column($actions, 'action_type'),
    'chained_action_count' => $actionReview['chained_action_count'] ?? null,
    'cycle_edges_blocked' => $actionReview['cycle_edges_blocked'] ?? null,
    'blocked_cycle_action_objects' => $actionReview['blocked_cycle_action_objects'] ?? [],
    'max_depth_edges_blocked' => $actionReview['max_depth_edges_blocked'] ?? null,
    'appearance_value_used_for_import' => false,
    'visible_text_includes_selected_appearance' => str_contains($text, 'Widget appearance stays metadata'),
    'action_payloads_excluded_from_visible_text' => true,
    'executes_appearance_streams' => false,
    'renders_appearances' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars(sprintf(
    '%s: %s; widget action review blocked %d cyclic edges',
    (string) ($field['name'] ?? 'field'),
    (string) ($field['value'] ?? ''),
    (int) ($actionReview['cycle_edges_blocked'] ?? 0)
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars(sprintf(
    'Review actions: %s',
    implode(' -> ', array_map('strval', array_column($actions, 'action_type')))
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
