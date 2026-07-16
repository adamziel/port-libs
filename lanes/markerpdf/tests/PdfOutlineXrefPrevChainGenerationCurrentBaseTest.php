<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineXrefPrevChainGenerationCurrentBasePdf = static function (): string {
    $previousIntro = 'BT /F1 12 Tf 72 720 Td (Previous generation outline intro) Tj ET';
    $currentIntro = 'BT /F1 12 Tf 72 720 Td (Current generation outline intro) Tj ET';
    $currentTarget = 'BT /F1 12 Tf 72 720 Td (Current generation outline target) Tj ET';

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
    $previousOffsets[6] = $addObject(6, 0, '<< /Title (Previous Generation Outline) /Parent 5 0 R /Dest /PreviousStart /A 12 0 R >>');
    $previousOffsets[9] = $addObject(9, 0, '<< /Names [(PreviousStart) [3 0 R /Fit]] >>');
    $previousOffsets[12] = $addObject(12, 0, "<< /S /JavaScript /JS (app.alert\\('previous generation outline action'\\)) >>");
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

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R /Outlines 5 1 R /Names << /Dests 9 1 R >> /PageMode /UseOutlines >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [3 1 R 4 1 R] /Count 2 >>');
    $addObject(3, 1, '<< /Type /Page /Parent 2 1 R /Contents 20 1 R >>');
    $addObject(4, 1, '<< /Type /Page /Parent 2 1 R /Contents 21 1 R >>');
    $addObject(5, 1, '<< /Type /Outlines /First 6 1 R /Last 7 1 R /Count 2 >>');
    $addObject(6, 1, '<< /Title (Current Generation Outline Start) /Parent 5 1 R /Dest /CurrentStart /Next 7 1 R /C [0 .2 .5] /F 2 >>');
    $addObject(7, 1, '<< /Title (Current Generation Outline Review) /Parent 5 1 R /Prev 6 1 R /A 12 1 R >>');
    $addObject(9, 1, '<< /Names [(CurrentStart) [3 1 R /FitH 700] (CurrentTarget) [4 1 R /XYZ 144 620 0]] >>');
    $addObject(12, 1, '<< /S /GoTo /D /CurrentTarget /Next 13 1 R >>');
    $addObject(13, 1, '<< /S /URI /URI (https://example.com/current-generation-outline-review) >>');
    $addObject(20, 1, "<< /Length " . strlen($currentIntro) . " >>\nstream\n{$currentIntro}\nendstream");
    $addObject(21, 1, "<< /Length " . strlen($currentTarget) . " >>\nstream\n{$currentTarget}\nendstream");

    $rows = '';
    foreach ([1, 2, 3, 4, 5, 6, 7, 9, 12, 13, 20, 21] as $objectNumber) {
        $rows .= $xrefStreamRow(1, 0, 1);
    }
    $compressedRows = gzcompress($rows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress outline generation xref stream.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 7 9 1 12 2 20 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'repairs damaged nonzero-generation current outline xref rows before Prev chain rows' => static function (
        TestRunner $t
    ) use ($outlineXrefPrevChainGenerationCurrentBasePdf): void {
        $pdf = $outlineXrefPrevChainGenerationCurrentBasePdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['Current Generation Outline Start', 'Current Generation Outline Review'], array_column($toc, 'title'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same(['FitH', 'XYZ'], array_column($toc, 'view_mode'));
        $t->same(['top' => 700.0], $toc[0]['view_parameters'] ?? null);
        $t->same(['left' => 144.0, 'top' => 620.0, 'zoom' => null], $toc[1]['view_parameters'] ?? null);
        $t->same(['Current Generation Outline Start', 'Current Generation Outline Review'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same(['Current Generation Outline Review', 'Current Generation Outline Review'], array_column($navigation['outline_action_review_actions'] ?? [], 'outline_title'));
        $t->same(['GoTo', 'URI'], array_column($navigation['outline_action_review_actions'] ?? [], 'action_type'));
        $t->same("Current generation outline intro\nCurrent generation outline target", $plainText);
        $t->true(str_contains($pdf, '/Root 1 1 R'));
        $t->true(str_contains($pdf, '/Prev '));
        $t->true(str_contains($pdf, '/Index [1 7 9 1 12 2 20 2]'));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'Previous Generation Outline'));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'previous generation outline action'));
        $t->true(!str_contains($plainText, 'Previous generation outline intro'));
        $t->true(!str_contains($plainText, 'Current Generation Outline Start'));
        $t->true(!str_contains($plainText, 'Current Generation Outline Review'));
    },
];
