<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfJavaScriptActionInspector;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$nameTreeJavaScriptPdf = static function (): array {
    $streamScript = "app.alert('stream-backed review action');";
    $compressedStreamScript = gzcompress($streamScript);
    if (!is_string($compressedStreamScript)) {
        throw new RuntimeException('Unable to compress JavaScript stream fixture.');
    }

    $utf16Script = "\xfe\xff\0a\0p\0p\0.\0a\0l\0e\0r\0t\0(\0'\0u\0t\0f\0-\0s\0t\0r\0i\0n\0g\0'\0)";
    $utf16Hex = strtoupper(bin2hex($utf16Script));

    $content = 'BT /F1 12 Tf 72 720 Td (Safe visible PDF text) Tj ET';
    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /JavaScript 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Kids [7 0 R 8 0 R] >>\nendobj\n"
        . "7 0 obj\n<< /Limits [(init) (review)] /Names [(init) 9 0 R (review) << /S /JavaScript /JS <{$utf16Hex}> >>] >>\nendobj\n"
        . "8 0 obj\n<< /Limits [(stream) (stream)] /Names [(stream) << /S /JavaScript /JS 10 0 R >>] >>\nendobj\n"
        . "9 0 obj\n<< /S /JavaScript /JS (app.alert\\('named import action disabled'\\)) >>\nendobj\n"
        . "10 0 obj\n<< /Length " . strlen($compressedStreamScript) . " /Filter /FlateDecode >>\nstream\n{$compressedStreamScript}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $streamScript];
};

$catalogAndAnnotationActionPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Reviewed import paragraph) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Second page clean text) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OpenAction 5 0 R /AA << /WC 6 0 R /WS << /S /URI /URI (https://example.com/safe) /Next [7 0 R 7 0 R] >> >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /AA << /O << /S /JavaScript /JS (pageOpen\\(\\)) >> >> /Annots [8 0 R 9 0 R 10 0 R] /Contents 11 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 12 0 R >>\nendobj\n"
        . "5 0 obj\n<< /S /JavaScript /JS (openReview\\(\\)) >>\nendobj\n"
        . "6 0 obj\n<< /S /JavaScript /JS (willCloseReview\\(\\)) >>\nendobj\n"
        . "7 0 obj\n<< /S /JavaScript /JS (chainedReview\\(\\)) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 200 718] /A << /S /JavaScript /JS (linkClick\\(\\)) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 680 200 698] /A << /S /URI /URI (javascript:alert\\(1\\)) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 640 200 660] /AA << /Fo << /S /JavaScript /JS (fieldFocus\\(\\)) >> >> >>\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "%%EOF";
};

$cyclicActionChainPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Action chain import body) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OpenAction 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /S /URI /URI (https://example.com/import) /Next [6 0 R 9 0 R] >>\nendobj\n"
        . "6 0 obj\n<< /S /JavaScript /JS (firstChainReview\\(\\)) /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /S /Launch /F (helper.exe) /Next [8 0 R 6 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /S /JavaScript /JS (deepChainReview\\(\\)) /Next 5 0 R >>\nendobj\n"
        . "9 0 obj\n<< /S /JavaScript /JS (siblingChainReview\\(\\)) >>\nendobj\n"
        . "%%EOF";
};

