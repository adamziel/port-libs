<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlinePrevBoundaryPdf = static function (): string {
    $coverContent = 'BT /F1 12 Tf 72 720 Td (Outline prev boundary cover body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline prev boundary appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Prev Boundary Current Chapter) /Parent 5 0 R /Dest /CurrentChapter /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Stale Prev Boundary Remote Review) /Parent 5 0 R /Prev 99 0 R /A 12 0 R /Next 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Title (Untrusted Tail After Bad Prev) /Parent 5 0 R /Prev 7 0 R /Dest /AppendixTarget >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (stale-prev-boundary.pdf) /D (stale-prev-target) /NewWindow true >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (CurrentChapter) [3 0 R /FitH 720]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($coverContent) . " >>\nstream\n{$coverContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'bounds document outline metadata when sibling Prev points outside the current chain' => static function (
        TestRunner $t
    ) use ($outlinePrevBoundaryPdf): void {
        $pdf = $outlinePrevBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(8, $outline['last_item_object'] ?? null);
        $t->same(3, $outline['declared_visible_count'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(1, $outline['max_depth'] ?? null);
        $t->same(['Prev Boundary Current Chapter'], $outline['titles'] ?? []);
        $t->same([6], array_column($items, 'outline_object'));
        $t->same([5], array_column($items, 'parent_object'));
        $t->same([0], array_column($items, 'page'));
        $t->same(['FitH'], array_column($items, 'view_mode'));
        $t->same(7, $items[0]['next_object'] ?? null, 'The rejected /Next object id remains reviewable but is not traversed.');
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Prev Boundary Remote Review'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Untrusted Tail After Bad Prev'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-prev-boundary.pdf'));
    },
    'applies the Prev backlink boundary to TOC navigation and remote action review' => static function (
        TestRunner $t
    ) use ($outlinePrevBoundaryPdf): void {
        $pdf = $outlinePrevBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['Prev Boundary Current Chapter'], array_column($toc, 'title'));
        $t->same([1], array_column($toc, 'level'));
        $t->same([0], array_column($toc, 'page'));
        $t->same(['Prev Boundary Current Chapter'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same([6], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same("Outline prev boundary cover body\nOutline prev boundary appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Prev Boundary Remote Review'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Untrusted Tail After Bad Prev'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stale-prev-boundary.pdf'));
        $t->true(!str_contains($plainText, 'Prev Boundary Current Chapter'));
        $t->true(!str_contains($plainText, 'Stale Prev Boundary Remote Review'));
        $t->true(!str_contains($plainText, 'Untrusted Tail After Bad Prev'));
        $t->true(!str_contains($plainText, 'stale-prev-boundary.pdf'));
    },
];
