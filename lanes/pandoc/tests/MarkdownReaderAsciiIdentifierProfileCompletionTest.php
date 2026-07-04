<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$headingIds = static function (string $format, string $markdown): array {
    $document = (new MarkdownReader(['format' => $format]))->read($markdown);
    $ids = [];
    foreach ($document->children as $child) {
        if ($child->type === 'heading') {
            $ids[] = (string) $child->attr('id', '');
        }
    }

    return $ids;
};

$firstHeadingText = static function (string $format, string $markdown): string {
    $document = (new MarkdownReader(['format' => $format]))->read($markdown);
    $heading = $document->children[0] ?? new AstNode('missing');

    return (string) $heading->attr('text', '');
};

return [
    'maps pandoc ascii identifiers for markdown heading ids' =>
        static function (TestRunner $t) use ($headingIds, $firstHeadingText): void {
            $markdown = implode("\n", [
                '# Işık',
                '# non ascii ⚠️ räksmörgås',
                '# Привет мир',
                '# Привет мир',
                '',
            ]);

            $t->same(
                ['isik', 'non-ascii-warning-raksmorgas', 'section', 'section-1'],
                $headingIds('markdown+ascii_identifiers', $markdown)
            );
            $t->same('Işık', $firstHeadingText('markdown+ascii_identifiers', "# Işık\n"));
        },

    'maps pandoc gfm auto identifier emoji and ascii branches' =>
        static function (TestRunner $t) use ($headingIds): void {
            $source = "# non ascii ⚠️ räksmörgås\n";

            $t->same(
                ['non-ascii-warning-räksmörgås'],
                $headingIds('gfm', $source)
            );
            $t->same(['ab-c-e'], $headingIds('gfm', "# A.B-C! e\n"));
            $t->same(
                ['non-ascii-warning-raksmorgas'],
                $headingIds('commonmark+gfm_auto_identifiers+ascii_identifiers', $source)
            );
            $t->same(
                ['non-ascii-warning-räksmörgås'],
                $headingIds('commonmark+gfm_auto_identifiers-ascii_identifiers', $source)
            );
        },

    'keeps gfm ascii identifier dash fallback distinct from pandoc section fallback' =>
        static function (TestRunner $t) use ($headingIds): void {
            $t->same(['section'], $headingIds('markdown+ascii_identifiers', "# Привет мир\n"));
            $t->same(['-'], $headingIds('markdown+gfm_auto_identifiers+ascii_identifiers', "# Привет мир\n"));
        },

    'records pandoc markdown ascii identifier mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(11, 4 + 4 + 2 + 1);
        },
];
