<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'code_block') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= ' ' . $inlineText($child);
    }

    return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
};

$findDefinitionLists = static function (AstNode $node) use (&$findDefinitionLists): array {
    $lists = $node->type === 'definition_list' ? [$node] : [];
    foreach ($node->children as $child) {
        array_push($lists, ...$findDefinitionLists($child));
    }

    return $lists;
};

$assertDefinitionList = static function (TestRunner $t, AstNode $document, array $expected, string $label) use ($inlineText): void {
    $list = $document->children[0] ?? new AstNode('missing');
    $t->same('definition_list', $list->type, $label . ' root list');
    $t->same(count($expected), count($list->children), $label . ' item count');

    foreach ($expected as $itemIndex => $expectedItem) {
        $item = $list->children[$itemIndex] ?? new AstNode('missing');
        $term = $item->children[0] ?? new AstNode('missing');
        $definitions = array_values(array_filter(
            $item->children,
            static fn (AstNode $child): bool => $child->type === 'definition'
        ));

        $t->same('definition_item', $item->type, $label . " item {$itemIndex} type");
        $t->same($expectedItem['term'], $item->attr('term'), $label . " item {$itemIndex} term attr");
        $t->same($expectedItem['termText'] ?? $expectedItem['term'], $inlineText($term), $label . " item {$itemIndex} term text");
        $t->same(count($expectedItem['definitions']), count($definitions), $label . " item {$itemIndex} definition count");

        foreach ($expectedItem['definitions'] as $definitionIndex => $expectedDefinition) {
            $definition = $definitions[$definitionIndex] ?? new AstNode('missing');
            $children = $definition->children;
            $types = array_map(static fn (AstNode $child): string => $child->type, $children);

            $t->same('definition', $definition->type, $label . " definition {$definitionIndex} type");
            $t->same($expectedDefinition['blocks'], $types, $label . " definition {$definitionIndex} blocks");
            $t->same($expectedDefinition['text'], $inlineText($definition), $label . " definition {$definitionIndex} text");
            if (array_key_exists('loose', $expectedDefinition)) {
                $t->same($expectedDefinition['loose'], (bool) $definition->attr('loose'), $label . " definition {$definitionIndex} loose");
            }
        }
    }
};

$paragraphDefinition = static fn (string $text, bool $loose = false): array => [
    'blocks' => [$loose ? 'paragraph' : 'plain'],
    'text' => $text,
    'loose' => $loose,
];

