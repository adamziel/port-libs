<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;

return [
    'bounds root XMP decoding in reader metadata and preserves Info fallback' => static function (TestRunner $t): void {
        $xmp = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
            . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title>Oversized XMP title</dc:title><dc:description>'
            . str_repeat('x', 1_048_577)
            . '</dc:description></rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
        $compressedXmp = gzcompress($xmp, 9);
        if (!is_string($compressedXmp)) {
            throw new RuntimeException('Unable to compress oversized XMP fixture.');
        }

        $pdf = "%PDF-1.7\n"
            . "1 0 obj << /Type /Catalog /Pages 2 0 R /Metadata 4 0 R >> endobj\n"
            . "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            . "3 0 obj << /Type /Page /Parent 2 0 R >> endobj\n"
            . '4 0 obj << /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
            . "5 0 obj << /Title (Info fallback after bounded XMP) >> endobj\n"
            . "trailer << /Root 1 0 R /Info 5 0 R >>\n%%EOF";

        $metadata = (new PdfMetadataExtractor())->extractReaderMetadata($pdf);
        $fullMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);

        $t->same(['info'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Info fallback after bounded XMP', $metadata['title']);
        $t->same(1, $metadata['page_count']);
        $t->same([], $fullMetadata['xmp']);
        $t->same('Info fallback after bounded XMP', $fullMetadata['title']);
    },

    'counts shared page-tree descendants once and terminates page cycles' => static function (TestRunner $t): void {
        $levels = 14;
        $pdf = "%PDF-1.7\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            . "2 0 obj << /Type /Pages /Kids [3 0 R 4 0 R] >> endobj\n";
        for ($level = 0; $level < $levels; $level++) {
            $left = 3 + (2 * $level);
            $right = $left + 1;
            $next = $level === $levels - 1 ? 3 + (2 * $levels) : $left + 2;
            $pdf .= "{$left} 0 obj << /Type /Pages /Kids [{$next} 0 R " . ($next + 1) . " 0 R] >> endobj\n";
            $pdf .= "{$right} 0 obj << /Type /Pages /Kids [{$next} 0 R " . ($next + 1) . " 0 R] >> endobj\n";
        }
        $lastPage = 3 + (2 * $levels);
        $pdf .= "{$lastPage} 0 obj << /Type /Page >> endobj\n"
            . ($lastPage + 1) . " 0 obj << /Type /Page >> endobj\n"
            . "trailer << /Root 1 0 R >>\n%%EOF";

        $structure = (new PdfMetadataExtractor())->extractStructuralMetadata($pdf);
        $cycle = "%PDF-1.7\n"
            . "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            . "2 0 obj << /Type /Pages /Kids [3 0 R] >> endobj\n"
            . "3 0 obj << /Type /Pages /Kids [2 0 R] >> endobj\n"
            . "trailer << /Root 1 0 R >>\n%%EOF";
        $cycleStructure = (new PdfMetadataExtractor())->extractStructuralMetadata($cycle);

        $t->same(32, $structure['object_count']);
        $t->same(2, $structure['page_count']);
        $t->true(!isset($structure['page_count_limited']));
        $t->same(0, $cycleStructure['page_count']);
    },

    'does not count unterminated stream openings as structural streams' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.7\n"
            . "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            . "2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\n"
            . "3 0 obj << /Length 3 >>\nstream\nabc\nendobj\n"
            . "trailer << /Root 1 0 R >>\n%%EOF";

        $structure = (new PdfMetadataExtractor())->extractStructuralMetadata($pdf);

        $t->same(0, $structure['stream_count']);
    },
];
