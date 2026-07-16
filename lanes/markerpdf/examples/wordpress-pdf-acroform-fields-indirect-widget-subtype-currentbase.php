<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect Widget subtype boundary body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R 22 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (listed.indirect.widget) /TU (Listed indirect Widget label) /TM (listed-indirect-widget-export) /V (Listed indirect Widget value) /DV (Listed indirect Widget default) /MaxLen 64 /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype 30 1 R /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (page.indirect.widget) /TU (Page repair indirect Widget label) /TM (page-indirect-widget-export) /V (Page repair indirect Widget value) /DV (Page repair indirect Widget default) /MaxLen 48 >>\nendobj\n"
    . "12 0 obj\n<< /Type /Annot /Subtype 30 1 R /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "16 0 obj\n<< /Type /Annot /Subtype 31 0 R /FT /Tx /T (text.annotation.decoy) /V (Text annotation decoy value) /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n<< /FT /Tx /T (stale.indirect.widget.parent) /V (Stale indirect Widget parent value) >>\nendobj\n"
    . "22 0 obj\n<< /Type /Annot /Subtype 32 0 R /Parent 20 0 R /Rect [72 520 320 544] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 1 obj\n/Widget\nendobj\n"
    . "30 0 obj\n/Link\nendobj\n"
    . "31 0 obj\n/Text\nendobj\n"
    . "32 1 obj\n/Widget\nendobj\n"
    . "32 0 obj\n/Link\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fields = [];
foreach ($form['fields'] as $field) {
    $fields[(string) ($field['name'] ?? '')] = $field;
}

$listed = $fields['listed.indirect.widget'] ?? null;
$pageRepair = $fields['page.indirect.widget'] ?? null;
$encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

if (!is_array($listed) || !is_array($pageRepair)) {
    throw new RuntimeException('Expected listed and page-repaired indirect Widget subtype fields.');
}
if (array_column($listed['widgets'] ?? [], 'object') !== [8]) {
    throw new RuntimeException('Expected listed field widget metadata to resolve through indirect /Subtype /Widget.');
}
if (array_column($pageRepair['widgets'] ?? [], 'object') !== [12]) {
    throw new RuntimeException('Expected page-owned Widget repair to resolve through indirect /Subtype /Widget.');
}
foreach (['text.annotation.decoy', 'Text annotation decoy value', 'stale.indirect.widget.parent', 'Stale indirect Widget parent value'] as $decoyText) {
    if (str_contains($encoded, $decoyText) || str_contains($visibleText, $decoyText)) {
        throw new RuntimeException('Expected non-widget or stale-generation subtype decoy to stay excluded: ' . $decoyText);
    }
}
foreach (['Listed indirect Widget value', 'Page repair indirect Widget value'] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException('Expected AcroForm values to remain review-only: ' . $reviewOnlyText);
    }
}

echo '<!-- markerpdf:pdf-acroform-fields-indirect-widget-subtype-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-fields-indirect-widget-subtype-currentbase',
    'native_boundary' => 'AcroForm Widget /Subtype names may be indirect only when object generation matches; non-widget and stale-generation subtype targets remain excluded',
    'field_names' => array_keys($fields),
    'listed_indirect_widget_subtype_resolved' => array_column($listed['widgets'], 'object') === [8],
    'page_widget_repair_indirect_subtype_resolved' => array_column($pageRepair['widgets'], 'object') === [12],
    'text_annotation_decoy_excluded' => !str_contains($encoded, 'text.annotation.decoy'),
    'stale_generation_widget_subtype_rejected' => !str_contains($encoded, 'stale.indirect.widget.parent'),
    'field_values_visible_in_text' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><tbody>\n";
foreach ($fields as $field) {
    $widgets = array_column($field['widgets'] ?? [], 'object');
    echo '<tr><td>' . htmlspecialchars((string) ($field['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td><td>'
        . htmlspecialchars(implode(',', array_map('strval', $widgets)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</td></tr>\n";
}
echo "</tbody></table></figure>\n<!-- /wp:table -->\n";
