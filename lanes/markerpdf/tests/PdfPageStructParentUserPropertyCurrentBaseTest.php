<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageStructParentUserPropertyPdf = static function (bool $advertiseUserProperties = true): string {
    $content = 'BT /F1 12 Tf '
        . '/Caption << /MCID 1 >> BDC 72 684 Td (ParentTree caption visible) Tj EMC '
        . '/Figure << /MCID 0 >> BDC 72 720 Td (ParentTree figure visible) Tj EMC ET';
    $userProperties = $advertiseUserProperties ? 'true' : 'false';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true /UserProperties {$userProperties} >> /StructTreeRoot 20 0 R /PageLabels << /Nums [0 << /P (asset-) /S /D /St 44 >>] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 44 /Contents 5 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Figure /Figure /Caption /Caption >> /ParentTree 30 0 R /K [21 0 R 22 0 R] >>\nendobj\n"
        . "30 0 obj\n<< /Kids [31 0 R] >>\nendobj\n"
        . "31 0 obj\n<< /Limits [44 44] /Nums [44 [21 0 R 22 0 R null]] >>\nendobj\n"
        . "21 0 obj\n<< /Type /StructElem /S /Figure /T (Hero figure structure) /A 23 0 R /K 0 >>\nendobj\n"
        . "22 0 obj\n<< /Type /StructElem /S /Caption /T (Hero caption structure) /A [24 0 R << /O /Layout /SpaceBefore 12 >>] /K 1 >>\nendobj\n"
        . "23 0 obj\n<< /O /UserProperties /P [<< /N (WP Block) /V (core/image) /F (Image block) >> << /N (Migration Stage) /V /review /H true >>] >>\nendobj\n"
        . "24 0 obj\n<< /O /UserProperties /P [<< /N (Alt Source) /V (PDF structure tree) >> << /N (Confidence) /V 0.94 /F (94%) >>] >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'maps page StructParents ParentTree UserProperties into WordPress review metadata' => static function (TestRunner $t) use ($pageStructParentUserPropertyPdf): void {
        $pdf = $pageStructParentUserPropertyPdf();
        $pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $textExtractor = new PdfTextExtractor();
        $plainText = $textExtractor->extractPlainText($pdf);

        $t->same(1, count($pageReviews));
        $page = $pageReviews[0];
        $t->same(0, $page['pnum']);
        $t->same(1, $page['page_number']);
        $t->same(3, $page['page_object']);
        $t->same('asset-44', $page['page_label']);
        $t->same([
            'source' => 'catalog_mark_info',
            'marked' => true,
            'user_properties' => true,
        ], $page['mark_info']);
        $t->same(44, $page['struct_parents']);
        $t->same(44, $page['parent_tree']['key']);
        $t->same([0, 1], $page['parent_tree']['mcids']);
        $t->same(['Figure', 'Caption'], array_column($page['parent_tree']['entries'], 'raw_role'));
        $t->same([21, 22], array_column($page['parent_tree']['entries'], 'struct_object'));

        $properties = $page['user_properties'];
        $t->same(true, $page['mark_info_user_properties']);
        $t->same(4, count($properties));
        $t->same(['WP Block', 'Migration Stage', 'Alt Source', 'Confidence'], array_column($properties, 'name'));
        $t->same(['Figure', 'Figure', 'Caption', 'Caption'], array_column($properties, 'struct_type'));
        $t->same(['Hero figure structure', 'Hero figure structure', 'Hero caption structure', 'Hero caption structure'], array_column($properties, 'title'));
        $t->same(['page_structparents_user_properties', 'page_structparents_user_properties', 'page_structparents_user_properties', 'page_structparents_user_properties'], array_column($properties, 'source'));
        $t->same([44, 44, 44, 44], array_column($properties, 'struct_parents'));
        $t->same([0, 0, 1, 1], array_column($properties, 'mcid'));
        $t->same([21, 21, 22, 22], array_column($properties, 'struct_object'));
        $t->same(['Figure', 'Figure', 'Caption', 'Caption'], array_column($properties, 'raw_role'));
        $t->same([23, 23, 24, 24], array_column($properties, 'attribute_object'));
        $t->same('core/image', $properties[0]['value']);
        $t->same('Image block', $properties[0]['formatted_value']);
        $t->same(false, $properties[0]['hidden']);
        $t->same('review', $properties[1]['value']);
        $t->same(true, $properties[1]['hidden']);
        $t->same('PDF structure tree', $properties[2]['value']);
        $t->same(0.94, $properties[3]['value']);
        $t->same('94%', $properties[3]['formatted_value']);

        $rows = $page['structure_marked_content'];
        $t->same([0, 1], array_column($rows, 'mcid'));
        $t->same([21, 22], array_column($rows, 'struct_object'));
        $t->same(['Figure', 'Caption'], array_column($rows, 'role'));
        $t->same(['asset-44', 'asset-44'], array_column($rows, 'page_label'));

        $t->same(['ParentTree figure visible', 'ParentTree caption visible'], $textExtractor->extractTextLines($pdf));
        $t->contains('ParentTree figure visible', $plainText);
        $t->contains('ParentTree caption visible', $plainText);
        $t->same(false, str_contains($plainText, 'Hero figure structure'));
        $t->same(false, str_contains($plainText, 'Hero caption structure'));
        $t->same(false, str_contains($plainText, 'core/image'));
        $t->same(false, str_contains($plainText, 'PDF structure tree'));

        $notAdvertised = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pageStructParentUserPropertyPdf(false));
        $t->same(1, count($notAdvertised));
        $t->same(false, array_key_exists('mark_info_user_properties', $notAdvertised[0]));
        $t->same(false, array_key_exists('user_properties', $notAdvertised[0]));
    },
];
