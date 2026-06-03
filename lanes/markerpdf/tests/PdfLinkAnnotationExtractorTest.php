<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Plugin docs) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Second page support) Tj ET';
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [7 0 R << /Type /Annot /Subtype /Text /Rect [72 700 160 718] /Contents (review note) >> 10 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Annots 11 0 R /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 160 718] /A << /S /URI /URI (https://example.com/plugin\\)docs) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 200 718] /A << /S /URI /URI (https://example.com/unreferenced) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 200 718] /Dest [4 0 R /Fit] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [200 700 260 718] /A << /S /URI /URI (javascript:alert\\(1\\)) >> >>\nendobj\n"
        . "11 0 obj\n[ << /Type /Annot /Subtype /Link /Rect [72 680 220 698] /A 12 0 R >> 9 0 R ]\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI <6d61696c746f3a696d706f7274406578616d706c652e636f6d> >>\nendobj\n"
        . "%%EOF";
};

$suppliedPages = static function (): array {
    return [
        [
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 260.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 260.0, 718.0],
                    'spans' => [
                        ['text' => 'Plugin docs', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                        ['text' => ' outside', 'bbox' => [170.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
        [
            'pnum' => 1,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 680.0, 250.0, 698.0],
                'lines' => [[
                    'bbox' => [72.0, 680.0, 250.0, 698.0],
                    'spans' => [
                        ['text' => 'Second page support', 'bbox' => [72.0, 680.0, 220.0, 698.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
    ];
};

$destinationBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Review jump and docs link) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Destination page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 13 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [7 0 R 8 0 R << /Type /Annot /Subtype /Text /Rect [250 700 320 718] /Contents (sticky only) >>] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [9 0 R] /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 155 718] /Dest [4 0 R /FitH 720] /AA << /E << /S /URI /URI (https://example.com/hover) >> /D 12 0 R >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 238 718] /A << /S /URI /URI (https://example.com/docs) /Next << /S /GoTo /D (named-review) >> >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 180 718] /A << /S /GoTo /D (named-review) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 640 180 658] /Dest [3 0 R /Fit] /AA << /E << /S /JavaScript /JS (staleHover\\(\\)) >> >> >>\nendobj\n"
        . "11 0 obj\n[4 0 R /XYZ 36 700 0]\nendobj\n"
        . "12 0 obj\n<< /S /JavaScript /JS (linkDownReview\\(\\)) >>\nendobj\n"
        . "13 0 obj\n<< /Names [(named-review) 11 0 R] >>\nendobj\n"
        . "%%EOF";
};

$destinationBoundaryPages = static function (): array {
    return [
        [
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 238.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 238.0, 718.0],
                    'spans' => [
                        ['text' => 'Review jump', 'bbox' => [72.0, 700.0, 155.0, 718.0], 'font' => 'Helvetica'],
                        ['text' => ' docs link', 'bbox' => [160.0, 700.0, 238.0, 718.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
        [
            'pnum' => 1,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 180.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 180.0, 718.0],
                    'spans' => [
                        ['text' => 'Destination page', 'bbox' => [72.0, 700.0, 180.0, 718.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
    ];
};

$widgetLinkPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Download docs Section jump Hidden widget) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Widget destination target) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 15 0 R /Names << /Dests 16 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [7 0 R 8 0 R 9 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 700 160 718] /P 3 0 R /F 4 /Parent 20 0 R /A 30 0 R /AA << /U 31 0 R >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [170 700 260 718] /P 3 0 R /F 4 /Parent 21 0 R /Dest [4 0 R /FitH 720] >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [270 700 340 718] /P 3 0 R /F 36 /Parent 22 0 R /A << /S /URI /URI (https://example.com/hidden-widget) >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 650 160 668] /Parent 22 0 R /A << /S /URI /URI (https://example.com/detached-widget) >> >>\nendobj\n"
        . "15 0 obj\n<< /Fields [20 0 R 21 0 R 22 0 R] >>\nendobj\n"
        . "16 0 obj\n<< /Names [(target-section) 17 0 R] >>\nendobj\n"
        . "17 0 obj\n[4 0 R /XYZ 36 700 0]\nendobj\n"
        . "20 0 obj\n<< /FT /Btn /T (download.docs) /Ff 65536 /Kids [7 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /FT /Btn /T (section.jump) /Ff 65536 /Kids [8 0 R] >>\nendobj\n"
        . "22 0 obj\n<< /FT /Btn /T (hidden.widget) /Ff 65536 /Kids [9 0 R 10 0 R] >>\nendobj\n"
        . "30 0 obj\n<< /S /URI /URI (https://example.com/widget-docs) /Next << /S /GoTo /D (target-section) >> >>\nendobj\n"
        . "31 0 obj\n<< /S /URI /URI (mailto:review@example.com) >>\nendobj\n"
        . "%%EOF";
};

$widgetLinkPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 340.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 340.0, 718.0],
                'spans' => [
                    ['text' => 'Download docs', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Section jump', 'bbox' => [170.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Hidden widget', 'bbox' => [270.0, 700.0, 340.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

$xmpOutlineLinkPdf = static function (): string {
    $xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Link XMP Review Title</rdf:li></rdf:Alt></dc:title>'
        . '<xmp:CreateDate>2026-06-02T20:27:13-04:00</xmp:CreateDate>'
        . '<xmp:ModifyDate>2026-06-02T21:28:14+02:00</xmp:ModifyDate>'
        . '<xmp:MetadataDate>2026-06-02T22:29:15Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
    $compressedXmp = gzcompress($xmp);
    if (!is_string($compressedXmp)) {
        throw new RuntimeException('Unable to compress XMP outline-link fixture.');
    }

    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Jump to chapter) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Chapter target body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 9 0 R >> /PageLabels 10 0 R /Metadata 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [7 0 R] /Contents 11 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 6 /Trans 12 0 R /AA << /O 13 0 R >> /Contents 14 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Chapter One Outline) /Parent 5 0 R /Dest /chapter-one >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 180 718] /Dest /chapter-one /AA << /E 15 0 R >> >>\nendobj\n"
        . "9 0 obj\n<< /Names [(chapter-one) [4 0 R /FitH 700]] >>\nendobj\n"
        . "10 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Chapter ) /St 4 >>] >>\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "12 0 obj\n<< /S /Wipe /D .75 /Dm /H /M /O /Di 180 >>\nendobj\n"
        . "13 0 obj\n<< /S /URI /URI (https://example.com/chapter-open-review) >>\nendobj\n"
        . "14 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "15 0 obj\n<< /S /JavaScript /JS (hoverImportReview\\(\\)) >>\nendobj\n"
        . "20 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

$xmpOutlineLinkPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 180.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 180.0, 718.0],
                'spans' => [
                    ['text' => 'Jump to chapter', 'bbox' => [72.0, 700.0, 180.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

$rotatedUserUnitLinkPdf = static function (): string {
    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /MediaBox [10 20 210 320] /CropBox [20 40 180 240] /Rotate 90 /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /UserUnit 9 0 R /Annots [7 0 R] >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [30 150 110 170] /A << /S /URI /URI (https://example.com/rotated-link) >> >>\nendobj\n"
        . "9 0 obj\n2\nendobj\n"
        . "%%EOF";
};

$rotatedUserUnitLinkPages = static function (): array {
    return [[
        'pnum' => 0,
        'bbox' => [0.0, 0.0, 400.0, 320.0],
        'rotation' => 90,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [220.0, 20.0, 260.0, 180.0],
            'lines' => [[
                'bbox' => [220.0, 20.0, 260.0, 180.0],
                'spans' => [
                    ['text' => 'Rotated link', 'bbox' => [220.0, 20.0, 260.0, 180.0], 'font' => 'Helvetica'],
                    ['text' => ' raw decoy', 'bbox' => [30.0, 150.0, 110.0, 170.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'extracts page link URI annotations at the native PDF page boundary' => static function (TestRunner $t) use ($linkPdf): void {
        $links = (new PdfLinkAnnotationExtractor())->extractPageLinks($linkPdf());

        $t->same(2, count($links));
        $t->same(0, $links[0]['pnum']);
        $t->same(3, $links[0]['page_object']);
        $t->same('https://example.com/plugin)docs', $links[0]['links'][0]['uri']);
        $t->same([72.0, 700.0, 160.0, 718.0], $links[0]['links'][0]['rect']);
        $t->same(7, $links[0]['links'][0]['annotation_object']);
        $t->same(1, $links[1]['pnum']);
        $t->same('mailto:import@example.com', $links[1]['links'][0]['uri']);
        $t->same(1, count($links[0]['links']), 'non-link, unsafe, and unreferenced annotations are excluded.');
        $t->same(2, count($links[1]['links']), 'GoTo destination annotations are retained as review-only local link metadata.');
        $t->same('local-destination', $links[1]['links'][1]['safety']);
        $t->same(1, $links[1]['links'][1]['destination_page']);
    },
    'applies URI annotations only to overlapping supplied pdftext spans' => static function (TestRunner $t) use ($linkPdf, $suppliedPages): void {
        $pages = (new PdfLinkAnnotationExtractor())->applyLinksToPages($suppliedPages(), $linkPdf());

        $t->same('https://example.com/plugin)docs', $pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_uri']);
        $t->same([72.0, 700.0, 160.0, 718.0], $pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_rect']);
        $t->true(!isset($pages[0]['blocks'][0]['lines'][0]['spans'][1]['link_uri']));
        $t->same('mailto:import@example.com', $pages[1]['blocks'][0]['lines'][0]['spans'][0]['link_uri']);
        $t->same(1, count($pages[0]['links']));
        $t->same(2, count($pages[1]['links']));
    },
    'renders linked spans as Markdown links before WordPress block conversion' => static function (TestRunner $t) use ($linkPdf, $suppliedPages): void {
        $linked = (new PdfLinkAnnotationExtractor())->applyLinksToPages($suppliedPages(), $linkPdf());
        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($linked));

        $t->same('[Plugin docs](https://example.com/plugin\\)docs) outside [Second page support](mailto:import@example.com)', $blocks[0]['text']);

        $html = "<!-- wp:paragraph -->\n<p>" . preg_replace('/\[([^]]+)\]\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)/', '<a href="$2">$1</a>', $blocks[0]['text']) . "</p>\n<!-- /wp:paragraph -->\n";
        $t->contains('<a href="https://example.com/plugin\\)docs">Plugin docs</a>', $html);
    },
    'keeps pages without link annotations unchanged' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n%%EOF";
        $pages = [[
            'pnum' => 0,
            'blocks' => [[
                'lines' => [[
                    'spans' => [['text' => 'Plain import text', 'bbox' => [10.0, 20.0, 100.0, 32.0]]],
                ]],
            ]],
        ]];

        $linked = (new PdfLinkAnnotationExtractor())->applyLinksToPages($pages, $pdf);

        $t->same($pages, $linked);
    },
    'reviews link destinations and annotation additional actions without executing them' => static function (TestRunner $t) use ($destinationBoundaryPdf, $destinationBoundaryPages): void {
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($destinationBoundaryPdf());

        $t->same(2, count($links));
        $t->same(2, count($links[0]['links']));
        $t->same(1, count($links[1]['links']));

        $local = $links[0]['links'][0];
        $t->same('GoTo', $local['action_type']);
        $t->same('local-destination', $local['safety']);
        $t->same(1, $local['destination_page']);
        $t->same('FitH', $local['view_mode']);
        $t->same(['top' => 720.0], $local['view_parameters']);
        $t->same(null, $local['uri']);
        $t->same(false, $local['executes_on_import']);
        $t->same(['E', 'D'], array_column($local['additional_actions'], 'event'));
        $t->same(['review-uri', 'blocked-javascript'], array_column($local['additional_actions'], 'safety'));

        $uri = $links[0]['links'][1];
        $t->same('https://example.com/docs', $uri['uri']);
        $t->same(['review-uri', 'local-destination'], array_column($uri['actions'], 'safety'));
        $t->same(true, $uri['actions'][1]['chained']);
        $t->same('named-review', $uri['actions'][1]['destination']);
        $t->same('XYZ', $uri['actions'][1]['view_mode']);
        $t->same(['left' => 36.0, 'top' => 700.0, 'zoom' => null], $uri['actions'][1]['view_parameters']);

        $named = $links[1]['links'][0];
        $t->same('named-review', $named['destination']);
        $t->same(1, $named['destination_page']);
        $t->same('XYZ', $named['view_mode']);

        $pages = $extractor->applyLinksToPages($destinationBoundaryPages(), $destinationBoundaryPdf());
        $t->same(1, $pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_destination_page']);
        $t->same('FitH', $pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_view_mode']);
        $t->true(!isset($pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_uri']));
        $t->same('https://example.com/docs', $pages[0]['blocks'][0]['lines'][0]['spans'][1]['link_uri']);
        $t->same('named-review', $pages[0]['blocks'][0]['lines'][0]['spans'][1]['link_actions_review'][1]['destination']);
        $t->same('named-review', $pages[1]['blocks'][0]['lines'][0]['spans'][0]['link_destination']);
        $t->same(3, array_sum(array_map(static fn (array $page): int => count($page['links'] ?? []), $pages)), 'unreferenced stale destination annotation is excluded.');
    },
    'promotes current page widget URI and destination annotations as non executing links' => static function (TestRunner $t) use ($widgetLinkPdf, $widgetLinkPages): void {
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($widgetLinkPdf());

        $t->same(1, count($links));
        $t->same(0, $links[0]['pnum']);
        $t->same(3, $links[0]['page_object']);
        $t->same(2, count($links[0]['links']), 'hidden current widgets and detached field-only widgets are not promoted.');

        $uriWidget = $links[0]['links'][0];
        $t->same('Widget', $uriWidget['annotation_subtype']);
        $t->true($uriWidget['widget_annotation']);
        $t->same(7, $uriWidget['annotation_object']);
        $t->same('https://example.com/widget-docs', $uriWidget['uri']);
        $t->same('review-uri', $uriWidget['safety']);
        $t->same(false, $uriWidget['executes_on_import']);
        $t->same(['review-uri', 'local-destination'], array_column($uriWidget['actions'], 'safety'));
        $t->same('target-section', $uriWidget['actions'][1]['destination']);
        $t->same(1, $uriWidget['actions'][1]['destination_page']);
        $t->same(['U'], array_column($uriWidget['additional_actions'], 'event'));
        $t->same(['review-uri'], array_column($uriWidget['additional_actions'], 'safety'));
        $t->same('mailto:review@example.com', $uriWidget['additional_actions'][0]['uri']);

        $destinationWidget = $links[0]['links'][1];
        $t->same('Widget', $destinationWidget['annotation_subtype']);
        $t->true($destinationWidget['widget_annotation']);
        $t->same('GoTo', $destinationWidget['action_type']);
        $t->same('local-destination', $destinationWidget['safety']);
        $t->same(1, $destinationWidget['destination_page']);
        $t->same('FitH', $destinationWidget['view_mode']);
        $t->same(['top' => 720.0], $destinationWidget['view_parameters']);

        $pages = $extractor->applyLinksToPages($widgetLinkPages(), $widgetLinkPdf());
        $t->same('https://example.com/widget-docs', $pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_uri']);
        $t->same('Widget', $pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_annotation_subtype']);
        $t->true($pages[0]['blocks'][0]['lines'][0]['spans'][0]['link_widget_annotation']);
        $t->same('FitH', $pages[0]['blocks'][0]['lines'][0]['spans'][1]['link_view_mode']);
        $t->same(1, $pages[0]['blocks'][0]['lines'][0]['spans'][1]['link_destination_page']);
        $t->true(!isset($pages[0]['blocks'][0]['lines'][0]['spans'][1]['link_uri']));
        $t->true(!isset($pages[0]['blocks'][0]['lines'][0]['spans'][2]['link_uri']));
        $t->true(!isset($pages[0]['blocks'][0]['lines'][0]['spans'][2]['link_destination_page']));

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($pages));
        $t->same('[Download docs](https://example.com/widget-docs) Section jump Hidden widget', $blocks[0]['text']);
    },
    'propagates XMP date and outline target context onto local link annotations' => static function (TestRunner $t) use ($xmpOutlineLinkPdf, $xmpOutlineLinkPages): void {
        $pdf = $xmpOutlineLinkPdf();
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);

        $t->same(1, count($links));
        $t->same(1, count($links[0]['links']));

        $link = $links[0]['links'][0];
        $t->same('local-destination', $link['safety']);
        $t->same('chapter-one', $link['destination']);
        $t->same(1, $link['destination_page']);
        $t->same('Chapter 4', $link['destination_page_label']);
        $t->same(['Chapter One Outline'], $link['target_outline_titles']);
        $t->same([1], $link['target_outline_levels']);
        $t->same('Wipe', $link['target_page_transition']['style']);
        $t->same(0.75, $link['target_page_transition']['duration']);
        $t->same(6.0, $link['target_display_duration']);
        $t->same(['page_open'], array_column($link['target_page_actions'], 'event_label'));
        $t->same(['review-uri'], array_column($link['target_page_actions'], 'safety'));
        $t->same('2026-06-02T20:27:13-04:00', $link['document_metadata_dates']['created_at']);
        $t->same('2026-06-03T00:27:13Z', $link['document_metadata_dates']['created_at_utc']);
        $t->same('2026-06-02T21:28:14+02:00', $link['document_metadata_dates']['modified_at']);
        $t->same('2026-06-02T19:28:14Z', $link['document_metadata_dates']['modified_at_utc']);
        $t->same('2026-06-02T22:29:15Z', $link['document_metadata_dates']['metadata_date_utc']);
        $t->same($link['document_metadata_dates'], $link['actions'][0]['document_metadata_dates']);

        $pages = $extractor->applyLinksToPages($xmpOutlineLinkPages(), $pdf);
        $span = $pages[0]['blocks'][0]['lines'][0]['spans'][0];
        $t->same('chapter-one', $span['link_destination']);
        $t->same('Chapter 4', $span['link_destination_page_label']);
        $t->same(['Chapter One Outline'], $span['link_target_outline_titles']);
        $t->same('Wipe', $span['link_target_page_transition']['style']);
        $t->same('2026-06-03T00:27:13Z', $span['link_document_metadata_dates']['created_at_utc']);
        $t->true(!isset($span['link_uri']));

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Jump to chapter', $plainText);
        $t->contains('Chapter target body', $plainText);
        $t->true(!str_contains($plainText, 'Link XMP Review Title'));
        $t->true(!str_contains($plainText, 'chapter-open-review'));
        $t->true(!str_contains($plainText, 'hoverImportReview'));
    },
    'maps rotated link annotation rectangles through page boxes before applying supplied pdftext spans' => static function (TestRunner $t) use ($rotatedUserUnitLinkPdf, $rotatedUserUnitLinkPages): void {
        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($rotatedUserUnitLinkPdf());

        $t->same(1, count($links));
        $link = $links[0]['links'][0];
        $t->same([30.0, 150.0, 110.0, 170.0], $link['rect']);
        $t->same([220.0, 20.0, 260.0, 180.0], $link['pdftext_rect']);
        $t->same([20.0, 40.0, 180.0, 240.0], $link['page_bbox']);
        $t->same(90, $link['page_rotation']);
        $t->same(2.0, $link['page_user_unit']);
        $t->same([0.0, 0.0, 400.0, 320.0], $link['display_page_bbox']);

        $pages = $extractor->applyLinksToPages($rotatedUserUnitLinkPages(), $rotatedUserUnitLinkPdf());
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];

        $t->same('https://example.com/rotated-link', $spans[0]['link_uri']);
        $t->same([220.0, 20.0, 260.0, 180.0], $spans[0]['link_rect']);
        $t->same('marker_pdftext_display', $spans[0]['link_rect_coordinate_space']);
        $t->same([30.0, 150.0, 110.0, 170.0], $spans[0]['link_page_rect']);
        $t->same([220.0, 20.0, 260.0, 180.0], $spans[0]['link_pdftext_rect']);
        $t->true(!isset($spans[1]['link_uri']), 'raw page-space decoy is not linked on a rotated pdftext page.');

        $processor = new MarkdownPostProcessor();
        $blocks = $processor->mergeBlocks($processor->mergeSpans($pages));
        $t->same('[Rotated link](https://example.com/rotated-link) raw decoy', $blocks[0]['text']);
    },
];
