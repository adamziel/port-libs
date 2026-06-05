<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineDirectRootBoundaryPdf = static function (): string {
    $coverContent = 'BT /F1 12 Tf 72 720 Td (Direct outline root boundary cover body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Direct outline root boundary appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines << /Type /Outlines /First 6 0 R /Count 1 >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Title (Direct Root Boundary Chapter) /Dest [3 0 R /FitH 720] /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Stale Direct Root Explicit Parent) /Parent 99 0 R /Prev 6 0 R /Dest [4 0 R /Fit] /A 12 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (stale-direct-root-outline.pdf) /D (stale-direct-root-target) /NewWindow true >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($coverContent) . " >>\nstream\n{$coverContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'bounds document outline metadata for direct root dictionaries with explicit stale parents' => static function (
        TestRunner $t
    ) use ($outlineDirectRootBoundaryPdf): void {
        $pdf = $outlineDirectRootBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(null, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(null, $outline['last_item_object'] ?? null);
        $t->same(1, $outline['declared_visible_count'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(['Direct Root Boundary Chapter'], $outline['titles'] ?? []);
        $t->same([6], array_column($items, 'outline_object'));
        $t->same([null], array_column($items, 'parent_object'));
        $t->same([7], array_column($items, 'next_object'));
        $t->same([0], array_column($items, 'page'));
        $t->same(['FitH'], array_column($items, 'view_mode'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Direct Root Explicit Parent'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-direct-root-outline.pdf'));
    },
    'applies direct root parent boundaries to TOC navigation and remote action rows' => static function (
        TestRunner $t
    ) use ($outlineDirectRootBoundaryPdf): void {
        $pdf = $outlineDirectRootBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $lightweightToc = $outlineExtractor->getPdfToc($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $textExtractor = new PdfTextExtractor();
        $lightweightMetadata = $textExtractor->extractOutlineMetadata($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $lightweightEncoded = json_encode($lightweightMetadata, JSON_UNESCAPED_SLASHES);

        $t->same(['Direct Root Boundary Chapter'], array_column($toc, 'title'));
        $t->same(['Direct Root Boundary Chapter'], array_column($lightweightToc, 'title'));
        $t->same([
            ['title' => 'Direct Root Boundary Chapter', 'level' => 1, 'page' => 0],
        ], $lightweightMetadata['pdf_toc']);
        $t->same([1], array_column($toc, 'level'));
        $t->same([0], array_column($toc, 'page'));
        $t->same(['FitH'], array_column($toc, 'view_mode'));
        $t->same(['Direct Root Boundary Chapter'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same([6], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same("Direct outline root boundary cover body\nDirect outline root boundary appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Direct Root Explicit Parent'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stale-direct-root-outline.pdf'));
        $t->true(is_string($lightweightEncoded) && !str_contains($lightweightEncoded, 'Stale Direct Root Explicit Parent'));
        $t->true(is_string($lightweightEncoded) && !str_contains($lightweightEncoded, 'stale-direct-root-outline.pdf'));
        $t->true(!str_contains($plainText, 'Direct Root Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Stale Direct Root Explicit Parent'));
        $t->true(!str_contains($plainText, 'stale-direct-root-outline.pdf'));
    },
];
