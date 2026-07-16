<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R 11 0 R 14 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 13 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 1 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (registration.email) /Ff 3 /V (editor@example.com) /DA (/Ti 11 Tf 0.25 g) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 600 300 620] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (registration.secret) /Ff 8192 /V (should-not-render) /Kids [11 0 R] >>\nendobj\n"
    . "11 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 560 300 580] /P 3 0 R /F 4 >>\nendobj\n"
    . "13 0 obj\n<< /FT /Ch /T (category) /Ff 917504 /V (Plugins) /Opt [(Themes) [(plugin) (Plugins)] (Blocks)] /Kids [14 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 13 0 R /Rect [72 520 220 540] /P 3 0 R /DA (/Helv 10 Tf 1 0 0 rg) >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = $form['fields'];

echo '<!-- markerpdf:pdf-acroform-field-flags ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-catalog-acroform',
    'native_boundary' => 'catalog /AcroForm /Fields traversal with inherited /Ff and /DA before Gutenberg review rendering',
    'need_appearances' => $form['need_appearances'],
    'field_count' => count($fields),
    'password_values_redacted' => true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($fields as $field) {
    $appearance = $field['default_appearance'] ?? [];
    $font = is_array($appearance) ? ($appearance['font_resource'] ?? 'default') : 'default';
    $size = is_array($appearance) && isset($appearance['font_size']) ? (string) $appearance['font_size'] : 'auto';
    $value = $field['value_redacted'] === true ? '[redacted]' : (string) ($field['value'] ?? '');
    $flags = $field['flag_names'] === [] ? 'none' : implode(', ', $field['flag_names']);

    echo '<li>'
        . htmlspecialchars($field['name'] . ': ' . $value . ' [' . $flags . '; ' . $font . ' ' . $size . 'pt]', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
