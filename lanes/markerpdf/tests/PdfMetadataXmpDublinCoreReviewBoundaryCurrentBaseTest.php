<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpDublinCoreReviewPacket = static function (
    string $title,
    string $description,
    string $date,
    array $languages = ['en-US', 'fr-CA'],
    string $rights = 'Copyright 2026 Data Liberation Review'
): string {
    $languageItems = '';
    foreach ($languages as $language) {
        $languageItems .= '<rdf:li>' . htmlspecialchars($language, ENT_XML1) . '</rdf:li>';
    }

    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="#private-dublin-core-decoy"'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' dc:language="zz-ZZ"'
        . ' dc:rights="Private rights decoy"/>'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Dublin Core Review Editor</rdf:li><rdf:li>Import Metadata Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-dublin-core-review</rdf:li></rdf:Bag></dc:subject>'
        . '<dc:format>application/pdf</dc:format>'
        . '<dc:language><rdf:Bag>' . $languageItems . '</rdf:Bag></dc:language>'
        . '<dc:rights><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($rights, ENT_XML1) . '</rdf:li></rdf:Alt></dc:rights>'
        . '<pdf:Producer>Dublin Core Review Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Dublin Core Review Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-06T07:19:12Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpDublinCoreReviewPdf = static function (
    string $metadataBytes,
    string $metadataDictionary,
    string $bodyText
): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP Dublin Core review fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Dublin Core Review Info Title) /Author (Info Dublin Core Author) /Producer (Info Dublin Core Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'extracts XMP Dublin Core format language and rights review metadata' => static function (
        TestRunner $t
    ) use ($xmpDublinCoreReviewPacket, $xmpDublinCoreReviewPdf): void {
        $currentXmp = $xmpDublinCoreReviewPacket(
            'Current Dublin Core Review XMP Title',
            'Dublin Core document properties stay metadata before WordPress import.',
            '2026-06-06T03:19:12-04:00'
        );
        $trailingXmp = $xmpDublinCoreReviewPacket(
            'Trailing Dublin Core Review Decoy Title',
            'Trailing Dublin Core packet must not replace current metadata.',
            '2026-06-06T07:59:59Z',
            ['es-MX'],
            'Trailing rights decoy'
        );
        $metadataBytes = $currentXmp . "\0\0\n" . $trailingXmp;
        $pdf = $xmpDublinCoreReviewPdf(
            $metadataBytes,
            '/Type /Metadata /Subtype /XML',
            'XMP Dublin Core Review Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $dublinCore = $metadata['xmp']['dublin_core'] ?? [];

        $t->same(['xmp', 'info'], $metadata['source']);
        $t->same('Current Dublin Core Review XMP Title', $metadata['title']);
        $t->same('Dublin Core document properties stay metadata before WordPress import.', $metadata['description']);
        $t->same(['Dublin Core Review Editor', 'Import Metadata Team'], $metadata['authors']);
        $t->same(['wordpress', 'xmp-dublin-core-review'], $metadata['keywords']);
        $t->same('application/pdf', $metadata['format']);
        $t->same('en-US', $metadata['language']);
        $t->same(['en-US', 'fr-CA'], $metadata['languages']);
        $t->same('Copyright 2026 Data Liberation Review', $metadata['rights']);
        $t->same('application/pdf', $metadata['xmp']['format'] ?? null);
        $t->same(['en-US', 'fr-CA'], $metadata['xmp']['languages'] ?? null);
        $t->same('en-US', $metadata['xmp']['language'] ?? null);
        $t->same('Copyright 2026 Data Liberation Review', $metadata['xmp']['rights'] ?? null);
        $t->same('xmp_dublin_core', $dublinCore['source'] ?? null);
        $t->same(true, $dublinCore['review_only'] ?? null);
        $t->same(false, $dublinCore['payload_included'] ?? null);
        $t->same('application/pdf', $dublinCore['format'] ?? null);
        $t->same(['en-US', 'fr-CA'], $dublinCore['languages'] ?? null);
        $t->same(2, $dublinCore['language_count'] ?? null);
        $t->same('Copyright 2026 Data Liberation Review', $dublinCore['rights'] ?? null);
        $t->same($dublinCore, $metadata['xmp_dublin_core'] ?? null);
        $t->same('Dublin Core Review Tool', $metadata['creator_tool']);
        $t->same('Dublin Core Review Producer', $metadata['producer']);
        $t->same('2026-06-06T03:19:12-04:00', $metadata['created_at']);
        $t->same('2026-06-06T07:19:12Z', $metadata['created_at_utc']);
        $t->same('2026-06-06T07:19:12Z', $metadata['metadata_date_utc']);
        $t->same(true, $metadata['xmp']['packet_boundary_applied'] ?? null);
        $t->same('Dublin Core Review Info Title', $metadata['info']['Title'] ?? null);
        $t->same('XMP Dublin Core Review Boundary Body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private rights decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'zz-ZZ'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing Dublin Core Review Decoy Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Trailing rights decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'es-MX'));
        $t->true(!str_contains($plainText, 'Current Dublin Core Review XMP Title'));
        $t->true(!str_contains($plainText, 'Copyright 2026 Data Liberation Review'));
        $t->true(!str_contains($plainText, 'Trailing Dublin Core Review Decoy Title'));
    },
    'summarizes rejected XMP Dublin Core document properties without exposing values' => static function (
        TestRunner $t
    ) use ($xmpDublinCoreReviewPacket, $xmpDublinCoreReviewPdf): void {
        $currentXmp = $xmpDublinCoreReviewPacket(
            'Rejected Dublin Core Review XMP Title',
            'Rejected Dublin Core properties are summarized only.',
            '2026-06-06T07:20:12Z'
        );
        $trailingXmp = $xmpDublinCoreReviewPacket(
            'Rejected Dublin Core Review Decoy Title',
            'Rejected trailing Dublin Core packet stays hidden.',
            '2026-06-06T07:59:59Z',
            ['es-MX'],
            'Rejected trailing rights decoy'
        );
        $metadataBytes = $currentXmp . "\0\0\n" . $trailingXmp;
        $pdf = $xmpDublinCoreReviewPdf(
            $metadataBytes,
            '/Type /EmbeddedFile /Subtype /text#2Fxml',
            'Rejected XMP Dublin Core Review Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Dublin Core Review Info Title', $metadata['title']);
        $t->same('Rejected XMP Dublin Core Review Body', $plainText);
        $t->same('rejected_non_metadata_xml_stream', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same('EmbeddedFile', $review['type'] ?? null);
        $t->same('text/xml', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataBytes), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataBytes), $review['sha256'] ?? null);
        $t->same([
            'title',
            'description',
            'creator_tool',
            'producer',
            'created_at',
            'metadata_date',
            'format',
            'rights',
            'authors',
            'keywords',
            'languages',
            'dublin_core',
        ], $summary['field_names'] ?? null);
        $t->same(12, $summary['field_count'] ?? null);
        $t->same(2, $summary['author_count'] ?? null);
        $t->same(2, $summary['keyword_count'] ?? null);
        $t->same(2, $summary['language_count'] ?? null);
        $t->same(true, $summary['packet_boundary_applied'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->same('2026-06-06T07:20:12Z', $summary['dates_utc']['created_at'] ?? null);
        $t->same('2026-06-06T07:19:12Z', $summary['dates_utc']['metadata_date'] ?? null);
        $t->true(in_array('format', $summary['redacted_fields'] ?? [], true));
        $t->true(in_array('rights', $summary['redacted_fields'] ?? [], true));
        $t->true(in_array('languages', $summary['redacted_fields'] ?? [], true));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Dublin Core Review XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Copyright 2026 Data Liberation Review'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Private rights decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected trailing rights decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'es-MX'));
        $t->true(!str_contains($plainText, 'Rejected Dublin Core Review XMP Title'));
        $t->true(!str_contains($plainText, 'Copyright 2026 Data Liberation Review'));
    },
];
