<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationPdf = static function (): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyAppendix [4 0 R /Fit] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 3 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Start Here) /Parent 5 0 R /Dest (wp-start) /Next 7 0 R /First 9 0 R /Count 1 >>\nendobj\n"
        . "7 0 obj\n<< /Title (Legacy Appendix) /Parent 5 0 R /A << /S /GoTo /D /LegacyAppendix >> >>\nendobj\n"
        . "8 0 obj\n<< /Names [(wp-start) [3 0 R /XYZ 72 720 0] (wp-child) << /D [4 0 R /FitH 650] >> (stale) [99 0 R /Fit]] >>\nendobj\n"
        . "9 0 obj\n<< /Title <FEFF004300680069006C0064002000530065006300740069006F006E> /Parent 6 0 R /Dest /wp-child >>\nendobj\n"
        . "%%EOF";
};

$kidNameTreePdf = static function (): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Review Link) /Parent 5 0 R /Dest /WP#20Review /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Direct Array) /Parent 5 0 R /Dest [3 0 R /Fit] >>\nendobj\n"
        . "8 0 obj\n<< /Kids [10 0 R 11 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Limits [(A) (M)] /Names [(Alpha) [3 0 R /Fit]] >>\nendobj\n"
        . "11 0 obj\n<< /Limits [(N) (Z)] /Names [(WP Review) [4 0 R /XYZ null null null]] >>\nendobj\n"
        . "%%EOF";
};

$remoteGoToPdf = static function (): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 4 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Appendix PDF) /Parent 5 0 R /A << /S /GoToR /F (appendix.pdf) /D [2 /FitH 720] /NewWindow true >> /Next 7 0 R /First 9 0 R /Count 1 >>\nendobj\n"
        . "7 0 obj\n<< /Title (Legacy Remote) /Parent 5 0 R /A 8 0 R /Next 10 0 R >>\nendobj\n"
        . "8 0 obj\n<< /S /GoToR /F << /F (legacy.pdf) /UF <FEFF007200650076006900650077002D00670075006900640065002E007000640066> >> /D /Chapter#202 /NewWindow false >>\nendobj\n"
        . "9 0 obj\n<< /Title <FEFF00520065006D006F007400650020004300680069006C0064> /Parent 6 0 R /A << /S /GoToR /F (child.pdf) /D (named-child) >> >>\nendobj\n"
        . "10 0 obj\n<< /Title (Local Only) /Parent 5 0 R /A << /S /GoTo /D [3 0 R /Fit] >> /Next 11 0 R >>\nendobj\n"
        . "11 0 obj\n<< /Title (Broken Remote) /Parent 5 0 R /A << /S /GoToR /F (missing-destination.pdf) >> >>\nendobj\n"
        . "%%EOF";
};

$openActionPdf = static function (string $openAction, string $extraObjects = ''): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OpenAction {$openAction} /Names << /Dests 8 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Names [(Import Start) [3 0 R /Fit] (Review Page) [4 0 R /FitH 640]] >>\nendobj\n"
        . $extraObjects
        . "%%EOF";
};

$destinationViewPdf = static function (): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageMode /UseOutlines /PageLayout /TwoColumnLeft /OpenAction /review-start >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 4 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Full Page) /Parent 5 0 R /Dest [3 0 R /Fit] /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Review Zoom) /Parent 5 0 R /Dest /review-start /Next 9 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Names [(review-start) [4 0 R /XYZ 144 640 0]] >>\nendobj\n"
        . "9 0 obj\n<< /Title (Width Fit) /Parent 5 0 R /A << /S /GoTo /D [4 0 R /FitH 700] >> /Next 10 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Title (Page Ref Only) /Parent 5 0 R /Dest 3 0 R >>\nendobj\n"
        . "%%EOF";
};

