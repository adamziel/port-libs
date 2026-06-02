<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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
    . "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 228 32] /Length " . strlen($selectedAppearance) . " >>\nstream\n{$selectedAppearance}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "70 0 obj\n<< /R 90 /BC [0 0.25 1] /BG [1 0.92 0.2] /CA (Approve import) /RC (Approve rollover) /AC (Approve pressed) /TP 5 /I 80 0 R /RI 81 0 R /IX 82 0 R /IF << /SW /A /S /P /A [0.25 0.75] /FB true >> >>\nendobj\n"
    . "72 0 obj\n<< /SW /N /S /A /A [0.5 0.5] /FB false >>\nendobj\n"
    . "80 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "81 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "82 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "%%EOF";

$fields = [];
foreach ((new PdfAcroFormExtractor())->extractFields($pdf) as $field) {
    $fields[(string) ($field['name'] ?? '')] = $field;
}

$approve = $fields['article.approve'] ?? null;
$detached = $fields['article.detached_caption'] ?? null;
if (!is_array($approve) || !is_array($detached)) {
    throw new RuntimeException('Expected AcroForm widget characteristic field rows.');
}

$approveWidget = $approve['widgets'][0] ?? null;
$detachedWidget = $detached['widgets'][0] ?? null;
$approveMk = is_array($approveWidget['appearance_characteristics'] ?? null) ? $approveWidget['appearance_characteristics'] : null;
$detachedMk = is_array($detachedWidget['appearance_characteristics'] ?? null) ? $detachedWidget['appearance_characteristics'] : null;
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

if (!is_array($approveMk) || !is_array($detachedMk)) {
    throw new RuntimeException('Expected widget /MK appearance-characteristic review metadata.');
}
if (str_contains($visibleText, 'Approve import') || str_contains($visibleText, 'Detached icon-only caption')) {
    throw new RuntimeException('Widget /MK captions leaked into visible WordPress text.');
}
if (($approveMk['renders_appearance'] ?? true) !== false || ($approveMk['executes_action'] ?? true) !== false) {
    throw new RuntimeException('Widget appearance characteristics must stay review-only.');
}

echo '<!-- markerpdf:pdf-acroform-widget-characteristics-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-widget-characteristics-review',
    'native_boundary' => 'AcroForm widget /MK captions, colors, icon objects, icon-fit, highlight mode, and selected /AP state are review metadata only during WordPress import',
    'visible_text' => $visibleText,
    'field_name' => $approve['name'] ?? null,
    'field_value' => $approve['value'] ?? null,
    'page_widget_highlight_mode' => $approveWidget['highlight_mode_label'] ?? null,
    'page_widget_appearance_state' => $approveWidget['appearance_state'] ?? null,
    'normal_caption' => $approveMk['normal_caption'] ?? null,
    'rollover_caption' => $approveMk['rollover_caption'] ?? null,
    'alternate_caption' => $approveMk['alternate_caption'] ?? null,
    'text_position_label' => $approveMk['text_position_label'] ?? null,
    'border_color' => $approveMk['border_color']['hex'] ?? null,
    'background_color' => $approveMk['background_color']['hex'] ?? null,
    'icon_objects' => [
        $approveMk['icon_object'] ?? null,
        $approveMk['rollover_icon_object'] ?? null,
        $approveMk['alternate_icon_object'] ?? null,
    ],
    'icon_fit' => $approveMk['icon_fit'] ?? null,
    'detached_widget_reviewed' => ($detachedWidget['referenced_from_page_annots'] ?? true) === false,
    'detached_caption' => $detachedMk['normal_caption'] ?? null,
    'caption_text_used_for_import' => false,
    'icon_payload_text_exposed' => false,
    'renders_appearance' => false,
    'executes_form_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars(sprintf(
    '%s imports field value "%s"; widget caption "%s" is review metadata.',
    (string) ($approve['name'] ?? 'field'),
    (string) ($approve['value'] ?? ''),
    (string) ($approveMk['normal_caption'] ?? '')
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars(sprintf(
    'Widget colors %s/%s and icon fit %s/%s are preserved for audit only.',
    (string) ($approveMk['border_color']['hex'] ?? 'none'),
    (string) ($approveMk['background_color']['hex'] ?? 'none'),
    (string) ($approveMk['icon_fit']['scale_when'] ?? 'none'),
    (string) ($approveMk['icon_fit']['scale_type'] ?? 'none')
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo '<li>' . htmlspecialchars(sprintf(
    'Detached widget caption "%s" remains hidden from visible import text.',
    (string) ($detachedMk['normal_caption'] ?? '')
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
