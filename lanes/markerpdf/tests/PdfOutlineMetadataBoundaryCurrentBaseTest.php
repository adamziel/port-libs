<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataBoundaryPdf = static function (): array {
    $currentXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Outline Metadata Boundary Title</rdf:li></rdf:Alt></dc:title>'
        . '<xmp:CreateDate>2026-06-02T23:00:09Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
    $staleXmp = str_replace('Current Outline Metadata Boundary Title', 'Stale Outline Metadata Boundary Title', $currentXmp);
    $currentXmpStream = gzcompress($currentXmp);
    $staleXmpStream = gzcompress($staleXmp);
    if (!is_string($currentXmpStream) || !is_string($staleXmpStream)) {
        throw new RuntimeException('Unable to compress outline metadata fixture streams.');
    }

    $introContent = 'BT /F1 12 Tf 72 720 Td (Current outline metadata intro text) Tj ET';
    $targetContent = 'BT /F1 12 Tf 72 720 Td (Current outline metadata target body) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale outline metadata body) Tj ET';

    $pdf = "%PDF-2.0\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Metadata 14 0 R /Outlines 40 0 R /Names << /Dests 50 0 R >> /PageMode /UseOutlines >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>');
    $addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Contents 32 0 R >>');
    $addObject(14, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmpStream) . " >>\nstream\n{$currentXmpStream}\nendstream");
    $addObject(31, 0, "<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream");
    $addObject(32, 0, "<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream");
    $addObject(40, 0, '<< /Type /Outlines /First 41 0 R /Last 42 0 R /Count 2 >>');
    $addObject(41, 0, '<< /Title (Current Outline Metadata Chapter) /Parent 40 0 R /Dest /ReviewStart /Next 42 0 R /First 43 0 R /Last 43 0 R /Count -1 /C [0 .35 .7] /F 2 >>');
    $addObject(42, 0, '<< /Title (Current Outline Action Appendix) /Parent 40 0 R /Prev 41 0 R /A 44 0 R >>');
    $addObject(43, 0, '<< /Title (Collapsed Child Metadata) /Parent 41 0 R /Dest /AppendixTarget /C [80 0 R 81 0 R 82 0 R] >>');
    $addObject(44, 0, '<< /S /GoTo /D [4 0 R /FitR 10 20 300 700] >>');
    $addObject(50, 0, '<< /Names [(AppendixTarget) [4 0 R /XYZ 144 null 0] (ReviewStart) [3 0 R /FitH 720]] >>');
    $addObject(60, 0, '<< /Title (Current Info Outline Fallback) /Author (Current Outline Metadata Author) >>');
    $addObject(80, 0, '-.25');
    $addObject(81, 0, '.5');
    $addObject(82, 0, '1.2');

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber < 91; $objectNumber++) {
        if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 90)) {
            $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
            continue;
        }

        $rows .= pack('CNn', 1, $objectNumber === 90 ? $xrefOffset : $offsets[$objectNumber], 0);
    }

    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress outline metadata xref stream.');
    }

    $pdf .= "90 0 obj\n"
        . '<< /Type /XRef /Size 91 /Root 1 0 R /Info 60 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 15 0 R /Outlines 70 0 R >>\nendobj\n"
        . "14 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($staleXmpStream) . " >>\nstream\n{$staleXmpStream}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Outlines /First 71 0 R /Last 71 0 R /Count 1 >>\nendobj\n"
        . "60 0 obj\n<< /Title (Stale Info Outline Fallback) /Author (Stale Outline Metadata Author) >>\nendobj\n"
        . "70 0 obj\n<< /Type /Outlines /First 71 0 R /Last 71 0 R /Count 1 >>\nendobj\n"
        . "71 0 obj\n<< /Title (Stale Outline Metadata Title) /Parent 70 0 R /Dest [3 0 R /Fit] >>\nendobj\n";

    return [$pdf, $currentXmp, $staleXmp, $staleContent];
};

$outlineActionDestinationBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline action destination boundary intro body) Tj ET';
    $targetContent = 'BT /F1 12 Tf 72 720 Td (Outline action destination boundary local target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 9 0 R /Count 4 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Local Action Boundary Chapter) /Parent 5 0 R /Dest /CurrentLocalTarget /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Remote Action Boundary Appendix) /Parent 5 0 R /A 12 0 R /Prev 6 0 R /Next 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Title (URI Action Boundary Appendix) /Parent 5 0 R /A 13 0 R /Prev 7 0 R /Next 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Title (Embedded Action Boundary Appendix) /Parent 5 0 R /A 14 0 R /Prev 8 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /GoToR /F (remote-outline.pdf) /D /CurrentLocalTarget /NewWindow true >>\nendobj\n"
        . "13 0 obj\n<< /S /URI /URI (https://example.com/remote-outline-review) /D /CurrentLocalTarget >>\nendobj\n"
        . "14 0 obj\n<< /S /GoToE /T << /R /C /N (embedded-outline.pdf) >> /D /CurrentLocalTarget /NewWindow false >>\nendobj\n"
        . "20 0 obj\n<< /Names [(CurrentLocalTarget) [4 0 R /FitH 680]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'summarizes current xref-selected catalog Outlines in document metadata' => static function (
        TestRunner $t
    ) use ($outlineMetadataBoundaryPdf): void {
        [$pdf] = $outlineMetadataBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];

        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current Outline Metadata Boundary Title', $metadata['title']);
        $t->same('Current Outline Metadata Author', $metadata['authors'][0] ?? null);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same($outline, $metadata['catalog']['document_outline'] ?? []);

        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(true, $outline['review_only'] ?? null);
        $t->same(false, $outline['payload_included'] ?? null);
        $t->same(40, $outline['outline_root_object'] ?? null);
        $t->same(41, $outline['first_item_object'] ?? null);
        $t->same(42, $outline['last_item_object'] ?? null);
        $t->same(2, $outline['declared_visible_count'] ?? null);
        $t->same(3, $outline['item_count'] ?? null);
        $t->same(3, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(2, $outline['max_depth'] ?? null);
        $t->same([
            'Current Outline Metadata Chapter',
            'Collapsed Child Metadata',
            'Current Outline Action Appendix',
        ], $outline['titles'] ?? []);

        $t->same(3, count($items));
        $t->same('Current Outline Metadata Chapter', $items[0]['title'] ?? null);
        $t->same(1, $items[0]['level'] ?? null);
        $t->same(41, $items[0]['outline_object'] ?? null);
        $t->same(43, $items[0]['first_child_object'] ?? null);
        $t->same(true, $items[0]['has_children'] ?? null);
        $t->same(-1, $items[0]['outline_count'] ?? null);
        $t->same(1, $items[0]['descendant_count'] ?? null);
        $t->same(true, $items[0]['is_collapsed'] ?? null);
        $t->same('collapsed', $items[0]['structure_state'] ?? null);
        $t->same(2, $items[0]['style_flags'] ?? null);
        $t->same(true, $items[0]['is_bold'] ?? null);
        $t->same([0.0, 0.35, 0.7], $items[0]['text_color_rgb'] ?? null);
        $t->same('#0059b3', $items[0]['text_color_hex'] ?? null);
        $t->same('ReviewStart', $items[0]['destination'] ?? null);
        $t->same(true, $items[0]['destination_resolved'] ?? null);
        $t->same(0, $items[0]['page'] ?? null);
        $t->same(3, $items[0]['page_object'] ?? null);
        $t->same('FitH', $items[0]['view_mode'] ?? null);
        $t->same(['top' => 720.0], $items[0]['view_parameters'] ?? null);

        $t->same('Collapsed Child Metadata', $items[1]['title'] ?? null);
        $t->same(2, $items[1]['level'] ?? null);
        $t->same(43, $items[1]['outline_object'] ?? null);
        $t->same(41, $items[1]['parent_object'] ?? null);
        $t->same('leaf', $items[1]['structure_state'] ?? null);
        $t->same([0.0, 0.5, 1.0], $items[1]['text_color_rgb'] ?? null);
        $t->same('#0080ff', $items[1]['text_color_hex'] ?? null);
        $t->same('AppendixTarget', $items[1]['destination'] ?? null);
        $t->same(1, $items[1]['page'] ?? null);
        $t->same(4, $items[1]['page_object'] ?? null);
        $t->same('XYZ', $items[1]['view_mode'] ?? null);
        $t->same(['left' => 144.0, 'top' => null, 'zoom' => null], $items[1]['view_parameters'] ?? null);

        $t->same('Current Outline Action Appendix', $items[2]['title'] ?? null);
        $t->same(42, $items[2]['outline_object'] ?? null);
        $t->same(41, $items[2]['previous_object'] ?? null);
        $t->same('GoTo', $items[2]['action_type'] ?? null);
        $t->same(44, $items[2]['action_object'] ?? null);
        $t->true(!array_key_exists('text_color_hex', $items[2]));
        $t->same(true, $items[2]['destination_resolved'] ?? null);
        $t->same(1, $items[2]['page'] ?? null);
        $t->same('FitR', $items[2]['view_mode'] ?? null);
        $t->same(['left' => 10.0, 'bottom' => 20.0, 'right' => 300.0, 'top' => 700.0], $items[2]['view_parameters'] ?? null);
    },
    'preserves outline text color metadata without promoting it to page text' => static function (
        TestRunner $t
    ) use ($outlineMetadataBoundaryPdf): void {
        [$pdf] = $outlineMetadataBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $items = $metadata['document_outline']['items'] ?? [];

        $t->same(['#0059b3', '#0080ff'], array_values(array_filter(array_map(
            static fn (array $item): ?string => $item['text_color_hex'] ?? null,
            $items
        ))));
        $t->same([0.0, 0.35, 0.7], $items[0]['text_color_rgb'] ?? null);
        $t->same('#0059b3', $items[0]['text_color_hex'] ?? null);
        $t->same([0.0, 0.5, 1.0], $items[1]['text_color_rgb'] ?? null);
        $t->same('#0080ff', $items[1]['text_color_hex'] ?? null);
        $t->true(!array_key_exists('text_color_hex', $items[2] ?? []));
        $t->true(!str_contains($plainText, '#0059b3'));
        $t->true(!str_contains($plainText, '#0080ff'));
    },
    'keeps outline metadata and stale appended objects out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineMetadataBoundaryPdf): void {
        [$pdf, $currentXmp, $staleXmp, $staleContent] = $outlineMetadataBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same("Current outline metadata intro text\nCurrent outline metadata target body", $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, $currentXmp));
        $t->true(is_string($encoded) && !str_contains($encoded, $staleXmp));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Outline Metadata Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Info Outline Fallback'));
        $t->true(!str_contains($plainText, 'Current Outline Metadata Chapter'));
        $t->true(!str_contains($plainText, 'Collapsed Child Metadata'));
        $t->true(!str_contains($plainText, 'Current Outline Action Appendix'));
        $t->true(!str_contains($plainText, 'Current Outline Metadata Boundary Title'));
        $t->true(!str_contains($plainText, 'Stale Outline Metadata Boundary Title'));
        $t->true(!str_contains($plainText, 'Stale Outline Metadata Title'));
        $t->true(!str_contains($plainText, $staleContent));
    },
    'does not resolve remote outline action destinations as current-document metadata targets' => static function (
        TestRunner $t
    ) use ($outlineActionDestinationBoundaryPdf): void {
        $pdf = $outlineActionDestinationBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(9, $outline['last_item_object'] ?? null);
        $t->same(4, $outline['declared_visible_count'] ?? null);
        $t->same(4, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(3, $outline['unresolved_destination_count'] ?? null);
        $t->same([
            'Local Action Boundary Chapter',
            'Remote Action Boundary Appendix',
            'URI Action Boundary Appendix',
            'Embedded Action Boundary Appendix',
        ], $outline['titles'] ?? []);
        $t->same([6, 7, 8, 9], array_column($items, 'outline_object'));
        $t->same([5, 5, 5, 5], array_column($items, 'parent_object'));
        $t->same(['CurrentLocalTarget', 'CurrentLocalTarget', 'CurrentLocalTarget', 'CurrentLocalTarget'], array_column($items, 'destination'));

        $t->same(true, $items[0]['destination_resolved'] ?? null);
        $t->same(null, $items[0]['action_type'] ?? null);
        $t->same(1, $items[0]['page'] ?? null);
        $t->same(4, $items[0]['page_object'] ?? null);
        $t->same('FitH', $items[0]['view_mode'] ?? null);
        $t->same(['top' => 680.0], $items[0]['view_parameters'] ?? null);

        foreach ([1 => 'GoToR', 2 => 'URI', 3 => 'GoToE'] as $index => $actionType) {
            $t->same($actionType, $items[$index]['action_type'] ?? null);
            $t->same(11 + $index, $items[$index]['action_object'] ?? null);
            $t->same(false, $items[$index]['destination_resolved'] ?? null);
            $t->true(!array_key_exists('page', $items[$index]));
            $t->true(!array_key_exists('page_object', $items[$index]));
            $t->true(!array_key_exists('view_mode', $items[$index]));
            $t->true(!array_key_exists('view_parameters', $items[$index]));
        }

        $t->same("Outline action destination boundary intro body\nOutline action destination boundary local target body", $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'remote-outline.pdf'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'remote-outline-review'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'embedded-outline.pdf'));
        $t->true(!str_contains($plainText, 'Local Action Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Remote Action Boundary Appendix'));
        $t->true(!str_contains($plainText, 'URI Action Boundary Appendix'));
        $t->true(!str_contains($plainText, 'Embedded Action Boundary Appendix'));
        $t->true(!str_contains($plainText, 'remote-outline.pdf'));
        $t->true(!str_contains($plainText, 'remote-outline-review'));
        $t->true(!str_contains($plainText, 'embedded-outline.pdf'));
    },
];