$bodyCases = [
    'compact colon definition' => [
        'markdown' => "Term\n: Definition",
        'items' => [['term' => 'Term', 'definitions' => [$paragraphDefinition('Definition')]]],
    ],
    'compact tilde definition' => [
        'markdown' => "Term\n~ Definition",
        'items' => [['term' => 'Term', 'definitions' => [$paragraphDefinition('Definition')]]],
    ],
    'no-space colon definition marker' => [
        'markdown' => "Term\n:Definition",
        'items' => [['term' => 'Term', 'definitions' => [$paragraphDefinition('Definition')]]],
    ],
    'one-space indented definition marker' => [
        'markdown' => "Term\n : Definition",
        'items' => [['term' => 'Term', 'definitions' => [$paragraphDefinition('Definition')]]],
    ],
    'two-space indented definition marker' => [
        'markdown' => "Term\n  : Definition",
        'items' => [['term' => 'Term', 'definitions' => [$paragraphDefinition('Definition')]]],
    ],
    'three-space indented definition marker' => [
        'markdown' => "Term\n   : Definition",
        'items' => [['term' => 'Term', 'definitions' => [$paragraphDefinition('Definition')]]],
    ],
    'blank before first definition makes definition loose' => [
        'markdown' => "Term\n\n: Definition",
        'items' => [['term' => 'Term', 'definitions' => [$paragraphDefinition('Definition', true)]]],
    ],
    'blank before two first definitions makes both loose' => [
        'markdown' => "Term\n\n: First\n\n: Second",
        'items' => [['term' => 'Term', 'definitions' => [
            $paragraphDefinition('First', true),
            $paragraphDefinition('Second', true),
        ]]],
    ],
    'multiple term lines become line-broken term' => [
        'markdown' => "Term one\nTerm two\n: Definition",
        'items' => [[
            'term' => "Term one\nTerm two",
            'termText' => 'Term one Term two',
            'definitions' => [$paragraphDefinition('Definition')],
        ]],
    ],
    'emphasized term keeps parsed inline term text' => [
        'markdown' => "*Term*\n: Definition",
        'items' => [['term' => '*Term*', 'termText' => 'Term', 'definitions' => [$paragraphDefinition('Definition')]]],
    ],
    'code term keeps parsed inline term text' => [
        'markdown' => "`Term`\n: Definition",
        'items' => [['term' => '`Term`', 'termText' => 'Term', 'definitions' => [$paragraphDefinition('Definition')]]],
    ],
    'definition body keeps inline emphasis and code text' => [
        'markdown' => "Term\n: **Strong** and `code`",
        'items' => [['term' => 'Term', 'definitions' => [$paragraphDefinition('Strong and code')]]],
    ],
    'lazy continuation joins definition paragraph' => [
        'markdown' => "Term\n: first\ncontinued",
        'items' => [['term' => 'Term', 'definitions' => [$paragraphDefinition('first continued')]]],
    ],
    'two lazy continuations join definition paragraph' => [
        'markdown' => "Term\n: first\ncontinued\nfinished",
        'items' => [['term' => 'Term', 'definitions' => [$paragraphDefinition('first continued finished')]]],
    ],
    'two-space continuation joins definition paragraph' => [
        'markdown' => "Term\n: first\n  continued",
        'items' => [['term' => 'Term', 'definitions' => [$paragraphDefinition('first continued')]]],
    ],
    'four-space continuation becomes second paragraph block' => [
        'markdown' => "Term\n: first\n    continued",
        'items' => [['term' => 'Term', 'definitions' => [[
            'blocks' => ['plain', 'plain'],
            'text' => 'first continued',
            'loose' => false,
        ]]]],
    ],
    'tab continuation becomes second paragraph block' => [
        'markdown' => "Term\n: first\n\tcontinued",
        'items' => [['term' => 'Term', 'definitions' => [[
            'blocks' => ['plain', 'plain'],
            'text' => 'first continued',
            'loose' => false,
        ]]]],
    ],
    'blank and four-space continuation becomes multi paragraph body' => [
        'markdown' => "Term\n: first\n\n    second paragraph",
        'items' => [['term' => 'Term', 'definitions' => [[
            'blocks' => ['plain', 'plain'],
            'text' => 'first second paragraph',
            'loose' => false,
        ]]]],
    ],
    'blank and repeated indented continuation keeps two body paragraphs' => [
        'markdown' => "Term\n: first\n\n    second paragraph\n\n    third paragraph",
        'items' => [['term' => 'Term', 'definitions' => [[
            'blocks' => ['plain', 'plain', 'plain'],
            'text' => 'first second paragraph third paragraph',
            'loose' => false,
        ]]]],
    ],
    'marker-line blockquote body' => [
        'markdown' => "Term\n: > quoted",
        'items' => [['term' => 'Term', 'definitions' => [[
            'blocks' => ['blockquote'],
            'text' => 'quoted',
            'loose' => false,
        ]]]],
    ],
    'marker-line bullet list body' => [
        'markdown' => "Term\n: - nested",
        'items' => [['term' => 'Term', 'definitions' => [[
            'blocks' => ['bullet_list'],
            'text' => 'nested',
            'loose' => false,
        ]]]],
    ],
    'marker-line ordered list body' => [
        'markdown' => "Term\n: 1. nested",
        'items' => [['term' => 'Term', 'definitions' => [[
            'blocks' => ['ordered_list'],
            'text' => 'nested',
            'loose' => false,
        ]]]],
    ],
    'marker-line heading body' => [
        'markdown' => "Term\n: # Heading",
        'items' => [['term' => 'Term', 'definitions' => [[
            'blocks' => ['heading'],
            'text' => 'Heading',
            'loose' => false,
        ]]]],
    ],
    'marker-line indented code body preserves code block' => [
        'markdown' => "Term\n:       code",
        'items' => [['term' => 'Term', 'definitions' => [[
            'blocks' => ['code_block'],
            'text' => 'code',
            'loose' => false,
        ]]]],
    ],
    'upstream command 11542 marker-line indented code body keeps nested marker literal' => [
        'markdown' => "Input\n\n:     Term\n\n        : Def",
        'items' => [['term' => 'Input', 'definitions' => [[
            'blocks' => ['code_block'],
            'text' => 'Term : Def',
            'loose' => true,
        ]]]],
    ],
    'continuation blockquote body after paragraph' => [
        'markdown' => "Term\n: first\n\n    > quoted",
        'items' => [['term' => 'Term', 'definitions' => [[
            'blocks' => ['plain', 'blockquote'],
            'text' => 'first quoted',
            'loose' => false,
        ]]]],
    ],
    'continuation bullet list body after paragraph' => [
        'markdown' => "Term\n: first\n\n    - nested",
        'items' => [['term' => 'Term', 'definitions' => [[
            'blocks' => ['plain', 'bullet_list'],
            'text' => 'first nested',
            'loose' => false,
        ]]]],
    ],
    'continuation ordered list body after paragraph' => [
        'markdown' => "Term\n: first\n\n    1. nested",
        'items' => [['term' => 'Term', 'definitions' => [[
            'blocks' => ['plain', 'ordered_list'],
            'text' => 'first nested',
            'loose' => false,
        ]]]],
    ],
    'continuation fenced code body after paragraph' => [
        'markdown' => "Term\n: first\n\n    ```\n    code\n    ```",
        'items' => [['term' => 'Term', 'definitions' => [[
            'blocks' => ['plain', 'code_block'],
            'text' => 'first code',
            'loose' => false,
        ]]]],
    ],
    'continuation indented code body after paragraph' => [
        'markdown' => "Term\n: first\n\n        code",
        'items' => [['term' => 'Term', 'definitions' => [[
            'blocks' => ['plain', 'code_block'],
            'text' => 'first code',
            'loose' => false,
        ]]]],
    ],
    'continuation heading body after paragraph' => [
        'markdown' => "Term\n: first\n\n    # Heading",
        'items' => [['term' => 'Term', 'definitions' => [[
            'blocks' => ['plain', 'heading'],
            'text' => 'first Heading',
            'loose' => false,
        ]]]],
    ],
    'continuation nested definition list body after paragraph' => [
        'markdown' => "Term\n: first\n\n    Nested\n    : nested definition",
        'items' => [['term' => 'Term', 'definitions' => [[
            'blocks' => ['plain', 'definition_list'],
            'text' => 'first Nested nested definition',
            'loose' => false,
        ]]]],
    ],
    'same term has two compact definitions' => [
        'markdown' => "Term\n: First\n: Second",
        'items' => [['term' => 'Term', 'definitions' => [
            $paragraphDefinition('First'),
            $paragraphDefinition('Second'),
        ]]],
    ],
    'same term has colon and tilde definitions' => [
        'markdown' => "Term\n: First\n~ Second",
        'items' => [['term' => 'Term', 'definitions' => [
            $paragraphDefinition('First'),
            $paragraphDefinition('Second'),
        ]]],
    ],
    'same term second definition has nested body' => [
        'markdown' => "Term\n: First\n: Second\n\n    - nested",
        'items' => [['term' => 'Term', 'definitions' => [
            $paragraphDefinition('First'),
            [
                'blocks' => ['plain', 'bullet_list'],
                'text' => 'Second nested',
                'loose' => false,
            ],
        ]]],
    ],
    'two adjacent items stay in one definition list' => [
        'markdown' => "Term one\n: First\nTerm two\n: Second",
        'items' => [
            ['term' => 'Term one', 'definitions' => [$paragraphDefinition('First')]],
            ['term' => 'Term two', 'definitions' => [$paragraphDefinition('Second')]],
        ],
    ],
    'blank between items stays in one definition list' => [
        'markdown' => "Term one\n: First\n\nTerm two\n: Second",
        'items' => [
            ['term' => 'Term one', 'definitions' => [$paragraphDefinition('First')]],
            ['term' => 'Term two', 'definitions' => [$paragraphDefinition('Second')]],
        ],
    ],
    'inline link in term keeps visible text' => [
        'markdown' => "[Term](/url)\n: Definition",
        'items' => [['term' => '[Term](/url)', 'termText' => 'Term', 'definitions' => [$paragraphDefinition('Definition')]]],
    ],
    'empty definition marker creates empty definition body' => [
        'markdown' => "Term\n:",
        'items' => [['term' => 'Term', 'definitions' => [[
            'blocks' => [],
            'text' => '',
            'loose' => false,
        ]]]],
    ],
];

