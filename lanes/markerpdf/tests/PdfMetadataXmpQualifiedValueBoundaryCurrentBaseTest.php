<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpQualifiedValuePacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt>'
        . '<rdf:li xml:lang="fr-FR" rdf:parseType="Resource"><rdf:value>Titre qualifie ignore</rdf:value><xmp:Label>ignored title qualifier</xmp:Label></rdf:li>'
        . '<rdf:li xml:lang="x-default" rdf:parseType="Resource"><rdf:value>' . htmlspecialchars($title, ENT_XML1) . '</rdf:value><xmp:Label>title qualifier noise</xmp:Label></rdf:li>'
        . '</rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq>'
        . '<rdf:li rdf:parseType="Resource"><rdf:value>Qualified Author One</rdf:value><xmp:role>author qualifier noise</xmp:role></rdf:li>'
        . '<rdf:li rdf:parseType="Resource"><rdf:value>Qualified Author Two</rdf:value><xmp:role>reviewer qualifier noise</xmp:role></rdf:li>'
        . '</rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default" rdf:parseType="Resource"><rdf:value>' . htmlspecialchars($description, ENT_XML1) . '</rdf:value><pdf:Producer>description qualifier noise</pdf:Producer></rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag>'
        . '<rdf:li rdf:parseType="Resource"><rdf:value>wordpress</rdf:value><xmp:tag>keyword qualifier noise</xmp:tag></rdf:li>'
        . '<rdf:li rdf:parseType="Resource"><rdf:value>qualified-xmp</rdf:value><xmp:tag>second keyword qualifier noise</xmp:tag></rdf:li>'
        . '</rdf:Bag></dc:subject>'
        . '<pdf:Producer rdf:parseType="Resource"><rdf:value>Qualified Value Producer</rdf:value><xmp:qualifier>producer qualifier noise</xmp:qualifier></pdf:Producer>'
        . '<xmp:CreatorTool rdf:parseType="Resource"><rdf:value>Qualified Value Tool</rdf:value><xmp:qualifier>tool qualifier noise</xmp:qualifier></xmp:CreatorTool>'
        . '<xmp:CreateDate rdf:parseType="Resource"><rdf:value>' . htmlspecialchars($date, ENT_XML1) . '</rdf:value><xmp:precision>date qualifier noise</xmp:precision></xmp:CreateDate>'
        . '<xmp:MetadataDate rdf:parseType="Resource"><rdf:value>2026-06-05T01:19:45Z</rdf:value><xmp:precision>metadata date qualifier noise</xmp:precision></xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpQualifiedValuePdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP qualified-value boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Qualified Value Info Title) /Author (Info Qualified Author) /Producer (Info Qualified Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'extracts qualified XMP rdf:value text without qualifier leakage' => static function (
        TestRunner $t
    ) use ($xmpQualifiedValuePacket, $xmpQualifiedValuePdf): void {
        $currentXmp = $xmpQualifiedValuePacket(
            'Current Qualified XMP Title',
            'Qualified property values remain metadata without qualifier text',
            '2026-06-04T21:18:45-04:00'
        );
        $decoyXmp = $xmpQualifiedValuePacket(
            'Trailing Qualified Decoy Title',
            'Trailing qualified packet must not replace current metadata',
            '2026-06-05T01:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpQualifiedValuePdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Qualified Value Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Qualified XMP Title', $metadata['title']);
        $t->same('Qualified property values remain metadata without qualifier text', $metadata['description']);
        $t->same(['Qualified Author One', 'Qualified Author Two'], $metadata['authors']);
        $t->same(['wordpress', 'qualified-xmp'], $metadata['keywords']);
        $t->same('Qualified Value Tool', $metadata['creator_tool']);
        $t->same('Qualified Value Producer', $metadata['producer']);
        $t->same('2026-06-04T21:18:45-04:00', $metadata['created_at']);
        $t->same('2026-06-05T01:18:45Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T01:19:45Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Qualified Value Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Qualified Value Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'title qualifier noise'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'author qualifier noise'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'description qualifier noise'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'date qualifier noise'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Qualified Decoy Title'));
        $t->true(!str_contains($plainText, 'Current Qualified XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Qualified Decoy Title'));
        $t->true(!str_contains($plainText, 'title qualifier noise'));
    },
    'summarizes rejected qualified XMP streams without exposing rdf:value text' => static function (
        TestRunner $t
    ) use ($xmpQualifiedValuePacket, $xmpQualifiedValuePdf): void {
        $currentXmp = $xmpQualifiedValuePacket(
            'Rejected Qualified XMP Title',
            'Rejected qualified value packet is summarized only',
            '2026-06-05T01:20:45Z'
        );
        $decoyXmp = $xmpQualifiedValuePacket(
            'Rejected Trailing Qualified Decoy Title',
            'Rejected trailing qualified packet stays hidden',
            '2026-06-05T01:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpQualifiedValuePdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected Qualified XMP Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Qualified Value Info Title', $metadata['title']);
        $t->same('Rejected Qualified XMP Boundary Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same(['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords'], $summary['field_names'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-05T01:20:45Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T01:19:45Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Qualified XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Trailing Qualified Decoy Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'title qualifier noise'));
        $t->true(!str_contains($plainText, 'Rejected Qualified XMP Title'));
        $t->true(!str_contains($plainText, 'title qualifier noise'));
    },
];
