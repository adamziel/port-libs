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

$choiceRichTextSubmitResetPdf = static function (): array {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm review body) Tj ET';
    $richText = '<body xmlns="http://www.w3.org/1999/xhtml"><p><b>Styled summary</b> &amp; review script blocked</p></body>';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 11 0 R 14 0 R 17 0 R 20 0 R 23 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 9 0 R 12 0 R 15 0 R 18 0 R 21 0 R] >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.summary) /Ff 33554432 /V (Plain summary) /DV (Draft summary) /RV ({$richText}) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 360 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "9 0 obj\n<< /FT /Ch /T (article.topics) /Ff 2097152 /V [(plugin) (themes)] /DV [(blocks)] /I [1 0] /Opt [[(themes) (Themes)] [(plugin) (Plugins)] [(blocks) (Blocks)]] /Kids [11 0 R] >>\nendobj\n"
        . "11 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 360 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "12 0 obj\n<< /FT /Tx /T (internal.secret) /Ff 4 /V (No export payload) /Kids [14 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 560 360 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "15 0 obj\n<< /FT /Tx /T (article.empty) /Kids [17 0 R] >>\nendobj\n"
        . "17 0 obj\n<< /Subtype /Widget /Parent 15 0 R /Rect [72 520 360 544] /P 3 0 R /F 4 >>\nendobj\n"
        . "18 0 obj\n<< /FT /Btn /T (actions.submit) /Ff 65536 /Kids [20 0 R] >>\nendobj\n"
        . "20 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [72 480 180 504] /P 3 0 R /F 4 /A << /S /SubmitForm /F 30 0 R /Flags 6 >> >>\nendobj\n"
        . "21 0 obj\n<< /FT /Btn /T (actions.reset) /Ff 65536 /Kids [23 0 R] /AA << /U << /S /ResetForm /Fields [(article.summary) 9 0 R 15 0 R] >> >> >>\nendobj\n"
        . "23 0 obj\n<< /Subtype /Widget /Parent 21 0 R /Rect [200 480 310 504] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (https://example.test/form-submit) >>\nendobj\n"
        . "%%EOF";

    return [$pdf, $richText, $pageText];
};

return [
    'reviews choice rich text submit and reset fields without executing form actions' => static function (TestRunner $t) use ($choiceRichTextSubmitResetPdf, $fieldsByName): void {
        [$pdf, $richText] = $choiceRichTextSubmitResetPdf();

        $extractor = new PdfAcroFormExtractor();
        $fields = $fieldsByName($extractor->extractFields($pdf));
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

        $summary = $fields['article.summary'];
        $richReview = $summary['rich_text_review'];
        $t->same('text', $summary['field_type_label']);
        $t->same(['rich_text'], $summary['flag_names']);
        $t->same('Plain summary', $summary['value']);
        $t->same('Draft summary', $summary['default_value']);
        $t->same('acroform_rich_text_value_review_boundary', $richReview['source']);
        $t->true($richReview['rich_text_flag']);
        $t->true($richReview['has_rich_text_value']);
        $t->same('Plain summary', $richReview['plain_value']);
        $t->same(hash('sha256', $richText), $richReview['rich_text_sha256']);
        $t->same('Styled summary & review script blocked', $richReview['rich_text_plain_preview']);
        $t->same(false, $richReview['rich_text_used_for_import']);
        $t->same(false, $richReview['rich_text_used_for_submit']);
        $t->same(false, $richReview['payload_text_exposed']);
        $t->same(false, $richReview['executes_rich_text_javascript']);

        $topics = $fields['article.topics'];
        $topicState = $topics['value_state'];
        $t->same(['plugin', 'themes'], $topicState['choice_values']);
        $t->same([1, 0], $topicState['selected_indices']);
        $t->same([
            ['index' => 1, 'export' => 'plugin', 'label' => 'Plugins'],
            ['index' => 0, 'export' => 'themes', 'label' => 'Themes'],
        ], $topicState['selected_options']);

        $submit = $fields['actions.submit']['widgets'][0]['actions'][0];
        $submitReview = $submit['field_value_review'];
        $submitRows = [];
        foreach ($submitReview['field_rows'] as $row) {
            $submitRows[$row['field_name']] = $row;
        }

        $t->same('SubmitForm', $submit['action_type']);
        $t->same('https://example.test/form-submit', $submit['target']);
        $t->same('html', $submit['submit_format']);
        $t->same('acroform_choice_richtext_submit_reset_review_boundary', $submitReview['source']);
        $t->same(6, $submitReview['candidate_field_count']);
        $t->same(3, $submitReview['included_field_count']);
        $t->same(3, $submitReview['excluded_field_count']);
        $t->same(['article.summary', 'article.topics', 'article.empty'], $submitReview['submitted_field_names']);
        $t->same(['internal.secret'], $submitReview['no_export_excluded_field_names']);
        $t->same(['actions.submit', 'actions.reset'], $submitReview['push_button_excluded_field_names']);
        $t->same(['article.topics'], $submitReview['choice_field_names']);
        $t->same(['article.summary'], $submitReview['rich_text_field_names']);
        $t->same('Plain summary', $submitRows['article.summary']['submit_value']);
        $t->same(false, $submitRows['article.summary']['rich_text_included']);
        $t->same(['plugin', 'themes'], $submitRows['article.topics']['submit_value']);
        $t->same(['Plugins', 'Themes'], array_column($submitRows['article.topics']['choice_review']['selected_options'], 'label'));
        $t->same(null, $submitRows['article.empty']['submit_value']);
        $t->same(null, $submitRows['article.empty']['submit_value_source']);
        $t->same(false, $submitRows['internal.secret']['submit_included']);
        $t->same('no_export', $submitRows['internal.secret']['omit_reason']);
        $t->same(false, $submitReview['exports_rich_text_html']);
        $t->same(false, $submitReview['executes_action']);
        $t->same(false, $submitReview['executes_javascript']);

        $reset = $fields['actions.reset']['actions'][0];
        $resetReview = $reset['field_value_review'];
        $resetRows = [];
        foreach ($resetReview['field_rows'] as $row) {
            $resetRows[$row['field_name']] = $row;
        }

        $t->same('ResetForm', $reset['action_type']);
        $t->same('include', $reset['fields_mode']);
        $t->same(3, $resetReview['reset_field_count']);
        $t->same(['article.summary', 'article.topics', 'article.empty'], $resetReview['reset_field_names']);
        $t->same(['article.summary', 'article.topics'], $resetReview['default_value_field_names']);
        $t->same(['article.empty'], $resetReview['cleared_field_names']);
        $t->same('Draft summary', $resetRows['article.summary']['reset_value']);
        $t->same(false, $resetRows['article.summary']['rich_text_restored']);
        $t->same(['blocks'], $resetRows['article.topics']['reset_value']);
        $t->same(['Blocks'], array_column($resetRows['article.topics']['choice_review']['selected_options'], 'label'));
        $t->same(null, $resetRows['article.empty']['reset_value']);
        $t->same('cleared_to_null', $resetRows['article.empty']['reset_value_source']);
        $t->same(false, $resetReview['restores_rich_text_html']);
        $t->same(false, $resetReview['executes_action']);
        $t->same(false, $resetReview['executes_javascript']);

        $t->contains('Visible AcroForm review body', $visibleText);
        $t->same(false, str_contains($visibleText, 'Styled summary'));
        $t->same(false, str_contains($visibleText, 'form-submit'));
        $t->same(false, str_contains($visibleText, 'No export payload'));
    },
];
