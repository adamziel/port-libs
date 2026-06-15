<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$rawSurgeCases = [
    ['format' => 'html', 'family' => 'html'],
    ['format' => 'html4', 'family' => 'html'],
    ['format' => 'html5', 'family' => 'html'],
    ['format' => 'xhtml', 'family' => 'html'],
    ['format' => 'html+raw_html', 'family' => 'html'],
    ['format' => 'html-native_divs', 'family' => 'html'],
    ['format' => 'html5+smart', 'family' => 'html'],
    ['format' => 'xhtml-native_spans', 'family' => 'html'],
    ['format' => 'tex', 'family' => 'tex'],
    ['format' => 'latex', 'family' => 'tex'],
    ['format' => 'context', 'family' => 'tex'],
    ['format' => 'latex+raw_tex', 'family' => 'tex'],
    ['format' => 'tex-macros', 'family' => 'tex'],
    ['format' => 'context+raw_tex', 'family' => 'tex'],
    ['format' => 'latex-smart', 'family' => 'tex'],
    ['format' => 'tex+native_divs', 'family' => 'tex'],
    ['format' => 'markdown', 'family' => 'markdown'],
    ['format' => 'markdown_strict', 'family' => 'markdown'],
    ['format' => 'markdown_phpextra', 'family' => 'markdown'],
    ['format' => 'markdown_github', 'family' => 'markdown'],
    ['format' => 'markdown_mmd', 'family' => 'markdown'],
    ['format' => 'pandoc', 'family' => 'markdown'],
    ['format' => 'commonmark', 'family' => 'markdown'],
    ['format' => 'commonmark_x', 'family' => 'markdown'],
    ['format' => 'gfm', 'family' => 'markdown'],
    ['format' => 'markdown+emoji', 'family' => 'markdown'],
    ['format' => 'pandoc-smart', 'family' => 'markdown'],
    ['format' => 'commonmark_x+emoji', 'family' => 'markdown'],
    ['format' => 'gfm+pipe_tables', 'family' => 'markdown'],
    ['format' => 'markdown-mmd', 'family' => 'markdown'],
];

$rawSurgeSlug = static function (string $format): string {
    return trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($format)), '-');
};

$rawSurgeInlinePayload = static function (array $case) use ($rawSurgeSlug): string {
    $slug = $rawSurgeSlug($case['format']);

    return match ($case['family']) {
        'html' => '<span data-raw="' . $slug . '">inline ' . $slug . '</span>',
        'tex' => '\\textbf{inline-' . $slug . '}',
        default => '**inline-' . $slug . '**',
    };
};

$rawSurgeBlockPayload = static function (array $case) use ($rawSurgeSlug): string {
    $slug = $rawSurgeSlug($case['format']);

    return match ($case['family']) {
        'html' => '<section data-raw="' . $slug . '"><p>block ' . $slug . '</p></section>',
        'tex' => '\\begin{center}' . "\n" . 'block-' . $slug . "\n" . '\\end{center}',
        default => '## block-' . $slug . "\n\n" . 'Mapped raw markdown.',
    };
};

$tests = [];

