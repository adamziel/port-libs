<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$selfPageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm self Kids cycle body) Tj ET';
$selfPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($selfPageText) . " >>\nstream\n{$selfPageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.self) /TU (Self cycle title label) /V (Self cycle field value) /DV (Self cycle default value) /MaxLen 80 /Kids [6 0 R 8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$ancestorPageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm ancestor Kids cycle body) Tj ET';
$ancestorPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($ancestorPageText) . " >>\nstream\n{$ancestorPageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (profile) /TU (Profile root label) /TM (profile-root-map) /V (parent@example.test) /DV (default@example.test) /MaxLen 64 /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Parent 10 0 R /T (email) /TU (Email child label) /TM (profile.email.export) /V (editor@example.test) /Kids [10 0 R 14 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfAcroFormExtractor();
$textExtractor = new PdfTextExtractor();
$selfForm = $extractor->extractForm($selfPdf);
$ancestorForm = $extractor->extractForm($ancestorPdf);
$selfText = $textExtractor->extractPlainText($selfPdf);
$ancestorText = $textExtractor->extractPlainText($ancestorPdf);

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[(string) ($field['name'] ?? '')] = $field;
    }

    return $indexed;
};

$selfFields = $fieldsByName($selfForm['fields']);
$ancestorFields = $fieldsByName($ancestorForm['fields']);

if (!isset($selfFields['article.self'])) {
    throw new RuntimeException('Expected self-referential Kids field to stay importable as review metadata.');
}
if (!isset($ancestorFields['profile.email'])) {
    throw new RuntimeException('Expected ancestor-cycle Kids field to stay bounded to the terminal branch.');
}
if (
    str_contains($selfText, 'Self cycle field value')
    || str_contains($selfText, 'Self cycle default value')
    || str_contains($ancestorText, 'editor@example.test')
    || str_contains($ancestorText, 'parent@example.test')
) {
    throw new RuntimeException('AcroForm field values must not become visible WordPress paragraph text.');
}

echo '<!-- markerpdf:pdf-acroform-fields-cycle-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-fields-cycle-boundary-currentbase',
    'native_boundary' => 'Malformed AcroForm /Kids cycles are ignored during field-tree descent while terminal field dictionaries, inherited attributes, and valid page widgets remain review-only metadata.',
    'self_cycle_field_names' => array_keys($selfFields),
    'ancestor_cycle_field_names' => array_keys($ancestorFields),
    'self_cycle_widget_objects' => array_column($selfFields['article.self']['widgets'] ?? [], 'object'),
    'ancestor_cycle_widget_objects' => array_column($ancestorFields['profile.email']['widgets'] ?? [], 'object'),
    'self_cycle_preserved_terminal_value' => ($selfFields['article.self']['value'] ?? null) === 'Self cycle field value',
    'ancestor_cycle_preserved_terminal_value' => ($ancestorFields['profile.email']['value'] ?? null) === 'editor@example.test',
    'visible_text_contains_form_values' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ([$selfFields['article.self'], $ancestorFields['profile.email']] as $field) {
    echo '<li>' . htmlspecialchars(
        (string) ($field['name'] ?? '') . ': ' . (string) ($field['field_name_review']['wordpress_label'] ?? $field['name'] ?? '') . ' [AcroForm review metadata]',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
