<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm direct Parent Kids boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent << /FT /Tx /T (direct.parent.kids) /TU (Direct Parent Kids label) /TM (direct-parent-kids-export) /V (Direct Parent Kids value) /DV (Direct Parent Kids default) /MaxLen 52 /Kids [<< /F 4 /P 3 0 R /Rect [72 640 320 664] /Sub#74ype /Widget >>] >> /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent << /FT /Tx /T (direct.parent.wrongkid.decoy) /TU (Wrong direct Parent Kids label) /TM (wrong-direct-parent-kids-export) /V (Wrong direct Parent Kids value) /Kids [<< /Subtype /Widget /Parent 99 0 R /F 4 /P 3 0 R /Rect [72 600 320 624] >>] >> /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "99 0 obj\n<< /FT /Tx /T (detached.wrongkid.parent) /V (Detached wrong direct kid parent value) >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

if (array_keys($fieldsByName) !== ['direct.parent.kids']) {
    throw new RuntimeException('Unexpected AcroForm field set for direct Parent Kids boundary.');
}

$field = $fieldsByName['direct.parent.kids'];
$encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);
foreach ([
    'direct.parent.wrongkid.decoy',
    'Wrong direct Parent Kids label',
    'wrong-direct-parent-kids-export',
    'Wrong direct Parent Kids value',
    'detached.wrongkid.parent',
    'Detached wrong direct kid parent value',
] as $decoyText) {
    if (str_contains($encoded, $decoyText) || str_contains($visibleText, $decoyText)) {
        throw new RuntimeException("Direct Parent Kids decoy leaked into WordPress review: {$decoyText}");
    }
}

foreach ([
    'Direct Parent Kids value',
    'Direct Parent Kids default',
    'Direct Parent Kids label',
] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException("AcroForm review-only field text leaked into visible WordPress text: {$reviewOnlyText}");
    }
}

$widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
$widgetObjects = array_values(array_filter(array_map(
    static fn (array $widget): mixed => $widget['object'] ?? null,
    $widgets
), static fn (mixed $object): bool => is_int($object)));

echo '<!-- markerpdf:pdf-acroform-fields-direct-parent-kids-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-direct-parent-kids-boundary',
    'native_boundary' => 'page-owned widgets whose direct Parent field dictionaries carry direct Kids widgets are matched to the real page annotation only when the synthetic direct kid belongs to that parent',
    'field_count' => count($form['fields']),
    'field_names' => array_keys($fieldsByName),
    'accepted_field_object_is_synthetic' => is_int($field['object'] ?? null) && ($field['object'] ?? 0) > 99,
    'page_widget_objects' => $widgetObjects,
    'page_widget_referenced' => $widgetObjects === [8],
    'wrong_parent_direct_kid_excluded' => !str_contains($encoded, 'direct.parent.wrongkid.decoy'),
    'field_values_review_only' => !str_contains($visibleText, 'Direct Parent Kids value')
        && !str_contains($visibleText, 'Direct Parent Kids default'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Value</th><th>Widget</th><th>Boundary</th></tr>\n";
echo '<tr><td>' . htmlspecialchars((string) ($field['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars((string) ($field['field_type_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars((string) ($field['value_state']['display_value'] ?? $field['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>' . htmlspecialchars(implode(',', array_map('strval', $widgetObjects)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
echo '<td>direct-parent-kids-review-only</td></tr>' . "\n";
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
