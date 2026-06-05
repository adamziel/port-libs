<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineTitleBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline title boundary intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline title boundary appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 10 0 R /Count 4 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Title Boundary Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Parent 5 0 R /Prev 6 0 R /Next 10 0 R /First 8 0 R /Last 9 0 R /Count 2 /A 12 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Title (Stale Child Under Untitled Outline) /Parent 7 0 R /Dest /StaleChild /Next 9 0 R /A 13 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Title (Stale Remote Child Under Untitled Outline) /Parent 7 0 R /Prev 8 0 R /A 14 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Title (Title Boundary Appendix) /Parent 5 0 R /Prev 7 0 R /Dest /AppendixTarget >>\nendobj\n"
        . "12 0 obj\n<< /S /JavaScript /JS (app.alert\\('untitled outline parent action'\\)) >>\nendobj\n"
        . "13 0 obj\n<< /S /URI /URI (https://example.com/stale-untitled-outline-child) >>\nendobj\n"
        . "14 0 obj\n<< /S /GoToR /F (stale-untitled-outline-child.pdf) /D (stale-child) >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (StaleChild) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'treats untitled outline items as child traversal boundaries in document metadata' => static function (
        TestRunner $t
    ) use ($outlineTitleBoundaryPdf): void {
        $pdf = $outlineTitleBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(10, $outline['last_item_object'] ?? null);
        $t->same(4, $outline['declared_visible_count'] ?? null);
        $t->same(2, $outline['item_count'] ?? null);
        $t->same(2, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(1, $outline['max_depth'] ?? null);
        $t->same(['Title Boundary Chapter', 'Title Boundary Appendix'], $outline['titles'] ?? []);
        $t->same([6, 10], array_column($items, 'outline_object'));
        $t->same([5, 5], array_column($items, 'parent_object'));
        $t->same(7, $items[0]['next_object'] ?? null);
        $t->same(7, $items[1]['previous_object'] ?? null);
        $t->same([0, 1], array_column($items, 'page'));
        $t->same(['FitH', 'Fit'], array_column($items, 'view_mode'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Child Under Untitled Outline'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Remote Child Under Untitled Outline'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'untitled outline parent action'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-untitled-outline-child'));
    },
    'applies untitled outline boundaries to TOC navigation and remote action review' => static function (
        TestRunner $t
    ) use ($outlineTitleBoundaryPdf): void {
        $pdf = $outlineTitleBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $expectedTitles = ['Title Boundary Chapter', 'Title Boundary Appendix'];

        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same([1, 1], array_column($toc, 'level'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same([6, 10], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same("Outline title boundary intro body\nOutline title boundary appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Child Under Untitled Outline'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stale-untitled-outline-child'));
        $t->true(!str_contains($plainText, 'Title Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Title Boundary Appendix'));
        $t->true(!str_contains($plainText, 'Stale Child Under Untitled Outline'));
        $t->true(!str_contains($plainText, 'untitled outline parent action'));
        $t->true(!str_contains($plainText, 'stale-untitled-outline-child'));
    },
];
