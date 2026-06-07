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

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm action dictionary boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 20 0 R 24 0 R] /NeedAppearances true >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.title) /V (Visible title review value) /Kids [8 0 R 22 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (article.summary) /V (Visible summary review value) /Kids [12 0 R] /AA << /K 32 0 R >> >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "20 0 obj\n<< /S /Hide /T (decoy.root.hide) /V (Root hide action decoy value) /H true >>\nendobj\n"
    . "22 0 obj\n<< /S /URI /T (decoy.kid.uri) /URI (javascript:alert('kid')) /V (Kid URI action decoy value) >>\nendobj\n"
    . "24 0 obj\n<< /S /JavaScript /T (decoy.root.javascript) /JS (app.alert('root')) /V (Root JavaScript action decoy value) >>\nendobj\n"
    . "32 0 obj\n<< /S /Hide /T [6 0 R (named.target)] /H true >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = $fieldsByName($form['fields']);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

if (array_keys($fields) !== ['article.title', 'article.summary']) {
    throw new RuntimeException('Expected action dictionaries to stay out of AcroForm field review.');
}

$summary = $fields['article.summary'] ?? null;
if (!is_array($summary)) {
    throw new RuntimeException('Expected article.summary field metadata.');
}

$summaryActions = $actionsByTrigger($summary['actions'] ?? []);
$hide = $summaryActions['K'] ?? null;
if (!is_array($hide) || ($hide['action_type'] ?? null) !== 'Hide') {
    throw new RuntimeException('Expected summary keystroke Hide action review metadata.');
}
if (($hide['executes_action'] ?? true) !== false || ($hide['executes_javascript'] ?? true) !== false) {
    throw new RuntimeException('PDF actions must stay review-only in the WordPress smoke.');
}

$decoyTerms = [
    'decoy.root.hide',
    'Root hide action decoy value',
    'decoy.kid.uri',
    'Kid URI action decoy value',
    'decoy.root.javascript',
    'Root JavaScript action decoy value',
    "javascript:alert('kid')",
    "app.alert('root')",
];
foreach ($decoyTerms as $decoyTerm) {
    if (str_contains($encoded, $decoyTerm) || str_contains($visibleText, $decoyTerm)) {
        throw new RuntimeException('AcroForm action dictionary decoy leaked into WordPress-visible review data.');
    }
}

foreach (['Visible title review value', 'Visible summary review value'] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException('AcroForm field review value leaked into visible WordPress text.');
    }
}

echo '<!-- markerpdf:pdf-acroform-fields-action-dictionary-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-field-action-dictionary-boundary',
    'native_boundary' => 'Action dictionaries listed in AcroForm /Fields or field /Kids are rejected as non-field dictionaries while legitimate field /AA Hide action metadata remains review-only.',
    'field_names' => array_keys($fields),
    'visible_text' => $visibleText,
    'decoy_action_fields_excluded' => true,
    'real_hide_action_reviewed' => ($hide['field_objects'] ?? []) === [6]
        && ($hide['field_names'] ?? []) === ['article.title', 'named.target'],
    'hide_field_objects' => $hide['field_objects'] ?? [],
    'hide_field_names' => $hide['field_names'] ?? [],
    'hide_unresolved_field_objects' => $hide['unresolved_field_objects'] ?? [],
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars('Imported field review keeps article.title and article.summary while rejecting root and kid action dictionaries with target names.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars('The legitimate Hide action remains review-only metadata for article.summary and does not execute JavaScript or form actions.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
