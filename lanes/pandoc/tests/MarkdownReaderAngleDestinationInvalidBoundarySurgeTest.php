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

$assertNoNodeOfType = static function (TestRunner $t, string $markdown, string $type, string $caseName) use ($collectNodesOfType): void {
    $document = (new MarkdownReader())->read($markdown);
    $nodes = $collectNodesOfType($document, $type);

    $t->same([], array_map(static fn (AstNode $node): string => $node->type, $nodes), $caseName);
};

$readFirstNodeOfType = static function (string $markdown, string $type) use ($collectNodesOfType): AstNode {
    $document = (new MarkdownReader())->read($markdown);
    $nodes = $collectNodesOfType($document, $type);

    return $nodes[0] ?? new AstNode('missing');
};

$slug = static function (string $name): string {
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $name) ?? $name);

    return trim($slug, '-') ?: 'case';
};

$invalidInlineLessDestinations = [
    'plain less' => '<alpha<bravo>',
    'double less' => '<alpha<<bravo>',
    'leading less' => '<<draft>',
    'path segment less' => '</docs/<draft>',
    'query less' => '<search?q=<draft>',
    'fragment less' => '<docs#<draft>',
    'entity then less' => '<AT&amp;T<draft>',
    'escaped greater then less' => '<angle\>review<draft>',
    'html-like span less' => '<alpha<span>',
    'parent directory less' => '<../<draft>',
    'mailto local less' => '<mailto:review<team>',
    'doi suffix less' => '<doi:10.1000/<abc>',
    'file path less' => '<file:///tmp/<draft>',
    'git path less' => '<git://example.test/<repo>',
    'space before less' => '<white space<draft>',
    'tab before less' => "<white\tspace<draft>",
    'two slash less' => '<two\\\\<slashes>',
    'after escaped less then raw less' => '<ok\<still<bad>',
    'punctuation less' => '<alpha;beta<gamma>',
    'unicode escaped bytes less' => '<caf%C3%A9<draft>',
];

$invalidImageLessDestinations = [
    'plain image less' => '<media<draft.png>',
    'path image less' => '<media/path<draft.png>',
    'query image less' => '<media/chart.svg?state=<draft>',
    'fragment image less' => '<media/chart.svg#<view>',
    'entity image less' => '<media/AT&amp;T<logo.png>',
    'space image less' => '<media/review figure<draft.png>',
    'tab image less' => "<media/review\tfigure<draft.png>",
    'root image less' => '</media<draft.png>',
    'parent image less' => '<../media/<draft.png>',
    'double less image' => '<media/<<draft.png>',
    'leading less image' => '<<media.png>',
    'escaped greater image less' => '<media/angle\>figure<draft.png>',
    'two slash image less' => '<media/two\\\\<slashes.png>',
    'punctuation image less' => '<media/a;b<c.png>',
    'encoded image less' => '<media/caf%C3%A9<draft.png>',
];

$invalidReferenceLessDestinations = [
    'reference plain less' => '<alpha<bravo>',
    'reference double less' => '<alpha<<bravo>',
    'reference path less' => '</docs/<draft>',
    'reference query less' => '<search?q=<draft>',
    'reference fragment less' => '<docs#<draft>',
    'reference entity less' => '<AT&amp;T<draft>',
    'reference mailto less' => '<mailto:review<team>',
    'reference doi less' => '<doi:10.1000/<abc>',
    'reference file less' => '<file:///tmp/<draft>',
    'reference git less' => '<git://example.test/<repo>',
    'reference space less' => '<white space<draft>',
    'reference tab less' => "<white\tspace<draft>",
    'reference two slash less' => '<two\\\\<slashes>',
    'reference escaped then raw less' => '<ok\<still<bad>',
    'reference punctuation less' => '<alpha;beta<gamma>',
];

$invalidInlineNewlineDestinations = [
    'plain newline' => "<alpha\nbravo>",
    'path newline' => "</docs\n/draft>",
    'query newline' => "<search?q=alpha\nbeta>",
    'fragment newline' => "<docs#alpha\nbeta>",
    'entity newline' => "<AT&amp;T\nreview>",
    'space newline' => "<white space\nreview>",
    'mailto newline' => "<mailto:review\nteam@example.test>",
    'doi newline' => "<doi:10.1000/\nabc>",
    'file newline' => "<file:///tmp/import\nsource.csv>",
    'git newline' => "<git://example.test/repo\nmain>",
    'leading newline' => "<\nalpha>",
    'trailing newline' => "<alpha\n>",
    'double newline' => "<alpha\n\nbravo>",
    'escaped less newline' => "<alpha\<ok\nbad>",
    'escaped greater newline' => "<alpha\>ok\nbad>",
];

