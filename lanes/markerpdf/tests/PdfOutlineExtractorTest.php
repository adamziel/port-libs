<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;

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
];
