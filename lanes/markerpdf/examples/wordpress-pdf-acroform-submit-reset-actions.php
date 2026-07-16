<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R 10 0 R 12 0 R 14 0 R 16 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 9 0 R 11 0 R 13 0 R 15 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (registration.email) /V (editor@example.com) /DV (pending@example.com) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 620 320 644] /P 3 0 R /F 4 >>\nendobj\n"
    . "9 0 obj\n<< /FT /Tx /T (registration.notes) /DV (Default reviewer note) /Kids [10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 580 320 604] /P 3 0 R /F 4 >>\nendobj\n"
    . "11 0 obj\n<< /FT /Tx /T (registration.internal) /Ff 4 /V (Do not export) /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 11 0 R /Rect [72 540 320 564] /P 3 0 R /F 4 >>\nendobj\n"
    . "13 0 obj\n<< /FT /Btn /T (actions.submit) /Ff 65536 /Kids [14 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 13 0 R /Rect [72 500 180 524] /P 3 0 R /F 4 /A << /S /SubmitForm /F 20 0 R /Fields [6 0 R 9 0 R] /Flags 6 >> >>\nendobj\n"
    . "15 0 obj\n<< /FT /Btn /T (actions.reset) /Ff 65536 /Kids [16 0 R] /AA << /U << /S /ResetForm /Fields [6 0 R] /Flags 1 >> >> >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 15 0 R /Rect [192 500 300 524] /P 3 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (https://example.test/marker-import) >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$actions = [];
foreach ($form['fields'] as $field) {
    foreach ($field['actions'] ?? [] as $action) {
        $actions[] = $action + ['field' => $field['name']];
    }
    foreach ($field['widgets'] ?? [] as $widget) {
        foreach ($widget['actions'] ?? [] as $action) {
            $actions[] = $action + ['field' => $field['name']];
        }
    }
}

$submitTargets = array_values(array_filter(array_map(
    static fn (array $action): ?string => ($action['action_type'] ?? null) === 'SubmitForm' ? ($action['target'] ?? null) : null,
    $actions
)));
$resetModes = array_values(array_filter(array_map(
    static fn (array $action): ?string => ($action['action_type'] ?? null) === 'ResetForm' ? ($action['fields_mode'] ?? null) : null,
    $actions
)));

echo '<!-- markerpdf:pdf-acroform-submit-reset-actions ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-widget-actions',
    'native_boundary' => 'widget /A SubmitForm and field /AA ResetForm review metadata before WordPress import rendering',
    'action_count' => count($actions),
    'submit_targets' => $submitTargets,
    'reset_modes' => $resetModes,
    'executes_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars('PDF form action review: native metadata only; no SubmitForm or ResetForm action was executed.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($actions as $action) {
    $fields = ($action['field_names'] ?? []) === [] ? 'all fields' : implode(', ', $action['field_names']);
    $line = match ($action['action_type'] ?? '') {
        'SubmitForm' => sprintf(
            '%s: %s %s to %s as %s; fields mode %s: %s',
            (string) $action['field'],
            (string) $action['trigger_label'],
            (string) $action['action_type'],
            (string) ($action['target'] ?? 'missing target'),
            (string) ($action['submit_format'] ?? 'unknown'),
            (string) ($action['fields_mode'] ?? 'unknown'),
            $fields
        ),
        'ResetForm' => sprintf(
            '%s: %s %s; fields mode %s: %s',
            (string) $action['field'],
            (string) $action['trigger_label'],
            (string) $action['action_type'],
            (string) ($action['fields_mode'] ?? 'unknown'),
            $fields
        ),
        default => null,
    };

    if ($line !== null) {
        echo '<li>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
    }
}
echo "</ul>\n<!-- /wp:list -->\n";
