<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$linebreak = static fn (): AstNode => new AstNode('linebreak');
$strong = static fn (string $value): AstNode => new AstNode('strong', [], [$text($value)]);
$emph = static fn (string $value): AstNode => new AstNode('emph', [], [$text($value)]);
$code = static fn (string $value): AstNode => new AstNode('code', ['text' => $value]);
$paragraph = static fn (string|array $value): AstNode => new AstNode(
    'paragraph',
    [],
    is_array($value) ? $value : [$text($value)]
);
$plain = static fn (string $value): AstNode => new AstNode('plain', [], [$text($value)]);
$heading = static fn (string $value, int $level = 2): AstNode => new AstNode('heading', ['level' => $level], [$text($value)]);
$codeBlock = static fn (string $value): AstNode => new AstNode('code_block', ['text' => $value]);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);
$line = static fn (string $value = ''): AstNode => $value === ''
    ? new AstNode('line')
    : new AstNode('line', [], [$text($value)]);
$lineBlock = static fn (array $lines): AstNode => new AstNode('line_block', [], $lines);
$div = static fn (array $children): AstNode => new AstNode('div', [], $children);
$listItem = static fn (array $children): AstNode => new AstNode('list_item', [], $children);
$bulletList = static fn (array $items): AstNode => new AstNode('bullet_list', [], $items);
$orderedList = static fn (array $items): AstNode => new AstNode('ordered_list', [], $items);
$definition = static fn (array $children): AstNode => new AstNode('definition', [], $children);
$definitionTerm = static fn (array $children): AstNode => new AstNode('definition_term', [], $children);
$definitionItem = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
    'definition_item',
    [],
    array_merge([$term], $definitions)
);
$definitionList = static fn (array $items): AstNode => new AstNode('definition_list', [], $items);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$definitionDocument = static fn (AstNode $term, array $definitions) => $document([
    $definitionList([$definitionItem($term, $definitions)]),
]);
$simpleDefinitionDocument = static fn (string $term, string $body) => $definitionDocument(
    $definitionTerm([$text($term)]),
    [$definition([$paragraph($body)])]
);

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'space' || $node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

$firstDefinitionChildren = static function (AstNode $document): array {
    $definition = $document->children[0]->children[0]->children[1] ?? null;

    return $definition instanceof AstNode ? $definition->children : [];
};

$tests = [];

$nestedDefinitionList = $definitionList([
    $definitionItem($definitionTerm([$text('Inner')]), [$definition([$paragraph('Nested')])]),
]);
$nestedTwoDefinitionList = $definitionList([
    $definitionItem($definitionTerm([$text('Inner')]), [
        $definition([$paragraph('First')]),
        $definition([$paragraph('Second')]),
    ]),
]);
$twoItemDefinitionList = $definitionList([
    $definitionItem($definitionTerm([$text('One')]), [$definition([$paragraph('First')])]),
    $definitionItem($definitionTerm([$text('Two')]), [$definition([$paragraph('Second')])]),
]);

