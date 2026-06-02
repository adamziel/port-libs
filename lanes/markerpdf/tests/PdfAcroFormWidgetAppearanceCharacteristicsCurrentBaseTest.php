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

$widgetAppearanceCharacteristicsPdf = static function (): array {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible widget characteristic review body) Tj ET';
    $selectedAppearance = 'BT /FApp 9 Tf 0 0 Td (Approved appearance review) Tj ET';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R] >>\nendobj\n"
        . "6 0 obj\n<< /FT /Btn /T (article.approve) /Ff 65536 /V (Approved value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 672] /P 3 0 R /F 4 /H /P /AS /Approved /MK 70 0 R /AP << /N << /Approved 30 0 R /Off 31 0 R >> >> /A 20 0 R >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (article.detached_caption) /V (Detached field value) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 580 300 612] /F 36 /H /N /MK << /BG [0.5] /CA (Detached icon-only caption) /TP 6 /I 80 0 R /IF 72 0 R >> >>\nendobj\n"
        . "20 0 obj\n<< /S /URI /URI (https://example.test/approve) >>\nendobj\n"
        . "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 228 32] /Resources << /Font << /FApp 32 0 R >> >> /Length " . strlen($selectedAppearance) . " >>\nstream\n{$selectedAppearance}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "32 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "70 0 obj\n<< /R 90 /BC [0 0.25 1] /BG [1 0.92 0.2] /CA (Approve import) /RC (Approve rollover) /AC (Approve pressed) /TP 5 /I 80 0 R /RI 81 0 R /IX 82 0 R /IF << /SW /A /S /P /A [0.25 0.75] /FB true >> >>\nendobj\n"
        . "72 0 obj\n<< /SW /N /S /A /A [0.5 0.5] /FB false >>\nendobj\n"
        . "80 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "81 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "82 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $selectedAppearance];
};

return [
    'reviews AcroForm widget appearance characteristics without rendering captions icons or actions' => static function (TestRunner $t) use ($widgetAppearanceCharacteristicsPdf, $fieldsByName): void {
        [$pdf, $selectedAppearance] = $widgetAppearanceCharacteristicsPdf();

        $fields = $fieldsByName((new PdfAcroFormExtractor())->extractFields($pdf));
        $approve = $fields['article.approve'];
        $approveWidget = $approve['widgets'][0];
        $approveMk = $approveWidget['appearance_characteristics'];
        $detached = $fields['article.detached_caption'];
        $detachedWidget = $detached['widgets'][0];
        $detachedMk = $detachedWidget['appearance_characteristics'];
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('Approved value', $approve['value']);
        $t->true($approveWidget['referenced_from_page_annots']);
        $t->same(0, $approveWidget['page_index']);
        $t->same(3, $approveWidget['page_object']);
        $t->same(0, $approveWidget['page_annotation_index']);
        $t->same('P', $approveWidget['highlight_mode']);
        $t->same('push', $approveWidget['highlight_mode_label']);
        $t->same('Approved', $approveWidget['appearance_state']);
        $t->same(['Approved', 'Off'], $approveWidget['appearance_states']);
        $t->same(30, $approveWidget['normal_appearance']['selected_appearance']['object']);
        $t->same(hash('sha256', $selectedAppearance), $approveWidget['normal_appearance']['selected_appearance']['decoded_sha256']);
        $t->same('URI', $approveWidget['actions'][0]['action_type']);
        $t->same('https://example.test/approve', $approveWidget['actions'][0]['target']);
        $t->same(false, $approveWidget['actions'][0]['executes_action']);

        $t->same('acroform_widget_mk_appearance_characteristics', $approveMk['source']);
        $t->same(70, $approveMk['dictionary_object']);
        $t->same(90, $approveMk['rotation']);
        $t->same('Approve import', $approveMk['normal_caption']);
        $t->same('Approve rollover', $approveMk['rollover_caption']);
        $t->same('Approve pressed', $approveMk['alternate_caption']);
        $t->same(5, $approveMk['text_position']);
        $t->same('caption_overlaid_icon', $approveMk['text_position_label']);
        $t->same('DeviceRGB', $approveMk['border_color']['space']);
        $t->same([0.0, 0.25, 1.0], $approveMk['border_color']['components']);
        $t->same('#0040ff', $approveMk['border_color']['hex']);
        $t->same('DeviceRGB', $approveMk['background_color']['space']);
        $t->same([1.0, 0.92, 0.2], $approveMk['background_color']['components']);
        $t->same('#ffeb33', $approveMk['background_color']['hex']);
        $t->same(80, $approveMk['icon_object']);
        $t->same(81, $approveMk['rollover_icon_object']);
        $t->same(82, $approveMk['alternate_icon_object']);
        $t->same('A', $approveMk['icon_fit']['scale_when']);
        $t->same('P', $approveMk['icon_fit']['scale_type']);
        $t->same([0.25, 0.75], $approveMk['icon_fit']['position']);
        $t->same(true, $approveMk['icon_fit']['fit_bounds']);
        $t->same(false, $approveMk['icon_fit']['renders_icon']);
        $t->same(false, $approveMk['appearance_value_used_for_import']);
        $t->same(false, $approveMk['caption_text_used_for_import']);
        $t->same(false, $approveMk['icon_payload_text_exposed']);
        $t->same(false, $approveMk['renders_appearance']);
        $t->same(false, $approveMk['executes_action']);

        $t->same('Detached field value', $detached['value']);
        $t->same(false, $detachedWidget['referenced_from_page_annots']);
        $t->same(null, $detachedWidget['page_index']);
        $t->same(null, $detachedWidget['page_object']);
        $t->same('no_view', $detachedWidget['annotation_visibility']);
        $t->true($detachedWidget['hidden']);
        $t->same(false, $detachedWidget['visible']);
        $t->true($detachedWidget['printable']);
        $t->true($detachedWidget['no_view']);
        $t->same('N', $detachedWidget['highlight_mode']);
        $t->same('none', $detachedWidget['highlight_mode_label']);

        $t->same('acroform_widget_mk_appearance_characteristics', $detachedMk['source']);
        $t->same(false, array_key_exists('dictionary_object', $detachedMk));
        $t->same('Detached icon-only caption', $detachedMk['normal_caption']);
        $t->same(6, $detachedMk['text_position']);
        $t->same('icon_only', $detachedMk['text_position_label']);
        $t->same('DeviceGray', $detachedMk['background_color']['space']);
        $t->same([0.5], $detachedMk['background_color']['components']);
        $t->same('#808080', $detachedMk['background_color']['hex']);
        $t->same(80, $detachedMk['icon_object']);
        $t->same('N', $detachedMk['icon_fit']['scale_when']);
        $t->same('A', $detachedMk['icon_fit']['scale_type']);
        $t->same([0.5, 0.5], $detachedMk['icon_fit']['position']);
        $t->same(false, $detachedMk['icon_fit']['fit_bounds']);
        $t->same(false, $detachedMk['icon_fit']['renders_icon']);
        $t->same(false, $detachedMk['caption_text_used_for_import']);
        $t->same(false, $detachedMk['renders_appearance']);

        $t->same("Visible widget characteristic review body\nApproved appearance review", $visibleText);
        $t->same(false, str_contains($visibleText, 'Approve import'));
        $t->same(false, str_contains($visibleText, 'Approve rollover'));
        $t->same(false, str_contains($visibleText, 'Detached icon-only caption'));
        $t->same(false, str_contains($visibleText, 'https://example.test/approve'));
    },
];