$profileFixtures = [
    "Term\n: Definition",
    "Term\n~ Definition",
    "Term\n\n: Definition",
    "Term\n: first\n\n    second paragraph",
];
$enabledProfiles = [
    [],
    ['format' => 'markdown'],
    ['format' => 'pandoc'],
    ['format' => 'commonmark_x'],
    ['format' => 'markdown_phpextra'],
    ['format' => 'markdown_mmd'],
    ['format' => 'commonmark+definition_lists'],
    ['format' => 'gfm+definition_lists'],
    ['format' => 'markdown_strict+definition_lists'],
];
$disabledProfiles = [
    ['format' => 'commonmark'],
    ['format' => 'gfm'],
    ['format' => 'markdown_github'],
    ['format' => 'markdown_strict'],
    ['format' => 'markdown-definition_lists'],
    ['extensions' => ['definition_lists' => false]],
];
$nonDefinitionCases = [
    'four-space indented colon marker remains non-definition' => "Term\n    : Definition",
    'tab-indented colon marker remains non-definition' => "Term\n\t: Definition",
    'four-space indented tilde marker remains non-definition' => "Term\n    ~ Definition",
    'fenced div opener remains non-definition marker' => "Term\n::: review",
    'tilde fence remains non-definition marker' => "Term\n~~~",
];

