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
];