$nativeOutputCases = [
    '01 simple paragraph body' => [
        $simpleDefinitionDocument('Term', 'Definition'),
        "Term\n:   Definition",
    ],
    '02 plain body stays inline' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$plain('Plain definition')])]),
        "Term\n:   Plain definition",
    ],
    '03 two paragraph body keeps continuation indent' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$paragraph('First'), $paragraph('Second')])]),
        "Term\n:   First\n\n    Second",
    ],
    '04 three paragraph body keeps continuation indent' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$paragraph('First'), $paragraph('Second'), $paragraph('Third')])]),
        "Term\n:   First\n\n    Second\n\n    Third",
    ],
    '05 two definitions keep separate markers' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$paragraph('First')]), $definition([$paragraph('Second')])]),
        "Term\n:   First\n:   Second",
    ],
    '06 empty definition keeps marker' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([])]),
        "Term\n:",
    ],
    '07 multiple items keep blank separator' => [
        $document([$twoItemDefinitionList]),
        "One\n:   First\n\nTwo\n:   Second",
    ],
    '08 term hard line break becomes second term line' => [
        $definitionDocument($definitionTerm([$text('Primary'), $linebreak(), $text('Alias')]), [$definition([$paragraph('Definition')])]),
        "Primary\nAlias\n:   Definition",
    ],
    '09 term two hard line breaks become term group' => [
        $definitionDocument($definitionTerm([$text('Primary'), $linebreak(), $text('Alias'), $linebreak(), $text('Synonym')]), [$definition([$paragraph('Definition')])]),
        "Primary\nAlias\nSynonym\n:   Definition",
    ],
    '10 rich term inline markdown is preserved' => [
        $definitionDocument($definitionTerm([$strong('Strong'), $space(), $emph('emph'), $space(), $code('code')]), [$definition([$paragraph('Definition')])]),
        "**Strong** *emph* `code`\n:   Definition",
    ],
    '11 colon-looking term is escaped' => [
        $simpleDefinitionDocument(': marker', 'Definition'),
        "\\: marker\n:   Definition",
    ],
    '12 tilde-looking term is escaped' => [
        $simpleDefinitionDocument('~ marker', 'Definition'),
        "\\~ marker\n:   Definition",
    ],
    '13 bullet-looking term is escaped' => [
        $simpleDefinitionDocument('- marker', 'Definition'),
        "\\- marker\n:   Definition",
    ],
    '14 ordered-looking term is escaped' => [
        $simpleDefinitionDocument('1. marker', 'Definition'),
        "1\\. marker\n:   Definition",
    ],
    '15 heading-looking term is escaped' => [
        $simpleDefinitionDocument('# marker', 'Definition'),
        "\\# marker\n:   Definition",
    ],
    '16 definition-marker paragraph is escaped' => [
        $simpleDefinitionDocument('Term', ': not another definition'),
        "Term\n:   \\: not another definition",
    ],
    '17 tilde-marker paragraph is escaped' => [
        $simpleDefinitionDocument('Term', '~ not another definition'),
        "Term\n:   \\~ not another definition",
    ],
    '18 bullet-marker paragraph is escaped' => [
        $simpleDefinitionDocument('Term', '- not a list'),
        "Term\n:   \\- not a list",
    ],
    '19 ordered-marker paragraph is escaped' => [
        $simpleDefinitionDocument('Term', '1. not ordered'),
        "Term\n:   1\\. not ordered",
    ],
    '20 continuation paragraph escapes heading marker' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$paragraph('First'), $paragraph('# not heading')])]),
        "Term\n:   First\n\n    \\# not heading",
    ],
    '21 leading bullet list detaches marker' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$bulletList([$listItem([$text('bullet')])])])]),
        "Term\n:\n\n    - bullet",
    ],
    '22 leading ordered list detaches marker' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$orderedList([$listItem([$text('one')])])])]),
        "Term\n:\n\n    1.  one",
    ],
    '23 leading nested definition list detaches marker' => [
        $definitionDocument($definitionTerm([$text('Outer')]), [$definition([$nestedDefinitionList])]),
        "Outer\n:\n\n    Inner\n    :   Nested",
    ],
    '24 nested definition list keeps multiple bodies' => [
        $definitionDocument($definitionTerm([$text('Outer')]), [$definition([$nestedTwoDefinitionList])]),
        "Outer\n:\n\n    Inner\n    :   First\n    :   Second",
    ],
    '25 paragraph then nested bullet keeps continuation indent' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$paragraph('Intro'), $bulletList([$listItem([$text('bullet')])])])]),
        "Term\n:   Intro\n\n    - bullet",
    ],
    '26 paragraph then nested definition keeps continuation indent' => [
        $definitionDocument($definitionTerm([$text('Outer')]), [$definition([$paragraph('Intro'), $nestedDefinitionList])]),
        "Outer\n:   Intro\n\n    Inner\n    :   Nested",
    ],
    '27 leading blockquote stays in body' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$blockquote([$paragraph('Quote')])])]),
        "Term\n:   > Quote",
    ],
    '28 leading code block detaches marker' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$codeBlock('echo')])]),
        "Term\n:\n\n        echo",
    ],
    '29 leading heading detaches marker' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$heading('Heading', 2)])]),
        "Term\n:\n\n    ## Heading",
    ],
    '30 leading line block detaches marker' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$lineBlock([$line('alpha'), $line('beta')])])]),
        "Term\n:\n\n    | alpha\n    | beta",
    ],
    '31 leading fenced div detaches colon fence' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$div([$paragraph('Inside')])])]),
        "Term\n:\n\n    :::\n    Inside\n    :::",
    ],
    '32 second definition can be nested list' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$paragraph('First')]), $definition([$bulletList([$listItem([$text('Second')])])])]),
        "Term\n:   First\n:\n\n    - Second",
    ],
    '33 list body followed by paragraph keeps body continuation' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$bulletList([$listItem([$text('bullet')])]), $paragraph('After')])]),
        "Term\n:\n\n    - bullet\n\n    After",
    ],
    '34 ordered body followed by nested definition keeps body continuation' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$orderedList([$listItem([$text('one')])]), $nestedDefinitionList])]),
        "Term\n:\n\n    1.  one\n\n    Inner\n    :   Nested",
    ],
    '35 complex multi item body keeps continuation indentation' => [
        $document([
            $definitionList([
                $definitionItem($definitionTerm([$text('Alpha')]), [$definition([$paragraph('First'), $bulletList([$listItem([$text('detail')])])])]),
                $definitionItem($definitionTerm([$text('Beta')]), [$definition([$paragraph('Second'), $orderedList([$listItem([$text('step')])])])]),
            ]),
        ]),
        "Alpha\n:   First\n\n    - detail\n\nBeta\n:   Second\n\n    1.  step",
    ],
    '36 native markdown can be disabled explicitly' => [
        $simpleDefinitionDocument('Term', 'Definition'),
        '<dl><dt>Term</dt><dd><p>Definition</p></dd></dl>',
        ['format' => 'markdown', 'extensions' => ['-definition_lists']],
    ],
];

