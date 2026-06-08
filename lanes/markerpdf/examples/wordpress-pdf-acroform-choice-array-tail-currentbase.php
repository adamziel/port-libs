<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm choice array tail body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 18 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 16 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Ch /T (workflow.tailed) /V [(publish)] 90 0 R /DV [(draft)] 91 0 R /Opt [[(draft) (Draft label)] [(publish) (Published label)]] 92 0 R /I [1] 93 0 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "16 0 obj\n<< /FT /Ch /T (workflow.comment) /V [(review)] % current value comment tail\n/DV [(draft)] % default value comment tail\n/Opt [[(draft) (Draft label)] [(review) (Review label)]] % option comment tail\n/I [1] % selected-index comment tail\n/Kids [18 0 R] >>\nendobj\n"
    . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "90 0 obj\n[(stale current choice must not surface)]\nendobj\n"
    . "91 0 obj\n[(stale default choice must not surface)]\nendobj\n"
    . "92 0 obj\n[[(stale export must not surface) (Stale label must not surface)]]\nendobj\n"
    . "93 0 obj\n[0]\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

$tailed = $fieldsByName['workflow.tailed'] ?? null;
$comment = $fieldsByName['workflow.comment'] ?? null;
if (!is_array($tailed) || !is_array($comment)) {
    throw new RuntimeException('Expected both choice boundary fields to be available for review.');
}
if (($tailed['value'] ?? null) !== null || ($tailed['default_value'] ?? null) !== null || ($tailed['options'] ?? []) !== []) {
    throw new RuntimeException('Tailed direct choice arrays must be excluded from form review metadata.');
}
if (($comment['value'] ?? null) !== ['review'] || ($comment['value_state']['selected_indices'] ?? null) !== [1]) {
    throw new RuntimeException('Comment-only choice array tails should preserve the selected review value.');
}
if ($visibleText !== 'Visible AcroForm choice array tail body') {
    throw new RuntimeException('Expected only page text to reach the WordPress visible-text path.');
}

$encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);
foreach (['publish', 'Published label', 'stale export must not surface', 'Stale label must not surface'] as $blockedText) {
    if (str_contains($encoded, $blockedText) || str_contains($visibleText, $blockedText)) {
        throw new RuntimeException('Malformed choice-array payload leaked into review or visible text.');
    }
}

$summary = [
    'source' => 'native-pdf-acroform-choice-array-tail-currentbase',
    'native_boundary' => 'AcroForm direct choice array operands with non-comment top-level tails are ignored before WordPress form review, while comment-only tails remain usable',
    'field_names' => array_keys($fieldsByName),
    'tailed_choice_arrays_excluded' => ($tailed['value'] ?? null) === null
        && ($tailed['default_value'] ?? null) === null
        && ($tailed['options'] ?? []) === [],
    'comment_choice_selected_indices' => $comment['value_state']['selected_indices'] ?? [],
    'comment_choice_selected_options' => $comment['value_state']['selected_options'] ?? [],
    'visible_text' => $visibleText,
    'payload_text_exposed' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-acroform-choice-array-tail-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Current value</th><th>Selected options</th></tr>\n";
foreach ($fieldsByName as $name => $field) {
    $selected = implode(', ', array_map(
        static fn (array $option): string => (string) ($option['label'] ?? $option['export'] ?? ''),
        is_array($field['value_state']['selected_options'] ?? null) ? $field['value_state']['selected_options'] : []
    ));
    echo '<tr><td>' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) ($field['value_state']['display_value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars($selected, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
