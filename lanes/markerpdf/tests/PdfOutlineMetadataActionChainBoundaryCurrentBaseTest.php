<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataActionChainBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline action chain metadata intro body) Tj ET';
    $targetContent = 'BT /F1 12 Tf 72 720 Td (Outline action chain metadata target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Action Chain Metadata Chapter) /Parent 5 0 R /A 12 0 R /C [0 .25 .5] /F 2 >>\nendobj\n"
        . "12 0 obj\n<< /S /GoTo /D /ReviewTarget /Next [13 0 R 14 0 R 13 0 R 15 0 R] >>\nendobj\n"
        . "13 0 obj\n<< /S /URI /URI (https://example.com/outline-action-chain-review) >>\nendobj\n"
        . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('outline action chain should not execute'\\)) /Next 12 0 R >>\nendobj\n"
        . "15 0 obj\n<< /S /Launch /F (outline-review-helper.exe) /Win << /F (outline-review-helper.exe) /O (open) >> >>\nendobj\n"
        . "20 0 obj\n<< /Names [(ReviewTarget) [4 0 R /FitH 680]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'summarizes outline action Next chains in document metadata without payload leakage' => static function (
        TestRunner $t
    ) use ($outlineMetadataActionChainBoundaryPdf): void {
        $pdf = $outlineMetadataActionChainBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $item = $items[0] ?? [];
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(['Action Chain Metadata Chapter'], $outline['titles'] ?? []);
        $t->same('Action Chain Metadata Chapter', $item['title'] ?? null);
        $t->same(6, $item['outline_object'] ?? null);
        $t->same('GoTo', $item['action_type'] ?? null);
        $t->same(12, $item['action_object'] ?? null);
        $t->same(true, $item['destination_resolved'] ?? null);
        $t->same('ReviewTarget', $item['destination'] ?? null);
        $t->same(1, $item['page'] ?? null);
        $t->same('FitH', $item['view_mode'] ?? null);
        $t->same(['top' => 680.0], $item['view_parameters'] ?? null);
        $t->same(true, $item['action_review_only'] ?? null);
        $t->same(false, $item['action_payload_included'] ?? null);
        $t->same(false, $item['executes_action'] ?? null);
        $t->same(4, $item['action_chain_count'] ?? null);
        $t->same(['GoTo', 'URI', 'JavaScript', 'Launch'], $item['action_chain_types'] ?? null);
        $t->same([12, 13, 14, 15], $item['action_chain_objects'] ?? null);
        $t->same(true, $item['action_chain_has_next'] ?? null);
        $t->same(true, $item['action_chain_has_javascript'] ?? null);
        $t->same(true, $item['action_chain_has_launch'] ?? null);
        $t->same('#004080', $item['text_color_hex'] ?? null);
        $t->same("Outline action chain metadata intro body\nOutline action chain metadata target body", $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'outline-action-chain-review'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'outline action chain should not execute'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'outline-review-helper.exe'));
        $t->true(!str_contains($plainText, 'Action Chain Metadata Chapter'));
        $t->true(!str_contains($plainText, 'outline-action-chain-review'));
        $t->true(!str_contains($plainText, 'outline action chain should not execute'));
        $t->true(!str_contains($plainText, 'outline-review-helper.exe'));
    },
    'matches navigation action review chain count while keeping document metadata sanitized' => static function (
        TestRunner $t
    ) use ($outlineMetadataActionChainBoundaryPdf): void {
        $pdf = $outlineMetadataActionChainBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $actions = $navigation['outline_action_review_actions'] ?? [];
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['GoTo', 'URI', 'JavaScript', 'Launch'], array_column($actions, 'action_type'));
        $t->same([12, 13, 14, 15], array_column($actions, 'action_object'));
        $t->same(4, $metadata['document_outline']['items'][0]['action_chain_count'] ?? null);
        $t->same(array_column($actions, 'action_type'), $metadata['document_outline']['items'][0]['action_chain_types'] ?? null);
        $t->true(is_string($encodedNavigation) && str_contains($encodedNavigation, 'outline-action-chain-review'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'outline-action-chain-review'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'outline-review-helper.exe'));
    },
];
