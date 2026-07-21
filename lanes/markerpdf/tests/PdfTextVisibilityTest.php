<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfWithPage = static function (
    string $content,
    string $resources = '<< >>',
    string $catalogExtra = '',
    string $extraObjects = '',
    string $pageBoxes = '/MediaBox [0 0 612 792] /CropBox [0 0 612 792]'
): string {
    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R {$catalogExtra} >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R {$pageBoxes} /Resources {$resources} /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . $extraObjects
        . "%%EOF";
};

$extractedTexts = static function (PdfTextExtractor $extractor, string $pdf): array {
    return [
        'lines' => $extractor->extractTextLines($pdf),
        'runs' => $extractor->extractTextRuns($pdf),
        'positioned' => array_column($extractor->extractPositionedTextRuns($pdf), 'text'),
    ];
};

$widthOnlyTrueTypeFont = static function (): string {
    $head = str_repeat("\0", 54);
    $head = substr_replace($head, pack('N', 0x00010000), 0, 4);
    $head = substr_replace($head, pack('N', 0x5F0F3CF5), 12, 4);
    $head = substr_replace($head, pack('n', 1000), 18, 2);
    $head = substr_replace($head, pack('n', (-200) & 0xFFFF), 38, 2);
    $head = substr_replace($head, pack('n', 800), 42, 2);

    $hhea = str_repeat("\0", 36);
    $hhea = substr_replace($hhea, pack('n', 1), 34, 2);
    $tables = [
        'head' => $head,
        'hhea' => $hhea,
        'hmtx' => pack('nn', 500, 0),
        'maxp' => pack('Nn', 0x00010000, 1),
    ];
    ksort($tables, SORT_STRING);
    $directory = pack('Nnnnn', 0x00010000, count($tables), 0, 0, 0);
    $records = '';
    $payload = '';
    $offset = 12 + (count($tables) * 16);
    foreach ($tables as $tag => $table) {
        $padding = (4 - (strlen($payload) % 4)) % 4;
        $payload .= str_repeat("\0", $padding);
        $offset += $padding;
        $records .= $tag . pack('NNN', 0, $offset, strlen($table));
        $payload .= $table;
        $offset += strlen($table);
    }

    return $directory . $records . $payload;
};

$pdfWithWidthOnlyTrueType = static function (
    string $shownText,
    float $clipWidth,
    string $declaredWidths = '[200 300]',
    int $lastChar = 66,
    string $widthsEntry = '/Widths 8 0 R'
) use ($pdfWithPage, $widthOnlyTrueTypeFont): string {
    $fontProgram = $widthOnlyTrueTypeFont();
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\nbegincmap\n"
        . "1 begincodespacerange\n<00><FF>\nendcodespacerange\n"
        . "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
    $content = 'q 10 0 ' . $clipWidth . ' 30 re W n '
        . 'BT /F1 10 Tf 1 0 0 1 12 10 Tm (' . $shownText . ') Tj ET Q';
    $resources = '<< /Font << /F1 5 0 R >> >>';
    $extraObjects = "5 0 obj\n"
        . "<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+WidthOnly "
        . "/FirstChar 65 /LastChar {$lastChar} {$widthsEntry} "
        . "/FontDescriptor 6 0 R /ToUnicode 9 0 R >>\n"
        . "endobj\n"
        . "6 0 obj\n"
        . "<< /Type /FontDescriptor /FontName /ABCDEF+WidthOnly /Flags 32 "
        . "/FontBBox [0 -200 1000 800] /ItalicAngle 0 /Ascent 800 /Descent -200 "
        . "/CapHeight 700 /StemV 80 /FontFile2 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n"
        . $fontProgram . "\nendstream\nendobj\n"
        . "8 0 obj\n{$declaredWidths}\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n"
        . $toUnicode . "\nendstream\nendobj\n";

    return $pdfWithPage($content, $resources, '', $extraObjects);
};

