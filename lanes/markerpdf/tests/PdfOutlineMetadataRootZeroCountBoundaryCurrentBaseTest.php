<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineRootZeroCountBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline root zero count intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline root zero count appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 0 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Stale Root Zero Count Chapter) /Parent 5 0 R /Dest /HiddenStart /Next 7 0 R /A 12 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Stale Root Zero Count Appendix) /Parent 5 0 R /Prev 6 0 R /Dest /HiddenAppendix /A 13 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /JavaScript /JS (app.alert\\('root zero count action leak'\\)) >>\nendobj\n"
        . "13 0 obj\n<< /S /GoToR /F (root-zero-count-appendix.pdf) /D (hidden-appendix) /NewWindow true >>\nendobj\n"
        . "20 0 obj\n<< /Names [(HiddenAppendix) [4 0 R /Fit] (HiddenStart) [3 0 R /FitH 720]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'preserves root outline metadata while suppressing Count zero child rows' => static function (
        TestRunner $t
    ) use ($outlineRootZeroCountBoundaryPdf): void {
        $pdf = $outlineRootZeroCountBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same($outline, $metadata['catalog']['document_outline'] ?? []);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(true, $outline['review_only'] ?? null);
        $t->same(false, $outline['payload_included'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(7, $outline['last_item_object'] ?? null);
        $t->same(true, $outline['has_children'] ?? null);
        $t->same(0, $outline['outline_count'] ?? null);
        $t->same(0, $outline['declared_visible_count'] ?? null);
        $t->same(0, $outline['descendant_count'] ?? null);
        $t->same(0, $outline['item_count'] ?? null);
        $t->same(0, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(0, $outline['max_depth'] ?? null);
        $t->same([], $outline['titles'] ?? null);
        $t->same([], $outline['items'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Root Zero Count Chapter'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Root Zero Count Appendix'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'root zero count action leak'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'root-zero-count-appendix.pdf'));
    },
    'applies root Count zero boundary to TOC navigation and action review' => static function (
        TestRunner $t
    ) use ($outlineRootZeroCountBoundaryPdf): void {
        $pdf = $outlineRootZeroCountBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $lightweightToc = $outlineExtractor->getPdfToc($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $structureContext = $outlineExtractor->getOutlineStructureDestinationPageContext($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same([], $toc);
        $t->same([], $lightweightToc);
        $t->same([], $navigation['outline']);
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same([], $structureContext);
        $t->same("Outline root zero count intro body\nOutline root zero count appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Root Zero Count Chapter'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Root Zero Count Appendix'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'root zero count action leak'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'root-zero-count-appendix.pdf'));
        $t->true(!str_contains($plainText, 'Stale Root Zero Count Chapter'));
        $t->true(!str_contains($plainText, 'Stale Root Zero Count Appendix'));
        $t->true(!str_contains($plainText, 'root zero count action leak'));
        $t->true(!str_contains($plainText, 'root-zero-count-appendix.pdf'));
    },
    'keeps root Count zero rows out of lightweight upstream pdf_toc metadata' => static function (
        TestRunner $t
    ) use ($outlineRootZeroCountBoundaryPdf): void {
        $pdf = $outlineRootZeroCountBoundaryPdf();
        $textExtractor = new PdfTextExtractor();
        $lightweightMetadata = $textExtractor->extractOutlineMetadata($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($lightweightMetadata, JSON_UNESCAPED_SLASHES);

        $t->same(2, $lightweightMetadata['pages']);
        $t->same([], $lightweightMetadata['pdf_toc']);
        $t->same("Outline root zero count intro body\nOutline root zero count appendix body", $plainText);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Root Zero Count Chapter'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Root Zero Count Appendix'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'root zero count action leak'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'root-zero-count-appendix.pdf'));
        $t->true(!str_contains($plainText, 'Stale Root Zero Count Chapter'));
        $t->true(!str_contains($plainText, 'root-zero-count-appendix.pdf'));
    },
];
