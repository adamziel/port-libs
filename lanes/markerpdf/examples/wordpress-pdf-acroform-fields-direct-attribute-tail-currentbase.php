<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[(string) ($field['name'] ?? '')] = $field;
    }

    return $indexed;
};

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible WordPress AcroForm direct attribute tail body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 18 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 16 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) /Q 1 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.valid) /Ff 0 /V (valid@example.test) /DV (draft@example.test) /Q 0 /MaxLen 64 /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx 90 0 R /T (article.tailed) /TU (Tailed direct attribute label) /TM (article.tailed.export) /Ff 4096 91 0 R /V (tailed current must not surface) 92 0 R /DV (tailed default must not surface) 93 0 R /Q 2 94 0 R /MaxLen 24 95 0 R /DA (/Helv 8 Tf 1 0 0 rg) 96 0 R /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "16 0 obj\n<< /FT /Tx % field type comment tail\n/T (article.comment) /Ff 4096 % flags comment tail\n/V (comment current value) % value comment tail\n/DV (comment default value) % default comment tail\n/Q 2 % quadding comment tail\n/MaxLen 40 % max length comment tail\n/Kids [18 0 R] >>\nendobj\n"
    . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "90 0 obj\n<< /FT /Sig /T (tail.ft.decoy) /V (Tail FT decoy value) >>\nendobj\n"
    . "91 0 obj\n8192\nendobj\n"
    . "92 0 obj\n(Tail current decoy object)\nendobj\n"
    . "93 0 obj\n(Tail default decoy object)\nendobj\n"
    . "94 0 obj\n0\nendobj\n"
    . "95 0 obj\n8\nendobj\n"
    . "96 0 obj\n(/Decoy 12 Tf 0 1 0 rg)\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = $fieldsByName($form['fields']);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($form, JSON_UNESCAPED_SLASHES) ?: '';

foreach (['article.valid', 'article.tailed', 'article.comment'] as $name) {
    if (!isset($fields[$name])) {
        throw new RuntimeException("Missing expected AcroForm field {$name}.");
    }
}

$tailed = $fields['article.tailed'];
$comment = $fields['article.comment'];

if (($tailed['field_type'] ?? null) !== null
    || ($tailed['flags'] ?? null) !== 0
    || ($tailed['value'] ?? null) !== null
    || ($tailed['default_value'] ?? null) !== null
    || ($tailed['max_length'] ?? null) !== null
) {
    throw new RuntimeException('Tailed direct AcroForm scalar attributes did not fail closed.');
}
if (($comment['field_type_label'] ?? null) !== 'text'
    || ($comment['flags'] ?? null) !== 4096
    || ($comment['value'] ?? null) !== 'comment current value'
    || ($comment['default_value'] ?? null) !== 'comment default value'
    || ($comment['max_length'] ?? null) !== 40
) {
    throw new RuntimeException('Comment-only AcroForm attribute tails should remain valid review metadata.');
}

foreach ([
    'tailed current must not surface',
    'tailed default must not surface',
    'Tail FT decoy value',
    'Tail current decoy object',
    'Tail default decoy object',
    '/Decoy 12 Tf',
] as $tailedText) {
    if (str_contains($encoded, $tailedText) || str_contains($visibleText, $tailedText)) {
        throw new RuntimeException("Malformed AcroForm direct attribute tail leaked into WordPress output: {$tailedText}");
    }
}
foreach (['valid@example.test', 'comment current value', 'Tailed direct attribute label'] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException("AcroForm review metadata leaked into visible WordPress text: {$reviewOnlyText}");
    }
}

$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'type' => $field['field_type_label'] ?? null,
        'value' => $field['value_state']['display_value'] ?? null,
        'widgets' => array_column($widgets, 'object'),
    ];
}

$summary = [
    'source' => 'native-pdf-acroform-direct-attribute-tail-boundary',
    'native_boundary' => 'Direct AcroForm scalar attributes followed by stray top-level operands fail closed before field review; comment-only tails remain valid.',
    'field_names' => array_column($rows, 'name'),
    'tailed_field_type_rejected' => ($tailed['field_type'] ?? null) === null,
    'tailed_flags_rejected' => ($tailed['flags'] ?? null) === 0,
    'tailed_value_rejected' => ($tailed['value'] ?? null) === null,
    'tailed_default_rejected' => ($tailed['default_value'] ?? null) === null,
    'tailed_max_length_rejected' => ($tailed['max_length'] ?? null) === null,
    'tailed_quadding_fell_back_to_acroform_default' => ($tailed['quadding'] ?? null) === 1,
    'comment_only_tails_preserved' => ($comment['value'] ?? null) === 'comment current value'
        && ($comment['max_length'] ?? null) === 40,
    'form_values_visible_in_text' => false,
    'tailed_attribute_text_visible' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-acroform-fields-direct-attribute-tail-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Review value</th><th>Widgets</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars('objects ' . implode(',', array_map('strval', $row['widgets'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
