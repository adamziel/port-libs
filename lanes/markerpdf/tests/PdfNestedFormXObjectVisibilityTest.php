<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$formXObject = static function (string $content, string $xObjects = '', string $bbox = '0 0 50 50'): string {
    $resources = $xObjects === '' ? '' : '/Resources << /XObject << ' . $xObjects . ' >> >> ';

    return '<< /Type /XObject /Subtype /Form /BBox [' . $bbox . '] '
        . $resources
        . '/Length ' . strlen($content) . ">>\nstream\n{$content}\nendstream";
};

$imageXObject = static function (): string {
    return "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray "
        . "/BitsPerComponent 8 /Length 1 >>\nstream\n\x80\nendstream";
};

$pdfWithXObjects = static function (
    string $pageContent,
    string $pageXObjects,
    array $extraObjects,
    string $pageResources = ''
): string {
    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
        . "/CropBox [0 0 612 792] /Resources << /Font << /F1 20 0 R >> "
        . "/XObject << {$pageXObjects} >> {$pageResources} >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . ">>\nstream\n"
        . $pageContent
        . "\nendstream\nendobj\n";
    foreach ($extraObjects as $objectNumber => $body) {
        $pdf .= $objectNumber . " 0 obj\n" . $body . "\nendobj\n";
    }

    return $pdf
        . "20 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica "
        . "/Encoding /WinAnsiEncoding >>\nendobj\n%%EOF";
};

