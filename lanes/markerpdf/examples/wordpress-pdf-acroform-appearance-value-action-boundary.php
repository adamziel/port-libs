<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$selectedAppearance = 'q BT /FApp 10 Tf 0 0 Td (Stale appearance text must stay review metadata) Tj ET Q';
$compressedSelectedAppearance = gzcompress($selectedAppearance);
if (!is_string($compressedSelectedAppearance)) {
    throw new RuntimeException('Unable to compress selected AcroForm appearance fixture.');
}

$staleAppearance = 'BT /FApp 10 Tf 0 0 Td (Stale alternate appearance) Tj ET';
$directAppearance = 'BT /FApp 10 Tf 0 0 Td (Direct appearance value must not replace field V) Tj ET';
$focusScript = "app.alert('focus review only');";
$compressedFocusScript = gzcompress($focusScript);
if (!is_string($compressedFocusScript)) {
    throw new RuntimeException('Unable to compress AcroForm action fixture.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.title) /V (Final field value) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 /AS /Fresh /AP << /N << /Fresh 30 0 R /Stale 31 0 R /Off 32 0 R >> >> /AA << /Fo 40 0 R >> >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (article.stale_state) /V (Current value despite stale AS) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 /AS /Ghost /AP << /N << /Fresh 30 0 R /Off 32 0 R >> >> >>\nendobj\n"
    . "14 0 obj\n<< /FT /Tx /T (summary.note) /V (Direct stream field value) /Kids [16 0 R] >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 /AP << /N 33 0 R >> /A << /S /JavaScript /JS (app.alert\\('activation review only'\\);) >> >>\nendobj\n"
    . "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 24] /Matrix [1 0 0 1 72 640] /Resources 50 0 R /Length " . strlen($compressedSelectedAppearance) . " /Filter /FlateDecode >>\nstream\n"
    . $compressedSelectedAppearance
    . "\nendstream\nendobj\n"
    . "31 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 24] /Length " . strlen($staleAppearance) . " >>\nstream\n{$staleAppearance}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 24] /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "33 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 24] /Resources 50 0 R /Length " . strlen($directAppearance) . " >>\nstream\n{$directAppearance}\nendstream\nendobj\n"
    . "40 0 obj\n<< /S /JavaScript /JS 41 0 R >>\nendobj\n"
    . "41 0 obj\n<< /Length " . strlen($compressedFocusScript) . " /Filter /FlateDecode >>\nstream\n"
    . $compressedFocusScript
    . "\nendstream\nendobj\n"
    . "50 0 obj\n<< /Font << /FApp 51 0 R >> /XObject << /Stamp 52 0 R >> >>\nendobj\n"
    . "51 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "52 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 40 12] /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "%%EOF";

$fields = (new PdfAcroFormExtractor())->extractFields($pdf);
$rows = [];
foreach ($fields as $field) {
    $widget = $field['widgets'][0] ?? [];
    $appearance = is_array($widget['normal_appearance'] ?? null) ? $widget['normal_appearance'] : [];
    $selected = is_array($appearance['selected_appearance'] ?? null) ? $appearance['selected_appearance'] : [];
    $actions = is_array($widget['actions'] ?? null) ? $widget['actions'] : [];

    $rows[] = [
        'name' => $field['name'],
        'value' => $field['value'],
        'appearance_state' => $widget['appearance_state'] ?? null,
        'normal_appearance_type' => $appearance['normal_appearance_type'] ?? null,
        'selected_appearance_object' => $selected['object'] ?? null,
        'selected_appearance_imports_visible_text' => $selected['imports_visible_text'] ?? null,
        'stale_appearance_state' => $appearance['stale_appearance_state'] ?? null,
        'action_count' => count($actions),
    ];
}

echo '<!-- markerpdf:pdf-acroform-appearance-value-action-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-catalog-acroform-widget-appearance',
    'native_boundary' => 'AcroForm widget /AP /N selected by /AS is review metadata; field /V remains the import value and /A /AA actions are not executed',
    'field_count' => count($fields),
    'selected_appearance_object' => $rows[0]['selected_appearance_object'] ?? null,
    'direct_appearance_object' => $rows[2]['selected_appearance_object'] ?? null,
    'stale_as_not_selected' => ($rows[1]['stale_appearance_state'] ?? null) === true,
    'appearance_value_used_for_import' => false,
    'appearance_stream_text_exposed' => false,
    'executes_appearance_streams' => false,
    'renders_appearances' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($rows as $row) {
    $details = sprintf(
        '%s: %s; appearance %s object %s; actions %d',
        (string) $row['name'],
        (string) $row['value'],
        (string) ($row['normal_appearance_type'] ?? 'none'),
        (string) ($row['selected_appearance_object'] ?? 'none'),
        (int) $row['action_count']
    );
    echo '<li>' . htmlspecialchars($details, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