$invalidImageReferenceNewlineDestinations = [
    'image ref plain newline' => "<media\ncover.png>",
    'image ref path newline' => "<media/path\ncover.png>",
    'image ref query newline' => "<media/chart.svg?state\n=draft>",
    'image ref fragment newline' => "<media/chart.svg#view\nmain>",
    'image ref entity newline' => "<media/AT&amp;T\nlogo.png>",
    'image ref space newline' => "<media/review figure\ncover.png>",
    'image ref root newline' => "</media\ncover.png>",
    'image ref parent newline' => "<../media\ncover.png>",
    'image ref leading newline' => "<\nmedia.png>",
    'image ref trailing newline' => "<media.png\n>",
    'image ref double newline' => "<media\n\ncover.png>",
    'image ref escaped less newline' => "<media\<ok\nbad.png>",
    'image ref escaped greater newline' => "<media\>ok\nbad.png>",
    'image ref file newline' => "<file:///tmp/media\ncover.png>",
    'image ref encoded newline' => "<media/caf%C3%A9\ndraft.png>",
];

$escapedLessSanityCases = [
    'inline link escaped less' => [
        'markdown' => '[escaped](<alpha\<bravo>)',
        'type' => 'link',
        'url' => 'alpha<bravo',
    ],
    'inline image escaped less' => [
        'markdown' => '![escaped](<media\<draft.png>)',
        'type' => 'image',
        'url' => 'media<draft.png',
    ],
    'reference link escaped less' => [
        'markdown' => "[escaped][ref]\n\n[ref]: <alpha\<bravo> \"Escaped\"",
        'type' => 'link',
        'url' => 'alpha<bravo',
        'title' => 'Escaped',
    ],
    'reference image escaped less' => [
        'markdown' => "![escaped][img]\n\n[img]: <media\<draft.png> \"Escaped image\"",
        'type' => 'image',
        'url' => 'media<draft.png',
        'title' => 'Escaped image',
    ],
];

$tests = [];

$tests['rejects upstream inline angle link destinations with unescaped less-than'] =
    static function (TestRunner $t) use ($invalidInlineLessDestinations, $assertNoNodeOfType, $slug): void {
        foreach ($invalidInlineLessDestinations as $name => $destination) {
            $markdown = '[invalid ' . $name . '](' . $destination . ' "Invalid ' . $slug($name) . '")';

            $assertNoNodeOfType($t, $markdown, 'link', $name);
        }
    };

$tests['rejects upstream inline angle image destinations with unescaped less-than'] =
    static function (TestRunner $t) use ($invalidImageLessDestinations, $assertNoNodeOfType, $slug): void {
        foreach ($invalidImageLessDestinations as $name => $destination) {
            $markdown = '![invalid ' . $name . '](' . $destination . ' "Invalid ' . $slug($name) . '")';

            $assertNoNodeOfType($t, $markdown, 'image', $name);
        }
    };

$tests['rejects upstream reference angle destinations with unescaped less-than'] =
    static function (TestRunner $t) use ($invalidReferenceLessDestinations, $assertNoNodeOfType, $slug): void {
        foreach ($invalidReferenceLessDestinations as $name => $destination) {
            $reference = 'ref-' . $slug($name);
            $markdown = "[invalid {$name}][{$reference}]\n\n[{$reference}]: {$destination} \"Invalid {$reference}\"";

            $assertNoNodeOfType($t, $markdown, 'link', $name);
        }
    };

$tests['rejects upstream inline angle link destinations with line breaks'] =
    static function (TestRunner $t) use ($invalidInlineNewlineDestinations, $assertNoNodeOfType, $slug): void {
        foreach ($invalidInlineNewlineDestinations as $name => $destination) {
            $markdown = '[invalid ' . $name . '](' . $destination . ' "Invalid ' . $slug($name) . '")';

            $assertNoNodeOfType($t, $markdown, 'link', $name);
        }
    };

$tests['rejects upstream image reference angle destinations with line breaks'] =
    static function (TestRunner $t) use ($invalidImageReferenceNewlineDestinations, $assertNoNodeOfType, $slug): void {
        foreach ($invalidImageReferenceNewlineDestinations as $name => $destination) {
            $reference = 'img-' . $slug($name);
            $markdown = "![invalid {$name}][{$reference}]\n\n[{$reference}]: {$destination} \"Invalid {$reference}\"";

            $assertNoNodeOfType($t, $markdown, 'image', $name);
        }
    };

$tests['preserves upstream escaped less-than angle destinations'] =
    static function (TestRunner $t) use ($escapedLessSanityCases, $readFirstNodeOfType): void {
        foreach ($escapedLessSanityCases as $name => $case) {
            $node = $readFirstNodeOfType($case['markdown'], $case['type']);

            $t->same($case['type'], $node->type, $name);
            $t->same($case['url'], $node->attr('url'), $name);
            $t->same($case['title'] ?? null, $node->attr('title'), $name);
        }
    };

$tests['records markdown angle destination invalid boundary mapped-case count'] =
    static function (
        TestRunner $t
    ) use (
        $invalidInlineLessDestinations,
        $invalidImageLessDestinations,
        $invalidReferenceLessDestinations,
        $invalidInlineNewlineDestinations,
        $invalidImageReferenceNewlineDestinations
    ): void {
        $t->same(
            80,
            count($invalidInlineLessDestinations)
                + count($invalidImageLessDestinations)
                + count($invalidReferenceLessDestinations)
                + count($invalidInlineNewlineDestinations)
                + count($invalidImageReferenceNewlineDestinations)
        );
    };

return $tests;
