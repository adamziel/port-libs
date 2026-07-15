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

    'keeps a no-Length filtered XMP stream intact when its compressed bytes mimic PDF delimiters' => static function (TestRunner $t): void {
        $xmp = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
            . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title>Delimiter-safe XMP title</dc:title><dc:description>before'
            . "\nendstream\nendobj\n"
            . 'after</dc:description></rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
        // Level zero emits an uncompressed DEFLATE block, making the marker
        // above appear verbatim inside otherwise valid filtered stream bytes.
        $compressedXmp = gzcompress($xmp, 0);
        if (!is_string($compressedXmp) || !str_contains($compressedXmp, "\nendstream\nendobj\n")) {
            throw new RuntimeException('Unable to construct delimiter-like filtered XMP fixture.');
        }

        $pdf = "%PDF-1.7\n"
            . "1 0 obj << /Type /Catalog /Pages 2 0 R /Metadata 4 0 R >> endobj\n"
            . "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            . "3 0 obj << /Type /Page /Parent 2 0 R >> endobj\n"
            . "4 0 obj << /Type /Metadata /Subtype /XML /Filter /FlateDecode >>\nstream\n{$compressedXmp}\nendstream\nendobj\n% legal delimiter comment\n"
            . "trailer << /Root 1 0 R >>\n%%EOF";

        $metadata = (new PdfMetadataExtractor())->extractReaderMetadata($pdf);

        $t->same(['xmp'], $metadata['source']);
        $t->same('Delimiter-safe XMP title', $metadata['title']);
        $t->same('before endstream endobj after', $metadata['description']);
        $t->same(4, $metadata['object_count']);
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
    'reader structural preflight ignores forged PDF syntax in comments strings and streams' => static function (TestRunner $t): void {
        $forged = "99 0 obj << /Type /Page >> endobj\ntrailer << /Encrypt 99 0 R >>";
        $pdf = "%PDF-1.7\n"
            . "% {$forged}\n"
            . "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            . "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            . "3 0 obj << /Type /Page /Parent 2 0 R >> endobj\n"
            . "4 0 obj << /Type /Example /Payload ({$forged}) >> endobj\n"
            . '5 0 obj << /Length ' . strlen($forged) . " >>\nstream\n{$forged}\nendstream\nendobj\n"
            . "trailer << /Root 1 0 R >>\n%%EOF";

        $metadata = (new PdfMetadataExtractor())->extractReaderStructuralMetadata($pdf);

        $t->same(5, $metadata['object_count']);
        $t->same(1, $metadata['stream_count']);
        $t->same(1, $metadata['page_count']);
        $t->true(!isset($metadata['encryption']));
    },
    'does not promote direct Info metadata from an ambiguous appended revision' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.7\n"
            . "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            . "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            . "3 0 obj << /Type /Page /Parent 2 0 R >> endobj\n"
            . "4 0 obj << /Title (Stale first revision title) >> endobj\n"
            . "trailer << /Root 1 0 R /Info 4 0 R >>\nstartxref\n0\n%%EOF\n"
            . "5 0 obj << /Title (Ambiguous appended title) >> endobj\n"
            . "trailer << /Root 1 0 R /Info 5 0 R >>\nstartxref\n0\n%%EOF";

        $metadata = (new PdfMetadataExtractor())->extractReaderMetadata($pdf);

        $t->same([], $metadata['info']);
        $t->true(!isset($metadata['title']));
    },
    'uses the exact active page tree with bounded TraceMonkey reader metadata' => static function (TestRunner $t): void {
        $sample = dirname(__DIR__, 3) . '/pandoc-showcase/samples/pdf-tracemonkey-tracemonkey.pdf';
        if (!is_file($sample)) {
            throw new RuntimeException('TraceMonkey PDF sample is required for reader preflight coverage.');
        }

        $bytes = file_get_contents($sample);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read TraceMonkey PDF sample.');
        }

        $startedAt = microtime(true);
        $metadata = (new PdfMetadataExtractor())->extractReaderStructuralMetadata($bytes);
        $elapsed = microtime(true) - $startedAt;

        $t->same(14, $metadata['page_count']);
        $t->true(!isset($metadata['page_count_limited']));
        $t->same(568, $metadata['object_count']);
        $t->true($elapsed < 8.0, 'Exact page inventory must remain bounded enough for import-mode selection.');
    },
];