return [
    'reviews catalog JavaScript name tree actions without executing them' => static function (TestRunner $t) use ($nameTreeJavaScriptPdf): void {
        [$pdf, $streamScript] = $nameTreeJavaScriptPdf();

        $review = (new PdfJavaScriptActionInspector())->reviewDocumentActions($pdf, 24);
        $text = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->true($review['has_javascript']);
        $t->true(!$review['executes_javascript']);
        $t->same(3, $review['action_count']);
        $t->same(['init', 'review', 'stream'], array_column($review['actions'], 'name'));
        $t->same(['document_name_tree', 'document_name_tree', 'document_name_tree'], array_column($review['actions'], 'source'));
        $t->same("app.alert('named import ...", $review['actions'][0]['script_preview']);
        $t->true($review['actions'][0]['script_truncated']);
        $t->same("app.alert('utf-string')", $review['actions'][1]['script_preview']);
        $t->same("app.alert('stream-backed...", $review['actions'][2]['script_preview']);
        $t->same(10, $review['actions'][2]['script_object']);
        $t->same(hash('sha256', $streamScript), $review['actions'][2]['script_sha256']);
        $t->same(strlen($streamScript), $review['actions'][2]['script_bytes']);
        $t->same('Safe visible PDF text', $text);
        $t->true(!str_contains($text, 'app.alert'));
    },
    'reviews catalog open actions, additional actions, page actions, and annotation JavaScript actions' => static function (TestRunner $t) use ($catalogAndAnnotationActionPdf): void {
        $pdf = $catalogAndAnnotationActionPdf();

        $review = (new PdfJavaScriptActionInspector())->reviewDocumentActions($pdf);
        $links = (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf);
        $text = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(6, $review['action_count']);
        $t->same([
            'catalog_open_action',
            'catalog_additional_action',
            'catalog_additional_action',
            'page_additional_action',
            'annotation_action',
            'annotation_additional_action',
        ], array_column($review['actions'], 'source'));
        $t->same(['openReview()', 'willCloseReview()', 'chainedReview()', 'pageOpen()', 'linkClick()', 'fieldFocus()'], array_column($review['actions'], 'script_preview'));
        $t->same(5, $review['actions'][0]['action_object']);
        $t->same('WC', $review['actions'][1]['event']);
        $t->same('WS', $review['actions'][2]['event']);
        $t->same(1, $review['actions'][2]['chain_index']);
        $t->same(0, $review['actions'][3]['page']);
        $t->same(8, $review['actions'][4]['annotation_object']);
        $t->same('Fo', $review['actions'][5]['event']);
        $t->same(10, $review['actions'][5]['annotation_object']);
        $t->same([], $links, 'javascript URI and JavaScript actions stay out of Markdown links.');
        $t->contains('Reviewed import paragraph', $text);
        $t->contains('Second page clean text', $text);
        $t->true(!str_contains($text, 'linkClick()'));
    },
    'returns an empty safety review for documents without JavaScript actions' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OpenAction [3 0 R /Fit] >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [4 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Annot /Subtype /Link /Rect [0 0 100 20] /A << /S /URI /URI (https://example.com/import) >> >>\nendobj\n"
            . "%%EOF";

        $review = (new PdfJavaScriptActionInspector())->reviewDocumentActions($pdf);

        $t->true(!$review['has_javascript']);
        $t->same(0, $review['action_count']);
        $t->same([], $review['actions']);
        $t->same(0, $review['chain_safety']['cycle_edges_blocked']);
    },
    'reviews cyclic JavaScript action chains once without executing or looping' => static function (TestRunner $t) use ($cyclicActionChainPdf): void {
        $pdf = $cyclicActionChainPdf();

        $review = (new PdfJavaScriptActionInspector())->reviewDocumentActions($pdf);
        $text = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->true($review['has_javascript']);
        $t->true(!$review['executes_javascript']);
        $t->same(3, $review['action_count']);
        $t->same(['firstChainReview()', 'deepChainReview()', 'siblingChainReview()'], array_column($review['actions'], 'script_preview'));
        $t->same([1, 3, 1], array_column($review['actions'], 'chain_index'));
        $t->same([true, true, true], array_column($review['actions'], 'chained'));
        $t->same([6, 8, 9], array_column($review['actions'], 'action_object'));
        $t->same(2, $review['chain_safety']['cycle_edges_blocked']);
        $t->same(0, $review['chain_safety']['max_depth_edges_blocked']);
        $t->same('Action chain import body', $text);
        $t->true(!str_contains($text, 'firstChainReview'));
    },
];
