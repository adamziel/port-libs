<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineZeroCountChildBoundaryPdf = static function (): string {
    $chapterContent = 'BT /F1 12 Tf 72 720 Td (Outline zero count chapter body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline zero count appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Zero Count Boundary Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R /First 8 0 R /Last 8 0 R /Count 0 >>\nendobj\n"
        . "7 0 obj\n<< /Title (Zero Count Boundary Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget >>\nendobj\n"
        . "8 0 obj\n<< /Title (Zero Count Hidden Child) /Parent 6 0 R /Dest /HiddenChildTarget /A 12 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (zero-count-hidden-child.pdf) /D (hidden-child-target) /NewWindow true >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (HiddenChildTarget) [4 0 R /FitR 1 2 3 4]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($chapterContent) . " >>\nstream\n{$chapterContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'does not traverse outline children when item Count declares zero descendants in document metadata' => static function (
        TestRunner $t
    ) use ($outlineZeroCountChildBoundaryPdf): void {
        $pdf = $outlineZeroCountChildBoundaryPdf();
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
        $t->same(2, $outline['item_count'] ?? null);
        $t->same(2, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(1, $outline['max_depth'] ?? null);
        $t->same([
            'Zero Count Boundary Chapter',
            'Zero Count Boundary Appendix',
        ], $outline['titles'] ?? []);
        $t->same([6, 7], array_column($items, 'outline_object'));
        $t->same([5, 5], array_column($items, 'parent_object'));
        $t->same([1, 1], array_column($items, 'level'));
        $t->same([0, 1], array_column($items, 'page'));
        $t->same(['FitH', 'Fit'], array_column($items, 'view_mode'));
        $t->same(8, $items[0]['first_child_object'] ?? null);
        $t->same(8, $items[0]['last_child_object'] ?? null);
        $t->same(0, $items[0]['outline_count'] ?? null);
        $t->same(0, $items[0]['descendant_count'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Zero Count Hidden Child'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'zero-count-hidden-child.pdf'));
    },
    'applies zero Count child boundary to TOC navigation and remote outline actions' => static function (
        TestRunner $t
    ) use ($outlineZeroCountChildBoundaryPdf): void {
        $pdf = $outlineZeroCountChildBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $expectedTitles = [
            'Zero Count Boundary Chapter',
            'Zero Count Boundary Appendix',
        ];

        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same([1, 1], array_column($toc, 'level'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same([6, 7], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same("Outline zero count chapter body\nOutline zero count appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Zero Count Hidden Child'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'zero-count-hidden-child.pdf'));
        $t->true(!str_contains($plainText, 'Zero Count Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Zero Count Boundary Appendix'));
        $t->true(!str_contains($plainText, 'Zero Count Hidden Child'));
    },
];
