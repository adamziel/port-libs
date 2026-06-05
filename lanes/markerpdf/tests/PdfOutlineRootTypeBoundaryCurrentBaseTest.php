<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineRootTypeBoundaryPdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Outline root type boundary page body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 3 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Page Root Spoofed Outline) /Parent 3 0 R /Dest /SpoofedTarget /A 12 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (spoofed-outline-root.pdf) /D (spoofed-target) >>\nendobj\n"
        . "20 0 obj\n<< /Names [(SpoofedTarget) [3 0 R /Fit]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'rejects typed page objects that spoof catalog outline roots in document metadata' => static function (
        TestRunner $t
    ) use ($outlineRootTypeBoundaryPdf): void {
        $pdf = $outlineRootTypeBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->true(!array_key_exists('document_outline', $metadata));
        $t->true(!array_key_exists('document_outline', $metadata['catalog']));
        $t->same(['SpoofedTarget'], $metadata['document_destinations']['names'] ?? []);
        $t->same(1, $metadata['document_destinations']['count'] ?? null);
        $t->same(0, $metadata['document_destinations']['destinations'][0]['page'] ?? null);
        $t->same(3, $metadata['document_destinations']['destinations'][0]['page_object'] ?? null);
        $t->same('Fit', $metadata['document_destinations']['destinations'][0]['view_mode'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Page Root Spoofed Outline'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'spoofed-outline-root.pdf'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'spoofed-target'));
    },
    'applies the typed outline-root guard to TOC navigation and action review rows' => static function (
        TestRunner $t
    ) use ($outlineRootTypeBoundaryPdf): void {
        $pdf = $outlineRootTypeBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $lightweightToc = $outlineExtractor->getPdfToc($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same([], $toc);
        $t->same([], $lightweightToc);
        $t->same([], $navigation['outline']);
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same('Outline root type boundary page body', $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Page Root Spoofed Outline'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'spoofed-outline-root.pdf'));
        $t->true(!str_contains($plainText, 'Page Root Spoofed Outline'));
        $t->true(!str_contains($plainText, 'spoofed-outline-root.pdf'));
    },
];
