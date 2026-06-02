<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 1 rg) /DR 30 0 R >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.title) /V (Default resource title) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (article.body) /V (Body copy) /DA (/Body 11 Tf 0.1 0.2 0.3 rg) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /FT /Tx /T (article.teaser) /V (Widget override) /Kids [16 0 R] >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 /DA (/Missing 10 Tf 0.5 g) >>\nendobj\n"
    . "30 0 obj\n<< /Font << /Helv 31 0 R /Body 32 0 R /Inline << /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /MacRomanEncoding >> >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding 35 0 R >>\nendobj\n"
    . "32 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEE+SourceSansPro /Encoding << /Type /Encoding /BaseEncoding /MacRomanEncoding >> /FontDescriptor 33 0 R >>\nendobj\n"
    . "33 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEE+SourceSansPro /Flags 32 /FontWeight 600 >>\nendobj\n"
    . "35 0 obj\n<< /Type /Encoding /BaseEncoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = [];
foreach ($form['fields'] as $field) {
    $fields[$field['name']] = $field;
}

$resolved = [];
$unresolved = [];
foreach ($form['fields'] as $field) {
    $appearance = is_array($field['default_appearance'] ?? null) ? $field['default_appearance'] : null;
    if ($appearance !== null && ($appearance['font_resource_resolved'] ?? false) === true) {
        $resolved[] = (string) $appearance['font_resource'] . ':' . (string) $appearance['font_resource_base_font'];
    }

    foreach ($field['widgets'] ?? [] as $widget) {
        $widgetAppearance = is_array($widget['default_appearance'] ?? null) ? $widget['default_appearance'] : null;
        if ($widgetAppearance !== null && ($widgetAppearance['font_resource'] ?? null) !== null && ($widgetAppearance['font_resource_resolved'] ?? false) === false) {
            $unresolved[] = (string) $field['name'] . ':' . (string) $widgetAppearance['font_resource'];
        }
    }
}
$resolved = array_values(array_unique($resolved));
$unresolved = array_values(array_unique($unresolved));

echo '<!-- markerpdf:pdf-acroform-default-resources ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-catalog-acroform-default-resources',
    'native_boundary' => 'catalog /AcroForm /DR /Font review metadata resolves /DA font resources before WordPress form review rendering',
    'field_count' => count($form['fields']),
    'default_resource_object' => $form['default_resources']['object'],
    'default_resource_font_count' => $form['default_resources']['font_count'],
    'resolved_default_appearance_fonts' => $resolved,
    'unresolved_widget_appearance_fonts' => $unresolved,
    'body_font_descriptor_flags' => $fields['article.body']['default_appearance']['font_descriptor_flags'] ?? null,
    'executes_appearance_streams' => false,
    'renders_appearances' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($form['fields'] as $field) {
    $appearance = is_array($field['default_appearance'] ?? null) ? $field['default_appearance'] : [];
    $font = (string) ($appearance['font_resource'] ?? 'default');
    $baseFont = (string) ($appearance['font_resource_base_font'] ?? 'unresolved');
    $value = $field['value_redacted'] === true ? '[redacted]' : (string) ($field['value'] ?? '');
    $widgetNotes = [];
    foreach ($field['widgets'] ?? [] as $widget) {
        $widgetAppearance = is_array($widget['default_appearance'] ?? null) ? $widget['default_appearance'] : null;
        if ($widgetAppearance !== null && ($widgetAppearance['source'] ?? null) === 'widget') {
            $widgetNotes[] = 'widget ' . (string) ($widgetAppearance['font_resource'] ?? 'default')
                . ' -> ' . (string) ($widgetAppearance['font_resource_base_font'] ?? 'unresolved');
        }
    }
    $suffix = $widgetNotes === [] ? '' : '; ' . implode(', ', $widgetNotes);

    echo '<li>' . htmlspecialchars(
        $field['name'] . ': ' . $value . ' [' . $font . ' -> ' . $baseFont . $suffix . ']',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
