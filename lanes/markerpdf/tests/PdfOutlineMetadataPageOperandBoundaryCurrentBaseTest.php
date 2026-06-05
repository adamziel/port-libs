<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlinePageOperandBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline page operand boundary intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline page operand boundary appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 9 0 R /Count 4 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Page Operand Boundary Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Invalid Numeric Dest Page Operand) /Parent 5 0 R /Prev 6 0 R /Dest [99 /FitH 640] /Next 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Title (Invalid Numeric Action Page Operand) /Parent 5 0 R /Prev 7 0 R /A 12 0 R /Next 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Title (Page Operand Boundary Appendix) /Parent 5 0 R /Prev 8 0 R /Dest /AppendixTarget >>\nendobj\n"
        . "12 0 obj\n<< /S /GoTo /D [88 /FitR 10 20 300 700] >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'keeps invalid local numeric page operands as unresolved outline document metadata' => static function (
        TestRunner $t
    ) use ($outlinePageOperandBoundaryPdf): void {
        $pdf = $outlinePageOperandBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(9, $outline['last_item_object'] ?? null);
        $t->same(4, $outline['declared_visible_count'] ?? null);
        $t->same(4, $outline['item_count'] ?? null);
        $t->same(2, $outline['resolved_destination_count'] ?? null);
        $t->same(2, $outline['unresolved_destination_count'] ?? null);
        $t->same(1, $outline['max_depth'] ?? null);
        $t->same([
            'Page Operand Boundary Chapter',
            'Invalid Numeric Dest Page Operand',
            'Invalid Numeric Action Page Operand',
            'Page Operand Boundary Appendix',
        ], $outline['titles'] ?? []);
        $t->same([6, 7, 8, 9], array_column($items, 'outline_object'));
        $t->same([5, 5, 5, 5], array_column($items, 'parent_object'));
        $t->same([0, null, null, 1], array_map(
            static fn (array $item): ?int => $item['page'] ?? null,
            $items
        ));
        $t->same([true, false, false, true], array_column($items, 'destination_resolved'));
        $t->same('GoTo', $items[2]['action_type'] ?? null);
        $t->same(12, $items[2]['action_object'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, '"page":99'));
        $t->true(is_string($encoded) && !str_contains($encoded, '"page":88'));
    },
    'rejects invalid local numeric page operands from TOC and navigation review rows' => static function (
        TestRunner $t
    ) use ($outlinePageOperandBoundaryPdf): void {
        $pdf = $outlinePageOperandBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $plainToc = $outlineExtractor->getPdfToc($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $expectedTitles = [
            'Page Operand Boundary Chapter',
            'Page Operand Boundary Appendix',
        ];

        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same($expectedTitles, array_column($plainToc, 'title'));
        $t->same([1, 1], array_column($toc, 'level'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same([6, 9], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([0, 1], array_column($navigation['outline'] ?? [], 'page'));
        $actionRows = $navigation['outline_action_review_actions'] ?? [];
        $t->same(['Invalid Numeric Action Page Operand'], array_column($actionRows, 'outline_title'));
        $t->same(['GoTo'], array_column($actionRows, 'action_type'));
        $t->same(['unsupported-action-review'], array_column($actionRows, 'safety'));
        $t->same([null], array_map(
            static fn (array $row): ?int => $row['page'] ?? null,
            $actionRows
        ));
        $t->same([], $remoteActions);
        $t->same("Outline page operand boundary intro body\nOutline page operand boundary appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Invalid Numeric Dest Page Operand'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, '"page":99'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, '"page":88'));
        $t->true(!str_contains($plainText, 'Page Operand Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Invalid Numeric Dest Page Operand'));
        $t->true(!str_contains($plainText, 'Invalid Numeric Action Page Operand'));
        $t->true(!str_contains($plainText, 'Page Operand Boundary Appendix'));
    },
];
