<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineActionNextOperandBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline action Next operand intro body) Tj ET';
    $targetContent = 'BT /F1 12 Tf 72 720 Td (Outline action Next operand target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Action Next Operand Chapter) /Parent 5 0 R /A 12 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /GoTo /D /ReviewTarget /Next 13 0 R 14 0 R >>\nendobj\n"
        . "13 0 obj\n<< /S /URI /URI (https://example.com/tailed-outline-next-action) >>\nendobj\n"
        . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('tailed outline action should not execute'\\)) >>\nendobj\n"
        . "20 0 obj\n<< /Names [(ReviewTarget) [4 0 R /FitH 640]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'rejects malformed outline action Next operands in document metadata action summaries' => static function (
        TestRunner $t
    ) use ($outlineActionNextOperandBoundaryPdf): void {
        $pdf = $outlineActionNextOperandBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $item = $outline['items'][0] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(['Action Next Operand Chapter'], $outline['titles'] ?? null);
        $t->same('Action Next Operand Chapter', $item['title'] ?? null);
        $t->same(6, $item['outline_object'] ?? null);
        $t->same('GoTo', $item['action_type'] ?? null);
        $t->same(12, $item['action_object'] ?? null);
        $t->same('ReviewTarget', $item['destination'] ?? null);
        $t->same(true, $item['destination_resolved'] ?? null);
        $t->same(1, $item['page'] ?? null);
        $t->same(4, $item['page_object'] ?? null);
        $t->same('FitH', $item['view_mode'] ?? null);
        $t->same(['top' => 640.0], $item['view_parameters'] ?? null);
        $t->same(true, $item['action_review_only'] ?? null);
        $t->same(false, $item['action_payload_included'] ?? null);
        $t->same(false, $item['executes_action'] ?? null);
        $t->same(1, $item['action_chain_count'] ?? null);
        $t->same(['GoTo'], $item['action_chain_types'] ?? null);
        $t->same([12], $item['action_chain_objects'] ?? null);
        $t->same(false, $item['action_chain_has_next'] ?? null);
        $t->same(false, $item['action_chain_has_javascript'] ?? null);
        $t->same(false, $item['action_chain_has_launch'] ?? null);

        $t->true(is_string($encoded) && !str_contains($encoded, 'tailed-outline-next-action'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'tailed outline action should not execute'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'JavaScript'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'URI'));
    },
    'keeps malformed outline action Next followups out of navigation review and visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineActionNextOperandBoundaryPdf): void {
        $pdf = $outlineActionNextOperandBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $plainToc = $outlineExtractor->getPdfToc($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $lightweight = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $lightweightEncoded = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

        $t->same(['Action Next Operand Chapter'], array_column($toc, 'title'));
        $t->same(['Action Next Operand Chapter'], array_column($plainToc, 'title'));
        $t->same(['Action Next Operand Chapter'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same(['Action Next Operand Chapter'], array_column($lightweight['pdf_toc'] ?? [], 'title'));
        $t->same([1], array_column($toc, 'level'));
        $t->same([1], array_column($toc, 'page'));
        $t->same(['FitH'], array_column($toc, 'view_mode'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same([], $remoteActions);
        $t->same("Outline action Next operand intro body\nOutline action Next operand target body", $plainText);

        foreach (['tailed-outline-next-action', 'tailed outline action should not execute', 'JavaScript'] as $forbidden) {
            $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, $forbidden));
            $t->true(is_string($lightweightEncoded) && !str_contains($lightweightEncoded, $forbidden));
            $t->true(!str_contains($plainText, $forbidden));
        }
        $t->true(!str_contains($plainText, 'Action Next Operand Chapter'));
        $t->true(!str_contains($plainText, 'ReviewTarget'));
    },
];
