<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineLastBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline last boundary intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline last boundary appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Last Boundary Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R /First 8 0 R /Last 8 0 R /Count -1 >>\nendobj\n"
        . "7 0 obj\n<< /Title (Last Boundary Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget /Next 9 0 R /A 12 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Title (Last Boundary Child) /Parent 6 0 R /Dest /ChildTarget /Next 10 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Title (Stale Root After Last) /Parent 5 0 R /Dest /StaleRootTarget /A 13 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Title (Stale Child After Last) /Parent 6 0 R /Dest /StaleChildTarget /A 14 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (appendix-review.pdf) /D (appendix-remote) /NewWindow true >>\nendobj\n"
        . "13 0 obj\n<< /S /GoToR /F (stale-root-after-last.pdf) /D (stale-root) >>\nendobj\n"
        . "14 0 obj\n<< /S /GoToR /F (stale-child-after-last.pdf) /D (stale-child) >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (ChildTarget) [3 0 R /XYZ 72 680 0] (StaleChildTarget) [4 0 R /FitR 1 2 3 4] (StaleRootTarget) [4 0 R /FitB]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'bounds document outline metadata traversal by declared Last item' => static function (
        TestRunner $t
    ) use ($outlineLastBoundaryPdf): void {
        $pdf = $outlineLastBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(7, $outline['last_item_object'] ?? null);
        $t->same(2, $outline['declared_visible_count'] ?? null);
        $t->same(3, $outline['item_count'] ?? null);
        $t->same(3, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(2, $outline['max_depth'] ?? null);
        $t->same([
            'Last Boundary Chapter',
            'Last Boundary Child',
            'Last Boundary Appendix',
        ], $outline['titles'] ?? []);
        $t->same([6, 8, 7], array_column($items, 'outline_object'));
        $t->same([5, 6, 5], array_column($items, 'parent_object'));
        $t->same([7, 10, 9], array_column($items, 'next_object'));
        $t->same([0, 0, 1], array_column($items, 'page'));
        $t->same(['FitH', 'XYZ', 'Fit'], array_column($items, 'view_mode'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Root After Last'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Child After Last'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-root-after-last.pdf'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-child-after-last.pdf'));
    },
    'applies Last boundary to TOC navigation and remote outline action review' => static function (
        TestRunner $t
    ) use ($outlineLastBoundaryPdf): void {
        $pdf = $outlineLastBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $expectedTitles = [
            'Last Boundary Chapter',
            'Last Boundary Child',
            'Last Boundary Appendix',
        ];

        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same([1, 2, 1], array_column($toc, 'level'));
        $t->same([0, 0, 1], array_column($toc, 'page'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same([6, 8, 7], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same(['Last Boundary Appendix'], array_column($navigation['outline_action_review_actions'] ?? [], 'outline_title'));
        $t->same(['GoToR'], array_column($navigation['outline_action_review_actions'] ?? [], 'action_type'));
        $t->same(['remote-document-review'], array_column($navigation['outline_action_review_actions'] ?? [], 'safety'));
        $t->same(['Last Boundary Appendix'], array_column($remoteActions, 'title'));
        $t->same(['appendix-review.pdf'], array_column($remoteActions, 'file'));
        $t->same("Outline last boundary intro body\nOutline last boundary appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Root After Last'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Child After Last'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stale-root-after-last.pdf'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stale-child-after-last.pdf'));
        $t->true(!str_contains($plainText, 'Last Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Last Boundary Child'));
        $t->true(!str_contains($plainText, 'Last Boundary Appendix'));
        $t->true(!str_contains($plainText, 'Stale Root After Last'));
        $t->true(!str_contains($plainText, 'Stale Child After Last'));
    },
];
