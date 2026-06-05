<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpSplitDescriptionPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li> </rdf:li></rdf:Seq></dc:creator>'
        . '<dc:subject><rdf:Bag><rdf:li> </rdf:li></rdf:Bag></dc:subject>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:creator><rdf:Seq><rdf:li>Split Description Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-split-description</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Split Description Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Split Description Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T12:20:09Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpSplitDescriptionPdf = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP split-description boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Split Description Info Title) /Author (Info Split Author) /Producer (Info Split Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'continues past empty XMP list fields in earlier top-level descriptions' => static function (
        TestRunner $t
    ) use ($xmpSplitDescriptionPacket, $xmpSplitDescriptionPdf): void {
        $currentXmp = $xmpSplitDescriptionPacket(
            'Current Split Description XMP Title',
            'XMP list fields can be split across top-level descriptions.',
            '2026-06-05T08:20:09-04:00'
        );
        $decoyXmp = $xmpSplitDescriptionPacket(
            'Trailing Split Description Decoy Title',
            'Trailing split-description packet must stay outside metadata.',
            '2026-06-05T12:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
        $pdf = $xmpSplitDescriptionPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Split Description Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Split Description XMP Title', $metadata['title']);
        $t->same('XMP list fields can be split across top-level descriptions.', $metadata['description']);
        $t->same(['Split Description Editor', 'Import Review Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-split-description'], $metadata['keywords']);
        $t->same('Split Description Boundary Tool', $metadata['creator_tool']);
        $t->same('Split Description Boundary Producer', $metadata['producer']);
        $t->same('2026-06-05T08:20:09-04:00', $metadata['created_at']);
        $t->same('2026-06-05T12:20:09Z', $metadata['created_at_utc']);
        $t->same('2026-06-05T12:20:09Z', $metadata['metadata_date_utc']);
        $t->same('UTF-8', $metadata['xmp']['packet_encoding'] ?? null);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Split Description Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Split Description Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Split Description Decoy Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing split-description packet'));
        $t->true(!str_contains($plainText, 'Current Split Description XMP Title'));
        $t->true(!str_contains($plainText, 'Trailing Split Description Decoy Title'));
    },
    'summarizes rejected XML streams from later non-empty XMP list descriptions' => static function (
        TestRunner $t
    ) use ($xmpSplitDescriptionPacket, $xmpSplitDescriptionPdf): void {
        $currentXmp = $xmpSplitDescriptionPacket(
            'Rejected Split Description XMP Title',
            'Rejected split-description packet is summarized only.',
            '2026-06-05T12:21:09Z'
        );
        $decoyXmp = $xmpSplitDescriptionPacket(
            'Rejected Split Description Decoy Title',
            'Rejected trailing split-description packet stays hidden.',
            '2026-06-05T12:59:59Z'
        );
        $metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
        $pdf = $xmpSplitDescriptionPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Split Description Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Split Description Info Title', $metadata['title']);
        $t->same('Rejected XMP Split Description Boundary Body', $plainText);
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
        $t->same('2026-06-05T12:21:09Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-05T12:20:09Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Split Description XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Split Description Decoy Title'));
        $t->true(!str_contains($plainText, 'Rejected Split Description XMP Title'));
        $t->true(!str_contains($plainText, 'Rejected Split Description Decoy Title'));
    },
];
