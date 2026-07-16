<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineXrefPrevChainOmittedRowsCurrentBasePdf = static function (): string {
    $previousIntro = 'BT /F1 12 Tf 72 720 Td (Previous omitted outline intro) Tj ET';
    $currentIntro = 'BT /F1 12 Tf 72 720 Td (Current omitted outline intro) Tj ET';
    $currentTarget = 'BT /F1 12 Tf 72 720 Td (Current omitted outline target) Tj ET';

    $pdf = "%PDF-1.7\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $previousOffsets = [];
    $previousOffsets[1] = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 9 0 R >> /PageMode /UseOutlines >>');
    $previousOffsets[2] = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $previousOffsets[3] = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 20 0 R >>');
    $previousOffsets[5] = $addObject(5, 0, '<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>');
    $previousOffsets[6] = $addObject(6, 0, '<< /Title (Previous Omitted Outline) /Parent 5 0 R /Dest /PreviousStart /A 12 0 R >>');
    $previousOffsets[9] = $addObject(9, 0, '<< /Names [(PreviousStart) [3 0 R /Fit]] >>');
    $previousOffsets[12] = $addObject(12, 0, "<< /S /JavaScript /JS (app.alert\\('previous omitted outline action'\\)) >>");
    $previousOffsets[20] = $addObject(20, 0, "<< /Length " . strlen($previousIntro) . " >>\nstream\n{$previousIntro}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n0 21\n"
        . $xrefTableRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 20; $objectNumber++) {
        $pdf .= isset($previousOffsets[$objectNumber])
            ? $xrefTableRow($previousOffsets[$objectNumber])
            : $xrefTableRow(0, 0, 'f');
    }
    $pdf .= "trailer\n<< /Size 21 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 9 0 R >> /PageMode /UseOutlines >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 20 0 R >>');
    $addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Contents 21 0 R >>');
    $addObject(5, 0, '<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>');
    $addObject(6, 0, '<< /Title (Current Omitted Outline Start) /Parent 5 0 R /Dest /CurrentStart /Next 7 0 R /C [0 .2 .5] /F 2 >>');
    $addObject(7, 0, '<< /Title (Current Omitted Outline Review) /Parent 5 0 R /Prev 6 0 R /A 12 0 R >>');
    $addObject(9, 0, '<< /Names [(CurrentStart) [3 0 R /FitH 700] (CurrentTarget) [4 0 R /XYZ 144 620 0]] >>');
    $addObject(12, 0, '<< /S /GoTo /D /CurrentTarget /Next 13 0 R >>');
    $addObject(13, 0, '<< /S /URI /URI (https://example.com/current-omitted-outline-review) >>');
    $addObject(20, 0, "<< /Length " . strlen($currentIntro) . " >>\nstream\n{$currentIntro}\nendstream");
    $addObject(21, 0, "<< /Length " . strlen($currentTarget) . " >>\nstream\n{$currentTarget}\nendstream");

    $rows = $xrefStreamRow(1, $currentCatalogOffset, 0);
    $compressedRows = gzcompress($rows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress outline omitted-row xref stream.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [1 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'repairs omitted current outline graph rows before stale xref Prev navigation rows' => static function (
        TestRunner $t
    ) use ($outlineXrefPrevChainOmittedRowsCurrentBasePdf): void {
        $pdf = $outlineXrefPrevChainOmittedRowsCurrentBasePdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['Current Omitted Outline Start', 'Current Omitted Outline Review'], array_column($toc, 'title'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same(['FitH', 'XYZ'], array_column($toc, 'view_mode'));
        $t->same(['top' => 700.0], $toc[0]['view_parameters'] ?? null);
        $t->same(['left' => 144.0, 'top' => 620.0, 'zoom' => null], $toc[1]['view_parameters'] ?? null);
        $t->same(['Current Omitted Outline Start', 'Current Omitted Outline Review'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same(['GoTo', 'URI'], array_column($navigation['outline_action_review_actions'] ?? [], 'action_type'));
        $t->same("Current omitted outline intro\nCurrent omitted outline target", $plainText);
        $t->true(str_contains($pdf, '/Index [1 1]'));
        $t->true(str_contains($pdf, '/Prev '));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'Previous Omitted Outline'));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'previous omitted outline action'));
        $t->true(!str_contains($plainText, 'Previous omitted outline intro'));
        $t->true(!str_contains($plainText, 'Current Omitted Outline Start'));
        $t->true(!str_contains($plainText, 'Current Omitted Outline Review'));
    },
];
