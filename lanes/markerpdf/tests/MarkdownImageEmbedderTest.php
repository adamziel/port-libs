<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\ImageExtractor;
use PortLibs\MarkerPDF\MarkdownImageEmbedder;

return [
    'embeds marker app markdown image references as png data uri html' => static function (TestRunner $t): void {
        $embedder = new MarkdownImageEmbedder();
        $markdown = "Intro\n\n![Chart preview](0_image_0.png)\n\nDone";

        $html = $embedder->markdownInsertImages($markdown, ['0_image_0.png' => 'PNG-BYTES']);

        $t->same(
            'Intro' . "\n\n" .
            '<img src="data:image/png;base64,UE5HLUJZVEVT" alt="Chart preview" style="max-width: 100%;">' . "\n\n" .
            'Done',
            $html
        );
    },
    'matches upstream optional markdown title syntax while preserving missing image links' => static function (TestRunner $t): void {
        $embedder = new MarkdownImageEmbedder();
        $markdown = "![Known](page_0.png \"source crop\")\n![Missing](page_1.png)\n![Spaced](image with space.png)";

        $html = $embedder->markdownInsertImages($markdown, ['page_0.png' => ['bytes' => 'KNOWN']]);

        $t->same(
            '<img src="data:image/png;base64,S05PV04=" alt="Known" style="max-width: 100%;">' . "\n" .
            '![Missing](page_1.png)' . "\n" .
            '![Spaced](image with space.png)',
            $html
        );
    },
    'replaces repeated marker image markdown like upstream string replacement' => static function (TestRunner $t): void {
        $embedder = new MarkdownImageEmbedder();
        $markdown = "![Crop](crop.png)\n![Crop](crop.png)";

        $html = $embedder->markdownInsertImages($markdown, ['crop.png' => 'CROP']);

        $t->same(
            '<img src="data:image/png;base64,Q1JPUA==" alt="Crop" style="max-width: 100%;">' . "\n" .
            '<img src="data:image/png;base64,Q1JPUA==" alt="Crop" style="max-width: 100%;">',
            $html
        );
    },
    'rejects image payloads without embeddable native bytes' => static function (TestRunner $t): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => (new MarkdownImageEmbedder())->markdownInsertImages(
                '![Bad](bad.png)',
                ['bad.png' => ['path' => '/tmp/bad.png']]
            )
        );
    },
    'builds a WordPress preview html block from Marker image placeholders' => static function (TestRunner $t): void {
        $page = [
            'pnum' => 3,
            'bbox' => [0.0, 0.0, 600.0, 800.0],
            'layout_boxes' => [
                ['label' => 'Figure', 'bbox' => [72.0, 120.0, 320.0, 260.0]],
            ],
            'blocks' => [
                [
                    'type' => 'Figure',
                    'bbox' => [70.0, 118.0, 322.0, 262.0],
                    'lines' => [
                        [
                            'bbox' => [72.0, 120.0, 320.0, 260.0],
                            'spans' => [['text' => 'chart placeholder', 'span_id' => 'chart_text']],
                        ],
                    ],
                ],
            ],
        ];
        $extractor = new ImageExtractor();
        $page = $extractor->insertImagePlaceholders($page, ['PNG']);
        $images = $extractor->imagesToDict([$page]);
        $markdown = $page['blocks'][0]['lines'][0]['spans'][0]['text'];
        $html = (new MarkdownImageEmbedder())->markdownInsertImages($markdown, $images);

        $t->same(
            "<!-- wp:html -->\n" .
            '<img src="data:image/png;base64,UE5H" alt="3_image_0.png" style="max-width: 100%;">' . "\n" .
            "<!-- /wp:html -->\n",
            "<!-- wp:html -->\n" . trim($html) . "\n<!-- /wp:html -->\n"
        );
    },
];
