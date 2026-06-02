<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageStructTreeMarkedContentAssociatedPayload = '<wp-export><post id="struct-marked-associated"/></wp-export>';
$pageStructTreeMarkedContentAssociatedChecksum = strtoupper(hash('md5', $pageStructTreeMarkedContentAssociatedPayload));
$pageStructTreeMarkedContentAssociatedPdf = static function () use (
    $pageStructTreeMarkedContentAssociatedPayload,
    $pageStructTreeMarkedContentAssociatedChecksum
): string {
    $content = 'BT /F1 12 Tf '
        . '/SectionTitle << /MCID 0 >> BDC 72 720 Td (Associated heading visible) Tj EMC '
        . '/BodyCopy << /MCID 1 /ActualText (Associated body replacement) >> BDC 72 684 Td (Body glyph noise) Tj EMC ET';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R /PageLabels << /Nums [0 << /P (marked-associated-) /S /D /St 7 >>] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 7 /Contents 5 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (struct-marked-source.xml) /Desc (Struct marked source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageStructTreeMarkedContentAssociatedPayload) . " /CheckSum <{$pageStructTreeMarkedContentAssociatedChecksum}> /ModDate (D:20260602213951Z) >> /Length " . strlen($pageStructTreeMarkedContentAssociatedPayload) . " >>\nstream\n{$pageStructTreeMarkedContentAssociatedPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap << /SectionTitle /H2 /BodyCopy /P >> /ParentTree 30 0 R /K [] >>\nendobj\n"
        . "30 0 obj\n<< /Kids [31 0 R] >>\nendobj\n"
        . "31 0 obj\n<< /Limits [7 7] /Nums [7 [21 0 R 22 0 R]] >>\nendobj\n"
        . "21 0 obj\n<< /Type /StructElem /S /SectionTitle /Pg 3 0 R /T (Associated heading structure) /AF [10 0 R] /K 0 >>\nendobj\n"
        . "22 0 obj\n<< /Type /StructElem /S /BodyCopy /Pg 3 0 R /T (Associated body structure) /K 1 >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'carries ParentTree StructElem associated files onto marked-content tagged rows' => static function (TestRunner $t) use (
        $pageStructTreeMarkedContentAssociatedPdf,
        $pageStructTreeMarkedContentAssociatedPayload,
        $pageStructTreeMarkedContentAssociatedChecksum
    ): void {
        $pdf = $pageStructTreeMarkedContentAssociatedPdf();
        $textExtractor = new PdfTextExtractor();
        $tagged = $textExtractor->extractTaggedContent($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);

        $t->same(['Associated heading visible', 'Associated body replacement'], $textExtractor->extractTextLines($pdf));
        $t->same("Associated heading visible\nAssociated body replacement", $plainText);
        $t->same(2, count($tagged));
        $t->same([0, 1], array_column($tagged, 'mcid'));
        $t->same(['SectionTitle', 'BodyCopy'], array_column($tagged, 'raw_role'));
        $t->same(['H2', 'P'], array_column($tagged, 'role'));
        $t->same([true, true], array_column($tagged, 'role_mapped'));
        $t->same(['Associated heading visible', 'Associated body replacement'], array_column($tagged, 'text'));
        $t->same([21, 22], array_column($tagged, 'struct_object'));
        $t->same('Associated heading structure', $tagged[0]['title']);
        $t->same('Associated body structure', $tagged[1]['title']);

        $files = $tagged[0]['associated_files'];
        $t->same(1, $tagged[0]['associated_file_count']);
        $t->same(false, array_key_exists('associated_file_count', $tagged[1]));
        $t->same(1, count($files));
        $t->same('structure_element_associated_files', $files[0]['source']);
        $t->same('struct-marked-source.xml', $files[0]['filename']);
        $t->same('Struct marked source export', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same('text/xml', $files[0]['mime_type']);
        $t->same(strlen($pageStructTreeMarkedContentAssociatedPayload), $files[0]['size']);
        $t->same(strlen($pageStructTreeMarkedContentAssociatedPayload), $files[0]['declared_size']);
        $t->same(hash('sha256', $pageStructTreeMarkedContentAssociatedPayload), $files[0]['content_sha256']);
        $t->same(strtolower($pageStructTreeMarkedContentAssociatedChecksum), $files[0]['checksum']);
        $t->same(hash('md5', $pageStructTreeMarkedContentAssociatedPayload), $files[0]['computed_checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same('D:20260602213951Z', $files[0]['modified_at']);
        $t->same(false, array_key_exists('content', $files[0]));

        $structureTree = $metadata['structure_tree'];
        $t->same('catalog_struct_tree_root', $structureTree['source']);
        $t->same(2, $structureTree['element_count']);
        $t->same([21, 22], array_column($structureTree['elements'], 'object'));
        $t->same([[0], [1]], array_column($structureTree['elements'], 'mcids'));
        $t->same(1, $structureTree['elements'][0]['associated_file_count']);
        $t->same('struct-marked-source.xml', $structureTree['elements'][0]['associated_files'][0]['filename']);

        $t->same(1, count($pageReviews));
        $page = $pageReviews[0];
        $t->same('marked-associated-7', $page['page_label']);
        $t->same(7, $page['struct_parents']);
        $t->same([0, 1], array_column($page['structure_marked_content'], 'mcid'));
        $t->same([21, 22], array_column($page['structure_marked_content'], 'struct_object'));
        $t->same(1, $page['structure_marked_content'][0]['associated_file_count']);
        $t->same('struct-marked-source.xml', $page['structure_marked_content'][0]['associated_files'][0]['filename']);

        $encoded = json_encode([$tagged, $metadata, $pageReviews], JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encoded, $pageStructTreeMarkedContentAssociatedPayload));
        $t->same(false, str_contains($plainText, '<wp-export>'));
        $t->same(false, str_contains($plainText, 'Body glyph noise'));
        $t->same(false, str_contains($plainText, 'Associated heading structure'));
        $t->same(false, str_contains($plainText, 'Associated body structure'));
        $t->same(false, str_contains($plainText, 'struct-marked-source.xml'));
        $t->same(false, str_contains($plainText, 'marked-associated-7'));
    },
];
