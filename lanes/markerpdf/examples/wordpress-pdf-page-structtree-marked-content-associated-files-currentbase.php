<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="struct-marked-associated"/></wp-export>';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$content = 'BT /F1 12 Tf '
    . '/SectionTitle << /MCID 0 >> BDC 72 720 Td (Associated heading visible) Tj EMC '
    . '/BodyCopy << /MCID 1 /ActualText (Associated body replacement) >> BDC 72 684 Td (Body glyph noise) Tj EMC ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R /PageLabels << /Nums [0 << /P (marked-associated-) /S /D /St 7 >>] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 7 /Contents 5 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (struct-marked-source.xml) /Desc (Struct marked source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602213951Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap << /SectionTitle /H2 /BodyCopy /P >> /ParentTree 30 0 R /K [] >>\nendobj\n"
    . "30 0 obj\n<< /Kids [31 0 R] >>\nendobj\n"
    . "31 0 obj\n<< /Limits [7 7] /Nums [7 [21 0 R 22 0 R]] >>\nendobj\n"
    . "21 0 obj\n<< /Type /StructElem /S /SectionTitle /Pg 3 0 R /T (Associated heading structure) /AF [10 0 R] /K 0 >>\nendobj\n"
    . "22 0 obj\n<< /Type /StructElem /S /BodyCopy /Pg 3 0 R /T (Associated body structure) /K 1 >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$textExtractor = new PdfTextExtractor();
$tagged = $textExtractor->extractTaggedContent($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);

if (count($tagged) !== 2) {
    throw new RuntimeException('Expected two StructTree marked-content rows.');
}
if (($tagged[0]['associated_file_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected StructElem associated FileSpec review metadata.');
}
if (array_key_exists('associated_file_count', $tagged[1])) {
    throw new RuntimeException('Unexpected associated file metadata on the body row.');
}
if (($pageReviews[0]['structure_marked_content'][0]['associated_files'][0]['checksum_matches'] ?? null) !== true) {
    throw new RuntimeException('Expected associated embedded-file checksum match in page review metadata.');
}
if (str_contains($plainText, '<wp-export>')
    || str_contains($plainText, 'Body glyph noise')
    || str_contains($plainText, 'Associated heading structure')
    || str_contains($plainText, 'Associated body structure')
    || str_contains($plainText, 'struct-marked-source.xml')
    || str_contains($plainText, 'marked-associated-7')
) {
    throw new RuntimeException('Expected StructTree and associated-file review metadata to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$associatedFiles = $tagged[0]['associated_files'] ?? [];
echo '<!-- markerpdf-page-structtree-marked-content-associated-files-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-tagged-content-parser',
    'native_boundary' => 'page /StructParents ParentTree StructElem /AF FileSpec metadata attached to marked-content review rows',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'page_label' => $pageReviews[0]['page_label'] ?? null,
    'struct_parents' => $pageReviews[0]['struct_parents'] ?? null,
    'tagged_mcids' => array_column($tagged, 'mcid'),
    'tagged_roles' => array_column($tagged, 'role'),
    'struct_objects' => array_column($tagged, 'struct_object'),
    'associated_filenames' => array_column($associatedFiles, 'filename'),
    'associated_relationships' => array_column($associatedFiles, 'relationship'),
    'checksum_matches' => array_column($associatedFiles, 'checksum_matches'),
    'payload_content_exposed' => array_key_exists('content', $associatedFiles[0] ?? []),
    'visible_text_excludes_review_metadata' => !str_contains($plainText, '<wp-export>')
        && !str_contains($plainText, 'Body glyph noise')
        && !str_contains($plainText, 'Associated heading structure')
        && !str_contains($plainText, 'Associated body structure')
        && !str_contains($plainText, 'struct-marked-source.xml')
        && !str_contains($plainText, 'marked-associated-7'),
]) . " -->\n";

foreach ($tagged as $row) {
    $text = htmlspecialchars((string) $row['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (($row['role'] ?? null) === 'H2') {
        echo "<!-- wp:heading {\"level\":2} -->\n";
        echo "<h2>{$text}</h2>\n";
        echo "<!-- /wp:heading -->\n\n";
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo "<p>{$text}</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:page-structtree-marked-content-associated-files-review ' . $htmlJson([
    'page_object' => $pageReviews[0]['page_object'] ?? null,
    'page_label' => $pageReviews[0]['page_label'] ?? null,
    'struct_parents' => $pageReviews[0]['struct_parents'] ?? null,
    'marked_content' => array_map(static fn (array $row): array => [
        'mcid' => $row['mcid'] ?? null,
        'role' => $row['role'] ?? null,
        'raw_role' => $row['raw_role'] ?? null,
        'struct_object' => $row['struct_object'] ?? null,
        'associated_file_count' => $row['associated_file_count'] ?? 0,
        'associated_files' => array_map(static fn (array $file): array => [
            'filename' => $file['filename'] ?? null,
            'relationship' => $file['relationship'] ?? null,
            'mime_type' => $file['mime_type'] ?? null,
            'size' => $file['size'] ?? null,
            'checksum_algorithm' => $file['checksum_algorithm'] ?? null,
            'checksum_matches' => $file['checksum_matches'] ?? null,
            'content_sha256' => $file['content_sha256'] ?? null,
            'modified_at' => $file['modified_at'] ?? null,
        ], $row['associated_files'] ?? []),
    ], $tagged),
]) . " -->\n";
