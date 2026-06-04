<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineGenerationBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline generation boundary intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline generation boundary appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Generation Boundary Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R /First 8 1 R /Last 8 1 R /Count -1 >>\nendobj\n"
        . "7 0 obj\n<< /Title (Generation Boundary Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget /Next 9 1 R /A 12 1 R >>\nendobj\n"
        . "8 0 obj\n<< /Title (Stale Generation Child) /Parent 6 0 R /Dest /StaleChildTarget /A 13 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Title (Stale Generation Sibling) /Parent 5 0 R /Dest /StaleSiblingTarget /A 14 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (stale-action.pdf) /D (stale-action-target) >>\nendobj\n"
        . "13 0 obj\n<< /S /GoToR /F (stale-child.pdf) /D (stale-child-target) >>\nendobj\n"
        . "14 0 obj\n<< /S /GoToR /F (stale-sibling.pdf) /D (stale-sibling-target) >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (StaleChildTarget) [4 0 R /FitR 1 2 3 4] (StaleSiblingTarget) [4 1 R /FitB]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'rejects mismatched generation outline item references in document metadata' => static function (
        TestRunner $t
    ) use ($outlineGenerationBoundaryPdf): void {
        $pdf = $outlineGenerationBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(2, $outline['declared_visible_count'] ?? null);
        $t->same(2, $outline['item_count'] ?? null);
        $t->same(2, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(1, $outline['max_depth'] ?? null);
        $t->same([
            'Generation Boundary Chapter',
            'Generation Boundary Appendix',
        ], $outline['titles'] ?? []);
        $t->same([6, 7], array_column($items, 'outline_object'));
        $t->same([5, 5], array_column($items, 'parent_object'));
        $t->same([0, 1], array_column($items, 'page'));
        $t->same(['FitH', 'Fit'], array_column($items, 'view_mode'));
        $t->same(8, $items[0]['first_child_object'] ?? null, 'The stale child object id remains reviewable but is not traversed.');
        $t->same(9, $items[1]['next_object'] ?? null, 'The stale sibling object id remains reviewable but is not traversed.');
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Generation Child'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Generation Sibling'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-action.pdf'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-child.pdf'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-sibling.pdf'));
    },
    'applies generation exact outline boundaries to TOC and navigation review rows' => static function (
        TestRunner $t
    ) use ($outlineGenerationBoundaryPdf): void {
        $pdf = $outlineGenerationBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $expectedTitles = [
            'Generation Boundary Chapter',
            'Generation Boundary Appendix',
        ];

        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same([1, 1], array_column($toc, 'level'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same([6, 7], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same("Outline generation boundary intro body\nOutline generation boundary appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Generation Child'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Generation Sibling'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stale-action.pdf'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stale-child.pdf'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stale-sibling.pdf'));
        $t->true(!str_contains($plainText, 'Generation Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Generation Boundary Appendix'));
        $t->true(!str_contains($plainText, 'Stale Generation Child'));
        $t->true(!str_contains($plainText, 'Stale Generation Sibling'));
    },
];
