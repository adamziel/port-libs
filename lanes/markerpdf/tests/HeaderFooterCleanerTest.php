<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\HeaderFooterCleaner;

return [
    'does not flag repeated edge lines before upstream three-page minimum' => static function (TestRunner $t): void {
        $cleaner = new HeaderFooterCleaner();
        $pages = [
            ['Migration Export', 'First page body', 'Draft footer'],
            ['Migration Export', 'Second page body', 'Draft footer'],
        ];

        $t->same([], $cleaner->findCommonEdgeLines($pages));
        $t->same($pages, $cleaner->removeCommonEdgeLines($pages));
    },
    'removes common page headers and footers from imported document pages' => static function (TestRunner $t): void {
        $cleaner = new HeaderFooterCleaner();
        $pages = [
            ['WordPress Migration Report', 'Post title one', 'Paragraph one', 'Internal draft'],
            ['WordPress Migration Report', 'Post title two', 'Paragraph two', 'Internal draft'],
            ['WordPress Migration Report', 'Post title three', 'Paragraph three', 'Internal draft'],
            ['WordPress Migration Report', 'Post title four', 'Paragraph four', 'Internal draft'],
        ];

        $t->same(['WordPress Migration Report', 'Internal draft'], $cleaner->findCommonEdgeLines($pages));
        $t->same(
            [
                ['Post title one', 'Paragraph one'],
                ['Post title two', 'Paragraph two'],
                ['Post title three', 'Paragraph three'],
                ['Post title four', 'Paragraph four'],
            ],
            $cleaner->removeCommonEdgeLines($pages)
        );
    },
    'normalizes leading and trailing digits like marker common-title cleanup' => static function (TestRunner $t): void {
        $cleaner = new HeaderFooterCleaner();

        $t->same(' Migration Guide ', $cleaner->replaceLeadingTrailingDigits('12 Migration Guide 34', ''));
        $t->same('PAGE Migration Guide PAGE', $cleaner->replaceLeadingTrailingDigits('12 Migration Guide 34', 'PAGE'));
    },
    'finds repeated title-like blocks only after upstream overlap threshold is met' => static function (TestRunner $t): void {
        $cleaner = new HeaderFooterCleaner();

        $t->same([], $cleaner->findOverlapElements([
            ['Migration Guide', 0],
            ['Migration Guide', 1],
            ['Migration Guide', 2],
        ]));
        $t->same([0, 1, 2, 3], $cleaner->findOverlapElements([
            ['Migration Guide', 0],
            ['Migration Guide', 1],
            ['Migration Guide', 2],
            ['Migration Guide', 3],
        ]));
    },
    'filters repeated numbered titles before WordPress block rendering' => static function (TestRunner $t): void {
        $cleaner = new HeaderFooterCleaner();
        $blocks = [
            ['type' => 'Section-header', 'text' => '# 1 Migration Guide'],
            ['type' => 'Text', 'text' => 'Keep first paragraph.'],
            ['type' => 'Section-header', 'text' => '# 2 Migration Guide'],
            ['type' => 'Text', 'text' => 'Keep second paragraph.'],
            ['type' => 'Section-header', 'text' => '# 3 Migration Guide'],
            ['type' => 'Section-header', 'text' => '# 4 Migration Guide'],
            ['type' => 'Section-header', 'text' => '# Appendix'],
        ];

        $t->same(
            [
                ['type' => 'Text', 'text' => 'Keep first paragraph.'],
                ['type' => 'Text', 'text' => 'Keep second paragraph.'],
                ['type' => 'Section-header', 'text' => '# Appendix'],
            ],
            $cleaner->filterCommonTitles($blocks)
        );
    },
];