foreach ($nativeOutputCases as $label => $case) {
    $tests['maps upstream markdown writer definition list profile surge native ' . $label] =
        static function (TestRunner $t) use ($case): void {
            [$document, $expected, $options] = [$case[0], $case[1], $case[2] ?? []];

            $t->same($expected, (new MarkdownWriter($options))->write($document));
        };
}

$roundTripCases = [
    '01 leading nested definition list survives' => [
        $definitionDocument($definitionTerm([$text('Outer')]), [$definition([$nestedDefinitionList])]),
        ['definition_list'],
    ],
    '02 leading bullet list survives' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$bulletList([$listItem([$text('bullet')])])])]),
        ['bullet_list'],
    ],
    '03 leading ordered list survives' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$orderedList([$listItem([$text('one')])])])]),
        ['ordered_list'],
    ],
    '04 paragraph then nested definition list survives' => [
        $definitionDocument($definitionTerm([$text('Outer')]), [$definition([$paragraph('Intro'), $nestedDefinitionList])]),
        ['paragraph', 'definition_list'],
    ],
    '05 second definition nested bullet survives' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$paragraph('First')]), $definition([$bulletList([$listItem([$text('Second')])])])]),
        ['bullet_list'],
        1,
    ],
    '06 term marker escapes keep literal term text' => [
        $simpleDefinitionDocument(': marker', 'Definition'),
        ['paragraph'],
        0,
        ': marker',
    ],
    '07 paragraph marker escapes stay paragraph text' => [
        $simpleDefinitionDocument('Term', ': not another definition'),
        ['paragraph'],
        0,
        'Term',
        ': not another definition',
    ],
    '08 term line break produces one definition item' => [
        $definitionDocument($definitionTerm([$text('Primary'), $linebreak(), $text('Alias')]), [$definition([$paragraph('Definition')])]),
        ['paragraph'],
        0,
        'Primary Alias',
    ],
];

foreach ($roundTripCases as $label => $case) {
    $tests['maps upstream markdown writer definition list profile surge round trip ' . $label] =
        static function (TestRunner $t) use ($case, $firstDefinitionChildren, $inlineText): void {
            [$document, $expectedTypes, $definitionIndex, $expectedTerm, $expectedFirstBody] = [
                $case[0],
                $case[1],
                $case[2] ?? 0,
                $case[3] ?? null,
                $case[4] ?? null,
            ];

            $markdown = (new MarkdownWriter())->write($document);
            $roundTrip = (new MarkdownReader())->read($markdown);
            $definition = $roundTrip->children[0]->children[0]->children[$definitionIndex + 1] ?? null;
            $t->true($definition instanceof AstNode, 'Expected definition after round trip');
            if (!$definition instanceof AstNode) {
                return;
            }

            $t->same($expectedTypes, array_map(static fn (AstNode $node): string => $node->type, $definition->children));

            if ($expectedTerm !== null) {
                $term = $roundTrip->children[0]->children[0]->children[0] ?? null;
                $t->true($term instanceof AstNode, 'Expected definition term after round trip');
                if ($term instanceof AstNode) {
                    $t->same($expectedTerm, trim($inlineText($term)));
                }
            }

            if ($expectedFirstBody !== null) {
                $children = $firstDefinitionChildren($roundTrip);
                $first = $children[0] ?? null;
                $t->true($first instanceof AstNode, 'Expected first definition body block');
                if ($first instanceof AstNode) {
                    $t->same($expectedFirstBody, trim($inlineText($first)));
                }
            }
        };
}

