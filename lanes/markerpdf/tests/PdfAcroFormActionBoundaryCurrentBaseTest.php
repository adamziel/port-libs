<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[$field['name']] = $field;
    }

    return $indexed;
};

$acroFormActionBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm action boundary body) Tj ET';

    return "%PDF-1.7\n"
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
};

return [
    'keeps platform launch and embedded goto AcroForm actions review only at current base' => static function (TestRunner $t) use ($acroFormActionBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormActionBoundaryPdf();
        $fields = $fieldsByName((new PdfAcroFormExtractor())->extractFields($pdf));
        $field = $fields['document.review'];
        $widget = $field['widgets'][0];
        $text = (new PdfTextExtractor())->extractPlainText($pdf);

        $fieldActions = $field['actions'];
        $widgetActions = $widget['actions'];

        $t->same('Native action metadata', $field['value']);
        $t->same(['Launch', 'GoToE'], array_column($fieldActions, 'action_type'));
        $t->same(['keystroke', 'keystroke'], array_column($fieldActions, 'trigger_label'));
        $t->same(['launch-action-review', 'embedded-document-review'], array_column($fieldActions, 'safety'));
        $t->same([false, true], array_map(
            static fn (array $action): bool => (bool) ($action['chained'] ?? false),
            $fieldActions
        ));

        $launch = $fieldActions[0];
        $t->same(20, $launch['action_object']);
        $t->same('review-helper.exe', $launch['target']);
        $t->same('Win', $launch['target_platform']);
        $t->same('open', $launch['operation']);
        $t->same('--dry-run', $launch['parameters']);
        $t->same('C:\\blocked', $launch['default_directory']);
        $t->true($launch['new_window']);
        $t->same(false, $launch['executes_action']);
        $t->same(false, $launch['executes_javascript']);

        $embedded = $fieldActions[1];
        $t->same(21, $embedded['action_object']);
        $t->same('embedded-review.pdf', $embedded['target']);
        $t->same(null, $embedded['target_scheme']);
        $t->same([['object' => 3], 'FitH', '612'], $embedded['destination']);
        $t->same(false, $embedded['new_window']);
        $t->same([
            'relationship' => 'C',
            'relationship_label' => 'child',
            'name' => 'embedded-review.pdf',
            'page' => 0,
            'annotation' => null,
            'nested_target' => null,
        ], $embedded['embedded_target']);
        $t->same(false, $embedded['executes_action']);
        $t->same(false, $embedded['executes_javascript']);

        $t->same(['GoToE'], array_column($widgetActions, 'action_type'));
        $widgetEmbedded = $widgetActions[0];
        $t->same('chapter-two', $widgetEmbedded['destination']);
        $t->same([
            'relationship' => 'P',
            'relationship_label' => 'parent',
            'name' => 'parent-package.pdf',
            'page' => null,
            'annotation' => null,
            'nested_target' => [
                'relationship' => 'C',
                'relationship_label' => 'child',
                'name' => 'nested-child.pdf',
                'page' => null,
                'annotation' => null,
                'nested_target' => null,
            ],
        ], $widgetEmbedded['embedded_target']);
        $t->same(false, $widgetEmbedded['executes_action']);
        $t->same(false, $widgetEmbedded['executes_javascript']);

        $t->same('acroform_action_chain_review_boundary', $field['action_review']['source']);
        $t->same(2, $field['action_review']['action_count']);
        $t->same(1, $field['action_review']['chained_action_count']);
        $t->same(0, $field['action_review']['cycle_edges_blocked']);
        $t->same(false, $field['action_review']['executes_action']);
        $t->same(false, $field['action_review']['executes_javascript']);

        $t->same('Visible AcroForm action boundary body', $text);
        $t->true(!str_contains($text, 'review-helper.exe'));
        $t->true(!str_contains($text, 'embedded-review.pdf'));
        $t->true(!str_contains($text, 'Embedded payload blocked'));
    },
];
