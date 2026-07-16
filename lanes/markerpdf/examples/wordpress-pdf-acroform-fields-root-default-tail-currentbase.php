<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm root default tail smoke body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 41 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/TailFont 9 Tf 1 0 0 rg) 90 0 R /DR 30 0 R 91 0 R /Q 2 92 0 R >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (defaults.tailed) /TU (Tailed root default label) /TM (tailed-root-default-export) /V (Tailed root default value) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Font << /TailFont 40 0 R >> >>\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "41 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "90 0 obj\n(/Tail default appearance operand must not surface)\nendobj\n"
    . "91 0 obj\n<< /Font << /TailDecoy 42 0 R >> >>\nendobj\n"
    . "92 0 obj\n0\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fields = [];
foreach ($form['fields'] as $field) {
    $fields[(string) ($field['name'] ?? '')] = $field;
}

$field = $fields['defaults.tailed'] ?? null;
if (!is_array($field)) {
    throw new RuntimeException('Expected tailed AcroForm field to remain reviewable.');
}
if (($form['default_resources']['font_count'] ?? null) !== 0 || !array_key_exists('source', $form['default_resources']) || $form['default_resources']['source'] !== null) {
    throw new RuntimeException('Tailed AcroForm /DR must not seed default resource review.');
}
if (!array_key_exists('default_appearance', $field) || $field['default_appearance'] !== null || !array_key_exists('quadding', $field) || $field['quadding'] !== null) {
    throw new RuntimeException('Tailed AcroForm /DA and /Q must not inherit into field review.');
}
if (str_contains((string) json_encode($form, JSON_UNESCAPED_SLASHES), 'TailFont') || str_contains($visibleText, 'Tailed root default value')) {
    throw new RuntimeException('Tailed root default operands or form values leaked into WordPress-visible output.');
}

echo '<!-- markerpdf:pdf-acroform-fields-root-default-tail-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-root-default-tail-boundary',
    'native_boundary' => 'AcroForm root /DA, /DR, and /Q defaults must be complete top-level values; tailed operands are rejected before inherited field appearance, resource, or quadding review.',
    'field_count' => count($form['fields']),
    'field_names' => array_keys($fields),
    'tailed_default_resources_rejected' => ($form['default_resources']['font_count'] ?? null) === 0,
    'tailed_default_appearance_rejected' => ($field['default_appearance'] ?? null) === null,
    'tailed_quadding_rejected' => ($field['quadding'] ?? null) === null,
    'field_value_review_only' => !str_contains($visibleText, 'Tailed root default value'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Value Source</th><th>Default Resources</th><th>Appearance</th></tr>\n";
echo '<tr><td>' . htmlspecialchars((string) ($field['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars((string) ($field['value_state']['hierarchy_boundary']['current_value_source'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>rejected tailed root /DR</td>';
echo "<td>rejected tailed root /DA and /Q</td></tr>\n";
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