$fallbackStructures = [
    'simple item' => [
        $simpleDefinitionDocument('Term', 'Definition'),
        '<dl><dt>Term</dt><dd><p>Definition</p></dd></dl>',
    ],
    'term line break' => [
        $definitionDocument($definitionTerm([$text('Primary'), $linebreak(), $text('Alias')]), [$definition([$paragraph('Definition')])]),
        '<dl><dt>Primary<br />Alias</dt><dd><p>Definition</p></dd></dl>',
    ],
    'two definitions' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$paragraph('First')]), $definition([$paragraph('Second')])]),
        '<dl><dt>Term</dt><dd><p>First</p></dd><dd><p>Second</p></dd></dl>',
    ],
    'multiple items' => [
        $document([$twoItemDefinitionList]),
        '<dl><dt>One</dt><dd><p>First</p></dd><dt>Two</dt><dd><p>Second</p></dd></dl>',
    ],
    'nested bullet body' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$bulletList([$listItem([$text('bullet')])])])]),
        '<dl><dt>Term</dt><dd><ul><li>bullet</li></ul></dd></dl>',
    ],
    'nested ordered body' => [
        $definitionDocument($definitionTerm([$text('Term')]), [$definition([$orderedList([$listItem([$text('one')])])])]),
        '<dl><dt>Term</dt><dd><ol><li>one</li></ol></dd></dl>',
    ],
    'nested definition body' => [
        $definitionDocument($definitionTerm([$text('Outer')]), [$definition([$nestedDefinitionList])]),
        '<dl><dt>Outer</dt><dd><dl><dt>Inner</dt><dd><p>Nested</p></dd></dl></dd></dl>',
    ],
    'code block body' => [
        $definitionDocument($definitionTerm([$text('Command')]), [$definition([$codeBlock('wp import')])]),
        '<dl><dt>Command</dt><dd><pre><code>wp import</code></pre></dd></dl>',
    ],
    'rich term html' => [
        $definitionDocument($definitionTerm([$strong('Strong'), $space(), $emph('emph')]), [$definition([$paragraph('Definition')])]),
        '<dl><dt><strong>Strong</strong> <em>emph</em></dt><dd><p>Definition</p></dd></dl>',
    ],
];

$fallbackFormats = [
    'commonmark' => ['format' => 'commonmark'],
    'gfm' => ['format' => 'gfm'],
    'markdown github' => ['format' => 'markdown_github'],
    'markdown strict' => ['format' => 'markdown_strict'],
];

foreach ($fallbackFormats as $formatLabel => $options) {
    foreach ($fallbackStructures as $structureLabel => $case) {
        $tests['maps upstream markdown writer definition list profile surge html fallback ' . $formatLabel . ' ' . $structureLabel] =
            static function (TestRunner $t) use ($case, $options): void {
                [$document, $expected] = $case;

                $t->same($expected, (new MarkdownWriter($options))->write($document));
            };
    }
}

$nativeProfileCases = [
    'markdown default keeps native' => [['format' => 'markdown'], "Term\n:   Definition"],
    'commonmark x keeps native' => [['format' => 'commonmark_x'], "Term\n:   Definition"],
    'php extra keeps native' => [['format' => 'markdown_phpextra'], "Term\n:   Definition"],
    'multimarkdown keeps native' => [['format' => 'markdown_mmd'], "Term\n:   Definition"],
    'commonmark format extension keeps native' => [['format' => 'commonmark+definition_lists'], "Term\n:   Definition"],
    'gfm format extension keeps native' => [['format' => 'gfm+definition_lists'], "Term\n:   Definition"],
    'github format extension keeps native' => [['format' => 'markdown_github+definition_lists'], "Term\n:   Definition"],
    'strict format extension keeps native' => [['format' => 'markdown_strict+definition_lists'], "Term\n:   Definition"],
    'commonmark configured extension keeps native' => [['format' => 'commonmark', 'extensions' => ['+definition_lists']], "Term\n:   Definition"],
    'gfm configured extension keeps native' => [['format' => 'gfm', 'extensions' => ['definition_list' => true]], "Term\n:   Definition"],
];

foreach ($nativeProfileCases as $label => $case) {
    $tests['maps upstream markdown writer definition list profile surge extension override ' . $label] =
        static function (TestRunner $t) use ($case, $simpleDefinitionDocument): void {
            [$options, $expected] = $case;

            $t->same($expected, (new MarkdownWriter($options))->write($simpleDefinitionDocument('Term', 'Definition')));
        };
}

$mappedCaseCount = count($nativeOutputCases) + count($roundTripCases)
    + count($fallbackFormats) * count($fallbackStructures)
    + count($nativeProfileCases);

$tests['records markdown writer definition list profile surge mapped case count'] =
    static function (TestRunner $t) use ($mappedCaseCount): void {
        $t->same(90, $mappedCaseCount);
    };

return $tests;
