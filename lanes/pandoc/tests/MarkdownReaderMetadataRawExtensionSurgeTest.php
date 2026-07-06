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
        'tex' => $kind === 'inline' ? 'raw_tex_inline' : 'raw_tex',
        default => 'raw_markdown',
    };
};

$rawSurgeInitialInlineNodeType = static function (array $case): string {
    return $case['family'] === 'html' ? 'raw_html_inline' : 'raw_inline';
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
        static function (TestRunner $t) use ($case, $rawSurgeInitialInlineNodeType, $rawSurgeInlinePayload, $rawSurgeTextAttr): void {
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
            $t->same($rawSurgeInitialInlineNodeType($case), $raw->type);
            $t->same($format, $raw->attr('format'));
            $t->same($rawText, $rawSurgeTextAttr($raw, $case));
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

$rawSurgeAttributeTokenVariants = [
    'leading token after id class' => static function (array $case) use ($rawSurgeSlug): string {
        $slug = $rawSurgeSlug($case['format']);

        return '{#raw-' . $slug
            . ' .raw-token .' . $case['family']
            . ' =' . $case['format']
            . ' data-family="' . $case['family'] . '"'
            . ' data-format="' . $case['format'] . '"}';
    },
    'trailing token after quoted attributes' => static function (array $case) use ($rawSurgeSlug): string {
        $slug = $rawSurgeSlug($case['format']);

        return '{.raw-token'
            . ' data-family="' . $case['family'] . '"'
            . ' data-format="' . $case['format'] . '"'
            . ' title="Raw attribute ' . $slug . '"'
            . ' =' . $case['format'] . '}';
    },
];

foreach ($rawSurgeCases as $case) {
    foreach ($rawSurgeAttributeTokenVariants as $variant => $rawSurgeAttributeSource) {
        $tests['maps upstream markdown fenced raw format attribute token ' . $variant . ' ' . $case['format']] =
            static function (TestRunner $t) use (
                $case,
                $rawSurgeAttributeSource,
                $rawSurgeBlockPayload,
                $rawSurgeExpectedNodeType,
                $rawSurgeReviewValue,
                $rawSurgeTextAttr
            ): void {
                $format = $case['format'];
                $rawText = $rawSurgeBlockPayload($case);
                $document = (new MarkdownReader())->read(implode("\n", [
                    '---',
                    'title: Raw format attribute token',
                    'review: {format: "' . $format . '", family: ' . $case['family'] . ', channel: attribute-token}',
                    '...',
                    '',
                    '```' . $rawSurgeAttributeSource($case),
                    $rawText,
                    '```',
                    '',
                    'After raw token block.',
                ]));
                $raw = $document->children[0] ?? new AstNode('missing');
                $paragraph = $document->children[1] ?? new AstNode('missing');

                $t->same('raw_block', $raw->type);
                $t->same($format, $raw->attr('format'));
                $t->same($rawText, $raw->attr('text'));
                $t->same('paragraph', $paragraph->type);
                $t->same('After raw token block.', $paragraph->attr('text'));

                $jsonPacket = (new PandocJsonWriter())->toArray($document);
                $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
                $roundTrips = [
                    'json' => (new PandocJsonReader())->readPacket($jsonPacket),
                    'native' => (new NativeReader())->read(json_encode($nativePacket, JSON_THROW_ON_ERROR)),
                ];

                foreach ($roundTrips as $source => $roundTrip) {
                    $meta = $roundTrip->attr('meta');
                    $roundTripRaw = $roundTrip->children[0] ?? new AstNode('missing');
                    $markdown = (new MarkdownWriter())->write($roundTrip);

                    $t->same($format, $rawSurgeReviewValue($meta, 'format'), "{$source} metadata format");
                    $t->same($case['family'], $rawSurgeReviewValue($meta, 'family'), "{$source} metadata family");
                    $t->same('attribute-token', $rawSurgeReviewValue($meta, 'channel'), "{$source} metadata channel");
                    $t->same($rawSurgeExpectedNodeType($case, 'block'), $roundTripRaw->type, "{$source} raw block family node");
                    $t->same($format, $roundTripRaw->attr('format'), "{$source} raw block format");
                    $t->same($rawText, $rawSurgeTextAttr($roundTripRaw, $case), "{$source} raw block text");
                    $t->contains($rawText, $markdown);
                }

                $blocks = (new WordPressBlockWriter())->write($document);
                if ($case['family'] === 'html') {
                    $t->contains('<!-- wp:html -->' . "\n" . $rawText . "\n" . '<!-- /wp:html -->', $blocks);
                } elseif ($case['family'] === 'tex') {
                    $t->contains(
                        '<pre class="wp-block-code"><code class="language-tex">'
                        . htmlspecialchars($rawText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                        . '</code></pre>',
                        $blocks
                    );
                }
            };
    }
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

$nativeSpanExtensionFormatCases = [
    ['name' => 'top level extension scalar', 'metadata' => ['extension: native_spans']],
    ['name' => 'top level extensions flow list', 'metadata' => ['extensions: [native_spans, raw_html]']],
    ['name' => 'top level extensions block list', 'metadata' => ['extensions:', '  - raw_html', '  - native_spans']],
    ['name' => 'top level markdown format plus extension', 'metadata' => ['format: "markdown+native_spans"']],
    ['name' => 'top level commonmark x format plus extension', 'metadata' => ['format: "commonmark_x+native_spans"']],
    ['name' => 'top level gfm format plus extension', 'metadata' => ['format: "gfm+native_spans"']],
    ['name' => 'top level format with raw html plus native spans', 'metadata' => ['format: "markdown+raw_html+native_spans"']],
    ['name' => 'top level from field format', 'metadata' => ['from: "markdown+native_spans"']],
    ['name' => 'top level input format field', 'metadata' => ['inputFormat: "markdown+native_spans"']],
    ['name' => 'top level reader format field', 'metadata' => ['readerFormat: "commonmark_x+native_spans"']],
    ['name' => 'top level markdown extensions flow list', 'metadata' => ['markdownExtensions: [raw_html, native_spans]']],
    ['name' => 'space separated extension scalar', 'metadata' => ['extension: "raw_html native_spans"']],
    ['name' => 'comma separated extension scalar', 'metadata' => ['extension: "raw_html,native_spans"']],
    ['name' => 'explicit plus extension scalar', 'metadata' => ['extension: "+native_spans"']],
    ['name' => 'format disables divs but enables spans', 'metadata' => ['format: "markdown-native_divs+native_spans"']],
    ['name' => 'format emoji plus native spans', 'metadata' => ['format: "markdown+emoji+native_spans"']],
    ['name' => 'review extension scalar', 'metadata' => ['review: {extension: native_spans, source: review-extension}']],
    ['name' => 'review extensions flow list', 'metadata' => ['review: {extensions: [native_spans, raw_html], source: review-extensions}']],
    ['name' => 'review format field', 'metadata' => ['review: {format: "markdown+native_spans", source: review-format}']],
    ['name' => 'review from field', 'metadata' => ['review: {from: "gfm+native_spans", source: review-from}']],
    ['name' => 'review input format field', 'metadata' => ['review: {inputFormat: "markdown+native_spans", source: review-input-format}']],
    ['name' => 'review reader format field', 'metadata' => ['review: {readerFormat: "commonmark_x+native_spans", source: review-reader-format}']],
    ['name' => 'source extension scalar', 'metadata' => ['source: {extension: native_spans, channel: source-extension}']],
    ['name' => 'source format field', 'metadata' => ['source: {format: "markdown+native_spans", channel: source-format}']],
    ['name' => 'source extensions list', 'metadata' => ['source: {extensions: [raw_html, native_spans], channel: source-extensions}']],
    ['name' => 'reader extension scalar', 'metadata' => ['reader: {extension: native_spans, channel: reader-extension}']],
    ['name' => 'reader format field', 'metadata' => ['reader: {format: "markdown+native_spans", channel: reader-format}']],
    ['name' => 'reader extensions list', 'metadata' => ['reader: {extensions: [native_spans], channel: reader-extensions}']],
    ['name' => 'input extension scalar', 'metadata' => ['input: {extension: native_spans, channel: input-extension}']],
    ['name' => 'input format field', 'metadata' => ['input: {format: "commonmark_x+native_spans", channel: input-format}']],
    ['name' => 'input extension flag map', 'metadata' => ['input: {extensions: {native_spans: true, raw_html: true}, channel: input-flag-map}']],
    ['name' => 'pandoc extension scalar', 'metadata' => ['pandoc: {extension: native_spans, channel: pandoc-extension}']],
    ['name' => 'pandoc gfm format field', 'metadata' => ['pandoc: {format: "gfm+native_spans", channel: pandoc-format}']],
    ['name' => 'pandoc extensions list', 'metadata' => ['pandoc: {extensions: [native_spans, emoji], channel: pandoc-extensions}']],
    ['name' => 'options extension scalar', 'metadata' => ['options: {extension: native_spans, channel: options-extension}']],
    ['name' => 'options extensions list', 'metadata' => ['options: {extensions: [native_spans], channel: options-extensions}']],
    ['name' => 'options format field', 'metadata' => ['options: {format: "markdown+native_spans", channel: options-format}']],
    ['name' => 'top level extension flag map', 'metadata' => ['extensions: {native_spans: true, raw_html: true}']],
    ['name' => 'review extension flag map', 'metadata' => ['review: {extensions: {native_spans: true, raw_html: true}, source: review-flag-map}']],
    ['name' => 'source extension flag map', 'metadata' => ['source: {extensions: {native_spans: true, raw_html: true}, channel: source-flag-map}']],
    ['name' => 'reader extension flag map', 'metadata' => ['reader: {extensions: {native_spans: true, raw_html: true}, channel: reader-flag-map}']],
    ['name' => 'input extension flag map with string true', 'metadata' => ['input: {extensions: {native_spans: "enabled", raw_html: true}, channel: input-string-map}']],
    ['name' => 'pandoc extension flag map', 'metadata' => ['pandoc: {extensions: {native_spans: true, emoji: true}, channel: pandoc-flag-map}']],
    ['name' => 'options extension flag map', 'metadata' => ['options: {extensions: {native_spans: true}, channel: options-flag-map}']],
    ['name' => 'format plus spans minus divs', 'metadata' => ['format: "markdown+native_spans-native_divs"']],
    ['name' => 'format raw html plus spans plus emoji', 'metadata' => ['format: "markdown-native_divs+raw_html+native_spans+emoji"']],
    ['name' => 'extension list containing format string', 'metadata' => ['extensions:', '  - raw_html', '  - "markdown+native_spans"']],
    ['name' => 'review raw attribute format string', 'metadata' => ['review: {format: "markdown+native_spans+raw_attribute", source: review-raw-attribute}']],
    ['name' => 'source native div span format string', 'metadata' => ['source: {format: "markdown+native_spans+native_divs", channel: source-native-div-span}']],
    ['name' => 'reader from format string', 'metadata' => ['reader: {from: "markdown+native_spans", channel: reader-from}']],
];

foreach ($nativeSpanExtensionFormatCases as $index => $case) {
    $tests['maps upstream markdown native span source extension format ' . $case['name']] =
        static function (TestRunner $t) use ($case, $index, $nativeSpanFirst, $nativeSpanInlineText): void {
            $slug = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($case['name'])), '-');
            $spanId = 'span-format-' . ($index + 1);
            $spanText = 'extension format ' . $slug;
            $metadata = array_merge(
                [
                    '---',
                    'title: Native span source extension **Packet**',
                    'extensionCase: "' . $case['name'] . '"',
                ],
                $case['metadata'],
                [
                    '...',
                    '',
                    'Before <span id="' . $spanId . '" class="format-case" data-case="' . $slug . '">' . $spanText . '</span> after.',
                ]
            );

            $document = (new MarkdownReader())->read(implode("\n", $metadata));
            $meta = $document->attr('meta');
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $span = $nativeSpanFirst($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same($case['name'], $meta['extensionCase'] ?? null);
            $t->same('paragraph', $paragraph->type);
            $t->same('span', $span->type);
            $t->same($spanId, $span->attr('id'));
            $t->same(['format-case'], $span->attr('classes', []));
            $t->same($slug, $span->attr('attributes', [])['case'] ?? null);
            $t->same($spanText, $nativeSpanInlineText($span));
            $t->contains('<span id="' . $spanId . '" class="format-case" data-case="' . $slug . '">', $blocks);
            $t->contains($spanText, $blocks);
        };
}

$tests['records upstream markdown native span source extension format surge mapped-case count'] =
    static function (TestRunner $t) use ($nativeSpanExtensionFormatCases): void {
        $t->same(50, count($nativeSpanExtensionFormatCases));
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
        'wordpressAttributes' => ['role' => 'note', 'style' => 'color:red'],
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

$nativeSpanMetadataSlug = static function (string $name): string {
    return trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($name)), '-');
};

$nativeSpanMetadataExtensionCases = [
    ['name' => 'extension format plus', 'metadata' => ['extension: markdown+native_spans']],
    ['name' => 'extension space list', 'metadata' => ['extension: raw_html native_spans']],
    ['name' => 'extension comma list', 'metadata' => ['extension: raw_html,native_spans']],
    ['name' => 'extension spaced plus list', 'metadata' => ['extension: raw_html + native_spans']],
    ['name' => 'extensions flow list', 'metadata' => ['extensions: [raw_html, native_spans]']],
    ['name' => 'extensions scalar', 'metadata' => ['extensions: native_spans']],
    ['name' => 'enabled extensions flow list', 'metadata' => ['enabledExtensions: [smart, native_spans]']],
    ['name' => 'reader extensions camel list', 'metadata' => ['readerExtensions: [native_spans, link_attributes]']],
    ['name' => 'reader extensions snake list', 'metadata' => ['reader_extensions: [emoji, native_spans]']],
    ['name' => 'format markdown plus', 'metadata' => ['format: markdown+native_spans']],
    ['name' => 'format pandoc plus', 'metadata' => ['format: pandoc+native_spans']],
    ['name' => 'format commonmark x plus', 'metadata' => ['format: commonmark_x+native_spans']],
    ['name' => 'format gfm plus', 'metadata' => ['format: gfm+native_spans']],
    ['name' => 'from markdown plus', 'metadata' => ['from: markdown+native_spans']],
    ['name' => 'input format camel plus', 'metadata' => ['inputFormat: markdown+native_spans']],
    ['name' => 'input format snake plus', 'metadata' => ['input_format: commonmark_x+native_spans']],
    ['name' => 'review extensions list', 'metadata' => ['review: {extensions: [native_spans, raw_html]}']],
    ['name' => 'review enabled extensions list', 'metadata' => ['review: {enabledExtensions: [native_spans]}']],
    ['name' => 'review reader extensions camel', 'metadata' => ['review: {readerExtensions: [native_spans]}']],
    ['name' => 'review reader extensions snake', 'metadata' => ['review: {reader_extensions: [native_spans]}']],
    ['name' => 'review format plus', 'metadata' => ['review: {format: markdown+native_spans}']],
    ['name' => 'review from plus', 'metadata' => ['review: {from: gfm+native_spans}']],
    ['name' => 'review input format camel', 'metadata' => ['review: {inputFormat: commonmark_x+native_spans}']],
    ['name' => 'review input format snake', 'metadata' => ['review: {input_format: markdown+native_spans}']],
    ['name' => 'reader extensions list', 'metadata' => ['reader: {extensions: [native_spans]}']],
    ['name' => 'reader format plus', 'metadata' => ['reader: {format: markdown+native_spans}']],
    ['name' => 'reader from plus', 'metadata' => ['reader: {from: markdown+native_spans}']],
    ['name' => 'reader snake extensions', 'metadata' => ['reader: {reader_extensions: [native_spans]}']],
    ['name' => 'input extensions list', 'metadata' => ['input: {extensions: [native_spans]}']],
    ['name' => 'input format plus', 'metadata' => ['input: {format: markdown+native_spans}']],
    ['name' => 'input snake format plus', 'metadata' => ['input: {input_format: markdown+native_spans}']],
    ['name' => 'source extensions list', 'metadata' => ['source: {extensions: [native_spans]}']],
    ['name' => 'source format plus', 'metadata' => ['source: {format: markdown+native_spans}']],
    ['name' => 'source reader extensions list', 'metadata' => ['source: {readerExtensions: [emoji, native_spans]}']],
    ['name' => 'pandoc reader extensions list', 'metadata' => ['pandoc: {readerExtensions: [native_spans]}']],
    ['name' => 'pandoc input format plus', 'metadata' => ['pandoc: {inputFormat: markdown+native_spans}']],
    ['name' => 'pandoc format plus', 'metadata' => ['pandoc: {format: commonmark_x+native_spans}']],
    ['name' => 'pandoc extensions list', 'metadata' => ['pandoc: {extensions: [smart, raw_html, native_spans]}']],
    ['name' => 'extensions flow map boolean', 'metadata' => ['extensions: {native_spans: true}']],
    ['name' => 'extensions flow map yes', 'metadata' => ['extensions: {native_spans: yes}']],
    ['name' => 'extensions flow map on', 'metadata' => ['extensions: {native_spans: "on"}']],
    ['name' => 'extensions flow map one', 'metadata' => ['extensions: {native_spans: 1}']],
    ['name' => 'reader extensions flow map', 'metadata' => ['readerExtensions: {native_spans: enabled}']],
    ['name' => 'review extensions flow map', 'metadata' => ['review: {extensions: {native_spans: true}}']],
    ['name' => 'input extensions flow map', 'metadata' => ['input: {extensions: {native_spans: true}}']],
    ['name' => 'reader enabled extensions map', 'metadata' => ['reader: {enabledExtensions: {native_spans: true}}']],
    ['name' => 'source snake extensions map', 'metadata' => ['source: {reader_extensions: {native_spans: true}}']],
    ['name' => 'pandoc extensions flow map', 'metadata' => ['pandoc: {extensions: {native_spans: true}}']],
    ['name' => 'format multi extension plus', 'metadata' => ['format: markdown+raw_html+native_spans']],
    ['name' => 'from multi extension plus', 'metadata' => ['from: gfm+emoji+native_spans']],
];

foreach ($nativeSpanMetadataExtensionCases as $case) {
    $tests['maps upstream markdown metadata native span enablement ' . $case['name']] =
        static function (TestRunner $t) use ($case, $nativeSpanMetadataSlug, $nativeSpanFirst, $nativeSpanInlineText): void {
            $slug = $nativeSpanMetadataSlug($case['name']);
            $source = array_merge([
                '---',
                'title: Native span metadata packet',
            ], $case['metadata'], [
                '...',
                '',
                'Before <span class="meta-span" data-case="' . $slug . '">extension ' . $slug . ' <strong>packet</strong></span> after.',
            ]);
            $document = (new MarkdownReader())->read(implode("\n", $source));
            $span = $nativeSpanFirst($document);
            $markdown = (new MarkdownWriter())->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);
            $roundTripDocuments = [
                'json' => (new PandocJsonReader())->readPacket((new PandocJsonWriter())->toArray($document)),
                'native' => (new NativeReader())->read((new NativeWriter())->write($document)),
            ];

            $t->same('span', $span->type, $case['name']);
            $t->same(['meta-span'], $span->attr('classes'), $case['name'] . ' classes');
            $t->same(['case' => $slug], $span->attr('attributes'), $case['name'] . ' attributes');
            $t->same(['class' => 'meta-span', 'data-case' => $slug], $span->attr('htmlAttributes'), $case['name'] . ' html attributes');
            $t->same('extension ' . $slug . ' packet', $nativeSpanInlineText($span), $case['name'] . ' text');
            $t->contains('[extension ' . $slug . ' **packet**]{.meta-span case="' . $slug . '"}', $markdown);
            $t->contains('<span class="meta-span" data-case="' . $slug . '">extension ' . $slug . ' <strong>packet</strong></span>', $blocks);

            foreach ($roundTripDocuments as $sourceName => $roundTrip) {
                $roundTripSpan = $nativeSpanFirst($roundTrip);

                $t->same('span', $roundTripSpan->type, $case['name'] . ' ' . $sourceName . ' type');
                $t->same(['meta-span'], $roundTripSpan->attr('classes'), $case['name'] . ' ' . $sourceName . ' classes');
                $t->same(['case' => $slug], $roundTripSpan->attr('attributes'), $case['name'] . ' ' . $sourceName . ' attributes');
                $t->same('extension ' . $slug . ' packet', $nativeSpanInlineText($roundTripSpan), $case['name'] . ' ' . $sourceName . ' text');
            }
        };
}

$tests['records upstream markdown metadata native span enablement mapped-case count'] =
    static function (TestRunner $t) use ($nativeSpanMetadataExtensionCases): void {
        $t->same(50, count($nativeSpanMetadataExtensionCases));
    };

$nativeDivAttributeSource = static function (array $attributes): string {
    $parts = [];
    foreach ($attributes as $name => $value) {
        $parts[] = $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    return implode(' ', $parts);
};

$nativeDivExpectedAttrs = static function (array $htmlAttributes): array {
    $id = '';
    $classes = [];
    $attributes = [];
    $normalizedHtmlAttributes = [];

    foreach ($htmlAttributes as $name => $value) {
        $name = strtolower((string) $name);
        $value = trim((string) $value);
        if ($name === 'id') {
            $id = $value;
            if ($value !== '') {
                $normalizedHtmlAttributes['id'] = $value;
            }
            continue;
        }

        if ($name === 'class') {
            $classes = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($classes !== []) {
                $normalizedHtmlAttributes['class'] = implode(' ', $classes);
            }
            continue;
        }

        $attributeName = str_starts_with($name, 'data-') ? substr($name, 5) : $name;
        if ($attributeName === '') {
            continue;
        }

        $attributes[$attributeName] = $value;
        $normalizedHtmlAttributes[$name] = $value;
    }

    return [
        'id' => $id,
        'classes' => $classes,
        'attributes' => $attributes,
        'htmlAttributes' => $normalizedHtmlAttributes,
    ];
};

$nativeDivSlug = static function (string $name): string {
    return trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($name)), '-');
};

