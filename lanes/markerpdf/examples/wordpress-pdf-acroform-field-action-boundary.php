<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (registration.url) /V (https://example.test/import) /Kids [8 0 R] /AA << /K << /S /Named /N /Print >> /V 20 0 R >> >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 620 320 644] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Btn /T (actions.import_data) /Ff 65536 /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 580 240 604] /P 3 0 R /F 4 /A 21 0 R >>\nendobj\n"
    . "20 0 obj\n<< /S /URI /URI (javascript:app.alert\\('blocked field validation'\\)) /Next [22 0 R << /S /Hide /T [(registration.url) 8 0 R] /H true >>] >>\nendobj\n"
    . "21 0 obj\n<< /S /ImportData /F 30 0 R >>\nendobj\n"
    . "22 0 obj\n<< /S /Launch /F (cmd.exe) /NewWindow true >>\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (review.fdf) >>\nendobj\n"
    . "%%EOF";

$fields = (new PdfAcroFormExtractor())->extractFields($pdf);
$actions = [];
foreach ($fields as $field) {
    foreach ($field['actions'] ?? [] as $action) {
        $actions[] = $action + ['field' => $field['name']];
    }

    foreach ($field['widgets'] ?? [] as $widget) {
        foreach ($widget['actions'] ?? [] as $action) {
            $actions[] = $action + ['field' => $field['name']];
        }
    }
}

$summary = [
    'source' => 'native-pdf-acroform-field-action-review',
    'native_boundary' => 'AcroForm field/widget action dictionaries are review metadata and are never executed during WordPress import',
    'action_types' => array_column($actions, 'action_type'),
    'safety_labels' => array_column($actions, 'safety'),
    'unsafe_uri_blocked' => in_array('blocked-unsafe-uri', array_column($actions, 'safety'), true),
    'launch_review_only' => in_array('launch-action-review', array_column($actions, 'safety'), true),
    'hide_targets' => array_values(array_unique(array_merge(...array_map(
        static fn (array $action): array => is_array($action['field_names'] ?? null) ? $action['field_names'] : [],
        $actions
    )))),
    'import_data_targets' => array_values(array_filter(array_map(
        static fn (array $action): ?string => ($action['action_type'] ?? null) === 'ImportData' ? ($action['target'] ?? null) : null,
        $actions
    ))),
    'executes_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-acroform-field-action-boundary ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($actions as $action) {
    $line = sprintf(
        '%s: %s %s, safety %s',
        (string) $action['field'],
        (string) $action['trigger_label'],
        (string) $action['action_type'],
        (string) ($action['safety'] ?? 'review')
    );
    echo '<li>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
