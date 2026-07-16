<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

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

$readFirstNodeOfType = static function (string $format, string $markdown, string $type) use ($collectNodesOfType): AstNode {
    $document = (new MarkdownReader(['format' => $format]))->read($markdown);
    $nodes = $collectNodesOfType($document, $type);

    return $nodes[0] ?? new AstNode('missing');
};

$assertNoNodeOfType = static function (TestRunner $t, string $format, string $markdown, string $type, string $caseName) use ($collectNodesOfType): void {
    $document = (new MarkdownReader(['format' => $format]))->read($markdown);
    $nodes = $collectNodesOfType($document, $type);

    $t->same([], array_map(static fn (AstNode $node): string => $node->type, $nodes), $format . ' ' . $caseName);
};

$slug = static function (string $name): string {
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $name) ?? $name);

    return trim($slug, '-') ?: 'case';
};

$strictFormats = [
    'commonmark',
    'commonmark_x',
    'gfm',
    'markdown_github',
    'markdown_strict',
];

$invalidBareSpaceControlDestinations = [
    'space' => 'docs/review packet',
    'tab' => "docs/review\tpacket",
    'shift-out' => 'docs/review' . chr(0x0E) . 'packet',
    'data-link-escape' => 'docs/review' . chr(0x10) . 'packet',
    'unit-separator' => 'docs/review' . chr(0x1F) . 'packet',
    'delete' => 'docs/review' . chr(0x7F) . 'packet',
    'bell' => 'docs/review' . chr(0x07) . 'packet',
    'nul' => 'docs/review' . chr(0x00) . 'packet',
];

$invalidReferenceParenDestinations = [
    'open paren' => 'refs/(draft',
    'double open paren' => 'refs/((draft',
    'close paren' => 'refs/draft)',
    'double close paren' => 'refs/draft))',
    'extra close paren' => 'refs/(draft))',
    'trailing open paren' => 'refs/(draft)(',
    'query open paren' => 'refs?q=(draft',
    'fragment close paren' => 'refs#draft)',
];

$validStrictCases = [
    'inline link title' => [
        'markdown' => '[valid](docs/review "Review title")',
        'type' => 'link',
        'url' => 'docs/review',
        'title' => 'Review title',
    ],
    'inline image title' => [
        'markdown' => '![valid image](media/review.png "Image title")',
        'type' => 'image',
        'url' => 'media/review.png',
        'title' => 'Image title',
    ],
    'inline balanced parens' => [
        'markdown' => '[valid](docs/(draft))',
        'type' => 'link',
        'url' => 'docs/(draft)',
    ],
    'inline escaped close paren' => [
        'markdown' => '[valid](docs/\)draft)',
        'type' => 'link',
        'url' => 'docs/)draft',
    ],
    'reference link title' => [
        'markdown' => "[valid]\n\n[valid]: refs/review \"Reference title\"",
        'type' => 'link',
        'url' => 'refs/review',
        'title' => 'Reference title',
    ],
    'reference image balanced parens' => [
        'markdown' => "![valid image][img]\n\n[img]: media/(draft).png \"Image title\"",
        'type' => 'image',
        'url' => 'media/(draft).png',
        'title' => 'Image title',
    ],
    'angle destination space' => [
        'markdown' => '[valid](<docs/review packet> "Angle title")',
        'type' => 'link',
        'url' => 'docs/review%20packet',
        'title' => 'Angle title',
    ],
];

$tests = [];

$tests['rejects strict-profile inline bare link destinations with raw whitespace and controls'] =
    static function (TestRunner $t) use ($strictFormats, $invalidBareSpaceControlDestinations, $assertNoNodeOfType, $slug): void {
        foreach ($strictFormats as $format) {
            foreach ($invalidBareSpaceControlDestinations as $name => $destination) {
                $label = 'invalid ' . $format . ' ' . $name;
                $markdown = '[' . $label . '](' . $destination . ')';

                $assertNoNodeOfType($t, $format, $markdown, 'link', $slug($name));
            }
        }
    };