$nativeDivCases = [
    ['name' => 'id class data review', 'htmlAttributes' => ['id' => 'div-alpha', 'class' => 'review primary', 'data-review' => 'alpha']],
    ['name' => 'class lang', 'htmlAttributes' => ['class' => 'locale', 'lang' => 'pl']],
    ['name' => 'title data index', 'htmlAttributes' => ['title' => 'Review title', 'data-index' => '7']],
    ['name' => 'direction aria label', 'htmlAttributes' => ['dir' => 'rtl', 'aria-label' => 'Direction review']],
    ['name' => 'role data kind', 'htmlAttributes' => ['role' => 'note', 'data-kind' => 'role']],
    ['name' => 'aria describedby', 'htmlAttributes' => ['aria-describedby' => 'note-a note-b', 'data-kind' => 'aria']],
    ['name' => 'translate no', 'htmlAttributes' => ['translate' => 'no', 'data-token' => 'literal']],
    ['name' => 'xml lang locale', 'htmlAttributes' => ['xml:lang' => 'pl', 'data-locale' => 'pl-PL']],
    ['name' => 'two data attrs', 'htmlAttributes' => ['data-lane' => 'pandoc', 'data-case' => 'native-div']],
    ['name' => 'compressed classes', 'htmlAttributes' => ['class' => 'primary   secondary', 'data-kind' => 'classes']],
    ['name' => 'data source path', 'htmlAttributes' => ['data-source-path' => 'markdown-reader', 'data-kind' => 'source']],
    ['name' => 'format extension packet', 'htmlAttributes' => ['class' => 'packet', 'data-format' => 'markdown', 'data-extension' => 'native_divs']],
    ['name' => 'language direction', 'htmlAttributes' => ['lang' => 'he', 'dir' => 'rtl', 'data-direction' => 'rtl']],
    ['name' => 'live region state', 'htmlAttributes' => ['role' => 'status', 'aria-live' => 'polite', 'data-state' => 'queued']],
    ['name' => 'controls panel', 'htmlAttributes' => ['aria-controls' => 'panel-a', 'data-panel' => 'a']],
    ['name' => 'expanded toggle', 'htmlAttributes' => ['aria-expanded' => 'true', 'data-toggle' => 'expanded']],
    ['name' => 'current nav', 'htmlAttributes' => ['aria-current' => 'page', 'data-nav' => 'current']],
    ['name' => 'details reference', 'htmlAttributes' => ['aria-details' => 'details-a', 'data-details' => 'a']],
    ['name' => 'hidden state', 'htmlAttributes' => ['aria-hidden' => 'true', 'data-visibility' => 'hidden']],
    ['name' => 'keyboard command', 'htmlAttributes' => ['aria-keyshortcuts' => 'Control+K', 'data-command' => 'palette']],
    ['name' => 'role description', 'htmlAttributes' => ['aria-roledescription' => 'review card', 'data-card' => 'summary']],
    ['name' => 'pressed button state', 'htmlAttributes' => ['aria-pressed' => 'false', 'data-button' => 'filter']],
    ['name' => 'quoted greater title', 'htmlAttributes' => ['title' => 'a > b', 'data-boundary' => 'quote-aware']],
    ['name' => 'entity title', 'htmlAttributes' => ['title' => 'A & B', 'data-entity' => 'ampersand']],
    ['name' => 'review stage', 'htmlAttributes' => ['data-review-id' => '42', 'data-review-stage' => 'draft']],
    ['name' => 'line range', 'htmlAttributes' => ['data-start-line' => '10', 'data-end-line' => '20']],
    ['name' => 'source origin', 'htmlAttributes' => ['data-source' => 'batch', 'data-origin' => 'html']],
    ['name' => 'batch index', 'htmlAttributes' => ['data-batch' => '20260615', 'data-index' => '28']],
    ['name' => 'slug family', 'htmlAttributes' => ['data-slug' => 'native-div', 'data-family' => 'markdown']],
    ['name' => 'mode variant', 'htmlAttributes' => ['data-mode' => 'reader', 'data-variant' => 'html-div']],
    ['name' => 'callout note role', 'htmlAttributes' => ['class' => 'callout note', 'role' => 'note', 'data-alert' => 'note']],
    ['name' => 'grid layout', 'htmlAttributes' => ['class' => 'grid two-column', 'data-layout' => 'two-column']],
    ['name' => 'uuid packet', 'htmlAttributes' => ['id' => 'packet-33', 'data-uuid' => 'review-33']],
    ['name' => 'language tag', 'htmlAttributes' => ['lang' => 'en-US', 'data-language' => 'english']],
    ['name' => 'arabic script direction', 'htmlAttributes' => ['dir' => 'rtl', 'lang' => 'ar', 'data-script' => 'arabic']],
    ['name' => 'dual locale', 'htmlAttributes' => ['xml:lang' => 'fr', 'lang' => 'fr', 'data-locale' => 'fr-FR']],
    ['name' => 'label title packet', 'htmlAttributes' => ['aria-label' => 'Review packet', 'title' => 'Review packet']],
    ['name' => 'note references', 'htmlAttributes' => ['aria-describedby' => 'note-a note-b', 'data-note' => 'two']],
    ['name' => 'heading references', 'htmlAttributes' => ['aria-labelledby' => 'heading-a heading-b', 'data-heading' => 'two']],
    ['name' => 'region label', 'htmlAttributes' => ['role' => 'region', 'aria-label' => 'Region packet', 'data-region' => 'main']],
    ['name' => 'group label', 'htmlAttributes' => ['role' => 'group', 'aria-label' => 'Group packet', 'data-group' => 'controls']],
    ['name' => 'doc abstract role', 'htmlAttributes' => ['role' => 'doc-abstract', 'data-doc' => 'abstract']],
    ['name' => 'complementary sidebar', 'htmlAttributes' => ['role' => 'complementary', 'data-sidebar' => 'related']],
    ['name' => 'translated language', 'htmlAttributes' => ['translate' => 'no', 'lang' => 'pl', 'data-translate' => 'locked']],
    ['name' => 'import path', 'htmlAttributes' => ['data-import-path' => 'posts/2026/review', 'data-import-kind' => 'post']],
    ['name' => 'mime format', 'htmlAttributes' => ['data-mime' => 'text/markdown', 'data-format' => 'markdown']],
    ['name' => 'checksum size', 'htmlAttributes' => ['data-checksum' => 'sha256-abc123', 'data-size' => '42']],
    ['name' => 'status classes', 'htmlAttributes' => ['data-status' => 'needs-review', 'class' => 'status review']],
    ['name' => 'native identity', 'htmlAttributes' => ['id' => 'native-div-49', 'class' => 'native div packet', 'data-native' => 'true']],
    ['name' => 'final packet', 'htmlAttributes' => ['data-final' => 'true', 'aria-label' => 'Final packet']],
];

