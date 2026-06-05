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

$pageWidgetFieldBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm page widget boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 14 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) /DR << /Font << /Helv 40 0 R >> >> >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (listed.email) /V (listed@example.test) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Ch /T (omitted.category) /V (page) /Opt [(post) (page)] /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /FT /Tx /T (inline.note) /V (inline page widget value) /Rect [72 560 320 584] /P 3 0 R /F 4 /DA (/Helv 8 Tf 0 0 1 rg) >>\nendobj\n"
        . "20 0 obj\n<< /FT /Tx /T (detached.secret) /V (detached widget value must not surface) /Kids [22 0 R] >>\nendobj\n"
        . "22 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 520 320 544] /F 4 >>\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$tokenAwareAcroFormFieldsBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible token-aware AcroForm field body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /DA (/Fields [99 0 R] should stay a literal default appearance comment) /Fie#6Cds [6 0 R] /NeedAppearances true >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.token) /TU (Tooltip text with /V (Decoy token title) and /Kids [99 0 R]) /V (Real token title) /Kids [8 0 R] /AA << /K << /S /Named /N /Print /Fields [99 0 R] >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "99 0 obj\n<< /FT /Tx /T (decoy.literal) /V (Decoy token title) >>\nendobj\n"
        . "%%EOF";
};

$acroFormGenerationMismatchBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm generation mismatch body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 1 R 12 1 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 1 R] /NeedAppearances true >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (stale.generation.listed) /V (stale listed value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (stale.page.widget.parent) /V (stale parent value) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 1 R /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

$acroFormGenerationExactBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm generation exact body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 1 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 1 R] /NeedAppearances true >>\nendobj\n"
        . "6 1 obj\n<< /FT /Tx /T (current.generation.email) /V (current-generation@example.test) /Kids [8 1 R] >>\nendobj\n"
        . "8 1 obj\n<< /Subtype /Widget /Parent 6 1 R /Rect [72 640 300 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (stale.generation.email) /V (stale-generation@example.test) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

$indirectAcroFormFieldArraysBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect array boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 15 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields 20 0 R /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.indirect) /V (Indirect field array title) /Kids 21 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "12 0 obj\n<< /FT /Tx /T (metadata.hidden) /Ff 4 /V (Metadata-only indirect value) >>\nendobj\n"
        . "15 0 obj\n<< /Subtype /Widget /Parent 42 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n[6 0 R 12 0 R 40 0 R]\nendobj\n"
        . "21 0 obj\n[8 0 R]\nendobj\n"
        . "40 0 obj\n<< /FT /Tx /T (profile) /V (Inherited profile value) /Kids 41 0 R >>\nendobj\n"
        . "41 0 obj\n[42 0 R]\nendobj\n"
        . "42 0 obj\n<< /T (name) /Kids 43 0 R >>\nendobj\n"
        . "43 0 obj\n[15 0 R]\nendobj\n"
        . "99 0 obj\n[101 0 R]\nendobj\n"
        . "101 0 obj\n<< /FT /Tx /T (detached.indirect.decoy) /V (Detached indirect decoy) >>\nendobj\n"
        . "%%EOF";
};

$indirectAcroFormWidgetOperandBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect widget operand boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R] /NeedAppearances true >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (geometry.visible) /V (visible geometry value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [30 0 R 31 0 R 32 0 R 33 0 R] /F 34 0 R /P 3 0 R >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (geometry.hidden) /V (hidden geometry value) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [40 0 R 41 0 R 42 0 R 43 0 R] /F 44 0 R /P 3 0 R >>\nendobj\n"
        . "14 0 obj\n<< /FT /Tx /T (geometry.no_view) /V (no-view geometry value) /Kids [16 0 R] >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [50 0 R 51 0 R 52 0 R 53 0 R] /F 54 0 R /P 3 0 R >>\nendobj\n"
        . "30 0 obj\n300\nendobj\n"
        . "31 0 obj\n664\nendobj\n"
        . "32 0 obj\n72\nendobj\n"
        . "33 0 obj\n640\nendobj\n"
        . "34 0 obj\n4\nendobj\n"
        . "40 0 obj\n260\nendobj\n"
        . "41 0 obj\n624\nendobj\n"
        . "42 0 obj\n72\nendobj\n"
        . "43 0 obj\n600\nendobj\n"
        . "44 0 obj\n2\nendobj\n"
        . "50 0 obj\n320\nendobj\n"
        . "51 0 obj\n584\nendobj\n"
        . "52 0 obj\n72\nendobj\n"
        . "53 0 obj\n560\nendobj\n"
        . "54 0 obj\n32\nendobj\n"
        . "%%EOF";
};

$acroFormArrayTokenBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm array token boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R (130 0 R) [132 0 R] << /Nested 134 0 R >> % 136 0 R stays a comment\n] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R (99 0 R) [101 0 R] << /Nested 102 0 R >> % 103 0 R stays a comment\n10 0 R] /NeedAppearances true >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.array) /V (Array token title) /Kids [8 0 R (198 0 R) [199 0 R] << /Nested 200 0 R >> % 201 0 R stays a comment\n] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (article.keep) /V (Kept top-level field) >>\nendobj\n"
        . "99 0 obj\n<< /FT /Tx /T (decoy.fields.literal) /V (Fields literal decoy) >>\nendobj\n"
        . "101 0 obj\n<< /FT /Tx /T (decoy.fields.nested_array) /V (Fields nested array decoy) >>\nendobj\n"
        . "102 0 obj\n<< /FT /Tx /T (decoy.fields.nested_dict) /V (Fields nested dictionary decoy) >>\nendobj\n"
        . "103 0 obj\n<< /FT /Tx /T (decoy.fields.comment) /V (Fields comment decoy) >>\nendobj\n"
        . "130 0 obj\n<< /Subtype /Widget /FT /Tx /T (decoy.annots.literal) /V (Annots literal decoy) /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "132 0 obj\n<< /Subtype /Widget /FT /Tx /T (decoy.annots.nested_array) /V (Annots nested array decoy) /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "134 0 obj\n<< /Subtype /Widget /FT /Tx /T (decoy.annots.nested_dict) /V (Annots nested dictionary decoy) /Rect [72 520 320 544] /P 3 0 R /F 4 >>\nendobj\n"
        . "136 0 obj\n<< /Subtype /Widget /FT /Tx /T (decoy.annots.comment) /V (Annots comment decoy) /Rect [72 480 320 504] /P 3 0 R /F 4 >>\nendobj\n"
        . "198 0 obj\n<< /FT /Tx /T (decoy.kids.literal) /V (Kids literal decoy) >>\nendobj\n"
        . "199 0 obj\n<< /FT /Tx /T (decoy.kids.nested_array) /V (Kids nested array decoy) >>\nendobj\n"
        . "200 0 obj\n<< /FT /Tx /T (decoy.kids.nested_dict) /V (Kids nested dictionary decoy) >>\nendobj\n"
        . "201 0 obj\n<< /FT /Tx /T (decoy.kids.comment) /V (Kids comment decoy) >>\nendobj\n"
        . "%%EOF";
};

$acroFormAlternateMappingNameBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm alternate mapping name body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (profile) /TU (Parent profile label must stay ancestor review) /TM (profile-parent-map) /V (Inherited profile value) /DV (Inherited draft value) /MaxLen 64 /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /T (email) /TU (Public email label) /TM (profile.email.export) /V (editor@example.test) /DV (draft@example.test) /MaxLen 12 /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n<< /FT /Tx /T (detached.tu.decoy) /TU (Detached tooltip must not surface) /TM (detached.map) /V (Detached alternate mapping decoy) >>\nendobj\n"
        . "%%EOF";
};

