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

$currentPageText = 'BT /F1 12 Tf 72 720 Td (Current xref generation AcroForm page body) Tj ET';
$stalePageText = 'BT /F1 12 Tf 72 720 Td (Stale higher generation AcroForm page body) Tj ET';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    if ($generation === 0) {
        $offsets[$objectNumber] = strlen($pdf);
    }

    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};

$addObject(20, 0, '<< /Type /Catalog /Pages 21 0 R /AcroForm 25 0 R >>');
$addObject(21, 0, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, 0, '<< /Type /Page /Parent 21 0 R /Contents 23 0 R /Annots [28 0 R 32 0 R] >>');
$addObject(23, 0, "<< /Length " . strlen($currentPageText) . " >>\nstream\n{$currentPageText}\nendstream");
$addObject(25, 0, '<< /Fields [26 0 R 30 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>');
$addObject(26, 0, '<< /FT /Tx /T (current.xref.email) /TU (Current xref email label) /V (current-xref@example.test) /Kids [28 0 R] >>');
$addObject(28, 0, '<< /Subtype /Widget /Parent 26 0 R /Rect [72 640 300 664] /P 22 0 R /F 4 >>');
$addObject(30, 0, '<< /FT /Ch /T (current.xref.status) /V (publish) /Opt [(draft) (publish)] /Kids [32 0 R] >>');
$addObject(32, 0, '<< /Subtype /Widget /Parent 30 0 R /Rect [72 600 260 624] /P 22 0 R /F 4 >>');

$addObject(20, 1, '<< /Type /Catalog /Pages 41 1 R /AcroForm 45 1 R >>');
$addObject(41, 1, '<< /Type /Pages /Kids [42 1 R] /Count 1 >>');
$addObject(42, 1, '<< /Type /Page /Parent 41 1 R /Contents 43 1 R /Annots [48 1 R] >>');
$addObject(43, 1, "<< /Length " . strlen($stalePageText) . " >>\nstream\n{$stalePageText}\nendstream");
$addObject(45, 1, '<< /Fields [46 1 R] /NeedAppearances false /DA (/Stale 9 Tf 1 0 0 rg) >>');
$addObject(46, 1, '<< /FT /Tx /T (stale.xref.email) /TU (Stale xref email label) /V (stale-xref@example.test) /Kids [48 1 R] >>');
$addObject(48, 1, '<< /Subtype /Widget /Parent 46 1 R /Rect [72 640 300 664] /P 42 1 R /F 4 >>');

$xrefOffset = strlen($pdf);
$maxObject = 48;
$pdf .= "xref\n0 " . ($maxObject + 1) . "\n"
    . "0000000000 65535 f \n";
for ($objectNumber = 1; $objectNumber <= $maxObject; $objectNumber++) {
    $pdf .= isset($offsets[$objectNumber])
        ? sprintf("%010d 00000 n \n", $offsets[$objectNumber])
        : "0000000000 00000 f \n";
}

$pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 20 0 R >>\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fields = $fieldsByName($form['fields']);
$encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

foreach (['current.xref.email', 'current.xref.status'] as $fieldName) {
    if (!isset($fields[$fieldName])) {
        throw new RuntimeException("Missing xref-selected AcroForm field {$fieldName}.");
    }
}
foreach (['stale.xref.email', 'Stale xref email label', 'stale-xref@example.test'] as $staleText) {
    if ((is_string($encoded) && str_contains($encoded, $staleText)) || str_contains($visibleText, $staleText)) {
        throw new RuntimeException("Stale higher-generation AcroForm text leaked into WordPress review: {$staleText}");
    }
}
if ($visibleText !== 'Current xref generation AcroForm page body') {
    throw new RuntimeException('Visible text did not follow the xref-selected page generation.');
}

$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'type' => $field['field_type_label'] ?? $field['field_type'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'widgets' => array_column($widgets, 'object'),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-xref-generation-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-xref-generation-boundary',
    'native_boundary' => 'Classic xref/startxref rows select the AcroForm catalog, field, and widget object generations before WordPress form review.',
    'field_names' => array_column($rows, 'name'),
    'field_count' => count($form['fields']),
    'xref_selected_fields_preserved' => isset($fields['current.xref.email'], $fields['current.xref.status']),
    'stale_higher_generation_fields_excluded' => is_string($encoded)
        && !str_contains($encoded, 'stale.xref.email')
        && !str_contains($encoded, 'stale-xref@example.test')
        && !str_contains($visibleText, 'Stale higher generation AcroForm page body'),
    'form_values_visible_in_text' => str_contains($visibleText, 'current-xref@example.test')
        || str_contains($visibleText, 'publish')
        || str_contains($visibleText, 'stale-xref@example.test'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Review value</th><th>Widget objects</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(',', array_map('strval', $row['widgets'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
