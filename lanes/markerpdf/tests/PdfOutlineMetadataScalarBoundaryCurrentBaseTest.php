<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataScalarBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline scalar boundary intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline scalar boundary appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title 16 0 R /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R /First 8 0 R /Last 8 0 R /Count 1 /A 12 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title 17 0 R /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /Fit] >>\nendobj\n"
        . "8 0 obj\n<< /Title (Stale Child Under Malformed Scalar) /Parent 6 0 R /Dest [4 0 R /XYZ 72 640 0] /A 13 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (malformed-scalar-title.pdf) /D (malformed-title) /Next 14 0 R >>\nendobj\n"
        . "13 0 obj\n<< /S /URI /URI (https://example.com/stale-outline-child) >>\nendobj\n"
        . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('malformed title action'\\)) >>\nendobj\n"
        . "16 0 obj\n(Scalar Boundary Spoof) /A 12 0 R /Next 99 0 R\nendobj\n"
        . "17 0 obj\n(Scalar Boundary Appendix) % comment-only title tail is valid whitespace\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'rejects indirect outline title scalar objects with trailing action tokens from document metadata' => static function (
        TestRunner $t
    ) use ($outlineMetadataScalarBoundaryPdf): void {
        $pdf = $outlineMetadataScalarBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(7, $outline['last_item_object'] ?? null);
        $t->same(2, $outline['declared_visible_count'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(1, $outline['max_depth'] ?? null);
        $t->same(['Scalar Boundary Appendix'], $outline['titles'] ?? []);
        $t->same([7], array_column($items, 'outline_object'));
        $t->same([5], array_column($items, 'parent_object'));
        $t->same([6], array_column($items, 'previous_object'));
        $t->same([1], array_column($items, 'page'));
        $t->same(['Fit'], array_column($items, 'view_mode'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Scalar Boundary Spoof'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Child Under Malformed Scalar'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'malformed-scalar-title.pdf'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'malformed title action'));
    },
    'applies indirect outline title scalar boundaries to TOC navigation and visible text' => static function (
        TestRunner $t
    ) use ($outlineMetadataScalarBoundaryPdf): void {
        $pdf = $outlineMetadataScalarBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['Scalar Boundary Appendix'], array_column($toc, 'title'));
        $t->same([1], array_column($toc, 'level'));
        $t->same([1], array_column($toc, 'page'));
        $t->same(['Scalar Boundary Appendix'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same([7], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same("Outline scalar boundary intro body\nOutline scalar boundary appendix body", $plainText);
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'Scalar Boundary Spoof'));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'Stale Child Under Malformed Scalar'));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'malformed-scalar-title.pdf'));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, 'stale-outline-child'));
        $t->true(!str_contains($plainText, 'Scalar Boundary Spoof'));
        $t->true(!str_contains($plainText, 'Scalar Boundary Appendix'));
        $t->true(!str_contains($plainText, 'malformed-scalar-title.pdf'));
        $t->true(!str_contains($plainText, 'malformed title action'));
    },
];
