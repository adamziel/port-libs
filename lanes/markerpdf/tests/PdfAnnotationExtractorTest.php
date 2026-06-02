<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;

$annotationPdf = static function (): string {
    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [5 0 R 8 0 R 9 0 R << /Type /Annot /Subtype /FreeText /Rect [72 620 260 646] /Contents (Inline free text) /C [0.25] /IC [0 1 0] /CA -0.2 /BS << /W 1 /S /U >> >>] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Annots 12 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Annot /Subtype /Text /Rect [220 700 72 680] /Contents (Review note) /T <FEFF0045006400690074006F0072> /NM /note#2D1 /M (D:20260602043000Z) /C [1 0.5 0] /CA .65 /Border [0 0 2 [3 1]] /Popup 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Annot /Subtype /Popup /Rect [240 640 420 720] /Parent 5 0 R /Open true >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [72 660 210 684] /Contents (Highlighted import) /C [0 0.25 1] /CA 1.4 /BS << /W 4 /S /D /D [2 2] >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Popup /Rect [220 648 420 704] /Parent 8 0 R /Open false >>\nendobj\n"
        . "12 0 obj\n[13 0 R 14 0 R]\nendobj\n"
        . "13 0 obj\n<< /Type /Annot /Subtype /Square /Rect [80 600 160 660] /Contents (CMYK border) /C [0.1 0.2 0.3 0.4] /Border [0 0 0] >>\nendobj\n"
        . "14 0 obj\n<< /Type /Annot /Subtype /Text /Rect [180 600 220 640] /Contents (No color) /C [] /Popup << /Type /Annot /Subtype /Popup /Rect [200 590 320 650] /Open true >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'extracts page annotation border color opacity and popup metadata' => static function (TestRunner $t) use ($annotationPdf): void {
        $pages = (new PdfAnnotationExtractor())->extractPageAnnotations($annotationPdf());

        $t->same(2, count($pages));
        $t->same(0, $pages[0]['pnum']);
        $t->same(3, $pages[0]['page_object']);
        $t->same(3, count($pages[0]['annotations']), 'reverse-linked Popup annotations are nested instead of duplicated.');

        $note = $pages[0]['annotations'][0];
        $t->same('Text', $note['subtype']);
        $t->same(5, $note['annotation_object']);
        $t->same([72.0, 680.0, 220.0, 700.0], $note['rect']);
        $t->same('Review note', $note['contents']);
        $t->same('Editor', $note['title']);
        $t->same('note-1', $note['name']);
        $t->same('D:20260602043000Z', $note['modified_at']);
        $t->same(0.65, $note['opacity']);
        $t->same('DeviceRGB', $note['border_color']['space']);
        $t->same([1.0, 0.5, 0.0], $note['border_color']['components']);
        $t->same('#ff8000', $note['border_color']['hex']);
        $t->same('Border', $note['border']['source']);
        $t->same(2.0, $note['border']['width']);
        $t->same('dashed', $note['border']['style']);
        $t->same([3.0, 1.0], $note['border']['dash_pattern']);
        $t->same(6, $note['popup']['object']);
        $t->same([240.0, 640.0, 420.0, 720.0], $note['popup']['rect']);
        $t->same(true, $note['popup']['open']);
        $t->same(5, $note['popup']['parent_object']);
    },
    'prefers BS border dictionaries and reverse-linked popup annotations' => static function (TestRunner $t) use ($annotationPdf): void {
        $highlight = (new PdfAnnotationExtractor())->extractPageAnnotations($annotationPdf())[0]['annotations'][1];

        $t->same('Highlight', $highlight['subtype']);
        $t->same(8, $highlight['annotation_object']);
        $t->same(1.0, $highlight['opacity'], 'opacity metadata is clamped for review UI consumers.');
        $t->same('DeviceRGB', $highlight['border_color']['space']);
        $t->same('#0040ff', $highlight['border_color']['hex']);
        $t->same('BS', $highlight['border']['source']);
        $t->same(4.0, $highlight['border']['width']);
        $t->same('dashed', $highlight['border']['style']);
        $t->same([2.0, 2.0], $highlight['border']['dash_pattern']);
        $t->same(9, $highlight['popup']['object']);
        $t->same(false, $highlight['popup']['open']);
        $t->same(8, $highlight['popup']['parent_object']);
    },
    'decodes direct annotations grayscale interior color and underline border style' => static function (TestRunner $t) use ($annotationPdf): void {
        $direct = (new PdfAnnotationExtractor())->extractPageAnnotations($annotationPdf())[0]['annotations'][2];

        $t->same(null, $direct['annotation_object']);
        $t->same('FreeText', $direct['subtype']);
        $t->same('Inline free text', $direct['contents']);
        $t->same(0.0, $direct['opacity']);
        $t->same('DeviceGray', $direct['border_color']['space']);
        $t->same([0.25], $direct['border_color']['components']);
        $t->same('#404040', $direct['border_color']['hex']);
        $t->same('DeviceRGB', $direct['interior_color']['space']);
        $t->same('#00ff00', $direct['interior_color']['hex']);
        $t->same('underline', $direct['border']['style']);
        $t->same(null, $direct['popup']);
    },
    'handles indirect annotation arrays transparent colors and direct popup dictionaries' => static function (TestRunner $t) use ($annotationPdf): void {
        $page = (new PdfAnnotationExtractor())->extractPageAnnotations($annotationPdf())[1];

        $t->same(1, $page['pnum']);
        $t->same(4, $page['page_object']);
        $t->same(2, count($page['annotations']));

        $square = $page['annotations'][0];
        $t->same('Square', $square['subtype']);
        $t->same('DeviceCMYK', $square['border_color']['space']);
        $t->same([0.1, 0.2, 0.3, 0.4], $square['border_color']['components']);
        $t->same('#8a7a6b', $square['border_color']['hex']);
        $t->same(0.0, $square['border']['width']);
        $t->same('none', $square['border']['style']);

        $transparent = $page['annotations'][1];
        $t->same('transparent', $transparent['border_color']['space']);
        $t->same([], $transparent['border_color']['components']);
        $t->same(null, $transparent['border_color']['hex']);
        $t->same(null, $transparent['popup']['object']);
        $t->same([200.0, 590.0, 320.0, 650.0], $transparent['popup']['rect']);
        $t->same(true, $transparent['popup']['open']);
    },
];
