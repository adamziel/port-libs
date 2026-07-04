<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$collectNodes = null;
$collectNodes = static function (AstNode $node, string $type) use (&$collectNodes): array {
    $matches = $node->type === $type ? [$node] : [];
    foreach ($node->children as $child) {
        array_push($matches, ...$collectNodes($child, $type));
    }

    return $matches;
};

$inlineText = null;
$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
};

$cases = [
    'double quote attribute close bracket' => [
        'body' => '<span data-note="]">raw</span>',
        'types' => ['raw_html_inline', 'text', 'raw_html_inline'],
        'html' => ['<span data-note="]">', '</span>'],
        'text' => 'raw',
    ],
    'single quote attribute close bracket' => [
        'body' => '<a href="/docs" title=\']\'>link</a>',
        'types' => ['raw_html_inline', 'text', 'raw_html_inline'],
        'html' => ['<a href="/docs" title=\']\'>', '</a>'],
        'text' => 'link',
    ],
    'unquoted attribute close bracket' => [
        'body' => '<span data-token=]>raw</span>',
        'types' => ['raw_html_inline', 'text', 'raw_html_inline'],
        'html' => ['<span data-token=]>', '</span>'],
        'text' => 'raw',
    ],
    'self closing attribute close bracket' => [
        'body' => 'before <img alt="]" src="/x.png" /> after',
        'types' => ['text', 'raw_html_inline', 'text'],
        'html' => ['<img alt="]" src="/x.png" />'],
        'text' => 'before after',
    ],
    'html comment close bracket' => [
        'body' => '<!-- review ] marker --> raw',
        'blockType' => 'raw_html',
        'rawHtml' => '<!-- review ] marker --> raw',
    ],
    'cdata close bracket' => [
        'body' => '<![CDATA[x ] y]]> raw',
        'blockType' => 'raw_html',
        'rawHtml' => '<![CDATA[x ] y]]> raw',
    ],
    'processing instruction close bracket' => [
        'body' => '<?review ] marker?> raw',
        'blockType' => 'raw_html',
        'rawHtml' => '<?review ] marker?> raw',
    ],
    'html tag before markdown link' => [
        'body' => '<span data-note="]">[label](https://example.test/a_(b))</span>',
        'types' => ['raw_html_inline', 'link', 'raw_html_inline'],
        'html' => ['<span data-note="]">', '</span>'],
        'text' => 'label',
        'linkUrl' => 'https://example.test/a_(b)',
    ],
    'code span and html bracket attributes' => [
        'body' => 'code `]` and <span data-x="]">raw</span>',
        'types' => ['text', 'code', 'text', 'raw_html_inline', 'text', 'raw_html_inline'],
        'html' => ['<span data-x="]">', '</span>'],
        'text' => 'code ] and raw',
    ],
    'math span and html bracket attributes' => [
        'body' => 'math $x [y]$ and <span data-x="]">raw</span>',
        'types' => ['text', 'math', 'text', 'raw_html_inline', 'text', 'raw_html_inline'],
        'html' => ['<span data-x="]">', '</span>'],
        'text' => 'math x [y] and raw',
    ],
];

$tests = [];
foreach ($cases as $name => $case) {
    $tests['maps upstream markdown reader inline note html boundary completion ' . $name] =
        static function (TestRunner $t) use ($case, $collectNodes, $inlineText): void {
            $document = (new MarkdownReader())->read('Before ^[' . $case['body'] . '] after.');
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $note = $collectNodes($paragraph, 'note')[0] ?? new AstNode('missing');
            $noteParagraph = $note->children[0] ?? new AstNode('missing');
            $htmlNodes = $collectNodes($noteParagraph, 'raw_html_inline');

            $t->same('paragraph', $paragraph->type, $case['body'] . ' outer paragraph');
            $t->same('note', $note->type, $case['body'] . ' note node');
            $t->same(' after.', ($paragraph->children[array_key_last($paragraph->children)] ?? new AstNode('missing'))->attr('text'), $case['body'] . ' trailing text');
            $t->same($case['blockType'] ?? 'paragraph', $noteParagraph->type, $case['body'] . ' note block type');

            if (($case['blockType'] ?? 'paragraph') === 'raw_html') {
                $t->same($case['rawHtml'], $noteParagraph->attr('html'), $case['body'] . ' raw HTML block');
                return;
            }

            $t->same($case['types'], array_map(static fn (AstNode $node): string => $node->type, $noteParagraph->children), $case['body'] . ' child types');
            $t->same($case['html'], array_map(static fn (AstNode $node): string => (string) $node->attr('html', ''), $htmlNodes), $case['body'] . ' raw HTML spans');
            $t->same($case['text'], $inlineText($noteParagraph), $case['body'] . ' normalized note text');

            if (isset($case['linkUrl'])) {
                $link = $collectNodes($noteParagraph, 'link')[0] ?? new AstNode('missing');
                $t->same($case['linkUrl'], $link->attr('url'), $case['body'] . ' link target');
            }
        };
}

$tests['leaves malformed inline note html boundary literal'] =
    static function (TestRunner $t) use ($collectNodes): void {
        $document = (new MarkdownReader())->read('Before ^[note <span data-x="]"> after.');
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $html = $collectNodes($paragraph, 'raw_html_inline')[0] ?? new AstNode('missing');

        $t->same([], $collectNodes($paragraph, 'note'), 'unclosed inline note remains literal');
        $t->same('<span data-x="]">', $html->attr('html'), 'raw HTML attribute bracket is still parsed as HTML');
        $t->same('Before ^[note  after.', $paragraph->attr('text'), 'literal inline note text remains in paragraph');
    };

$tests['renders inline note html boundary completion through wordpress handoff'] =
    static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read('Before ^[<span data-note="]">raw</span>] after.');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<span data-note="]">raw</span>', $blocks);
    };

$tests['records markdown reader inline note html boundary completion mapped-case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(12, count($cases) + 2);
    };

return $tests;
