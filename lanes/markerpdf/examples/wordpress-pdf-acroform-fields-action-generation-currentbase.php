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

$actionsByTrigger = static function (array $actions): array {
    $indexed = [];
    foreach ($actions as $action) {
        $indexed[(string) ($action['trigger'] ?? '')] = $action;
    }

    return $indexed;
};

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm action field generation boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 1 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 1 R 10 0 R] /NeedAppearances true >>\nendobj\n"
    . "6 1 obj\n<< /FT /Tx /T (current.email) /V (current@example.test) /Kids [8 1 R] /AA << /V 30 0 R /F 31 0 R >> >>\nendobj\n"
    . "8 1 obj\n<< /Subtype /Widget /Parent 6 1 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (stale.email) /V (stale@example.test) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 620 320 644] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (current.title) /V (Reviewed title) /DV (Default title) /Kids [12 0 R] /AA << /Bl 32 0 R >> >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /S /SubmitForm /F (https://example.test/submit) /Fields [6 0 R 10 0 R 99 0 R (named.extra)] /Flags 4 >>\nendobj\n"
    . "31 0 obj\n<< /S /ResetForm /Fields [6 0 R 10 0 R 99 0 R (named.extra)] >>\nendobj\n"
    . "32 0 obj\n<< /S /Hide /T [6 0 R 10 0 R 100 0 R (named.extra)] /H true >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = $fieldsByName($form['fields']);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$email = $fields['current.email'] ?? null;
$title = $fields['current.title'] ?? null;
if (!is_array($email) || !is_array($title)) {
    throw new RuntimeException('Expected current-generation AcroForm fields.');
}

$emailActions = $actionsByTrigger($email['actions'] ?? []);
$titleActions = $actionsByTrigger($title['actions'] ?? []);
$submit = $emailActions['V'] ?? null;
$reset = $emailActions['F'] ?? null;
$hide = $titleActions['Bl'] ?? null;
if (!is_array($submit) || !is_array($reset) || !is_array($hide)) {
    throw new RuntimeException('Expected SubmitForm, ResetForm, and Hide action review rows.');
}
if (in_array(6, $submit['field_objects'] ?? [], true) || in_array(6, $reset['field_objects'] ?? [], true) || in_array(6, $hide['field_objects'] ?? [], true)) {
    throw new RuntimeException('Stale generation-zero field reference leaked into action review targets.');
}
foreach (['current@example.test', 'Reviewed title', 'Default title', 'stale@example.test', 'https://example.test/submit'] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException('AcroForm action review metadata leaked into visible WordPress text.');
    }
}

echo '<!-- markerpdf:pdf-acroform-fields-action-generation-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-action-field-generation-boundary',
    'native_boundary' => 'AcroForm action field lists resolve object references with generation matching before WordPress import review.',
    'field_names' => array_keys($fields),
    'visible_text' => $visibleText,
    'submit_field_objects' => $submit['field_objects'] ?? [],
    'submit_field_names' => $submit['field_names'] ?? [],
    'submit_unresolved_field_objects' => $submit['unresolved_field_objects'] ?? [],
    'reset_field_objects' => $reset['field_objects'] ?? [],
    'reset_field_names' => $reset['field_names'] ?? [],
    'reset_unresolved_field_objects' => $reset['unresolved_field_objects'] ?? [],
    'hide_field_objects' => $hide['field_objects'] ?? [],
    'hide_field_names' => $hide['field_names'] ?? [],
    'hide_unresolved_field_objects' => $hide['unresolved_field_objects'] ?? [],
    'stale_generation_field_excluded' => !in_array(6, $submit['field_objects'] ?? [], true)
        && !in_array(6, $reset['field_objects'] ?? [], true)
        && !in_array(6, $hide['field_objects'] ?? [], true),
    'missing_field_targets_preserved_for_review' => ($submit['unresolved_field_objects'] ?? []) === [99]
        && ($reset['unresolved_field_objects'] ?? []) === [99]
        && ($hide['unresolved_field_objects'] ?? []) === [100],
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars('SubmitForm reviews ' . implode(', ', $submit['field_names'] ?? []) . ' and drops the stale generation-zero email target.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('ResetForm and Hide keep missing field objects as unresolved review metadata without executing PDF actions.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
