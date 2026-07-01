<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

/**
 * @return list<AstNode>
 */
$collectNodesOfType = static function (AstNode $node, string $type) use (&$collectNodesOfType): array {
    $nodes = [];
    if ($node->type === $type) {
        $nodes[] = $node;
    }

    foreach ($node->children as $child) {
        array_push($nodes, ...$collectNodesOfType($child, $type));
    }

    return $nodes;
};

$readFirstNodeOfType = static function (string $markdown, string $type, array $options = []) use ($collectNodesOfType): AstNode {
    $document = (new MarkdownReader($options))->read($markdown);
    $nodes = $collectNodesOfType($document, $type);

    return $nodes[0] ?? new AstNode('missing');
};

$invalidCases = [
    'angle inline link unescaped less' => [
        'markdown' => '[bad](<alpha<bravo> "bad")',
        'type' => 'link',
        'needle' => 'href="alpha<bravo"',
    ],
    'angle inline image unescaped less' => [
        'markdown' => '![bad](<media<draft.png> "bad")',
        'type' => 'image',
        'needle' => 'src="media<draft.png"',
    ],
    'angle reference link newline' => [
        'markdown' => "[bad][ref]\n\n[ref]: <alpha\nbravo> \"bad\"",
        'type' => 'link',
        'needle' => 'href="alpha',
    ],
    'bare reference link unescaped less' => [
        'markdown' => "[bad][ref]\n\n[ref]: /refs/bad<raw \"bad\"",
        'type' => 'link',
        'needle' => 'href="/refs/bad<raw"',
    ],
    'commonmark bare inline whitespace' => [
        'markdown' => '[bad](docs/review packet)',
        'type' => 'link',
        'needle' => 'href="docs/review%20packet"',
        'options' => ['format' => 'commonmark'],
    ],
    'gfm bare image control' => [
        'markdown' => '![bad](media/review' . chr(0x1F) . 'packet.png)',
        'type' => 'image',
        'needle' => 'src="media/review',
        'options' => ['format' => 'gfm'],
    ],
    'markdown strict reference unbalanced paren' => [
        'markdown' => "[bad][ref]\n\n[ref]: refs/(draft \"bad\"",
        'type' => 'link',
        'needle' => 'href="refs/(draft"',
        'options' => ['format' => 'markdown_strict'],
    ],
];

$validCases = [
    'angle inline escaped less' => [
        'markdown' => '[ok](<alpha\<bravo> "ok")',
        'type' => 'link',
        'url' => 'alpha<bravo',
        'title' => 'ok',
    ],
    'angle reference escaped less' => [
        'markdown' => "[ok][ref]\n\n[ref]: <alpha\<bravo> \"ok\"",
        'type' => 'link',
        'url' => 'alpha<bravo',
        'title' => 'ok',
    ],
    'strict bare title' => [
        'markdown' => '[ok](docs/review "Review title")',
        'type' => 'link',
        'url' => 'docs/review',
        'title' => 'Review title',
        'options' => ['format' => 'commonmark'],
    ],
    'strict balanced parens' => [
        'markdown' => '[ok](docs/(draft))',
        'type' => 'link',
        'url' => 'docs/(draft)',
        'options' => ['format' => 'gfm'],
    ],
    'default markdown bare space compatibility' => [
        'markdown' => '[ok](docs/review packet)',
        'type' => 'link',
        'url' => 'docs/review%20packet',
    ],
];

return [
    'rejects upstream markdown destination validation boundary cases' => static function (TestRunner $t) use ($invalidCases, $collectNodesOfType): void {
        foreach ($invalidCases as $name => $case) {
            $document = (new MarkdownReader($case['options'] ?? []))->read($case['markdown']);
            $nodes = $collectNodesOfType($document, $case['type']);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same([], array_map(static fn (AstNode $node): string => $node->type, $nodes), $name);
            $t->same(false, str_contains($blocks, $case['needle']), $name . ' wordpress output');
        }
    },

    'preserves valid escaped and profile-compatible destinations' => static function (TestRunner $t) use ($validCases, $readFirstNodeOfType): void {
        foreach ($validCases as $name => $case) {
            $node = $readFirstNodeOfType($case['markdown'], $case['type'], $case['options'] ?? []);

            $t->same($case['type'], $node->type, $name);
            $t->same($case['url'], $node->attr('url'), $name . ' url');
            $t->same($case['title'] ?? null, $node->attr('title'), $name . ' title');
        }
    },

    'records markdown destination validation completion mapped-case count' => static function (TestRunner $t) use ($invalidCases, $validCases): void {
        $t->same(12, count($invalidCases) + count($validCases));
    },
];
