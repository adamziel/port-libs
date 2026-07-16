<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineIndirectOutlinesRootTypePdf = static function (): string {
    $coverContent = 'BT /F1 12 Tf 72 720 Td (Indirect Outlines root type cover body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Indirect Outlines root type appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type 18 0 R /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Indirect Outlines Root Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Indirect Outlines Root Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /Fit] >>\nendobj\n"
        . "18 0 obj\n/Outlines\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($coverContent) . " >>\nstream\n{$coverContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

$outlineIndirectPageRootTypeSpoofPdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Indirect page root type spoof body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 9 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Title (Indirect Page Root Spoofed Outline) /Parent 9 0 R /Dest /SpoofedTarget /A 12 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type 18 0 R /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (indirect-page-root-spoof.pdf) /D (spoofed-target) >>\nendobj\n"
        . "18 0 obj\n/Page\nendobj\n"
        . "20 0 obj\n<< /Names [(SpoofedTarget) [3 0 R /Fit]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'accepts indirect /Outlines root Type names in document outline metadata and navigation' => static function (
        TestRunner $t
    ) use ($outlineIndirectOutlinesRootTypePdf): void {
        $pdf = $outlineIndirectOutlinesRootTypePdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $lightweightToc = $outlineExtractor->getPdfToc($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $lightweightMetadata = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

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
        $t->same(['Indirect Outlines Root Chapter', 'Indirect Outlines Root Appendix'], $outline['titles'] ?? []);
        $t->same([6, 7], array_column($items, 'outline_object'));
        $t->same([0, 1], array_column($items, 'page'));
        $t->same(['FitH', 'Fit'], array_column($items, 'view_mode'));
        $t->same(['Indirect Outlines Root Chapter', 'Indirect Outlines Root Appendix'], array_column($toc, 'title'));
        $t->same(['Indirect Outlines Root Chapter', 'Indirect Outlines Root Appendix'], array_column($lightweightToc, 'title'));
        $t->same(['Indirect Outlines Root Chapter', 'Indirect Outlines Root Appendix'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same([
            ['title' => 'Indirect Outlines Root Chapter', 'level' => 1, 'page' => 0],
            ['title' => 'Indirect Outlines Root Appendix', 'level' => 1, 'page' => 1],
        ], $lightweightMetadata['pdf_toc']);
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same("Indirect Outlines root type cover body\nIndirect Outlines root type appendix body", $plainText);
        $t->true(!str_contains($plainText, 'Indirect Outlines Root Chapter'));
        $t->true(!str_contains($plainText, 'Indirect Outlines Root Appendix'));
    },
    'rejects indirect typed page objects that spoof catalog outline roots' => static function (
        TestRunner $t
    ) use ($outlineIndirectPageRootTypeSpoofPdf): void {
        $pdf = $outlineIndirectPageRootTypeSpoofPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $lightweightToc = $outlineExtractor->getPdfToc($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $lightweightMetadata = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $lightweightEncoded = json_encode($lightweightMetadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->true(!array_key_exists('document_outline', $metadata));
        $t->same(['SpoofedTarget'], $metadata['document_destinations']['names'] ?? []);
        $t->same([], $toc);
        $t->same([], $lightweightToc);
        $t->same([], $navigation['outline']);
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same([], $lightweightMetadata['pdf_toc']);
        $t->same('Indirect page root type spoof body', $plainText);
        $t->true(is_string($metadataEncoded) && !str_contains($metadataEncoded, 'Indirect Page Root Spoofed Outline'));
        $t->true(is_string($metadataEncoded) && !str_contains($metadataEncoded, 'indirect-page-root-spoof.pdf'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Indirect Page Root Spoofed Outline'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'indirect-page-root-spoof.pdf'));
        $t->true(is_string($lightweightEncoded) && !str_contains($lightweightEncoded, 'Indirect Page Root Spoofed Outline'));
        $t->true(is_string($lightweightEncoded) && !str_contains($lightweightEncoded, 'indirect-page-root-spoof.pdf'));
        $t->true(!str_contains($plainText, 'Indirect Page Root Spoofed Outline'));
        $t->true(!str_contains($plainText, 'indirect-page-root-spoof.pdf'));
    },
];