foreach ($rawSurgeCases as $case) {
    $tests['maps upstream markdown raw attribute inline format ' . $case['format'] . ' with metadata'] =
        static function (TestRunner $t) use ($case, $rawSurgeInlinePayload): void {
            $format = $case['format'];
            $rawText = $rawSurgeInlinePayload($case);
            $document = (new MarkdownReader())->read(implode("\n", [
                '---',
                'title: Raw attribute **Packet**',
                'review: {format: "' . $format . '", family: ' . $case['family'] . ', kind: inline}',
                '...',
                '',
                'Before `' . $rawText . '`{=' . $format . '} after.',
            ]));

            $meta = $document->attr('meta');
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $raw = $paragraph->children[1] ?? new AstNode('missing');
            $markdown = (new MarkdownWriter())->write($document);

            $t->same($format, $meta['review']['format'] ?? null);
            $t->same($case['family'], $meta['review']['family'] ?? null);
            $t->same('inline', $meta['review']['kind'] ?? null);
            $t->same('paragraph', $paragraph->type);
            $t->same('raw_inline', $raw->type);
            $t->same($format, $raw->attr('format'));
            $t->same($rawText, $raw->attr('text'));
            $t->contains($rawText, $markdown);
            $t->true(
                !str_contains($markdown, '`' . $rawText . '`{=' . $format . '}'),
                'Raw attribute inline should write raw text, not a code literal'
            );

            if ($case['family'] === 'html') {
                $blocks = (new WordPressBlockWriter())->write($document);
                $t->contains($rawText, $blocks);
                $t->true(!str_contains($blocks, '<code>'), 'HTML raw inline should not become code');
            } elseif ($case['family'] === 'tex') {
                $blocks = (new WordPressBlockWriter())->write($document);
                $t->contains(
                    '<span class="pandoc-raw-tex">' . htmlspecialchars($rawText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>',
                    $blocks
                );
            }
        };

    $tests['maps upstream markdown raw attribute fenced block format ' . $case['format'] . ' with metadata'] =
        static function (TestRunner $t) use ($case, $rawSurgeBlockPayload): void {
            $format = $case['format'];
            $rawText = $rawSurgeBlockPayload($case);
            $document = (new MarkdownReader())->read(implode("\n", [
                '---',
                'title: Raw block **Packet**',
                'review: {format: "' . $format . '", family: ' . $case['family'] . ', kind: block}',
                '...',
                '',
                '```{=' . $format . '}',
                $rawText,
                '```',
                '',
                'After raw block.',
            ]));

            $meta = $document->attr('meta');
            $raw = $document->children[0] ?? new AstNode('missing');
            $paragraph = $document->children[1] ?? new AstNode('missing');
            $markdown = (new MarkdownWriter())->write($document);

            $t->same($format, $meta['review']['format'] ?? null);
            $t->same($case['family'], $meta['review']['family'] ?? null);
            $t->same('block', $meta['review']['kind'] ?? null);
            $t->same('raw_block', $raw->type);
            $t->same($format, $raw->attr('format'));
            $t->same($rawText, $raw->attr('text'));
            $t->same('paragraph', $paragraph->type);
            $t->same('After raw block.', $paragraph->attr('text'));
            $t->contains($rawText, $markdown);

            if ($case['family'] === 'html') {
                $blocks = (new WordPressBlockWriter())->write($document);
                $t->contains('<!-- wp:html -->' . "\n" . $rawText . "\n" . '<!-- /wp:html -->', $blocks);
            } elseif ($case['family'] === 'tex') {
                $blocks = (new WordPressBlockWriter())->write($document);
                $t->contains(
                    '<pre class="wp-block-code"><code class="language-tex">'
                    . htmlspecialchars($rawText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</code></pre>',
                    $blocks
                );
            }
        };
}

$nativeSpanInlineText = static function (AstNode $node) use (&$nativeSpanInlineText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'linebreak' || $node->type === 'softbreak') {
        return "\n";
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $nativeSpanInlineText($child);
    }

    return $text;
};

$nativeSpanFirst = static function (AstNode $document): AstNode {
    foreach ($document->children as $block) {
        foreach ($block->children as $inline) {
            if ($inline->type === 'span') {
                return $inline;
            }
        }
    }

    return new AstNode('missing');
};

$nativeSpanCases = [
    [
        'name' => 'id class data review',
        'attrs' => 'id="span-alpha" class="review primary" data-review="alpha"',
        'content' => 'native alpha',
        'text' => 'native alpha',
        'id' => 'span-alpha',
        'classes' => ['review', 'primary'],
        'attributes' => ['review' => 'alpha'],
        'htmlAttributes' => ['id' => 'span-alpha', 'class' => 'review primary', 'data-review' => 'alpha'],
    ],
    [
        'name' => 'language class',
        'attrs' => 'class="locale" lang="pl"',
        'content' => 'Zrodlo',
        'text' => 'Zrodlo',
        'classes' => ['locale'],
        'attributes' => ['lang' => 'pl'],
        'htmlAttributes' => ['class' => 'locale', 'lang' => 'pl'],
    ],
    [
        'name' => 'title data index',
        'attrs' => 'title="Review title" data-index="7"',
        'content' => 'indexed title',
        'text' => 'indexed title',
        'attributes' => ['title' => 'Review title', 'index' => '7'],
        'htmlAttributes' => ['title' => 'Review title', 'data-index' => '7'],
    ],
    [
        'name' => 'direction aria label',
        'attrs' => 'dir="rtl" aria-label="Direction review"',
        'content' => 'directional',
        'text' => 'directional',
        'attributes' => ['dir' => 'rtl', 'aria-label' => 'Direction review'],
        'htmlAttributes' => ['dir' => 'rtl', 'aria-label' => 'Direction review'],
    ],
    [
        'name' => 'role style',
        'attrs' => 'role="note" style="color:red"',
        'content' => 'styled note',
        'text' => 'styled note',
        'attributes' => ['role' => 'note', 'style' => 'color:red'],
        'htmlAttributes' => ['role' => 'note', 'style' => 'color:red'],
        'wordpressAttributes' => ['role' => 'note'],
    ],
    [
        'name' => 'entity text',
        'attrs' => 'class="entity" data-origin="html"',
        'content' => 'AT&amp;T packet',
        'text' => 'AT&T packet',
        'classes' => ['entity'],
        'attributes' => ['origin' => 'html'],
        'htmlAttributes' => ['class' => 'entity', 'data-origin' => 'html'],
    ],
    [
        'name' => 'strong child',
        'attrs' => 'class="strong-review" data-kind="strong"',
        'content' => '<strong>strong child</strong>',
        'text' => 'strong child',
        'classes' => ['strong-review'],
        'attributes' => ['kind' => 'strong'],
        'htmlAttributes' => ['class' => 'strong-review', 'data-kind' => 'strong'],
    ],
    [
        'name' => 'emphasis child',
        'attrs' => 'class="em-review" data-kind="em"',
        'content' => '<em>em child</em>',
        'text' => 'em child',
        'classes' => ['em-review'],
        'attributes' => ['kind' => 'em'],
        'htmlAttributes' => ['class' => 'em-review', 'data-kind' => 'em'],
    ],
    [
        'name' => 'code child',
        'attrs' => 'class="code-review" data-kind="code"',
        'content' => '<code>$value</code>',
        'text' => '$value',
        'classes' => ['code-review'],
        'attributes' => ['kind' => 'code'],
        'htmlAttributes' => ['class' => 'code-review', 'data-kind' => 'code'],
    ],
    [
        'name' => 'link child',
        'attrs' => 'class="link-review" data-kind="link"',
        'content' => '<a href="/target" title="Target title">linked</a>',
        'text' => 'linked',
        'classes' => ['link-review'],
        'attributes' => ['kind' => 'link'],
        'htmlAttributes' => ['class' => 'link-review', 'data-kind' => 'link'],
    ],
    [
        'name' => 'superscript child',
        'attrs' => 'class="script-review" data-kind="sup"',
        'content' => 'x<sup>2</sup>',
        'text' => 'x2',
        'classes' => ['script-review'],
        'attributes' => ['kind' => 'sup'],
        'htmlAttributes' => ['class' => 'script-review', 'data-kind' => 'sup'],
        'wordpressContent' => 'x<sup>2</sup>',
    ],
    [
        'name' => 'subscript child',
        'attrs' => 'class="script-review" data-kind="sub"',
        'content' => 'H<sub>2</sub>O',
        'text' => 'H2O',
        'classes' => ['script-review'],
        'attributes' => ['kind' => 'sub'],
        'htmlAttributes' => ['class' => 'script-review', 'data-kind' => 'sub'],
        'wordpressContent' => 'H<sub>2</sub>O',
    ],
    [
        'name' => 'underline child',
        'attrs' => 'class="underline-review" data-kind="underline"',
        'content' => '<u>underlined</u>',
        'text' => 'underlined',
        'classes' => ['underline-review'],
        'attributes' => ['kind' => 'underline'],
        'htmlAttributes' => ['class' => 'underline-review', 'data-kind' => 'underline'],
    ],
    [
        'name' => 'deleted child',
        'attrs' => 'class="delete-review" data-kind="delete"',
        'content' => '<del>deleted</del>',
        'text' => 'deleted',
        'classes' => ['delete-review'],
        'attributes' => ['kind' => 'delete'],
        'htmlAttributes' => ['class' => 'delete-review', 'data-kind' => 'delete'],
    ],
    [
        'name' => 'quoted child',
        'attrs' => 'class="quote-review" data-kind="quote"',
        'content' => '<q>quoted</q>',
        'text' => 'quoted',
        'classes' => ['quote-review'],
        'attributes' => ['kind' => 'quote'],
        'htmlAttributes' => ['class' => 'quote-review', 'data-kind' => 'quote'],
    ],
    [
        'name' => 'linebreak child',
        'attrs' => 'class="break-review" data-kind="break"',
        'content' => 'line<br>break',
        'text' => "line\nbreak",
        'classes' => ['break-review'],
        'attributes' => ['kind' => 'break'],
        'htmlAttributes' => ['class' => 'break-review', 'data-kind' => 'break'],
        'wordpressContent' => 'line<br/>break',
    ],
    [
        'name' => 'nested span child',
        'attrs' => 'class="outer-review" data-kind="nested"',
        'content' => 'outer <span class="inner-review" data-inner="yes">inner</span>',
        'text' => 'outer inner',
        'classes' => ['outer-review'],
        'attributes' => ['kind' => 'nested'],
        'htmlAttributes' => ['class' => 'outer-review', 'data-kind' => 'nested'],
        'wordpressContent' => 'outer <span class="inner-review" data-inner="yes">inner</span>',
    ],
    [
        'name' => 'compressed classes',
        'attrs' => 'class="primary   secondary" data-kind="classes"',
        'content' => 'class normalization',
        'text' => 'class normalization',
        'classes' => ['primary', 'secondary'],
        'attributes' => ['kind' => 'classes'],
        'htmlAttributes' => ['class' => 'primary secondary', 'data-kind' => 'classes'],
    ],
    [
        'name' => 'data source hyphen',
        'attrs' => 'data-source-path="markdown-reader" data-kind="source"',
        'content' => 'source path',
        'text' => 'source path',
        'attributes' => ['source-path' => 'markdown-reader', 'kind' => 'source'],
        'htmlAttributes' => ['data-source-path' => 'markdown-reader', 'data-kind' => 'source'],
    ],
    [
        'name' => 'custom resource',
        'attrs' => 'resource="urn:review:1" data-kind="resource"',
        'content' => 'resource attr',
        'text' => 'resource attr',
        'attributes' => ['resource' => 'urn:review:1', 'kind' => 'resource'],
        'htmlAttributes' => ['resource' => 'urn:review:1', 'data-kind' => 'resource'],
        'wordpressAttributes' => ['data-kind' => 'resource'],
    ],
    [
        'name' => 'aria describedby',
        'attrs' => 'aria-describedby="note-a note-b" data-kind="aria"',
        'content' => 'aria described',
        'text' => 'aria described',
        'attributes' => ['aria-describedby' => 'note-a note-b', 'kind' => 'aria'],
        'htmlAttributes' => ['aria-describedby' => 'note-a note-b', 'data-kind' => 'aria'],
    ],
    [
        'name' => 'translate no',
        'attrs' => 'translate="no" data-kind="translate"',
        'content' => 'literal token',
        'text' => 'literal token',
        'attributes' => ['translate' => 'no', 'kind' => 'translate'],
        'htmlAttributes' => ['translate' => 'no', 'data-kind' => 'translate'],
    ],
    [
        'name' => 'tabindex',
        'attrs' => 'tabindex="0" data-kind="tabindex"',
        'content' => 'focusable',
        'text' => 'focusable',
        'attributes' => ['tabindex' => '0', 'kind' => 'tabindex'],
        'htmlAttributes' => ['tabindex' => '0', 'data-kind' => 'tabindex'],
        'wordpressAttributes' => ['data-kind' => 'tabindex'],
    ],
    [
        'name' => 'two data attrs',
        'attrs' => 'data-lane="pandoc" data-case="native-span"',
        'content' => 'two data attrs',
        'text' => 'two data attrs',
        'attributes' => ['lane' => 'pandoc', 'case' => 'native-span'],
        'htmlAttributes' => ['data-lane' => 'pandoc', 'data-case' => 'native-span'],
    ],
    [
        'name' => 'escaped angle text',
        'attrs' => 'class="escaped" data-kind="angle"',
        'content' => '&lt;escaped&gt;',
        'text' => '<escaped>',
        'classes' => ['escaped'],
        'attributes' => ['kind' => 'angle'],
        'htmlAttributes' => ['class' => 'escaped', 'data-kind' => 'angle'],
    ],
    [
        'name' => 'strong emphasis nested',
        'attrs' => 'class="nested-format" data-kind="format"',
        'content' => '<strong><em>both</em></strong>',
        'text' => 'both',
        'classes' => ['nested-format'],
        'attributes' => ['kind' => 'format'],
        'htmlAttributes' => ['class' => 'nested-format', 'data-kind' => 'format'],
    ],
    [
        'name' => 'mixed inline children',
        'attrs' => 'class="mixed" data-kind="mixed"',
        'content' => 'mix <strong>strong</strong> and <code>code</code>',
        'text' => 'mix strong and code',
        'classes' => ['mixed'],
        'attributes' => ['kind' => 'mixed'],
        'htmlAttributes' => ['class' => 'mixed', 'data-kind' => 'mixed'],
        'wordpressContent' => 'mix <strong>strong</strong> and <code>code</code>',
    ],
    [
        'name' => 'review packet',
        'attrs' => 'id="packet-span" class="review-packet" data-format="markdown" data-extension="native_spans"',
        'content' => 'packet span',
        'text' => 'packet span',
        'id' => 'packet-span',
        'classes' => ['review-packet'],
        'attributes' => ['format' => 'markdown', 'extension' => 'native_spans'],
        'htmlAttributes' => ['id' => 'packet-span', 'class' => 'review-packet', 'data-format' => 'markdown', 'data-extension' => 'native_spans'],
    ],
];

foreach ($nativeSpanCases as $case) {
    $tests['maps upstream markdown native span extension ' . $case['name'] . ' with metadata'] =
        static function (TestRunner $t) use ($case, $nativeSpanFirst, $nativeSpanInlineText): void {
            $document = (new MarkdownReader())->read(implode("\n", [
                '---',
                'title: Native span **Packet**',
                'review: {extension: native_spans, family: html, kind: inline, name: "' . $case['name'] . '"}',
                '...',
                '',
                'Before <span ' . $case['attrs'] . '>' . $case['content'] . '</span> after.',
            ]));

            $meta = $document->attr('meta');
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $span = $nativeSpanFirst($document);
            $attributes = $span->attr('attributes', []);
            $htmlAttributes = $span->attr('htmlAttributes', []);

            $t->same('native_spans', $meta['review']['extension'] ?? null);
            $t->same('html', $meta['review']['family'] ?? null);
            $t->same('inline', $meta['review']['kind'] ?? null);
            $t->same($case['name'], $meta['review']['name'] ?? null);
            $t->same('paragraph', $paragraph->type);
            $t->same('span', $span->type);
            $t->same($case['id'] ?? '', $span->attr('id', ''));
            $t->same($case['classes'] ?? [], $span->attr('classes', []));
            $t->same($case['text'], $nativeSpanInlineText($span));

            foreach ($case['attributes'] ?? [] as $name => $value) {
                $t->same($value, $attributes[$name] ?? null, $case['name'] . ' attribute ' . $name);
            }
            foreach ($case['htmlAttributes'] ?? [] as $name => $value) {
                $t->same($value, $htmlAttributes[$name] ?? null, $case['name'] . ' HTML attribute ' . $name);
            }
        };

    $tests['round trips upstream markdown native span extension ' . $case['name'] . ' through writers'] =
        static function (TestRunner $t) use ($case): void {
            $document = (new MarkdownReader(['nativeSpans' => true]))->read(
                'Before <span ' . $case['attrs'] . '>' . $case['content'] . '</span> after.'
            );
            $markdown = (new MarkdownWriter())->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->contains('Before ', $markdown);
            $t->contains(' after.', $markdown);
            foreach ($case['classes'] ?? [] as $class) {
                $t->contains('.' . $class, $markdown, $case['name'] . ' Markdown class ' . $class);
            }
            foreach ($case['attributes'] ?? [] as $name => $value) {
                $t->contains($name . '="' . $value . '"', $markdown, $case['name'] . ' Markdown attribute ' . $name);
            }
            foreach ($case['wordpressAttributes'] ?? $case['htmlAttributes'] ?? [] as $name => $value) {
                $t->contains($name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"', $blocks, $case['name'] . ' WordPress attribute ' . $name);
            }
            $t->contains($case['wordpressContent'] ?? htmlspecialchars($case['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $blocks);
        };
}

$nativeDivCases = [
    [
        'name' => 'id class data review',
        'attrs' => 'id="div-alpha" class="review primary" data-review="alpha"',
        'id' => 'div-alpha',
        'classes' => ['review', 'primary'],
        'attributes' => ['review' => 'alpha'],
        'htmlAttributes' => ['id' => 'div-alpha', 'class' => 'review primary', 'data-review' => 'alpha'],
    ],
    [
        'name' => 'language class',
        'attrs' => 'class="locale" lang="pl"',
        'classes' => ['locale'],
        'attributes' => ['lang' => 'pl'],
        'htmlAttributes' => ['class' => 'locale', 'lang' => 'pl'],
    ],
    [
        'name' => 'title data index',
        'attrs' => 'title="Review title" data-index="7"',
        'attributes' => ['title' => 'Review title', 'index' => '7'],
        'htmlAttributes' => ['title' => 'Review title', 'data-index' => '7'],
    ],
    [
        'name' => 'direction aria label',
        'attrs' => 'dir="rtl" aria-label="Direction review"',
        'attributes' => ['dir' => 'rtl', 'aria-label' => 'Direction review'],
        'htmlAttributes' => ['dir' => 'rtl', 'aria-label' => 'Direction review'],
    ],
    [
        'name' => 'role style',
        'attrs' => 'role="note" style="color:red"',
        'attributes' => ['role' => 'note', 'style' => 'color:red'],
        'htmlAttributes' => ['role' => 'note', 'style' => 'color:red'],
        'wordpressAttributes' => ['role' => 'note'],
    ],
    [
        'name' => 'data source hyphen',
        'attrs' => 'data-source-path="markdown-reader" data-kind="source"',
        'attributes' => ['source-path' => 'markdown-reader', 'kind' => 'source'],
        'htmlAttributes' => ['data-source-path' => 'markdown-reader', 'data-kind' => 'source'],
    ],
    [
        'name' => 'custom resource',
        'attrs' => 'resource="urn:review:1" data-kind="resource"',
        'attributes' => ['resource' => 'urn:review:1', 'kind' => 'resource'],
        'htmlAttributes' => ['resource' => 'urn:review:1', 'data-kind' => 'resource'],
        'wordpressAttributes' => ['data-kind' => 'resource'],
    ],
    [
        'name' => 'aria describedby',
        'attrs' => 'aria-describedby="note-a note-b" data-kind="aria"',
        'attributes' => ['aria-describedby' => 'note-a note-b', 'kind' => 'aria'],
        'htmlAttributes' => ['aria-describedby' => 'note-a note-b', 'data-kind' => 'aria'],
    ],
    [
        'name' => 'translate no',
        'attrs' => 'translate="no" data-kind="translate"',
        'attributes' => ['translate' => 'no', 'kind' => 'translate'],
        'htmlAttributes' => ['translate' => 'no', 'data-kind' => 'translate'],
    ],
    [
        'name' => 'tabindex',
        'attrs' => 'tabindex="0" data-kind="tabindex"',
        'attributes' => ['tabindex' => '0', 'kind' => 'tabindex'],
        'htmlAttributes' => ['tabindex' => '0', 'data-kind' => 'tabindex'],
        'wordpressAttributes' => ['data-kind' => 'tabindex'],
    ],
    [
        'name' => 'two data attrs',
        'attrs' => 'data-lane="pandoc" data-case="native-div"',
        'attributes' => ['lane' => 'pandoc', 'case' => 'native-div'],
        'htmlAttributes' => ['data-lane' => 'pandoc', 'data-case' => 'native-div'],
    ],
    [
        'name' => 'compressed classes',
        'attrs' => 'class="primary   secondary" data-kind="classes"',
        'classes' => ['primary', 'secondary'],
        'attributes' => ['kind' => 'classes'],
        'htmlAttributes' => ['class' => 'primary secondary', 'data-kind' => 'classes'],
    ],
    [
        'name' => 'quoted greater than title',
        'attrs' => 'title="A > B review" data-kind="angle"',
        'attributes' => ['title' => 'A > B review', 'kind' => 'angle'],
        'htmlAttributes' => ['title' => 'A > B review', 'data-kind' => 'angle'],
    ],
    [
        'name' => 'single quoted greater than',
        'attrs' => "data-title='A > B packet' data-kind='single-quote'",
        'attributes' => ['title' => 'A > B packet', 'kind' => 'single-quote'],
        'htmlAttributes' => ['data-title' => 'A > B packet', 'data-kind' => 'single-quote'],
    ],
    [
        'name' => 'xml language direction',
        'attrs' => 'xml:lang="en-US" dir="ltr" data-kind="language"',
        'attributes' => ['xml:lang' => 'en-US', 'dir' => 'ltr', 'kind' => 'language'],
        'htmlAttributes' => ['xml:lang' => 'en-US', 'dir' => 'ltr', 'data-kind' => 'language'],
    ],
    [
        'name' => 'presentation role title',
        'attrs' => 'role="presentation" title="Presentation packet"',
        'attributes' => ['role' => 'presentation', 'title' => 'Presentation packet'],
        'htmlAttributes' => ['role' => 'presentation', 'title' => 'Presentation packet'],
    ],
    [
        'name' => 'extension packet',
        'attrs' => 'data-format="markdown" data-extension="native_divs"',
        'attributes' => ['format' => 'markdown', 'extension' => 'native_divs'],
        'htmlAttributes' => ['data-format' => 'markdown', 'data-extension' => 'native_divs'],
    ],
    [
        'name' => 'aria controls expanded',
        'attrs' => 'aria-controls="packet-panel" aria-expanded="true" data-kind="controls"',
        'attributes' => ['aria-controls' => 'packet-panel', 'aria-expanded' => 'true', 'kind' => 'controls'],
        'htmlAttributes' => ['aria-controls' => 'packet-panel', 'aria-expanded' => 'true', 'data-kind' => 'controls'],
    ],
    [
        'name' => 'itemprop metadata',
        'attrs' => 'itemprop="articleBody" data-kind="microdata"',
        'attributes' => ['itemprop' => 'articleBody', 'kind' => 'microdata'],
        'htmlAttributes' => ['itemprop' => 'articleBody', 'data-kind' => 'microdata'],
        'wordpressAttributes' => ['data-kind' => 'microdata'],
    ],
    [
        'name' => 'draggable false',
        'attrs' => 'draggable="false" data-kind="drag"',
        'attributes' => ['draggable' => 'false', 'kind' => 'drag'],
        'htmlAttributes' => ['draggable' => 'false', 'data-kind' => 'drag'],
        'wordpressAttributes' => ['data-kind' => 'drag'],
    ],
    [
        'name' => 'spellcheck true',
        'attrs' => 'spellcheck="true" data-kind="spellcheck"',
        'attributes' => ['spellcheck' => 'true', 'kind' => 'spellcheck'],
        'htmlAttributes' => ['spellcheck' => 'true', 'data-kind' => 'spellcheck'],
        'wordpressAttributes' => ['data-kind' => 'spellcheck'],
    ],
    [
        'name' => 'contenteditable false',
        'attrs' => 'contenteditable="false" data-kind="editable"',
        'attributes' => ['contenteditable' => 'false', 'kind' => 'editable'],
        'htmlAttributes' => ['contenteditable' => 'false', 'data-kind' => 'editable'],
        'wordpressAttributes' => ['data-kind' => 'editable'],
    ],
    [
        'name' => 'slot packet',
        'attrs' => 'slot="review-packet" data-kind="slot"',
        'attributes' => ['slot' => 'review-packet', 'kind' => 'slot'],
        'htmlAttributes' => ['slot' => 'review-packet', 'data-kind' => 'slot'],
        'wordpressAttributes' => ['data-kind' => 'slot'],
    ],
    [
        'name' => 'rdf vocabulary',
        'attrs' => 'vocab="https://schema.org/" typeof="Article" data-kind="rdf"',
        'attributes' => ['vocab' => 'https://schema.org/', 'typeof' => 'Article', 'kind' => 'rdf'],
        'htmlAttributes' => ['vocab' => 'https://schema.org/', 'typeof' => 'Article', 'data-kind' => 'rdf'],
        'wordpressAttributes' => ['data-kind' => 'rdf'],
    ],
    [
        'name' => 'prefix mapping',
        'attrs' => 'prefix="schema: https://schema.org/" data-kind="prefix"',
        'attributes' => ['prefix' => 'schema: https://schema.org/', 'kind' => 'prefix'],
        'htmlAttributes' => ['prefix' => 'schema: https://schema.org/', 'data-kind' => 'prefix'],
        'wordpressAttributes' => ['data-kind' => 'prefix'],
    ],
    [
        'name' => 'data path slash',
        'attrs' => 'data-path="/review/source.md" data-kind="path"',
        'attributes' => ['path' => '/review/source.md', 'kind' => 'path'],
        'htmlAttributes' => ['data-path' => '/review/source.md', 'data-kind' => 'path'],
    ],
    [
        'name' => 'data json packet',
        'attrs' => 'data-json="{review:true}" data-kind="json"',
        'attributes' => ['json' => '{review:true}', 'kind' => 'json'],
        'htmlAttributes' => ['data-json' => '{review:true}', 'data-kind' => 'json'],
    ],
    [
        'name' => 'uppercase data attribute',
        'attrs' => 'DATA-REVIEW="Upper" data-kind="case"',
        'attributes' => ['review' => 'Upper', 'kind' => 'case'],
        'htmlAttributes' => ['data-review' => 'Upper', 'data-kind' => 'case'],
    ],
    [
        'name' => 'aria live polite',
        'attrs' => 'aria-live="polite" data-kind="live"',
        'attributes' => ['aria-live' => 'polite', 'kind' => 'live'],
        'htmlAttributes' => ['aria-live' => 'polite', 'data-kind' => 'live'],
    ],
    [
        'name' => 'source position',
        'attrs' => 'data-source-line="42" data-source-column="7"',
        'attributes' => ['source-line' => '42', 'source-column' => '7'],
        'htmlAttributes' => ['data-source-line' => '42', 'data-source-column' => '7'],
    ],
];

$nativeDivMarkdownAttrNeedle = static function (string $name, string $value): string {
    return $name . '="' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
};

foreach ($nativeDivCases as $case) {
    $tests['maps upstream markdown native div extension ' . $case['name'] . ' with metadata'] =
        static function (TestRunner $t) use ($case): void {
            $document = (new MarkdownReader())->read(implode("\n", [
                '---',
                'title: Native div **Packet**',
                'review: {extension: native_divs, family: html, kind: block, name: "' . $case['name'] . '"}',
                '...',
                '',
                '<div ' . $case['attrs'] . '>',
                'Native **source** packet for ' . $case['name'] . '.',
                '</div>',
            ]));

            $meta = $document->attr('meta');
            $div = $document->children[0] ?? new AstNode('missing');
            $paragraph = $div->children[0] ?? new AstNode('missing');
            $attributes = $div->attr('attributes', []);
            $htmlAttributes = $div->attr('htmlAttributes', []);

            $t->same('native_divs', $meta['review']['extension'] ?? null);
            $t->same('html', $meta['review']['family'] ?? null);
            $t->same('block', $meta['review']['kind'] ?? null);
            $t->same($case['name'], $meta['review']['name'] ?? null);
            $t->same('div', $div->type);
            $t->same($case['id'] ?? '', $div->attr('id', ''));
            $t->same($case['classes'] ?? [], $div->attr('classes', []));
            $t->same('paragraph', $paragraph->type);
            $t->same('Native source packet for ' . $case['name'] . '.', $paragraph->attr('text'));
            $t->same(['text', 'strong', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
            $t->same('source', $paragraph->children[1]->children[0]->attr('text'));

            foreach ($case['attributes'] ?? [] as $name => $value) {
                $t->same($value, $attributes[$name] ?? null, $case['name'] . ' attribute ' . $name);
            }
            foreach ($case['htmlAttributes'] ?? [] as $name => $value) {
                $t->same($value, $htmlAttributes[$name] ?? null, $case['name'] . ' HTML attribute ' . $name);
            }
        };

    $tests['round trips upstream markdown native div extension ' . $case['name'] . ' through writers'] =
        static function (TestRunner $t) use ($case, $nativeDivMarkdownAttrNeedle): void {
            $document = (new MarkdownReader())->read(implode("\n", [
                '<div ' . $case['attrs'] . '>',
                'Native **source** packet for ' . $case['name'] . '.',
                '</div>',
            ]));
            $markdown = (new MarkdownWriter())->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->contains(':::', $markdown);
            $t->contains('Native **source** packet for ' . $case['name'] . '.', $markdown);
            if (($case['id'] ?? '') !== '') {
                $t->contains('#' . $case['id'], $markdown, $case['name'] . ' Markdown id');
            }
            foreach ($case['classes'] ?? [] as $class) {
                $t->contains('.' . $class, $markdown, $case['name'] . ' Markdown class ' . $class);
            }
            foreach ($case['attributes'] ?? [] as $name => $value) {
                $t->contains($nativeDivMarkdownAttrNeedle($name, $value), $markdown, $case['name'] . ' Markdown attribute ' . $name);
            }
            foreach ($case['wordpressAttributes'] ?? $case['htmlAttributes'] ?? [] as $name => $value) {
                $t->contains($name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"', $blocks, $case['name'] . ' WordPress attribute ' . $name);
            }
            $t->contains('Native <strong>source</strong> packet for ' . $case['name'] . '.', $blocks);
        };
}

return $tests;
