<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$calculateScript = "event.value = Number(this.getField('invoice.amount').value) + 3;";
$compressedCalculateScript = gzcompress($calculateScript);
if (!is_string($compressedCalculateScript)) {
    throw new RuntimeException('Unable to compress calculation script fixture.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R 11 0 R 14 0 R 17 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 13 0 R 16 0 R] /SigFlags 3 /CO [10 0 R 6 0 R 13 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (invoice.amount) /V (25.00) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 240 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (invoice.total) /V (28.00) /Kids [11 0 R] /AA << /C << /S /JavaScript /JS 30 0 R >> >> >>\nendobj\n"
    . "11 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 240 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "13 0 obj\n<< /FT /Sig /T (approval.signature) /V 31 0 R /Lock 32 0 R /Kids [14 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 13 0 R /Rect [72 552 300 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "16 0 obj\n<< /FT /Tx /T (internal.notes) /V (editable review note) /Kids [17 0 R] >>\nendobj\n"
    . "17 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [72 512 300 536] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($compressedCalculateScript) . " /Filter /FlateDecode >>\nstream\n"
    . $compressedCalculateScript
    . "\nendstream\nendobj\n"
    . "31 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Editor Reviewer) /M (D:20260602081217Z) /ByteRange [0 128 512 64] /Contents <0102030405> >>\nendobj\n"
    . "32 0 obj\n<< /Type /SigFieldLock /Action /Include /Fields [(invoice.amount) (invoice.total)] /P 2 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = $form['fields'];

$calculationOrderNames = array_values(array_filter(array_map(
    static fn (array $entry): ?string => is_string($entry['field_name'] ?? null) ? $entry['field_name'] : null,
    $form['calculation_order']
)));
$calculatedFields = [];
$lockedFields = [];
$signatureRows = [];
foreach ($fields as $field) {
    $calculation = is_array($field['calculation_state'] ?? null) ? $field['calculation_state'] : [];
    $lock = is_array($field['signature_lock_state'] ?? null) ? $field['signature_lock_state'] : [];
    if (($calculation['has_calculate_action'] ?? false) === true) {
        $calculatedFields[] = (string) $field['name'];
    }
    if (($lock['effective_locked'] ?? false) === true) {
        $lockedFields[] = (string) $field['name'];
    }
    if (($field['field_type'] ?? null) === 'Sig' && is_array($field['signature_state'] ?? null)) {
        $signatureRows[] = [
            'name' => $field['name'],
            'signed' => $field['signature_state']['signed'] ?? false,
            'append_only' => $field['signature_state']['append_only'] ?? false,
            'byte_range_segments' => $field['signature_state']['byte_range_segment_count'] ?? 0,
        ];
    }
}

echo '<!-- markerpdf:pdf-acroform-calculation-signature-state ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-catalog-acroform-calculation-signature-state',
    'native_boundary' => 'catalog /AcroForm /SigFlags /CO, field /AA /C JavaScript review, and signed /Lock field-state metadata before WordPress import rendering',
    'signature_flags' => $form['signature_flags'],
    'calculation_order' => $calculationOrderNames,
    'calculated_fields' => $calculatedFields,
    'signature_fields' => $signatureRows,
    'locked_fields' => $lockedFields,
    'script_sha256' => hash('sha256', $calculateScript),
    'executes_calculations' => false,
    'executes_javascript' => false,
    'executes_signature_validation' => false,
    'executes_signing' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($fields as $field) {
    $calculation = is_array($field['calculation_state'] ?? null) ? $field['calculation_state'] : [];
    $lock = is_array($field['signature_lock_state'] ?? null) ? $field['signature_lock_state'] : [];
    $parts = [
        (string) $field['field_type_label'],
        'value ' . (($field['value'] ?? null) === null ? 'review-only' : (string) $field['value']),
    ];
    if (($calculation['in_calculation_order'] ?? false) === true) {
        $parts[] = 'calculation order #' . ((int) ($calculation['calculation_order_index'] ?? -1) + 1);
    }
    if (($calculation['has_calculate_action'] ?? false) === true) {
        $parts[] = 'calculate script reviewed, not executed';
    }
    if (($lock['effective_locked'] ?? false) === true) {
        $parts[] = 'locked by ' . implode(', ', $lock['locked_by_signatures'] ?? []);
    }
    if (($field['field_type'] ?? null) === 'Sig') {
        $signatureState = is_array($field['signature_state'] ?? null) ? $field['signature_state'] : [];
        $parts[] = (($signatureState['signed'] ?? false) === true ? 'signed' : 'unsigned') . ' append-only review';
    }

    echo '<li>' . htmlspecialchars((string) $field['name'] . ': ' . implode('; ', $parts), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
