<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineTitleOperandBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline title operand boundary intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline title operand boundary appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 10 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Malformed Direct Title Operand) 99 0 R /Parent 5 0 R /Dest /ChapterStart /Next 10 0 R /First 8 0 R /Last 8 0 R /Count 1 /A 12 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Title (Stale Child Under Malformed Direct Title) /Parent 6 0 R /Dest /StaleChild /A 13 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Title (Safe Direct Title Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (malformed-direct-title.pdf) /D (malformed-title) /Next 14 0 R >>\nendobj\n"
        . "13 0 obj\n<< /S /URI /URI (https://example.com/stale-direct-title-child) >>\nendobj\n"
        . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('malformed direct title action'\\)) >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (StaleChild) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'rejects direct outline title values with trailing top level operands from document metadata' => static function (
        TestRunner $t
    ) use ($outlineTitleOperandBoundaryPdf): void {
        $pdf = $outlineTitleOperandBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $lightweightMetadata = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedLightweight = json_encode($lightweightMetadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(10, $outline['last_item_object'] ?? null);
        $t->same(2, $outline['declared_visible_count'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(['Safe Direct Title Appendix'], $outline['titles'] ?? []);
        $t->same([10], array_column($items, 'outline_object'));
        $t->same([5], array_column($items, 'parent_object'));
        $t->same([6], array_column($items, 'previous_object'));
        $t->same([1], array_column($items, 'page'));
        $t->same(['Fit'], array_column($items, 'view_mode'));
        $t->same([
            ['title' => 'Safe Direct Title Appendix', 'level' => 1, 'page' => 1],
        ], $lightweightMetadata['pdf_toc']);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Malformed Direct Title Operand'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Child Under Malformed Direct Title'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'malformed-direct-title.pdf'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'malformed direct title action'));
        $t->true(is_string($encodedLightweight) && !str_contains($encodedLightweight, 'Malformed Direct Title Operand'));
    },
    'applies direct outline title operand boundaries to TOC navigation action review and visible text' => static function (
        TestRunner $t
    ) use ($outlineTitleOperandBoundaryPdf): void {
        $pdf = $outlineTitleOperandBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $plainToc = $outlineExtractor->getPdfToc($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['Safe Direct Title Appendix'], array_column($toc, 'title'));
        $t->same(['Safe Direct Title Appendix'], array_column($plainToc, 'title'));
        $t->same([1], array_column($toc, 'page'));
        $t->same(['Fit'], array_column($toc, 'view_mode'));
        $t->same(['Safe Direct Title Appendix'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same([10], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same("Outline title operand boundary intro body\nOutline title operand boundary appendix body", $plainText);
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'Malformed Direct Title Operand'));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'Stale Child Under Malformed Direct Title'));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'malformed-direct-title.pdf'));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'stale-direct-title-child'));
        $t->true(!str_contains($plainText, 'Malformed Direct Title Operand'));
        $t->true(!str_contains($plainText, 'Safe Direct Title Appendix'));
        $t->true(!str_contains($plainText, 'malformed direct title action'));
    },
];
