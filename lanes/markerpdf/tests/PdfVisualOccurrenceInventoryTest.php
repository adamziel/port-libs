<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\NativePdfFactsProvider;
use PortLibs\MarkerPDF\PdfTextExtractor;

/** @param array<int,string> $objects */
$visualInventoryPdf = static function (array $objects): string {
    ksort($objects, SORT_NUMERIC);
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $number => $body) {
        $offsets[$number] = strlen($pdf);
        $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $size = max(array_keys($objects)) + 1;
    $pdf .= "xref\n0 " . $size . "\n0000000000 65535 f \n";
    for ($number = 1; $number < $size; $number++) {
        $pdf .= isset($offsets[$number])
            ? sprintf("%010d 00000 n \n", $offsets[$number])
            : "0000000000 00000 f \n";
    }
    $pdf .= "trailer\n<< /Size " . $size . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";

    return $pdf;
};

$mixedVisualPdf = static function () use ($visualInventoryPdf): string {
    $content = "BT /F1 12 Tf 20 180 Td (Readable text survives every visual disposition.) Tj ET\n"
        . "q 40 0 0 30 20 120 cm /Im1 Do Q\n"
        . "q /Invisible gs 30 0 0 20 80 120 cm /Im1 Do Q\n"
        . "q 0 0 0 0 0 0 cm /Im1 Do Q\n"
        . "q 24 0 0 24 130 120 cm BI /W 1 /H 1 /CS /G /BPC 8 ID\n\x80\nEI Q\n"
        . "q 1 0 0 1 20 20 cm /FxChart Do Q\n"
        . "q 1 0 0 1 100 20 cm /FxBroken Do Q\n"
        . "10 70 20 20 re f 28 70 20 20 re f 46 70 20 20 re f 64 70 20 20 re f\n"
        . "/Shade1 sh\n";
    $form = "0 0 50 20 re f 0 22 50 18 re f";
    $image = "\x00";

    return $visualInventoryPdf([
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200]'
            . ' /Resources << /Font << /F1 7 0 R >> /ExtGState << /Invisible 8 0 R >>'
            . ' /Shading << /Shade1 9 0 R >>'
            . ' /XObject << /Im1 5 0 R /FxChart 6 0 R /FxBroken 10 0 R >> >> /Contents 4 0 R >>',
        4 => '<< /Length ' . strlen($content) . ">>\nstream\n" . $content . "endstream",
        5 => '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray'
            . ' /BitsPerComponent 8 /Length ' . strlen($image) . ">>\nstream\n" . $image . "\nendstream",
        6 => '<< /Type /XObject /Subtype /Form /BBox [0 0 50 40] /Length ' . strlen($form) . ">>\nstream\n" . $form . "\nendstream",
        7 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        8 => '<< /Type /ExtGState /ca 0 >>',
        9 => '<< /ShadingType 2 /ColorSpace /DeviceRGB /Coords [0 0 200 200] /Function << /FunctionType 2 /Domain [0 1] /C0 [0 0 0] /C1 [1 1 1] /N 1 >> >>',
        10 => '<< /Type /XObject /Subtype /Form /Length 0 >>\nstream\n\nendstream',
    ]);
};

