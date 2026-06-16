<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$code = static fn (string $value): AstNode => new AstNode('code', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], [$paragraph($children)]);
$citation = static fn (array $attrs): AstNode => new AstNode('citation', $attrs + ['id' => 'doe2026']);

$locatorLabels = [
    'page' => 'p.',
    'article-locator' => 'art.',
    'appendix' => 'app.',
    'book' => 'bk.',
    'canon' => 'c.',
    'chapter' => 'ch.',
    'column' => 'col.',
    'elocation' => 'e-loc.',
    'equation' => 'eq.',
    'figure' => 'fig.',
    'folio' => 'fol.',
    'line' => 'l.',
    'note' => 'n.',
    'opus' => 'op.',
    'paragraph' => 'para.',
    'part' => 'pt.',
    'rule' => 'r.',
    'section' => 'sec.',
    'sub-verbo' => 's.v.',
    'supplement' => 'supp.',
    'table' => 'tbl.',
    'timestamp' => 'ts.',
    'title' => 'tit.',
    'verse' => 'v.',
    'volume' => 'vol.',
];

$modeCases = [
    'normal' => [
        'attrs' => [],
        'expected' => static fn (string $locator): string => '[@doe2026, ' . $locator . ']',
    ],
    'suppress author' => [
        'attrs' => ['mode' => 'suppress_author'],
        'expected' => static fn (string $locator): string => '[-@doe2026, ' . $locator . ']',
    ],
    'author in text' => [
        'attrs' => ['mode' => 'author_in_text'],
        'expected' => static fn (string $locator): string => '@doe2026, ' . $locator,
    ],
];

$cases = [];
foreach ($locatorLabels as $label => $prefix) {
    foreach ($modeCases as $modeName => $modeCase) {
        $locator = $prefix . ' 7';
        $cases[$label . ' ' . $modeName] = [
            'node' => $citation($modeCase['attrs'] + [
                'locatorLabel' => $label,
                'locatorValue' => '7',
            ]),
            'expected' => $modeCase['expected']($locator),
        ];
    }
}

$tests = [
    'records markdown writer citation locator final harvest mapped-case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(75, count($cases));
    },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer citation locator final harvest ' . $label] =
        static function (TestRunner $t) use ($case, $document): void {
            $markdown = (new MarkdownWriter())->write($document([$case['node']]));

            $t->same($case['expected'], $markdown);
        };
}

$tests['uses citation locator alias fields'] =
    static function (TestRunner $t) use ($citation, $document): void {
        $node = $citation([
            'citationLocatorLabel' => 'section',
            'citationLocatorValue' => '2',
        ]);

        $t->same('[@doe2026, sec. 2]', (new MarkdownWriter())->write($document([$node])));
    };

$tests['uses inline locator value markup'] =
    static function (TestRunner $t) use ($citation, $document, $text, $space, $code): void {
        $node = $citation([
            'locatorLabel' => 'chapter',
            'locatorValue' => [$text('appendix'), $space(), $code('A')],
        ]);

        $t->same('[@doe2026, ch. appendix `A`]', (new MarkdownWriter())->write($document([$node])));
    };

$tests['keeps explicit suffix ahead of locator fields'] =
    static function (TestRunner $t) use ($citation, $document): void {
        $node = $citation([
            'mode' => 'author_in_text',
            'suffix' => 'chapter *intro*',
            'locatorLabel' => 'page',
            'locatorValue' => '9',
        ]);

        $t->same('@doe2026 [chapter \*intro\*]', (new MarkdownWriter())->write($document([$node])));
    };

$tests['does not double-prefix already labelled locator text'] =
    static function (TestRunner $t) use ($citation, $document): void {
        $node = $citation([
            'locator' => 'p. 9',
            'locatorLabel' => 'page',
        ]);

        $t->same('[@doe2026, p. 9]', (new MarkdownWriter())->write($document([$node])));
    };

$tests['renders locator citation html fallback when citations disabled'] =
    static function (TestRunner $t) use ($citation, $document): void {
        $node = $citation([
            'locatorLabel' => 'page',
            'locatorValue' => '9',
        ]);

        $markdown = (new MarkdownWriter(['format' => 'gfm']))->write($document([$node]));

        $t->same('<span class="citation" data-cites="doe2026">[@doe2026, p. 9]</span>', $markdown);
    };

$tests['reenables locator citation syntax for profile extension override'] =
    static function (TestRunner $t) use ($citation, $document): void {
        $node = $citation([
            'locatorLabel' => 'page',
            'locatorValue' => '9',
        ]);

        $markdown = (new MarkdownWriter(['format' => 'gfm+citations']))->write($document([$node]));

        $t->same('[@doe2026, p. 9]', $markdown);
    };

$tests['wraps locator citation inside bracketed span without dropping suffix'] =
    static function (TestRunner $t) use ($citation, $document): void {
        $span = new AstNode('span', ['class' => 'source'], [
            $citation([
                'locatorLabel' => 'figure',
                'locatorValue' => '3',
            ]),
        ]);

        $markdown = (new MarkdownWriter())->write($document([$span]));

        $t->same('[[@doe2026, fig. 3]]{.source}', $markdown);
    };

return $tests;
