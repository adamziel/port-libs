<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpParseTypeLiteralPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/"'
        . ' xmlns:html="http://www.w3.org/1999/xhtml">'
        . '<dc:title><rdf:Alt>'
        . '<rdf:li xml:lang="fr-FR" rdf:parseType="Literal"><html:span>Titre literal ignore</html:span></rdf:li>'
        . '<rdf:li xml:lang="x-default" rdf:parseType="Literal"><html:span>' . htmlspecialchars($title, ENT_XML1) . '</html:span></rdf:li>'
        . '</rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq>'
        . '<rdf:li rdf:parseType="Literal"><html:span>Literal Boundary Author One</html:span></rdf:li>'
        . '<rdf:li rdf:parseType="Literal"><html:span>Literal Boundary Author Two</html:span></rdf:li>'
        . '</rdf:Seq></dc:creator>'
        . '<dc:description rdf:parseType="Literal"><html:p>' . htmlspecialchars($description, ENT_XML1) . '</html:p></dc:description>'
        . '<dc:subject><rdf:Bag>'
        . '<rdf:li rdf:parseType="Literal"><html:span>wordpress</html:span></rdf:li>'
        . '<rdf:li rdf:parseType="Literal"><html:span>xmp-literal</html:span></rdf:li>'
        . '</rdf:Bag></dc:subject>'
        . '<pdf:Producer rdf:parseType="Literal"><html:span>Literal Boundary Producer</html:span></pdf:Producer>'
        . '<xmp:CreatorTool rdf:parseType="Literal"><html:span>Literal Boundary Tool</html:span></xmp:CreatorTool>'
        . '<xmp:CreateDate rdf:parseType="Literal"><html:time>' . htmlspecialchars($date, ENT_XML1) . '</html:time></xmp:CreateDate>'
        . '<xmp:MetadataDate rdf:parseType="Literal"><html:time>2026-06-08T11:34:29Z</html:time></xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpParseTypeLiteralPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP parseType Literal fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (ParseType Literal Info Title) /Author (Info Literal Author) /Producer (Info Literal Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'extracts RDF parseType Literal XMP text values without XML tag leakage' => static function (
        TestRunner $t
    ) use ($xmpParseTypeLiteralPacket, $xmpParseTypeLiteralPdf): void {
        $currentXmp = $xmpParseTypeLiteralPacket(
            'Current Literal XMP Title',
            'Literal XML nodes become plain metadata values',
            '2026-06-08T07:33:29-04:00'
        );
        $decoyXmp = $xmpParseTypeLiteralPacket(
            'Trailing Literal Decoy Title',
            'Trailing literal packet must stay outside current XMP',
            '2026-06-08T11:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpParseTypeLiteralPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP ParseType Literal Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Literal XMP Title', $metadata['title']);
        $t->same('Literal XML nodes become plain metadata values', $metadata['description']);
        $t->same(['Literal Boundary Author One', 'Literal Boundary Author Two'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-literal'], $metadata['keywords']);
        $t->same('Literal Boundary Tool', $metadata['creator_tool']);
        $t->same('Literal Boundary Producer', $metadata['producer']);
        $t->same('2026-06-08T07:33:29-04:00', $metadata['created_at']);
        $t->same('2026-06-08T11:33:29Z', $metadata['created_at_utc']);
        $t->same('2026-06-08T11:34:29Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('ParseType Literal Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP ParseType Literal Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Titre literal ignore'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Literal Decoy Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, '<html:span>'));
        $t->true(!str_contains($plainText, 'Current Literal XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Literal Decoy Title'));
        $t->true(!str_contains($plainText, '<html:span>'));
    },
    'summarizes rejected parseType Literal XML streams without exposing literal payload text' => static function (
        TestRunner $t
    ) use ($xmpParseTypeLiteralPacket, $xmpParseTypeLiteralPdf): void {
        $currentXmp = $xmpParseTypeLiteralPacket(
            'Rejected Literal XMP Title',
            'Rejected literal XMP stays review-only',
            '2026-06-08T11:35:29Z'
        );
        $decoyXmp = $xmpParseTypeLiteralPacket(
            'Rejected Literal Decoy Title',
            'Rejected trailing literal packet stays hidden',
            '2026-06-08T11:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpParseTypeLiteralPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected ParseType Literal Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('ParseType Literal Info Title', $metadata['title']);
        $t->same('Rejected ParseType Literal Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(2, $summary['author_count'] ?? null);
        $t->same(2, $summary['keyword_count'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-08T11:35:29Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-08T11:34:29Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Literal XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Literal Decoy Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, '<html:span>'));
        $t->true(!str_contains($plainText, 'Rejected Literal XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected Literal Decoy Title'));
    },
];
