<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineItemMetadataSummaryBoundaryPdf = static function (): array {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Outline item metadata summary visible body) Tj ET';
    $catalogXmp = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Catalog Metadata Summary Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
    $itemXmp = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hidden Bookmark Metadata Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
    $malformedItemXmp = str_replace('Hidden Bookmark Metadata Title', 'Hidden Malformed Bookmark Metadata Title', $itemXmp);
    $trailingItemXmp = str_replace('Hidden Bookmark Metadata Title', 'Hidden Trailing Bookmark Metadata Title', $itemXmp);

    $catalogStream = gzcompress($catalogXmp);
    $itemStream = gzcompress($itemXmp);
    $malformedItemStream = gzcompress($malformedItemXmp);
    $trailingItemStream = gzcompress($trailingItemXmp);
    if (
        !is_string($catalogStream)
        || !is_string($itemStream)
        || !is_string($malformedItemStream)
        || !is_string($trailingItemStream)
    ) {
        throw new RuntimeException('Unable to compress outline item metadata summary streams.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 20 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Reviewed Bookmark Metadata) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R /Metadata 8 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Malformed Bookmark Metadata) /Parent 5 0 R /Prev 6 0 R /Dest [3 0 R /FitH 680] /A 12 0 R /Metadata 9 0 R 10 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($itemStream) . " >>\nstream\n{$itemStream}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($malformedItemStream) . " >>\nstream\n{$malformedItemStream}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($trailingItemStream) . " >>\nstream\n{$trailingItemStream}\nendstream\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI (https://example.com/bookmark-metadata-review) >>\nendobj\n"
        . "20 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($catalogStream) . " >>\nstream\n{$catalogStream}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $itemXmp, $malformedItemXmp, $trailingItemXmp];
};

return [
    'summarizes outline item Metadata review boundaries without promoting bookmark XMP' => static function (
        TestRunner $t
    ) use ($outlineItemMetadataSummaryBoundaryPdf): void {
        [$pdf, $itemXmp, $malformedItemXmp, $trailingItemXmp] = $outlineItemMetadataSummaryBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $reviewed = $items[0]['metadata_stream_review'] ?? [];
        $malformed = $items[1]['metadata_stream_review'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'catalog'], $metadata['source']);
        $t->same('Catalog Metadata Summary Title', $metadata['title'] ?? null);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same(['Reviewed Bookmark Metadata', 'Malformed Bookmark Metadata'], $outline['titles'] ?? []);
        $t->same(2, $outline['item_count'] ?? null);
        $t->same(2, $outline['resolved_destination_count'] ?? null);
        $t->same(2, $outline['item_metadata_stream_count'] ?? null);
        $t->same(true, $outline['item_metadata_stream_review_only'] ?? null);
        $t->same(false, $outline['item_metadata_stream_payload_included'] ?? null);
        $t->same(false, $outline['item_metadata_stream_accepted_as_document_xmp'] ?? null);
        $t->same([
            'reviewed_outline_item_metadata_stream',
            'rejected_malformed_outline_item_metadata_operand',
        ], $outline['item_metadata_stream_statuses'] ?? null);
        $t->same([8, 9], $outline['item_metadata_stream_objects'] ?? null);
        $t->same([10], $outline['item_metadata_stream_trailing_reference_objects'] ?? null);
        $t->same(['Metadata'], $outline['item_metadata_stream_types'] ?? null);
        $t->same(['XML'], $outline['item_metadata_stream_subtypes'] ?? null);
        $t->same(['FlateDecode'], $outline['item_metadata_stream_filters'] ?? null);

        $t->same('reviewed_outline_item_metadata_stream', $reviewed['status'] ?? null);
        $t->same(8, $reviewed['object_number'] ?? null);
        $t->same(hash('sha256', $itemXmp), $reviewed['sha256'] ?? null);
        $t->same(['title'], $reviewed['xmp_summary']['field_names'] ?? null);
        $t->same(true, $reviewed['xmp_summary']['text_values_redacted'] ?? null);
        $t->same('rejected_malformed_outline_item_metadata_operand', $malformed['status'] ?? null);
        $t->same(9, $malformed['object_number'] ?? null);
        $t->same([10], $malformed['trailing_reference_object_numbers'] ?? null);
        $t->same(false, $malformed['accepted_as_document_xmp'] ?? null);

        foreach ([$itemXmp, $malformedItemXmp, $trailingItemXmp] as $payload) {
            $t->true(is_string($encoded) && !str_contains($encoded, $payload));
        }
        $t->true(is_string($encoded) && !str_contains($encoded, 'Hidden Bookmark Metadata Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Hidden Malformed Bookmark Metadata Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Hidden Trailing Bookmark Metadata Title'));
    },
    'carries outline item Metadata review into navigation while keeping bookmark payloads hidden' => static function (
        TestRunner $t
    ) use ($outlineItemMetadataSummaryBoundaryPdf): void {
        [$pdf, $itemXmp, $malformedItemXmp, $trailingItemXmp] = $outlineItemMetadataSummaryBoundaryPdf();

        $outlineExtractor = new PdfOutlineExtractor();
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $outlineRows = $navigation['outline'] ?? [];
        $actions = $navigation['outline_action_review_actions'] ?? [];
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['Reviewed Bookmark Metadata', 'Malformed Bookmark Metadata'], array_column($outlineRows, 'title'));
        $t->same('reviewed_outline_item_metadata_stream', $outlineRows[0]['metadata_stream_review']['status'] ?? null);
        $t->same(8, $outlineRows[0]['metadata_stream_review']['object_number'] ?? null);
        $t->same('rejected_malformed_outline_item_metadata_operand', $outlineRows[1]['metadata_stream_review']['status'] ?? null);
        $t->same([10], $outlineRows[1]['metadata_stream_review']['trailing_reference_object_numbers'] ?? null);
        $t->same(['URI'], array_column($actions, 'action_type'));
        $t->same('review-uri', $actions[0]['safety'] ?? null);
        $t->same('rejected_malformed_outline_item_metadata_operand', $actions[0]['outline_metadata_stream_review']['status'] ?? null);
        $t->same(9, $actions[0]['outline_metadata_stream_review']['object_number'] ?? null);
        $t->same([10], $actions[0]['outline_metadata_stream_review']['trailing_reference_object_numbers'] ?? null);
        $t->same('Outline item metadata summary visible body', $plainText);

        foreach ([
            'Hidden Bookmark Metadata Title',
            'Hidden Malformed Bookmark Metadata Title',
            'Hidden Trailing Bookmark Metadata Title',
            $itemXmp,
            $malformedItemXmp,
            $trailingItemXmp,
        ] as $payload) {
            $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, $payload));
            $t->true(!str_contains($plainText, $payload));
        }
        $t->true(!str_contains($plainText, 'Reviewed Bookmark Metadata'));
        $t->true(!str_contains($plainText, 'Malformed Bookmark Metadata'));
    },
];
