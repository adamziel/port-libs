<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$plainText = null;
$plainText = static function (AstNode $node) use (&$plainText): string {
    $text = '';
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        $text .= (string) $node->attr('text', '');
    } elseif ($node->type === 'raw_tex') {
        $text .= (string) $node->attr('tex', '');
    } elseif ($node->type === 'raw_html' || $node->type === 'raw_html_inline') {
        $text .= (string) $node->attr('html', '');
    }

    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return $text;
};

$slug = static function (string $value): string {
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? $value);

    return trim($slug, '-') ?: 'case';
};

$rawHtmlElementTags = [
    'section',
    'article',
    'aside',
    'header',
    'footer',
    'nav',
    'main',
    'figure',
    'figcaption',
    'details',
    'summary',
    'dialog',
    'form',
    'fieldset',
    'iframe',
    'object',
    'video',
    'audio',
    'canvas',
    'svg',
    'math',
    'ruby',
    'time',
    'meter',
    'progress',
    'picture',
    'select',
    'datalist',
    'output',
    'search',
    'address',
    'center',
    'menu',
    'title',
    'mark',
];

$rawBlockCases = [];
foreach ($rawHtmlElementTags as $tag) {
    $label = 'raw-html-' . $slug($tag);
    $rawBlockCases['html element ' . $tag] = [
        'label' => $label,
        'source' => '<' . $tag . ' data-source="' . $label . '">' . "\n"
            . '(@' . $label . ') raw example' . "\n"
            . '</' . $tag . '>',
    ];
}

$rawBlockCases += [
    'html comment' => [
        'label' => 'raw-html-comment',
        'source' => "<!--\n(@raw-html-comment) raw example\n-->",
    ],
    'html processing instruction' => [
        'label' => 'raw-html-processing',
        'source' => "<?review\n(@raw-html-processing) raw example\n?>",
    ],
    'html declaration' => [
        'label' => 'raw-html-declaration',
        'source' => "<!REVIEW\n(@raw-html-declaration) raw example\n>",
    ],
    'html cdata' => [
        'label' => 'raw-html-cdata',
        'source' => "<![CDATA[\n(@raw-html-cdata) raw example\n]]>",
    ],
    'custom generic html element' => [
        'label' => 'raw-html-custom-element',
        'source' => "<x-review-boundary>\n(@raw-html-custom-element) raw example\n</x-review-boundary>",
    ],
];

foreach ([
    'equation',
    'align',
    'gather',
    'tikzpicture',
    'tabular',
    'displaymath',
    'matrix',
    'center',
] as $environment) {
    $label = 'raw-tex-' . $slug($environment);
    $rawBlockCases['latex environment ' . $environment] = [
        'label' => $label,
        'source' => '\\begin{' . $environment . '}' . "\n"
            . '(@' . $label . ') raw example' . "\n"
            . '\\end{' . $environment . '}',
    ];
}

$rawBlockCases['context formula environment'] = [
    'label' => 'raw-tex-context-formula',
    'source' => "\\start[formula]\n(@raw-tex-context-formula) raw example\n\\stop[formula]",
];
$rawBlockCases['context review environment'] = [
    'label' => 'raw-tex-context-review',
    'source' => "\\start[review]\n(@raw-tex-context-review) raw example\n\\stop[review]",
];

