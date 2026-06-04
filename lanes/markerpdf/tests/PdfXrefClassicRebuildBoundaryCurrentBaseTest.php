<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefClassicRebuildBoundaryCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale classic rebuild page) Tj T* (Old trailer root leak) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current classic rebuild page) Tj T* (Latest trailer boundary kept) Tj ET';

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 6\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . "trailer\n<< /Size 15 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(10, '<< /Type /Catalog /Pages 11 0 R >>');
    $addObject(11, '<< /Type /Pages /Kids [12 0 R] /Count 1 >>');
    $addObject(12, '<< /Type /Page /Parent 11 0 R /Resources << /Font << /F1 13 0 R >> >> /Contents 14 0 R >>');
    $addObject(13, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(14, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $pdf .= "xref\n"
        . "10 5\n"
        . $xrefRow($offsets[10])
        . $xrefRow($offsets[11])
        . $xrefRow($offsets[12])
        . $xrefRow($offsets[13])
        . $xrefRow($offsets[14])
        . "trailer\n<< /Size 15 /Root 10 0 R >>\n"
        . "startxref\n999999\n%%EOF";

    return $pdf;
};

$xrefClassicRebuildStaleStartxrefCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale valid startxref page) Tj T* (Earlier trailer root leak) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current stale-pointer rebuild page) Tj T* (Stale startxref pointer repaired) Tj ET';

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 6\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . "trailer\n<< /Size 15 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(10, '<< /Type /Catalog /Pages 11 0 R >>');
    $addObject(11, '<< /Type /Pages /Kids [12 0 R] /Count 1 >>');
    $addObject(12, '<< /Type /Page /Parent 11 0 R /Resources << /Font << /F1 13 0 R >> >> /Contents 14 0 R >>');
    $addObject(13, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(14, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $pdf .= "xref\n"
        . "10 5\n"
        . $xrefRow($offsets[10])
        . $xrefRow($offsets[11])
        . $xrefRow($offsets[12])
        . $xrefRow($offsets[13])
        . $xrefRow($offsets[14])
        . "trailer\n<< /Size 15 /Root 10 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF";

    return $pdf;
};

$xrefClassicRebuildEofBoundedCurrentBasePdf = static function (): string {
    $currentXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current EOF Bounded XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $trailingXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Trailing Garbage XRef Title</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current EOF bounded page) Tj T* (Post EOF xref ignored) Tj ET';
    $trailingContent = 'BT /F1 12 Tf 72 720 Td (Trailing garbage xref page) Tj T* (Post EOF root leak) Tj ET';

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current EOF Bounded Info Title) /Author (Current Importer) >>');

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 8\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . "trailer\n<< /Size 28 /Root 1 0 R /Info 7 0 R >>\n"
        . "startxref\n999999\n%%EOF\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, "<< /Length " . strlen($trailingContent) . " >>\nstream\n{$trailingContent}\nendstream");
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($trailingXmp) . " >>\nstream\n{$trailingXmp}\nendstream");
    $addObject(27, '<< /Title (Trailing Garbage Info Title) /Author (Garbage Importer) >>');

    $pdf .= "xref\n"
        . "20 8\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow($offsets[23])
        . $xrefRow($offsets[24])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[26])
        . $xrefRow($offsets[27])
        . "trailer\n<< /Size 28 /Root 20 0 R /Info 27 0 R >>\n";

    return $pdf;
};

return [
    'rebuilds damaged startxref from the latest classic xref trailer boundary before WordPress text extraction' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefClassicRebuildBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current classic rebuild page', 'Latest trailer boundary kept'], $extractor->extractTextLines($pdf));
        $t->same(['Current classic rebuild page', 'Latest trailer boundary kept'], $extractor->extractTextRuns($pdf));
        $t->same("Current classic rebuild page\nLatest trailer boundary kept", $text);
        $t->same("Current classic rebuild page\nLatest trailer boundary kept\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale classic rebuild page'));
        $t->true(!str_contains($text, 'Old trailer root leak'));
        $t->true(!str_contains($text, "\0"));
    },
    'rebuilds stale but valid startxref from the later classic xref trailer boundary before WordPress text extraction' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildStaleStartxrefCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefClassicRebuildStaleStartxrefCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current stale-pointer rebuild page', 'Stale startxref pointer repaired'], $extractor->extractTextLines($pdf));
        $t->same(['Current stale-pointer rebuild page', 'Stale startxref pointer repaired'], $extractor->extractTextRuns($pdf));
        $t->same("Current stale-pointer rebuild page\nStale startxref pointer repaired", $text);
        $t->same("Current stale-pointer rebuild page\nStale startxref pointer repaired\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale valid startxref page'));
        $t->true(!str_contains($text, 'Earlier trailer root leak'));
        $t->true(!str_contains($text, "\0"));
    },
    'bounds classic xref rebuild before trailing EOF garbage tables' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildEofBoundedCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefClassicRebuildEofBoundedCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);

        $t->same(['Current EOF bounded page', 'Post EOF xref ignored'], $extractor->extractTextLines($pdf));
        $t->same(['Current EOF bounded page', 'Post EOF xref ignored'], $extractor->extractTextRuns($pdf));
        $t->same("Current EOF bounded page\nPost EOF xref ignored", $text);
        $t->same("Current EOF bounded page\nPost EOF xref ignored\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current EOF Bounded XRef Title', $metadata['title']);
        $t->same('Current EOF Bounded Info Title', $metadata['info']['Title']);
        $t->same('Current Importer', $metadata['info']['Author']);
        $t->true(!str_contains($text, 'Trailing garbage xref page'));
        $t->true(!str_contains($text, 'Post EOF root leak'));
        $t->true(!str_contains(json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '', 'Trailing Garbage'));
        $t->true(!str_contains($text, "\0"));
    },
];
