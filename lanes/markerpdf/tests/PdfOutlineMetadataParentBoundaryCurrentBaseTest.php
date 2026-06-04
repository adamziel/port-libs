<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineParentBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline parent boundary intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline parent boundary appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Parent Boundary Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R /First 8 0 R /Last 8 0 R /Count -1 >>\nendobj\n"
        . "7 0 obj\n<< /Title (Parent Boundary Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget >>\nendobj\n"
        . "8 0 obj\n<< /Title (Parent Boundary Child) /Parent 6 0 R /Dest /ChildTarget /Next 7 0 R >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (ChildTarget) [3 0 R /XYZ 72 680 0]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'bounds document outline metadata traversal by each item parent object' => static function (
        TestRunner $t
    ) use ($outlineParentBoundaryPdf): void {
        $pdf = $outlineParentBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(true, $outline['review_only'] ?? null);
        $t->same(false, $outline['payload_included'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(7, $outline['last_item_object'] ?? null);
        $t->same(2, $outline['declared_visible_count'] ?? null);
        $t->same(3, $outline['item_count'] ?? null);
        $t->same(3, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(2, $outline['max_depth'] ?? null);
        $t->same([
            'Parent Boundary Chapter',
            'Parent Boundary Child',
            'Parent Boundary Appendix',
        ], $outline['titles'] ?? []);
        $t->same([
            'Parent Boundary Chapter',
            'Parent Boundary Child',
            'Parent Boundary Appendix',
        ], array_column($items, 'title'));
        $t->same([1, 2, 1], array_column($items, 'level'));
        $t->same([6, 8, 7], array_column($items, 'outline_object'));
        $t->same([5, 6, 5], array_column($items, 'parent_object'));
        $t->same([0, 0, 1], array_column($items, 'page'));
        $t->same(['FitH', 'XYZ', 'Fit'], array_column($items, 'view_mode'));
        $t->same('AppendixTarget', $items[2]['destination'] ?? null);
        $t->same(7, $items[0]['next_object'] ?? null);
        $t->same(7, $items[1]['next_object'] ?? null);
    },
    'applies the same outline parent boundary to TOC and navigation review rows' => static function (
        TestRunner $t
    ) use ($outlineParentBoundaryPdf): void {
        $pdf = $outlineParentBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same([
            'Parent Boundary Chapter',
            'Parent Boundary Child',
            'Parent Boundary Appendix',
        ], array_column($toc, 'title'));
        $t->same([1, 2, 1], array_column($toc, 'level'));
        $t->same([0, 0, 1], array_column($toc, 'page'));
        $t->same([
            'Parent Boundary Chapter',
            'Parent Boundary Child',
            'Parent Boundary Appendix',
        ], array_column($navigation['outline'] ?? [], 'title'));
        $t->same([1, 2, 1], array_column($navigation['outline'] ?? [], 'level'));
        $t->same([6, 8, 7], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([5, 6, 5], array_column($navigation['outline'] ?? [], 'parent_object'));
        $t->same("Outline parent boundary intro body\nOutline parent boundary appendix body", $plainText);
        $t->true(!str_contains($plainText, 'Parent Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Parent Boundary Child'));
        $t->true(!str_contains($plainText, 'Parent Boundary Appendix'));
    },
];
