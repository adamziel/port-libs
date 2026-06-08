<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineIndirectCountBoundaryPdfs = static function (): array {
    $rootBody = 'BT /F1 12 Tf 72 720 Td (Indirect root count boundary body) Tj ET';
    $chapterBody = 'BT /F1 12 Tf 72 720 Td (Indirect item count chapter body) Tj ET';
    $appendixBody = 'BT /F1 12 Tf 72 720 Td (Indirect item count appendix body) Tj ET';

    $rootCountPdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 9 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Title (Indirect Root Count Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /A 12 0 R >>\nendobj\n"
        . "9 0 obj\n1 99 0 R\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI (https://example.com/indirect-root-count-action) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($rootBody) . " >>\nstream\n{$rootBody}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Title (Indirect Root Count Info) /Author (Current Outline Boundary Team) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 40 0 R >>\n%%EOF";

    $itemCountPdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Indirect Item Count Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R /First 8 0 R /Last 8 0 R /Count 18 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Indirect Item Count Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget >>\nendobj\n"
        . "8 0 obj\n<< /Title (Indirect Item Count Hidden Child) /Parent 6 0 R /Dest /HiddenChildTarget /A 12 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (indirect-item-count-hidden.pdf) /D (hidden-child-target) /NewWindow true >>\nendobj\n"
        . "18 0 obj\n1 99 0 R\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (HiddenChildTarget) [4 0 R /FitR 1 2 3 4]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($chapterBody) . " >>\nstream\n{$chapterBody}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixBody) . " >>\nstream\n{$appendixBody}\nendstream\nendobj\n"
        . "%%EOF";

    return [$rootCountPdf, $itemCountPdf];
};

return [
    'rejects indirect outline root Count objects with trailing operands before TOC promotion' => static function (
        TestRunner $t
    ) use ($outlineIndirectCountBoundaryPdfs): void {
        [$pdf] = $outlineIndirectCountBoundaryPdfs();
        $textExtractor = new PdfTextExtractor();
        $outlineExtractor = new PdfOutlineExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->true(in_array('catalog', $metadata['source'] ?? [], true));
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('Indirect Root Count Info', $lightweight['document_info']['title'] ?? null);
        $t->same('Current Outline Boundary Team', $lightweight['document_info']['author'] ?? null);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(6, $outline['last_item_object'] ?? null);
        $t->same(null, $outline['outline_count'] ?? null);
        $t->same(null, $outline['declared_visible_count'] ?? null);
        $t->same(0, $outline['item_count'] ?? null);
        $t->same(0, $outline['resolved_destination_count'] ?? null);
        $t->same([], $outline['titles'] ?? null);
        $t->same([], $toc);
        $t->same([], $lightweight['pdf_toc']);
        $t->same([], $navigation['outline'] ?? []);
        $t->same([], $navigation['outline_action_review_actions'] ?? []);
        $t->same([], $remoteActions);
        $t->same('Indirect root count boundary body', $plainText);

        foreach ([
            'Indirect Root Count Chapter',
            'indirect-root-count-action',
        ] as $hidden) {
            $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $hidden));
            $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
    'blocks child traversal when outline item Count resolves to a tailed indirect object' => static function (
        TestRunner $t
    ) use ($outlineIndirectCountBoundaryPdfs): void {
        [, $pdf] = $outlineIndirectCountBoundaryPdfs();
        $textExtractor = new PdfTextExtractor();
        $outlineExtractor = new PdfOutlineExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $structureContext = $outlineExtractor->getOutlineStructureDestinationPageContext($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

        $expectedTitles = [
            'Indirect Item Count Chapter',
            'Indirect Item Count Appendix',
        ];

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(2, $outline['declared_visible_count'] ?? null);
        $t->same(2, $outline['item_count'] ?? null);
        $t->same(2, $outline['resolved_destination_count'] ?? null);
        $t->same(1, $outline['max_depth'] ?? null);
        $t->same($expectedTitles, $outline['titles'] ?? null);
        $t->same([6, 7], array_column($items, 'outline_object'));
        $t->same([5, 5], array_column($items, 'parent_object'));
        $t->same([1, 1], array_column($items, 'level'));
        $t->same([0, 1], array_column($items, 'page'));
        $t->same(8, $items[0]['first_child_object'] ?? null);
        $t->same(8, $items[0]['last_child_object'] ?? null);
        $t->same(null, $items[0]['outline_count'] ?? null);
        $t->same(null, $items[0]['descendant_count'] ?? null);
        $t->same('parent', $items[0]['structure_state'] ?? null);

        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same($expectedTitles, array_column($lightweight['pdf_toc'] ?? [], 'title'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same([6, 7], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same($expectedTitles, array_column($structureContext, 'title'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same("Indirect item count chapter body\nIndirect item count appendix body", $plainText);

        foreach ([
            'Indirect Item Count Hidden Child',
            'indirect-item-count-hidden.pdf',
        ] as $hidden) {
            $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $hidden));
            $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $hidden));
            $t->true(is_string($encodedLightweight) && !str_contains($encodedLightweight, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
