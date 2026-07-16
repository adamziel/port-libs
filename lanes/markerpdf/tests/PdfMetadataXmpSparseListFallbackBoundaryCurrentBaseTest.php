<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpSparseListFallbackPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt>'
        . '<rdf:li> </rdf:li>'
        . '<rdf:Description><rdf:Alt>'
        . '<rdf:li xml:lang="fr-FR">Titre sparse ignore</rdf:li>'
        . '<rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li>'
        . '</rdf:Alt></rdf:Description>'
        . '</rdf:Alt></dc:title>'
        . '<dc:creator>'
        . '<rdf:Seq><rdf:li> </rdf:li></rdf:Seq>'
        . '<rdf:Description><rdf:Seq><rdf:li>Sparse List Author One</rdf:li><rdf:li>Sparse List Author Two</rdf:li></rdf:Seq></rdf:Description>'
        . '</dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject>'
        . '<rdf:Bag><rdf:li> </rdf:li></rdf:Bag>'
        . '<rdf:Description><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-sparse-list</rdf:li></rdf:Bag></rdf:Description>'
        . '</dc:subject>'
        . '<pdf:Producer>Sparse List Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Sparse List Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T17:15:01Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpSparseListFallbackPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP sparse-list fallback fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Sparse List Info Title) /Author (Info Sparse Author) /Producer (Info Sparse Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'falls through empty XMP RDF list placeholders to resource-wrapped metadata arrays' => static function (
        TestRunner $t
    ) use ($xmpSparseListFallbackPacket, $xmpSparseListFallbackPdf): void {
        $currentXmp = $xmpSparseListFallbackPacket(
            'Current Sparse List XMP Title',
            'Sparse RDF list placeholders should not concatenate fallback list text.',
            '2026-06-05T13:15:01-04:00'
        );
        $decoyXmp = $xmpSparseListFallbackPacket(
            'Trailing Sparse List Decoy Title',
            'Trailing sparse-list packet stays outside the current metadata boundary.',
            '2026-06-05T18:00:00Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpSparseListFallbackPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Sparse List Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Sparse List XMP Title', $metadata['title']);
        $t->same('Sparse RDF list placeholders should not concatenate fallback list text.', $metadata['description']);
        $t->same(['Sparse List Author One', 'Sparse List Author Two'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-sparse-list'], $metadata['keywords']);
        $t->same('Sparse List Boundary Tool', $metadata['creator_tool']);
        $t->same('Sparse List Boundary Producer', $metadata['producer']);
        $t->same('2026-06-05T13:15:01-04:00', $metadata['created_at']);
        $t->same('2026-06-05T17:15:01Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T17:15:01Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Sparse List Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Sparse List Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Sparse List Author OneSparse List Author Two'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'wordpressxmp-sparse-list'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Titre sparse ignore'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Sparse List Decoy Title'));
        $t->true(!str_contains($plainText, 'Current Sparse List XMP Title'));
        $t->true(!str_contains($plainText, 'Sparse List Author One'));
        $t->true(!str_contains($plainText, 'Trailing Sparse List Decoy Title'));
    },
    'summarizes rejected sparse-list XMP streams without concatenating hidden list text' => static function (
        TestRunner $t
    ) use ($xmpSparseListFallbackPacket, $xmpSparseListFallbackPdf): void {
        $currentXmp = $xmpSparseListFallbackPacket(
            'Rejected Sparse List XMP Title',
            'Rejected sparse-list XMP should be summarized only.',
            '2026-06-05T17:16:01Z'
        );
        $decoyXmp = $xmpSparseListFallbackPacket(
            'Rejected Sparse List Decoy Title',
            'Rejected trailing sparse-list packet stays hidden.',
            '2026-06-05T18:00:00Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpSparseListFallbackPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Sparse List Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Sparse List Info Title', $metadata['title']);
        $t->same('Rejected XMP Sparse List Boundary Body', $plainText);
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
        $t->same('2026-06-05T17:16:01Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T17:15:01Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Sparse List XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Sparse List Author One'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Sparse List Author OneSparse List Author Two'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Sparse List Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Sparse List XMP Title'));
        $t->true(!str_contains($plainText, 'Sparse List Author One'));
        $t->true(!str_contains($plainText, 'Rejected Sparse List Decoy Title'));
    },
];
