<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\HeadingCleaner;

$outlineDocument = static function (): object {
    return new class {
        public int $maxDepth = 0;

        /**
         * @return list<object|array<string, mixed>>
         */
        public function get_toc(int $max_depth = 15): array
        {
            $this->maxDepth = $max_depth;

            return [
                (object) ['title' => 'Migration Runbook', 'level' => 1, 'page_index' => 0],
                ['title' => 'Media Cleanup', 'level' => 2, 'page_index' => 4],
            ];
        }
    };
};

return [
    'splits heading lines out of text blocks using upstream bbox overlap threshold' => static function (TestRunner $t): void {
        $cleaner = new HeadingCleaner();
        $pages = [
            [
                'pnum' => 0,
                'layout_boxes' => [
                    ['label' => 'Section-header', 'bbox' => [48.0, 29.0, 282.0, 49.0]],
                ],
                'blocks' => [
                    [
                        'type' => 'Text',
                        'lines' => [
                            ['text' => 'Introductory paragraph.', 'bbox' => [50.0, 10.0, 300.0, 22.0]],
                            ['text' => 'migration overview', 'bbox' => [50.0, 30.0, 280.0, 48.0]],
                            ['text' => 'Body after the heading.', 'bbox' => [50.0, 58.0, 320.0, 70.0]],
                        ],
                    ],
                ],
            ],
        ];

        $split = $cleaner->splitHeadingBlocks($pages);

        $t->same(['Text', 'Section-header', 'Text'], array_column($split[0]['blocks'], 'type'));
        $t->same('migration overview', $split[0]['blocks'][1]['lines'][0]['text']);
        $t->same([50.0, 30.0, 280.0, 48.0], $split[0]['blocks'][1]['bbox']);
    },
    'keeps heading levels at upstream default when too few heading heights can be bucketed' => static function (TestRunner $t): void {
        $cleaner = new HeadingCleaner();
        $pages = [
            [
                'pnum' => 0,
                'blocks' => [
                    ['type' => 'Title', 'lines' => [['text' => 'Migration Plan', 'height' => 36.0]]],
                    ['type' => 'Section-header', 'lines' => [['text' => 'Import Steps', 'height' => 20.0]]],
                ],
            ],
        ];

        $inferred = $cleaner->inferHeadingLevels($pages);

        $t->same(2, $inferred[0]['blocks'][0]['heading_level']);
        $t->same(2, $inferred[0]['blocks'][1]['heading_level']);
    },
    'infers larger heading heights as lower markdown heading levels' => static function (TestRunner $t): void {
        $cleaner = new HeadingCleaner();
        $pages = [
            [
                'pnum' => 0,
                'blocks' => [
                    ['type' => 'Title', 'lines' => [['text' => 'Migration Plan', 'height' => 37.0], ['text' => '2026', 'height' => 36.0]]],
                    ['type' => 'Section-header', 'lines' => [['text' => 'Export', 'height' => 24.0], ['text' => 'Inventory', 'height' => 24.0]]],
                    ['type' => 'Section-header', 'lines' => [['text' => 'Media', 'height' => 18.0], ['text' => 'Cleanup', 'height' => 18.0]]],
                    ['type' => 'Section-header', 'lines' => [['text' => 'Notes', 'height' => 12.0], ['text' => 'Archive', 'height' => 12.0]]],
                    ['type' => 'Text', 'lines' => [['text' => 'Normal content', 'height' => 12.0]]],
                ],
            ],
        ];

        $ranges = $cleaner->bucketHeadings([37, 36, 24, 24, 18, 18, 12, 12]);
        $inferred = $cleaner->inferHeadingLevels($pages);

        $t->same([[36.0, 37.0], [24.0, 24.0], [18.0, 18.0], [12.0, 12.0]], $ranges);
        $t->same([1, 2, 3, 4], array_column(array_slice($inferred[0]['blocks'], 0, 4), 'heading_level'));
        $t->true(!isset($inferred[0]['blocks'][4]['heading_level']));
    },
    'computes a WordPress import table of contents from heading blocks' => static function (TestRunner $t): void {
        $cleaner = new HeadingCleaner();
        $pages = [
            [
                'pnum' => 0,
                'blocks' => [
                    ['type' => 'Title', 'heading_level' => 1, 'lines' => [['spans' => [['text' => 'Migration Plan']]]]],
                    ['type' => 'Text', 'text' => 'Intro paragraph.'],
                ],
            ],
            [
                'pnum' => 2,
                'blocks' => [
                    ['type' => 'Section-header', 'heading_level' => 2, 'prelim_text' => 'Media Cleanup'],
                ],
            ],
        ];

        $t->same(
            [
                ['title' => 'Migration Plan', 'level' => 1, 'page' => 0],
                ['title' => 'Media Cleanup', 'level' => 2, 'page' => 2],
            ],
            $cleaner->computeToc($pages)
        );
    },
    'maps upstream pdf outline items from get_toc with max depth' => static function (TestRunner $t) use ($outlineDocument): void {
        $doc = $outlineDocument();
        $toc = (new HeadingCleaner())->getPdfToc($doc, 7);

        $t->same(7, $doc->maxDepth);
        $t->same(
            [
                ['title' => 'Migration Runbook', 'level' => 1, 'page' => 0],
                ['title' => 'Media Cleanup', 'level' => 2, 'page' => 4],
            ],
            $toc
        );
    },
    'rejects malformed pdf outline adapters before WordPress TOC import' => static function (TestRunner $t): void {
        $cleaner = new HeadingCleaner();

        $t->throws(InvalidArgumentException::class, static fn () => $cleaner->getPdfToc(new stdClass()));
        $t->throws(InvalidArgumentException::class, static fn () => $cleaner->getPdfToc(new class {
            public function get_toc(int $max_depth = 15): array
            {
                return [(object) ['title' => 'Missing page', 'level' => 1]];
            }
        }));
    },
];
