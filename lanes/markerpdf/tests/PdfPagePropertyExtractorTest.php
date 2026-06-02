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
    'carries page associated checksum state with MarkInfo UserProperties and PieceInfo review metadata' => static function (TestRunner $t): void {
        $sourcePayload = '<wp-export><post id="72"/></wp-export>';
        $previewPayload = '{"preview":"edited-after-checksum"}';
        $pageText = 'BT /F1 12 Tf 72 720 Td (Page Attachment Checksum Review) Tj ET';
        $sourceChecksum = strtoupper(hash('md5', $sourcePayload));
        $staleChecksum = str_repeat('0a', 16);

        $pdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true /UserProperties true /Suspects false >> /StructTreeRoot 20 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /PieceInfo << /WPImport << /LastModified (D:20260602162900Z) /Private << /BatchId (page-72) /NeedsReview true >> >> >> /AF [10 0 R 12 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Original WordPress export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /CreationDate (D:20260602162800Z) /ModDate (D:20260602162930Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Type /Filespec /UF (preview.json) /Desc (Generated page preview) /AFRelationship /Alternative /EF << /UF 13 0 R >> >>\nendobj\n"
            . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($previewPayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($previewPayload) . " >>\nstream\n{$previewPayload}\nendstream\nendobj\n"
            . "20 0 obj\n<< /Type /StructTreeRoot /K 21 0 R >>\nendobj\n"
            . "21 0 obj\n<< /Type /StructElem /S /Figure /T (Attachment figure) /Pg 3 0 R /A 22 0 R /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
            . "22 0 obj\n<< /O /UserProperties /P [<< /N (WP Block) /V (core/file) /F (File block) >> << /N (Needs Attachment Review) /V true /H true >>] >>\nendobj\n"
            . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $pages = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(1, count($pages));
        $page = $pages[0];
        $t->same([
            'source' => 'catalog_mark_info',
            'marked' => true,
            'user_properties' => true,
            'suspects' => false,
        ], $page['mark_info']);
        $t->same('D:20260602162900Z', $page['piece_info']['WPImport']['last_modified']);
        $t->same('page-72', $page['piece_info']['WPImport']['private']['BatchId']);
        $t->same(true, $page['piece_info']['WPImport']['private']['NeedsReview']);
        $t->same(true, $page['mark_info_user_properties']);
        $t->same(['WP Block', 'Needs Attachment Review'], array_column($page['user_properties'], 'name'));
        $t->same(['Source', 'Alternative'], array_column($page['page_associated_files'], 'relationship'));

        $source = $page['page_associated_files'][0];
        $t->same('source.xml', $source['filename']);
        $t->same(strtolower($sourceChecksum), $source['checksum']);
        $t->same('md5', $source['checksum_algorithm']);
        $t->same(hash('md5', $sourcePayload), $source['computed_checksum']);
        $t->same(true, $source['checksum_matches']);
        $t->same('D:20260602162800Z', $source['created_at']);
        $t->same('D:20260602162930Z', $source['modified_at']);
        $t->same(false, array_key_exists('content', $source));

        $preview = $page['page_associated_files'][1];
        $t->same('preview.json', $preview['filename']);
        $t->same($staleChecksum, $preview['checksum']);
        $t->same(hash('md5', $previewPayload), $preview['computed_checksum']);
        $t->same(false, $preview['checksum_matches']);
        $t->same(false, array_key_exists('content', $preview));

        $t->contains('Page Attachment Checksum Review', $plainText);
        $t->same(false, str_contains($plainText, '<wp-export>'));
        $t->same(false, str_contains($plainText, 'edited-after-checksum'));
    },
    'composes page PieceInfo with article thread beads and StructTree MCR review metadata' => static function (TestRunner $t): void {
        $firstPageContent = 'BT /F1 12 Tf '
            . '/Article << /MCID 0 >> BDC 72 720 Td (Article heading visible) Tj EMC '
            . '/Article << /MCID 1 >> BDC 72 680 Td (Article body visible) Tj EMC ET';
        $secondPageContent = 'BT /F1 12 Tf /Aside << /MCID 0 >> BDC 72 720 Td (Related article visible) Tj EMC ET';
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Threads [20 0 R] /MarkInfo << /Marked true >> /StructTreeRoot 40 0 R /PageLabels << /Nums [0 << /P (A-) /S /D /St 9 >> 1 << /P (B-) /S /D /St 10 >>] >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /PieceInfo << /WPArticle << /LastModified (D:20260602165500Z) /Private << /ThreadId (thread-9) /ReviewStage /mcr-check /NeedsReview true >> >> >> >>\nendobj\n"
            . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
            . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Editorial Article Thread) >> >>\nendobj\n"
            . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [60 700 260 740] /N 22 0 R /V 23 0 R >>\nendobj\n"
            . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [60 660 260 699] /N 23 0 R /V 21 0 R >>\nendobj\n"
            . "23 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [60 700 260 740] /N 21 0 R /V 22 0 R >>\nendobj\n"
            . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
            . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
            . "40 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Art /Article /Aside /P >> /K [41 0 R 42 0 R] >>\nendobj\n"
            . "41 0 obj\n<< /Type /StructElem /S /Art /Pg 3 0 R /Lang (en-US) /T (Thread article section) /Alt (Article alternate review text) /ActualText (Article actual review text) /ID (article-9) /C [/feature /review] /K [<< /Type /MCR /Pg 3 0 R /MCID 0 >> << /Type /MCR /Pg 3 0 R /MCID 1 >>] >>\nendobj\n"
            . "42 0 obj\n<< /Type /StructElem /S /Aside /Pg 4 0 R /T (Related aside) /K << /Type /MCR /Pg 4 0 R /MCID 0 >> >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $pages = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(2, count($pages));

        $first = $pages[0];
        $t->same(0, $first['pnum']);
        $t->same(3, $first['page_object']);
        $t->same('D:20260602165500Z', $first['piece_info']['WPArticle']['last_modified']);
        $t->same('thread-9', $first['piece_info']['WPArticle']['private']['ThreadId']);
        $t->same('mcr-check', $first['piece_info']['WPArticle']['private']['ReviewStage']);
        $t->same(true, $first['piece_info']['WPArticle']['private']['NeedsReview']);
        $t->same(['Editorial Article Thread'], $first['article_thread_titles']);
        $t->same([21, 22], array_column($first['article_thread_beads'], 'bead_object'));
        $t->same(['A-9', 'A-9'], array_column($first['article_thread_beads'], 'page_label'));
        $t->same([22, 23], array_column($first['article_thread_beads'], 'next_bead_object'));
        $t->same([23, 21], array_column($first['article_thread_beads'], 'previous_bead_object'));

        $mcrRows = $first['structure_marked_content'];
        $t->same(2, count($mcrRows));
        $t->same([0, 1], array_column($mcrRows, 'mcid'));
        $t->same([41, 41], array_column($mcrRows, 'struct_object'));
        $t->same(['Art', 'Art'], array_column($mcrRows, 'raw_role'));
        $t->same(['Article', 'Article'], array_column($mcrRows, 'role'));
        $t->same([true, true], array_column($mcrRows, 'role_mapped'));
        $t->same(['A-9', 'A-9'], array_column($mcrRows, 'page_label'));
        $t->same('Thread article section', $mcrRows[0]['title']);
        $t->same('Article alternate review text', $mcrRows[0]['alternate_text']);
        $t->same('Article actual review text', $mcrRows[0]['actual_text']);
        $t->same(['feature', 'review'], $mcrRows[0]['classes']);

        $second = $pages[1];
        $t->same(1, $second['pnum']);
        $t->same(4, $second['page_object']);
        $t->same(false, array_key_exists('piece_info', $second));
        $t->same([23], array_column($second['article_thread_beads'], 'bead_object'));
        $t->same('B-10', $second['article_thread_beads'][0]['page_label']);
        $t->same([0], array_column($second['structure_marked_content'], 'mcid'));
        $t->same('P', $second['structure_marked_content'][0]['role']);
        $t->same('Related aside', $second['structure_marked_content'][0]['title']);

        $t->contains('Article heading visible', $plainText);
        $t->contains('Article body visible', $plainText);
        $t->contains('Related article visible', $plainText);
        $t->same(false, str_contains($plainText, 'Editorial Article Thread'));
        $t->same(false, str_contains($plainText, 'Thread article section'));
        $t->same(false, str_contains($plainText, 'Article alternate review text'));
        $t->same(false, str_contains($plainText, 'Article actual review text'));
        $t->same(false, str_contains($plainText, 'thread-9'));
    },
    'merges page StructParents ParentTree rows with inherited Resources transition and labels for review' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf '
            . '/BodyAlias /BodyProp BDC 72 700 Td (Body glyph noise) Tj EMC '
            . '/DeckTitle << /MCID 0 >> BDC 72 720 Td (Deck heading visible) Tj EMC ET';
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R /PageLabels << /Nums [0 << /P (deck-) /S /D /St 3 >>] >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 7 0 R >> /Properties << /BodyProp 8 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Contents 5 0 R /Dur 8 /Trans 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /S /Dissolve /D 0.5 >>\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "8 0 obj\n<< /MCID 1 /ActualText (Inherited resource body) >>\nendobj\n"
            . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap 40 0 R /ParentTree 30 0 R /K [21 0 R 22 0 R] >>\nendobj\n"
            . "21 0 obj\n<< /Type /StructElem /S /DeckTitle /P 20 0 R /K 0 >>\nendobj\n"
            . "22 0 obj\n<< /Type /StructElem /S /BodyAlias /P 20 0 R /K 1 >>\nendobj\n"
            . "30 0 obj\n<< /Nums [0 [21 0 R 22 0 R]] >>\nendobj\n"
            . "40 0 obj\n<< /DeckTitle /H2 /BodyAlias /P >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $extractor = new PdfTextExtractor();
        $pages = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(1, count($pages));
        $page = $pages[0];
        $t->same(0, $page['pnum']);
        $t->same(3, $page['page_object']);
        $t->same([
            'source' => 'catalog_mark_info',
            'marked' => true,
        ], $page['mark_info']);

        $presentation = $page['page_presentation'];
        $t->same('deck-3', $presentation['page_label']);
        $t->same(8.0, $presentation['display_duration']);
        $t->same('Dissolve', $presentation['transition']['style']);
        $t->same(0.5, $presentation['transition']['duration']);

        $rows = $page['structure_marked_content'];
        $t->same(2, count($rows));
        $t->same(['page_structparents_parenttree_tagged_content', 'page_structparents_parenttree_tagged_content'], array_column($rows, 'source'));
        $t->same([0, 1], array_column($rows, 'mcid'));
        $t->same(['DeckTitle', 'BodyAlias'], array_column($rows, 'raw_role'));
        $t->same(['H2', 'P'], array_column($rows, 'role'));
        $t->same([true, true], array_column($rows, 'role_mapped'));
        $t->same(['deck-3', 'deck-3'], array_column($rows, 'page_label'));
        $t->same([['DeckTitle'], ['BodyAlias']], array_column($rows, 'content_tags'));
        $t->same([true, true], array_column($rows, 'resources_resolved_for_tagged_text'));
        $t->same(false, array_key_exists('text', $rows[0]));
        $t->same(false, array_key_exists('text', $rows[1]));

        $t->same("Deck heading visible\nInherited resource body", $plainText);
        $t->same(['Deck heading visible', 'Inherited resource body'], $extractor->extractTextLines($pdf));
        $t->same(['Deck heading visible', 'Inherited resource body'], array_column($extractor->extractTaggedContent($pdf), 'text'));
        $t->same(false, str_contains($plainText, 'Body glyph noise'));
        $t->same(false, str_contains($plainText, 'deck-3'));
    },
];
