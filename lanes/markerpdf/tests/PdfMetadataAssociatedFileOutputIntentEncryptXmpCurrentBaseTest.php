<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

$encryptedAssociatedFileOutputIntentXmpPdf = static function (): array {
    $rootXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Encrypted AF Root XMP Title</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Only root XMP is explicitly unencrypted</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-02T22:12:39Z</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-02T22:13:39Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
    $fileXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Encrypted Attachment XMP Hidden Title</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Attachment XMP requires decryption boundary</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:MetadataDate>2026-06-02T22:14:39Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';

    $rootXmpStream = gzcompress($rootXmp);
    $fileXmpStream = gzcompress($fileXmp);
    $rootProfile = 'Encrypted root OutputIntent profile bytes should not be parsed without decryption';
    $fileProfile = 'Encrypted attachment OutputIntent profile bytes should not be parsed without decryption';
    $rootProfileStream = gzcompress($rootProfile);
    $fileProfileStream = gzcompress($fileProfile);
    if (!is_string($rootXmpStream) || !is_string($fileXmpStream) || !is_string($rootProfileStream) || !is_string($fileProfileStream)) {
        throw new RuntimeException('Unable to compress encrypted associated-file metadata fixture streams.');
    }

    $payload = '<wp-export><post id="encrypted-associated-file"/></wp-export>';
    $content = 'BT /F1 12 Tf 72 720 Td (Encrypted associated visible text leak) Tj ET';
    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R /OutputIntents [9 0 R] /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootXmpStream) . " >>\nstream\n{$rootXmpStream}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($fileXmpStream) . " >>\nstream\n{$fileXmpStream}\nendstream\nendobj\n"
        . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($rootProfileStream) . " >>\nstream\n{$rootProfileStream}\nendstream\nendobj\n"
        . "8 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($fileProfileStream) . " >>\nstream\n{$fileProfileStream}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Encrypted Root PDF/A) /Info (Encrypted root profile) /DestOutputProfile 7 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (encrypted-source.xml) /Desc (Encrypted source payload) /AFRelationship /Source /Metadata 6 0 R /OutputIntents [13 0 R] /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . ' /CheckSum <' . strtoupper(hash('md5', $payload)) . "> >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
        . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Encrypted Attachment PDF/A) /Info (Encrypted attachment profile) /DestOutputProfile 8 0 R >>\nendobj\n"
        . "20 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -64 /EncryptMetadata false /StmF /StdCF /StrF /StdCF /EFF /StdCF /CF << /StdCF << /CFM /AESV2 /Length 16 /AuthEvent /DocOpen >> >> >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Encrypt 20 0 R >>\n%%EOF";

    return [$pdf, $rootProfile, $fileProfile, $payload];
};

return [
    'preserves unencrypted root XMP while blocking encrypted associated FileSpec metadata and OutputIntent rows' => static function (
        TestRunner $t
    ) use ($encryptedAssociatedFileOutputIntentXmpPdf): void {
        [$pdf, $rootProfile, $fileProfile, $payload] = $encryptedAssociatedFileOutputIntentXmpPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $preflight = (new PdfSecurityPreflight())->analyze($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $policy = $metadata['encryption']['metadata_source_policy'] ?? [];
        $file = $metadata['associated_files'][0] ?? [];
        $filePolicy = $file['encryption_policy'] ?? [];
        $provenance = $file['provenance_review'] ?? [];

        $t->same(['encryption', 'xmp', 'catalog'], $metadata['source']);
        $t->same('Encrypted AF Root XMP Title', $metadata['title']);
        $t->same('Only root XMP is explicitly unencrypted', $metadata['description']);
        $t->same('2026-06-02T22:12:39Z', $metadata['created_at']);
        $t->same('2026-06-02T22:13:39Z', $metadata['metadata_date']);
        $t->same(false, $metadata['encryption']['encrypt_metadata']);
        $t->same('preserved_unencrypted_by_encrypt_metadata_false', $policy['xmp_stream_policy'] ?? null);
        $t->same('suppressed_encrypted_stream_or_strings', $policy['output_intents_policy'] ?? null);
        $t->same('suppressed_encrypted_associated_file_metadata', $policy['associated_files_policy'] ?? null);
        $t->same(['output_intents', 'associated_files'], $policy['suppressed_sources'] ?? []);
        $t->same(['xmp'], $policy['preserved_sources'] ?? []);
        $t->same([], $metadata['output_intents']);
        $t->true(!isset($metadata['pdfa']));

        $t->same('catalog_associated_files', $file['source'] ?? null);
        $t->same(true, $file['associated_file'] ?? null);
        $t->same(0, $file['associated_file_index'] ?? null);
        $t->same(10, $file['file_spec_object'] ?? null);
        $t->same(11, $file['embedded_file_object'] ?? null);
        $t->same('Source', $file['relationship'] ?? null);
        $t->same('encrypted_associated_file_review', $filePolicy['source'] ?? null);
        $t->same('StdCF', $filePolicy['string_filter'] ?? null);
        $t->same('StdCF', $filePolicy['embedded_file_filter'] ?? null);
        $t->same('suppressed_encrypted_strings', $filePolicy['file_spec_strings_policy'] ?? null);
        $t->same('suppressed_encrypted_embedded_file_streams', $filePolicy['embedded_file_stream_policy'] ?? null);
        $t->same('suppressed_encrypted_associated_metadata_streams', $filePolicy['metadata_stream_policy'] ?? null);
        $t->same('suppressed_encrypted_associated_output_intents', $filePolicy['output_intents_policy'] ?? null);
        $t->same(false, $filePolicy['payload_hash_available'] ?? null);
        $t->same(false, $filePolicy['xmp_summary_available'] ?? null);
        $t->same(false, $filePolicy['attachment_output_intents_available'] ?? null);
        $t->same(false, array_key_exists('filename', $file));
        $t->same(false, array_key_exists('description', $file));
        $t->same(false, array_key_exists('content_sha256', $file));
        $t->same(false, array_key_exists('metadata_review', $file));
        $t->same(false, array_key_exists('output_intents_review', $file));

        $t->same('associated_file_provenance', $provenance['source'] ?? null);
        $t->same(true, $provenance['review_only'] ?? null);
        $t->same(false, $provenance['payload_included'] ?? null);
        $t->same('Source', $provenance['relationship'] ?? null);
        $t->same('original_source', $provenance['relationship_role'] ?? null);
        $t->same(['filespec_afrelationship', 'encrypted_file_spec_boundary'], $provenance['sources'] ?? []);
        $t->same(false, array_key_exists('payload', $provenance));
        $t->same(false, array_key_exists('xmp_metadata', $provenance));
        $t->same(false, array_key_exists('pdfa_output_intents', $provenance));

        $t->same('', $plainText);
        $t->same('block_encrypted_content_review_security_metadata', $preflight['import_decision']);
        $t->true(is_string($encoded) && !str_contains($encoded, $payload));
        $t->true(is_string($encoded) && !str_contains($encoded, 'encrypted-source.xml'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Encrypted Attachment XMP Hidden Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Encrypted Attachment PDF/A'));
        $t->true(is_string($encoded) && !str_contains($encoded, $rootProfile));
        $t->true(is_string($encoded) && !str_contains($encoded, $fileProfile));
        $t->true(is_string($encoded) && !str_contains($encoded, 'DEADBEEF'));
        $t->true(!str_contains($plainText, 'Encrypted associated visible text leak'));
    },
];
