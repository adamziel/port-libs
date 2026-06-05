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

$acroFormWidgetPageMismatchBoundaryPdf = static function (): string {
    $pageOneText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm widget P mismatch page one body) Tj ET';
    $pageTwoText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm widget P mismatch page two body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Annots [8 0 R 12 0 R 14 0 R 16 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Annots [20 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (listed.first) /V (listed first value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (wrongpage.parent) /V (wrong page parent value must not surface) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 4 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /FT /Tx /T (wrongpage.inline) /V (wrong page inline value must not surface) /Rect [72 560 320 584] /P 4 0 R /F 4 >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /FT /Tx /T (floating.nop) /V (floating no page value) /Rect [72 520 320 544] /F 4 >>\nendobj\n"
        . "18 0 obj\n<< /FT /Ch /T (listed.second) /V (publish) /Opt [(draft) (publish)] /Kids [20 0 R] >>\nendobj\n"
        . "20 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [72 640 260 664] /P 4 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageOneText) . " >>\nstream\n{$pageOneText}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($pageTwoText) . " >>\nstream\n{$pageTwoText}\nendstream\nendobj\n"
        . "%%EOF";
};

$directWidgetFieldsRootBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible direct widget Fields boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 14 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [8 0 R 14 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (direct.widget.parent) /TU (Direct widget parent label) /TM (direct-widget-parent-map) /V (Parent value from direct widget Fields ref) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (direct.group) /V (Parent group value) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Parent 10 0 R /T (child) /V (Child terminal value) /Kids [14 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "16 0 obj\n<< /FT /Tx /T (detached.widget.ref) /V (Detached direct widget decoy) /Kids [18 0 R] >>\nendobj\n"
        . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [72 560 320 584] /F 4 >>\nendobj\n"
        . "%%EOF";
};

$acroFormChildFieldRootBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm child field root boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R 24 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [12 0 R 24 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (profile) /TU (Profile parent label) /TM (profile-parent-map) /V (parent@example.test) /DV (profile@example.test) /MaxLen 64 /Kids [12 0 R] /DA (/Helv 10 Tf 0 0 0 rg) >>\nendobj\n"
        . "12 0 obj\n<< /Parent 10 0 R /T (email) /TU (Editor email label) /TM (profile.email.export) /V (editor@example.test) /Kids [14 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n<< /FT /Ch /T (settings) /TU (Settings parent label) /V (public) /Opt [(private) (public)] /Kids [24 0 R] >>\nendobj\n"
        . "24 0 obj\n<< /Subtype /Widget /Parent 20 0 R /T (visibility) /TU (Visibility label) /TM (settings.visibility.export) /V (private) /Rect [72 600 280 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /FT /Tx /T (detached.parent.decoy) /V (Detached parent decoy) /Kids [32 0 R] >>\nendobj\n"
        . "32 0 obj\n<< /Parent 30 0 R /T (child) /V (Detached child decoy) >>\nendobj\n"
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

$acroFormIndirectScalarGenerationBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm scalar generation boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R] /NeedAppearances true >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T 30 1 R /TU 31 1 R /TM 32 1 R /V 33 1 R /DV 34 1 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Ch /T (profile.choice) /V 35 1 R /Opt [[36 1 R 37 1 R] [38 0 R 39 0 R]] /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /FT /Tx /T (profile.invalid) /TU 42 0 R /TM 43 0 R /V 40 0 R /DV 41 0 R /Kids [16 0 R] >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 1 obj\n(profile.title)\nendobj\n"
        . "31 1 obj\n(Current title label)\nendobj\n"
        . "32 1 obj\n(profile.title.export)\nendobj\n"
        . "33 1 obj\n(Current title value)\nendobj\n"
        . "34 1 obj\n(Default title value)\nendobj\n"
        . "35 1 obj\n(page)\nendobj\n"
        . "36 1 obj\n(post)\nendobj\n"
        . "37 1 obj\n(Post label)\nendobj\n"
        . "38 1 obj\n(stale option export must not surface)\nendobj\n"
        . "39 1 obj\n(stale option label must not surface)\nendobj\n"
        . "40 1 obj\n(stale current value must not surface)\nendobj\n"
        . "41 1 obj\n(stale default value must not surface)\nendobj\n"
        . "42 1 obj\n(Stale alternate label must not surface)\nendobj\n"
        . "43 1 obj\n(stale.mapping.must.not.surface)\nendobj\n"
        . "%%EOF";
};

$acroFormIndirectNumericAttributeBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect numeric attributes body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R] /NeedAppearances true >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (secret.indirect) /Ff 30 1 R /V (Sensitive value must redact) /DV (Default sensitive value) /MaxLen 31 1 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (public.max) /Ff 32 1 R /V (Too long value) /MaxLen 33 1 R /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /FT /Ch /T (choice.indirect) /Ff 34 1 R /V [(plugin) (themes)] /I [35 1 R 36 1 R 37 0 R] /Opt [[(themes) (Themes)] [(plugin) (Plugins)] [(blocks) (Blocks)]] /Kids [16 0 R] >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 1 obj\n8192\nendobj\n"
        . "31 1 obj\n8\nendobj\n"
        . "32 1 obj\n3\nendobj\n"
        . "33 1 obj\n6\nendobj\n"
        . "34 1 obj\n2097152\nendobj\n"
        . "35 1 obj\n1\nendobj\n"
        . "36 1 obj\n0\nendobj\n"
        . "37 1 obj\n2\nendobj\n"
        . "%%EOF";
};

$acroFormIndirectFieldTypeBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect field type boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R] /NeedAppearances true >>\nendobj\n"
        . "6 0 obj\n<< /FT 30 1 R /T (indirect.type.title) /V (Indirect type title value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT 31 1 R /T (indirect.type.category) /V (page) /Opt [(post) (page)] /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /FT 32 0 R /T (stale.type.review) /V (Stale type remains unknown review value) /Kids [16 0 R] >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 1 obj\n/T#78\nendobj\n"
        . "30 0 obj\n/Sig\nendobj\n"
        . "31 1 obj\n/C#68\nendobj\n"
        . "31 0 obj\n/Btn\nendobj\n"
        . "32 1 obj\n/Tx\nendobj\n"
        . "%%EOF";
};

$acroFormCommentWidgetSubtypeBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm comment widget boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.comment) /V (Comment-safe field value) /Kids [8 0 R 10 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot\n% /Subtype /Widget /Parent 6 0 R /Rect [72 600 320 624] stays a comment-only widget decoy\n/Subtype /Text /Rect [72 600 320 624] /P 3 0 R /F 4 /Contents (Comment-only child widget decoy) >>\nendobj\n"
        . "12 0 obj\n<< /Type /Annot\n% /Subtype /Widget should not promote this text annotation into an AcroForm field\n/Subtype /Text /FT /Tx /T (comment.promoted) /V (Comment subtype decoy value) /Rect [72 560 320 584] /P 3 0 R /F 4 /Contents (Comment-only page widget decoy) >>\nendobj\n"
        . "%%EOF";
};

$acroFormUnownedWidgetParentBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm unowned widget parent boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 22 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (listed.safe) /V (Listed safe value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (unowned.parent) /V (Unowned parent value must not surface) /Kids [14 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 560 300 584] /F 4 >>\nendobj\n"
        . "20 0 obj\n<< /FT /Ch /T (owned.omitted) /V (publish) /Opt [(draft) (publish)] /Kids [22 0 R] >>\nendobj\n"
        . "22 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 520 260 544] /P 3 0 R /F 4 >>\nendobj\n"
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
    'rejects wrong-page AcroForm widget P references before page-owned field repair' => static function (
        TestRunner $t
    ) use ($acroFormWidgetPageMismatchBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormWidgetPageMismatchBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['listed.first', 'floating.nop', 'listed.second'], array_keys($fields));
        $t->same(3, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $listed = $fields['listed.first'];
        $t->same([8], array_column($listed['widgets'], 'object'));
        $t->same([0], array_column($listed['widgets'], 'page_index'));
        $t->same([3], array_column($listed['widgets'], 'page_object'));
        $t->same([0], array_column($listed['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($listed['widgets'], 'referenced_from_page_annots'));

        $floating = $fields['floating.nop'];
        $t->same(16, $floating['object']);
        $t->same('floating no page value', $floating['value']);
        $t->same([16], array_column($floating['widgets'], 'object'));
        $t->same([0], array_column($floating['widgets'], 'page_index'));
        $t->same([3], array_column($floating['widgets'], 'page_object'));
        $t->same([3], array_column($floating['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($floating['widgets'], 'referenced_from_page_annots'));

        $second = $fields['listed.second'];
        $t->same(18, $second['object']);
        $t->same('choice', $second['field_type_label']);
        $t->same('publish', $second['value']);
        $t->same([20], array_column($second['widgets'], 'object'));
        $t->same([1], array_column($second['widgets'], 'page_index'));
        $t->same([4], array_column($second['widgets'], 'page_object'));
        $t->same([0], array_column($second['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($second['widgets'], 'referenced_from_page_annots'));

        foreach (['wrongpage.parent', 'wrongpage.inline'] as $wrongPageName) {
            $t->true(!isset($fields[$wrongPageName]));
            $t->true(is_string($encoded) && !str_contains($encoded, $wrongPageName));
        }

        $t->true(str_contains($visibleText, 'Visible AcroForm widget P mismatch page one body'));
        $t->true(str_contains($visibleText, 'Visible AcroForm widget P mismatch page two body'));
        $t->true(!str_contains($visibleText, 'wrong page parent value must not surface'));
        $t->true(!str_contains($visibleText, 'wrong page inline value must not surface'));
        $t->true(!str_contains($visibleText, 'floating no page value'));
        $t->true(!str_contains($visibleText, 'listed first value'));
        $t->true(!str_contains($visibleText, 'publish'));
    },
    'normalizes direct widget entries in AcroForm Fields to their parent field roots' => static function (
        TestRunner $t
    ) use ($directWidgetFieldsRootBoundaryPdf, $fieldsByName): void {
        $pdf = $directWidgetFieldsRootBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['direct.widget.parent', 'direct.group.child'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $direct = $fields['direct.widget.parent'];
        $t->same(6, $direct['object']);
        $t->same('direct.widget.parent', $direct['name']);
        $t->same('text', $direct['field_type_label']);
        $t->same('Parent value from direct widget Fields ref', $direct['value']);
        $t->same('field_terminal', $direct['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same('Direct widget parent label', $direct['alternate_name']);
        $t->same('direct-widget-parent-map', $direct['mapping_name']);
        $t->same(['FT', 'V'], $direct['field_hierarchy']['local_attributes']);
        $t->same(['DA'], $direct['field_hierarchy']['inherited_attributes']);
        $t->same([8], array_column($direct['widgets'], 'object'));
        $t->same([0], array_column($direct['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($direct['widgets'], 'referenced_from_page_annots'));

        $child = $fields['direct.group.child'];
        $t->same(12, $child['object']);
        $t->same('Child terminal value', $child['value']);
        $t->same([10, 12], array_column($child['field_hierarchy']['path'], 'object'));
        $t->same(['direct.group', 'direct.group.child'], array_column($child['field_hierarchy']['path'], 'full_name'));
        $t->same(['FT', 'DA'], $child['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $child['field_hierarchy']['local_value_attributes']);
        $t->same([14], array_column($child['widgets'], 'object'));
        $t->same([1], array_column($child['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($child['widgets'], 'referenced_from_page_annots'));
        $t->same('field_terminal_override', $child['value_state']['hierarchy_boundary']['current_value_source']);

        $t->true(!isset($fields['detached.widget.ref']));
        $t->true(str_contains($visibleText, 'Visible direct widget Fields boundary body'));
        $t->true(!str_contains($visibleText, 'Parent value from direct widget Fields ref'));
        $t->true(!str_contains($visibleText, 'Child terminal value'));
        $t->true(!str_contains($visibleText, 'Detached direct widget decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, '"name":"#8"'));
        $t->true(is_string($encoded) && !str_contains($encoded, '"name":"#14"'));
    },
    'normalizes child AcroForm Fields entries to their parent field roots' => static function (
        TestRunner $t
    ) use ($acroFormChildFieldRootBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormChildFieldRootBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['profile.email', 'settings.visibility'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $email = $fields['profile.email'];
        $t->same(12, $email['object']);
        $t->same('profile.email', $email['name']);
        $t->same('email', $email['partial_name']);
        $t->same('Editor email label', $email['alternate_name']);
        $t->same('profile.email.export', $email['mapping_name']);
        $t->same('Tx', $email['field_type']);
        $t->same('text', $email['field_type_label']);
        $t->same('editor@example.test', $email['value']);
        $t->same('profile@example.test', $email['default_value']);
        $t->same([10, 12], array_column($email['field_hierarchy']['path'], 'object'));
        $t->same(['profile', 'email'], array_column($email['field_hierarchy']['path'], 'partial_name'));
        $t->same(['Profile parent label', 'Editor email label'], array_column($email['field_hierarchy']['path'], 'alternate_name'));
        $t->same(['profile-parent-map', 'profile.email.export'], array_column($email['field_hierarchy']['path'], 'mapping_name'));
        $t->same(['FT', 'DV', 'DA', 'MaxLen'], $email['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $email['field_hierarchy']['local_value_attributes']);
        $t->same('field_terminal_override', $email['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(true, $email['value_state']['hierarchy_boundary']['terminal_overrides_parent_value']);
        $t->same(true, $email['max_length_review']['max_length_inherited']);
        $t->same(10, $email['max_length_review']['max_length_source_object']);
        $t->same([14], array_column($email['widgets'], 'object'));
        $t->same([0], array_column($email['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($email['widgets'], 'referenced_from_page_annots'));

        $visibility = $fields['settings.visibility'];
        $t->same(24, $visibility['object']);
        $t->same('settings.visibility', $visibility['name']);
        $t->same('visibility', $visibility['partial_name']);
        $t->same('Visibility label', $visibility['alternate_name']);
        $t->same('settings.visibility.export', $visibility['mapping_name']);
        $t->same('Ch', $visibility['field_type']);
        $t->same('choice', $visibility['field_type_label']);
        $t->same('private', $visibility['value']);
        $t->same([
            ['export' => 'private', 'label' => 'private'],
            ['export' => 'public', 'label' => 'public'],
        ], $visibility['options']);
        $t->same([20, 24], array_column($visibility['field_hierarchy']['path'], 'object'));
        $t->same(['settings', 'visibility'], array_column($visibility['field_hierarchy']['path'], 'partial_name'));
        $t->same(['FT', 'DA', 'Opt'], $visibility['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $visibility['field_hierarchy']['local_value_attributes']);
        $t->same('field_terminal_override', $visibility['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same([['index' => 0, 'export' => 'private', 'label' => 'private']], $visibility['value_state']['selected_options']);
        $t->same([24], array_column($visibility['widgets'], 'object'));
        $t->same([1], array_column($visibility['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($visibility['widgets'], 'referenced_from_page_annots'));

        $t->true(!isset($fields['email']));
        $t->true(!isset($fields['visibility']));
        $t->true(!isset($fields['detached.parent.decoy.child']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Detached child decoy'));
        $t->true(str_contains($visibleText, 'Visible AcroForm child field root boundary body'));
        $t->true(!str_contains($visibleText, 'editor@example.test'));
        $t->true(!str_contains($visibleText, 'parent@example.test'));
        $t->true(!str_contains($visibleText, 'Detached parent decoy'));
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
    'rejects generation mismatched indirect scalar operands in AcroForm fields' => static function (
        TestRunner $t
    ) use ($acroFormIndirectScalarGenerationBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormIndirectScalarGenerationBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['profile.title', 'profile.choice', 'profile.invalid'], array_keys($fields));
        $t->same(3, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $title = $fields['profile.title'];
        $t->same(6, $title['object']);
        $t->same('Current title label', $title['alternate_name']);
        $t->same('profile.title.export', $title['mapping_name']);
        $t->same('Current title value', $title['value']);
        $t->same('Default title value', $title['default_value']);
        $t->same('field_terminal', $title['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same([8], array_column($title['widgets'], 'object'));
        $t->same([0], array_column($title['widgets'], 'page_annotation_index'));

        $choice = $fields['profile.choice'];
        $t->same('choice', $choice['field_type_label']);
        $t->same('page', $choice['value']);
        $t->same([['export' => 'post', 'label' => 'Post label']], $choice['options']);
        $t->same(['page'], $choice['value_state']['unmatched_values']);
        $t->same([12], array_column($choice['widgets'], 'object'));

        $invalid = $fields['profile.invalid'];
        $t->same(14, $invalid['object']);
        $t->same(null, $invalid['alternate_name']);
        $t->same('profile.invalid', $invalid['mapping_name']);
        $t->same(null, $invalid['value']);
        $t->same(null, $invalid['default_value']);
        $t->same(null, $invalid['value_state']['display_value']);
        $t->same(false, $invalid['value_state']['changed_from_default']);
        $t->same([16], array_column($invalid['widgets'], 'object'));

        foreach ([
            'stale option export must not surface',
            'stale option label must not surface',
            'stale current value must not surface',
            'stale default value must not surface',
            'Stale alternate label must not surface',
            'stale.mapping.must.not.surface',
        ] as $staleText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $staleText));
            $t->true(!str_contains($visibleText, $staleText));
        }

        $t->true(str_contains($visibleText, 'Visible AcroForm scalar generation boundary body'));
        $t->true(!str_contains($visibleText, 'Current title value'));
        $t->true(!str_contains($visibleText, 'Default title value'));
        $t->true(!str_contains($visibleText, 'Current title label'));
    },
    'resolves generation exact indirect numeric AcroForm field attributes' => static function (
        TestRunner $t
    ) use ($acroFormIndirectNumericAttributeBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormIndirectNumericAttributeBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['secret.indirect', 'public.max', 'choice.indirect'], array_keys($fields));
        $t->same(3, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $secret = $fields['secret.indirect'];
        $t->same(8192, $secret['flags']);
        $t->same(['password'], $secret['flag_names']);
        $t->same(null, $secret['value']);
        $t->same(null, $secret['default_value']);
        $t->same('[redacted]', $secret['value_state']['display_value']);
        $t->same('redacted_password', $secret['value_state']['state_source']);
        $t->same(8, $secret['max_length']);
        $t->same(6, $secret['max_length_review']['max_length_source_object']);
        $t->same(true, $secret['max_length_review']['value_redacted']);
        $t->same(false, $secret['max_length_review']['password_value_length_exposed']);

        $public = $fields['public.max'];
        $t->same(3, $public['flags']);
        $t->same(['read_only', 'required'], $public['flag_names']);
        $t->same(6, $public['max_length']);
        $t->same(true, $public['max_length_review']['current_value_exceeds_max_length']);
        $t->same(14, $public['max_length_review']['current_value_length']);

        $choice = $fields['choice.indirect'];
        $t->same(2097152, $choice['flags']);
        $t->same(['multi_select'], $choice['flag_names']);
        $t->same([1, 0], $choice['value_state']['selected_indices']);
        $t->same('field', $choice['value_state']['selected_indices_source']);
        $t->same([
            ['index' => 1, 'export' => 'plugin', 'label' => 'Plugins'],
            ['index' => 0, 'export' => 'themes', 'label' => 'Themes'],
        ], $choice['value_state']['selected_options']);
        $t->same([], $choice['value_state']['unmatched_values']);

        foreach (['30 1 R', '31 1 R', '32 1 R', '33 1 R', '34 1 R', '35 1 R', '36 1 R', '37 0 R'] as $rawToken) {
            $t->true(is_string($encoded) && !str_contains($encoded, $rawToken));
        }
        $t->true(str_contains($visibleText, 'Visible AcroForm indirect numeric attributes body'));
        $t->true(!str_contains($visibleText, 'Sensitive value must redact'));
        $t->true(!str_contains($visibleText, 'Too long value'));
    },
    'resolves generation exact indirect AcroForm field type names' => static function (
        TestRunner $t
    ) use ($acroFormIndirectFieldTypeBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormIndirectFieldTypeBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['indirect.type.title', 'indirect.type.category', 'stale.type.review'], array_keys($fields));
        $t->same(3, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $title = $fields['indirect.type.title'];
        $t->same(6, $title['object']);
        $t->same('Tx', $title['field_type']);
        $t->same('text', $title['field_type_label']);
        $t->same('Indirect type title value', $title['value']);
        $t->same(['FT', 'V'], $title['field_hierarchy']['local_attributes']);
        $t->same(6, $title['field_hierarchy']['field_type_source_object']);
        $t->same([8], array_column($title['widgets'], 'object'));
        $t->same([0], array_column($title['widgets'], 'page_annotation_index'));

        $choice = $fields['indirect.type.category'];
        $t->same(10, $choice['object']);
        $t->same('Ch', $choice['field_type']);
        $t->same('choice', $choice['field_type_label']);
        $t->same('page', $choice['value']);
        $t->same([
            ['export' => 'post', 'label' => 'post'],
            ['export' => 'page', 'label' => 'page'],
        ], $choice['options']);
        $t->same([['index' => 1, 'export' => 'page', 'label' => 'page']], $choice['value_state']['selected_options']);
        $t->same('inferred_from_value', $choice['value_state']['selected_indices_source']);
        $t->same([12], array_column($choice['widgets'], 'object'));
        $t->same([1], array_column($choice['widgets'], 'page_annotation_index'));

        $stale = $fields['stale.type.review'];
        $t->same(14, $stale['object']);
        $t->same(null, $stale['field_type']);
        $t->same('unknown', $stale['field_type_label']);
        $t->same('Stale type remains unknown review value', $stale['value']);
        $t->same([16], array_column($stale['widgets'], 'object'));
        $t->same([2], array_column($stale['widgets'], 'page_annotation_index'));

        $t->true(str_contains($visibleText, 'Visible AcroForm indirect field type boundary body'));
        $t->true(!str_contains($visibleText, 'Indirect type title value'));
        $t->true(!str_contains($visibleText, 'Stale type remains unknown review value'));
        foreach (['/Sig', '/Btn', '30 0 R', '31 0 R', '32 0 R'] as $staleToken) {
            $t->true(is_string($encoded) && !str_contains($encoded, $staleToken));
        }
    },
    'ignores comment-only Widget subtype markers in AcroForm fields and page annotations' => static function (
        TestRunner $t
    ) use ($acroFormCommentWidgetSubtypeBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormCommentWidgetSubtypeBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['article.comment'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['article.comment'];
        $t->same(6, $field['object']);
        $t->same('text', $field['field_type_label']);
        $t->same('Comment-safe field value', $field['value']);
        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 640.0, 320.0, 664.0], $field['widgets'][0]['rect']);

        $t->true(!isset($fields['comment.promoted']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'comment.promoted'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Comment subtype decoy value'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Comment-only child widget decoy'));
        $t->true(str_contains($visibleText, 'Visible AcroForm comment widget boundary body'));
        $t->true(!str_contains($visibleText, 'Comment-safe field value'));
        $t->true(!str_contains($visibleText, 'Comment subtype decoy value'));
        $t->true(!str_contains($visibleText, 'Comment-only page widget decoy'));
    },
    'rejects page widget parent repair when the parent field Kids do not own the widget' => static function (
        TestRunner $t
    ) use ($acroFormUnownedWidgetParentBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormUnownedWidgetParentBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['listed.safe', 'owned.omitted'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $listed = $fields['listed.safe'];
        $t->same(6, $listed['object']);
        $t->same('Listed safe value', $listed['value']);
        $t->same([8], array_column($listed['widgets'], 'object'));
        $t->same([0], array_column($listed['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($listed['widgets'], 'referenced_from_page_annots'));

        $omitted = $fields['owned.omitted'];
        $t->same(20, $omitted['object']);
        $t->same('choice', $omitted['field_type_label']);
        $t->same('publish', $omitted['value']);
        $t->same([22], array_column($omitted['widgets'], 'object'));
        $t->same([2], array_column($omitted['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($omitted['widgets'], 'referenced_from_page_annots'));

        $t->true(!isset($fields['unowned.parent']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'unowned.parent'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Unowned parent value must not surface'));
        $t->true(str_contains($visibleText, 'Visible AcroForm unowned widget parent boundary body'));
        $t->true(!str_contains($visibleText, 'Listed safe value'));
        $t->true(!str_contains($visibleText, 'publish'));
        $t->true(!str_contains($visibleText, 'Unowned parent value must not surface'));
    },
];
