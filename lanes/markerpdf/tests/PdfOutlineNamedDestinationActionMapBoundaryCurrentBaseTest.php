<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineNamedDestinationActionMapBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Intro outline action map page remains visible) Tj ET';
    $targetContent = 'BT /F1 12 Tf 72 720 Td (Target outline action map page remains visible) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 25 0 R /Threads [20 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 32 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Local review action destination) /Parent 5 0 R /Dest /ReviewAction /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Thread review action destination) /Parent 5 0 R /Dest /ThreadAction /Prev 6 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Names [(ReviewAction) 9 0 R (ReviewTarget) [4 0 R /FitH 640] (ThreadAction) 10 0 R] >>\nendobj\n"
        . "9 0 obj\n<< /S /GoTo /D /ReviewTarget /Next 11 0 R >>\nendobj\n"
        . "10 0 obj\n<< /S /Thread /D (Boundary Thread) /B 22 0 R /Next 12 0 R >>\nendobj\n"
        . "11 0 obj\n<< /S /URI /URI (https://example.com/local-review-action) >>\nendobj\n"
        . "12 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden thread action followup'\\)) >>\nendobj\n"
        . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Boundary Thread) >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [64 684 280 734] /N 22 0 R /V 22 0 R >>\nendobj\n"
        . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [292 684 548 734] /N 21 0 R /V 21 0 R >>\nendobj\n"
        . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Target ) /St 5 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'keeps page destination and action review maps separate for named outline destinations' => static function (
        TestRunner $t
    ) use ($outlineNamedDestinationActionMapBoundaryPdf): void {
        $pdf = $outlineNamedDestinationActionMapBoundaryPdf();
        $extractor = new PdfOutlineExtractor();
        $metadata = $extractor->getNavigationReviewMetadata($pdf);
        $toc = $extractor->getPdfToc($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(['outline', 'outline_actions', 'article_threads', 'page_review'], $metadata['source']);
        $t->same(
            [['title' => 'Local review action destination', 'level' => 1, 'page' => 1, 'destination' => 'ReviewAction']],
            $toc,
            'The named GoTo action resolves through its page destination, but the Thread action remains review-only.'
        );
        $t->same(['Local review action destination'], array_column($metadata['outline'], 'title'));
        $t->same('ReviewAction', $metadata['outline'][0]['destination']);
        $t->same('Target 5', $metadata['outline'][0]['page_label']);
        $t->same('FitH', $metadata['outline'][0]['view_mode']);
        $t->same(['top' => 640.0], $metadata['outline'][0]['view_parameters']);

        $actions = $metadata['outline_action_review_actions'];
        $t->same(4, count($actions));
        $t->same(
            ['Local review action destination', 'Local review action destination', 'Thread review action destination', 'Thread review action destination'],
            array_column($actions, 'outline_title')
        );
        $t->same(['GoTo', 'URI', 'Thread', 'JavaScript'], array_column($actions, 'action_type'));
        $t->same(['local-destination', 'review-uri', 'article-thread-review', 'blocked-javascript'], array_column($actions, 'safety'));
        $t->same([9, 11, 10, 12], array_column($actions, 'action_object'));
        $t->same(['ReviewAction', 'ReviewAction', 'ThreadAction', 'ThreadAction'], array_column($actions, 'destination_action_name'));
        $t->same(['ReviewTarget', null, 'Boundary Thread', null], array_column($actions, 'destination'));
        $t->same([1, null, 1, null], [
            $actions[0]['page'] ?? null,
            $actions[1]['page'] ?? null,
            $actions[2]['page'] ?? null,
            $actions[3]['page'] ?? null,
        ]);
        $t->same([null, true, null, true], [
            $actions[0]['chained'] ?? null,
            $actions[1]['chained'] ?? null,
            $actions[2]['chained'] ?? null,
            $actions[3]['chained'] ?? null,
        ]);
        $t->same([false, false, false, false], array_column($actions, 'executes_on_import'));
        $t->same('Target 5', $actions[0]['page_label']);
        $t->same('Boundary Thread', $actions[2]['thread_title']);
        $t->same(1, $actions[2]['page']);
        $t->same('Target 5', $actions[2]['page_label']);
        $t->same([21, 22], array_column($actions[2]['target_article_beads'], 'bead_object'));
        $t->same(['Boundary Thread'], array_column($metadata['article_threads'], 'title'));
        $t->same('https://example.com/local-review-action', $actions[1]['uri']);

        $t->contains('Intro outline action map page remains visible', $plainText);
        $t->contains('Target outline action map page remains visible', $plainText);
        $t->true(!str_contains($plainText, 'Local review action destination'));
        $t->true(!str_contains($plainText, 'Thread review action destination'));
        $t->true(!str_contains($plainText, 'ReviewAction'));
        $t->true(!str_contains($plainText, 'ReviewTarget'));
        $t->true(!str_contains($plainText, 'ThreadAction'));
        $t->true(!str_contains($plainText, 'hidden thread action followup'));
    },
];
