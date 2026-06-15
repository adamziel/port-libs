<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$rawAttributeSpacingCases = [
    ['format' => 'html', 'family' => 'html', 'spec' => '{ = html }'],
    ['format' => 'html4', 'family' => 'html', 'spec' => '{= html4 }'],
    ['format' => 'html5', 'family' => 'html', 'spec' => '{ =html5}'],
    ['format' => 'xhtml', 'family' => 'html', 'spec' => "{\t=\txhtml }"],
    ['format' => 'html+raw_html', 'family' => 'html', 'spec' => '{ = html+raw_html }'],
    ['format' => 'html5+smart', 'family' => 'html', 'spec' => '{ = html5+smart }'],
    ['format' => 'tex', 'family' => 'tex', 'spec' => '{ = tex }'],
    ['format' => 'latex', 'family' => 'tex', 'spec' => '{= latex }'],
    ['format' => 'context', 'family' => 'tex', 'spec' => '{ =context }'],
    ['format' => 'latex+raw_tex', 'family' => 'tex', 'spec' => "{\t=\tlatex+raw_tex }"],
    ['format' => 'tex-macros', 'family' => 'tex', 'spec' => '{ = tex-macros }'],
    ['format' => 'context+raw_tex', 'family' => 'tex', 'spec' => '{ = context+raw_tex }'],
    ['format' => 'latex-smart', 'family' => 'tex', 'spec' => '{ = latex-smart }'],
    ['format' => 'markdown', 'family' => 'markdown', 'spec' => '{ = markdown }'],
    ['format' => 'markdown_strict', 'family' => 'markdown', 'spec' => '{= markdown_strict }'],
    ['format' => 'markdown_phpextra', 'family' => 'markdown', 'spec' => '{ =markdown_phpextra}'],
    ['format' => 'markdown_github', 'family' => 'markdown', 'spec' => "{\t=\tmarkdown_github }"],
    ['format' => 'markdown_mmd', 'family' => 'markdown', 'spec' => '{ = markdown_mmd }'],
    ['format' => 'pandoc', 'family' => 'markdown', 'spec' => '{ = pandoc }'],
    ['format' => 'commonmark', 'family' => 'markdown', 'spec' => '{ = commonmark }'],
    ['format' => 'commonmark_x', 'family' => 'markdown', 'spec' => '{= commonmark_x }'],
    ['format' => 'gfm', 'family' => 'markdown', 'spec' => '{ =gfm }'],
    ['format' => 'markdown+emoji', 'family' => 'markdown', 'spec' => "{\t=\tmarkdown+emoji }"],
    ['format' => 'pandoc-smart', 'family' => 'markdown', 'spec' => '{ = pandoc-smart }'],
    ['format' => 'gfm+pipe_tables', 'family' => 'markdown', 'spec' => '{ = gfm+pipe_tables }'],
];

$rawAttributeSpacingSlug = static function (string $format): string {
    return trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($format)), '-');
};

$rawAttributeSpacingBlockPayload = static function (array $case) use ($rawAttributeSpacingSlug): string {
    $slug = $rawAttributeSpacingSlug($case['format']);

    return match ($case['family']) {
        'html' => '<aside data-raw-spacing="' . $slug . '"><p>block ' . $slug . '</p></aside>',
        'tex' => '\\begin{quote}' . "\n" . 'block-' . $slug . "\n" . '\\end{quote}',
        default => '### block-' . $slug . "\n\n" . 'Raw markdown spacing packet.',
    };
};

$rawAttributeSpacingInlinePayload = static function (array $case) use ($rawAttributeSpacingSlug): string {
    $slug = $rawAttributeSpacingSlug($case['format']);

    return match ($case['family']) {
        'html' => '<span data-raw-spacing="' . $slug . '">inline ' . $slug . '</span>',
        'tex' => '\\emph{inline-' . $slug . '}',
        default => '**inline-' . $slug . '**',
    };
};

$tests = [];

foreach ($rawAttributeSpacingCases as $case) {
    $tests['maps upstream markdown spaced raw block attribute format ' . $case['format'] . ' with metadata'] =
        static function (TestRunner $t) use ($case, $rawAttributeSpacingBlockPayload): void {
            $rawText = $rawAttributeSpacingBlockPayload($case);
            $document = (new MarkdownReader())->read(implode("\n", [
                '---',
                'title: Raw block spacing **Packet**',
                'review: {extension: raw_attribute, spacing: true, format: "' . $case['format'] . '", family: ' . $case['family'] . ', kind: block}',
                '...',
                '',
                '``` ' . $case['spec'],
                $rawText,
                '```',
                '',
                'After spaced raw block.',
            ]));

            $meta = $document->attr('meta');
            $raw = $document->children[0] ?? new AstNode('missing');
            $paragraph = $document->children[1] ?? new AstNode('missing');
            $markdown = (new MarkdownWriter())->write($document);

            $t->same('raw_attribute', $meta['review']['extension'] ?? null);
            $t->same(true, $meta['review']['spacing'] ?? null);
            $t->same($case['format'], $meta['review']['format'] ?? null);
            $t->same($case['family'], $meta['review']['family'] ?? null);
            $t->same('block', $meta['review']['kind'] ?? null);
            $t->same('raw_block', $raw->type);
            $t->same($case['format'], $raw->attr('format'));
            $t->same($rawText, $raw->attr('text'));
            $t->same('paragraph', $paragraph->type);
            $t->same('After spaced raw block.', $paragraph->attr('text'));
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

    $tests['maps upstream markdown spaced raw inline attribute format ' . $case['format'] . ' with metadata'] =
        static function (TestRunner $t) use ($case, $rawAttributeSpacingInlinePayload): void {
            $rawText = $rawAttributeSpacingInlinePayload($case);
            $document = (new MarkdownReader())->read(implode("\n", [
                '---',
                'title: Raw inline spacing **Packet**',
                'review: {extension: raw_attribute, spacing: true, format: "' . $case['format'] . '", family: ' . $case['family'] . ', kind: inline}',
                '...',
                '',
                'Before `' . $rawText . '`' . $case['spec'] . ' after.',
            ]));

            $meta = $document->attr('meta');
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $raw = $paragraph->children[1] ?? new AstNode('missing');
            $markdown = (new MarkdownWriter())->write($document);

            $t->same('raw_attribute', $meta['review']['extension'] ?? null);
            $t->same(true, $meta['review']['spacing'] ?? null);
            $t->same($case['format'], $meta['review']['format'] ?? null);
            $t->same($case['family'], $meta['review']['family'] ?? null);
            $t->same('inline', $meta['review']['kind'] ?? null);
            $t->same('paragraph', $paragraph->type);
            $t->same('raw_inline', $raw->type);
            $t->same($case['format'], $raw->attr('format'));
            $t->same($rawText, $raw->attr('text'));
            $t->contains($rawText, $markdown);
            $t->true(
                !str_contains($markdown, '`' . $rawText . '`' . $case['spec']),
                'Spaced raw inline attribute should normalize to raw text, not remain code'
            );

            if ($case['family'] === 'html') {
                $blocks = (new WordPressBlockWriter())->write($document);
                $t->contains($rawText, $blocks);
                $t->true(!str_contains($blocks, '<code>'), 'HTML raw inline should not become code');
            } elseif ($case['family'] === 'tex') {
                $blocks = (new WordPressBlockWriter())->write($document);
                $t->contains(
                    '<span class="pandoc-raw-tex">'
                    . htmlspecialchars($rawText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</span>',
                    $blocks
                );
            }
        };
}

return $tests;
