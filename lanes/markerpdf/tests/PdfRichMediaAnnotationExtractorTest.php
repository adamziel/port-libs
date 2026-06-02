<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfRichMediaAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$richMediaAnnotationPdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Article Body) Tj ET';
    $screenAppearance = 'BT /F1 12 Tf 0 0 Td (Embedded Video Noise) Tj ET';
    $richMediaAppearance = 'BT /F1 12 Tf 0 0 Td (Rich Media Noise) Tj ET';
    $widgetAppearance = 'BT /F1 12 Tf 0 0 Td (Printable Widget Review) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 17 0 R >> >> /Annots [5 0 R 6 0 R 7 0 R 8 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Annot /Subtype /Screen /Rect [72 600 260 700] /T (Training video) /Contents (MP4 launch annotation) /A 12 0 R /AA << /PV 13 0 R >> /AP << /N 9 0 R >> >>\nendobj\n"
        . "6 0 obj\n<< /Type /Annot /Subtype /RichMedia /Rect [72 500 300 590] /Alt (Rich media package) /RichMediaContent 15 0 R /A << /S /URI /URI (https://cdn.example.com/asset.mp4) >> /AP << /N 10 0 R >> >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 680 170 698] /A << /S /URI /URI (https://example.com/docs) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 460 260 490] /AP << /N 11 0 R >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 17 0 R >> >> /Length " . strlen($screenAppearance) . " >>\nstream\n{$screenAppearance}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 17 0 R >> >> /Length " . strlen($richMediaAppearance) . " >>\nstream\n{$richMediaAppearance}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 17 0 R >> >> /Length " . strlen($widgetAppearance) . " >>\nstream\n{$widgetAppearance}\nendstream\nendobj\n"
        . "12 0 obj\n<< /S /Rendition /R << /C 16 0 R /OP 0 >> >>\nendobj\n"
        . "13 0 obj\n<< /S /JavaScript /JS (app.alert\\(\\'do not run\\'\\)) >>\nendobj\n"
        . "15 0 obj\n<< /RichMediaContent << /Assets << /Names [(intro-video.mp4) 16 0 R] >> >> >>\nendobj\n"
        . "16 0 obj\n<< /Type /Filespec /F (intro-video.mp4) /UF <FEFF0069006E00740072006F002D0076006900640065006F002E006D00700034> >>\nendobj\n"
        . "17 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'extracts screen and rich media annotations as review-only metadata' => static function (TestRunner $t) use ($richMediaAnnotationPdf): void {
        $pages = (new PdfRichMediaAnnotationExtractor())->extractReviewAnnotations($richMediaAnnotationPdf());

        $t->same(1, count($pages));
        $t->same(0, $pages[0]['pnum']);
        $t->same(3, $pages[0]['page_object']);
        $t->same(['Screen', 'RichMedia'], array_column($pages[0]['annotations'], 'subtype'));

        $screen = $pages[0]['annotations'][0];
        $t->same(5, $screen['annotation_object']);
        $t->same([72.0, 600.0, 260.0, 700.0], $screen['rect']);
        $t->same('Training video', $screen['title']);
        $t->same('MP4 launch annotation', $screen['contents']);
        $t->same(['Rendition', 'JavaScript'], $screen['action_types']);
        $t->same(['intro-video.mp4'], $screen['file_names']);
        $t->same(false, $screen['executes_media']);
        $t->same(false, $screen['executes_javascript']);
        $t->same(true, $screen['requires_review']);

        $richMedia = $pages[0]['annotations'][1];
        $t->same('RichMedia', $richMedia['subtype']);
        $t->same('Rich media package', $richMedia['alternate_text']);
        $t->same(['URI'], $richMedia['action_types']);
        $t->same(['https://cdn.example.com/asset.mp4'], $richMedia['action_uris']);
        $t->same(['intro-video.mp4'], $richMedia['asset_names']);
        $t->same(['intro-video.mp4'], $richMedia['file_names']);
        $t->same(false, $richMedia['executes_media']);
        $t->same(false, $richMedia['executes_javascript']);
    },
    'keeps rich media and screen appearances out of native text extraction' => static function (TestRunner $t) use ($richMediaAnnotationPdf): void {
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($richMediaAnnotationPdf());

        $t->same(['Article Body', 'Printable Widget Review'], $extractor->extractTextLines($richMediaAnnotationPdf()));
        $t->true(!str_contains($plainText, 'Embedded Video Noise'));
        $t->true(!str_contains($plainText, 'Rich Media Noise'));
        $t->true(str_contains($plainText, 'Printable Widget Review'));
    },
    'does not promote screen or rich media URI actions to Markdown links' => static function (TestRunner $t) use ($richMediaAnnotationPdf): void {
        $pages = [[
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 680.0, 170.0, 698.0],
                'lines' => [[
                    'bbox' => [72.0, 680.0, 170.0, 698.0],
                    'spans' => [[
                        'text' => 'Article docs',
                        'bbox' => [72.0, 680.0, 170.0, 698.0],
                        'font' => 'Helvetica',
                    ]],
                ]],
            ]],
        ]];

        $linked = (new PdfLinkAnnotationExtractor())->applyLinksToPages($pages, $richMediaAnnotationPdf());

        $t->same(1, count($linked[0]['links']));
        $t->same('https://example.com/docs', $linked[0]['links'][0]['uri']);
        $t->same('https://example.com/docs', $linked[0]['blocks'][0]['lines'][0]['spans'][0]['link_uri']);
    },
];
