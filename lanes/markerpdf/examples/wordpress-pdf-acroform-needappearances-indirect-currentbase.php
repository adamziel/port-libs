<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildPdf = static function (string $needAppearancesObjectBody): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect NeedAppearances body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances 30 0 R /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (needappearances.indirect) /TU (Indirect NeedAppearances label) /TM (needappearances-export) /V (Indirect NeedAppearances value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n{$needAppearancesObjectBody}\nendobj\n"
        . "%%EOF";
};

$extractByName = static function (array $form): array {
    $fieldsByName = [];
    foreach ($form['fields'] as $field) {
        $fieldsByName[(string) ($field['name'] ?? '')] = $field;
    }

    return $fieldsByName;
};

$extractor = new PdfAcroFormExtractor();
$textExtractor = new PdfTextExtractor();

$validPdf = $buildPdf('true');
$tailedPdf = $buildPdf('true /BadOperand');
$validForm = $extractor->extractForm($validPdf);
$tailedForm = $extractor->extractForm($tailedPdf);
$validFields = $extractByName($validForm);
$tailedFields = $extractByName($tailedForm);
$validText = $textExtractor->extractPlainText($validPdf);
$tailedText = $textExtractor->extractPlainText($tailedPdf);

$validField = $validFields['needappearances.indirect'] ?? null;
$tailedField = $tailedFields['needappearances.indirect'] ?? null;
if (!is_array($validField) || !is_array($tailedField)) {
    throw new RuntimeException('Expected the indirect NeedAppearances AcroForm field to remain reviewable.');
}
if (($validForm['need_appearances'] ?? null) !== true) {
    throw new RuntimeException('Expected a complete indirect true NeedAppearances object to resolve.');
}
if (($tailedForm['need_appearances'] ?? null) !== false) {
    throw new RuntimeException('Expected a tailed indirect NeedAppearances object to fail closed.');
}

foreach ([$validText, $tailedText] as $text) {
    if (str_contains($text, 'Indirect NeedAppearances value')) {
        throw new RuntimeException('AcroForm field values must stay out of visible WordPress text.');
    }
}

$tailedEncoded = (string) json_encode($tailedForm, JSON_UNESCAPED_SLASHES);
if (str_contains($tailedEncoded, 'BadOperand')) {
    throw new RuntimeException('Tailed indirect NeedAppearances operands must not leak into form review metadata.');
}

$rows = [[
    'case' => 'indirect true',
    'need_appearances' => $validForm['need_appearances'] ?? null,
    'field' => $validField['name'] ?? null,
    'widgets' => array_column(is_array($validField['widgets'] ?? null) ? $validField['widgets'] : [], 'object'),
], [
    'case' => 'tailed indirect true',
    'need_appearances' => $tailedForm['need_appearances'] ?? null,
    'field' => $tailedField['name'] ?? null,
    'widgets' => array_column(is_array($tailedField['widgets'] ?? null) ? $tailedField['widgets'] : [], 'object'),
]];

echo '<!-- markerpdf:pdf-acroform-needappearances-indirect-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-needappearances-indirect-boundary',
    'native_boundary' => 'AcroForm /NeedAppearances accepts a complete indirect boolean object before WordPress form review; tailed indirect boolean objects fail closed while fields remain reviewable.',
    'valid_indirect_need_appearances' => $validForm['need_appearances'] ?? null,
    'tailed_indirect_need_appearances' => $tailedForm['need_appearances'] ?? null,
    'field_names' => array_column($rows, 'field'),
    'visible_text_excludes_field_values' => !str_contains($validText, 'Indirect NeedAppearances value') && !str_contains($tailedText, 'Indirect NeedAppearances value'),
    'tailed_operand_excluded' => !str_contains($tailedEncoded, 'BadOperand'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Case</th><th>NeedAppearances</th><th>Field</th><th>Widgets</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['case'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(json_encode($row['need_appearances'], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['field'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(',', array_map('strval', $row['widgets'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
