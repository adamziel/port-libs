<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineDirectRootTraversalOperandBoundaryPdfs = static function (): array {
    $firstBody = 'BT /F1 12 Tf 72 720 Td (Direct root first operand body) Tj ET';
    $lastIntro = 'BT /F1 12 Tf 72 720 Td (Direct root last operand intro) Tj ET';
    $lastAppendix = 'BT /F1 12 Tf 72 720 Td (Direct root last operand appendix) Tj ET';

    $firstPdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines << /Type /Outlines /First 6 0 R 9 0 R /Last 6 0 R /Count 1 >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Title (Direct Root First Operand Chapter) /Dest [3 0 R /FitH 720] /A 12 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /Outlines /First 10 0 R /Last 10 0 R /Count 1 >>\nendobj\n"
        . "10 0 obj\n<< /Title (Direct Root First Operand Decoy) /Parent 9 0 R /Dest [3 0 R /Fit] /A 13 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI (https://example.com/direct-root-first-action) >>\nendobj\n"
        . "13 0 obj\n<< /S /URI /URI (https://example.com/direct-root-first-decoy-action) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstBody) . " >>\nstream\n{$firstBody}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Title (Direct Root First Operand Info) /Author (Current Outline Boundary Team) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 40 0 R >>\n%%EOF";

    $lastPdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines << /Type /Outlines /First 6 0 R /Last 7 0 R 9 0 R /Count 2 >> /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Title (Direct Root Last Operand Chapter) /Dest /DirectStart /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Direct Root Last Operand Appendix) /Prev 6 0 R /Dest /DirectAppendix /Next 9 0 R /A 12 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Title (Direct Root Last Operand Decoy) /Prev 7 0 R /Dest /DirectDecoy /A 13 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (direct-root-last-review.pdf) /D (direct-appendix) /NewWindow true >>\nendobj\n"
        . "13 0 obj\n<< /S /GoToR /F (direct-root-last-decoy.pdf) /D (direct-decoy) >>\nendobj\n"
        . "20 0 obj\n<< /Names [(DirectAppendix) [4 0 R /Fit] (DirectDecoy) [4 0 R /FitB] (DirectStart) [3 0 R /FitH 720]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($lastIntro) . " >>\nstream\n{$lastIntro}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($lastAppendix) . " >>\nstream\n{$lastAppendix}\nendstream\nendobj\n"
        . "%%EOF";

    return [$firstPdf, $lastPdf];
};

return [
    'rejects direct outline root First references with trailing operands before TOC promotion' => static function (
        TestRunner $t
    ) use ($outlineDirectRootTraversalOperandBoundaryPdfs): void {
        [$pdf] = $outlineDirectRootTraversalOperandBoundaryPdfs();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $textExtractor = new PdfTextExtractor();
        $outlineExtractor = new PdfOutlineExtractor();
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same('Direct Root First Operand Info', $metadata['title']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(null, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(6, $outline['last_item_object'] ?? null);
        $t->same(0, $outline['item_count'] ?? null);
        $t->same([], $outline['titles'] ?? null);
        $t->same([], $toc);
        $t->same([], $lightweight['pdf_toc']);
        $t->same([], $navigation['outline'] ?? []);
        $t->same([], $navigation['outline_action_review_actions'] ?? []);
        $t->same([], $remoteActions);
        $t->same('Direct root first operand body', $plainText);
        foreach ([
            'Direct Root First Operand Chapter',
            'Direct Root First Operand Decoy',
            'direct-root-first-action',
            'direct-root-first-decoy-action',
        ] as $hidden) {
            $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $hidden));
            $t->true(is_string($encodedLightweight) && !str_contains($encodedLightweight, $hidden));
            $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
    'rejects direct outline root Last references with trailing operands before navigation and remote action review' => static function (
        TestRunner $t
    ) use ($outlineDirectRootTraversalOperandBoundaryPdfs): void {
        [, $pdf] = $outlineDirectRootTraversalOperandBoundaryPdfs();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $textExtractor = new PdfTextExtractor();
        $outlineExtractor = new PdfOutlineExtractor();
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $structureContext = $outlineExtractor->getOutlineStructureDestinationPageContext($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(null, $outline['outline_root_object'] ?? null);
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
        $t->same("Direct root last operand intro\nDirect root last operand appendix", $plainText);
        foreach ([
            'Direct Root Last Operand Chapter',
            'Direct Root Last Operand Appendix',
            'Direct Root Last Operand Decoy',
            'direct-root-last-review.pdf',
            'direct-root-last-decoy.pdf',
        ] as $hidden) {
            $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $hidden));
            $t->true(is_string($encodedLightweight) && !str_contains($encodedLightweight, $hidden));
            $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
