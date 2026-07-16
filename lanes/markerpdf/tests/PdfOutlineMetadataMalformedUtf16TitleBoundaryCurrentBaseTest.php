<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMalformedUtf16TitleBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline malformed UTF16 title intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline malformed UTF16 title appendix body) Tj ET';
    $validUtf16Title = '<FEFF00430075007200720065006E00740020005200650076006900650077>';
    $malformedUtf16Title = '<FEFF004D0061006C0066006F0072006D0065FF>';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title {$validUtf16Title} /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title {$malformedUtf16Title} /Parent 5 0 R /Prev 6 0 R /A 12 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (malformed-title-remote.pdf) /D (malformed-title-target) /NewWindow true >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'rejects malformed UTF-16 outline titles from document metadata and navigation' => static function (
        TestRunner $t
    ) use ($outlineMalformedUtf16TitleBoundaryPdf): void {
        $pdf = $outlineMalformedUtf16TitleBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['Current Review'], $metadata['document_outline']['titles'] ?? []);
        $t->same(1, $metadata['document_outline']['item_count'] ?? null);
        $t->same(1, $metadata['document_outline']['resolved_destination_count'] ?? null);
        $t->same(['Current Review'], array_column($toc, 'title'));
        $t->same([0], array_column($toc, 'page'));
        $t->same(['FitH'], array_column($toc, 'view_mode'));
        $t->same(['Current Review'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Malformed'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'malformed-title-remote.pdf'));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'Malformed'));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'malformed-title-remote.pdf'));
    },
    'keeps malformed outline title action operands out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineMalformedUtf16TitleBoundaryPdf): void {
        $pdf = $outlineMalformedUtf16TitleBoundaryPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same("Outline malformed UTF16 title intro body\nOutline malformed UTF16 title appendix body", $plainText);
        $t->true(!str_contains($plainText, 'Current Review'));
        $t->true(!str_contains($plainText, 'Malformed'));
        $t->true(!str_contains($plainText, 'malformed-title-remote.pdf'));
        $t->true(!str_contains($plainText, 'malformed-title-target'));
    },
];