foreach ($nativeDivCases as $case) {
    $tests['maps upstream markdown native div extension ' . $case['name'] . ' with metadata'] =
        static function (TestRunner $t) use ($case, $nativeDivAttributeSource, $nativeDivExpectedAttrs, $nativeDivSlug): void {
            $slug = $nativeDivSlug($case['name']);
            $content = 'Native div ' . $slug . ' with **strong** child.';
            $document = (new MarkdownReader())->read(implode("\n", [
                '---',
                'title: Native div **Packet**',
                'review: {extension: native_divs, family: html, kind: block, name: "' . $case['name'] . '"}',
                '...',
                '',
                '<div ' . $nativeDivAttributeSource($case['htmlAttributes']) . '>' . $content . '</div>',
            ]));

            $meta = $document->attr('meta');
            $div = $document->children[0] ?? new AstNode('missing');
            $plain = $div->children[0] ?? new AstNode('missing');
            $expected = $nativeDivExpectedAttrs($case['htmlAttributes']);
            $markdown = (new MarkdownWriter())->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('native_divs', $meta['review']['extension'] ?? null);
            $t->same('html', $meta['review']['family'] ?? null);
            $t->same('block', $meta['review']['kind'] ?? null);
            $t->same($case['name'], $meta['review']['name'] ?? null);
            $t->same('div', $div->type);
            $t->same($expected['id'], $div->attr('id', ''));
            $t->same($expected['classes'], $div->attr('classes', []));
            $t->same($expected['attributes'], $div->attr('attributes', []));
            $t->same($expected['htmlAttributes'], $div->attr('htmlAttributes', []));
            $t->same('plain', $plain->type);
            $t->same(['text', 'strong', 'text'], array_map(static fn (AstNode $node): string => $node->type, $plain->children));
            $t->same('strong', $plain->children[1]->children[0]->attr('text'));
            $t->contains('::: ', $markdown);
            $t->contains($content, $markdown);
            $t->contains('<div', $blocks);
            $t->contains('Native div ' . $slug . ' with <strong>strong</strong> child.', $blocks);

            foreach ($expected['htmlAttributes'] as $name => $value) {
                if ($name === 'class') {
                    $t->contains(
                        'class="wp-block-group ' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"',
                        $blocks,
                        $case['name'] . ' WordPress group class'
                    );
                    continue;
                }

                $t->contains(
                    $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"',
                    $blocks,
                    $case['name'] . ' WordPress attribute ' . $name
                );
            }
        };
}

$tests['records upstream markdown native div extension surge mapped-case count'] =
    static function (TestRunner $t) use ($nativeDivCases): void {
        $t->same(50, count($nativeDivCases));
    };

return $tests;