$tests = [];

foreach ($bodyCases as $label => $case) {
    $tests['maps pandoc markdown definition-list final harvest ' . $label] =
        static function (TestRunner $t) use ($case, $assertDefinitionList, $label): void {
            $document = (new MarkdownReader())->read($case['markdown']);
            $assertDefinitionList($t, $document, $case['items'], $label);
        };
}

foreach ($enabledProfiles as $profileIndex => $options) {
    foreach ($profileFixtures as $fixtureIndex => $markdown) {
        $tests["maps definition-list enabled profile {$profileIndex} fixture {$fixtureIndex}"] =
            static function (TestRunner $t) use ($options, $markdown, $findDefinitionLists): void {
                $document = (new MarkdownReader($options))->read($markdown);
                $t->same(1, count($findDefinitionLists($document)));
            };
    }
}

foreach ($disabledProfiles as $profileIndex => $options) {
    foreach ($profileFixtures as $fixtureIndex => $markdown) {
        $tests["leaves definition markers literal for disabled profile {$profileIndex} fixture {$fixtureIndex}"] =
            static function (TestRunner $t) use ($options, $markdown, $findDefinitionLists): void {
                $document = (new MarkdownReader($options))->read($markdown);
                $t->same(0, count($findDefinitionLists($document)));
            };
    }
}

foreach ($nonDefinitionCases as $label => $markdown) {
    $tests['does not steal non-definition marker ' . $label] =
        static function (TestRunner $t) use ($markdown, $findDefinitionLists): void {
            $document = (new MarkdownReader())->read($markdown);
            $t->same(0, count($findDefinitionLists($document)));
        };
}

$tests['records markdown reader definition-list final harvest mapped-case count'] =
    static function (TestRunner $t) use ($bodyCases, $enabledProfiles, $disabledProfiles, $profileFixtures, $nonDefinitionCases): void {
        $mapped = count($bodyCases)
            + (count($enabledProfiles) * count($profileFixtures))
            + (count($disabledProfiles) * count($profileFixtures))
            + count($nonDefinitionCases);

        $t->same(104, $mapped);
    };

$tests['maps upstream command 11542 definition indented code block fixture exactly'] =
    static function (TestRunner $t): void {
        $source = (string) file_get_contents(
            dirname(__DIR__) . '/fixtures/upstream-command-11542-definition-code-block.md'
        );
        $document = (new MarkdownReader())->read($source);
        $definitionList = $document->children[0] ?? new AstNode('missing');
        $item = $definitionList->children[0] ?? new AstNode('missing');
        $definition = $item->children[1] ?? new AstNode('missing');
        $codeBlock = $definition->children[0] ?? new AstNode('missing');

        $t->same('definition_list', $definitionList->type);
        $t->same('definition_item', $item->type);
        $t->same('Input', $item->attr('term'));
        $t->same('definition', $definition->type);
        $t->same(['code_block'], array_map(static fn (AstNode $node): string => $node->type, $definition->children));
        $t->same("Term\n\n  : Def", $codeBlock->attr('text'));
    };

return $tests;
