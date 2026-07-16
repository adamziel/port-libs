<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineTitlelessBridgeBoundaryPdf = static function (): string {
    $coverContent = 'BT /F1 12 Tf 72 720 Td (Outline titleless bridge current body) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Outline titleless bridge stale body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Current Titleless Bridge Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 8 0 R /A 12 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Parent 5 0 R /Prev 6 0 R /Next 9 0 R /A 13 0 R /Dest /StaleBridgeTarget >>\nendobj\n"
        . "9 0 obj\n<< /Title (Stale Titleless Bridge Appendix) /Parent 5 0 R /Prev 8 0 R /Dest /StaleTarget /A 14 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /GoTo /D /CurrentStart >>\nendobj\n"
        . "13 0 obj\n<< /S /JavaScript /JS (app.alert\\('titleless bridge action leak'\\)) >>\nendobj\n"
        . "14 0 obj\n<< /S /GoToR /F (stale-titleless-bridge.pdf) /D (stale-titleless-dest) /NewWindow true >>\nendobj\n"
        . "20 0 obj\n<< /Names [(CurrentStart) [3 0 R /FitH 720] (StaleBridgeTarget) [4 0 R /Fit] (StaleTarget) [4 0 R /XYZ 10 20 0]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($coverContent) . " >>\nstream\n{$coverContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'stops document outline metadata at titleless sibling bridge before stale rows' => static function (
        TestRunner $t
    ) use ($outlineTitlelessBridgeBoundaryPdf): void {
        $pdf = $outlineTitlelessBridgeBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(null, $outline['last_item_object'] ?? null);
        $t->same(1, $outline['declared_visible_count'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(['Current Titleless Bridge Chapter'], $outline['titles'] ?? []);
        $t->same([6], array_column($items, 'outline_object'));
        $t->same([5], array_column($items, 'parent_object'));
        $t->same([8], array_column($items, 'next_object'));
        $t->same([0], array_column($items, 'page'));
        $t->same(['FitH'], array_column($items, 'view_mode'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Titleless Bridge Appendix'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-titleless-bridge.pdf'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'titleless bridge action leak'));
    },
    'applies titleless sibling bridge boundary to TOC navigation and remote action review' => static function (
        TestRunner $t
    ) use ($outlineTitlelessBridgeBoundaryPdf): void {
        $pdf = $outlineTitlelessBridgeBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $lightweightToc = $outlineExtractor->getPdfToc($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $lightweightMetadata = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $lightweightEncoded = json_encode($lightweightMetadata, JSON_UNESCAPED_SLASHES);

        $t->same(['Current Titleless Bridge Chapter'], array_column($toc, 'title'));
        $t->same(['Current Titleless Bridge Chapter'], array_column($lightweightToc, 'title'));
        $t->same([0], array_column($toc, 'page'));
        $t->same(['FitH'], array_column($toc, 'view_mode'));
        $t->same(['Current Titleless Bridge Chapter'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same([6], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same([
            ['title' => 'Current Titleless Bridge Chapter', 'level' => 1, 'page' => 0],
        ], $lightweightMetadata['pdf_toc']);
        $t->same("Outline titleless bridge current body\nOutline titleless bridge stale body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Titleless Bridge Appendix'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stale-titleless-bridge.pdf'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'titleless bridge action leak'));
        $t->true(is_string($lightweightEncoded) && !str_contains($lightweightEncoded, 'Stale Titleless Bridge Appendix'));
        $t->true(!str_contains($plainText, 'Current Titleless Bridge Chapter'));
        $t->true(!str_contains($plainText, 'Stale Titleless Bridge Appendix'));
        $t->true(!str_contains($plainText, 'stale-titleless-bridge.pdf'));
        $t->true(!str_contains($plainText, 'titleless bridge action leak'));
    },
];
