<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R 12 0 R 16 0 R 18 0 R 22 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R 20 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.title) /V (Final import title) /DV (Draft import title) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Ch /T (article.topics) /Ff 2097152 /V [(plugin) (themes)] /DV (blocks) /I [1 0] /Opt [[(themes) (Themes)] [(plugin) (Plugins)] [(blocks) (Blocks)]] /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /FT /Btn /T (delivery.method) /Ff 49152 /V /Online /DV /Pickup /Kids [16 0 R 18 0 R] >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 90 578] /P 3 0 R /F 4 /AS /Online /AP << /N << /Online 30 0 R /Off 30 0 R >> >> >>\nendobj\n"
    . "18 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [108 560 126 578] /P 3 0 R /F 4 /AS /Off /AP << /N << /Pickup 30 0 R /Off 30 0 R >> >> >>\nendobj\n"
    . "20 0 obj\n<< /FT /Btn /T (review.consent) /DV /Yes /Kids [22 0 R] >>\nendobj\n"
    . "22 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 520 90 538] /P 3 0 R /F 4 /AS /Yes /AP << /N << /Yes 30 0 R /Off 30 0 R >> >> >>\nendobj\n"
    . "30 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "%%EOF";

$fields = (new PdfAcroFormExtractor())->extractFields($pdf);
$stateRows = [];
foreach ($fields as $field) {
    $state = is_array($field['value_state'] ?? null) ? $field['value_state'] : [];
    $selected = [];
    foreach ($state['selected_options'] ?? [] as $option) {
        if (is_array($option)) {
            $selected[] = (string) ($option['label'] ?? $option['export'] ?? '');
        }
    }

    $stateRows[] = [
        'name' => $field['name'],
        'type' => $field['field_type_label'],
        'display' => $state['display_value'] ?? null,
        'effective' => $state['effective_current_state'] ?? $state['current'] ?? null,
        'default' => $state['default'] ?? $state['default_state'] ?? null,
        'changed' => $state['changed_from_default'] ?? null,
        'selected_options' => array_values(array_filter($selected, static fn (string $value): bool => $value !== '')),
        'checked_widgets' => $state['checked_widget_count'] ?? null,
        'state_source' => $state['state_source'] ?? $state['current_source'] ?? null,
    ];
}

echo '<!-- markerpdf:pdf-acroform-current-value-state ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-catalog-acroform-current-values',
    'native_boundary' => 'catalog /AcroForm field /V /DV /I /Opt and widget /AS state extraction before WordPress review rendering',
    'field_count' => count($fields),
    'changed_field_count' => count(array_filter($stateRows, static fn (array $row): bool => ($row['changed'] ?? null) === true)),
    'appearance_state_fallbacks' => array_values(array_filter(array_map(
        static fn (array $row): ?string => ($row['state_source'] ?? null) === 'widget_appearance_state' ? (string) $row['name'] : null,
        $stateRows
    ))),
    'widget_default_matches' => array_values(array_filter(array_map(
        static fn (array $row): ?string => ($row['state_source'] ?? null) === 'widget_appearance_state' && ($row['changed'] ?? null) === false ? (string) $row['name'] : null,
        $stateRows
    ))),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($stateRows as $row) {
    $details = [];
    if ($row['display'] !== null) {
        $details[] = 'current ' . $row['display'];
    } elseif ($row['effective'] !== null) {
        $details[] = 'current state ' . (is_array($row['effective']) ? implode(', ', $row['effective']) : (string) $row['effective']);
    }
    if ($row['default'] !== null) {
        $details[] = 'default ' . (is_array($row['default']) ? implode(', ', $row['default']) : (string) $row['default']);
    }
    if ($row['selected_options'] !== []) {
        $details[] = 'selected ' . implode(', ', $row['selected_options']);
    }
    if ($row['checked_widgets'] !== null) {
        $details[] = (string) $row['checked_widgets'] . ' checked widget';
    }

    echo '<li>' . htmlspecialchars($row['name'] . ' (' . $row['type'] . '): ' . implode('; ', $details), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
