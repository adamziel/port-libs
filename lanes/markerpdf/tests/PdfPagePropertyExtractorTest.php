<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pagePropertyPdf = static function (): string {
    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true /UserProperties true /Suspects false >> /StructTreeRoot 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 5 0 R /PieceInfo 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /PieceInfo << /WPSecond << /LastModified (D:20260602071300Z) /Private << /Template (appendix) /Imported false >> >> >> >>\nendobj\n"
        . "5 0 obj\n<< /Length 54 >>\nstream\nBT /F1 12 Tf 72 720 Td (Visible Page Review) Tj ET\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /StructTreeRoot /K [21 0 R 22 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Type /StructElem /S /Figure /T (Hero image) /Pg 3 0 R /A 23 0 R /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
        . "22 0 obj\n<< /Type /StructElem /S /P /T (Second page note) /Pg 4 0 R /A [24 0 R << /O /Layout /SpaceBefore 12 >>] >>\nendobj\n"
        . "23 0 obj\n<< /O /UserProperties /P [<< /N (WP Block) /V (core/image) /F (Image block) >> << /N (Supplier) /V (Migration App) /H true >> << /N (Priority) /V 7 >>] >>\nendobj\n"
        . "24 0 obj\n<< /O /UserProperties /P [<< /N (Needs Review) /V true >> << /N (Score) /V 0.875 /F (87.5%) >>] >>\nendobj\n"
        . "30 0 obj\n<< /WPPage << /LastModified (D:20260602071100Z) /Private << /Template (landing) /BlockCount 3 /NeedsReview true /Tags [(hero) /review 5] /Nested << /State /Imported >> >> >> >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'extracts page PieceInfo and tagged PDF UserProperties for WordPress review metadata' => static function (TestRunner $t) use ($pagePropertyPdf): void {
        $pages = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pagePropertyPdf());

        $t->same(2, count($pages));

        $first = $pages[0];
        $t->same(0, $first['pnum']);
        $t->same(3, $first['page_object']);
        $t->same(true, $first['mark_info_user_properties']);
        $t->same('D:20260602071100Z', $first['piece_info']['WPPage']['last_modified']);
        $t->same('landing', $first['piece_info']['WPPage']['private']['Template']);
        $t->same(3, $first['piece_info']['WPPage']['private']['BlockCount']);
        $t->same(true, $first['piece_info']['WPPage']['private']['NeedsReview']);
        $t->same(['hero', 'review', 5], $first['piece_info']['WPPage']['private']['Tags']);
        $t->same('Imported', $first['piece_info']['WPPage']['private']['Nested']['State']);
        $t->same(3, count($first['user_properties']));

        $t->same('Figure', $first['user_properties'][0]['struct_type']);
        $t->same('Hero image', $first['user_properties'][0]['title']);
        $t->same(23, $first['user_properties'][0]['attribute_object']);
        $t->same('WP Block', $first['user_properties'][0]['name']);
        $t->same('core/image', $first['user_properties'][0]['value']);
        $t->same('Image block', $first['user_properties'][0]['formatted_value']);
        $t->same(false, $first['user_properties'][0]['hidden']);
        $t->same('Supplier', $first['user_properties'][1]['name']);
        $t->same('Migration App', $first['user_properties'][1]['value']);
        $t->same(true, $first['user_properties'][1]['hidden']);
        $t->same('Priority', $first['user_properties'][2]['name']);
        $t->same(7, $first['user_properties'][2]['value']);

        $second = $pages[1];
        $t->same(1, $second['pnum']);
        $t->same(4, $second['page_object']);
        $t->same('D:20260602071300Z', $second['piece_info']['WPSecond']['last_modified']);
        $t->same('appendix', $second['piece_info']['WPSecond']['private']['Template']);
        $t->same(false, $second['piece_info']['WPSecond']['private']['Imported']);
        $t->same(2, count($second['user_properties']));
        $t->same('P', $second['user_properties'][0]['struct_type']);
        $t->same('Second page note', $second['user_properties'][0]['title']);
        $t->same('Needs Review', $second['user_properties'][0]['name']);
        $t->same(true, $second['user_properties'][0]['value']);
        $t->same('Score', $second['user_properties'][1]['name']);
        $t->same(0.875, $second['user_properties'][1]['value']);
        $t->same('87.5%', $second['user_properties'][1]['formatted_value']);
    },
    'returns no page review rows when PieceInfo is absent and MarkInfo does not advertise UserProperties' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true /UserProperties false >> /StructTreeRoot 20 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "20 0 obj\n<< /Type /StructTreeRoot /K 21 0 R >>\nendobj\n"
            . "21 0 obj\n<< /Type /StructElem /S /Figure /Pg 3 0 R /A << /O /UserProperties /P [<< /N (Hidden) /V (Ignored) >>] >> >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $t->same([], (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf));
        $t->same([], (new PdfPagePropertyExtractor())->extractPageReviewMetadata('%PDF-1.4 no catalog'));
    },
    'extracts MarkInfo flags and page associated Filespec review boundaries' => static function (TestRunner $t): void {
        $sourcePayload = '<wp-export><post id="20"/></wp-export>';
        $previewPayload = 'BT /F1 12 Tf 72 720 Td (Page AF Payload Leak) Tj ET';
        $pageText = 'BT /F1 12 Tf 72 720 Td (Page Associated Review) Tj ET';
        $pdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true /UserProperties false /Suspects true >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 5 0 R /PieceInfo << /WPImport << /LastModified (D:20260602085800Z) /Private << /BatchId (batch-20) /NeedsReview true >> >> >> /AF [10 0 R << /Type /Filespec /UF (preview.pdf) /Desc (Rendered preview) /AFRelationship /Alternative /EF << /UF 15 0 R >> >> 99 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Original WordPress export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
            . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Length " . strlen($previewPayload) . " >>\nstream\n{$previewPayload}\nendstream\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $pages = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(1, count($pages));
        $page = $pages[0];
        $t->same(0, $page['pnum']);
        $t->same(3, $page['page_object']);
        $t->same([
            'source' => 'catalog_mark_info',
            'marked' => true,
            'user_properties' => false,
            'suspects' => true,
        ], $page['mark_info']);
        $t->same('D:20260602085800Z', $page['piece_info']['WPImport']['last_modified']);
        $t->same('batch-20', $page['piece_info']['WPImport']['private']['BatchId']);
        $t->same(true, $page['piece_info']['WPImport']['private']['NeedsReview']);

        $associated = $page['page_associated_files'];
        $t->same(2, count($associated));

        $source = $associated[0];
        $t->same('page_associated_files', $source['source']);
        $t->same(true, $source['associated_file']);
        $t->same(0, $source['associated_file_index']);
        $t->same('source.xml', $source['name']);
        $t->same('source.xml', $source['filename']);
        $t->same('Original WordPress export', $source['description']);
        $t->same('Source', $source['relationship']);
        $t->same('text/xml', $source['mime_type']);
        $t->same(10, $source['file_spec_object']);
        $t->same(11, $source['embedded_file_object']);
        $t->same(strlen($sourcePayload), $source['declared_size']);
        $t->same(strlen($sourcePayload), $source['size']);
        $t->same(hash('sha256', $sourcePayload), $source['content_sha256']);
        $t->same(false, array_key_exists('content', $source));

        $preview = $associated[1];
        $t->same(1, $preview['associated_file_index']);
        $t->same('preview.pdf', $preview['name']);
        $t->same('preview.pdf', $preview['filename']);
        $t->same('Rendered preview', $preview['description']);
        $t->same('Alternative', $preview['relationship']);
        $t->same('application/pdf', $preview['mime_type']);
        $t->same(null, $preview['file_spec_object']);
        $t->same(15, $preview['embedded_file_object']);
        $t->same(strlen($previewPayload), $preview['size']);
        $t->same(hash('sha256', $previewPayload), $preview['content_sha256']);

        $t->contains('Page Associated Review', $plainText);
        $t->same(false, str_contains($plainText, 'wp-export'));
        $t->same(false, str_contains($plainText, 'Page AF Payload Leak'));
    },
    'combines page associated Filespecs with transition and action review metadata' => static function (TestRunner $t): void {
        $sourcePayload = '<wp-export><post id="44"/></wp-export>';
        $previewPayload = 'BT /F1 12 Tf 72 720 Td (Associated Transition Payload Leak) Tj ET';
        $pageText = 'BT /F1 12 Tf 72 720 Td (Associated Transition Review) Tj ET';
        $pdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageLabels << /Nums [0 << /P (deck-) /S /D /St 7 >>] >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Dur 12 /Trans 5 0 R /AA << /O 6 0 R /C << /S /URI /URI (javascript:alert\\(1\\)) /Next 7 0 R >> >> /AF [10 0 R << /Type /Filespec /UF (preview.pdf) /Desc (Rendered slide preview) /AFRelationship /Alternative /EF << /UF 15 0 R >> >>] >>\nendobj\n"
            . "5 0 obj\n<< /S /Fly /D 0.75 /Dm /V /M /I /Di 270 /SS 0.8 /B false >>\nendobj\n"
            . "6 0 obj\n<< /S /URI /URI (https://example.com/deck-notes) >>\nendobj\n"
            . "7 0 obj\n<< /S /GoToR /F (appendix.pdf) /D (Slide 8) /NewWindow true >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Original WordPress export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
            . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Length " . strlen($previewPayload) . " >>\nstream\n{$previewPayload}\nendstream\nendobj\n"
            . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $pages = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(1, count($pages));
        $page = $pages[0];
        $t->same(0, $page['pnum']);
        $t->same(3, $page['page_object']);
        $t->same(2, count($page['page_associated_files']));
        $t->same(['Source', 'Alternative'], array_column($page['page_associated_files'], 'relationship'));
        $t->same(['source.xml', 'preview.pdf'], array_column($page['page_associated_files'], 'filename'));
        $t->same(hash('sha256', $sourcePayload), $page['page_associated_files'][0]['content_sha256']);
        $t->same(hash('sha256', $previewPayload), $page['page_associated_files'][1]['content_sha256']);
        $t->same(false, array_key_exists('content', $page['page_associated_files'][0]));
        $t->same(false, array_key_exists('content', $page['page_associated_files'][1]));

        $presentation = $page['page_presentation'];
        $t->same(0, $presentation['pnum']);
        $t->same(1, $presentation['page_number']);
        $t->same(3, $presentation['page_object']);
        $t->same('deck-7', $presentation['page_label']);
        $t->same(12.0, $presentation['display_duration']);
        $t->same([
            'style' => 'Fly',
            'duration' => 0.75,
            'dimension' => 'V',
            'motion' => 'I',
            'direction' => 270.0,
            'scale' => 0.8,
            'opaque_background' => false,
        ], $presentation['transition']);
        $t->same(3, count($presentation['actions']));
        $t->same(['page_open', 'page_close', 'page_close'], array_column($presentation['actions'], 'event_label'));
        $t->same(['URI', 'URI', 'GoToR'], array_column($presentation['actions'], 'action_type'));
        $t->same(['review-uri', 'blocked-unsafe-uri', 'remote-document-review'], array_column($presentation['actions'], 'safety'));
        $t->same(false, $presentation['actions'][0]['executes_on_import']);
        $t->same('https://example.com/deck-notes', $presentation['actions'][0]['uri']);
        $t->same(false, $presentation['actions'][1]['is_safe_uri']);
        $t->same(true, $presentation['actions'][2]['chained']);
        $t->same('appendix.pdf', $presentation['actions'][2]['file']);
        $t->same('Slide 8', $presentation['actions'][2]['destination']);
        $t->same(true, $presentation['actions'][2]['new_window']);

        $t->contains('Associated Transition Review', $plainText);
        $t->same(false, str_contains($plainText, 'wp-export'));
        $t->same(false, str_contains($plainText, 'Associated Transition Payload Leak'));
        $t->same(false, str_contains($plainText, 'javascript:alert'));
        $t->same(false, str_contains($plainText, 'appendix.pdf'));
    },
];