$indirectNameTreeDestinationPdf = static function (): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /OpenAction 16 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Indirect Name) /Parent 5 0 R /Dest 12 0 R /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Indirect Action) /Parent 5 0 R /A << /S /GoTo /D 16 0 R >> >>\nendobj\n"
        . "8 0 obj\n<< /Kids [9 0 R 10 0 R] >>\nendobj\n"
        . "9 0 obj\n<< /Limits [(A) (M)] /Names [12 0 R 13 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Limits [(N) (Z)] /Names [16 0 R 17 0 R] >>\nendobj\n"
        . "12 0 obj\n<FEFF0049006E0064006900720065006300740020005200650076006900650077>\nendobj\n"
        . "13 0 obj\n<< /D 14 0 R >>\nendobj\n"
        . "14 0 obj\n[4 0 R /FitBH null]\nendobj\n"
        . "16 0 obj\n(Page Four)\nendobj\n"
        . "17 0 obj\n<< /D [3 0 R /FitR 10 20 300 740] >>\nendobj\n"
        . "%%EOF";
};

$pagePresentationPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Slide body stays visible) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Second slide stays clean) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 8 /Trans 5 0 R /AA << /O 6 0 R /C << /S /GoToR /F (deck-appendix.pdf) /D /Slide#202 /NewWindow true >> >> /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Trans << /S /Split /D .75 /Dm /H /M /O /Di 90 /SS .5 /B true >> /AA << /O << /S /Launch /Win << /F (helper.exe) /O (print) >> >> /C << /S /URI /URI (javascript:alert\\(1\\)) /Next [7 0 R 7 0 R] >> >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /S /Dissolve /D 1.5 >>\nendobj\n"
        . "6 0 obj\n<< /S /URI /URI (https://example.com/slide-notes) >>\nendobj\n"
        . "7 0 obj\n<< /S /GoTo /D /Start >>\nendobj\n"
        . "20 0 obj\n<< /Names [(Start) [3 0 R /Fit]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'resolves PDF outline named destinations before WordPress TOC import' => static function (TestRunner $t) use ($namedDestinationPdf): void {
        $toc = (new PdfOutlineExtractor())->getPdfToc($namedDestinationPdf());

        $t->same(
            [
                ['title' => 'Start Here', 'level' => 1, 'page' => 0, 'destination' => 'wp-start'],
                ['title' => 'Child Section', 'level' => 2, 'page' => 1, 'destination' => 'wp-child'],
                ['title' => 'Legacy Appendix', 'level' => 1, 'page' => 1, 'destination' => 'LegacyAppendix'],
            ],
            $toc
        );
    },
    'honors max depth while following sibling outline items' => static function (TestRunner $t) use ($namedDestinationPdf): void {
        $toc = (new PdfOutlineExtractor())->getPdfToc($namedDestinationPdf(), 1);

        $t->same(['Start Here', 'Legacy Appendix'], array_column($toc, 'title'));
        $t->same([1, 1], array_column($toc, 'level'));
    },
    'resolves kid name trees and PDF name escapes used by GoTo destinations' => static function (TestRunner $t) use ($kidNameTreePdf): void {
        $toc = (new PdfOutlineExtractor())->getPdfToc($kidNameTreePdf());

        $t->same(
            [
                ['title' => 'Review Link', 'level' => 1, 'page' => 1, 'destination' => 'WP Review'],
                ['title' => 'Direct Array', 'level' => 1, 'page' => 0, 'destination' => null],
            ],
            $toc
        );
    },
    'returns no native TOC for malformed or unresolved outline destinations' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
            . "6 0 obj\n<< /Title (Broken Link) /Parent 5 0 R /Dest /MissingName >>\nendobj\n"
            . "%%EOF";

        $t->same([], (new PdfOutlineExtractor())->getPdfToc($pdf));
        $t->same([], (new PdfOutlineExtractor())->getPdfToc('%PDF-1.4 no catalog here'));
    },
    'extracts PDF outline remote GoTo actions for external document review' => static function (TestRunner $t) use ($remoteGoToPdf): void {
        $actions = (new PdfOutlineExtractor())->getRemoteGoToActions($remoteGoToPdf());

        $t->same(3, count($actions));
        $t->same(
            [
                'title' => 'Appendix PDF',
                'level' => 1,
                'file' => 'appendix.pdf',
                'destination' => null,
                'page' => 2,
                'new_window' => true,
            ],
            $actions[0]
        );
        $t->same(
            [
                'title' => 'Remote Child',
                'level' => 2,
                'file' => 'child.pdf',
                'destination' => 'named-child',
                'page' => null,
                'new_window' => null,
            ],
            $actions[1]
        );
        $t->same('review-guide.pdf', $actions[2]['file']);
        $t->same('Chapter 2', $actions[2]['destination']);
        $t->same(false, $actions[2]['new_window']);
    },
    'keeps remote GoTo actions out of same-document TOC page rows and honors max depth' => static function (TestRunner $t) use ($remoteGoToPdf): void {
        $extractor = new PdfOutlineExtractor();

        $t->same([['title' => 'Local Only', 'level' => 1, 'page' => 0, 'destination' => null]], $extractor->getPdfToc($remoteGoToPdf()));
        $t->same(['Appendix PDF', 'Legacy Remote'], array_column($extractor->getRemoteGoToActions($remoteGoToPdf(), 1), 'title'));
        $t->same([], $extractor->getRemoteGoToActions('%PDF-1.4 no catalog here'));
    },
    'extracts local catalog OpenAction destinations as non-executing review metadata' => static function (TestRunner $t) use ($openActionPdf): void {
        $extractor = new PdfOutlineExtractor();

        $direct = $extractor->getOpenActionReviewActions($openActionPdf('[4 0 R /FitH 640]'));
        $named = $extractor->getOpenActionReviewActions($openActionPdf('<< /S /GoTo /D (Import Start) >>'));

        $t->same(
            [[
                'action_type' => 'GoTo',
                'safety' => 'local-destination',
                'page' => 1,
                'destination' => null,
                'uri' => null,
                'file' => null,
                'operation' => null,
                'new_window' => null,
                'is_safe_uri' => null,
                'executes_on_import' => false,
            ]],
            $direct
        );
        $t->same('Import Start', $named[0]['destination']);
        $t->same(0, $named[0]['page']);
        $t->same(false, $named[0]['executes_on_import']);
    },
    'classifies catalog OpenAction URI and Launch actions for WordPress safety review' => static function (TestRunner $t) use ($openActionPdf): void {
        $extractor = new PdfOutlineExtractor();

        $safeUri = $extractor->getOpenActionReviewActions($openActionPdf('<< /S /URI /URI (https://example.com/import-checklist) >>'));
        $unsafeUri = $extractor->getOpenActionReviewActions($openActionPdf('<< /S /URI /URI (javascript:alert\\(1\\)) >>'));
        $launch = $extractor->getOpenActionReviewActions($openActionPdf('9 0 R', "9 0 obj\n<< /S /Launch /F (installer.exe) /Win << /F (setup.exe) /O (open) /P (/silent) >> /NewWindow true >>\nendobj\n"));

        $t->same('URI', $safeUri[0]['action_type']);
        $t->same('https://example.com/import-checklist', $safeUri[0]['uri']);
        $t->same('review-uri', $safeUri[0]['safety']);
        $t->true($safeUri[0]['is_safe_uri']);
        $t->same(false, $safeUri[0]['executes_on_import']);

        $t->same('javascript:alert(1)', $unsafeUri[0]['uri']);
        $t->same('blocked-unsafe-uri', $unsafeUri[0]['safety']);
        $t->same(false, $unsafeUri[0]['is_safe_uri']);

        $t->same('Launch', $launch[0]['action_type']);
        $t->same('installer.exe', $launch[0]['file']);
        $t->same('open', $launch[0]['operation']);
        $t->same('blocked-launch', $launch[0]['safety']);
        $t->same(true, $launch[0]['new_window']);
        $t->same(false, $launch[0]['executes_on_import']);
    },
    'keeps catalog OpenAction remote GoToR out of same-document outline rows' => static function (TestRunner $t) use ($openActionPdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $openActionPdf('<< /S /GoToR /F << /UF <FEFF00650078007400650072006E0061006C002E007000640066> >> /D [3 /Fit] /NewWindow false >>');

        $review = $extractor->getOpenActionReviewActions($pdf);

        $t->same(
            [[
                'action_type' => 'GoToR',
                'safety' => 'remote-document-review',
                'page' => 3,
                'destination' => null,
                'uri' => null,
                'file' => 'external.pdf',
                'operation' => null,
                'new_window' => false,
                'is_safe_uri' => null,
                'executes_on_import' => false,
            ]],
            $review
        );
        $t->same([], $extractor->getPdfToc($pdf));
        $t->same([], $extractor->getRemoteGoToActions($pdf));
    },
    'extracts catalog OpenAction destination view metadata for WordPress import review' => static function (TestRunner $t) use ($destinationViewPdf): void {
        $metadata = (new PdfOutlineExtractor())->getCatalogPageViewMetadata($destinationViewPdf());

        $t->same(['page_mode', 'page_layout', 'open_action'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('TwoColumnLeft', $metadata['page_layout']);
        $t->same(
            [
                'page' => 1,
                'destination' => 'review-start',
                'view_mode' => 'XYZ',
                'view_position' => [144.0, 640.0, null],
                'view_parameters' => ['left' => 144.0, 'top' => 640.0, 'zoom' => null],
            ],
            $metadata['open_action']
        );
        $t->same(['source' => []], (new PdfOutlineExtractor())->getCatalogPageViewMetadata('%PDF-1.4 no catalog here'));
    },
    'preserves destination Fit and XYZ page view metadata without changing basic TOC rows' => static function (TestRunner $t) use ($destinationViewPdf): void {
        $extractor = new PdfOutlineExtractor();
        $toc = $extractor->getPdfTocWithDestinationViews($destinationViewPdf());

        $t->same(
            [
                ['title' => 'Full Page', 'level' => 1, 'page' => 0, 'destination' => null],
                ['title' => 'Review Zoom', 'level' => 1, 'page' => 1, 'destination' => 'review-start'],
                ['title' => 'Width Fit', 'level' => 1, 'page' => 1, 'destination' => null],
                ['title' => 'Page Ref Only', 'level' => 1, 'page' => 0, 'destination' => null],
            ],
            $extractor->getPdfToc($destinationViewPdf())
        );
        $t->same('Fit', $toc[0]['view_mode']);
        $t->same([], $toc[0]['view_position']);
        $t->same([], $toc[0]['view_parameters']);
        $t->same('XYZ', $toc[1]['view_mode']);
        $t->same([144.0, 640.0, null], $toc[1]['view_position']);
        $t->same(['left' => 144.0, 'top' => 640.0, 'zoom' => null], $toc[1]['view_parameters']);
        $t->same('FitH', $toc[2]['view_mode']);
        $t->same([700.0], $toc[2]['view_position']);
        $t->same(['top' => 700.0], $toc[2]['view_parameters']);
        $t->same(null, $toc[3]['view_mode']);
        $t->same([], $toc[3]['view_position']);
        $t->same([], $toc[3]['view_parameters']);
        $t->same([], $extractor->getPdfTocWithDestinationViews('%PDF-1.4 no catalog here'));
    },
    'resolves indirect name-tree strings and destination dictionaries' => static function (TestRunner $t) use ($indirectNameTreeDestinationPdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $indirectNameTreeDestinationPdf();

        $t->same(
            [
                ['title' => 'Indirect Name', 'level' => 1, 'page' => 1, 'destination' => 'Indirect Review'],
                ['title' => 'Indirect Action', 'level' => 1, 'page' => 0, 'destination' => 'Page Four'],
            ],
            $extractor->getPdfToc($pdf)
        );

        $toc = $extractor->getPdfTocWithDestinationViews($pdf);
        $t->same('FitBH', $toc[0]['view_mode']);
        $t->same([null], $toc[0]['view_position']);
        $t->same(['top' => null], $toc[0]['view_parameters']);
        $t->same('FitR', $toc[1]['view_mode']);
        $t->same([10.0, 20.0, 300.0, 740.0], $toc[1]['view_position']);
        $t->same(['left' => 10.0, 'bottom' => 20.0, 'right' => 300.0, 'top' => 740.0], $toc[1]['view_parameters']);

        $metadata = $extractor->getCatalogPageViewMetadata($pdf);
        $t->same(['open_action'], $metadata['source']);
        $t->same('Page Four', $metadata['open_action']['destination']);
        $t->same(0, $metadata['open_action']['page']);
        $t->same('FitR', $metadata['open_action']['view_mode']);
    },
    'extracts page transition duration and additional action review metadata' => static function (TestRunner $t) use ($pagePresentationPdf): void {
        $pages = (new PdfOutlineExtractor())->getPageTransitionActionMetadata($pagePresentationPdf());

        $t->same(2, count($pages));
        $t->same(0, $pages[0]['pnum']);
        $t->same(3, $pages[0]['page_object']);
        $t->same(8.0, $pages[0]['display_duration']);
        $t->same(
            [
                'style' => 'Dissolve',
                'duration' => 1.5,
                'dimension' => null,
                'motion' => null,
                'direction' => null,
                'scale' => null,
                'opaque_background' => null,
            ],
            $pages[0]['transition']
        );
        $t->same(2, count($pages[0]['actions']));
        $t->same('O', $pages[0]['actions'][0]['event']);
        $t->same('page_open', $pages[0]['actions'][0]['event_label']);
        $t->same('URI', $pages[0]['actions'][0]['action_type']);
        $t->same('review-uri', $pages[0]['actions'][0]['safety']);
        $t->same('https://example.com/slide-notes', $pages[0]['actions'][0]['uri']);
        $t->same(true, $pages[0]['actions'][0]['is_safe_uri']);
        $t->same(6, $pages[0]['actions'][0]['action_object']);
        $t->same(false, $pages[0]['actions'][0]['executes_on_import']);
        $t->same('C', $pages[0]['actions'][1]['event']);
        $t->same('page_close', $pages[0]['actions'][1]['event_label']);
        $t->same('GoToR', $pages[0]['actions'][1]['action_type']);
        $t->same('remote-document-review', $pages[0]['actions'][1]['safety']);
        $t->same('deck-appendix.pdf', $pages[0]['actions'][1]['file']);
        $t->same('Slide 2', $pages[0]['actions'][1]['destination']);
        $t->same(true, $pages[0]['actions'][1]['new_window']);

        $t->same(1, $pages[1]['pnum']);
        $t->same(4, $pages[1]['page_object']);
        $t->same(null, $pages[1]['display_duration']);
        $t->same(
            [
                'style' => 'Split',
                'duration' => 0.75,
                'dimension' => 'H',
                'motion' => 'O',
                'direction' => 90.0,
                'scale' => 0.5,
                'opaque_background' => true,
            ],
            $pages[1]['transition']
        );
        $t->same(3, count($pages[1]['actions']), 'duplicate chained actions are deduplicated within one page event.');
        $t->same('Launch', $pages[1]['actions'][0]['action_type']);
        $t->same('blocked-launch', $pages[1]['actions'][0]['safety']);
        $t->same('helper.exe', $pages[1]['actions'][0]['file']);
        $t->same('print', $pages[1]['actions'][0]['operation']);
        $t->same('URI', $pages[1]['actions'][1]['action_type']);
        $t->same('blocked-unsafe-uri', $pages[1]['actions'][1]['safety']);
        $t->same(false, $pages[1]['actions'][1]['is_safe_uri']);
        $t->same('GoTo', $pages[1]['actions'][2]['action_type']);
        $t->same('local-destination', $pages[1]['actions'][2]['safety']);
        $t->same(0, $pages[1]['actions'][2]['page']);
        $t->same('Start', $pages[1]['actions'][2]['destination']);
        $t->same(true, $pages[1]['actions'][2]['chained']);
        $t->same(7, $pages[1]['actions'][2]['action_object']);
    },
    'keeps page transitions and actions as metadata outside visible text extraction' => static function (TestRunner $t) use ($pagePresentationPdf): void {
        $text = (new PdfTextExtractor())->extractPlainText($pagePresentationPdf());
        $empty = (new PdfOutlineExtractor())->getPageTransitionActionMetadata("%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n%%EOF");

        $t->contains('Slide body stays visible', $text);
        $t->contains('Second slide stays clean', $text);
        $t->true(!str_contains($text, 'deck-appendix.pdf'));
        $t->true(!str_contains($text, 'javascript:alert'));
        $t->same([], $empty);
    },
];
