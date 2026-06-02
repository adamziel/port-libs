<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineArticleThreadBeadNavigationPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Intro article text stays visible) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Story left column text) Tj ET '
        . 'BT /F1 12 Tf 320 720 Td (Story right column text) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /OpenAction /ArticleIntro /Names << /Dests 8 0 R >> /PageLabels 25 0 R /Threads [20 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Story Bead Target) /Parent 5 0 R /Dest /ArticleStory >>\nendobj\n"
        . "8 0 obj\n<< /Names [(ArticleIntro) [3 0 R /Fit] (ArticleStory) [4 0 R /FitH 700]] >>\nendobj\n"
        . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Magazine Article Thread) >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [60 700 260 740] /N 22 0 R /V 23 0 R >>\nendobj\n"
        . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [60 700 260 740] /N 23 0 R /V 21 0 R >>\nendobj\n"
        . "23 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [300 700 520 740] /N 21 0 R /V 22 0 R >>\nendobj\n"
        . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Story ) /St 7 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'attaches article thread bead navigation metadata to outline and open-action targets' => static function (TestRunner $t) use ($outlineArticleThreadBeadNavigationPdf): void {
        $metadata = (new PdfOutlineExtractor())->getNavigationReviewMetadata($outlineArticleThreadBeadNavigationPdf());

        $t->same(['outline', 'open_action', 'article_threads'], $metadata['source']);
        $t->same(1, count($metadata['article_threads']));
        $thread = $metadata['article_threads'][0];
        $t->same(0, $thread['thread_index']);
        $t->same(20, $thread['thread_object']);
        $t->same('Magazine Article Thread', $thread['title']);
        $t->same(3, $thread['bead_count']);
        $t->same([21, 22, 23], array_column($thread['beads'], 'bead_object'));
        $t->same([22, 23, 21], array_column($thread['beads'], 'next_bead_object'));
        $t->same([23, 21, 22], array_column($thread['beads'], 'previous_bead_object'));
        $t->same(['Intro 1', 'Story 7', 'Story 7'], array_column($thread['beads'], 'page_label'));
        $t->same([
            [60.0, 700.0, 260.0, 740.0],
            [60.0, 700.0, 260.0, 740.0],
            [300.0, 700.0, 520.0, 740.0],
        ], array_column($thread['beads'], 'rect'));

        $outline = $metadata['outline'][0];
        $t->same('Story Bead Target', $outline['title']);
        $t->same(1, $outline['page']);
        $t->same('Story 7', $outline['page_label']);
        $t->same('ArticleStory', $outline['destination']);
        $t->same('FitH', $outline['view_mode']);
        $t->same(['top' => 700.0], $outline['view_parameters']);
        $t->same(['Magazine Article Thread'], $outline['target_article_thread_titles']);
        $t->same([22, 23], array_column($outline['target_article_beads'], 'bead_object'));
        $t->same(['Story 7', 'Story 7'], array_column($outline['target_article_beads'], 'page_label'));

        $openAction = $metadata['open_action_review_actions'][0];
        $t->same('GoTo', $openAction['action_type']);
        $t->same('ArticleIntro', $openAction['destination']);
        $t->same('Intro 1', $openAction['page_label']);
        $t->same([21], array_column($openAction['target_article_beads'], 'bead_object'));

        $openDestination = $metadata['open_action_destination'];
        $t->same(0, $openDestination['page']);
        $t->same('Intro 1', $openDestination['page_label']);
        $t->same([21], array_column($openDestination['target_article_beads'], 'bead_object'));
    },
    'keeps outline article thread dictionaries out of visible WordPress text' => static function (TestRunner $t) use ($outlineArticleThreadBeadNavigationPdf): void {
        $pdf = $outlineArticleThreadBeadNavigationPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $toc = (new PdfOutlineExtractor())->getPdfToc($pdf);

        $t->contains('Intro article text stays visible', $plainText);
        $t->contains('Story left column text', $plainText);
        $t->contains('Story right column text', $plainText);
        $t->true(!str_contains($plainText, 'Magazine Article Thread'));
        $t->true(!str_contains($plainText, 'Story Bead Target'));
        $t->true(!str_contains($plainText, 'ArticleIntro'));
        $t->same([
            ['title' => 'Story Bead Target', 'level' => 1, 'page' => 1, 'destination' => 'ArticleStory'],
        ], $toc);
    },
];