$tests['rejects strict-profile inline bare image destinations with raw whitespace and controls'] =
    static function (TestRunner $t) use ($strictFormats, $invalidBareSpaceControlDestinations, $assertNoNodeOfType, $slug): void {
        foreach ($strictFormats as $format) {
            foreach ($invalidBareSpaceControlDestinations as $name => $destination) {
                $label = 'invalid ' . $format . ' image ' . $name;
                $markdown = '![' . $label . '](' . $destination . ')';

                $assertNoNodeOfType($t, $format, $markdown, 'image', $slug($name));
            }
        }
    };

$tests['rejects strict-profile reference bare link destinations with raw whitespace and controls'] =
    static function (TestRunner $t) use ($strictFormats, $invalidBareSpaceControlDestinations, $assertNoNodeOfType, $slug): void {
        foreach ($strictFormats as $format) {
            foreach ($invalidBareSpaceControlDestinations as $name => $destination) {
                $reference = 'ref-' . $format . '-' . $slug($name);
                $markdown = "[invalid {$name}][{$reference}]\n\n[{$reference}]: {$destination}";

                $assertNoNodeOfType($t, $format, $markdown, 'link', $reference);
            }
        }
    };

$tests['rejects strict-profile reference bare image destinations with raw whitespace and controls'] =
    static function (TestRunner $t) use ($strictFormats, $invalidBareSpaceControlDestinations, $assertNoNodeOfType, $slug): void {
        foreach ($strictFormats as $format) {
            foreach ($invalidBareSpaceControlDestinations as $name => $destination) {
                $reference = 'img-' . $format . '-' . $slug($name);
                $markdown = "![invalid {$name}][{$reference}]\n\n[{$reference}]: {$destination}";

                $assertNoNodeOfType($t, $format, $markdown, 'image', $reference);
            }
        }
    };

$tests['rejects strict-profile reference bare link destinations with unbalanced parentheses'] =
    static function (TestRunner $t) use ($strictFormats, $invalidReferenceParenDestinations, $assertNoNodeOfType, $slug): void {
        foreach ($strictFormats as $format) {
            foreach ($invalidReferenceParenDestinations as $name => $destination) {
                $reference = 'paren-' . $format . '-' . $slug($name);
                $markdown = "[invalid {$name}][{$reference}]\n\n[{$reference}]: {$destination} \"Paren title\"";

                $assertNoNodeOfType($t, $format, $markdown, 'link', $reference);
            }
        }
    };

$tests['rejects strict-profile reference bare image destinations with unbalanced parentheses'] =
    static function (TestRunner $t) use ($strictFormats, $invalidReferenceParenDestinations, $assertNoNodeOfType, $slug): void {
        foreach ($strictFormats as $format) {
            foreach ($invalidReferenceParenDestinations as $name => $destination) {
                $reference = 'paren-img-' . $format . '-' . $slug($name);
                $markdown = "![invalid {$name}][{$reference}]\n\n[{$reference}]: {$destination} \"Paren image\"";

                $assertNoNodeOfType($t, $format, $markdown, 'image', $reference);
            }
        }
    };

$tests['preserves valid strict-profile bare titles balanced parens escapes and angle destinations'] =
    static function (TestRunner $t) use ($strictFormats, $validStrictCases, $readFirstNodeOfType): void {
        foreach ($strictFormats as $format) {
            foreach ($validStrictCases as $name => $case) {
                $node = $readFirstNodeOfType($format, $case['markdown'], $case['type']);

                $t->same($case['type'], $node->type, $format . ' ' . $name);
                $t->same($case['url'], $node->attr('url'), $format . ' ' . $name . ' url');
                $t->same($case['title'] ?? null, $node->attr('title'), $format . ' ' . $name . ' title');
            }
        }
    };

$tests['records markdown strict bare destination mapped-case count'] =
    static function (
        TestRunner $t
    ) use (
        $strictFormats,
        $invalidBareSpaceControlDestinations,
        $invalidReferenceParenDestinations
    ): void {
        $t->same(
            240,
            count($strictFormats)
                * (
                    (count($invalidBareSpaceControlDestinations) * 4)
                    + (count($invalidReferenceParenDestinations) * 2)
                )
        );
    };

return $tests;
