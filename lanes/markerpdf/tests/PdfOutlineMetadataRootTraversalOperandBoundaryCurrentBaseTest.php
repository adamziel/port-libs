<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineRootTraversalOperandBoundaryPdfs = static function (): array {
    $firstBody = 'BT /F1 12 Tf 72 720 Td (Root First operand boundary body) Tj ET';
    $lastIntro = 'BT /F1 12 Tf 72 720 Td (Root Last operand intro body) Tj ET';
    $lastAppendix = 'BT /F1 12 Tf 72 720 Td (Root Last operand appendix body) Tj ET';

    $firstPdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R 9 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Root First Operand Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /A 12 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /Outlines /First 10 0 R /Last 10 0 R /Count 1 >>\nendobj\n"
        . "10 0 obj\n<< /Title (Root First Operand Decoy) /Parent 9 0 R /Dest [3 0 R /Fit] >>\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI (https://example.com/root-first-operand-action) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstBody) . " >>\nstream\n{$firstBody}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Title (Root First Operand Info) /Author (Current Outline Boundary Team) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 40 0 R >>\n%%EOF";

    $lastPdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R 9 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Root Last Operand Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Root Last Operand Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget /Next 9 0 R /A 12 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Title (Root Last Operand Decoy) /Parent 5 0 R /Prev 7 0 R /A 13 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (root-last-operand-review.pdf) /D (appendix-review) /NewWindow true >>\nendobj\n"
        . "13 0 obj\n<< /S /GoToR /F (root-last-operand-decoy.pdf) /D (decoy-review) >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($lastIntro) . " >>\nstream\n{$lastIntro}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($lastAppendix) . " >>\nstream\n{$lastAppendix}\nendstream\nendobj\n"
        . "%%EOF";

    return [$firstPdf, $lastPdf];
};

return [
    'rejects outline root First references with trailing operands before TOC promotion' => static function (
        TestRunner $t
    ) use ($outlineRootTraversalOperandBoundaryPdfs): void {
        [$pdf] = $outlineRootTraversalOperandBoundaryPdfs();
        $textExtractor = new PdfTextExtractor();
        $outlineExtractor = new PdfOutlineExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->true(in_array('catalog', $metadata['source'] ?? [], true));
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('Root First Operand Info', $lightweight['document_info']['title'] ?? null);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(6, $outline['last_item_object'] ?? null);
        $t->same(0, $outline['item_count'] ?? null);
        $t->same([], $outline['titles'] ?? null);
        $t->same([], $toc);
        $t->same([], $lightweight['pdf_toc']);
        $t->same([], $navigation['outline'] ?? []);
        $t->same([], $navigation['outline_action_review_actions'] ?? []);
        $t->same('Root First operand boundary body', $plainText);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Root First Operand Chapter'));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'root-first-operand-action'));
        $t->true(!str_contains($plainText, 'Root First Operand Decoy'));
    },
    'rejects outline root Last references with trailing operands before navigation and remote action review' => static function (
        TestRunner $t
    ) use ($outlineRootTraversalOperandBoundaryPdfs): void {
        [, $pdf] = $outlineRootTraversalOperandBoundaryPdfs();
        $textExtractor = new PdfTextExtractor();
        $outlineExtractor = new PdfOutlineExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $structureContext = $outlineExtractor->getOutlineStructureDestinationPageContext($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(7, $outline['last_item_object'] ?? null);
        $t->same(2, $outline['declared_visible_count'] ?? null);
        $t->same(0, $outline['item_count'] ?? null);
        $t->same(0, $outline['resolved_destination_count'] ?? null);
        $t->same([], $outline['titles'] ?? null);
        $t->same([], $toc);
        $t->same([], $lightweight['pdf_toc']);
        $t->same([], $navigation['outline'] ?? []);
        $t->same([], $navigation['outline_action_review_actions'] ?? []);
        $t->same([], $remoteActions);
        $t->same([], $structureContext);
        $t->same("Root Last operand intro body\nRoot Last operand appendix body", $plainText);
        foreach ([
            'Root Last Operand Chapter',
            'Root Last Operand Appendix',
            'Root Last Operand Decoy',
            'root-last-operand-review.pdf',
            'root-last-operand-decoy.pdf',
        ] as $hidden) {
            $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $hidden));
            $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
