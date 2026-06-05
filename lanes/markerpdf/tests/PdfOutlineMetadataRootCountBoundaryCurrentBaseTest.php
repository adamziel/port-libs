<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineRootCountBoundaryPdf = static function (): string {
    $coverContent = 'BT /F1 12 Tf 72 720 Td (Outline root count cover body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline root count appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count -2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Root Count Collapsed Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Root Count Collapsed Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget >>\nendobj\n"
        . "8 0 obj\n<< /Title (Stale Root Count Tail) /Parent 5 0 R /Prev 7 0 R /Dest /StaleTarget >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (StaleTarget) [4 0 R /FitR 1 2 3 4]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($coverContent) . " >>\nstream\n{$coverContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'preserves collapsed outline root Count state in document metadata' => static function (
        TestRunner $t
    ) use ($outlineRootCountBoundaryPdf): void {
        $pdf = $outlineRootCountBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same($outline, $metadata['catalog']['document_outline'] ?? []);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(7, $outline['last_item_object'] ?? null);
        $t->same(true, $outline['has_children'] ?? null);
        $t->same(-2, $outline['outline_count'] ?? null);
        $t->same(2, $outline['declared_visible_count'] ?? null);
        $t->same(2, $outline['descendant_count'] ?? null);
        $t->same(false, $outline['is_open'] ?? null);
        $t->same(true, $outline['is_collapsed'] ?? null);
        $t->same('collapsed', $outline['structure_state'] ?? null);
        $t->same(2, $outline['item_count'] ?? null);
        $t->same(2, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(1, $outline['max_depth'] ?? null);
        $t->same([
            'Root Count Collapsed Chapter',
            'Root Count Collapsed Appendix',
        ], $outline['titles'] ?? []);
        $t->same([6, 7], array_column($items, 'outline_object'));
        $t->same([5, 5], array_column($items, 'parent_object'));
        $t->same([0, 1], array_column($items, 'page'));
        $t->same(['FitH', 'Fit'], array_column($items, 'view_mode'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Root Count Tail'));
    },
    'keeps collapsed root outline metadata out of visible WordPress text while TOC rows remain importable' => static function (
        TestRunner $t
    ) use ($outlineRootCountBoundaryPdf): void {
        $pdf = $outlineRootCountBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $expectedTitles = [
            'Root Count Collapsed Chapter',
            'Root Count Collapsed Appendix',
        ];

        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same([1, 1], array_column($toc, 'level'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same([6, 7], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same("Outline root count cover body\nOutline root count appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Root Count Tail'));
        $t->true(!str_contains($plainText, 'Root Count Collapsed Chapter'));
        $t->true(!str_contains($plainText, 'Root Count Collapsed Appendix'));
        $t->true(!str_contains($plainText, 'Stale Root Count Tail'));
    },
];
