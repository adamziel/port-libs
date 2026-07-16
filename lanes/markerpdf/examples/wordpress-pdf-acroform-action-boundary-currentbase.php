<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm action boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (document.review) /V (Native action metadata) /Kids [8 0 R] /AA << /K 20 0 R >> >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 360 664] /P 3 0 R /F 4 /A 22 0 R >>\nendobj\n"
    . "20 0 obj\n<< /S /Launch /Win << /F (review-helper.exe) /P (--dry-run) /O (open) /D (C:\\\\blocked) >> /NewWindow true /Next 21 0 R >>\nendobj\n"
    . "21 0 obj\n<< /S /GoToE /F 30 0 R /D [3 0 R /FitH 612] /T << /R /C /N (embedded-review.pdf) /P 0 >> /NewWindow false >>\nendobj\n"
    . "22 0 obj\n<< /S /GoToE /D (chapter-two) /T 31 0 R >>\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /UF (embedded-review.pdf) /EF << /F 32 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /R /P /N (parent-package.pdf) /T << /R /C /N (nested-child.pdf) >> >>\nendobj\n"
    . "32 0 obj\n<< /Type /EmbeddedFile /Length 24 >>\nstream\nEmbedded payload blocked\nendstream\nendobj\n"
    . "%%EOF";

$fields = (new PdfAcroFormExtractor())->extractFields($pdf);
$field = $fields[0] ?? null;
if (!is_array($field)) {
    throw new RuntimeException('Expected AcroForm field review metadata.');
}

$widget = $field['widgets'][0] ?? null;
if (!is_array($widget)) {
    throw new RuntimeException('Expected AcroForm widget review metadata.');
}

$actions = array_merge($field['actions'] ?? [], $widget['actions'] ?? []);
$actionTypes = array_column($actions, 'action_type');
$launchActions = array_values(array_filter($actions, static fn (array $action): bool => ($action['action_type'] ?? null) === 'Launch'));
$embeddedActions = array_values(array_filter($actions, static fn (array $action): bool => ($action['action_type'] ?? null) === 'GoToE'));
$text = (new PdfTextExtractor())->extractPlainText($pdf);

if ($actionTypes !== ['Launch', 'GoToE', 'GoToE']) {
    throw new RuntimeException('Expected Launch and GoToE AcroForm action review rows.');
}
if (($launchActions[0]['target_platform'] ?? null) !== 'Win' || ($launchActions[0]['target'] ?? null) !== 'review-helper.exe') {
    throw new RuntimeException('Expected platform Launch target review metadata.');
}
if (count($embeddedActions) !== 2 || ($embeddedActions[0]['embedded_target']['relationship_label'] ?? null) !== 'child') {
    throw new RuntimeException('Expected embedded GoTo target review metadata.');
}
if (str_contains($text, 'review-helper.exe') || str_contains($text, 'embedded-review.pdf') || str_contains($text, 'Embedded payload blocked')) {
    throw new RuntimeException('Action targets or embedded payload leaked into visible text.');
}

echo '<!-- markerpdf:pdf-acroform-action-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-platform-embedded-action-boundary',
    'native_boundary' => 'AcroForm Launch platform dictionaries and GoToE embedded-document actions are review metadata only; WordPress import does not execute actions or expose embedded payload text.',
    'field_name' => $field['name'] ?? null,
    'field_value' => $field['value'] ?? null,
    'action_types' => $actionTypes,
    'launch_targets' => array_values(array_filter(array_map(
        static fn (array $action): ?string => ($action['action_type'] ?? null) === 'Launch' ? ($action['target'] ?? null) : null,
        $actions
    ))),
    'launch_platforms' => array_values(array_filter(array_map(
        static fn (array $action): ?string => ($action['action_type'] ?? null) === 'Launch' ? ($action['target_platform'] ?? null) : null,
        $actions
    ))),
    'embedded_targets' => array_values(array_filter(array_map(
        static fn (array $action): ?string => ($action['action_type'] ?? null) === 'GoToE' ? ($action['embedded_target']['name'] ?? null) : null,
        $actions
    ))),
    'embedded_target_relationships' => array_values(array_filter(array_map(
        static fn (array $action): ?string => ($action['action_type'] ?? null) === 'GoToE' ? ($action['embedded_target']['relationship_label'] ?? null) : null,
        $actions
    ))),
    'executes_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'embedded_payload_text_exposed' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars(sprintf(
    '%s keeps value "%s" while %s actions stay review-only.',
    (string) ($field['name'] ?? 'document.review'),
    (string) ($field['value'] ?? ''),
    implode(' / ', $actionTypes)
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars(sprintf(
    'Launch target %s and embedded target %s are not executed or imported as paragraph text.',
    (string) ($launchActions[0]['target'] ?? ''),
    (string) ($embeddedActions[0]['embedded_target']['name'] ?? '')
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
