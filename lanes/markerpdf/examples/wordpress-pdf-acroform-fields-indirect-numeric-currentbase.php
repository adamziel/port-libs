<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect numeric attributes body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R] /NeedAppearances true >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (secret.indirect) /Ff 30 1 R /V (Sensitive value must redact) /DV (Default sensitive value) /MaxLen 31 1 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (public.max) /Ff 32 1 R /V (Too long value) /MaxLen 33 1 R /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /FT /Ch /T (choice.indirect) /Ff 34 1 R /V [(plugin) (themes)] /I [35 1 R 36 1 R 37 0 R] /Opt [[(themes) (Themes)] [(plugin) (Plugins)] [(blocks) (Blocks)]] /Kids [16 0 R] >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 1 obj\n8192\nendobj\n"
    . "31 1 obj\n8\nendobj\n"
    . "32 1 obj\n3\nendobj\n"
    . "33 1 obj\n6\nendobj\n"
    . "34 1 obj\n2097152\nendobj\n"
    . "35 1 obj\n1\nendobj\n"
    . "36 1 obj\n0\nendobj\n"
    . "37 1 obj\n2\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['secret.indirect', 'public.max', 'choice.indirect'] as $fieldName) {
    if (!isset($fieldsByName[$fieldName])) {
        throw new RuntimeException("Expected AcroForm field {$fieldName} to be present.");
    }
}

$secret = $fieldsByName['secret.indirect'];
$public = $fieldsByName['public.max'];
$choice = $fieldsByName['choice.indirect'];
$encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

if (($secret['flags'] ?? null) !== 8192 || ($secret['value_state']['display_value'] ?? null) !== '[redacted]') {
    throw new RuntimeException('Indirect AcroForm password flag did not resolve and redact.');
}
if (($public['flags'] ?? null) !== 3 || ($public['max_length'] ?? null) !== 6) {
    throw new RuntimeException('Indirect AcroForm read-only/required flags or MaxLen did not resolve.');
}
if (($choice['flags'] ?? null) !== 2097152 || ($choice['value_state']['selected_indices'] ?? null) !== [1, 0]) {
    throw new RuntimeException('Indirect AcroForm choice flags or selected indexes did not resolve.');
}
if (is_string($encoded) && str_contains($encoded, '37 0 R')) {
    throw new RuntimeException('Generation-mismatched choice index reference leaked into AcroForm review metadata.');
}
if (str_contains($visibleText, 'Sensitive value must redact') || str_contains($visibleText, 'Too long value')) {
    throw new RuntimeException('AcroForm indirect numeric field values must stay out of visible WordPress text.');
}

$rows = [];
foreach ($form['fields'] as $field) {
    $state = is_array($field['value_state'] ?? null) ? $field['value_state'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'flags' => $field['flags'] ?? null,
        'flag_names' => implode(', ', array_map('strval', $field['flag_names'] ?? [])),
        'display_value' => $state['display_value'] ?? null,
        'selected_indices' => implode(',', array_map('strval', $state['selected_indices'] ?? [])),
        'max_length' => $field['max_length'] ?? null,
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-indirect-numeric-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-indirect-numeric-attributes-boundary',
    'native_boundary' => 'Generation-exact indirect AcroForm numeric attributes resolve for field flags, MaxLen, and choice selected indexes before WordPress review; stale generation numeric refs are ignored.',
    'field_names' => array_column($rows, 'name'),
    'password_flag_resolved' => ($secret['flags'] ?? null) === 8192,
    'password_value_redacted' => ($secret['value_state']['display_value'] ?? null) === '[redacted]',
    'read_required_flags_resolved' => ($public['flag_names'] ?? null) === ['read_only', 'required'],
    'maxlen_resolved' => ($public['max_length'] ?? null) === 6,
    'maxlen_exceeded_reviewed' => ($public['max_length_review']['current_value_exceeds_max_length'] ?? null) === true,
    'choice_multiselect_flag_resolved' => ($choice['flag_names'] ?? null) === ['multi_select'],
    'choice_indices_resolved' => ($choice['value_state']['selected_indices'] ?? null) === [1, 0],
    'stale_choice_index_excluded' => ($choice['value_state']['selected_indices'] ?? null) === [1, 0],
    'form_values_visible_in_text' => str_contains($visibleText, 'Sensitive value must redact') || str_contains($visibleText, 'Too long value'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Flags</th><th>Value</th><th>MaxLen</th><th>Selected</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['flag_names'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['display_value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['max_length'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['selected_indices'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
