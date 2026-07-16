<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpResourceWrappedListPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:Description><rdf:Alt>'
        . '<rdf:li xml:lang="fr-FR">Titre de ressource ignore</rdf:li>'
        . '<rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li>'
        . '</rdf:Alt></rdf:Description></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Description>'
        . '<rdf:Seq><rdf:li>Resource Wrapped Author One</rdf:li><rdf:li>Resource Wrapped Author Two</rdf:li></rdf:Seq>'
        . '<xmp:roles><rdf:Bag><rdf:li>internal author qualifier</rdf:li></rdf:Bag></xmp:roles>'
        . '</rdf:Description></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:Description><rdf:Alt><rdf:li xml:lang="x-default">'
        . htmlspecialchars($description, ENT_XML1)
        . '</rdf:li></rdf:Alt><pdf:Producer><rdf:Seq><rdf:li>description qualifier list</rdf:li></rdf:Seq></pdf:Producer></rdf:Description></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Description>'
        . '<rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-resource-wrapper</rdf:li></rdf:Bag>'
        . '<xmp:labels><rdf:Seq><rdf:li>internal keyword qualifier</rdf:li></rdf:Seq></xmp:labels>'
        . '</rdf:Description></dc:subject>'
        . '<pdf:Producer>Resource Wrapped Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Resource Wrapped Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T16:48:20Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpResourceWrappedListPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP resource-wrapped-list boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Resource Wrapped Info Title) /Author (Info Resource Author) /Producer (Info Resource Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'extracts XMP list containers nested in RDF resource wrappers without qualifier leakage' => static function (
        TestRunner $t
    ) use ($xmpResourceWrappedListPacket, $xmpResourceWrappedListPdf): void {
        $currentXmp = $xmpResourceWrappedListPacket(
            'Current Resource Wrapped XMP Title',
            'Resource-wrapped RDF list containers stay structured before WordPress import',
            '2026-06-05T12:48:20-04:00'
        );
        $decoyXmp = $xmpResourceWrappedListPacket(
            'Trailing Resource Wrapped Decoy Title',
            'Trailing resource-wrapped packet must stay outside metadata',
            '2026-06-05T17:00:00Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpResourceWrappedListPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Resource Wrapped Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Resource Wrapped XMP Title', $metadata['title']);
        $t->same('Resource-wrapped RDF list containers stay structured before WordPress import', $metadata['description']);
        $t->same(['Resource Wrapped Author One', 'Resource Wrapped Author Two'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-resource-wrapper'], $metadata['keywords']);
        $t->same('Resource Wrapped Tool', $metadata['creator_tool']);
        $t->same('Resource Wrapped Producer', $metadata['producer']);
        $t->same('2026-06-05T12:48:20-04:00', $metadata['created_at']);
        $t->same('2026-06-05T16:48:20Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T16:48:20Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Resource Wrapped Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Resource Wrapped Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'internal author qualifier'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'internal keyword qualifier'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'description qualifier list'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Resource Wrapped Decoy Title'));
        $t->true(!str_contains($plainText, 'Current Resource Wrapped XMP Title'));
        $t->true(!str_contains($plainText, 'internal author qualifier'));
        $t->true(!str_contains($plainText, 'Trailing Resource Wrapped Decoy Title'));
    },
    'summarizes rejected resource-wrapped XMP lists without exposing list text' => static function (
        TestRunner $t
    ) use ($xmpResourceWrappedListPacket, $xmpResourceWrappedListPdf): void {
        $currentXmp = $xmpResourceWrappedListPacket(
            'Rejected Resource Wrapped XMP Title',
            'Rejected resource-wrapped XMP is summarized only',
            '2026-06-05T16:49:20Z'
        );
        $decoyXmp = $xmpResourceWrappedListPacket(
            'Rejected Resource Wrapped Decoy Title',
            'Rejected trailing resource-wrapped packet stays hidden',
            '2026-06-05T17:00:00Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpResourceWrappedListPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Resource Wrapped Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Resource Wrapped Info Title', $metadata['title']);
        $t->same('Rejected XMP Resource Wrapped Boundary Body', $plainText);
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
        $t->same('2026-06-05T16:49:20Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T16:48:20Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Resource Wrapped XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Resource Wrapped Author One'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'internal keyword qualifier'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Resource Wrapped Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Resource Wrapped XMP Title'));
        $t->true(!str_contains($plainText, 'Resource Wrapped Author One'));
        $t->true(!str_contains($plainText, 'Rejected Resource Wrapped Decoy Title'));
    },
];