return [
    'propagates nested Form image resources into exact visibility bounds' => static function (
        TestRunner $t
    ) use ($formXObject, $imageXObject, $pdfWithXObjects): void {
        $formContent = 'q 40 0 0 40 0 0 cm /Im0 Do Q';
        $pageContent = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (high text) Tj ET '
            . 'q 1 0 0 1 300 300 cm /Fm Do Q';
        $pdf = $pdfWithXObjects($pageContent, '/Fm 5 0 R', [
            5 => $formXObject($formContent, '/Im0 6 0 R'),
            6 => $imageXObject(),
        ]);
        $extractor = new PdfTextExtractor();
        $visibility = $extractor->diagnostics($pdf)['textVisibility'];
        $placements = $extractor->extractImagePlacements($pdf);

        $t->same(0, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same([], $visibility['unresolvedReasons'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
        $t->same(1, count($placements));
        $t->same(6, $placements[0]['object'] ?? null);
        $t->same(['Fm', 'Im0'], $placements[0]['resourcePath'] ?? null);
        $t->same([
            'x1' => 300.0,
            'y1' => 300.0,
            'x2' => 340.0,
            'y2' => 340.0,
        ], $placements[0]['bbox'] ?? null);
    },

    'charges a decomposed Form only for its exact primitives' => static function (
        TestRunner $t
    ) use ($formXObject, $pdfWithXObjects): void {
        $pageContent = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (earlier text) Tj ET /Fm Do';
        $disjoint = $pdfWithXObjects($pageContent, '/Fm 5 0 R', [
            5 => $formXObject('600 0 m 600 792 l S', '', '0 0 612 792'),
        ]);
        $covering = $pdfWithXObjects($pageContent, '/Fm 5 0 R', [
            5 => $formXObject('60 690 180 50 re f', '', '0 0 612 792'),
        ]);

        $disjointVisibility = (new PdfTextExtractor())->diagnostics($disjoint)['textVisibility'];
        $coveringVisibility = (new PdfTextExtractor())->diagnostics($covering)['textVisibility'];

        // The declared Form BBox covers the page in both inputs. A decoded,
        // bounded invocation is represented by its injected program, so the
        // disjoint line is not treated as a second whole-page paint while the
        // genuinely covering primitive remains fail closed.
        $t->same(0, $disjointVisibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(true, $disjointVisibility['complete'] ?? null);
        $t->same(1, $coveringVisibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(false, $coveringVisibility['complete'] ?? null);
    },

    'retains the conservative Form invocation for transparency groups' => static function (
        TestRunner $t
    ) use ($pdfWithXObjects): void {
        $formContent = '600 0 m 600 792 l S';
        $formObject = '<< /Type /XObject /Subtype /Form /BBox [0 0 612 792] '
            . '/Group << /S /Transparency >> '
            . '/Length ' . strlen($formContent) . ">>\nstream\n{$formContent}\nendstream";
        $pageContent = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (earlier text) Tj ET /Fm Do';
        $pdf = $pdfWithXObjects($pageContent, '/Fm 5 0 R', [5 => $formObject]);
        $visibility = (new PdfTextExtractor())->diagnostics($pdf)['textVisibility'];

        $t->same(1, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },

    'retains the conservative Form invocation when it contains an inline image' => static function (
        TestRunner $t
    ) use ($formXObject, $pdfWithXObjects): void {
        $formContent = "q 180 0 0 50 60 690 cm BI /W 1 /H 1 /CS /G /BPC 8 ID \x80 EI Q";
        $pageContent = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (earlier text) Tj ET /Fm Do';
        $pdf = $pdfWithXObjects($pageContent, '/Fm 5 0 R', [
            5 => $formXObject($formContent, '', '0 0 612 792'),
        ]);
        $visibility = (new PdfTextExtractor())->diagnostics($pdf)['textVisibility'];

        $t->same(1, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },

    'retains the conservative Form invocation beyond the visibility token budget' => static function (
        TestRunner $t
    ) use ($formXObject, $pdfWithXObjects): void {
        $formContent = str_repeat('600 0 m ', 30) . '60 690 180 50 re f';
        $pageContent = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (earlier text) Tj ET /Fm Do';
        $pdf = $pdfWithXObjects($pageContent, '/Fm 5 0 R', [
            5 => $formXObject($formContent, '', '0 0 612 792'),
        ]);
        $visibility = (new PdfTextExtractor([
            'pdfMaxContentTokens' => 64,
        ]))->diagnostics($pdf)['textVisibility'];

        $t->same(1, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },

    'fails closed when individually bounded Forms exceed the combined byte budget' => static function (
        TestRunner $t
    ) use ($formXObject, $pdfWithXObjects): void {
        $pageContent = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (earlier text) Tj ET /A Do /B Do';
        $pdf = $pdfWithXObjects($pageContent, '/A 5 0 R /B 6 0 R', [
            5 => $formXObject('500 0 m 500 100 l S', '', '500 0 612 100'),
            6 => $formXObject('600 0 m 600 792 l S', '', '0 0 612 792'),
        ]);

        $control = (new PdfTextExtractor())->diagnostics($pdf);
        $t->same(true, $control['textVisibility']['complete'] ?? null);
        $t->same(0, $control['textVisibility']['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same([], $control['pageExtractionIssues'] ?? null);

        $diagnostics = (new PdfTextExtractor([
            'pdfMaxTokenizedContentStreamBytes' => 160,
            'pdfMaxContentTokens' => 4096,
        ]))->diagnostics($pdf);
        $visibility = $diagnostics['textVisibility'];
        $expectedIssue = [
            'page' => 1,
            'pageObject' => 3,
            'contentReference' => 4,
            'contentObject' => 4,
            'reason' => 'form_xobject_expansion_byte_limit',
            'filters' => [],
            'limit' => 160,
            'actual' => 183,
            'recoverable' => true,
        ];

        $t->same(1, $visibility['visibleOutputRuns'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
        $t->same(1, $visibility['unresolvedRuns'] ?? null);
        $t->same(1, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same([
            'form-xobject-expansion-limit',
            'later-paint-occlusion',
        ], $visibility['unresolvedReasons'] ?? null);
        $t->same([
            'form-xobject-expansion-limit' => 1,
            'later-paint-occlusion' => 1,
        ], $visibility['unresolvedReasonCounts'] ?? null);
        $t->same([$expectedIssue], $diagnostics['pageExtractionIssues'] ?? null);
        $t->same([$expectedIssue], $diagnostics['resourceLimitIssues'] ?? null);
    },

    'fails closed before an early Form expansion consumes the combined token budget' => static function (
        TestRunner $t
    ) use ($formXObject, $pdfWithXObjects): void {
        $pageContent = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (earlier text) Tj ET /A Do /B Do';
        $pdf = $pdfWithXObjects($pageContent, '/A 5 0 R /B 6 0 R', [
            5 => $formXObject('500 0 m 500 100 l S', '', '500 0 612 100'),
            6 => $formXObject('600 0 m 600 792 l S', '', '0 0 612 792'),
        ]);
        $diagnostics = (new PdfTextExtractor([
            'pdfMaxTokenizedContentStreamBytes' => 4096,
            'pdfMaxContentTokens' => 40,
        ]))->diagnostics($pdf);
        $visibility = $diagnostics['textVisibility'];
        $expectedIssue = [
            'page' => 1,
            'pageObject' => 3,
            'contentReference' => 4,
            'contentObject' => 4,
            'reason' => 'form_xobject_expansion_token_limit',
            'filters' => [],
            'limit' => 40,
            'actual' => 41,
            'recoverable' => true,
        ];

        $t->same(1, $visibility['visibleOutputRuns'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
        $t->same(1, $visibility['unresolvedRuns'] ?? null);
        $t->same(1, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same([
            'form-xobject-expansion-limit',
            'later-paint-occlusion',
        ], $visibility['unresolvedReasons'] ?? null);
        $t->same([
            'form-xobject-expansion-limit' => 1,
            'later-paint-occlusion' => 1,
        ], $visibility['unresolvedReasonCounts'] ?? null);
        $t->same([$expectedIssue], $diagnostics['pageExtractionIssues'] ?? null);
        $t->same([$expectedIssue], $diagnostics['resourceLimitIssues'] ?? null);
    },

    'fails closed when expanded Contents members exceed the logical page budget' => static function (
        TestRunner $t
    ) use ($formXObject): void {
        $memberA = '/A Do';
        $memberB = '/B Do';
        $formA = $formXObject('BT /F1 12 Tf 1 0 0 1 72 700 Tm (form A text) Tj ET', '', '0 0 612 792');
        $formB = $formXObject('BT /F1 12 Tf 1 0 0 1 72 650 Tm (form B text) Tj ET', '', '0 0 612 792');
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
            . "/Resources << /Font << /F1 20 0 R >> /XObject << /A 5 0 R /B 7 0 R >> >> "
            . "/Contents [4 0 R 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($memberA) . ">>\nstream\n{$memberA}\nendstream\nendobj\n"
            . "5 0 obj\n{$formA}\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($memberB) . ">>\nstream\n{$memberB}\nendstream\nendobj\n"
            . "7 0 obj\n{$formB}\nendobj\n"
            . "20 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica "
            . "/Encoding /WinAnsiEncoding >>\nendobj\n%%EOF";

        $controlExtractor = new PdfTextExtractor([
            'pdfMaxTokenizedContentStreamBytes' => 4096,
            'pdfMaxContentTokens' => 4096,
        ]);
        $t->same(['form A text', 'form B text'], $controlExtractor->extractTextLines($pdf));
        $t->same(true, $controlExtractor->diagnostics($pdf)['textVisibility']['complete'] ?? null);

        foreach ([
            [
                'options' => [
                    'pdfMaxTokenizedContentStreamBytes' => 180,
                    'pdfMaxContentTokens' => 4096,
                ],
                'reason' => 'form_xobject_expansion_byte_limit',
            ],
            [
                'options' => [
                    'pdfMaxTokenizedContentStreamBytes' => 4096,
                    'pdfMaxContentTokens' => 60,
                ],
                'reason' => 'form_xobject_expansion_token_limit',
            ],
        ] as $case) {
            $diagnostics = (new PdfTextExtractor($case['options']))->diagnostics($pdf);
            $t->same(false, $diagnostics['textVisibility']['complete'] ?? null);
            $t->same(1, $diagnostics['textVisibility']['unresolvedRuns'] ?? null);
            $t->same(1, $diagnostics['textVisibility']['unresolvedOcclusionRiskRuns'] ?? null);
            $t->same(
                ['form-xobject-expansion-limit', 'later-paint-occlusion'],
                $diagnostics['textVisibility']['unresolvedReasons'] ?? null
            );
            $t->same(
                [$case['reason']],
                array_column($diagnostics['pageExtractionIssues'] ?? [], 'reason')
            );
        }
    },

    'does not decompose an over-byte-limit Form after resource renaming' => static function (
        TestRunner $t
    ) use ($pdfWithXObjects): void {
        $formContent = str_repeat(' ', 220) . '60 690 180 50 re f';
        $form = '<< /Type /XObject /Subtype /Form /BBox [0 0 612 792] '
            . '/Resources << /Font << /Unused 20 0 R >> >> '
            . '/Length ' . strlen($formContent) . ">>\nstream\n{$formContent}\nendstream";
        $pageContent = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (earlier text) Tj ET /Fm Do';
        $diagnostics = (new PdfTextExtractor([
            'pdfMaxTokenizedContentStreamBytes' => 200,
        ]))->diagnostics($pdfWithXObjects($pageContent, '/Fm 5 0 R', [5 => $form]));

        $t->same(1, $diagnostics['textVisibility']['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(false, $diagnostics['textVisibility']['complete'] ?? null);
        $t->same(
            ['tokenized_form_xobject_byte_limit'],
            array_column($diagnostics['pageExtractionIssues'] ?? [], 'reason')
        );

        $formOnlyContent = str_repeat(' ', 220)
            . 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (form-only text) Tj ET';
        $formOnly = '<< /Type /XObject /Subtype /Form /BBox [0 0 612 792] '
            . '/Resources << /Font << /Unused 20 0 R >> >> '
            . '/Length ' . strlen($formOnlyContent) . ">>\nstream\n{$formOnlyContent}\nendstream";
        $formOnlyPdf = $pdfWithXObjects('/Fm Do', '/Fm 5 0 R', [5 => $formOnly]);
        $formOnlyExtractor = new PdfTextExtractor([
            'pdfMaxTokenizedContentStreamBytes' => 200,
        ]);
        $formOnlyDiagnostics = $formOnlyExtractor->diagnostics($formOnlyPdf);
        $t->same([], $formOnlyExtractor->extractTextLines($formOnlyPdf));
        $t->same(1, $formOnlyDiagnostics['textVisibility']['unresolvedRuns'] ?? null);
        $t->same(0, $formOnlyDiagnostics['textVisibility']['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(false, $formOnlyDiagnostics['textVisibility']['complete'] ?? null);
    },

    'preserves an inline image while renaming Form resources and retains Do' => static function (
        TestRunner $t
    ) use ($pdfWithXObjects): void {
        $formContent = "q 180 0 0 50 60 690 cm BI /W 1 /H 1 /CS /G /BPC 8 ID \x80 EI Q";
        $form = '<< /Type /XObject /Subtype /Form /BBox [0 0 612 792] '
            . '/Resources << /Font << /Unused 20 0 R >> >> '
            . '/Length ' . strlen($formContent) . ">>\nstream\n{$formContent}\nendstream";
        $pageContent = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (earlier text) Tj ET /Fm Do';
        $diagnostics = (new PdfTextExtractor())->diagnostics(
            $pdfWithXObjects($pageContent, '/Fm 5 0 R', [5 => $form])
        );

        $t->same(1, $diagnostics['textVisibility']['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(false, $diagnostics['textVisibility']['complete'] ?? null);
        $t->same([], $diagnostics['pageExtractionIssues'] ?? null);
    },

    'keeps crafted page and nested Form XObject resource names collision safe' => static function (
        TestRunner $t
    ) use ($formXObject, $imageXObject, $pdfWithXObjects): void {
        $formContent = 'q 40 0 0 40 0 0 cm /Im0 Do Q';
        $pageContent = 'BT /F1 12 Tf 1 0 0 1 50 50 Tm (corner text) Tj ET '
            . 'q 1 0 0 1 300 300 cm /Fm Do Q '
            . 'q 100 0 0 100 0 0 cm /X5_Im0 Do Q';
        $pdf = $pdfWithXObjects($pageContent, '/Fm 5 0 R /X5_Im0 7 0 R', [
            5 => $formXObject($formContent, '/Im0 6 0 R'),
            6 => $imageXObject(),
            // The crafted page resource occupies the Form's natural prefix.
            // Its transformed Form bounds are disjoint from corner text; an
            // unsafe overwrite with the nested image would cover that text.
            7 => $formXObject('', '', '3 3 4 4'),
        ]);
        $extractor = new PdfTextExtractor();
        $visibility = $extractor->diagnostics($pdf)['textVisibility'];
        $placements = $extractor->extractImagePlacements($pdf);

        $t->same(0, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
        $t->same(1, count($placements));
        $t->same(6, $placements[0]['object'] ?? null);
        $t->same([
            'x1' => 300.0,
            'y1' => 300.0,
            'x2' => 340.0,
            'y2' => 340.0,
        ], $placements[0]['bbox'] ?? null);
    },

    'propagates image resources through multiple nested Form scopes' => static function (
        TestRunner $t
    ) use ($formXObject, $imageXObject, $pdfWithXObjects): void {
        $innerContent = 'q 40 0 0 40 0 0 cm /Im0 Do Q';
        $pageContent = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (high text) Tj ET '
            . 'q 1 0 0 1 300 300 cm /Outer Do Q';
        $pdf = $pdfWithXObjects($pageContent, '/Outer 5 0 R', [
            5 => $formXObject('/Inner Do', '/Inner 6 0 R'),
            6 => $formXObject($innerContent, '/Im0 7 0 R'),
            7 => $imageXObject(),
        ]);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['high text'], $extractor->extractTextLines($pdf));
        $t->same(0, $diagnostics['textVisibility']['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same([], $diagnostics['textVisibility']['unresolvedReasons'] ?? null);
        $t->same(true, $diagnostics['textVisibility']['complete'] ?? null);
        $t->same([], $diagnostics['pageExtractionIssues'] ?? null);
    },

    'expands a Form whose name and Do operator cross Contents members' => static function (
        TestRunner $t
    ) use ($formXObject): void {
        $memberA = '/Fm ';
        $memberB = 'Do';
        $form = $formXObject(
            'BT /F1 12 Tf 1 0 0 1 72 700 Tm (FORM-VISIBLE) Tj ET',
            '',
            '0 0 612 792'
        );
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
            . "/Resources << /Font << /F1 20 0 R >> /XObject << /Fm 6 0 R >> >> "
            . "/Contents [4 0 R 5 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($memberA) . ">>\nstream\n{$memberA}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($memberB) . ">>\nstream\n{$memberB}\nendstream\nendobj\n"
            . "6 0 obj\n{$form}\nendobj\n"
            . "20 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica "
            . "/Encoding /WinAnsiEncoding >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['FORM-VISIBLE'], $extractor->extractTextLines($pdf));
        $t->same(1, $diagnostics['textVisibility']['visibleOutputRuns'] ?? null);
        $t->same(true, $diagnostics['textVisibility']['complete'] ?? null);
        $t->same([], $diagnostics['pageExtractionIssues'] ?? null);
    },

    'does not expand Form Do operators inside same or split text objects' => static function (
        TestRunner $t
    ) use ($formXObject, $pdfWithXObjects): void {
        $form = $formXObject(
            'BT /F1 12 Tf 1 0 0 1 72 680 Tm (FORM-IN-TEXT-OBJECT) Tj ET',
            '',
            '0 0 612 792'
        );
        $sameMemberContent = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (PAGE-TEXT) Tj /Fm Do ET';
        $sameMember = $pdfWithXObjects($sameMemberContent, '/Fm 5 0 R', [5 => $form]);

        $memberA = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (PAGE-TEXT) Tj';
        $memberB = '/Fm Do';
        $memberC = 'ET';
        $splitMembers = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
            . "/Resources << /Font << /F1 20 0 R >> /XObject << /Fm 6 0 R >> >> "
            . "/Contents [4 0 R 5 0 R 7 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($memberA) . ">>\nstream\n{$memberA}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($memberB) . ">>\nstream\n{$memberB}\nendstream\nendobj\n"
            . "6 0 obj\n{$form}\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($memberC) . ">>\nstream\n{$memberC}\nendstream\nendobj\n"
            . "20 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica "
            . "/Encoding /WinAnsiEncoding >>\nendobj\n%%EOF";

        foreach (['same member' => $sameMember, 'split members' => $splitMembers] as $label => $pdf) {
            $extractor = new PdfTextExtractor();
            $diagnostics = $extractor->diagnostics($pdf);
            $placements = $extractor->extractFormXObjectPlacements($pdf);

            $t->same(['PAGE-TEXT'], $extractor->extractTextLines($pdf), $label);
            $t->same(1, $diagnostics['textVisibility']['visibleOutputRuns'] ?? null, $label);
            $t->same([], $diagnostics['textVisibility']['laterPaintRisks'] ?? null, $label);
            $t->same(true, $diagnostics['textVisibility']['complete'] ?? null, $label);
            $t->same(1, count($placements), $label);
            $t->same(false, $placements[0]['placementEligible'] ?? null, $label);
            $t->same('form-invalid-graphics-context', $placements[0]['dispositionReason'] ?? null, $label);
        }
    },

    'bounds recursive Form resources while retaining a cycle extraction issue' => static function (
        TestRunner $t
    ) use ($formXObject, $pdfWithXObjects): void {
        $pageContent = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (high text) Tj ET '
            . 'q 1 0 0 1 300 300 cm /Fm Do Q';
        $pdf = $pdfWithXObjects($pageContent, '/Fm 5 0 R', [
            5 => $formXObject('/Loop Do', '/Loop 5 0 R'),
        ]);
        $diagnostics = (new PdfTextExtractor())->diagnostics($pdf);

        $t->same(0, $diagnostics['textVisibility']['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(1, $diagnostics['textVisibility']['unresolvedRuns'] ?? null);
        $t->same(['form-xobject-expansion-unresolved'], $diagnostics['textVisibility']['unresolvedReasons'] ?? null);
        $t->same(false, $diagnostics['textVisibility']['complete'] ?? null);
        $t->same(
            ['form_xobject_cycle'],
            array_column($diagnostics['pageExtractionIssues'] ?? [], 'reason')
        );
    },

    'keeps missing and tampered nested XObject targets fail closed' => static function (
        TestRunner $t
    ) use ($formXObject, $pdfWithXObjects): void {
        $formContent = 'q 40 0 0 40 0 0 cm /Im0 Do Q';
        $pageContent = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (high text) Tj ET '
            . 'q 1 0 0 1 60 680 cm /Fm Do Q';
        $missing = $pdfWithXObjects($pageContent, '/Fm 5 0 R', [
            5 => $formXObject($formContent, '/Im0 99 0 R'),
        ]);
        $tampered = $pdfWithXObjects($pageContent, '/Fm 5 0 R', [
            5 => $formXObject($formContent, '/Im0 6 0 R'),
            6 => '<< /Type /XObject /Subtype /Bogus >>',
        ]);

        foreach ([$missing, $tampered] as $index => $pdf) {
            $visibility = (new PdfTextExtractor())->diagnostics($pdf)['textVisibility'];
            $t->same(1, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
            $t->same(1, $visibility['unresolvedReasonCounts']['later-paint-occlusion'] ?? null);
            $t->same($index === 0
                ? ['form-xobject-expansion-unresolved', 'later-paint-occlusion']
                : ['later-paint-occlusion'], $visibility['unresolvedReasons'] ?? null);
            $t->same(false, $visibility['complete'] ?? null);
        }

        $missingDiagnostics = (new PdfTextExtractor())->diagnostics($missing);
        $t->same(
            ['unresolved_xobject_reference'],
            array_column($missingDiagnostics['pageExtractionIssues'] ?? [], 'reason')
        );
    },

    'keeps a following unbounded shading paint fail closed after an exact nested image' => static function (
        TestRunner $t
    ) use ($formXObject, $imageXObject, $pdfWithXObjects): void {
        $formContent = 'q 40 0 0 40 0 0 cm /Im0 Do Q';
        $pageContent = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (high text) Tj ET '
            . 'q 1 0 0 1 300 300 cm /Fm Do Q /Sh0 sh';
        $pdf = $pdfWithXObjects($pageContent, '/Fm 5 0 R', [
            5 => $formXObject($formContent, '/Im0 6 0 R'),
            6 => $imageXObject(),
            8 => '<< /ShadingType 2 /ColorSpace /DeviceGray >>',
        ], '/Shading << /Sh0 8 0 R >>');
        $visibility = (new PdfTextExtractor())->diagnostics($pdf)['textVisibility'];

        $t->same(1, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(1, $visibility['unresolvedReasonCounts']['later-paint-occlusion'] ?? null);
        $t->same(['later-paint-occlusion'], $visibility['unresolvedReasons'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },
];