return [
    'excludes definitely non-painting text rendering modes from visible extraction' => static function (
        TestRunner $t
    ) use ($pdfWithPage, $extractedTexts): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm '
            . '0 Tr (fill) Tj T* 1 Tr (stroke) Tj T* 2 Tr (fill-stroke) Tj T* '
            . '3 Tr (SECRET-INVISIBLE) Tj T* '
            . '4 Tr (fill-clip) Tj T* 5 Tr (stroke-clip) Tj T* 6 Tr (fill-stroke-clip) Tj T* '
            . '7 Tr (SECRET-CLIP-ONLY) Tj ET';
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithPage($content);
        $texts = $extractedTexts($extractor, $pdf);

        $expected = ['fill', 'stroke', 'fill-stroke', 'fill-clip', 'stroke-clip', 'fill-stroke-clip'];
        $t->same($expected, $texts['lines']);
        $t->same($expected, $texts['runs']);
        $t->same($expected, $texts['positioned']);

        $diagnostics = $extractor->diagnostics($pdf);
        $t->same(2, $diagnostics['textVisibility']['suppressedNonPaintingRuns'] ?? null);
        $t->same(0, $diagnostics['textVisibility']['unresolvedRuns'] ?? null);
        $t->contains('2 non-painting PDF text run(s)', implode("\n", $diagnostics['warnings']));
    },

    'restores text rendering mode through the graphics state stack' => static function (
        TestRunner $t
    ) use ($pdfWithPage, $extractedTexts): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm 0 Tr (before) Tj '
            . 'q 3 Tr 1 0 0 1 72 700 Tm (SECRET) Tj Q '
            . '1 0 0 1 72 680 Tm (after) Tj ET';
        $extractor = new PdfTextExtractor();

        foreach ($extractedTexts($extractor, $pdfWithPage($content)) as $texts) {
            $t->same(['before', 'after'], $texts);
        }
    },

    'does not promote ActualText attached only to non-painting glyphs into visible text' => static function (
        TestRunner $t
    ) use ($pdfWithPage, $extractedTexts): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm 3 Tr '
            . '/Span << /ActualText (SECRET-ACCESSIBLE-ONLY) >> BDC (hidden glyphs) Tj EMC '
            . '0 Tr /Span << /ActualText (Visible replacement) >> BDC (visible glyphs) Tj EMC T* '
            . '3 Tr /Span << /ActualText (Mixed replacement) >> BDC '
            . '(hidden portion) Tj 0 Tr (visible portion) Tj EMC ET';
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithPage($content);

        foreach ($extractedTexts($extractor, $pdf) as $texts) {
            $t->same(['Visible replacement', 'Mixed replacement'], $texts);
        }

        $diagnostics = $extractor->diagnostics($pdf);
        $t->same(1, $diagnostics['textVisibility']['suppressedNonPaintingActualTextRuns'] ?? null);
        $t->same(1, $diagnostics['textVisibility']['suppressedAccessibilityReplacementRuns'] ?? null);
        $t->same(2, $diagnostics['textVisibility']['visibleOutputRuns'] ?? null);
    },

    'excludes text when the active ExtGState proves its painting alpha is zero' => static function (
        TestRunner $t
    ) use ($pdfWithPage, $extractedTexts): void {
        $resources = '<< /ExtGState << '
            . '/ZeroFill << /ca 0 /CA 1 >> '
            . '/ZeroStroke << /ca 1 /CA 0 >> '
            . '/BothZero << /ca 0 /CA 0 >> '
            . '/Opaque << /ca 1 /CA 1 >> '
            . '>> >>';
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm '
            . '/ZeroFill gs 0 Tr (SECRET-FILL) Tj T* 1 Tr (visible-stroke) Tj T* '
            . '/ZeroStroke gs 1 Tr (SECRET-STROKE) Tj T* 0 Tr (visible-fill) Tj T* '
            . '/BothZero gs 2 Tr (SECRET-BOTH) Tj T* '
            . '/Opaque gs 2 Tr (visible-both) Tj ET';
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithPage($content, $resources);
        $expected = ['visible-stroke', 'visible-fill', 'visible-both'];

        foreach ($extractedTexts($extractor, $pdf) as $texts) {
            $t->same($expected, $texts);
        }

        $visibility = $extractor->diagnostics($pdf)['textVisibility'];
        $t->same(3, $visibility['suppressedZeroAlphaRuns'] ?? null);
        $t->same(0, $visibility['unresolvedRuns'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
    },

    'retains but diagnoses text whose ExtGState visibility cannot be proven' => static function (
        TestRunner $t
    ) use ($pdfWithPage, $extractedTexts): void {
        $resources = '<< /ExtGState << /Masked << /ca 1 /CA 1 /SMask << /S /Alpha >> >> >> >>';
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm '
            . '/Masked gs (masked-but-unresolved) Tj T* /Missing gs (missing-state) Tj ET';
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithPage($content, $resources);

        foreach ($extractedTexts($extractor, $pdf) as $texts) {
            $t->same(['masked-but-unresolved', 'missing-state'], $texts);
        }

        $visibility = $extractor->diagnostics($pdf)['textVisibility'];
        $t->same(false, $visibility['complete'] ?? null);
        $t->same(2, $visibility['unresolvedRuns'] ?? null);
        $t->true(in_array('ext-gstate-opacity-or-soft-mask', $visibility['unresolvedReasons'] ?? [], true));
    },

    'honors default-hidden optional content and retains unresolved memberships for review' => static function (
        TestRunner $t
    ) use ($pdfWithPage, $extractedTexts): void {
        $catalogExtra = '/OCProperties << /OCGs [5 0 R 6 0 R] '
            . '/D << /BaseState /ON /OFF [5 0 R] /ON [6 0 R] >> >>';
        // No whitespace is required between a PDF name and a dictionary.
        $resources = '<</Properties<</Hidden 5 0 R /Shown 6 0 R /Membership 7 0 R>>>>';
        $extraObjects = "5 0 obj\n<< /Type /OCG /Name (Hidden layer) >>\nendobj\n"
            . "6 0 obj\n<< /Type /OCG /Name (Shown layer) >>\nendobj\n"
            . "7 0 obj\n<< /Type /OCMD /OCGs [5 0 R 6 0 R] /P /AnyOn >>\nendobj\n";
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm '
            . '/OC /Hidden BDC (SECRET-HIDDEN-LAYER) Tj EMC T* '
            . '/OC /Hidden BDC /Span << /ActualText (SECRET-HIDDEN-ACTUALTEXT) >> BDC (glyphs) Tj EMC EMC T* '
            . '/OC /Shown BDC (shown-layer) Tj EMC T* '
            . '/OC /Membership BDC (membership-unresolved) Tj EMC T* '
            . '/OC /Missing BDC (missing-property-unresolved) Tj EMC ET';
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithPage($content, $resources, $catalogExtra, $extraObjects);
        $expected = ['shown-layer', 'membership-unresolved', 'missing-property-unresolved'];

        foreach ($extractedTexts($extractor, $pdf) as $texts) {
            $t->same($expected, $texts);
        }

        $visibility = $extractor->diagnostics($pdf)['textVisibility'];
        $t->same(2, $visibility['suppressedOptionalContentRuns'] ?? null);
        $t->same(2, $visibility['unresolvedRuns'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
        $t->true(in_array('optional-content-state', $visibility['unresolvedReasons'] ?? [], true));
    },

    'excludes text whose conservative bounds are wholly outside the effective CropBox' => static function (
        TestRunner $t
    ) use ($pdfWithPage, $extractedTexts): void {
        $content = 'BT /F1 10 Tf '
            . '1 0 0 1 20 50 Tm (inside) Tj '
            . '1 0 0 1 150 50 Tm (SECRET-RIGHT) Tj '
            . '1 0 0 1 20 150 Tm /Span << /ActualText (SECRET-ABOVE-ACTUALTEXT) >> BDC (glyphs) Tj EMC '
            . '1 0 0 1 98 30 Tm (partial) Tj ET';
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithPage(
            $content,
            '<< >>',
            '',
            '',
            '/MediaBox [0 0 200 200] /CropBox [0 0 100 100]'
        );

        foreach ($extractedTexts($extractor, $pdf) as $texts) {
            $t->same(['inside', 'partial'], $texts);
        }

        $visibility = $extractor->diagnostics($pdf)['textVisibility'];
        $t->same(2, $visibility['suppressedOutsidePageRuns'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
    },

    'recovers a proven terminal overflow suffix only in logical line text' => static function (
        TestRunner $t
    ) use ($pdfWithPage): void {
        $resources = '<< /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >>';
        $content = 'BT /F1 10 Tf '
            . '1 0 0 1 340 70 Tm [(visible ) (boundary ) (overflow ) (tail)] TJ '
            . '1 0 0 1 10 58 Tm (continues inside) Tj ET';
        $pdf = $pdfWithPage(
            $content,
            $resources,
            '',
            '',
            '/MediaBox [0 0 400 100] /CropBox [0 0 400 100]'
        );
        $extractor = new PdfTextExtractor();

        $t->same(
            ['visible boundary overflow tail', 'continues inside'],
            $extractor->extractTextLines($pdf)
        );
        $visibleRuns = implode('', array_map(
            static fn (array|string $run): string => is_array($run) ? $run['text'] : $run,
            $extractor->extractTextRuns($pdf)
        ));
        $positionedRuns = implode('', array_column($extractor->extractPositionedTextRuns($pdf), 'text'));
        $t->true(!str_contains($visibleRuns, 'overflow'));
        $t->true(!str_contains($positionedRuns, 'overflow'));

        $visibility = $extractor->diagnostics($pdf)['textVisibility'];
        $t->same(2, $visibility['suppressedOutsidePageRuns'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
    },

    'fails closed when overflow lacks same-font contiguous line continuation proof' => static function (
        TestRunner $t
    ) use ($pdfWithPage): void {
        $resources = '<< /Font << '
            . '/F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> '
            . '/F2 << /Type /Font /Subtype /Type1 /BaseFont /Times-Roman >> '
            . '>> >>';
        $differentFontContent = 'BT /F1 10 Tf '
            . '1 0 0 1 340 70 Tm [(visible ) (boundary ) (SECRET-FONT)] TJ '
            . '/F2 10 Tf 1 0 0 1 10 58 Tm (different font) Tj ET';
        $discontinuousContent = 'BT /F1 10 Tf '
            . '1 0 0 1 340 70 Tm [(visible ) (boundary ) -5000 (SECRET-GAP)] TJ '
            . '1 0 0 1 10 58 Tm (matching continuation) Tj ET';

        foreach ([$differentFontContent, $discontinuousContent] as $content) {
            $pdf = $pdfWithPage(
                $content,
                $resources,
                '',
                '',
                '/MediaBox [0 0 400 100] /CropBox [0 0 400 100]'
            );
            $lines = implode("\n", (new PdfTextExtractor())->extractTextLines($pdf));
            $t->true(!str_contains($lines, 'SECRET-'));
        }
    },

    'clips rotated text by its oriented ink bounds at the effective CropBox' => static function (
        TestRunner $t
    ) use ($pdfWithPage, $extractedTexts): void {
        $content = 'BT /F1 10 Tf '
            . '0 1 -1 0 20 -10 Tm (x) Tj '
            . '0 1 -1 0 40 -2 Tm (visible-rotated) Tj ET';
        $resources = '<< /Font << /F1 5 0 R >> >>';
        $font = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithPage(
            $content,
            $resources,
            '',
            $font,
            '/MediaBox [0 0 200 200] /CropBox [0 0 100 100]'
        );

        foreach ($extractedTexts($extractor, $pdf) as $texts) {
            $t->same(['visible-rotated'], $texts);
        }

        $visibility = $extractor->diagnostics($pdf)['textVisibility'];
        $t->same(1, $visibility['suppressedOutsidePageRuns'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
    },

    'keeps only proven off-page operations out of later paint risk' => static function (
        TestRunner $t
    ) use ($pdfWithPage, $extractedTexts): void {
        $pageBoxes = '/MediaBox [0 0 200 200] /CropBox [0 0 100 100]';
        $outside = 'BT /F1 10 Tf 1 0 0 1 20 -30 Tm (outside-before-fill) Tj ET '
            . '0 0 100 100 re f';
        $partial = 'BT /F1 10 Tf 1 0 0 1 20 1 Tm (partial-before-fill) Tj ET '
            . '0 0 100 100 re f';
        $unknown = 'BT /F1 10 Tf (unknown-position-before-fill) Tj ET '
            . '0 0 100 100 re f';
        $risen = 'BT /F1 10 Tf 50 Ts 1 0 0 1 20 -30 Tm (risen-into-page) Tj ET '
            . '0 0 100 100 re f';
        $stroked = '80 w BT /F1 10 Tf 1 Tr 1 0 0 1 120 50 Tm (stroke-into-page) Tj ET '
            . '0 0 100 100 re f';

        $outsideVisibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithPage($outside, '<< >>', '', '', $pageBoxes)
        )['textVisibility'];
        $partialVisibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithPage($partial, '<< >>', '', '', $pageBoxes)
        )['textVisibility'];
        $unknownVisibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithPage($unknown, '<< >>', '', '', $pageBoxes)
        )['textVisibility'];
        $risenPdf = $pdfWithPage($risen, '<< >>', '', '', $pageBoxes);
        $risenExtractor = new PdfTextExtractor();
        $risenVisibility = $risenExtractor->diagnostics($risenPdf)['textVisibility'];
        $strokedPdf = $pdfWithPage($stroked, '<< >>', '', '', $pageBoxes);
        $strokedExtractor = new PdfTextExtractor();
        $strokedVisibility = $strokedExtractor->diagnostics($strokedPdf)['textVisibility'];

        $t->same(1, $outsideVisibility['suppressedOutsidePageRuns'] ?? null);
        $t->same(0, $outsideVisibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(true, $outsideVisibility['complete'] ?? null);
        $t->same(1, $partialVisibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(false, $partialVisibility['complete'] ?? null);
        $t->same(1, $unknownVisibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(false, $unknownVisibility['complete'] ?? null);
        foreach ($extractedTexts($risenExtractor, $risenPdf) as $texts) {
            $t->same(['risen-into-page'], $texts);
        }
        $t->same(0, $risenVisibility['suppressedOutsidePageRuns'] ?? null);
        $t->same(1, $risenVisibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(false, $risenVisibility['complete'] ?? null);
        foreach ($extractedTexts($strokedExtractor, $strokedPdf) as $texts) {
            $t->same(['stroke-into-page'], $texts);
        }
        $t->same(0, $strokedVisibility['suppressedOutsidePageRuns'] ?? null);
        $t->same(1, $strokedVisibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(false, $strokedVisibility['complete'] ?? null);
    },

    'clips individual TJ segments at the CropBox without suppressing a visible duplicate' => static function (
        TestRunner $t
    ) use ($pdfWithPage, $extractedTexts): void {
        $content = 'BT /F1 10 Tf 1 0 0 1 20 70 Tm (for) Tj ET '
            . 'BT /F1 10 Tf 1 0 0 1 60 50 Tm [(files) -6000 (for)] TJ ET';
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithPage(
            $content,
            '<< >>',
            '',
            '',
            '/MediaBox [0 0 200 200] /CropBox [0 0 100 100]'
        );

        foreach ($extractedTexts($extractor, $pdf) as $texts) {
            $t->same(['for', 'files'], $texts);
        }

        $visibility = $extractor->diagnostics($pdf)['textVisibility'];
        $t->same(1, $visibility['suppressedOutsidePageRuns'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
    },

    'keeps Form visibility resources scoped when their names collide with page resources' => static function (
        TestRunner $t
    ) use ($pdfWithPage, $extractedTexts): void {
        $formContent = '/Shared gs BT /F1 12 Tf 1 0 0 1 20 80 Tm (SECRET-FORM-ALPHA) Tj ET '
            . '/Opaque gs /OC /Layer BDC BT /F1 12 Tf 1 0 0 1 20 60 Tm '
            . '/Span << /ActualText (SECRET-FORM-OCG-ACTUALTEXT) >> BDC (hidden glyphs) Tj EMC ET EMC '
            . 'BT /F1 12 Tf 1 0 0 1 20 40 Tm (form-visible) Tj ET';
        $formObject = "5 0 obj\n"
            . '<< /Type /XObject /Subtype /Form /BBox [0 0 200 100] '
            . '/Resources << /ExtGState << '
            . '/Shared << /ca 0 /CA 0 >> /Opaque << /ca 1 /CA 1 >> '
            . '>> /Properties << /Layer 6 0 R >> >> '
            . '/Length ' . strlen($formContent) . ">>\nstream\n{$formContent}\nendstream\nendobj\n";
        $extraObjects = $formObject
            . "6 0 obj\n<< /Type /OCG /Name (Form hidden layer) >>\nendobj\n"
            . "7 0 obj\n<< /Type /OCG /Name (Page shown layer) >>\nendobj\n";
        $catalogExtra = '/OCProperties << /OCGs [6 0 R 7 0 R] '
            . '/D << /BaseState /ON /OFF [6 0 R] /ON [7 0 R] >> >>';
        $resources = '<< /XObject << /Fm 5 0 R >> '
            . '/ExtGState << /Shared << /ca 1 /CA 1 >> >> '
            . '/Properties << /Layer 7 0 R >> >>';
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm (page-visible) Tj ET '
            . '/OC /Layer BDC BT /F1 12 Tf 1 0 0 1 72 700 Tm (page-layer-visible) Tj ET EMC '
            . '/Fm Do';
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithPage($content, $resources, $catalogExtra, $extraObjects);

        foreach ($extractedTexts($extractor, $pdf) as $texts) {
            $t->same(['page-visible', 'page-layer-visible', 'form-visible'], $texts);
        }

        $visibility = $extractor->diagnostics($pdf)['textVisibility'];
        $t->same(3, $visibility['visibleOutputRuns'] ?? null);
        $t->same(1, $visibility['suppressedZeroAlphaRuns'] ?? null);
        $t->same(1, $visibility['suppressedOptionalContentRuns'] ?? null);
        $t->same(1, $visibility['suppressedAccessibilityReplacementRuns'] ?? null);
        $t->same(0, $visibility['unresolvedClippingRuns'] ?? null);
        $t->same(0, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
    },

    'keeps disjoint later image Form and filled rectangle paints out of occlusion risk' => static function (
        TestRunner $t
    ) use ($pdfWithPage, $extractedTexts): void {
        $formContent = '0 0 40 40 re f';
        $extraObjects = "5 0 obj\n"
            . "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray"
            . " /BitsPerComponent 8 /Length 1 >>\nstream\n\x80\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 40 40]"
            . ' /Length ' . strlen($formContent) . ">>\nstream\n{$formContent}\nendstream\nendobj\n";
        $resources = '<< /XObject << /Im 5 0 R /Fm 6 0 R >> >>';
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm (high text) Tj ET '
            . 'q 100 0 0 100 72 580 cm /Im Do Q '
            . 'q 1 0 0 1 400 200 cm /Fm Do Q '
            . '400 300 50 50 re f '
            . 'BT /F1 12 Tf 1 0 0 1 72 540 Tm (later low text) Tj ET';
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithPage($content, $resources, '', $extraObjects);

        foreach ($extractedTexts($extractor, $pdf) as $texts) {
            $t->same(['high text', 'later low text'], $texts);
        }
        $visibility = $extractor->diagnostics($pdf)['textVisibility'];
        $t->same(0, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
    },

    'marks only text intersected by a later opaque image or filled rectangle at risk' => static function (
        TestRunner $t
    ) use ($pdfWithPage): void {
        $extraObjects = "5 0 obj\n"
            . "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray"
            . " /BitsPerComponent 8 /Length 1 >>\nstream\n\x80\nendstream\nendobj\n";
        $resources = '<< /XObject << /Im 5 0 R >> >>';
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm (covered text) Tj ET '
            . '60 700 180 50 re f '
            . 'q 180 0 0 60 60 690 cm /Im Do Q '
            . 'BT /F1 12 Tf 1 0 0 1 72 540 Tm (uncovered later text) Tj ET';
        $extractor = new PdfTextExtractor();
        $visibility = $extractor->diagnostics($pdfWithPage($content, $resources, '', $extraObjects))['textVisibility'];

        $t->same(1, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(1, $visibility['unresolvedReasonCounts']['later-paint-occlusion'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },

    'marks unresolved clipping and each later-paint occlusion interval incomplete deterministically' => static function (
        TestRunner $t
    ) use ($pdfWithPage, $extractedTexts): void {
        $content = '0 0 100 100 re W n '
            . 'BT /F1 12 Tf 1 0 0 1 20 80 Tm (before-first-paint) Tj ET '
            . '0 0 100 100 re f '
            . 'BT /F1 12 Tf 1 0 0 1 20 60 Tm (before-second-paint) Tj ET '
            . '0 0 100 100 re f';
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithPage($content);

        foreach ($extractedTexts($extractor, $pdf) as $texts) {
            $t->same(['before-first-paint', 'before-second-paint'], $texts);
        }

        $diagnostics = $extractor->diagnostics($pdf);
        $visibility = $diagnostics['textVisibility'];
        $t->same(2, $visibility['visibleOutputRuns'] ?? null);
        $t->same(2, $visibility['unresolvedRuns'] ?? null);
        $t->same(2, $visibility['unresolvedClippingRuns'] ?? null);
        $t->same(2, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(2, $visibility['unresolvedReasonCounts']['later-paint-occlusion'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
        $t->contains('may be occluded by later painting operations', implode("\n", $diagnostics['warnings']));
    },

    'proves scoped axis-aligned rectangle clips only for fully contained text bounds' => static function (
        TestRunner $t
    ) use ($pdfWithPage): void {
        $resources = '<< /Font << /F1 << /Type /Font /Subtype /Type1 '
            . '/BaseFont /Helvetica /Encoding /WinAnsiEncoding >> >> >>';
        $content = 'q 0 0 200 100 re W n '
            . 'BT /F1 10 Tf 1 0 0 1 20 50 Tm (inside clip) Tj ET Q '
            . 'BT /F1 10 Tf 1 0 0 1 300 500 Tm (after restore) Tj ET';
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithPage($content, $resources)
        )['textVisibility'];

        $t->same(0, $visibility['unresolvedClippingRuns'] ?? null);
        $t->same([], $visibility['unresolvedReasons'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
    },

    'uses declared advances without inventing an encoding for embedded TrueType clip evidence' => static function (
        TestRunner $t
    ) use ($pdfWithWidthOnlyTrueType): void {
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithWidthOnlyTrueType('AB', 7.0);
        $positioned = $extractor->extractPositionedTextRuns($pdf);
        $visibility = $extractor->diagnostics($pdf)['textVisibility'];

        $t->same(['AB'], $extractor->extractTextRuns($pdf));
        $t->same(17.0, $positioned[0]['textX2'] ?? null);
        $t->same(0, $visibility['unresolvedClippingRuns'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
    },

    'keeps an undeclared width in a width-only TrueType font fail closed' => static function (
        TestRunner $t
    ) use ($pdfWithWidthOnlyTrueType): void {
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithWidthOnlyTrueType('AC', 100.0)
        )['textVisibility'];

        $t->same(1, $visibility['unresolvedClippingRuns'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },

    'keeps malformed width-only TrueType array entries fail closed without shifting indexes' => static function (
        TestRunner $t
    ) use ($pdfWithWidthOnlyTrueType): void {
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithWidthOnlyTrueType('AB', 7.0, '[200 malformed 300]', 67)
        )['textVisibility'];

        $t->same(1, $visibility['unresolvedClippingRuns'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },

    'keeps a mismatched width-only TrueType array count fail closed' => static function (
        TestRunner $t
    ) use ($pdfWithWidthOnlyTrueType): void {
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithWidthOnlyTrueType('AB', 7.0, '[200 300]', 67)
        )['textVisibility'];

        $t->same(1, $visibility['unresolvedClippingRuns'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },

    'ignores a commented width array before the one top-level width declaration' => static function (
        TestRunner $t
    ) use ($pdfWithWidthOnlyTrueType): void {
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithWidthOnlyTrueType(
            'AB',
            7.0,
            '[200 300]',
            66,
            "% /Widths [200 300]\n/Widths [2000 3000]"
        );
        $positioned = $extractor->extractPositionedTextRuns($pdf);
        $visibility = $extractor->diagnostics($pdf)['textVisibility'];

        $t->same(62.0, $positioned[0]['textX2'] ?? null);
        $t->same(1, $visibility['unresolvedClippingRuns'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },

    'rejects duplicate top-level width declarations as width-only evidence' => static function (
        TestRunner $t
    ) use ($pdfWithWidthOnlyTrueType): void {
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithWidthOnlyTrueType(
            'AB',
            7.0,
            '[200 300]',
            66,
            '/Widths [200 300] /Widths [2000 3000]'
        );
        $positioned = $extractor->extractPositionedTextRuns($pdf);
        $visibility = $extractor->diagnostics($pdf)['textVisibility'];

        $t->same(22.0, $positioned[0]['textX2'] ?? null);
        $t->same(1, $visibility['unresolvedClippingRuns'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },

    'proves TraceMonkey chart text inside declared-width rectangle clips' => static function (
        TestRunner $t
    ): void {
        $path = dirname(__DIR__, 3)
            . '/pandoc-showcase/samples/pdf-tracemonkey-tracemonkey.pdf';
        $pdf = file_get_contents($path);
        $t->true(is_string($pdf) && $pdf !== '', 'Expected the TraceMonkey PDF fixture.');
        if (!is_string($pdf) || $pdf === '') {
            return;
        }

        $visibility = (new PdfTextExtractor([
            'pdfStartPage' => 10,
            'pdfMaxPages' => 4,
        ]))->diagnostics($pdf)['textVisibility'];
        $summaries = [];
        foreach ($visibility['pages'] ?? [] as $summary) {
            if (is_array($summary) && is_int($summary['page'] ?? null)) {
                $summaries[$summary['page']] = $summary;
            }
        }
        foreach ([10, 11, 13] as $page) {
            $t->same(
                0,
                $summaries[$page]['unresolvedClippingRuns'] ?? null,
                'Expected exact declared font widths to prove chart text on page ' . $page . '.'
            );
            $t->true(
                !isset($summaries[$page]['unresolvedReasonCounts']['unresolved-clipping-path']),
                'Page ' . $page . ' retained a clipping-path uncertainty.'
            );
        }
    },

    'keeps partial arbitrary and skewed clipping paths fail closed' => static function (
        TestRunner $t
    ) use ($pdfWithPage): void {
        $resources = '<< /Font << /F1 << /Type /Font /Subtype /Type1 '
            . '/BaseFont /Helvetica /Encoding /WinAnsiEncoding >> >> >>';
        $arbitrary = '0 0 m 200 0 l 100 100 l h W n '
            . 'BT /F1 10 Tf 1 0 0 1 40 50 Tm (arbitrary path) Tj ET';
        $skewed = 'q 1 .2 0 1 0 0 cm 0 0 200 100 re W n '
            . 'BT /F1 10 Tf 1 0 0 1 40 50 Tm (skewed rectangle) Tj ET Q';

        foreach ([$arbitrary, $skewed] as $content) {
            $visibility = (new PdfTextExtractor())->diagnostics(
                $pdfWithPage($content, $resources)
            )['textVisibility'];
            $t->same(1, $visibility['unresolvedClippingRuns'] ?? null);
            $t->same(false, $visibility['complete'] ?? null);
        }
    },

    'uses arbitrary clip outer bounds to localize later shading risk' => static function (
        TestRunner $t
    ) use ($pdfWithPage): void {
        $resources = '<< /Font << /F1 << /Type /Font /Subtype /Type1 '
            . '/BaseFont /Helvetica /Encoding /WinAnsiEncoding >> >> '
            . '/Shading << /Sh0 5 0 R >> >>';
        $extraObjects = "5 0 obj\n<< /ShadingType 2 /ColorSpace /DeviceGray /Coords [0 0 20 0] "
            . "/Function << /FunctionType 2 /Domain [0 1] /C0 [0] /C1 [1] /N 1 >> "
            . "/Extend [true true] >>\nendobj\n";
        $clipAndShading = '0 0 m 20 0 l 10 20 l h W n /Sh0 sh';
        $extractor = new PdfTextExtractor();

        $disjoint = 'BT /F1 10 Tf 1 0 0 1 300 500 Tm (distant target) Tj ET '
            . $clipAndShading;
        $disjointVisibility = $extractor->diagnostics(
            $pdfWithPage($disjoint, $resources, '', $extraObjects)
        )['textVisibility'];
        $t->same(0, $disjointVisibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(true, $disjointVisibility['complete'] ?? null);

        $overlapping = 'BT /F1 10 Tf 1 0 0 1 5 5 Tm (near target) Tj ET '
            . $clipAndShading;
        $overlappingVisibility = $extractor->diagnostics(
            $pdfWithPage($overlapping, $resources, '', $extraObjects)
        )['textVisibility'];
        $t->same(1, $overlappingVisibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(1, $overlappingVisibility['unresolvedReasonCounts']['later-paint-occlusion'] ?? null);
        $t->same(false, $overlappingVisibility['complete'] ?? null);

        $unknownTransform = 'BT /F1 10 Tf 1 0 0 1 5 5 Tm (target) Tj ET '
            . '1 0 0 1 1000001 0 cm 300 300 m 320 300 l 310 320 l h W n /Sh0 sh';
        $unknownTransformVisibility = $extractor->diagnostics(
            $pdfWithPage($unknownTransform, $resources, '', $extraObjects)
        )['textVisibility'];
        $t->same(1, $unknownTransformVisibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(false, $unknownTransformVisibility['complete'] ?? null);
    },

    'keeps path geometry unknown after a rejected CTM branch' => static function (
        TestRunner $t
    ) use ($pdfWithPage): void {
        $resources = '<< /Font << /F1 << /Type /Font /Subtype /Type1 '
            . '/BaseFont /Helvetica /Encoding /WinAnsiEncoding >> >> '
            . '/Shading << /Sh0 5 0 R >> >>';
        $extraObjects = "5 0 obj\n<< /ShadingType 2 /ColorSpace /DeviceGray /Coords [0 0 20 0] "
            . "/Function << /FunctionType 2 /Domain [0 1] /C0 [0] /C1 [1] /N 1 >> "
            . "/Extend [true true] >>\nendobj\n";
        $prefix = 'BT /F1 12 Tf 1 0 0 1 300 500 Tm (target) Tj ET ';
        $streams = [
            $prefix
                . 'q 1 0 0 1 1000001 0 cm '
                . '-999721 490 m -999621 490 l -999621 530 l -999721 530 l h '
                . 'Q W n /Sh0 sh',
            $prefix
                . '1 0 0 1 1000001 0 cm -999721 490 100 40 re f',
        ];

        foreach ($streams as $content) {
            $diagnostics = (new PdfTextExtractor())->diagnostics(
                $pdfWithPage($content, $resources, '', $extraObjects)
            );
            $t->same(1, $diagnostics['textVisibility']['unresolvedOcclusionRiskRuns'] ?? null);
            $t->same(false, $diagnostics['textVisibility']['complete'] ?? null);
            $t->true(in_array(
                'invalid_content_transform',
                array_column($diagnostics['pageExtractionIssues'] ?? [], 'reason'),
                true
            ));
        }
    },

    'applies a rectangle clip after a painting path ends it' => static function (
        TestRunner $t
    ) use ($pdfWithPage): void {
        $resources = '<< /Font << /F1 << /Type /Font /Subtype /Type1 '
            . '/BaseFont /Helvetica /Encoding /WinAnsiEncoding >> >> >>';
        $content = '0 0 200 100 re W f '
            . 'BT /F1 10 Tf 1 0 0 1 20 50 Tm (paint-ended clip) Tj ET';
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithPage($content, $resources)
        )['textVisibility'];

        $t->same(0, $visibility['unresolvedClippingRuns'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
    },

    'binds multi-fragment TJ geometry to its one text-show operator' => static function (
        TestRunner $t
    ) use ($pdfWithPage): void {
        $resources = '<< /Font << /F1 << /Type /Font /Subtype /Type1 '
            . '/BaseFont /Helvetica /Encoding /WinAnsiEncoding >> >> >>';
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm '
            . '[(first-a) -3000 (first-b)] TJ '
            . '1 0 0 1 72 620 Tm (target) Tj ET '
            . '60 610 120 40 re f';
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithPage($content, $resources)
        )['textVisibility'];

        $t->same(1, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(1, $visibility['unresolvedReasonCounts']['later-paint-occlusion'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },

    'excludes proven trailing-space advances but keeps a true stroke crossing unresolved' => static function (
        TestRunner $t
    ) use ($pdfWithPage): void {
        $resources = '<< /Font << /F1 5 0 R >> >>';
        $extraObjects = "5 0 obj\n<< /Type /Font /Subtype /Type1 "
            . "/BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";
        $content = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (ABC ) Tj ET '
            . '0.5 w 0 J 98 690 m 98 716 l S '
            . 'BT /F1 12 Tf 1 0 0 1 72 650 Tm (ABC) Tj ET '
            . '96.8 640 m 96.8 666 l S';
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithPage($content, $resources, '', $extraObjects)
        )['textVisibility'];

        $t->same(1, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(1, $visibility['unresolvedReasonCounts']['later-paint-occlusion'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },

    'proves an oriented text parallelogram disjoint from a false-overlap cubic stroke box' => static function (
        TestRunner $t
    ) use ($pdfWithPage): void {
        $resources = '<< /Font << /F1 << /Type /Font /Subtype /Type1 '
            . '/BaseFont /Helvetica /Encoding /WinAnsiEncoding >> >> >>';
        // This is the Muir page-four geometry reduced to one glyph and one
        // stroke. Their axis-aligned boxes overlap, but the stroked curve and
        // the oriented conservative text parallelogram are disjoint.
        $content = 'BT /F1 7.889 Tf '
            . '.6847077 -.7288178 .7288178 .6847077 297.0434 529.6103 Tm (T) Tj ET '
            . 'q 1.914 w 1 0 0 1 288.3212 533.3932 cm '
            . '0 0 m 18.445 -19.661 22.803 -21.964 v S Q';
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithPage($content, $resources)
        )['textVisibility'];

        $t->same(0, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same([], $visibility['unresolvedReasons'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
    },

    'keeps a truly crossing cubic stroke fail closed at the subdivision bound' => static function (
        TestRunner $t
    ) use ($pdfWithPage): void {
        $resources = '<< /Font << /F1 << /Type /Font /Subtype /Type1 '
            . '/BaseFont /Helvetica /Encoding /WinAnsiEncoding >> >> >>';
        $content = 'BT /F1 12 Tf 1 0 0 1 100 100 Tm (ABC) Tj ET '
            . '2 w 100 105 m 110 105 130 105 150 105 c S';
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithPage($content, $resources)
        )['textVisibility'];

        $t->same(1, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(1, $visibility['unresolvedReasonCounts']['later-paint-occlusion'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },

    'keeps a crossing cubic fail closed after sub-nanounit path CTM drift' => static function (
        TestRunner $t
    ) use ($pdfWithPage): void {
        $resources = '<< /Font << /F1 << /Type /Font /Subtype /Type1 '
            . '/BaseFont /Helvetica /Encoding /WinAnsiEncoding >> >> >>';
        $content = 'BT /F1 12 Tf 1 0 0 1 100 100 Tm (ABC) Tj ET '
            . 'q 0.0000000001 0 0 0.0000000001 0 0 cm '
            . '1000000000 w 900000000000 1050000000000 m '
            . '1000000000000 1050000000000 1400000000000 1050000000000 '
            . '1500000000000 1050000000000 c '
            . '10 0 0 10 0 0 cm S Q';
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithPage($content, $resources)
        )['textVisibility'];

        $t->same(1, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(1, $visibility['unresolvedReasonCounts']['later-paint-occlusion'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },

    'bounds transformed line curve and rectangle strokes with scoped graphics state' => static function (
        TestRunner $t
    ) use ($pdfWithPage): void {
        $resources = '<< /Font << /F1 << /Type /Font /Subtype /Type1 '
            . '/BaseFont /Helvetica /Encoding /WinAnsiEncoding >> >> >>';
        $content = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (ABC) Tj ET '
            . 'q 2 .5 0 3 300 100 cm .5 w 2 J 1 j 3 M '
            . '0 0 m 20 10 l S '
            . '0 0 m 10 40 20 -40 30 0 c S '
            . '0 0 m 10 40 30 0 v S '
            . '0 0 m 10 40 30 0 y S '
            . '0 0 20 10 re S Q '
            . 'q 100 w 2 J 2 j 20 M Q '
            . '110 690 m 110 710 l S';
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithPage($content, $resources)
        )['textVisibility'];

        $t->same(0, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same([], $visibility['unresolvedReasons'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
    },

    'applies ExtGState LW LC LJ and ML to finite stroke bounds' => static function (
        TestRunner $t
    ) use ($pdfWithPage): void {
        $resources = '<< /Font << /F1 << /Type /Font /Subtype /Type1 '
            . '/BaseFont /Helvetica /Encoding /WinAnsiEncoding >> >> '
            . '/ExtGState << /ThinSquare << /ca 1 /CA 1 /LW .5 /LC 2 /LJ 0 /ML 2 >> >> >>';
        $content = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (ABC ) Tj ET '
            . '100 w 0 J 2 j 20 M /ThinSquare gs '
            . '98 690 m 98 716 l S';
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithPage($content, $resources)
        )['textVisibility'];

        $t->same(0, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same([], $visibility['unresolvedReasons'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
    },

    'carries later-paint visibility across ordered Contents members' => static function (TestRunner $t): void {
        $firstContent = 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (ABC) Tj ET ';
        $secondContent = '.5 w 96.8 690 m 96.8 716 l S';
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /CropBox [0 0 612 792] "
            . "/Resources << /Font << /F1 4 0 R >> >> /Contents [5 0 R 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($firstContent) . " >>\nstream\n{$firstContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($secondContent) . " >>\nstream\n{$secondContent}\nendstream\nendobj\n%%EOF";
        $visibility = (new PdfTextExtractor())->diagnostics($pdf)['textVisibility'];

        $t->same(1, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(1, $visibility['unresolvedReasonCounts']['later-paint-occlusion'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },

    'keeps the raw Muir page-four visibility diagnostic complete' => static function (
        TestRunner $t
    ): void {
        $path = dirname(__DIR__, 3)
            . '/pandoc-showcase/samples/pdf-muir-beach-brochure-muir-beach-brochure.pdf';
        $pdf = is_file($path) ? file_get_contents($path) : false;
        $t->true(is_string($pdf) && $pdf !== '', 'Expected the Muir brochure fixture.');
        if (!is_string($pdf) || $pdf === '') {
            return;
        }

        $visibility = (new PdfTextExtractor())->diagnostics($pdf)['textVisibility'];
        $pageFour = null;
        foreach ($visibility['pages'] ?? [] as $pageSummary) {
            if (($pageSummary['page'] ?? null) === 4) {
                $pageFour = $pageSummary;
                break;
            }
        }

        $t->same(0, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
        $t->same(0, $pageFour['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(true, $pageFour['complete'] ?? null);
    },
];
