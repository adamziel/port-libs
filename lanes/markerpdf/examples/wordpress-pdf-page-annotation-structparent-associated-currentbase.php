<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="annotation-struct"/></wp-export>';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$content = 'BT /F1 12 Tf 72 720 Td (Visible page annotation context) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [6 0 R 7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Type /Annot /Subtype /Text /StructParent 17 /Rect [72 676 280 724] /Contents (Review note stays metadata) /T (Annotation QA) /NM /struct-note >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 18 /Rect [72 640 260 668] /Contents (Reference link review) /A << /S /URI /URI (https://example.com/struct-link) >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (annotation-source.xml) /Desc (Original annotation source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602192222Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /ReviewNote /P /DocLink /Link >> /ParentTree 31 0 R /K [40 0 R 41 0 R] >>\nendobj\n"
    . "31 0 obj\n<< /Nums [17 40 0 R 18 41 0 R] >>\nendobj\n"
    . "40 0 obj\n<< /Type /StructElem /S /ReviewNote /Pg 3 0 R /T (Annotation note structure) /Alt (Annotation alternate review) /AF [10 0 R] /K << /Type /OBJR /Obj 6 0 R >> >>\nendobj\n"
    . "41 0 obj\n<< /Type /StructElem /S /DocLink /Pg 3 0 R /T (Annotation link structure) /ActualText (Link actual text review) /K << /Type /OBJR /Obj 7 0 R >> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$textExtractor = new PdfTextExtractor();
$plainText = $textExtractor->extractPlainText($pdf);
$lines = $textExtractor->extractTextLines($pdf);

if (count($annotationPages) !== 1 || count($annotationPages[0]['annotations'] ?? []) !== 2) {
    throw new RuntimeException('Expected two current page annotations.');
}

$annotations = $annotationPages[0]['annotations'];
$note = $annotations[0];
$file = $note['structure_parent']['associated_files'][0] ?? null;
if (($note['struct_parent'] ?? null) !== 17 || ($note['structure_parent']['struct_object'] ?? null) !== 40) {
    throw new RuntimeException('Expected the note annotation StructParent to resolve through the ParentTree.');
}
if (!is_array($file) || ($file['checksum_matches'] ?? null) !== true || array_key_exists('content', $file)) {
    throw new RuntimeException('Expected StructElem associated-file checksum metadata without payload exposure.');
}
if (($annotations[1]['structure_parent']['role'] ?? null) !== 'Link') {
    throw new RuntimeException('Expected the link annotation StructParent role map to resolve.');
}
if ($lines !== ['Visible page annotation context']
    || str_contains($plainText, 'Review note stays metadata')
    || str_contains($plainText, 'Annotation note structure')
    || str_contains($plainText, 'Annotation alternate review')
    || str_contains($plainText, 'Link actual text review')
    || str_contains($plainText, '<wp-export>')
    || str_contains($plainText, 'annotation-source.xml')
) {
    throw new RuntimeException('Expected annotation structure metadata and associated payloads to remain out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-page-annotation-structparent-associated-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-page-annotation-structparent-associated-review',
    'native_boundary' => 'page annotation /StructParent keys resolve through StructTreeRoot ParentTree OBJR entries and StructElem associated files before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'annotation_objects' => array_column($annotations, 'annotation_object'),
    'struct_parent_keys' => array_column($annotations, 'struct_parent'),
    'structure_roles' => array_map(
        static fn (array $annotation): ?string => $annotation['structure_parent']['role'] ?? null,
        $annotations
    ),
    'associated_filename' => $file['filename'] ?? null,
    'associated_checksum_matches' => $file['checksum_matches'] ?? null,
    'raw_associated_content_exposed' => array_key_exists('content', $file ?? []),
    'visible_text_excludes_review_metadata' => !str_contains($plainText, '<wp-export>')
        && !str_contains($plainText, 'Annotation note structure')
        && !str_contains($plainText, 'Link actual text review')
        && !str_contains($plainText, 'annotation-source.xml'),
]) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

foreach ($annotations as $annotation) {
    echo '<!-- markerpdf:page-annotation-structparent-review ' . $htmlJson([
        'annotation_object' => $annotation['annotation_object'] ?? null,
        'subtype' => $annotation['subtype'] ?? null,
        'struct_parent' => $annotation['struct_parent'] ?? null,
        'structure_parent' => [
            'struct_object' => $annotation['structure_parent']['struct_object'] ?? null,
            'role' => $annotation['structure_parent']['role'] ?? null,
            'title' => $annotation['structure_parent']['title'] ?? null,
            'annotation_objects' => $annotation['structure_parent']['annotation_objects'] ?? [],
            'current_annotation_object_ref_matched' => $annotation['structure_parent']['current_annotation_object_ref_matched'] ?? null,
        ],
        'associated_files' => array_map(static fn (array $associatedFile): array => [
            'filename' => $associatedFile['filename'] ?? null,
            'relationship' => $associatedFile['relationship'] ?? null,
            'mime_type' => $associatedFile['mime_type'] ?? null,
            'checksum_algorithm' => $associatedFile['checksum_algorithm'] ?? null,
            'checksum_matches' => $associatedFile['checksum_matches'] ?? null,
            'content_sha256' => $associatedFile['content_sha256'] ?? null,
        ], $annotation['structure_parent']['associated_files'] ?? []),
    ]) . " -->\n";
}
