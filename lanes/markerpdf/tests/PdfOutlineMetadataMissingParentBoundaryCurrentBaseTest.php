<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMissingParentBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline missing parent boundary intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline missing parent boundary appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Missing Parent Boundary Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R /First 8 0 R /Count -1 >>\nendobj\n"
        . "7 0 obj\n<< /Title (Missing Parent Boundary Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget >>\nendobj\n"
        . "8 0 obj\n<< /Title (Missing Parent Boundary Child) /Parent 6 0 R /Dest /ChildTarget /Next 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Title (Orphan Outline After Child) /Dest /OrphanTarget /A 12 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (orphan-outline-action.pdf) /D (orphan-target) >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (ChildTarget) [3 0 R /XYZ 72 680 0] (OrphanTarget) [4 0 R /FitR 1 2 3 4]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'bounds document outline metadata when child Next points at an item without Parent' => static function (
        TestRunner $t
    ) use ($outlineMissingParentBoundaryPdf): void {
        $pdf = $outlineMissingParentBoundaryPdf();
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
            'Missing Parent Boundary Chapter',
            'Missing Parent Boundary Child',
            'Missing Parent Boundary Appendix',
        ], $outline['titles'] ?? []);
        $t->same([6, 8, 7], array_column($items, 'outline_object'));
        $t->same([5, 6, 5], array_column($items, 'parent_object'));
        $t->same([0, 0, 1], array_column($items, 'page'));
        $t->same(['FitH', 'XYZ', 'Fit'], array_column($items, 'view_mode'));
        $t->same(9, $items[1]['next_object'] ?? null, 'The orphan /Next object id remains reviewable but is not traversed.');
        $t->true(is_string($encoded) && !str_contains($encoded, 'Orphan Outline After Child'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'orphan-outline-action.pdf'));
    },
    'applies missing Parent outline boundary to TOC navigation and remote action rows' => static function (
        TestRunner $t
    ) use ($outlineMissingParentBoundaryPdf): void {
        $pdf = $outlineMissingParentBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $expectedTitles = [
            'Missing Parent Boundary Chapter',
            'Missing Parent Boundary Child',
            'Missing Parent Boundary Appendix',
        ];

        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same([1, 2, 1], array_column($toc, 'level'));
        $t->same([0, 0, 1], array_column($toc, 'page'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same([6, 8, 7], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same("Outline missing parent boundary intro body\nOutline missing parent boundary appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Orphan Outline After Child'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'orphan-outline-action.pdf'));
        $t->true(!str_contains($plainText, 'Missing Parent Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Missing Parent Boundary Child'));
        $t->true(!str_contains($plainText, 'Missing Parent Boundary Appendix'));
        $t->true(!str_contains($plainText, 'Orphan Outline After Child'));
    },
];
