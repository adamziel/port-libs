<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdfForOpenAction = static function (string $openAction, string $extraObjects = ''): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OpenAction {$openAction} /Names << /Dests 8 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Names [(Welcome Page) [3 0 R /Fit] (Review Page) [4 0 R /FitH 640]] >>\nendobj\n"
        . $extraObjects
        . "%%EOF";
};

$documents = [
    'welcome-uri.pdf' => $pdfForOpenAction('<< /S /URI /URI (https://example.com/import-checklist) >>'),
    'launch-helper.pdf' => $pdfForOpenAction('9 0 R', "9 0 obj\n<< /S /Launch /F (installer.exe) /Win << /F (setup.exe) /O (open) /P (/silent) >> /NewWindow true >>\nendobj\n"),
    'remote-appendix.pdf' => $pdfForOpenAction('<< /S /GoToR /F << /UF <FEFF00650078007400650072006E0061006C002E007000640066> >> /D [3 /Fit] /NewWindow false >>'),
];

$extractor = new PdfOutlineExtractor();
$rows = [];
foreach ($documents as $document => $pdfBytes) {
    foreach ($extractor->getOpenActionReviewActions($pdfBytes) as $action) {
        $action['document'] = $document;
        $rows[] = $action;
    }
}

echo '<!-- markerpdf-pdf-openaction-safety-review ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-openaction-review-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'open_action_count' => count($rows),
    'action_types' => array_column($rows, 'action_type'),
    'all_review_only' => array_reduce($rows, static fn (bool $carry, array $row): bool => $carry && $row['executes_on_import'] === false, true),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($rows as $row) {
    $attrs = [
        'data-marker-document' => $row['document'],
        'data-marker-openaction-type' => $row['action_type'],
        'data-marker-openaction-safety' => $row['safety'],
        'data-marker-executes-on-import' => $row['executes_on_import'] ? 'true' : 'false',
    ];
    foreach (['uri', 'file', 'destination', 'operation'] as $key) {
        if (is_string($row[$key]) && $row[$key] !== '') {
            $attrs['data-marker-openaction-' . str_replace('_', '-', $key)] = $row[$key];
        }
    }
    if (is_int($row['page'])) {
        $attrs['data-marker-openaction-page'] = (string) $row['page'];
    }

    $attrText = '';
    foreach ($attrs as $name => $value) {
        $attrText .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    $label = $row['document'] . ': ' . $row['action_type'] . ' ' . $row['safety'];
    echo '<li' . $attrText . '>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
