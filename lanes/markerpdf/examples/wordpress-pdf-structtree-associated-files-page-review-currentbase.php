<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$catalogPayload = '<wp-export><post id="catalog"/></wp-export>';
$pagePayload = '{"page":"preview"}';
$structurePayload = '<wp-export><post id="struct"/></wp-export>';
$supplementPayload = '<caption>Alternative figure caption data</caption>';
$structureXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Struct Associated XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
$structureProfile = 'Struct associated ICC bytes stay review only';
$compressedXmp = gzcompress($structureXmp);
$compressedProfile = gzcompress($structureProfile);
if (!is_string($compressedXmp) || !is_string($compressedProfile)) {
    throw new RuntimeException('Unable to compress structure-associated fixture streams.');
}

$catalogChecksum = strtoupper(hash('md5', $catalogPayload));
$structureChecksum = strtoupper(hash('md5', $structurePayload));
$pageContent = 'BT /F1 12 Tf /Figure << /MCID 0 >> BDC 72 720 Td (Struct AF Visible Figure) Tj EMC ET';
$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /MarkInfo << /Marked true >> /StructTreeRoot 40 0 R /AF [8 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /AF [10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Filespec /F (catalog-source.xml) /Desc (Catalog source export) /AFRelationship /Source /EF << /F 9 0 R >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($catalogPayload) . " /CheckSum <{$catalogChecksum}> >> /Length " . strlen($catalogPayload) . " >>\nstream\n{$catalogPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (page-preview.json) /Desc (Page preview payload) /AFRelationship /Alternative /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($pagePayload) . " >> /Length " . strlen($pagePayload) . " >>\nstream\n{$pagePayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (figure-source.xml) /Desc (Structure element source export) /AFRelationship /Source /Metadata 30 0 R /OutputIntents [32 0 R] /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($structurePayload) . " /CheckSum <{$structureChecksum}> /ModDate (D:20260602173100Z) >> /Length " . strlen($structurePayload) . " >>\nstream\n{$structurePayload}\nendstream\nendobj\n"
    . "22 0 obj\n<< /Type /Filespec /F (figure-caption.xml) /Desc (Accessible figure caption supplement) /AFRelationship /Supplement /EF << /F 23 0 R >> >>\nendobj\n"
    . "23 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fxml /Params << /Size " . strlen($supplementPayload) . " >> /Length " . strlen($supplementPayload) . " >>\nstream\n{$supplementPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "31 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($compressedProfile) . " >>\nstream\n{$compressedProfile}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Struct Associated sRGB) /Info (Attachment-local structure PDF/A) /DestOutputProfile 31 0 R >>\nendobj\n"
    . "40 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Figure /Figure >> /K 41 0 R >>\nendobj\n"
    . "41 0 obj\n<< /Type /StructElem /S /Figure /Pg 3 0 R /T (Figure structure with associated files) /Alt (Figure alternate review) /AF [20 0 R 22 0 R] /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (count($pageReviews) !== 1) {
    throw new RuntimeException('Expected one page review row.');
}

$pageReview = $pageReviews[0];
$mcrRows = $pageReview['structure_marked_content'] ?? [];
$structureFiles = $mcrRows[0]['associated_files'] ?? [];
if (count($structureFiles) !== 2 || ($mcrRows[0]['associated_file_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected two StructElem-associated files on the page MCID review row.');
}
if (count($pageReview['page_associated_files'] ?? []) !== 1) {
    throw new RuntimeException('Expected page-associated file metadata to remain separate.');
}
if (($structureFiles[0]['provenance_review']['relationship_role'] ?? null) !== 'original_source'
    || ($structureFiles[1]['provenance_review']['relationship_role'] ?? null) !== 'supplemental_representation'
) {
    throw new RuntimeException('Expected structure-associated relationship provenance roles.');
}
if (array_key_exists('content', $structureFiles[0]) || array_key_exists('content', $structureFiles[1])) {
    throw new RuntimeException('Expected structure-associated payloads to remain omitted.');
}
if (str_contains($plainText, '<wp-export>')
    || str_contains($plainText, '<caption>')
    || str_contains($plainText, 'Struct Associated XMP Title')
    || str_contains($plainText, 'Struct associated ICC bytes')
    || (is_string($encodedMetadata) && (
        str_contains($encodedMetadata, $structurePayload)
        || str_contains($encodedMetadata, $supplementPayload)
        || str_contains($encodedMetadata, $structureXmp)
        || str_contains($encodedMetadata, $structureProfile)
    ))
) {
    throw new RuntimeException('Expected structure-associated payloads and nested metadata to remain review-only.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-structtree-associated-files-page-review-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-structtree-associated-files-page-review',
    'native_boundary' => 'StructElem /AF FileSpec rows are preserved on page MCID review metadata while catalog/page associated files stay separate before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'catalog_associated_file_count' => count($metadata['associated_files'] ?? []),
    'page_associated_file_count' => count($pageReview['page_associated_files'] ?? []),
    'structure_mcid_count' => count($mcrRows),
    'structure_associated_file_count' => count($structureFiles),
    'relationship_roles' => array_map(
        static fn (array $file): ?string => $file['provenance_review']['relationship_role'] ?? null,
        $structureFiles
    ),
    'associated_pdfa_identifiers' => $structureFiles[0]['provenance_review']['pdfa_output_intents']['output_condition_identifiers'] ?? [],
    'payload_content_omitted' => !array_key_exists('content', $structureFiles[0]) && !array_key_exists('content', $structureFiles[1]),
    'visible_text_excludes_payloads' => !str_contains($plainText, '<wp-export>')
        && !str_contains($plainText, '<caption>')
        && !str_contains($plainText, 'Struct Associated XMP Title')
        && !str_contains($plainText, 'Struct associated ICC bytes'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($structureFiles as $file) {
    echo '<!-- markerpdf:structtree-associated-file-page-review ' . $htmlJson([
        'struct_object' => $mcrRows[0]['struct_object'] ?? null,
        'mcid' => $mcrRows[0]['mcid'] ?? null,
        'filename' => $file['filename'] ?? null,
        'relationship' => $file['relationship'] ?? null,
        'description' => $file['description'] ?? null,
        'size' => $file['size'] ?? null,
        'content_sha256' => $file['content_sha256'] ?? null,
        'checksum_matches' => $file['checksum_matches'] ?? null,
        'provenance_review' => $file['provenance_review'] ?? [],
    ]) . " -->\n";
}
