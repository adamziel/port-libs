<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineActionDestinationOperandBoundaryPdf = static function (): string {
    $chapterContent = 'BT /F1 12 Tf 72 720 Td (Outline action destination operand chapter body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline action destination operand appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Malformed Action D Chapter) /Parent 5 0 R /A 12 0 R /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Action D Clean Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /Fit] >>\nendobj\n"
        . "12 0 obj\n<< /S /GoTo /D [3 0 R /FitH 720] 99 0 R >>\nendobj\n"
        . "99 0 obj\n<< /S /URI /URI (https://example.com/tailed-action-destination-decoy) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($chapterContent) . " >>\nstream\n{$chapterContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'keeps tailed GoTo action D operands unresolved in document outline metadata' => static function (
        TestRunner $t
    ) use ($outlineActionDestinationOperandBoundaryPdf): void {
        $pdf = $outlineActionDestinationOperandBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $malformed = $items[0] ?? [];
        $clean = $items[1] ?? [];
        $review = $malformed['action_destination_operand_boundary_review'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(7, $outline['last_item_object'] ?? null);
        $t->same(2, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(1, $outline['unresolved_destination_count'] ?? null);
        $t->same(['Malformed Action D Chapter', 'Action D Clean Appendix'], $outline['titles'] ?? []);

        $t->same('Malformed Action D Chapter', $malformed['title'] ?? null);
        $t->same(6, $malformed['outline_object'] ?? null);
        $t->same(false, $malformed['destination_resolved'] ?? null);
        $t->same(null, $malformed['destination'] ?? null);
        $t->same('GoTo', $malformed['action_type'] ?? null);
        $t->same(12, $malformed['action_object'] ?? null);

        $t->same('outline_action_destination_operand_boundary', $review['source'] ?? null);
        $t->same('rejected_malformed_outline_action_d_operand', $review['status'] ?? null);
        $t->same('D', $review['key'] ?? null);
        $t->same(2, $review['operand_count'] ?? null);
        $t->same('array', $review['operand_shape'] ?? null);
        $t->same(['indirect_reference'], $review['trailing_operand_shapes'] ?? null);
        $t->same([99], $review['trailing_reference_object_numbers'] ?? null);
        $t->same(false, $review['navigation_promoted'] ?? null);

        $t->same('Action D Clean Appendix', $clean['title'] ?? null);
        $t->same(true, $clean['destination_resolved'] ?? null);
        $t->same(1, $clean['page'] ?? null);
        $t->same('Fit', $clean['view_mode'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'tailed-action-destination-decoy'));
    },
    'excludes tailed GoTo action D operands from TOC rows while preserving review metadata' => static function (
        TestRunner $t
    ) use ($outlineActionDestinationOperandBoundaryPdf): void {
        $pdf = $outlineActionDestinationOperandBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $plainToc = $outlineExtractor->getPdfToc($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $textExtractor = new PdfTextExtractor();
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $lightweightEncoded = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

        $t->same(['Action D Clean Appendix'], array_column($toc, 'title'));
        $t->same(['Action D Clean Appendix'], array_column($plainToc, 'title'));
        $t->same(['Action D Clean Appendix'], array_column($lightweight['pdf_toc'] ?? [], 'title'));
        $t->same(['Action D Clean Appendix'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same([1], array_column($toc, 'page'));
        $t->same(['Fit'], array_column($toc, 'view_mode'));

        $actions = $navigation['outline_action_review_actions'] ?? [];
        $t->same(['Malformed Action D Chapter'], array_column($actions, 'outline_title'));
        $t->same(['GoTo'], array_column($actions, 'action_type'));
        $t->same(['unsupported-action-review'], array_column($actions, 'safety'));
        $t->same([null], array_column($actions, 'page'));
        $t->same([12], array_column($actions, 'action_object'));
        $t->same([], $remoteActions);
        $t->same("Outline action destination operand chapter body\nOutline action destination operand appendix body", $plainText);

        foreach (['tailed-action-destination-decoy', 'https://example.com/tailed-action-destination-decoy'] as $forbidden) {
            $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, $forbidden));
            $t->true(is_string($lightweightEncoded) && !str_contains($lightweightEncoded, $forbidden));
            $t->true(!str_contains($plainText, $forbidden));
        }
        $t->true(!str_contains($plainText, 'Malformed Action D Chapter'));
        $t->true(!str_contains($plainText, 'Action D Clean Appendix'));
    },
];
