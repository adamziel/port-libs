<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmpPacket = static function (string $title): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<xmp:CreateDate>2026-06-02T19:40:21Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$fileXmp = $xmpPacket('Portfolio FileSpec XMP Hidden Title');
$pieceInfoXmp = $xmpPacket('Portfolio PieceInfo XMP Hidden Title');
$profileBytes = 'Portfolio attachment ICC bytes should be hashed only';
$privatePayload = 'BT /F1 12 Tf 72 720 Td (Portfolio PieceInfo Private Payload Leak) Tj ET';
$sourcePayload = '<wp-export><post id="portfolio-pieceinfo-outputintent"/></wp-export>';

$fileXmpStream = gzcompress($fileXmp);
$pieceInfoXmpStream = gzcompress($pieceInfoXmp);
$profileStream = gzcompress($profileBytes);
$privateStream = gzcompress($privatePayload);
if (
    !is_string($fileXmpStream)
    || !is_string($pieceInfoXmpStream)
    || !is_string($profileStream)
    || !is_string($privateStream)
) {
    throw new RuntimeException('Unable to compress Portfolio metadata smoke streams.');
}

$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$content = 'BT /F1 12 Tf 72 720 Td (Current Portfolio Metadata Body) Tj ET';
$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Collection /View /T /D (source.xml) /Schema << /Subject << /Subtype /S /N (Subject) /O 1 >> /Priority << /Subtype /N /N (Priority) /O 2 >> /Bytes << /Subtype /Size /N (Bytes) /O 3 >> >> /Sort << /S [/Priority /Subject] /A [true false] >> >>\nendobj\n"
    . "6 0 obj\n<< /Names [(source.xml) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (legacy-source.xml) /UF (source.xml) /Desc (Original WordPress portfolio export) /AFRelationship /Source /Metadata 30 0 R /OutputIntents [40 0 R << /Type /OutputIntent /S /GTS_PDFX /OutputConditionIdentifier (Portfolio Proof) >>] /CI 20 0 R /PieceInfo 31 0 R /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602194021Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Subject (Migration Source) /Priority << /Type /CollectionSubitem /D 2 /P (P) >> /Bytes " . strlen($sourcePayload) . " >>\nendobj\n"
    . "30 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($fileXmpStream) . " >>\nstream\n{$fileXmpStream}\nendstream\nendobj\n"
    . "31 0 obj\n<< /WPImport << /LastModified (D:20260602194100Z) /Private << /ManifestId (portfolio-meta-1940) /Metadata 32 0 R /OutputIntents [40 0 R] /PrivateStream 33 0 R >> >> >>\nendobj\n"
    . "32 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($pieceInfoXmpStream) . " >>\nstream\n{$pieceInfoXmpStream}\nendstream\nendobj\n"
    . "33 0 obj\n<< /Type /Metadata /Subtype /application#2Fjson /Filter /FlateDecode /Length " . strlen($privateStream) . " >>\nstream\n{$privateStream}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Portfolio Attachment sRGB) /Info (Attachment-local PDF/A profile) /DestOutputProfile 41 0 R >>\nendobj\n"
    . "41 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($profileStream) . " >>\nstream\n{$profileStream}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$documentMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$file = $files[0] ?? [];
$provenance = $file['provenance_review'] ?? [];
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);
$encodedDocumentMetadata = json_encode($documentMetadata, JSON_UNESCAPED_SLASHES);

if (($provenance['source'] ?? null) !== 'portfolio_filespec_provenance') {
    throw new RuntimeException('Expected Portfolio FileSpec provenance review metadata.');
}
if (($provenance['pdfa_output_intents']['output_condition_identifiers'] ?? []) !== ['Portfolio Attachment sRGB']) {
    throw new RuntimeException('Expected FileSpec OutputIntent profile provenance.');
}
if (($provenance['piece_info']['entries'][0]['metadata_streams'][0]['sha256'] ?? null) !== hash('sha256', $pieceInfoXmp)) {
    throw new RuntimeException('Expected PieceInfo private XMP stream hash provenance.');
}
if (($documentMetadata['output_intents'] ?? []) !== [] || isset($documentMetadata['pdfa'])) {
    throw new RuntimeException('Attachment-local OutputIntent must not become document PDF/A metadata.');
}
if (
    !is_string($encodedFiles)
    || !is_string($encodedDocumentMetadata)
    || str_contains($encodedFiles, 'Portfolio FileSpec XMP Hidden Title')
    || str_contains($encodedFiles, 'Portfolio PieceInfo XMP Hidden Title')
    || str_contains($encodedFiles, $profileBytes)
    || str_contains($encodedFiles, $privatePayload)
    || str_contains($encodedDocumentMetadata, 'Portfolio FileSpec XMP Hidden Title')
) {
    throw new RuntimeException('Expected XMP, ICC, and PieceInfo private payload bytes to stay out of review output.');
}
if ($plainText !== 'Current Portfolio Metadata Body' || str_contains($plainText, '<wp-export>')) {
    throw new RuntimeException('Expected current visible page text only.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-metadata-portfolio-pieceinfo-outputintent-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-portfolio-filespec-provenance-review',
    'native_boundary' => 'Portfolio FileSpec /Metadata, /PieceInfo private streams, and attachment-local /OutputIntents are derived review metadata before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'filename' => $file['filename'] ?? null,
    'relationship_role' => $provenance['relationship_role'] ?? null,
    'portfolio_fields' => $provenance['portfolio_fields']['field_names'] ?? [],
    'pieceinfo_applications' => $provenance['piece_info']['applications'] ?? [],
    'pieceinfo_xmp_hash' => $provenance['piece_info']['entries'][0]['metadata_streams'][0]['sha256'] ?? null,
    'outputintent_identifiers' => $provenance['pdfa_output_intents']['output_condition_identifiers'] ?? [],
    'profile_sha256' => $provenance['pdfa_output_intents']['profile_sha256'] ?? [],
    'metadata_payloads_included' => $provenance['metadata_payloads_included'] ?? null,
    'document_pdfa_promoted' => isset($documentMetadata['pdfa']),
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:portfolio-filespec-provenance ' . $htmlJson([
    'payload' => $provenance['payload'] ?? [],
    'portfolio' => $provenance['portfolio'] ?? [],
    'piece_info' => $provenance['piece_info'] ?? [],
    'pdfa_output_intents' => $provenance['pdfa_output_intents'] ?? [],
]) . " -->\n";
