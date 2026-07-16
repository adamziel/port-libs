<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm inherited action boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R 16 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article) /AA << /V 30 0 R /C 31 0 R >> /Kids [10 0 R 12 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Parent 6 0 R /T (title) /V (Reviewed inherited title) /DV (Draft inherited title) /Kids [14 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Parent 6 0 R /T (slug) /V (reviewed-slug) /AA << /F 32 0 R >> /Kids [16 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /S /SubmitForm /F (https://example.test/inherited-submit) /Fields [10 0 R 12 0 R (named.extra)] /Flags 6 >>\nendobj\n"
    . "31 0 obj\n<< /S /JavaScript /JS (this.getField('article.title').value = 'calculated';) >>\nendobj\n"
    . "32 0 obj\n<< /S /ResetForm /Fields [10 0 R] >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['article.title', 'article.slug'] as $name) {
    if (!isset($fieldsByName[$name])) {
        throw new RuntimeException("Missing expected AcroForm field {$name}.");
    }
}

$actionsByTrigger = static function (array $actions): array {
    $indexed = [];
    foreach ($actions as $action) {
        $indexed[(string) ($action['trigger'] ?? '')] = $action;
    }

    return $indexed;
};

$title = $fieldsByName['article.title'];
$slug = $fieldsByName['article.slug'];
$titleActions = $actionsByTrigger(is_array($title['actions'] ?? null) ? $title['actions'] : []);
$slugActions = $actionsByTrigger(is_array($slug['actions'] ?? null) ? $slug['actions'] : []);

if (array_keys($titleActions) !== ['V', 'C']) {
    throw new RuntimeException('Parent AcroForm /AA actions were not inherited by the terminal title field.');
}
if (($titleActions['V']['source_object'] ?? null) !== 6 || ($titleActions['C']['source_object'] ?? null) !== 6) {
    throw new RuntimeException('Inherited AcroForm /AA action source object was not preserved for review.');
}
if (($titleActions['V']['field_value_review']['submitted_field_names'] ?? null) !== ['article.title', 'article.slug']) {
    throw new RuntimeException('Inherited SubmitForm action did not review selected child field values.');
}
if (array_keys($slugActions) !== ['F'] || ($slugActions['F']['source_object'] ?? null) !== 12) {
    throw new RuntimeException('Terminal AcroForm /AA did not override the parent additional-actions dictionary.');
}
foreach ([
    'Reviewed inherited title',
    'Draft inherited title',
    'reviewed-slug',
    'https://example.test/inherited-submit',
    'calculated',
] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException("Review-only AcroForm inherited action text leaked into visible WordPress text: {$reviewOnlyText}");
    }
}

$rows = [];
foreach ($form['fields'] as $field) {
    $actions = is_array($field['actions'] ?? null) ? $field['actions'] : [];
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'action_triggers' => array_values(array_filter(array_map(
            static fn (array $action): ?string => is_string($action['trigger'] ?? null) ? $action['trigger'] : null,
            $actions
        ))),
        'action_source_objects' => array_values(array_filter(array_map(
            static fn (array $action): ?int => is_int($action['source_object'] ?? null) ? $action['source_object'] : null,
            $actions
        ))),
        'widget_objects' => array_column($widgets, 'object'),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-inherited-actions-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-inherited-additional-actions-boundary',
    'native_boundary' => 'Parent field /AA additional actions are inherited by terminal child fields for review metadata, while terminal /AA dictionaries override inherited parent actions; SubmitForm, ResetForm, and JavaScript actions are not executed.',
    'field_count' => count($form['fields']),
    'field_names' => array_column($rows, 'name'),
    'title_inherited_action_triggers' => $rows[0]['action_triggers'] ?? [],
    'title_inherited_action_source_objects' => $rows[0]['action_source_objects'] ?? [],
    'slug_terminal_action_triggers' => $rows[1]['action_triggers'] ?? [],
    'slug_terminal_action_source_objects' => $rows[1]['action_source_objects'] ?? [],
    'submit_review_field_names' => $titleActions['V']['field_value_review']['submitted_field_names'] ?? [],
    'terminal_aa_overrides_parent_aa' => array_keys($slugActions) === ['F'],
    'review_only_text_excluded_from_visible_text' => $visibleText === 'Visible AcroForm inherited action boundary body',
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Value</th><th>Action triggers</th><th>Widgets</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(', ', $row['action_triggers']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(', ', array_map('strval', $row['widget_objects'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