return [
    'repairs AcroForm field discovery from page owned widget annotations only' => static function (TestRunner $t) use ($pageWidgetFieldBoundaryPdf, $fieldsByName): void {
        $pdf = $pageWidgetFieldBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(['listed.email', 'omitted.category', 'inline.note'], array_keys($fields));
        $t->same(3, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $listed = $fields['listed.email'];
        $t->same(6, $listed['object']);
        $t->same('listed@example.test', $listed['value']);
        $t->same([8], array_column($listed['widgets'], 'object'));
        $t->same([0], array_column($listed['widgets'], 'page_index'));
        $t->same([true], array_column($listed['widgets'], 'referenced_from_page_annots'));
        $t->same([0], array_column($listed['widgets'], 'page_annotation_index'));

        $omitted = $fields['omitted.category'];
        $t->same(10, $omitted['object']);
        $t->same('choice', $omitted['field_type_label']);
        $t->same('page', $omitted['value']);
        $t->same([['export' => 'post', 'label' => 'post'], ['export' => 'page', 'label' => 'page']], $omitted['options']);
        $t->same([12], array_column($omitted['widgets'], 'object'));
        $t->same([1], array_column($omitted['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($omitted['widgets'], 'referenced_from_page_annots'));
        $t->same('field_terminal', $omitted['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(false, $omitted['field_hierarchy']['executes_form_actions']);
        $t->same(false, $omitted['field_hierarchy']['executes_javascript']);

        $inline = $fields['inline.note'];
        $t->same(14, $inline['object']);
        $t->same('text', $inline['field_type_label']);
        $t->same('inline page widget value', $inline['value']);
        $t->same([14], array_column($inline['widgets'], 'object'));
        $t->same([2], array_column($inline['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($inline['widgets'], 'referenced_from_page_annots'));
        $t->same('field_terminal', $inline['value_state']['hierarchy_boundary']['current_value_source']);

        $t->true(!isset($fields['detached.secret']));
        $t->true(str_contains($visibleText, 'Visible AcroForm page widget boundary body'));
        $t->true(!str_contains($visibleText, 'detached widget value must not surface'));
        $t->true(!str_contains($visibleText, 'inline page widget value'));
    },
    'uses token aware AcroForm field keys before WordPress review metadata' => static function (TestRunner $t) use ($tokenAwareAcroFormFieldsBoundaryPdf, $fieldsByName): void {
        $pdf = $tokenAwareAcroFormFieldsBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(['article.token'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['article.token'];
        $t->same(6, $field['object']);
        $t->same('text', $field['field_type_label']);
        $t->same('Real token title', $field['value']);
        $t->same('field_terminal', $field['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));
        $t->same(['FT', 'V'], $field['field_hierarchy']['local_attributes']);
        $t->same(['DA'], $field['field_hierarchy']['inherited_attributes']);
        $t->same('Named', $field['actions'][0]['action_type']);
        $t->same('K', $field['actions'][0]['trigger']);

        $t->true(!isset($fields['decoy.literal']));
        $t->true(str_contains($visibleText, 'Visible token-aware AcroForm field body'));
        $t->true(!str_contains($visibleText, 'Decoy token title'));
        $t->true(!str_contains($visibleText, 'Real token title'));
    },
    'rejects generation mismatched AcroForm field and page widget references' => static function (
        TestRunner $t
    ) use ($acroFormGenerationMismatchBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormGenerationMismatchBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same([], array_keys($fields));
        $t->same(0, count($form['fields']));
        $t->same(true, $form['need_appearances']);
        $t->true(str_contains($visibleText, 'Visible AcroForm generation mismatch body'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale.generation.listed'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale.page.widget.parent'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale listed value'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale parent value'));
        $t->true(!str_contains($visibleText, 'stale listed value'));
        $t->true(!str_contains($visibleText, 'stale parent value'));
    },
    'keeps exact nonzero generation AcroForm fields before stale same object decoys' => static function (
        TestRunner $t
    ) use ($acroFormGenerationExactBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormGenerationExactBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['current.generation.email'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);
        $current = $fields['current.generation.email'];
        $t->same(6, $current['object']);
        $t->same('current-generation@example.test', $current['value']);
        $t->same([8], array_column($current['widgets'], 'object'));
        $t->same([0], array_column($current['widgets'], 'page_index'));
        $t->same([true], array_column($current['widgets'], 'referenced_from_page_annots'));
        $t->same([0], array_column($current['widgets'], 'page_annotation_index'));
        $t->same('field_terminal', $current['value_state']['hierarchy_boundary']['current_value_source']);
        $t->true(str_contains($visibleText, 'Visible AcroForm generation exact body'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale.generation.email'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-generation@example.test'));
        $t->true(!str_contains($visibleText, 'current-generation@example.test'));
        $t->true(!str_contains($visibleText, 'stale-generation@example.test'));
    },
    'resolves indirect AcroForm Fields and Kids arrays before WordPress field review' => static function (
        TestRunner $t
    ) use ($indirectAcroFormFieldArraysBoundaryPdf, $fieldsByName): void {
        $pdf = $indirectAcroFormFieldArraysBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['article.indirect', 'metadata.hidden', 'profile.name'], array_keys($fields));
        $t->same(3, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $article = $fields['article.indirect'];
        $t->same(6, $article['object']);
        $t->same('Indirect field array title', $article['value']);
        $t->same([8], array_column($article['widgets'], 'object'));
        $t->same([0], array_column($article['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($article['widgets'], 'referenced_from_page_annots'));

        $metadata = $fields['metadata.hidden'];
        $t->same(12, $metadata['object']);
        $t->same(['no_export'], $metadata['flag_names']);
        $t->same('Metadata-only indirect value', $metadata['value']);
        $t->same([], $metadata['widgets']);
        $t->same('field_terminal', $metadata['value_state']['hierarchy_boundary']['current_value_source']);

        $profile = $fields['profile.name'];
        $t->same(42, $profile['object']);
        $t->same('Inherited profile value', $profile['value']);
        $t->same([40, 42], array_column($profile['field_hierarchy']['path'], 'object'));
        $t->same(['profile', 'name'], array_column($profile['field_hierarchy']['path'], 'partial_name'));
        $t->same(['FT', 'V', 'DA'], $profile['field_hierarchy']['inherited_attributes']);
        $t->same([15], array_column($profile['widgets'], 'object'));
        $t->same([1], array_column($profile['widgets'], 'page_annotation_index'));
        $t->same('field_hierarchy_inherited', $profile['value_state']['hierarchy_boundary']['current_value_source']);

        $t->true(is_string($encoded) && !str_contains($encoded, 'detached.indirect.decoy'));
        $t->true(str_contains($visibleText, 'Visible AcroForm indirect array boundary body'));
        $t->true(!str_contains($visibleText, 'Indirect field array title'));
        $t->true(!str_contains($visibleText, 'Metadata-only indirect value'));
        $t->true(!str_contains($visibleText, 'Inherited profile value'));
        $t->true(!str_contains($visibleText, 'Detached indirect decoy'));
    },
    'resolves indirect AcroForm widget Rect and F operands before WordPress field review' => static function (
        TestRunner $t
    ) use ($indirectAcroFormWidgetOperandBoundaryPdf, $fieldsByName): void {
        $pdf = $indirectAcroFormWidgetOperandBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['geometry.visible', 'geometry.hidden', 'geometry.no_view'], array_keys($fields));
        $t->same(3, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $visible = $fields['geometry.visible']['widgets'][0];
        $t->same([72.0, 640.0, 300.0, 664.0], $visible['rect']);
        $t->same(4, $visible['annotation_flags']);
        $t->same(['print'], $visible['annotation_flag_names']);
        $t->same('visible', $visible['annotation_visibility']);
        $t->same(true, $visible['visible']);
        $t->same(true, $visible['printable']);
        $t->same(false, $visible['hidden']);
        $t->same(false, $visible['no_view']);

        $hidden = $fields['geometry.hidden']['widgets'][0];
        $t->same([72.0, 600.0, 260.0, 624.0], $hidden['rect']);
        $t->same(2, $hidden['annotation_flags']);
        $t->same(['hidden'], $hidden['annotation_flag_names']);
        $t->same('hidden', $hidden['annotation_visibility']);
        $t->same(false, $hidden['visible']);
        $t->same(true, $hidden['hidden']);
        $t->same(false, $hidden['printable']);
        $t->same(false, $hidden['no_view']);

        $noView = $fields['geometry.no_view']['widgets'][0];
        $t->same([72.0, 560.0, 320.0, 584.0], $noView['rect']);
        $t->same(32, $noView['annotation_flags']);
        $t->same(['no_view'], $noView['annotation_flag_names']);
        $t->same('no_view', $noView['annotation_visibility']);
        $t->same(false, $noView['visible']);
        $t->same(true, $noView['hidden']);
        $t->same(false, $noView['printable']);
        $t->same(true, $noView['no_view']);

        $t->true(str_contains($visibleText, 'Visible AcroForm indirect widget operand boundary body'));
        $t->true(!str_contains($visibleText, 'visible geometry value'));
        $t->true(!str_contains($visibleText, 'hidden geometry value'));
        $t->true(!str_contains($visibleText, 'no-view geometry value'));
        $t->true(is_string($encoded) && str_contains($encoded, 'geometry.visible'));
        $t->true(is_string($encoded) && !str_contains($encoded, '30 0 R'));
    },
    'uses token aware AcroForm reference arrays before WordPress field review' => static function (
        TestRunner $t
    ) use ($acroFormArrayTokenBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormArrayTokenBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['article.array', 'article.keep'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $article = $fields['article.array'];
        $t->same(6, $article['object']);
        $t->same('Array token title', $article['value']);
        $t->same([8], array_column($article['widgets'], 'object'));
        $t->same([0], array_column($article['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($article['widgets'], 'referenced_from_page_annots'));
        $t->same('field_terminal', $article['value_state']['hierarchy_boundary']['current_value_source']);

        $keep = $fields['article.keep'];
        $t->same(10, $keep['object']);
        $t->same('Kept top-level field', $keep['value']);
        $t->same([], $keep['widgets']);

        foreach ([
            'decoy.fields.literal',
            'decoy.fields.nested_array',
            'decoy.fields.nested_dict',
            'decoy.fields.comment',
            'decoy.annots.literal',
            'decoy.annots.nested_array',
            'decoy.annots.nested_dict',
            'decoy.annots.comment',
            'decoy.kids.literal',
            'decoy.kids.nested_array',
            'decoy.kids.nested_dict',
            'decoy.kids.comment',
        ] as $decoyName) {
            $t->true(!isset($fields[$decoyName]));
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyName));
        }

        $t->true(str_contains($visibleText, 'Visible AcroForm array token boundary body'));
        $t->true(!str_contains($visibleText, 'Array token title'));
        $t->true(!str_contains($visibleText, 'Fields literal decoy'));
        $t->true(!str_contains($visibleText, 'Annots nested dictionary decoy'));
        $t->true(!str_contains($visibleText, 'Kids comment decoy'));
    },
    'preserves AcroForm alternate and mapping names as review metadata only' => static function (
        TestRunner $t
    ) use ($acroFormAlternateMappingNameBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormAlternateMappingNameBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['profile.email'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['profile.email'];
        $review = is_array($field['field_name_review'] ?? null) ? $field['field_name_review'] : [];
        $path = $field['field_hierarchy']['path'] ?? [];
        $t->same(8, $field['object']);
        $t->same('profile.email', $field['name']);
        $t->same('email', $field['partial_name']);
        $t->same('Public email label', $field['alternate_name'] ?? null);
        $t->same('profile.email.export', $field['mapping_name']);
        $t->same('editor@example.test', $field['value']);
        $t->same('draft@example.test', $field['default_value']);
        $t->same('text', $field['field_type_label']);
        $t->same(['FT', 'DA'], $field['field_hierarchy']['inherited_attributes']);
        $t->same(['V', 'DV', 'MaxLen'], $field['field_hierarchy']['local_attributes']);
        $t->same(['V', 'DV'], $field['field_hierarchy']['local_value_attributes']);
        $t->same([6, 8], array_column($path, 'object'));
        $t->same(['profile', 'email'], array_column($path, 'partial_name'));
        $t->same(['Parent profile label must stay ancestor review', 'Public email label'], array_column($path, 'alternate_name'));
        $t->same(['profile-parent-map', 'profile.email.export'], array_column($path, 'mapping_name'));
        $t->same('field_terminal_override', $field['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(12, $field['max_length']);
        $t->same(12, $field['max_length_review']['max_length']);
        $t->same(8, $field['max_length_review']['max_length_source_object']);
        $t->same(false, $field['max_length_review']['max_length_inherited']);
        $t->same(true, $field['max_length_review']['current_value_exceeds_max_length']);

        $t->same('acroform_field_name_review_boundary', $review['source'] ?? null);
        $t->same('Public email label', $review['alternate_name'] ?? null);
        $t->same('profile.email.export', $review['mapping_name'] ?? null);
        $t->same('Public email label', $review['wordpress_label'] ?? null);
        $t->same(false, $review['alternate_name_used_as_visible_text'] ?? null);
        $t->same(false, $review['mapping_name_used_as_visible_text'] ?? null);
        $t->same(false, $review['executes_form_actions'] ?? null);
        $t->same(false, $review['executes_javascript'] ?? null);

        $t->true(!isset($fields['detached.tu.decoy']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Detached alternate mapping decoy'));
        $t->true(str_contains($visibleText, 'Visible AcroForm alternate mapping name body'));
        $t->true(!str_contains($visibleText, 'Public email label'));
        $t->true(!str_contains($visibleText, 'profile.email.export'));
        $t->true(!str_contains($visibleText, 'editor@example.test'));
        $t->true(!str_contains($visibleText, 'Parent profile label must stay ancestor review'));
    },
];
