<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpDublinCoreProvenancePacket = static function (
    string $title,
    string $description,
    string $date,
    string $identifier = 'doi:10.5555/markerpdf.dc.provenance'
): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="#private-provenance-decoy"'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' dc:identifier="private-doi-decoy"'
        . ' dc:publisher="Private Publisher Decoy"/>'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Dublin Core Provenance Editor</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-dublin-core-provenance</rdf:li></rdf:Bag></dc:subject>'
        . '<dc:identifier><rdf:Bag><rdf:li>' . htmlspecialchars($identifier, ENT_XML1) . '</rdf:li><rdf:li>urn:uuid:markerpdf-provenance</rdf:li></rdf:Bag></dc:identifier>'
        . '<dc:publisher><rdf:Seq><rdf:li>WordPress Data Liberation Press</rdf:li></rdf:Seq></dc:publisher>'
        . '<dc:contributor><rdf:Seq><rdf:li>Metadata Reviewer</rdf:li><rdf:li>Import Curator</rdf:li></rdf:Seq></dc:contributor>'
        . '<dc:relation><rdf:Bag><rdf:li>isPartOf:Migration Packet 7</rdf:li><rdf:li>hasFormat:text/markdown</rdf:li></rdf:Bag></dc:relation>'
        . '<dc:source>source-report.pdf</dc:source>'
        . '<dc:type><rdf:Bag><rdf:li>Text</rdf:li><rdf:li>Dataset</rdf:li></rdf:Bag></dc:type>'
        . '<dc:coverage>Customer migration archive 2026</dc:coverage>'
        . '<pdf:Producer>Dublin Core Provenance Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Dublin Core Provenance Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T07:31:40Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpDublinCoreProvenancePdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP Dublin Core provenance fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Dublin Core Provenance Info Title) /Author (Info Provenance Author) /Producer (Info Provenance Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'preserves XMP Dublin Core provenance fields as review-only metadata' => static function (
        TestRunner $t
    ) use ($xmpDublinCoreProvenancePacket, $xmpDublinCoreProvenancePdf): void {
        $currentXmp = $xmpDublinCoreProvenancePacket(
            'Current Dublin Core Provenance XMP Title',
            'Dublin Core provenance fields stay metadata before WordPress import.',
            '2026-06-08T03:31:40-04:00'
        );
        $trailingXmp = $xmpDublinCoreProvenancePacket(
            'Trailing Dublin Core Provenance Decoy Title',
            'Trailing provenance packet must not replace current metadata.',
            '2026-06-08T07:59:59Z',
            'doi:10.5555/trailing.decoy'
        );
        $metadataBytes = $currentXmp . "\0\0\n" . $trailingXmp;
        $pdf = $xmpDublinCoreProvenancePdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Dublin Core Provenance Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $dublinCore = $metadata['xmp_dublin_core'] ?? [];

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Dublin Core Provenance XMP Title', $metadata['title']);
        $t->same('Dublin Core provenance fields stay metadata before WordPress import.', $metadata['description']);
        $t->same(['Dublin Core Provenance Editor'], $metadata['authors']);
        $t->same(['doi:10.5555/markerpdf.dc.provenance', 'urn:uuid:markerpdf-provenance'], $metadata['xmp']['identifiers'] ?? null);
        $t->same(['WordPress Data Liberation Press'], $metadata['xmp']['publishers'] ?? null);
        $t->same(['Metadata Reviewer', 'Import Curator'], $metadata['xmp']['contributors'] ?? null);
        $t->same(['isPartOf:Migration Packet 7', 'hasFormat:text/markdown'], $metadata['xmp']['relations'] ?? null);
        $t->same(['source-report.pdf'], $metadata['xmp']['source_documents'] ?? null);
        $t->same(['Text', 'Dataset'], $metadata['xmp']['types'] ?? null);
        $t->same(['Customer migration archive 2026'], $metadata['xmp']['coverage'] ?? null);
        $t->same('xmp_dublin_core', $dublinCore['source'] ?? null);
        $t->same(true, $dublinCore['review_only'] ?? null);
        $t->same(false, $dublinCore['payload_included'] ?? null);
        $t->same(['doi:10.5555/markerpdf.dc.provenance', 'urn:uuid:markerpdf-provenance'], $dublinCore['identifiers'] ?? null);
        $t->same(2, $dublinCore['identifier_count'] ?? null);
        $t->same(['WordPress Data Liberation Press'], $dublinCore['publishers'] ?? null);
        $t->same(1, $dublinCore['publisher_count'] ?? null);
        $t->same(['Metadata Reviewer', 'Import Curator'], $dublinCore['contributors'] ?? null);
        $t->same(2, $dublinCore['contributor_count'] ?? null);
        $t->same(['isPartOf:Migration Packet 7', 'hasFormat:text/markdown'], $dublinCore['relations'] ?? null);
        $t->same(['source-report.pdf'], $dublinCore['source_documents'] ?? null);
        $t->same(['Text', 'Dataset'], $dublinCore['types'] ?? null);
        $t->same(['Customer migration archive 2026'], $dublinCore['coverage'] ?? null);
        $t->same('Dublin Core Provenance Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Dublin Core Provenance Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'private-doi-decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private Publisher Decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Dublin Core Provenance Decoy Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'doi:10.5555/trailing.decoy'));
        $t->true(!str_contains($plainText, 'Current Dublin Core Provenance XMP Title'));
        $t->true(!str_contains($plainText, 'WordPress Data Liberation Press'));
        $t->true(!str_contains($plainText, 'Trailing Dublin Core Provenance Decoy Title'));
    },
    'summarizes rejected XMP Dublin Core provenance fields without exposing values' => static function (
        TestRunner $t
    ) use ($xmpDublinCoreProvenancePacket, $xmpDublinCoreProvenancePdf): void {
        $currentXmp = $xmpDublinCoreProvenancePacket(
            'Rejected Dublin Core Provenance XMP Title',
            'Rejected provenance fields are summarized only.',
            '2026-06-08T07:32:40Z'
        );
        $trailingXmp = $xmpDublinCoreProvenancePacket(
            'Rejected Dublin Core Provenance Decoy Title',
            'Rejected trailing provenance packet stays hidden.',
            '2026-06-08T07:59:59Z',
            'doi:10.5555/rejected.trailing.decoy'
        );
        $metadataBytes = $currentXmp . "\0\0\n" . $trailingXmp;
        $pdf = $xmpDublinCoreProvenancePdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Dublin Core Provenance Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Dublin Core Provenance Info Title', $metadata['title']);
        $t->same('Rejected XMP Dublin Core Provenance Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->true(in_array('dublin_core', $summary['field_names'] ?? [], true));
        $t->true(in_array('dublin_core', $summary['redacted_fields'] ?? [], true));
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-08T07:32:40Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-08T07:31:40Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Dublin Core Provenance XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'doi:10.5555/markerpdf.dc.provenance'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'WordPress Data Liberation Press'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'doi:10.5555/rejected.trailing.decoy'));
        $t->true(!str_contains($plainText, 'Rejected Dublin Core Provenance XMP Title'));
        $t->true(!str_contains($plainText, 'WordPress Data Liberation Press'));
    },
];
