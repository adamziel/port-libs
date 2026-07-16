<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpNestedQualifierPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt>'
        . '<rdf:li xml:lang="fr-FR" rdf:parseType="Resource"><rdf:value>Titre de qualification ignore</rdf:value><xmp:labels><rdf:Bag><rdf:li>ignored title label qualifier</rdf:li></rdf:Bag></xmp:labels></rdf:li>'
        . '<rdf:li xml:lang="x-default" rdf:parseType="Resource"><rdf:value>' . htmlspecialchars($title, ENT_XML1) . '</rdf:value><xmp:labels><rdf:Bag><rdf:li>title nested qualifier label</rdf:li></rdf:Bag></xmp:labels></rdf:li>'
        . '</rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq>'
        . '<rdf:li rdf:parseType="Resource"><rdf:value>Nested Qualifier Author One</rdf:value><xmp:roles><rdf:Bag><rdf:li>copy editor qualifier</rdf:li></rdf:Bag></xmp:roles></rdf:li>'
        . '<rdf:li rdf:parseType="Resource"><rdf:value>Nested Qualifier Author Two</rdf:value><xmp:roles><rdf:Seq><rdf:li>metadata reviewer qualifier</rdf:li></rdf:Seq></xmp:roles></rdf:li>'
        . '</rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default" rdf:parseType="Resource"><rdf:value>' . htmlspecialchars($description, ENT_XML1) . '</rdf:value><pdf:Producer><rdf:Seq><rdf:li>description nested qualifier producer</rdf:li></rdf:Seq></pdf:Producer></rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag>'
        . '<rdf:li>wordpress</rdf:li>'
        . '<rdf:li rdf:parseType="Resource"><rdf:value>nested-qualifier-xmp</rdf:value><xmp:tag><rdf:Seq><rdf:li>internal keyword qualifier</rdf:li></rdf:Seq></xmp:tag></rdf:li>'
        . '</rdf:Bag></dc:subject>'
        . '<pdf:Producer rdf:parseType="Resource"><rdf:value>Nested Qualifier Producer</rdf:value><xmp:qualifier><rdf:Bag><rdf:li>producer nested qualifier</rdf:li></rdf:Bag></xmp:qualifier></pdf:Producer>'
        . '<xmp:CreatorTool rdf:parseType="Resource"><rdf:value>Nested Qualifier Tool</rdf:value><xmp:qualifier><rdf:Bag><rdf:li>tool nested qualifier</rdf:li></rdf:Bag></xmp:qualifier></xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T02:55:34Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpNestedQualifierPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP nested-qualifier boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Nested Qualifier Info Title) /Author (Info Fallback Author) /Producer (Info Fallback Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'extracts direct XMP RDF collection values without nested qualifier list leakage' => static function (
        TestRunner $t
    ) use ($xmpNestedQualifierPacket, $xmpNestedQualifierPdf): void {
        $currentXmp = $xmpNestedQualifierPacket(
            'Current Nested Qualifier XMP Title',
            'Nested qualifier lists stay out of WordPress metadata values',
            '2026-06-04T22:55:34-04:00'
        );
        $decoyXmp = $xmpNestedQualifierPacket(
            'Trailing Nested Qualifier Decoy Title',
            'Trailing nested qualifier packet must stay hidden',
            '2026-06-05T03:33:33Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpNestedQualifierPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Nested Qualifier Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Nested Qualifier XMP Title', $metadata['title']);
        $t->same('Nested qualifier lists stay out of WordPress metadata values', $metadata['description']);
        $t->same(['Nested Qualifier Author One', 'Nested Qualifier Author Two'], $metadata['authors']);
        $t->same(['wordpress', 'nested-qualifier-xmp'], $metadata['keywords']);
        $t->same('Nested Qualifier Tool', $metadata['creator_tool']);
        $t->same('Nested Qualifier Producer', $metadata['producer']);
        $t->same('2026-06-04T22:55:34-04:00', $metadata['created_at']);
        $t->same('2026-06-05T02:55:34Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T02:55:34Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Nested Qualifier Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Nested Qualifier Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'copy editor qualifier'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'internal keyword qualifier'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'title nested qualifier label'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Nested Qualifier Decoy Title'));
        $t->true(!str_contains($plainText, 'Current Nested Qualifier XMP Title'));
        $t->true(!str_contains($plainText, 'copy editor qualifier'));
        $t->true(!str_contains($plainText, 'internal keyword qualifier'));
    },
    'summarizes rejected XMP streams using direct RDF collection counts only' => static function (
        TestRunner $t
    ) use ($xmpNestedQualifierPacket, $xmpNestedQualifierPdf): void {
        $currentXmp = $xmpNestedQualifierPacket(
            'Rejected Nested Qualifier XMP Title',
            'Rejected nested qualifier packet is summarized only',
            '2026-06-05T02:56:34Z'
        );
        $decoyXmp = $xmpNestedQualifierPacket(
            'Rejected Nested Qualifier Decoy Title',
            'Rejected nested qualifier decoy stays hidden',
            '2026-06-05T03:33:33Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpNestedQualifierPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected Nested Qualifier Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Nested Qualifier Info Title', $metadata['title']);
        $t->same('Rejected Nested Qualifier Boundary Body', $plainText);
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
        $t->same('2026-06-05T02:56:34Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T02:55:34Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Nested Qualifier XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'copy editor qualifier'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'internal keyword qualifier'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Nested Qualifier Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Nested Qualifier XMP Title'));
        $t->true(!str_contains($plainText, 'internal keyword qualifier'));
    },
];
