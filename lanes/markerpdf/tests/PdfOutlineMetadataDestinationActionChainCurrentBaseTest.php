<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataDestinationActionChainPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline destination action metadata intro body) Tj ET';
    $targetContent = 'BT /F1 12 Tf 72 720 Td (Outline destination action metadata target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Destination Action Metadata Chapter) /Parent 5 0 R /Dest /ReviewAction >>\nendobj\n"
        . "20 0 obj\n<< /Names [(ReviewTarget) [4 0 R /FitH 640] (ReviewAction) 21 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /S /GoTo /D /ReviewTarget /Next [22 0 R 23 0 R 24 0 R] >>\nendobj\n"
        . "22 0 obj\n<< /S /URI /URI (https://example.com/outline-document-metadata-action) >>\nendobj\n"
        . "23 0 obj\n<< /S /JavaScript /JS (app.alert\\('outline document metadata action should not execute'\\)) >>\nendobj\n"
        . "24 0 obj\n<< /S /Launch /F (metadata-review-helper.exe) /Win << /F (metadata-review-helper.exe) /O (open) >> >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'summarizes destination action chains in document outline metadata without payloads' => static function (
        TestRunner $t
    ) use ($outlineMetadataDestinationActionChainPdf): void {
        $pdf = $outlineMetadataDestinationActionChainPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $item = $outline['items'][0] ?? [];
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(['Destination Action Metadata Chapter'], $outline['titles'] ?? []);
        $t->same('Destination Action Metadata Chapter', $item['title'] ?? null);
        $t->same(6, $item['outline_object'] ?? null);
        $t->same('ReviewTarget', $item['destination'] ?? null);
        $t->same(true, $item['destination_resolved'] ?? null);
        $t->same(1, $item['page'] ?? null);
        $t->same('2', $item['page_label'] ?? null);
        $t->same(4, $item['page_object'] ?? null);
        $t->same('FitH', $item['view_mode'] ?? null);
        $t->same(['top' => 640.0], $item['view_parameters'] ?? null);
        $t->same(true, $item['destination_action_review_only'] ?? null);
        $t->same(false, $item['destination_action_payload_included'] ?? null);
        $t->same(false, $item['destination_action_executes_action'] ?? null);
        $t->same('ReviewAction', $item['destination_action_name'] ?? null);
        $t->same(21, $item['destination_action_object'] ?? null);
        $t->same('GoTo', $item['destination_action_type'] ?? null);
        $t->same(4, $item['destination_action_chain_count'] ?? null);
        $t->same(['GoTo', 'URI', 'JavaScript', 'Launch'], $item['destination_action_chain_types'] ?? null);
        $t->same([21, 22, 23, 24], $item['destination_action_chain_objects'] ?? null);
        $t->same(true, $item['destination_action_chain_has_next'] ?? null);
        $t->same(true, $item['destination_action_chain_has_javascript'] ?? null);
        $t->same(true, $item['destination_action_chain_has_launch'] ?? null);
        $t->same("Outline destination action metadata intro body\nOutline destination action metadata target body", $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'outline-document-metadata-action'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'outline document metadata action should not execute'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'metadata-review-helper.exe'));
        $t->true(!str_contains($plainText, 'Destination Action Metadata Chapter'));
        $t->true(!str_contains($plainText, 'ReviewAction'));
        $t->true(!str_contains($plainText, 'ReviewTarget'));
        $t->true(!str_contains($plainText, 'outline-document-metadata-action'));
        $t->true(!str_contains($plainText, 'outline document metadata action should not execute'));
        $t->true(!str_contains($plainText, 'metadata-review-helper.exe'));
    },
    'keeps destination action summaries separate from direct outline action metadata' => static function (
        TestRunner $t
    ) use ($outlineMetadataDestinationActionChainPdf): void {
        $pdf = $outlineMetadataDestinationActionChainPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $item = $metadata['document_outline']['items'][0] ?? [];

        $t->same(null, $item['action_type'] ?? null);
        $t->same(null, $item['action_object'] ?? null);
        $t->true(!array_key_exists('action_review_only', $item));
        $t->true(!array_key_exists('action_payload_included', $item));
        $t->true(!array_key_exists('executes_action', $item));
        $t->true(!array_key_exists('action_chain_count', $item));
        $t->true(!array_key_exists('action_chain_types', $item));
        $t->true(!array_key_exists('action_chain_objects', $item));
        $t->same('ReviewAction', $item['destination_action_name'] ?? null);
        $t->same(false, $item['destination_action_payload_included'] ?? null);
        $t->same(false, $item['destination_action_executes_action'] ?? null);
        $t->same(4, $item['destination_action_chain_count'] ?? null);
        $t->same(['GoTo', 'URI', 'JavaScript', 'Launch'], $item['destination_action_chain_types'] ?? null);
        $t->same([21, 22, 23, 24], $item['destination_action_chain_objects'] ?? null);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'outline-document-metadata-action'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'outline document metadata action should not execute'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'metadata-review-helper.exe'));
    },
];
