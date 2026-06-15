<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

/**
 * @return array<string, array{htmlAttributes: array<string, string>}>
 */
function markdown_native_div_extension_completion_cases(): array
{
    return [
        'id class data review' => ['htmlAttributes' => ['id' => 'native-div-alpha', 'class' => 'review primary', 'data-review' => 'alpha']],
        'class data status' => ['htmlAttributes' => ['class' => 'review status', 'data-status' => 'accepted']],
        'id role note' => ['htmlAttributes' => ['id' => 'native-div-role-note', 'role' => 'note', 'data-kind' => 'role']],
        'language direction' => ['htmlAttributes' => ['class' => 'locale', 'lang' => 'pl', 'dir' => 'ltr']],
        'aria label' => ['htmlAttributes' => ['class' => 'a11y', 'aria-label' => 'Review packet']],
        'aria describedby' => ['htmlAttributes' => ['class' => 'a11y', 'aria-describedby' => 'note-a note-b']],
        'title attribute' => ['htmlAttributes' => ['class' => 'titled', 'title' => 'Reviewer packet']],
        'style attribute' => ['htmlAttributes' => ['class' => 'styled', 'style' => 'border:1px solid red']],
        'resource attribute' => ['htmlAttributes' => ['class' => 'rdf', 'resource' => 'urn:review:1']],
        'about property' => ['htmlAttributes' => ['class' => 'rdfa', 'about' => '#review', 'property' => 'schema:reviewBody']],
        'typeof attribute' => ['htmlAttributes' => ['class' => 'rdfa', 'typeof' => 'schema:Review']],
        'vocab attribute' => ['htmlAttributes' => ['class' => 'rdfa', 'vocab' => 'https://schema.org/']],
        'itemprop attribute' => ['htmlAttributes' => ['class' => 'microdata', 'itemprop' => 'reviewBody']],
        'itemtype attribute' => ['htmlAttributes' => ['class' => 'microdata', 'itemtype' => 'https://schema.org/Review']],
        'source path' => ['htmlAttributes' => ['class' => 'source', 'data-source-path' => 'markdown-reader']],
        'case key' => ['htmlAttributes' => ['class' => 'source', 'data-case' => 'native-div']],
        'row coordinate' => ['htmlAttributes' => ['class' => 'grid', 'data-row' => '4']],
        'column coordinate' => ['htmlAttributes' => ['class' => 'grid', 'data-column' => '7']],
        'format marker' => ['htmlAttributes' => ['class' => 'format', 'data-format' => 'markdown']],
        'extension marker' => ['htmlAttributes' => ['class' => 'format', 'data-extension' => 'native_divs']],
        'writer marker' => ['htmlAttributes' => ['class' => 'handoff', 'data-writer' => 'markdown']],
        'reader marker' => ['htmlAttributes' => ['class' => 'handoff', 'data-reader' => 'markdown']],
        'reviewer marker' => ['htmlAttributes' => ['class' => 'handoff', 'data-reviewer' => 'import']],
        'locale marker' => ['htmlAttributes' => ['class' => 'locale', 'data-locale' => 'en-US']],
        'language marker' => ['htmlAttributes' => ['class' => 'locale', 'data-language' => 'English']],
        'checksum marker' => ['htmlAttributes' => ['class' => 'digest', 'data-checksum' => 'sha256-abc123']],
        'origin marker' => ['htmlAttributes' => ['class' => 'origin', 'data-origin' => 'html-reader']],
        'target marker' => ['htmlAttributes' => ['class' => 'target', 'data-target' => '#review-target']],
        'caption marker' => ['htmlAttributes' => ['class' => 'caption', 'data-caption' => 'figure-review']],
        'kind marker' => ['htmlAttributes' => ['class' => 'kind', 'data-kind' => 'admonition']],
        'priority marker' => ['htmlAttributes' => ['class' => 'priority', 'data-priority' => 'high']],
        'section marker' => ['htmlAttributes' => ['class' => 'section', 'data-section' => 'appendix']],
        'depth marker' => ['htmlAttributes' => ['class' => 'section', 'data-depth' => '3']],
        'index marker' => ['htmlAttributes' => ['class' => 'index', 'data-index' => '12']],
        'note marker' => ['htmlAttributes' => ['class' => 'note', 'data-note' => 'review note']],
        'translate no' => ['htmlAttributes' => ['class' => 'i18n', 'translate' => 'no']],
        'tabindex zero' => ['htmlAttributes' => ['class' => 'focus', 'tabindex' => '0']],
        'doc notice role' => ['htmlAttributes' => ['class' => 'notice', 'role' => 'doc-notice']],
        'aria live polite' => ['htmlAttributes' => ['class' => 'live', 'aria-live' => 'polite']],
        'aria hidden false' => ['htmlAttributes' => ['class' => 'visibility', 'aria-hidden' => 'false']],
        'right to left arabic' => ['htmlAttributes' => ['class' => 'locale', 'dir' => 'rtl', 'lang' => 'ar']],
        'draggable false' => ['htmlAttributes' => ['class' => 'interactive', 'draggable' => 'false']],
        'spellcheck false' => ['htmlAttributes' => ['class' => 'editing', 'spellcheck' => 'false']],
        'contenteditable false' => ['htmlAttributes' => ['class' => 'editing', 'contenteditable' => 'false']],
        'inputmode text' => ['htmlAttributes' => ['class' => 'input', 'inputmode' => 'text']],
        'popover manual' => ['htmlAttributes' => ['class' => 'popover', 'popover' => 'manual']],
        'slot name' => ['htmlAttributes' => ['class' => 'component', 'slot' => 'review']],
        'part name' => ['htmlAttributes' => ['class' => 'component', 'part' => 'review-body']],
        'exportparts name' => ['htmlAttributes' => ['class' => 'component', 'exportparts' => 'body:review-body']],
        'inert false marker' => ['htmlAttributes' => ['class' => 'state', 'data-inert' => 'false']],
    ];
}

