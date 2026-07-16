<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$calculateScript = "event.value = this.getField('invoice.amount').value * 1.0825;";
$compressedCalculateScript = gzcompress($calculateScript);
if (!is_string($compressedCalculateScript)) {
    throw new RuntimeException('Unable to compress calculation script fixture.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R 11 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /CO [10 0 R 6 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (invoice.amount) /V (25.00) /Kids [8 0 R] /AA << /K 20 0 R /F << /S /JavaScript /JS (AFNumber_Format\\(2, 0, 0, 0, \"\", true\\);) >> /V << /S /JavaScript /JS (if \\(event.value < 0\\) event.rc = false;) >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 620 240 644] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (invoice.total) /V (27.06) /Kids [11 0 R] /AA << /C << /S /JavaScript /JS 30 0 R >> >> >>\nendobj\n"
    . "11 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 580 240 604] /P 3 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n<< /S /JavaScript /JS (AFNumber_Keystroke\\(2, 0, 0, 0, \"\", true\\);) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($compressedCalculateScript) . " /Filter /FlateDecode >>\nstream\n"
    . $compressedCalculateScript
    . "\nendstream\nendobj\n"
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

echo '<!-- markerpdf:pdf-acroform-calculation-format-actions ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-additional-actions',
    'native_boundary' => 'AcroForm /CO plus field/widget /AA keystroke format validate calculate JavaScript review metadata',
    'calculation_order' => array_column($form['calculation_order'], 'field_name'),
    'action_count' => count($actions),
    'action_triggers' => array_column($actions, 'trigger_label'),
    'script_hashes' => array_column($actions, 'script_sha256'),
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars('PDF AcroForm calculation and formatting scripts were reviewed as metadata only; no JavaScript was executed.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($actions as $action) {
    $line = sprintf(
        '%s: %s %s action, script hash %s',
        (string) $action['field'],
        (string) $action['trigger_label'],
        (string) $action['action_type'],
        substr((string) ($action['script_sha256'] ?? ''), 0, 16)
    );
    echo '<li>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
