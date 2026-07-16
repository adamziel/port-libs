<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Associated Security Bundle Title</rdf:li></rdf:Alt></dc:title>'
    . '<xmp:CreateDate>2026-06-02T22:08:00Z</xmp:CreateDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$xmpStream = gzcompress($xmp);
$profileBytes = 'Associated security bundle PDF/A profile bytes';
$profileStream = gzcompress($profileBytes);
if (!is_string($xmpStream) || !is_string($profileStream)) {
    throw new RuntimeException('Unable to compress associated security smoke streams.');
}

$catalogPayload = '<wp-export><post id="catalog-security-bundle"/></wp-export>';
$targetPayload = '<wp-page-target id="outline-security-associated"/>';
$catalogChecksum = strtoupper(hash('md5', $catalogPayload));
$targetChecksum = strtoupper(hash('md5', $targetPayload));
$introContent = 'BT /F1 12 Tf 72 720 Td (Associated security intro body) Tj ET';
$targetContent = 'BT /F1 12 Tf 72 720 Td (Associated security target body) Tj ET';
$signaturePayload = 'WORDPRESS_ASSOCIATED_SECURITY_SIGNATURE_BYTES_SHOULD_NOT_LEAK';
$signatureContentsToken = '<' . strtoupper(bin2hex($signaturePayload)) . '>';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R /OutputIntents [9 0 R] /AF [10 0 R] /Outlines 40 0 R /Names << /Dests 50 0 R >> /OpenAction 60 0 R /AcroForm 80 0 R /Perms << /DocMDP 90 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 32 0 R /AF [30 0 R] /Dur 5 /Trans 16 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($xmpStream) . " >>\nstream\n{$xmpStream}\nendstream\nendobj\n"
    . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($profileStream) . " >>\nstream\n{$profileStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Associated Security PDF/A) /Info (Security bundle output intent) /DestOutputProfile 7 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (catalog-security-source.xml) /Desc (Catalog associated security source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($catalogPayload) . " /CheckSum <{$catalogChecksum}> >> /Length " . strlen($catalogPayload) . " >>\nstream\n{$catalogPayload}\nendstream\nendobj\n"
    . "16 0 obj\n<< /S /Dissolve /D .5 >>\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (outline-security-target.xml) /Desc (Outline security target source) /AFRelationship /Supplement /EF << /F 33 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream\nendobj\n"
    . "33 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($targetPayload) . " /CheckSum <{$targetChecksum}> >> /Length " . strlen($targetPayload) . " >>\nstream\n{$targetPayload}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Outlines /First 41 0 R /Count 1 >>\nendobj\n"
    . "41 0 obj\n<< /Title (Associated Security Outline) /Parent 40 0 R /A 61 0 R >>\nendobj\n"
    . "50 0 obj\n<< /Names [(BundleTarget) [4 0 R /FitH 710]] >>\nendobj\n"
    . "60 0 obj\n<< /S /GoTo /D /BundleTarget /Next [62 0 R] >>\nendobj\n"
    . "61 0 obj\n<< /S /GoTo /D /BundleTarget /Next [63 0 R 64 0 R] >>\nendobj\n"
    . "62 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden open associated review'\\)) >>\nendobj\n"
    . "63 0 obj\n<< /S /URI /URI (https://example.com/associated-outline-review) >>\nendobj\n"
    . "64 0 obj\n<< /S /Launch /F (associated-outline-helper.exe) /Win << /F (associated-outline-helper.exe) /O (open) >> >>\nendobj\n"
    . "80 0 obj\n<< /Fields [81 0 R] /SigFlags 3 >>\nendobj\n"
    . "81 0 obj\n<< /FT /Sig /T (approval.associatedSecurity) /V 90 0 R >>\nendobj\n"
    . "90 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Associated Security Reviewer) /M (D:20260602220800Z) /ByteRange [0 AAAAAAAAAA BBBBBBBBBB CCCCCCCCCC] /Contents {$signatureContentsToken} /Reference [<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$gapStart = strpos($pdf, $signatureContentsToken);
if ($gapStart === false) {
    throw new RuntimeException('Unable to locate signature contents token in associated security smoke.');
}

$gapEnd = $gapStart + strlen($signatureContentsToken);
$pdf = strtr($pdf, [
    'AAAAAAAAAA' => sprintf('%010d', $gapStart),
    'BBBBBBBBBB' => sprintf('%010d', $gapEnd),
    'CCCCCCCCCC' => sprintf('%010d', strlen($pdf) - $gapEnd),
]);

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$actionReview = is_array($preflight['document_action_security_review'] ?? null)
    ? $preflight['document_action_security_review']
    : [];
$encoded = json_encode([$metadata, $preflight], JSON_UNESCAPED_SLASHES);

if (($metadata['pdfa_associated_files']['filenames'] ?? []) !== ['catalog-security-source.xml']) {
    throw new RuntimeException('Expected catalog associated FileSpec metadata in WordPress smoke.');
}
if (($actionReview['destination_action_target_page_associated_file_filenames'] ?? []) !== ['outline-security-target.xml']) {
    throw new RuntimeException('Expected security action target associated-file metadata in WordPress smoke.');
}
if (!is_string($encoded)
    || str_contains($encoded, $catalogPayload)
    || str_contains($encoded, $targetPayload)
    || str_contains($encoded, $signaturePayload)
    || str_contains($encoded, $profileBytes)
) {
    throw new RuntimeException('Expected associated payloads, signature bytes, and ICC bytes to stay review-only.');
}
if (str_contains($plainText, 'outline-security-target.xml')
    || str_contains($plainText, 'catalog-security-source.xml')
    || str_contains($plainText, 'associated-outline-helper.exe')
    || str_contains($plainText, 'hidden open associated review')
) {
    throw new RuntimeException('Expected action operands and associated-file names to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-associated-security-bundle-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-metadata-catalog-outline-associated-security-bundle-currentbase',
    'native_boundary' => 'catalog PDF/A /AF metadata and outline/OpenAction security review carry target page associated-file provenance without executing PDF actions or exposing payload bytes',
    'title' => $metadata['title'] ?? null,
    'catalog_af_filenames' => $metadata['pdfa_associated_files']['filenames'] ?? [],
    'target_action_associated_filenames' => $actionReview['destination_action_target_page_associated_file_filenames'] ?? [],
    'target_action_checksum_statuses' => $actionReview['destination_action_target_page_associated_file_checksum_statuses'] ?? [],
    'unsafe_action_count' => $actionReview['unsafe_action_count'] ?? null,
    'import_decision' => $preflight['import_decision'] ?? null,
    'payload_content_exposed' => false,
    'executes_pdf_actions' => $preflight['executes_pdf_actions'] ?? null,
    'executes_signature_validation' => $preflight['executes_signature_validation'] ?? null,
    'executes_python_or_models' => $preflight['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $preflight['executes_external_pdf_tools'] ?? null,
]) . " -->\n";

foreach (array_filter(array_map('trim', explode("\n", $plainText))) as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