/**
 * @param array<string, string> $htmlAttributes
 */
function markdown_native_div_opening_attributes(array $htmlAttributes): string
{
    $parts = [];
    foreach ($htmlAttributes as $name => $value) {
        $parts[] = $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    return implode(' ', $parts);
}

/**
 * @param array<string, string> $htmlAttributes
 * @return array<string, string>
 */
function markdown_native_div_expected_attributes(array $htmlAttributes): array
{
    $attributes = [];
    foreach ($htmlAttributes as $name => $value) {
        if ($name === 'id' || $name === 'class') {
            continue;
        }

        $attributes[str_starts_with($name, 'data-') ? substr($name, 5) : $name] = $value;
    }

    return $attributes;
}

function markdown_native_div_wordpress_preserves_attribute(string $attribute): bool
{
    return $attribute === 'id'
        || $attribute === 'class'
        || $attribute === 'title'
        || $attribute === 'role'
        || $attribute === 'lang'
        || $attribute === 'dir'
        || $attribute === 'translate'
        || str_starts_with($attribute, 'data-')
        || str_starts_with($attribute, 'aria-');
}

$tests = [];
foreach (markdown_native_div_extension_completion_cases() as $name => $case) {
    $tests['maps upstream markdown native div extension completion ' . $name] =
        static function (TestRunner $t) use ($name, $case): void {
            $htmlAttributes = $case['htmlAttributes'];
            $body = 'Native div packet ' . $name . ' with **metadata**.';
            $plainText = 'Native div packet ' . $name . ' with metadata.';
            $document = (new MarkdownReader())->read(implode("\n", [
                '---',
                'extension: [native_divs, raw_html]',
                'review: {extension: native_divs, family: html, kind: block, name: "' . $name . '"}',
                '...',
                '',
                '<div ' . markdown_native_div_opening_attributes($htmlAttributes) . '>',
                $body,
                '</div>',
            ]));

            $meta = $document->attr('meta');
            $div = $document->children[0] ?? new AstNode('missing');
            $paragraph = $div->children[0] ?? new AstNode('missing');
            $markdown = (new MarkdownWriter())->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->true(in_array('native_divs', $meta['extension'] ?? [], true), $name . ' metadata extension');
            $t->same('native_divs', $meta['review']['extension'] ?? null);
            $t->same('html', $meta['review']['family'] ?? null);
            $t->same('block', $meta['review']['kind'] ?? null);
            $t->same($name, $meta['review']['name'] ?? null);
            $t->same('div', $div->type);
            $t->same($htmlAttributes['id'] ?? '', $div->attr('id', ''));
            $t->same(
                isset($htmlAttributes['class']) ? preg_split('/\s+/', $htmlAttributes['class'], -1, PREG_SPLIT_NO_EMPTY) : [],
                $div->attr('classes', [])
            );
            foreach (markdown_native_div_expected_attributes($htmlAttributes) as $attribute => $value) {
                $t->same($value, $div->attr('attributes', [])[$attribute] ?? null, $name . ' native attribute ' . $attribute);
                $t->contains($attribute . '="' . $value . '"', $markdown, $name . ' Markdown attribute ' . $attribute);
            }
            foreach ($htmlAttributes as $attribute => $value) {
                $t->same($value, $div->attr('htmlAttributes', [])[$attribute] ?? null, $name . ' HTML attribute ' . $attribute);
                if ($attribute === 'id') {
                    $t->contains('#' . $value, $markdown, $name . ' Markdown id');
                } elseif ($attribute === 'class') {
                    foreach (preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $class) {
                        $t->contains('.' . $class, $markdown, $name . ' Markdown class ' . $class);
                    }
                }
                if (markdown_native_div_wordpress_preserves_attribute($attribute)) {
                    $t->contains($attribute . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"', $blocks, $name . ' WordPress attribute ' . $attribute);
                }
            }

            $t->same('paragraph', $paragraph->type);
            $t->same($plainText, $paragraph->attr('text'));
            $t->same(['text', 'strong', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
            $t->contains('Native div packet ' . $name . ' with **metadata**.', $markdown);
            $t->contains('<strong>metadata</strong>', $blocks);
        };
}

return $tests;