return [
    'keeps numbered example labels inside raw blocks from resolving references' =>
        static function (TestRunner $t) use ($plainText, $rawBlockCases): void {
            $t->same(50, count($rawBlockCases));

            foreach ($rawBlockCases as $name => $case) {
                $markdown = $case['source'] . "\n\n" . 'See (@' . $case['label'] . ').';
                $document = (new MarkdownReader())->read($markdown);
                $paragraph = $document->children[array_key_last($document->children)] ?? new AstNode('missing');
                $blocks = (new WordPressBlockWriter())->write($document);

                $t->same('paragraph', $paragraph->type, $name . ' trailing paragraph');
                $t->same('See (@' . $case['label'] . ').', $paragraph->attr('text'), $name . ' literal reference text');
                $t->contains('(@' . $case['label'] . ') raw example', $plainText($document), $name . ' raw source survives');
                $t->same(false, str_contains($blocks, 'See (1).'), $name . ' should not render resolved ordinal');
            }
        },

    'keeps numbered examples inside native markdown divs addressable' =>
        static function (TestRunner $t): void {
            $markdown = "<div>\n(@inside-div) real example\n</div>\n\nSee (@inside-div).";
            $document = (new MarkdownReader())->read($markdown);
            $div = $document->children[0] ?? new AstNode('missing');
            $list = $div->children[0] ?? new AstNode('missing');
            $paragraph = $document->children[1] ?? new AstNode('missing');

            $t->same('div', $div->type);
            $t->same('ordered_list', $list->type);
            $t->same('example', $list->attr('style'));
            $t->same('real example', $list->children[0]->attr('text'));
            $t->same('See (1).', $paragraph->attr('text'));
        },

    'orders numbered examples through nested native markdown divs' =>
        static function (TestRunner $t): void {
            $markdown = "<div>\n(@outer) outer\n<div>\n(@inner) inner\n</div>\n(@after) after\n</div>\n\nSee (@outer), (@inner), (@after).";
            $document = (new MarkdownReader())->read($markdown);
            $div = $document->children[0] ?? new AstNode('missing');
            $outer = $div->children[0] ?? new AstNode('missing');
            $innerDiv = $div->children[1] ?? new AstNode('missing');
            $after = $div->children[2] ?? new AstNode('missing');
            $paragraph = $document->children[1] ?? new AstNode('missing');

            $t->same(1, $outer->attr('start'));
            $t->same(1, $innerDiv->children[0]->attr('start'));
            $t->same(3, $after->attr('start'));
            $t->same('See (1), (2), (3).', $paragraph->attr('text'));
        },

    'keeps same-line native div examples addressable in source order' =>
        static function (TestRunner $t): void {
            $sameLine = (new MarkdownReader())->read(
                "<div>(@same) nope</div>\n(@after) yes\n\nSee (@same), (@after)."
            );
            $nestedSameLine = (new MarkdownReader())->read(
                "<div><div>(@inner) x</div></div>\n\nSee (@inner)."
            );
            $closingTail = (new MarkdownReader())->read(
                "<div>x</div>(@outer) x\n\nSee (@outer)."
            );
            $nestedClosingTail = (new MarkdownReader())->read(
                "<div><div>(@inner) y</div>(@outer) x</div>\n\nSee (@inner), (@outer)."
            );

            $t->same('See (1), (2).', $sameLine->children[array_key_last($sameLine->children)]->attr('text'));
            $t->same('See (1).', $nestedSameLine->children[array_key_last($nestedSameLine->children)]->attr('text'));
            $t->same('ordered_list', $closingTail->children[1]->type);
            $t->same('See (1).', $closingTail->children[array_key_last($closingTail->children)]->attr('text'));
            $t->same(2, $nestedClosingTail->children[0]->children[1]->attr('start'));
            // This tail becomes a list only in the recursive reader for the
            // enclosing native div. Its label is intentionally not exported
            // to the outer document's references.
            $t->same('See (1), (@outer).', $nestedClosingTail->children[array_key_last($nestedClosingTail->children)]->attr('text'));
        },

    'indexes virtual tails after source-bound HTML blocks' =>
        static function (TestRunner $t): void {
            $sources = [
                'article' => '<article>x</article>',
                'details' => '<details><summary>x</summary></details>',
                'div' => '<div>x</div>',
                'paragraph' => '<p>x</p>',
                'table' => '<table><tr><td>x</td></tr></table>',
            ];

            foreach ($sources as $tag => $source) {
                $label = 'same-line-' . $tag . '-tail';
                $document = (new MarkdownReader())->read(
                    $source . '(@' . $label . ') tail' . "\n\n"
                    . 'See (@' . $label . ').'
                );

                $paragraph = $document->children[array_key_last($document->children)] ?? new AstNode('missing');
                $t->same('paragraph', $paragraph->type, $tag . ' trailing reference block');
                $t->same('See (1).', $paragraph->attr('text'), $tag . ' virtual tail reference');
                $t->same(
                    true,
                    array_filter($document->children, static fn (AstNode $node): bool => $node->type === 'ordered_list') !== [],
                    $tag . ' virtual tail list'
                );
            }
        },

    'keeps blank-terminated raw HTML tails opaque to numbered example discovery' =>
        static function (TestRunner $t): void {
            foreach (['section', 'aside', 'header', 'main'] as $tag) {
                foreach (['physical' => "\n", 'same-line' => ''] as $shape => $separator) {
                    $label = 'opaque-' . $tag . '-' . $shape;
                    $document = (new MarkdownReader())->read(
                        '<' . $tag . '>x</' . $tag . '>' . $separator . '(@' . $label . ') tail' . "\n\n"
                        . 'See (@' . $label . ').'
                    );
                    $raw = $document->children[0] ?? new AstNode('missing');
                    $paragraph = $document->children[array_key_last($document->children)] ?? new AstNode('missing');

                    $t->same('raw_html', $raw->type, $tag . ' ' . $shape . ' raw block');
                    $t->contains('(@' . $label . ') tail', $raw->attr('html'), $tag . ' ' . $shape . ' raw source');
                    $t->same('See (@' . $label . ').', $paragraph->attr('text'), $tag . ' ' . $shape . ' literal reference');
                }
            }
        },

    'keeps inline HTML same-line tails opaque to numbered example discovery' =>
        static function (TestRunner $t): void {
            foreach (['button', 'del', 'ins'] as $tag) {
                $label = 'inline-opaque-' . $tag;
                $document = (new MarkdownReader())->read(
                    '<' . $tag . '>x</' . $tag . '>(@' . $label . ') tail' . "\n\n"
                    . 'See (@' . $label . ').'
                );
                $first = $document->children[0] ?? new AstNode('missing');
                $paragraph = $document->children[array_key_last($document->children)] ?? new AstNode('missing');

                $t->same('paragraph', $first->type, $tag . ' same-line container');
                $t->contains('(@' . $label . ') tail', $first->attr('text'), $tag . ' same-line source');
                $t->same('See (@' . $label . ').', $paragraph->attr('text'), $tag . ' literal reference');
            }
        },

    'extracts reference definitions from source-bound virtual HTML tails' =>
        static function (TestRunner $t): void {
            $sources = [
                'article' => '<article>x</article>',
                'details' => '<details><summary>x</summary></details>',
                'div' => '<div>x</div>',
                'paragraph' => '<p>x</p>',
                'table' => '<table><tr><td>x</td></tr></table>',
            ];

            foreach ($sources as $tag => $source) {
                $label = 'virtual-reference-' . $tag;
                $document = (new MarkdownReader())->read(
                    $source . '[' . $label . ']: /' . $label . " \"Tail {$tag}\"\n\n"
                    . '[' . $label . ']'
                );
                $paragraph = $document->children[array_key_last($document->children)] ?? new AstNode('missing');
                $link = $paragraph->children[0] ?? new AstNode('missing');

                $t->same('paragraph', $paragraph->type, $tag . ' reference paragraph');
                $t->same('link', $link->type, $tag . ' reference link');
                $t->same('/' . $label, $link->attr('url'), $tag . ' virtual definition URL');
            }

            $metadataCases = [
                'yaml' => [
                    "---\ntitle: Virtual YAML\n---\n\n<div>x</div>[virtual-yaml]: /virtual-yaml\n\n[virtual-yaml]",
                    'Virtual YAML',
                    'virtual-yaml',
                ],
                'title block' => [
                    "% Virtual Title\n\n<div>x</div>[virtual-title]: /virtual-title\n\n[virtual-title]",
                    'Virtual Title',
                    'virtual-title',
                ],
            ];
            foreach ($metadataCases as $name => [$markdown, $title, $label]) {
                $document = (new MarkdownReader())->read($markdown);
                $paragraph = $document->children[array_key_last($document->children)] ?? new AstNode('missing');
                $link = $paragraph->children[0] ?? new AstNode('missing');

                $t->same($title, $document->attr('meta', [])['title'] ?? '', $name . ' title');
                $t->same('/' . $label, $link->attr('url'), $name . ' virtual definition URL');
            }
        },

    'keeps opaque HTML tails out of reference definition extraction' =>
        static function (TestRunner $t): void {
            foreach (['section', 'aside', 'header', 'main'] as $tag) {
                $label = 'opaque-reference-' . $tag;
                $document = (new MarkdownReader())->read(
                    '<' . $tag . '>x</' . $tag . '>[' . $label . ']: /' . $label . "\n\n"
                    . '[' . $label . ']'
                );
                $raw = $document->children[0] ?? new AstNode('missing');
                $paragraph = $document->children[array_key_last($document->children)] ?? new AstNode('missing');

                $t->same('raw_html', $raw->type, $tag . ' opaque reference raw block');
                $t->contains('[' . $label . ']: /' . $label, $raw->attr('html'), $tag . ' opaque reference source');
                $t->same('[' . $label . ']', $paragraph->attr('text'), $tag . ' literal shortcut');
            }
        },
];
