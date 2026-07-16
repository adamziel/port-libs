<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpGenerationBoundaryPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xmpGenerationBoundaryPdf = static function (callable $packet): array {
    $rootXmp = $packet(
        'Current Generation Root XMP Title',
        'Root XMP remains the document metadata source',
        '2026-06-05T00:08:56Z'
    );
    $staleAttachmentXmp = $packet(
        'Stale Generation Attachment XMP Title',
        'Stale same-object generation must not be summarized',
        '2026-06-05T00:09:56Z'
    );
    $currentAttachmentXmp = $packet(
        'Current Generation Attachment XMP Title',
        'Only exact FileSpec metadata generation is summarized',
        '2026-06-05T00:10:56Z'
    );

    $rootXmpStream = gzcompress($rootXmp);
    $staleAttachmentXmpStream = gzcompress($staleAttachmentXmp);
    $currentAttachmentXmpStream = gzcompress($currentAttachmentXmp);
    if (
        !is_string($rootXmpStream)
        || !is_string($staleAttachmentXmpStream)
        || !is_string($currentAttachmentXmpStream)
    ) {
        throw new RuntimeException('Unable to compress XMP generation boundary fixture streams.');
    }

    $mismatchedPayload = '<wp-export><post id="mismatched-generation"/></wp-export>';
    $exactPayload = '<wp-export><post id="exact-generation"/></wp-export>';
    $content = 'BT /F1 12 Tf 72 720 Td (Current XMP Generation Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n";
    $offsets = [];
    $generations = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets, &$generations): void {
        $offsets[$objectNumber] = strlen($pdf);
        $generations[$objectNumber] = $generation;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R /AF [10 0 R 12 0 R] >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(5, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($rootXmpStream) . " >>\nstream\n{$rootXmpStream}\nendstream");
    $addObject(6, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleAttachmentXmpStream) . " >>\nstream\n{$staleAttachmentXmpStream}\nendstream");
    $addObject(6, 1, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentAttachmentXmpStream) . " >>\nstream\n{$currentAttachmentXmpStream}\nendstream");
    $addObject(10, 0, '<< /Type /Filespec /F (mismatched-generation.xml) /Desc (Mismatched generation attachment) /AFRelationship /Source /Metadata 6 0 R /EF << /F 11 0 R >> >>');
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($mismatchedPayload) . " >>\nstream\n{$mismatchedPayload}\nendstream");
    $addObject(12, 0, '<< /Type /Filespec /F (exact-generation.xml) /Desc (Exact generation attachment) /AFRelationship /Schema /Metadata 6 1 R /EF << /F 13 0 R >> >>');
    $addObject(13, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($exactPayload) . " >>\nstream\n{$exactPayload}\nendstream");

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber < 91; $objectNumber++) {
        if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 90)) {
            $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
            continue;
        }

        $rows .= pack('CNn', 1, $objectNumber === 90 ? $xrefOffset : $offsets[$objectNumber], $generations[$objectNumber] ?? 0);
    }

    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress XMP generation boundary xref stream.');
    }

    $pdf .= "90 0 obj\n"
        . '<< /Type /XRef /Size 91 /Root 1 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $rootXmp, $staleAttachmentXmp, $currentAttachmentXmp, $mismatchedPayload, $exactPayload];
};

return [
    'keeps FileSpec XMP metadata provenance generation exact before WordPress import review' => static function (
        TestRunner $t
    ) use ($xmpGenerationBoundaryPdf, $xmpGenerationBoundaryPacket): void {
        [$pdf, $rootXmp, $staleAttachmentXmp, $currentAttachmentXmp, $mismatchedPayload, $exactPayload] =
            $xmpGenerationBoundaryPdf($xmpGenerationBoundaryPacket);

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $files = $metadata['associated_files'] ?? [];
        $mismatched = $files[0] ?? [];
        $exact = $files[1] ?? [];
        $mismatchedProvenance = is_array($mismatched['provenance_review'] ?? null) ? $mismatched['provenance_review'] : [];
        $exactProvenance = is_array($exact['provenance_review'] ?? null) ? $exact['provenance_review'] : [];
        $exactXmp = is_array($exactProvenance['xmp_metadata'] ?? null) ? $exactProvenance['xmp_metadata'] : [];

        $t->same(['xmp', 'catalog'], $metadata['source']);
        $t->same('Current Generation Root XMP Title', $metadata['title']);
        $t->same('Root XMP remains the document metadata source', $metadata['description']);
        $t->same('Current XMP Generation Boundary Body', $plainText);
        $t->same(2, count($files));

        $t->same('mismatched-generation.xml', $mismatched['filename'] ?? null);
        $t->same(['filespec_afrelationship', 'embedded_file_payload_hash'], $mismatchedProvenance['sources'] ?? []);
        $t->same(false, array_key_exists('xmp_metadata', $mismatchedProvenance));
        $t->same(false, in_array('filespec_metadata_stream', $mismatchedProvenance['sources'] ?? [], true));

        $t->same('exact-generation.xml', $exact['filename'] ?? null);
        $t->same(['filespec_afrelationship', 'embedded_file_payload_hash', 'filespec_metadata_stream'], $exactProvenance['sources'] ?? []);
        $t->same(6, $exactXmp['object_number'] ?? null);
        $t->same(1, $exactXmp['object_generation'] ?? null);
        $t->same(strlen($currentAttachmentXmp), $exactXmp['bytes'] ?? null);
        $t->same(hash('sha256', $currentAttachmentXmp), $exactXmp['sha256'] ?? null);
        $t->same(['title', 'description', 'created_at'], $exactXmp['xmp_summary']['field_names'] ?? null);
        $t->same(false, $exactXmp['payload_included'] ?? null);

        $t->same(true, is_string($encoded));
        $t->true(!str_contains((string) $encoded, 'Stale Generation Attachment XMP Title'));
        $t->true(!str_contains((string) $encoded, 'Current Generation Attachment XMP Title'));
        $t->true(!str_contains((string) $encoded, $staleAttachmentXmp));
        $t->true(!str_contains((string) $encoded, $mismatchedPayload));
        $t->true(!str_contains((string) $encoded, $exactPayload));
        $t->true(!str_contains($plainText, 'Current Generation Root XMP Title'));
        $t->true(!str_contains($plainText, 'Current Generation Attachment XMP Title'));
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