return [
    'inventories every raster inline Form and conservative page vector with stable dispositions' => static function (TestRunner $t) use ($mixedVisualPdf): void {
        $pdf = $mixedVisualPdf();
        $extractor = new PdfTextExtractor();
        $first = $extractor->extractVisualOccurrences($pdf);
        $second = $extractor->extractVisualOccurrences($pdf);

        $t->same(array_column($first, 'id'), array_column($second, 'id'));
        $t->same(count($first), count(array_unique(array_column($first, 'id'))), 'Every painted occurrence needs its own stable source id.');
        $byKind = [];
        foreach ($first as $occurrence) {
            $byKind[(string) ($occurrence['kind'] ?? '')][] = $occurrence;
            $t->true(in_array($occurrence['disposition'] ?? null, ['pending', 'intentional_omission', 'unresolved'], true));
        }

        $t->same(3, count($byKind['image-xobject'] ?? []));
        $t->same(1, count($byKind['inline-image'] ?? []));
        $t->same(2, count($byKind['form-xobject'] ?? []));
        $t->true(count($byKind['page-vector-region'] ?? []) >= 2);

        $imagesByReason = [];
        foreach ($byKind['image-xobject'] as $image) {
            if (is_string($image['dispositionReason'] ?? null)) {
                $imagesByReason[$image['dispositionReason']] = $image;
            }
        }
        $t->same('intentional_omission', $imagesByReason['image-not-visible']['disposition'] ?? null);
        $t->same('unresolved', $imagesByReason['image-transform-invalid']['disposition'] ?? null);
        $t->same('pending', $byKind['inline-image'][0]['disposition'] ?? null);
        $t->same([], $byKind['inline-image'][0]['resourcePath'] === ['inline-image'] ? [] : ['unexpected-resource-path']);

        $formsByReason = [];
        foreach ($byKind['form-xobject'] as $form) {
            if (is_string($form['dispositionReason'] ?? null)) {
                $formsByReason[$form['dispositionReason']] = $form;
            }
        }
        $t->same('unresolved', $formsByReason['form-bbox-missing-or-invalid']['disposition'] ?? null);
        $t->true(count(array_filter(
            $byKind['page-vector-region'],
            static fn (array $item): bool => ($item['placementEligible'] ?? false) === true
        )) >= 1, 'A connected multi-paint chart-sized region should be browser-croppable.');
        $t->true(count(array_filter(
            $byKind['page-vector-region'],
            static fn (array $item): bool => ($item['dispositionReason'] ?? '') === 'page-vector-shading-bounds-unknown'
        )) === 1, 'Unbounded shading must be explicit rather than a false completeness pass.');
    },

    'carries the occurrence inventory and native ids through provider facts without changing text' => static function (TestRunner $t) use ($mixedVisualPdf): void {
        $pdf = $mixedVisualPdf();
        $facts = (new NativePdfFactsProvider())->extract($pdf);
        $page = $facts->page(1);
        $graphics = $page?->graphics() ?? [];
        $occurrences = $graphics['visualOccurrences'] ?? [];

        $t->true($occurrences !== []);
        $t->same(count($occurrences), count(array_unique(array_column($occurrences, 'id'))));
        foreach ($occurrences as $occurrence) {
            $t->true(is_string($occurrence['provenance']['nativeId'] ?? null));
            $t->true(($occurrence['provenance']['nativeId'] ?? '') !== '');
        }
        $text = implode("\n", array_column($page?->text()['lines'] ?? [], 'text'));
        $t->contains('Readable text survives every visual disposition.', $text);
    },

    'marks incomplete Form inspection and malformed inline image boundaries unresolved' => static function (TestRunner $t) use ($visualInventoryPdf): void {
        $formContent = str_repeat("0 0 20 20 re f 2 2 10 10 re f ", 20);
        $content = "q 1 0 0 1 10 10 cm /Fx Do Q\nBI /W 1 /H 1 /CS /G /BPC 8";
        $pdf = $visualInventoryPdf([
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] /Resources << /XObject << /Fx 5 0 R >> >> /Contents 4 0 R >>',
            4 => '<< /Length ' . strlen($content) . ">>\nstream\n" . $content . "\nendstream",
            5 => '<< /Type /XObject /Subtype /Form /BBox [0 0 20 20] /Length ' . strlen($formContent) . ">>\nstream\n" . $formContent . "\nendstream",
        ]);

        $occurrences = (new PdfTextExtractor(['pdfMaxTokenizedContentStreamBytes' => 256]))->extractVisualOccurrences($pdf);
        $form = array_values(array_filter($occurrences, static fn (array $item): bool => ($item['kind'] ?? '') === 'form-xobject'))[0] ?? [];
        $inline = array_values(array_filter($occurrences, static fn (array $item): bool => ($item['kind'] ?? '') === 'inline-image'))[0] ?? [];

        $t->same(false, $form['visualSummary']['complete'] ?? null);
        $t->same('unresolved', $inline['disposition'] ?? null);
        $t->same('inline-image-boundary-invalid', $inline['dispositionReason'] ?? null);
    },
    'bounds a one page occurrence bomb while reporting the exact recoverable tail' => static function (TestRunner $t) use ($visualInventoryPdf): void {
        $paintCount = 8_300;
        $content = str_repeat("q 1 0 0 1 0 0 cm /Im Do Q\n", $paintCount);
        $image = "\x80";
        $pdf = $visualInventoryPdf([
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100]'
                . ' /Resources << /XObject << /Im 5 0 R >> >> /Contents 4 0 R >>',
            4 => '<< /Length ' . strlen($content) . ">>\nstream\n" . $content . "endstream",
            5 => '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray'
                . ' /BitsPerComponent 8 /Length 1>>' . "\nstream\n" . $image . "\nendstream",
        ]);

        $occurrences = (new PdfTextExtractor())->extractVisualOccurrences($pdf);
        $limitIssue = $occurrences[count($occurrences) - 1] ?? [];

        $t->same(8_192, count($occurrences));
        $t->same('inspection-issue', $limitIssue['kind'] ?? null);
        $t->same('visual-occurrence-limit', $limitIssue['dispositionReason'] ?? null);
        $t->same('resource-limit', $limitIssue['issueType'] ?? null);
        $t->same(true, $limitIssue['recoverable'] ?? null);
        $t->same($paintCount - 8_191, $limitIssue['omittedOccurrences'] ?? null);
        $t->same(
            count($occurrences),
            count(array_unique(array_column($occurrences, 'id'))),
            'The bounded inventory must retain stable unique ids plus one typed terminal issue.'
        );
    },
];
