<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;
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

$rawSurgeExpectedNodeType = static function (array $case, string $kind): string {
    return match ($case['family']) {
        'html' => $kind === 'inline' ? 'raw_html_inline' : 'raw_html',
        'tex' => 'raw_tex',
        default => 'raw_markdown',
    };
};

$rawSurgeTextAttr = static function (AstNode $node, array $case): string {
    return match ($case['family']) {
        'html' => (string) $node->attr('html', $node->attr('text', '')),
        'tex' => (string) $node->attr('tex', $node->attr('text', '')),
        default => (string) $node->attr('markdown', $node->attr('text', '')),
    };
};

$rawSurgeReviewValue = static function (mixed $meta, string $key): mixed {
    if (!is_array($meta)) {
        return null;
    }

    $review = $meta['review'] ?? null;
    if (!is_array($review)) {
        return null;
    }

    if (($review['type'] ?? null) === 'map' && is_array($review['items'] ?? null)) {
        return $review['items'][$key] ?? null;
    }

    if (($review['t'] ?? null) === 'MetaMap' && is_array($review['c'] ?? null)) {
        $value = $review['c'][$key] ?? null;

        return is_array($value) && ($value['t'] ?? null) === 'MetaString' ? ($value['c'] ?? null) : $value;
    }

    return $review[$key] ?? null;
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

    $tests['maps upstream markdown raw attribute inline format ' . $case['format'] . ' through json and native raw family rehydration'] =
        static function (TestRunner $t) use ($case, $rawSurgeInlinePayload, $rawSurgeExpectedNodeType, $rawSurgeTextAttr, $rawSurgeReviewValue): void {
            $format = $case['format'];
            $rawText = $rawSurgeInlinePayload($case);
            $document = (new MarkdownReader())->read(implode("\n", [
                '---',
                'title: Raw inline family rehydration',
                'review: {format: "' . $format . '", family: ' . $case['family'] . ', channel: inline-roundtrip}',
                '...',
                '',
                'Before `' . $rawText . '`{=' . $format . '} after.',
            ]));
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

            $roundTrips = [
                'json' => (new PandocJsonReader())->readPacket($jsonPacket),
                'native' => (new NativeReader())->read(json_encode($nativePacket, JSON_THROW_ON_ERROR)),
            ];

            foreach ($roundTrips as $source => $roundTrip) {
                $meta = $roundTrip->attr('meta');
                $paragraph = $roundTrip->children[0] ?? new AstNode('missing');
                $raw = $paragraph->children[1] ?? new AstNode('missing');
                $markdown = (new MarkdownWriter())->write($roundTrip);

                $t->same($format, $rawSurgeReviewValue($meta, 'format'), "{$source} metadata format");
                $t->same($case['family'], $rawSurgeReviewValue($meta, 'family'), "{$source} metadata family");
                $t->same('inline-roundtrip', $rawSurgeReviewValue($meta, 'channel'), "{$source} metadata channel");
                $t->same('paragraph', $paragraph->type, "{$source} paragraph node");
                $t->same($rawSurgeExpectedNodeType($case, 'inline'), $raw->type, "{$source} raw inline family node");
                $t->same($format, $raw->attr('format'), "{$source} raw inline format");
                $t->same($rawText, $rawSurgeTextAttr($raw, $case), "{$source} raw inline text");
                $t->contains($rawText, $markdown);
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

    $tests['maps upstream markdown raw attribute fenced block format ' . $case['format'] . ' through json and native raw family rehydration'] =
        static function (TestRunner $t) use ($case, $rawSurgeBlockPayload, $rawSurgeExpectedNodeType, $rawSurgeTextAttr, $rawSurgeReviewValue): void {
            $format = $case['format'];
            $rawText = $rawSurgeBlockPayload($case);
            $document = (new MarkdownReader())->read(implode("\n", [
                '---',
                'title: Raw block family rehydration',
                'review: {format: "' . $format . '", family: ' . $case['family'] . ', channel: block-roundtrip}',
                '...',
                '',
                '```{=' . $format . '}',
                $rawText,
                '```',
                '',
                'After raw block.',
            ]));
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

            $roundTrips = [
                'json' => (new PandocJsonReader())->readPacket($jsonPacket),
                'native' => (new NativeReader())->read(json_encode($nativePacket, JSON_THROW_ON_ERROR)),
            ];

            foreach ($roundTrips as $source => $roundTrip) {
                $meta = $roundTrip->attr('meta');
                $raw = $roundTrip->children[0] ?? new AstNode('missing');
                $paragraph = $roundTrip->children[1] ?? new AstNode('missing');
                $markdown = (new MarkdownWriter())->write($roundTrip);

                $t->same($format, $rawSurgeReviewValue($meta, 'format'), "{$source} metadata format");
                $t->same($case['family'], $rawSurgeReviewValue($meta, 'family'), "{$source} metadata family");
                $t->same('block-roundtrip', $rawSurgeReviewValue($meta, 'channel'), "{$source} metadata channel");
                $t->same($rawSurgeExpectedNodeType($case, 'block'), $raw->type, "{$source} raw block family node");
                $t->same($format, $raw->attr('format'), "{$source} raw block format");
                $t->same($rawText, $rawSurgeTextAttr($raw, $case), "{$source} raw block text");
                $t->same('paragraph', $paragraph->type, "{$source} following paragraph");
                $t->same('After raw block.', $paragraph->attr('text'), "{$source} following paragraph text");
                $t->contains($rawText, $markdown);
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

$nativeDivFirst = static function (AstNode $document): AstNode {
    foreach ($document->children as $block) {
        if ($block->type === 'div') {
            return $block;
        }
    }

    return new AstNode('missing');
};

$nativeDivCases = [
    [
        'name' => 'id class data source',
        'attrs' => 'id="html-div-alpha" class="import primary" data-source="batch-1"',
        'text' => 'HTML native div alpha.',
        'id' => 'html-div-alpha',
        'classes' => ['import', 'primary'],
        'attributes' => ['source' => 'batch-1'],
        'htmlAttributes' => ['id' => 'html-div-alpha', 'class' => 'import primary', 'data-source' => 'batch-1'],
        'markdownAttrs' => '{#html-div-alpha .import .primary source="batch-1"}',
    ],
    [
        'name' => 'compressed classes data format',
        'attrs' => 'class="review   packet" data-format="html"',
        'text' => 'Compressed class packet.',
        'classes' => ['review', 'packet'],
        'attributes' => ['format' => 'html'],
        'htmlAttributes' => ['class' => 'review packet', 'data-format' => 'html'],
        'markdownAttrs' => '{.review .packet format="html"}',
    ],
    [
        'name' => 'role aria label',
        'attrs' => 'role="note" aria-label="Review note"',
        'text' => 'Role and aria packet.',
        'attributes' => ['role' => 'note', 'aria-label' => 'Review note'],
        'htmlAttributes' => ['role' => 'note', 'aria-label' => 'Review note'],
        'markdownAttrs' => '{role="note" aria-label="Review note"}',
    ],
    [
        'name' => 'language title',
        'attrs' => 'lang="pl" title="Zrodlo review"',
        'text' => 'Language title packet.',
        'attributes' => ['lang' => 'pl', 'title' => 'Zrodlo review'],
        'htmlAttributes' => ['lang' => 'pl', 'title' => 'Zrodlo review'],
        'markdownAttrs' => '{lang="pl" title="Zrodlo review"}',
    ],
    [
        'name' => 'direction translate',
        'attrs' => 'dir="rtl" translate="no"',
        'text' => 'Direction packet.',
        'attributes' => ['dir' => 'rtl', 'translate' => 'no'],
        'htmlAttributes' => ['dir' => 'rtl', 'translate' => 'no'],
        'markdownAttrs' => '{dir="rtl" translate="no"}',
    ],
    [
        'name' => 'data source path',
        'attrs' => 'data-source-path="reader/html" data-kind="source-path"',
        'text' => 'Source path packet.',
        'attributes' => ['source-path' => 'reader/html', 'kind' => 'source-path'],
        'htmlAttributes' => ['data-source-path' => 'reader/html', 'data-kind' => 'source-path'],
        'markdownAttrs' => '{source-path="reader/html" kind="source-path"}',
    ],
    [
        'name' => 'cite resource data',
        'attrs' => 'cite="https://example.test/review" resource="urn:review:2" data-kind="resource"',
        'text' => 'Citation resource packet.',
        'attributes' => ['cite' => 'https://example.test/review', 'resource' => 'urn:review:2', 'kind' => 'resource'],
        'htmlAttributes' => ['cite' => 'https://example.test/review', 'resource' => 'urn:review:2', 'data-kind' => 'resource'],
        'wordpressAttributes' => ['data-kind' => 'resource'],
        'markdownAttrs' => '{cite="https://example.test/review" resource="urn:review:2" kind="resource"}',
    ],
    [
        'name' => 'style filtered alignment',
        'attrs' => 'style="color: red; text-align:center; border: 0"',
        'text' => 'Style filter packet.',
        'attributes' => ['style' => 'color: red; border: 0'],
        'htmlAttributes' => ['style' => 'color: red; border: 0'],
        'wordpressAttributes' => [],
        'markdownAttrs' => '{style="color: red; border: 0"}',
    ],
    [
        'name' => 'data count rank',
        'attrs' => 'data-count="7" data-rank="02"',
        'text' => 'Count rank packet.',
        'attributes' => ['count' => '7', 'rank' => '02'],
        'htmlAttributes' => ['data-count' => '7', 'data-rank' => '02'],
        'markdownAttrs' => '{count="7" rank="02"}',
    ],
    [
        'name' => 'aria describedby',
        'attrs' => 'aria-describedby="note-a note-b" data-kind="aria"',
        'text' => 'Describedby packet.',
        'attributes' => ['aria-describedby' => 'note-a note-b', 'kind' => 'aria'],
        'htmlAttributes' => ['aria-describedby' => 'note-a note-b', 'data-kind' => 'aria'],
        'markdownAttrs' => '{aria-describedby="note-a note-b" kind="aria"}',
    ],
    [
        'name' => 'id only',
        'attrs' => 'id="only-id"',
        'text' => 'Identifier only packet.',
        'id' => 'only-id',
        'htmlAttributes' => ['id' => 'only-id'],
        'markdownAttrs' => '{#only-id}',
    ],
    [
        'name' => 'class only',
        'attrs' => 'class="content-card"',
        'text' => 'Class only packet.',
        'classes' => ['content-card'],
        'htmlAttributes' => ['class' => 'content-card'],
        'markdownAttrs' => '{.content-card}',
    ],
    [
        'name' => 'title quote',
        'attrs' => 'title="Reviewer &quot;quote&quot;" data-kind="quote"',
        'text' => 'Quoted title packet.',
        'attributes' => ['title' => 'Reviewer "quote"', 'kind' => 'quote'],
        'htmlAttributes' => ['title' => 'Reviewer "quote"', 'data-kind' => 'quote'],
        'markdownAttrs' => '{title="Reviewer \"quote\"" kind="quote"}',
        'markdownContains' => 'title="Reviewer \"quote\""',
    ],
    [
        'name' => 'data ampersand',
        'attrs' => 'data-note="A &amp; B" data-kind="amp"',
        'text' => 'Ampersand packet.',
        'attributes' => ['note' => 'A & B', 'kind' => 'amp'],
        'htmlAttributes' => ['data-note' => 'A & B', 'data-kind' => 'amp'],
        'markdownAttrs' => '{note="A & B" kind="amp"}',
    ],
    [
        'name' => 'xml language',
        'attrs' => 'xml:lang="fr" data-kind="xml-lang"',
        'text' => 'XML language packet.',
        'attributes' => ['xml:lang' => 'fr', 'kind' => 'xml-lang'],
        'htmlAttributes' => ['xml:lang' => 'fr', 'data-kind' => 'xml-lang'],
        'markdownAttrs' => '{xml:lang="fr" kind="xml-lang"}',
    ],
    [
        'name' => 'custom property',
        'attrs' => 'property="schema:name" data-kind="property"',
        'text' => 'Property packet.',
        'attributes' => ['property' => 'schema:name', 'kind' => 'property'],
        'htmlAttributes' => ['property' => 'schema:name', 'data-kind' => 'property'],
        'wordpressAttributes' => ['data-kind' => 'property'],
        'markdownAttrs' => '{property="schema:name" kind="property"}',
    ],
    [
        'name' => 'about datatype',
        'attrs' => 'about="#thing" datatype="Text" data-kind="rdf"',
        'text' => 'RDF packet.',
        'attributes' => ['about' => '#thing', 'datatype' => 'Text', 'kind' => 'rdf'],
        'htmlAttributes' => ['about' => '#thing', 'datatype' => 'Text', 'data-kind' => 'rdf'],
        'wordpressAttributes' => ['data-kind' => 'rdf'],
        'markdownAttrs' => '{about="#thing" datatype="Text" kind="rdf"}',
    ],
    [
        'name' => 'data lane case',
        'attrs' => 'data-lane="pandoc" data-case="native-div"',
        'text' => 'Lane case packet.',
        'attributes' => ['lane' => 'pandoc', 'case' => 'native-div'],
        'htmlAttributes' => ['data-lane' => 'pandoc', 'data-case' => 'native-div'],
        'markdownAttrs' => '{lane="pandoc" case="native-div"}',
    ],
    [
        'name' => 'id class title',
        'attrs' => 'id="review-card" class="card highlight" title="Review card"',
        'text' => 'Card packet.',
        'id' => 'review-card',
        'classes' => ['card', 'highlight'],
        'attributes' => ['title' => 'Review card'],
        'htmlAttributes' => ['id' => 'review-card', 'class' => 'card highlight', 'title' => 'Review card'],
        'markdownAttrs' => '{#review-card .card .highlight title="Review card"}',
    ],
    [
        'name' => 'data depth parent',
        'attrs' => 'data-depth="2" data-parent="root"',
        'text' => 'Depth parent packet.',
        'attributes' => ['depth' => '2', 'parent' => 'root'],
        'htmlAttributes' => ['data-depth' => '2', 'data-parent' => 'root'],
        'markdownAttrs' => '{depth="2" parent="root"}',
    ],
    [
        'name' => 'aria live atomic',
        'attrs' => 'aria-live="polite" aria-atomic="true"',
        'text' => 'Live region packet.',
        'attributes' => ['aria-live' => 'polite', 'aria-atomic' => 'true'],
        'htmlAttributes' => ['aria-live' => 'polite', 'aria-atomic' => 'true'],
        'markdownAttrs' => '{aria-live="polite" aria-atomic="true"}',
    ],
    [
        'name' => 'data column row',
        'attrs' => 'data-row="4" data-column="status"',
        'text' => 'Grid provenance packet.',
        'attributes' => ['row' => '4', 'column' => 'status'],
        'htmlAttributes' => ['data-row' => '4', 'data-column' => 'status'],
        'markdownAttrs' => '{row="4" column="status"}',
    ],
    [
        'name' => 'class language data',
        'attrs' => 'class="locale review" lang="en-US" data-reviewer="editor"',
        'text' => 'Locale reviewer packet.',
        'classes' => ['locale', 'review'],
        'attributes' => ['lang' => 'en-US', 'reviewer' => 'editor'],
        'htmlAttributes' => ['class' => 'locale review', 'lang' => 'en-US', 'data-reviewer' => 'editor'],
        'markdownAttrs' => '{.locale .review lang="en-US" reviewer="editor"}',
    ],
    [
        'name' => 'data start end',
        'attrs' => 'data-start="intro" data-end="summary"',
        'text' => 'Boundary packet.',
        'attributes' => ['start' => 'intro', 'end' => 'summary'],
        'htmlAttributes' => ['data-start' => 'intro', 'data-end' => 'summary'],
        'markdownAttrs' => '{start="intro" end="summary"}',
    ],
    [
        'name' => 'id data status',
        'attrs' => 'id="status-packet" data-status="ready" data-owner="docs"',
        'text' => 'Status owner packet.',
        'id' => 'status-packet',
        'attributes' => ['status' => 'ready', 'owner' => 'docs'],
        'htmlAttributes' => ['id' => 'status-packet', 'data-status' => 'ready', 'data-owner' => 'docs'],
        'markdownAttrs' => '{#status-packet status="ready" owner="docs"}',
    ],
];

foreach ($nativeDivCases as $case) {
    $tests['maps upstream markdown html native div extension ' . $case['name'] . ' with metadata'] =
        static function (TestRunner $t) use ($case, $nativeDivFirst): void {
            $document = (new MarkdownReader())->read(implode("\n", [
                '---',
                'title: Native div **Packet**',
                'review: {extension: native_divs, family: html, kind: block, name: "' . $case['name'] . '"}',
                '...',
                '',
                '<div ' . $case['attrs'] . '>',
                $case['text'],
                '</div>',
            ]));

            $meta = $document->attr('meta');
            $div = $nativeDivFirst($document);
            $paragraph = $div->children[0] ?? new AstNode('missing');
            $attributes = $div->attr('attributes', []);
            $htmlAttributes = $div->attr('htmlAttributes', []);
            $markdown = (new MarkdownWriter())->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('native_divs', $meta['review']['extension'] ?? null);
            $t->same('html', $meta['review']['family'] ?? null);
            $t->same('block', $meta['review']['kind'] ?? null);
            $t->same($case['name'], $meta['review']['name'] ?? null);
            $t->same('div', $div->type);
            $t->same($case['id'] ?? '', $div->attr('id', ''));
            $t->same($case['classes'] ?? [], $div->attr('classes', []));
            $t->same('paragraph', $paragraph->type);
            $t->same($case['text'], $paragraph->attr('text'));
            foreach ($case['attributes'] ?? [] as $name => $value) {
                $t->same($value, $attributes[$name] ?? null, $case['name'] . ' attribute ' . $name);
            }
            foreach ($case['htmlAttributes'] ?? [] as $name => $value) {
                $t->same($value, $htmlAttributes[$name] ?? null, $case['name'] . ' HTML attribute ' . $name);
            }

            $t->contains('::: ' . $case['markdownAttrs'], $markdown);
            $t->contains($case['markdownContains'] ?? $case['markdownAttrs'], $markdown);
            foreach ($case['wordpressAttributes'] ?? $case['htmlAttributes'] ?? [] as $name => $value) {
                $t->contains($name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"', $blocks, $case['name'] . ' WordPress attribute ' . $name);
            }
            $t->contains('<p>' . htmlspecialchars($case['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>', $blocks);
        };

    $tests['round trips upstream markdown html native div extension ' . $case['name'] . ' through json native and markdown writers'] =
        static function (TestRunner $t) use ($case, $nativeDivFirst): void {
            $document = (new MarkdownReader())->read(implode("\n", [
                '<div ' . $case['attrs'] . '>',
                $case['text'],
                '</div>',
            ]));
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
            $markdownRoundTrip = (new MarkdownReader())->read((new MarkdownWriter())->write($document));

            $roundTrips = [
                'json' => (new PandocJsonReader())->readPacket($jsonPacket),
                'native' => (new NativeReader())->read(json_encode($nativePacket, JSON_THROW_ON_ERROR)),
                'markdown' => $markdownRoundTrip,
            ];

            foreach ($roundTrips as $source => $roundTrip) {
                $div = $nativeDivFirst($roundTrip);
                $paragraph = $div->children[0] ?? new AstNode('missing');
                $attributes = $div->attr('attributes', []);
                $markdown = (new MarkdownWriter())->write($roundTrip);

                $t->same('div', $div->type, "{$source} div node");
                $t->same($case['id'] ?? '', $div->attr('id', ''), "{$source} div id");
                $t->same($case['classes'] ?? [], $div->attr('classes', []), "{$source} div classes");
                foreach ($case['attributes'] ?? [] as $name => $value) {
                    $t->same($value, $attributes[$name] ?? null, "{$source} {$case['name']} attribute {$name}");
                }
                $t->same('paragraph', $paragraph->type, "{$source} paragraph node");
                $t->same($case['text'], $paragraph->attr('text'), "{$source} paragraph text");
                $t->contains('::: ' . $case['markdownAttrs'], $markdown, "{$source} Markdown div attributes");
            }
        };
}

return $tests;
