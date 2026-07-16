<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineTrailerRootLightweightBoundaryPdf = static function (): string {
    $currentBody = 'BT /F1 12 Tf 72 720 Td (Current trailer root outline body) Tj ET';
    $staleBody = 'BT /F1 12 Tf 72 720 Td (Stale catalog outline body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Stale Catalog Outline) /Parent 5 0 R /Dest [3 0 R /Fit] /A 12 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Type /Catalog /Pages 20 0 R /Outlines 25 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "20 0 obj\n<< /Type /Pages /Kids [21 0 R] /Count 1 >>\nendobj\n"
        . "21 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 31 0 R >>\nendobj\n"
        . "25 0 obj\n<< /Type /Outlines /First 26 0 R /Last 26 0 R /Count 1 >>\nendobj\n"
        . "26 0 obj\n<< /Title (Current Trailer Root Outline) /Parent 25 0 R /Dest [21 0 R /FitH 700] >>\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI (https://example.com/stale-catalog-outline) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($staleBody) . " >>\nstream\n{$staleBody}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($currentBody) . " >>\nstream\n{$currentBody}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Title (Current Trailer Root Info) /Author (Outline Team) >>\nendobj\n"
        . "trailer\n<< /Root 10 0 R /Info 40 0 R >>\n%%EOF";
};

$outlineUnresolvedTrailerRootBoundaryPdf = static function (): string {
    $staleBody = 'BT /F1 12 Tf 72 720 Td (Stale invalid root body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Stale Invalid Root Outline) /Parent 5 0 R /Dest [3 0 R /Fit] /A 12 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI (https://example.com/stale-invalid-root-outline) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($staleBody) . " >>\nstream\n{$staleBody}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Title (Invalid Trailer Root Info) >>\nendobj\n"
        . "trailer\n<< /Root 99 0 R /Info 40 0 R >>\n%%EOF";
};

return [
    'uses trailer Root catalog for lightweight outline metadata without startxref' => static function (
        TestRunner $t
    ) use ($outlineTrailerRootLightweightBoundaryPdf): void {
        $pdf = $outlineTrailerRootLightweightBoundaryPdf();
        $textExtractor = new PdfTextExtractor();
        $outlineExtractor = new PdfOutlineExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $plainToc = $outlineExtractor->getPdfToc($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

        $expectedTitles = ['Current Trailer Root Outline'];

        $t->same('Current Trailer Root Info', $metadata['title'] ?? null);
        $t->same('Outline Team', $metadata['authors'][0] ?? null);
        $t->same('UseOutlines', $metadata['page_mode'] ?? null);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(25, $outline['outline_root_object'] ?? null);
        $t->same(26, $outline['first_item_object'] ?? null);
        $t->same(26, $outline['last_item_object'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same($expectedTitles, $outline['titles'] ?? null);
        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same($expectedTitles, array_column($plainToc, 'title'));
        $t->same($expectedTitles, array_column($lightweight['pdf_toc'] ?? [], 'title'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same([26], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([0], array_column($toc, 'page'));
        $t->same(['FitH'], array_column($toc, 'view_mode'));
        $t->same([], $navigation['outline_action_review_actions'] ?? []);
        $t->same([], $remoteActions);
        $t->same('Current trailer root outline body', $plainText);

        foreach ([
            'Stale Catalog Outline',
            'stale-catalog-outline',
            'Stale catalog outline body',
        ] as $hidden) {
            $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $hidden));
            $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $hidden));
            $t->true(is_string($encodedLightweight) && !str_contains($encodedLightweight, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
    'fails closed instead of falling back to stale catalog outlines when trailer Root is unresolved' => static function (
        TestRunner $t
    ) use ($outlineUnresolvedTrailerRootBoundaryPdf): void {
        $pdf = $outlineUnresolvedTrailerRootBoundaryPdf();
        $textExtractor = new PdfTextExtractor();
        $outlineExtractor = new PdfOutlineExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $plainToc = $outlineExtractor->getPdfToc($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

        $t->same('Invalid Trailer Root Info', $lightweight['document_info']['title'] ?? null);
        $t->same(false, array_key_exists('document_outline', $metadata));
        $t->same([], $toc);
        $t->same([], $plainToc);
        $t->same([], $lightweight['pdf_toc']);
        $t->same([], $navigation['outline'] ?? []);
        $t->same([], $navigation['outline_action_review_actions'] ?? []);
        $t->same([], $remoteActions);
        $t->same('', $plainText);

        foreach ([
            'Stale Invalid Root Outline',
            'stale-invalid-root-outline',
            'Stale invalid root body',
        ] as $hidden) {
            $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $hidden));
            $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $hidden));
            $t->true(is_string($encodedLightweight) && !str_contains($encodedLightweight, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
