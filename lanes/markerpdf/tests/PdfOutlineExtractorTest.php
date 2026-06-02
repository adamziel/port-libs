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

$indirectDestinationViewOperandPdf = static function (): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /OpenAction 18 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 3 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Indirect Zoom) /Parent 5 0 R /Dest [3 0 R 12 0 R 13 0 R 14 0 R 15 0 R] /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Indirect Left Fit) /Parent 5 0 R /Dest /fit-left /Next 9 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Names [(fit-left) << /D [4 0 R 16 0 R 17 0 R] >>] >>\nendobj\n"
        . "9 0 obj\n<< /Title (Indirect Rectangle) /Parent 5 0 R /A << /S /GoTo /D 18 0 R >> >>\nendobj\n"
        . "12 0 obj\n/XYZ\nendobj\n"
        . "13 0 obj\n72\nendobj\n"
        . "14 0 obj\n620\nendobj\n"
        . "15 0 obj\n0\nendobj\n"
        . "16 0 obj\n/FitV\nendobj\n"
        . "17 0 obj\n222\nendobj\n"
        . "18 0 obj\n[4 0 R 19 0 R 20 0 R 21 0 R 22 0 R 23 0 R]\nendobj\n"
        . "19 0 obj\n/FitR\nendobj\n"
        . "20 0 obj\n10\nendobj\n"
        . "21 0 obj\n20\nendobj\n"
        . "22 0 obj\n300\nendobj\n"
        . "23 0 obj\n740\nendobj\n"
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

$namedDestinationFitReviewPdf = static function (): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 4 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Bounding Box Fit) /Parent 5 0 R /Dest /box-fit /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Top Bounding Fit) /Parent 5 0 R /Dest /top-fit /Next 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Title (Left Bounding Fit) /Parent 5 0 R /Dest /left-fit /Next 10 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Title (Zoom Review) /Parent 5 0 R /Dest /zoom-review >>\nendobj\n"
        . "8 0 obj\n<< /Names [(box-fit) [3 0 R /FitB 111 222] (top-fit) [4 0 R /FitBH null 999] (left-fit) [4 0 R /FitBV 144 888 999] (zoom-review) [3 0 R /XYZ 72 640 0 777]] >>\nendobj\n"
        . "%%EOF";
};

$pagePresentationPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Slide body stays visible) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Second slide stays clean) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /PageLabels 21 0 R /PageLayout /TwoPageRight /PageMode /UseOutlines /ViewerPreferences 8 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 8 /Trans 5 0 R /AA << /O 6 0 R /C << /S /GoToR /F (deck-appendix.pdf) /D /Slide#202 /NewWindow true >> >> /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Trans << /S /Split /D .75 /Dm /H /M /O /Di 90 /SS .5 /B true >> /AA << /O << /S /Launch /Win << /F (helper.exe) /O (print) >> >> /C << /S /URI /URI (javascript:alert\\(1\\)) /Next [7 0 R 7 0 R] >> >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /S /Dissolve /D 1.5 >>\nendobj\n"
        . "6 0 obj\n<< /S /URI /URI (https://example.com/slide-notes) >>\nendobj\n"
        . "7 0 obj\n<< /S /GoTo /D /Start >>\nendobj\n"
        . "8 0 obj\n<< /DisplayDocTitle true /Direction 25 0 R /PrintScaling /None /PrintClip /Bogus /NumCopies 26 0 R /Enforce [ /PrintScaling /Bogus /NumCopies ] >>\nendobj\n"
        . "20 0 obj\n<< /Names [(Start) [3 0 R /Fit]] >>\nendobj\n"
        . "21 0 obj\n<< /Kids [22 0 R 23 0 R] >>\nendobj\n"
        . "22 0 obj\n<< /Limits [0 0] /Nums [0 << /S /r /P (intro-) /St 2 >> 2 << /S /D /P (stale-) /St 99 >>] >>\nendobj\n"
        . "23 0 obj\n<< /Limits [1 1] /Nums [1 << /S /D /P (Slide ) /St 7 >>] >>\nendobj\n"
        . "25 0 obj\n/R2L\nendobj\n"
        . "26 0 obj\n2\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "%%EOF";
};

$navigationReviewPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Preface text stays visible) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Chapter target text stays visible) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /OpenAction 40 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /PageMode /UseOutlines /ViewerPreferences << /DisplayDocTitle true >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 5 /Trans 15 0 R /AA << /O 42 0 R >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Chapter Target) /Parent 5 0 R /Dest /ChapterStart >>\nendobj\n"
        . "8 0 obj\n<< /Names [(ChapterStart) 14 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /D 13 0 R >>\nendobj\n"
        . "13 0 obj\n[4 0 R 16 0 R 17 0 R]\nendobj\n"
        . "15 0 obj\n<< /S /Blinds /D .5 /Dm /V >>\nendobj\n"
        . "16 0 obj\n/FitH\nendobj\n"
        . "17 0 obj\n640\nendobj\n"
        . "20 0 obj\n<< /Nums [0 << /S /r /P (front-) /St 2 >> 1 << /S /D /P (Body ) /St 1 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /S /GoTo /D 41 0 R /Next 42 0 R >>\nendobj\n"
        . "41 0 obj\n(ChapterStart)\nendobj\n"
        . "42 0 obj\n<< /S /URI /URI (https://example.com/chapter-notes) >>\nendobj\n"
        . "%%EOF";
};

$navigationTaggedStructurePdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Preface page stays visible) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf '
        . '/BodyAlias << /MCID 1 >> BDC 72 704 Td (Target body second) Tj EMC '
        . '/DeckTitle << /MCID 0 >> BDC 72 720 Td (Target heading first) Tj EMC '
        . '/Artifact << /MCID 2 >> BDC 72 680 Td (Target artifact noise) Tj EMC ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /OpenAction 40 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /MarkInfo << /Marked true >> /StructTreeRoot 50 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 7 0 R >> >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Accessible Chapter Target) /Parent 5 0 R /Dest /ChapterStart >>\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "8 0 obj\n<< /Names [(ChapterStart) [4 0 R /FitH 640] (StaleTarget) [99 0 R /Fit]] >>\nendobj\n"
        . "20 0 obj\n<< /Nums [0 << /S /r /P (front-) /St 2 >> 1 << /S /D /P (Body ) /St 1 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /S /GoTo /D /ChapterStart >>\nendobj\n"
        . "50 0 obj\n<< /Type /StructTreeRoot /RoleMap 60 0 R /ParentTree 55 0 R /K [52 0 R 53 0 R] >>\nendobj\n"
        . "52 0 obj\n<< /Type /StructElem /S /DeckTitle /P 50 0 R /K 0 >>\nendobj\n"
        . "53 0 obj\n<< /Type /StructElem /S /BodyAlias /P 50 0 R /K 1 >>\nendobj\n"
        . "55 0 obj\n<< /Nums [0 [52 0 R 53 0 R]] >>\nendobj\n"
        . "60 0 obj\n<< /DeckTitle /H2 /BodyAlias /P >>\nendobj\n"
        . "%%EOF";
};

$outlineNameTreeTransitionActionPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Title slide stays visible) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Deck target stays visible) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 9 /Trans 15 0 R /AA << /O 42 0 R /C << /S /URI /URI (javascript:alert\\(1\\)) >> >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Deck Target) /Parent 5 0 R /Dest /DeckStart /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Action Target) /Parent 5 0 R /A << /S /GoTo /D 41 0 R >> >>\nendobj\n"
        . "8 0 obj\n<< /Kids [9 0 R 10 0 R] >>\nendobj\n"
        . "9 0 obj\n<< /Limits [(A) (M)] /Names [(DeckStart) 14 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Limits [(N) (Z)] /Names [(Stale) [99 0 R /Fit]] >>\nendobj\n"
        . "13 0 obj\n[4 0 R 16 0 R 17 0 R]\nendobj\n"
        . "14 0 obj\n<< /D 13 0 R >>\nendobj\n"
        . "15 0 obj\n<< /S /Fly /D .75 /M /I /Di 270 /SS .8 /B false >>\nendobj\n"
        . "16 0 obj\n/FitH\nendobj\n"
        . "17 0 obj\n610\nendobj\n"
        . "20 0 obj\n<< /Nums [0 << /S /D /P (Slide ) /St 1 >> 1 << /S /D /P (Deck ) /St 5 >>] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "41 0 obj\n(DeckStart)\nendobj\n"
        . "42 0 obj\n<< /S /URI /URI (https://example.com/deck-notes) >>\nendobj\n"
        . "%%EOF";
};

$outlineActionTransitionNavigationPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Cover page stays visible) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Deck page stays visible) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 6 /Trans 16 0 R /AA << /O 15 0 R >> /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Deck Action) /Parent 5 0 R /A 12 0 R /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (External Notes) /Parent 5 0 R /A << /S /URI /URI (javascript:alert\\(1\\)) >> >>\nendobj\n"
        . "8 0 obj\n<< /Names [(DeckStart) 9 0 R] >>\nendobj\n"
        . "9 0 obj\n<< /D [4 0 R /XYZ 72 640 0] >>\nendobj\n"
        . "12 0 obj\n<< /S /GoTo /D /DeckStart /Next [13 0 R 14 0 R 14 0 R] >>\nendobj\n"
        . "13 0 obj\n<< /S /URI /URI (https://example.com/deck-notes) >>\nendobj\n"
        . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden outline script'\\)) >>\nendobj\n"
        . "15 0 obj\n<< /S /URI /URI (https://example.com/page-open) >>\nendobj\n"
        . "16 0 obj\n<< /S /Push /D 1 /Di 0 >>\nendobj\n"
        . "20 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Deck ) /St 3 >>] >>\nendobj\n"
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
    'reviews catalog OpenAction chained actions without executing hidden followups' => static function (TestRunner $t) use ($openActionPdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $openActionPdf(
            '5 0 R',
            "5 0 obj\n<< /S /URI /URI (https://example.com/start) /Next [6 0 R 7 0 R 7 0 R] >>\nendobj\n"
                . "6 0 obj\n<< /S /Launch /F (post-import-helper.exe) /Win << /O (open) >> >>\nendobj\n"
                . "7 0 obj\n<< /S /GoTo /D (Review Page) >>\nendobj\n"
        );

        $actions = $extractor->getOpenActionReviewActions($pdf);

        $t->same(3, count($actions), 'catalog OpenAction /Next rows are reviewed once.');
        $t->same(['URI', 'Launch', 'GoTo'], array_column($actions, 'action_type'));
        $t->same(['review-uri', 'blocked-launch', 'local-destination'], array_column($actions, 'safety'));
        $t->same([5, 6, 7], array_column($actions, 'action_object'));
        $t->same([null, true, true], [
            $actions[0]['chained'] ?? null,
            $actions[1]['chained'] ?? null,
            $actions[2]['chained'] ?? null,
        ]);
        $t->same('post-import-helper.exe', $actions[1]['file']);
        $t->same(1, $actions[2]['page']);
        $t->same('Review Page', $actions[2]['destination']);
        $t->same([false, false, false], array_column($actions, 'executes_on_import'));
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
    'resolves indirect destination view mode and coordinate operands' => static function (TestRunner $t) use ($indirectDestinationViewOperandPdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $indirectDestinationViewOperandPdf();
        $toc = $extractor->getPdfTocWithDestinationViews($pdf);

        $t->same(
            [
                ['title' => 'Indirect Zoom', 'level' => 1, 'page' => 0, 'destination' => null],
                ['title' => 'Indirect Left Fit', 'level' => 1, 'page' => 1, 'destination' => 'fit-left'],
                ['title' => 'Indirect Rectangle', 'level' => 1, 'page' => 1, 'destination' => null],
            ],
            $extractor->getPdfToc($pdf)
        );
        $t->same('XYZ', $toc[0]['view_mode']);
        $t->same([72.0, 620.0, null], $toc[0]['view_position']);
        $t->same(['left' => 72.0, 'top' => 620.0, 'zoom' => null], $toc[0]['view_parameters']);
        $t->same('FitV', $toc[1]['view_mode']);
        $t->same([222.0], $toc[1]['view_position']);
        $t->same(['left' => 222.0], $toc[1]['view_parameters']);
        $t->same('FitR', $toc[2]['view_mode']);
        $t->same([10.0, 20.0, 300.0, 740.0], $toc[2]['view_position']);
        $t->same(['left' => 10.0, 'bottom' => 20.0, 'right' => 300.0, 'top' => 740.0], $toc[2]['view_parameters']);

        $metadata = $extractor->getCatalogPageViewMetadata($pdf);
        $t->same(['open_action'], $metadata['source']);
        $t->same(1, $metadata['open_action']['page']);
        $t->same('FitR', $metadata['open_action']['view_mode']);
        $t->same([10.0, 20.0, 300.0, 740.0], $metadata['open_action']['view_position']);
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
    'normalizes named destination Fit family operands for outline review metadata' => static function (TestRunner $t) use ($namedDestinationFitReviewPdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $namedDestinationFitReviewPdf();

        $t->same(
            [
                ['title' => 'Bounding Box Fit', 'level' => 1, 'page' => 0, 'destination' => 'box-fit'],
                ['title' => 'Top Bounding Fit', 'level' => 1, 'page' => 1, 'destination' => 'top-fit'],
                ['title' => 'Left Bounding Fit', 'level' => 1, 'page' => 1, 'destination' => 'left-fit'],
                ['title' => 'Zoom Review', 'level' => 1, 'page' => 0, 'destination' => 'zoom-review'],
            ],
            $extractor->getPdfToc($pdf)
        );

        $toc = $extractor->getPdfTocWithDestinationViews($pdf);
        $t->same(['FitB', 'FitBH', 'FitBV', 'XYZ'], array_column($toc, 'view_mode'));
        $t->same([], $toc[0]['view_position']);
        $t->same([], $toc[0]['view_parameters']);
        $t->same([null], $toc[1]['view_position']);
        $t->same(['top' => null], $toc[1]['view_parameters']);
        $t->same([144.0], $toc[2]['view_position']);
        $t->same(['left' => 144.0], $toc[2]['view_parameters']);
        $t->same([72.0, 640.0, null], $toc[3]['view_position']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => null], $toc[3]['view_parameters']);

        $navigation = $extractor->getNavigationReviewMetadata($pdf);
        $t->same(['outline'], $navigation['source']);
        $t->same(['box-fit', 'top-fit', 'left-fit', 'zoom-review'], array_column($navigation['outline'], 'destination'));
        $t->same([[], [null], [144.0], [72.0, 640.0, null]], array_column($navigation['outline'], 'view_position'));
        $t->same([[], ['top' => null], ['left' => 144.0], ['left' => 72.0, 'top' => 640.0, 'zoom' => null]], array_column($navigation['outline'], 'view_parameters'));
    },
    'extracts page transition duration and additional action review metadata' => static function (TestRunner $t) use ($pagePresentationPdf): void {
        $pages = (new PdfOutlineExtractor())->getPageTransitionActionMetadata($pagePresentationPdf());

        $t->same(2, count($pages));
        $t->same(0, $pages[0]['pnum']);
        $t->same(1, $pages[0]['page_number']);
        $t->same(3, $pages[0]['page_object']);
        $t->same('intro-ii', $pages[0]['page_label']);
        $t->same([
            'page_layout' => 'TwoPageRight',
            'page_mode' => 'UseOutlines',
            'viewer_preferences' => [
                'display_doc_title' => true,
                'direction' => 'R2L',
                'print_scaling' => 'None',
                'enforce' => ['PrintScaling', 'NumCopies'],
                'num_copies' => 2,
            ],
        ], $pages[0]['catalog_view']);
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
        $t->same(2, $pages[1]['page_number']);
        $t->same(4, $pages[1]['page_object']);
        $t->same('Slide 7', $pages[1]['page_label']);
        $t->same($pages[0]['catalog_view'], $pages[1]['catalog_view']);
        $t->true(!str_starts_with($pages[1]['page_label'], 'stale-'));
        $t->true(!array_key_exists('print_clip', $pages[1]['catalog_view']['viewer_preferences']));
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
    'aligns PageLabels and viewer preferences with page transition review boundaries' => static function (TestRunner $t) use ($pagePresentationPdf): void {
        $pages = (new PdfOutlineExtractor())->getPageTransitionActionMetadata($pagePresentationPdf());
        $actions = [];
        foreach ($pages as $page) {
            foreach ($page['actions'] as $action) {
                $actions[] = $action;
            }
        }

        $t->same(['intro-ii', 'Slide 7'], array_column($pages, 'page_label'));
        $t->same([1, 2], array_column($pages, 'page_number'));
        $t->same(['Dissolve', 'Split'], array_map(
            static fn (array $page): ?string => $page['transition']['style'] ?? null,
            $pages
        ));
        $t->same('TwoPageRight', $pages[0]['catalog_view']['page_layout']);
        $t->same('UseOutlines', $pages[0]['catalog_view']['page_mode']);
        $t->same('R2L', $pages[0]['catalog_view']['viewer_preferences']['direction']);
        $t->same('None', $pages[0]['catalog_view']['viewer_preferences']['print_scaling']);
        $t->same(['PrintScaling', 'NumCopies'], $pages[0]['catalog_view']['viewer_preferences']['enforce']);
        $t->same(2, $pages[0]['catalog_view']['viewer_preferences']['num_copies']);
        $t->true(!array_key_exists('print_clip', $pages[0]['catalog_view']['viewer_preferences']));
        $t->true(!in_array('stale-99', array_column($pages, 'page_label'), true));
        $t->same([], array_values(array_filter(
            $actions,
            static fn (array $action): bool => ($action['executes_on_import'] ?? true) !== false
        )));
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
    'combines outline destinations OpenAction page labels and transitions for navigation review' => static function (TestRunner $t) use ($navigationReviewPdf): void {
        $extractor = new PdfOutlineExtractor();
        $metadata = $extractor->getNavigationReviewMetadata($navigationReviewPdf());

        $t->same(['outline', 'open_action', 'page_presentations'], $metadata['source']);
        $t->same(1, count($metadata['outline']));
        $t->same('Chapter Target', $metadata['outline'][0]['title']);
        $t->same(1, $metadata['outline'][0]['page']);
        $t->same('Body 1', $metadata['outline'][0]['page_label']);
        $t->same('ChapterStart', $metadata['outline'][0]['destination']);
        $t->same('FitH', $metadata['outline'][0]['view_mode']);
        $t->same([640.0], $metadata['outline'][0]['view_position']);
        $t->same(['top' => 640.0], $metadata['outline'][0]['view_parameters']);

        $t->true(isset($metadata['open_action_destination']));
        $t->same(1, $metadata['open_action_destination']['page']);
        $t->same('Body 1', $metadata['open_action_destination']['page_label']);
        $t->same('ChapterStart', $metadata['open_action_destination']['destination']);
        $t->same('FitH', $metadata['open_action_destination']['view_mode']);
        $t->same(['top' => 640.0], $metadata['open_action_destination']['view_parameters']);
        $t->same(5.0, $metadata['open_action_destination']['target_display_duration']);
        $t->same('Blinds', $metadata['open_action_destination']['target_page_transition']['style']);
        $t->same(0.5, $metadata['open_action_destination']['target_page_transition']['duration']);
        $t->same('V', $metadata['open_action_destination']['target_page_transition']['dimension']);

        $t->same(['GoTo', 'URI'], array_column($metadata['open_action_review_actions'], 'action_type'));
        $t->same(['local-destination', 'review-uri'], array_column($metadata['open_action_review_actions'], 'safety'));
        $t->same([false, false], array_column($metadata['open_action_review_actions'], 'executes_on_import'));
        $t->same([40, 42], array_column($metadata['open_action_review_actions'], 'action_object'));
        $t->same(true, $metadata['open_action_review_actions'][1]['chained']);

        $t->same(1, count($metadata['page_presentations']));
        $t->same('Body 1', $metadata['page_presentations'][0]['page_label']);
        $t->same('Blinds', $metadata['page_presentations'][0]['transition']['style']);
        $t->same('URI', $metadata['page_presentations'][0]['actions'][0]['action_type']);
        $t->same(false, $metadata['page_presentations'][0]['actions'][0]['executes_on_import']);

        $text = (new PdfTextExtractor())->extractPlainText($navigationReviewPdf());
        $t->contains('Preface text stays visible', $text);
        $t->contains('Chapter target text stays visible', $text);
        $t->true(!str_contains($text, 'https://example.com/chapter-notes'));
    },
    'attaches StructElem tagged content to page-label named-destination navigation review' => static function (TestRunner $t) use ($navigationTaggedStructurePdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $navigationTaggedStructurePdf();
        $metadata = $extractor->getNavigationReviewMetadata($pdf);

        $t->same(['outline', 'open_action', 'tagged_content', 'page_review'], $metadata['source']);
        $t->same(1, count($metadata['outline']));
        $t->same('Accessible Chapter Target', $metadata['outline'][0]['title']);
        $t->same(1, $metadata['outline'][0]['page']);
        $t->same('Body 1', $metadata['outline'][0]['page_label']);
        $t->same('ChapterStart', $metadata['outline'][0]['destination']);
        $t->same('FitH', $metadata['outline'][0]['view_mode']);
        $t->same(['top' => 640.0], $metadata['outline'][0]['view_parameters']);
        $t->same(['H2', 'P'], $metadata['outline'][0]['target_structure_roles']);
        $t->same(['Target heading first', 'Target body second'], array_column($metadata['outline'][0]['target_tagged_content'], 'text'));
        $t->same([0, 1], array_column($metadata['outline'][0]['target_tagged_content'], 'mcid'));
        $t->same(['DeckTitle', 'BodyAlias'], array_column($metadata['outline'][0]['target_tagged_content'], 'raw_role'));

        $t->same(1, count($metadata['open_action_review_actions']));
        $t->same('Body 1', $metadata['open_action_review_actions'][0]['page_label']);
        $t->same(['H2', 'P'], $metadata['open_action_review_actions'][0]['target_structure_roles']);
        $t->same(['Target heading first', 'Target body second'], array_column($metadata['open_action_review_actions'][0]['target_tagged_content'], 'text'));

        $t->true(isset($metadata['open_action_destination']));
        $t->same('Body 1', $metadata['open_action_destination']['page_label']);
        $t->same(['H2', 'P'], $metadata['open_action_destination']['target_structure_roles']);
        $t->same(['Target heading first', 'Target body second'], array_column($metadata['open_action_destination']['target_tagged_content'], 'text'));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains("Target heading first\nTarget body second", $plainText);
        $t->true(!str_contains($plainText, 'Target artifact noise'));
    },
    'annotates outline name tree targets with page transition and action review metadata' => static function (TestRunner $t) use ($outlineNameTreeTransitionActionPdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $outlineNameTreeTransitionActionPdf();
        $metadata = $extractor->getNavigationReviewMetadata($pdf);

        $t->same(['outline', 'page_presentations'], $metadata['source']);
        $t->same(2, count($metadata['outline']));
        $t->same(['Deck Target', 'Action Target'], array_column($metadata['outline'], 'title'));
        $t->same(['DeckStart', 'DeckStart'], array_column($metadata['outline'], 'destination'));

        foreach ($metadata['outline'] as $outline) {
            $t->same(1, $outline['page']);
            $t->same('Deck 5', $outline['page_label']);
            $t->same('FitH', $outline['view_mode']);
            $t->same([610.0], $outline['view_position']);
            $t->same(['top' => 610.0], $outline['view_parameters']);
            $t->true(array_key_exists('target_display_duration', $outline));
            $t->same(9.0, $outline['target_display_duration']);
            $t->same('Fly', $outline['target_page_transition']['style']);
            $t->same(0.75, $outline['target_page_transition']['duration']);
            $t->same('I', $outline['target_page_transition']['motion']);
            $t->same(270.0, $outline['target_page_transition']['direction']);
            $t->same(0.8, $outline['target_page_transition']['scale']);
            $t->same(false, $outline['target_page_transition']['opaque_background']);
            $t->same(['page_open', 'page_close'], array_column($outline['target_page_actions'], 'event_label'));
            $t->same(['review-uri', 'blocked-unsafe-uri'], array_column($outline['target_page_actions'], 'safety'));
            $t->same([false, false], array_column($outline['target_page_actions'], 'executes_on_import'));
        }

        $t->same(1, count($metadata['page_presentations']));
        $t->same('Deck 5', $metadata['page_presentations'][0]['page_label']);
        $t->same('Fly', $metadata['page_presentations'][0]['transition']['style']);
        $t->true(!array_key_exists('open_action_destination', $metadata));

        $text = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Title slide stays visible', $text);
        $t->contains('Deck target stays visible', $text);
        $t->true(!str_contains($text, 'https://example.com/deck-notes'));
        $t->true(!str_contains($text, 'javascript:alert'));
    },
    'surfaces outline action chains as review-only navigation metadata' => static function (TestRunner $t) use ($outlineActionTransitionNavigationPdf): void {
        $extractor = new PdfOutlineExtractor();
        $pdf = $outlineActionTransitionNavigationPdf();
        $metadata = $extractor->getNavigationReviewMetadata($pdf);

        $t->same(['outline', 'outline_actions', 'page_presentations'], $metadata['source']);
        $t->same(1, count($metadata['outline']));
        $t->same('Deck Action', $metadata['outline'][0]['title']);
        $t->same('Deck 3', $metadata['outline'][0]['page_label']);
        $t->same('XYZ', $metadata['outline'][0]['view_mode']);
        $t->same('Push', $metadata['outline'][0]['target_page_transition']['style']);
        $t->same(['page_open'], array_column($metadata['outline'][0]['target_page_actions'], 'event_label'));

        $actions = $metadata['outline_action_review_actions'];
        $t->same(4, count($actions), 'outline action rows include the local target, hidden chained actions, and non-local sibling action.');
        $t->same(['Deck Action', 'Deck Action', 'Deck Action', 'External Notes'], array_column($actions, 'outline_title'));
        $t->same([1, 1, 1, 1], array_column($actions, 'outline_level'));
        $t->same([6, 6, 6, 7], array_column($actions, 'outline_object'));
        $t->same(['GoTo', 'URI', 'JavaScript', 'URI'], array_column($actions, 'action_type'));
        $t->same(['local-destination', 'review-uri', 'blocked-javascript', 'blocked-unsafe-uri'], array_column($actions, 'safety'));
        $t->same([false, false, false, false], array_column($actions, 'executes_on_import'));
        $t->same([12, 13, 14, null], [
            $actions[0]['action_object'] ?? null,
            $actions[1]['action_object'] ?? null,
            $actions[2]['action_object'] ?? null,
            $actions[3]['action_object'] ?? null,
        ]);
        $t->same([null, true, true, null], [
            $actions[0]['chained'] ?? null,
            $actions[1]['chained'] ?? null,
            $actions[2]['chained'] ?? null,
            $actions[3]['chained'] ?? null,
        ]);
        $t->same('Deck 3', $actions[0]['page_label']);
        $t->same('DeckStart', $actions[0]['destination']);
        $t->same('Push', $actions[0]['target_page_transition']['style']);
        $t->same(6.0, $actions[0]['target_display_duration']);
        $t->same(['review-uri'], array_column($actions[0]['target_page_actions'], 'safety'));
        $t->same('https://example.com/deck-notes', $actions[1]['uri']);
        $t->same(false, $actions[3]['is_safe_uri']);

        $text = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Cover page stays visible', $text);
        $t->contains('Deck page stays visible', $text);
        $t->true(!str_contains($text, 'https://example.com/deck-notes'));
        $t->true(!str_contains($text, 'hidden outline script'));
        $t->true(!str_contains($text, 'javascript:alert'));
    },
    'returns stable empty navigation review metadata without a PDF catalog' => static function (TestRunner $t): void {
        $metadata = (new PdfOutlineExtractor())->getNavigationReviewMetadata('%PDF-1.4 no catalog here');

        $t->same([], $metadata['source']);
        $t->same([], $metadata['outline']);
        $t->same([], $metadata['open_action_review_actions']);
        $t->same([], $metadata['outline_action_review_actions']);
        $t->same([], $metadata['page_presentations']);
        $t->true(!array_key_exists('open_action_destination', $metadata));
    },
];
