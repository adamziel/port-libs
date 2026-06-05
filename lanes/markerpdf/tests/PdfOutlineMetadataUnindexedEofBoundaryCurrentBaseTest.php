<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataUnindexedEofBoundaryCurrentBasePdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Current unindexed outline body) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Outlines 40 0 R /PageMode /UseOutlines >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(10, '<< /Title (Current Unindexed Outline Info) /Author (Current Metadata Author) >>');

    // These outline objects are intentionally omitted from the damaged xref table.
    // Native repair may use direct objects before the selected EOF, but not stale
    // duplicates appended after that EOF.
    $addObject(40, '<< /Type /Outlines /First 41 0 R /Last 41 0 R /Count 1 >>');
    $addObject(41, '<< /Title (Current Unindexed Outline Chapter) /Parent 40 0 R /Dest [3 0 R /FitH 720] /C [0 .4 .8] /F 2 >>');

    $xrefOffset = strlen($pdf);
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets[10])
        . "trailer\n<< /Size 11 /Root 1 0 R /Info 10 0 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF\n"
        . "40 0 obj\n<< /Type /Outlines /First 42 0 R /Last 42 0 R /Count 1 >>\nendobj\n"
        . "42 0 obj\n<< /Title (Stale Post EOF Outline Chapter) /Parent 40 0 R /Dest [3 0 R /Fit] >>\nendobj\n";

    return $pdf;
};

return [
    'uses pre-eof unindexed outline objects before stale post-eof duplicates in metadata' => static function (
        TestRunner $t
    ) use ($outlineMetadataUnindexedEofBoundaryCurrentBasePdf): void {
        $pdf = $outlineMetadataUnindexedEofBoundaryCurrentBasePdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same('Current Unindexed Outline Info', $metadata['title']);
        $t->same('Current Metadata Author', $metadata['authors'][0] ?? null);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(40, $outline['outline_root_object'] ?? null);
        $t->same(41, $outline['first_item_object'] ?? null);
        $t->same(41, $outline['last_item_object'] ?? null);
        $t->same(['Current Unindexed Outline Chapter'], $outline['titles'] ?? []);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(1, count($items));
        $t->same('Current Unindexed Outline Chapter', $items[0]['title'] ?? null);
        $t->same(41, $items[0]['outline_object'] ?? null);
        $t->same(0, $items[0]['page'] ?? null);
        $t->same(3, $items[0]['page_object'] ?? null);
        $t->same('FitH', $items[0]['view_mode'] ?? null);
        $t->same(['top' => 720.0], $items[0]['view_parameters'] ?? null);
        $t->same('#0066cc', $items[0]['text_color_hex'] ?? null);

        $t->same('Current unindexed outline body', $plainText);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Post EOF Outline Chapter'));
        $t->true(!str_contains($plainText, 'Current Unindexed Outline Chapter'));
        $t->true(!str_contains($plainText, 'Stale Post EOF Outline Chapter'));
    },
];
