<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineNavigationEofBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Current EOF outline intro text) Tj ET';
    $targetContent = 'BT /F1 12 Tf 72 720 Td (Current EOF outline target text) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale trailing outline body) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /PageMode /UseOutlines >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>');
    $addObject(4, '<< /Type /Page /Parent 2 0 R /Dur 6 /Trans 16 0 R /AA << /O 17 0 R >> /Contents 31 0 R >>');
    $addObject(5, '<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>');
    $addObject(6, '<< /Title (Current EOF Outline Start) /Parent 5 0 R /Dest /CurrentStart /Next 7 0 R /C [0 .25 .5] /F 2 >>');
    $addObject(7, '<< /Title (Current EOF Action Target) /Parent 5 0 R /Prev 6 0 R /A 9 0 R >>');
    $addObject(8, '<< /Names [(CurrentStart) [3 0 R /FitH 700] (CurrentTarget) [4 0 R /XYZ 120 640 0]] >>');
    $addObject(9, '<< /S /GoTo /D /CurrentTarget /Next 10 0 R >>');
    $addObject(10, '<< /S /URI /URI (https://example.com/current-eof-outline-review) >>');
    $addObject(16, '<< /S /Fly /D .75 /M /I /Di 270 >>');
    $addObject(17, '<< /S /URI /URI (https://example.com/current-page-open-review) >>');
    $addObject(20, '<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Target ) /St 5 >>] >>');
    $addObject(30, "<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream");
    $addObject(31, "<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream");

    $xrefOffset = strlen($pdf);
    $maxObject = 31;
    $pdf .= "xref\n0 " . ($maxObject + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($objectNumber = 1; $objectNumber <= $maxObject; $objectNumber++) {
        $pdf .= isset($offsets[$objectNumber])
            ? sprintf("%010d 00000 n \n", $offsets[$objectNumber])
            : "0000000000 00000 f \n";
    }
    $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 70 0 R /Names << /Dests 72 0 R >> >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
        . "70 0 obj\n<< /Type /Outlines /First 71 0 R /Last 71 0 R /Count 1 >>\nendobj\n"
        . "71 0 obj\n<< /Title (Stale EOF Outline Should Not Import) /Parent 70 0 R /A << /S /GoTo /D /StaleTarget /Next 73 0 R >> >>\nendobj\n"
        . "72 0 obj\n<< /Names [(StaleTarget) [4 0 R /Fit]] >>\nendobj\n"
        . "73 0 obj\n<< /S /JavaScript /JS (app.alert\\('stale eof outline action'\\)) >>\nendobj\n";

    return $pdf;
};

return [
    'uses the current EOF-bounded outline tree for WordPress navigation metadata' => static function (
        TestRunner $t
    ) use ($outlineNavigationEofBoundaryPdf): void {
        $pdf = $outlineNavigationEofBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);

        $t->same(['Current EOF Outline Start', 'Current EOF Action Target'], array_column($toc, 'title'));
        $t->same(['outline', 'outline_actions', 'page_presentations'], $navigation['source']);
        $t->same(['Current EOF Outline Start', 'Current EOF Action Target'], array_column($navigation['outline'], 'title'));
        $t->same(['Current EOF Outline Start', 'Current EOF Action Target'], $metadata['document_outline']['titles'] ?? null);
        $t->same(2, $metadata['document_outline']['item_count'] ?? null);
        $t->same(2, $metadata['document_outline']['resolved_destination_count'] ?? null);

        $first = $navigation['outline'][0] ?? [];
        $second = $navigation['outline'][1] ?? [];
        $t->same('#004080', $first['text_color_hex'] ?? null);
        $t->same(true, $first['is_bold'] ?? null);
        $t->same('CurrentStart', $first['destination'] ?? null);
        $t->same('Intro 1', $first['page_label'] ?? null);
        $t->same('CurrentTarget', $second['destination'] ?? null);
        $t->same('XYZ', $second['view_mode'] ?? null);
        $t->same('Fly', $second['target_page_transition']['style'] ?? null);

        $actions = $navigation['outline_action_review_actions'] ?? [];
        $t->same(['GoTo', 'URI'], array_column($actions, 'action_type'));
        $t->same('XYZ', $actions[0]['destination_action_target_view_mode'] ?? null);
        $t->same('Fly', $actions[0]['destination_action_target_page_transition']['style'] ?? null);
        $t->same(['page_open'], array_column($actions[0]['destination_action_target_page_actions'] ?? [], 'event_label'));
        $t->same([false, false], array_column($actions, 'executes_on_import'));

        $encoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $t->true(is_string($encoded));
        $t->true(!str_contains($encoded, 'Stale EOF Outline Should Not Import'));
        $t->true(!str_contains($encoded, 'stale eof outline action'));
    },
    'keeps trailing stale outline operands out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineNavigationEofBoundaryPdf): void {
        $pdf = $outlineNavigationEofBoundaryPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);

        $t->same("Current EOF outline intro text\nCurrent EOF outline target text", $plainText);
        $t->same(['Current EOF Outline Start', 'Current EOF Action Target'], array_column($navigation['outline'], 'title'));
        $t->true(!str_contains($plainText, 'Current EOF Outline Start'));
        $t->true(!str_contains($plainText, 'Current EOF Action Target'));
        $t->true(!str_contains($plainText, 'Stale EOF Outline Should Not Import'));
        $t->true(!str_contains($plainText, 'stale eof outline action'));
        $t->true(!str_contains($plainText, 'Stale trailing outline body'));
    },
];
