<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$utf16Hex = static function (string $value): string {
    $encoded = iconv('UTF-8', 'UTF-16BE', $value);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode UTF-16BE fixture string.');
    }

    return '<FEFF' . strtoupper(bin2hex($encoded)) . '>';
};

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm FileSpec resource body) Tj ET';
$submitPayload = 'Submitted payload blocked';
$relatedPayload = 'Related stylesheet blocked';
$importPayload = 'Imported FDF payload blocked';
$launchPayload = 'Launch helper payload blocked';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R 20 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R 18 0 R] /DA (/Body 10 Tf 0 0 0 rg) /DR 30 0 R >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.title) /V (Review value) /DA (/Body 10 Tf 0 0 0 rg) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 360 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Btn /T (actions.submit_filespec) /Ff 65536 /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 190 624] /P 3 0 R /F 4 /A << /S /SubmitForm /F 40 0 R /Fields [6 0 R] /Flags 36 >> >>\nendobj\n"
    . "14 0 obj\n<< /FT /Btn /T (actions.import_filespec) /Ff 65536 /Kids [16 0 R] /AA << /U << /S /ImportData /F 50 0 R >> >> >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [200 600 318 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "18 0 obj\n<< /FT /Btn /T (actions.launch_filespec) /Ff 65536 /Kids [20 0 R] >>\nendobj\n"
    . "20 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [328 600 446 624] /P 3 0 R /F 4 /A << /S /Launch /F 60 0 R /Win << /F 61 0 R /O (open) /P (--review-only) /D (C:\\\\blocked) >> /NewWindow true >> >>\nendobj\n"
    . "30 0 obj\n<< /Font << /Body 31 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "40 0 obj\n<< /Type /Filespec /FS /URL /F (https://example.test/fallback-export.fdf) /UF " . $utf16Hex('https://example.test/current-export.xfdf') . " /Desc (Current submit endpoint) /AFRelationship /FormData /EF << /F 41 0 R >> /RF << /F [(review-style.css) 42 0 R] >> >>\nendobj\n"
    . "41 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fvnd.adobe.xfdf /Params << /Size " . strlen($submitPayload) . " /CheckSum (submit-checksum) /ModDate (D:20260602194000Z) >> /Length " . strlen($submitPayload) . " >>\nstream\n{$submitPayload}\nendstream\nendobj\n"
    . "42 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /Params << /Size " . strlen($relatedPayload) . " /CheckSum (related-checksum) >> /Length " . strlen($relatedPayload) . " >>\nstream\n{$relatedPayload}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Filespec /F (review-import.fdf) /Desc (ImportData source) /AFRelationship /Data /EF << /F 51 0 R >> >>\nendobj\n"
    . "51 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fvnd.fdf /Params << /Size " . strlen($importPayload) . " /CheckSum (import-checksum) >> /Length " . strlen($importPayload) . " >>\nstream\n{$importPayload}\nendstream\nendobj\n"
    . "60 0 obj\n<< /Type /Filespec /F (fallback-launch.exe) /UF (launch-current.exe) /Desc (Launch helper) /EF << /F 62 0 R >> >>\nendobj\n"
    . "61 0 obj\n<< /Type /Filespec /F (win-fallback.exe) /UF (win-current.exe) >>\nendobj\n"
    . "62 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Foctet-stream /Params << /Size " . strlen($launchPayload) . " /CheckSum (launch-checksum) >> /Length " . strlen($launchPayload) . " >>\nstream\n{$launchPayload}\nendstream\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = [];
foreach ($form['fields'] as $field) {
    $fields[$field['name']] = $field;
}

$submit = $fields['actions.submit_filespec']['widgets'][0]['actions'][0] ?? null;
$import = $fields['actions.import_filespec']['actions'][0] ?? null;
$launch = $fields['actions.launch_filespec']['widgets'][0]['actions'][0] ?? null;
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

if (!is_array($submit) || !is_array($import) || !is_array($launch)) {
    throw new RuntimeException('Expected AcroForm FileSpec action rows.');
}
if (!is_array($submit['file_spec'] ?? null) || !is_array($import['file_spec'] ?? null) || !is_array($launch['file_spec'] ?? null)) {
    throw new RuntimeException('Expected FileSpec review metadata on AcroForm actions.');
}
foreach ([$submitPayload, $relatedPayload, $importPayload, $launchPayload, 'current-export.xfdf', 'review-import.fdf', 'launch-current.exe'] as $blockedText) {
    if (str_contains($visibleText, $blockedText)) {
        throw new RuntimeException('AcroForm FileSpec action payload leaked into visible text.');
    }
}

echo '<!-- markerpdf:pdf-acroform-resource-action-filespec-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-resource-action-filespec-review',
    'native_boundary' => 'AcroForm default resources remain form metadata, while SubmitForm, ImportData, and Launch FileSpec dictionaries are review-only action metadata before WordPress import.',
    'visible_text' => $visibleText,
    'default_resource_font' => $form['default_resources']['fonts']['Body']['base_font'] ?? null,
    'submit_target' => $submit['target'] ?? null,
    'submit_file_spec_object' => $submit['file_spec']['file_spec_object'] ?? null,
    'submit_file_spec_relationship' => $submit['file_spec']['relationship'] ?? null,
    'submit_embedded_file_objects' => $submit['file_spec']['embedded_file_objects'] ?? [],
    'submit_related_file_count' => $submit['file_spec']['related_file_count'] ?? 0,
    'import_target' => $import['target'] ?? null,
    'import_relationship' => $import['file_spec']['relationship'] ?? null,
    'launch_target' => $launch['target'] ?? null,
    'launch_platform_target' => $launch['platform_file_spec']['filename'] ?? null,
    'executes_form_actions' => false,
    'submits_pdf_on_import' => false,
    'imports_form_data' => false,
    'embedded_payload_text_exposed' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars('AcroForm /DR resolves Body to ' . (string) ($form['default_resources']['fonts']['Body']['base_font'] ?? 'unknown') . ' for review metadata.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('SubmitForm reviews FileSpec target ' . (string) ($submit['target'] ?? '') . ' and ' . count($submit['file_spec']['embedded_files'] ?? []) . ' embedded file stream without submitting data during import.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('ImportData and Launch FileSpec actions remain non-executing review rows.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
