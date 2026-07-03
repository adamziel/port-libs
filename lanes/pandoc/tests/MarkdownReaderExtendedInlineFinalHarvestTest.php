<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return "\n";
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

$matchesInlineType = static function (AstNode $node, string $type): bool {
    if ($type === 'mark') {
        $classes = $node->attr('classes', []);

        return $node->type === 'span' && is_array($classes) && in_array('mark', $classes, true);
    }

    return $node->type === $type;
};

$findInline = static function (AstNode $node, callable $predicate) use (&$findInline): AstNode {
    if ($predicate($node)) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findInline($child, $predicate);
        if ($match->type !== 'missing') {
            return $match;
        }
    }

    return new AstNode('missing');
};

$assertNodeAttrs = static function (TestRunner $t, AstNode $node, array $case, string $label): void {
    if (array_key_exists('id', $case)) {
        $t->same($case['id'], $node->attr('id'), $label . ' id');
    }

    if (array_key_exists('classes', $case)) {
        $t->same($case['classes'], $node->attr('classes', []), $label . ' classes');
    }

    if (array_key_exists('attributes', $case)) {
        $attributes = $node->attr('attributes', []);
        $t->true(is_array($attributes), $label . ' attributes should be present');
        foreach ($case['attributes'] as $name => $value) {
            $t->same($value, $attributes[$name] ?? null, $label . ' attribute ' . $name);
        }
    }
};

$semanticElementGroups = [
    'sup element' => ['tag' => 'sup', 'type' => 'superscript', 'text' => '2'],
    'sub element' => ['tag' => 'sub', 'type' => 'subscript', 'text' => '2'],
    'u element' => ['tag' => 'u', 'type' => 'underline', 'text' => 'under'],
    'ins element' => ['tag' => 'ins', 'type' => 'underline', 'text' => 'inserted'],
    'del element' => ['tag' => 'del', 'type' => 'strikeout', 'text' => 'deleted'],
    's element' => ['tag' => 's', 'type' => 'strikeout', 'text' => 'stale'],
    'strike element' => ['tag' => 'strike', 'type' => 'strikeout', 'text' => 'old'],
    'mark element' => ['tag' => 'mark', 'type' => 'mark', 'text' => 'flag'],
];

$attributeVariants = [
    'plain' => ['source' => ''],
    'id' => ['source' => ' id="inline-source"', 'id' => 'inline-source'],
    'data' => ['source' => ' data-case="extended-inline"', 'attributes' => ['case' => 'extended-inline']],
    'class' => ['source' => ' class="source-inline"', 'classes' => ['source-inline']],
];

$elementCases = [];
foreach ($semanticElementGroups as $groupName => $group) {
    foreach ($attributeVariants as $variantName => $variant) {
        $classes = $variant['classes'] ?? null;
        if ($group['type'] === 'mark') {
            $classes = $classes === null ? ['mark'] : array_merge(['mark'], $classes);
        }

        $elementCases[$groupName . ' ' . $variantName] = array_filter([
            'html' => '<' . $group['tag'] . $variant['source'] . '>' . $group['text'] . '</' . $group['tag'] . '>',
            'type' => $group['type'],
            'text' => $group['text'],
            'id' => $variant['id'] ?? null,
            'classes' => $classes,
            'attributes' => $variant['attributes'] ?? null,
        ], static fn ($value): bool => $value !== null);
    }
}

$spanCases = [
    'smallcaps font variant style' => [
        'html' => '<span style="font-variant: small-caps">caps</span>',
        'type' => 'small_caps',
        'text' => 'caps',
    ],
    'smallcaps font variant caps style' => [
        'html' => '<span style="font-variant-caps: small-caps">caps</span>',
        'type' => 'small_caps',
        'text' => 'caps',
    ],
    'smallcaps leading class with trailing class' => [
        'html' => '<span class="smallcaps review">caps</span>',
        'type' => 'small_caps',
        'text' => 'caps',
        'classes' => ['review'],
    ],
    'smallcaps hyphenated leading class' => [
        'html' => '<span class="small-caps">caps</span>',
        'type' => 'small_caps',
        'text' => 'caps',
    ],
    'smallcaps style with id and data' => [
        'html' => '<span id="caps-id" data-case="caps" style="font-variant: small-caps">caps</span>',
        'type' => 'small_caps',
        'text' => 'caps',
        'id' => 'caps-id',
        'attributes' => ['case' => 'caps'],
    ],
    'smallcaps class with title' => [
        'html' => '<span class="smallcaps source" title="Review caps">caps</span>',
        'type' => 'small_caps',
        'text' => 'caps',
        'classes' => ['source'],
        'attributes' => ['title' => 'Review caps'],
    ],
    'underline text decoration style' => [
        'html' => '<span style="text-decoration: underline">under</span>',
        'type' => 'underline',
        'text' => 'under',
    ],
    'underline text decoration line style' => [
        'html' => '<span style="text-decoration-line: underline">under</span>',
        'type' => 'underline',
        'text' => 'under',
    ],
    'underline leading class with trailing class' => [
        'html' => '<span class="underline review">under</span>',
        'type' => 'underline',
        'text' => 'under',
        'classes' => ['review'],
    ],
    'underlined leading class' => [
        'html' => '<span class="underlined">under</span>',
        'type' => 'underline',
        'text' => 'under',
    ],
    'underline style with id and data' => [
        'html' => '<span id="under-id" data-case="under" style="text-decoration: underline">under</span>',
        'type' => 'underline',
        'text' => 'under',
        'id' => 'under-id',
        'attributes' => ['case' => 'under'],
    ],
    'underline class with data' => [
        'html' => '<span class="underline source" data-case="under-class">under</span>',
        'type' => 'underline',
        'text' => 'under',
        'classes' => ['source'],
        'attributes' => ['case' => 'under-class'],
    ],
    'strikeout text decoration style' => [
        'html' => '<span style="text-decoration: line-through">deleted</span>',
        'type' => 'strikeout',
        'text' => 'deleted',
    ],
    'strikeout text decoration line style' => [
        'html' => '<span style="text-decoration-line: line-through">deleted</span>',
        'type' => 'strikeout',
        'text' => 'deleted',
    ],
    'strikeout leading class with trailing class' => [
        'html' => '<span class="strikeout review">deleted</span>',
        'type' => 'strikeout',
        'text' => 'deleted',
        'classes' => ['review'],
    ],
    'strikethrough leading class' => [
        'html' => '<span class="strikethrough">deleted</span>',
        'type' => 'strikeout',
        'text' => 'deleted',
    ],
    'strike-through leading class' => [
        'html' => '<span class="strike-through">deleted</span>',
        'type' => 'strikeout',
        'text' => 'deleted',
    ],
    'strikeout style with id and data' => [
        'html' => '<span id="strike-id" data-case="strike" style="text-decoration: line-through">deleted</span>',
        'type' => 'strikeout',
        'text' => 'deleted',
        'id' => 'strike-id',
        'attributes' => ['case' => 'strike'],
    ],
    'superscript vertical align style' => [
        'html' => '<span style="vertical-align: super">2</span>',
        'type' => 'superscript',
        'text' => '2',
    ],
    'superscript leading class with trailing class' => [
        'html' => '<span class="superscript review">2</span>',
        'type' => 'superscript',
        'text' => '2',
        'classes' => ['review'],
    ],
    'super leading class' => [
        'html' => '<span class="super">2</span>',
        'type' => 'superscript',
        'text' => '2',
    ],
    'superscript style with id and data' => [
        'html' => '<span id="super-id" data-case="super" style="vertical-align: super">2</span>',
        'type' => 'superscript',
        'text' => '2',
        'id' => 'super-id',
        'attributes' => ['case' => 'super'],
    ],
    'superscript class with title' => [
        'html' => '<span class="superscript source" title="Exponent">2</span>',
        'type' => 'superscript',
        'text' => '2',
        'classes' => ['source'],
        'attributes' => ['title' => 'Exponent'],
    ],
    'subscript vertical align style' => [
        'html' => '<span style="vertical-align: sub">2</span>',
        'type' => 'subscript',
        'text' => '2',
    ],
    'subscript leading class with trailing class' => [
        'html' => '<span class="subscript review">2</span>',
        'type' => 'subscript',
        'text' => '2',
        'classes' => ['review'],
    ],
    'sub leading class' => [
        'html' => '<span class="sub">2</span>',
        'type' => 'subscript',
        'text' => '2',
    ],
    'subscript style with id and data' => [
        'html' => '<span id="sub-id" data-case="sub" style="vertical-align: sub">2</span>',
        'type' => 'subscript',
        'text' => '2',
        'id' => 'sub-id',
        'attributes' => ['case' => 'sub'],
    ],
    'subscript class with title' => [
        'html' => '<span class="subscript source" title="Index">2</span>',
        'type' => 'subscript',
        'text' => '2',
        'classes' => ['source'],
        'attributes' => ['title' => 'Index'],
    ],
    'mark background color style' => [
        'html' => '<span style="background-color: yellow">flag</span>',
        'type' => 'mark',
        'text' => 'flag',
        'classes' => ['mark'],
    ],
    'mark background shorthand style' => [
        'html' => '<span style="background: #ff0">flag</span>',
        'type' => 'mark',
        'text' => 'flag',
        'classes' => ['mark'],
    ],
    'mark leading class with trailing class' => [
        'html' => '<span class="mark review">flag</span>',
        'type' => 'mark',
        'text' => 'flag',
        'classes' => ['mark', 'review'],
    ],
    'highlight leading class with trailing class' => [
        'html' => '<span class="highlight review">flag</span>',
        'type' => 'mark',
        'text' => 'flag',
        'classes' => ['mark', 'highlight', 'review'],
    ],
    'highlighted leading class with data' => [
        'html' => '<span class="highlighted" data-case="mark">flag</span>',
        'type' => 'mark',
        'text' => 'flag',
        'classes' => ['mark', 'highlighted'],
        'attributes' => ['case' => 'mark'],
    ],
];

$profileCases = [
    'markdown keeps mark syntax literal' => ['format' => 'markdown', 'markdown' => 'Review ==flag==.', 'absent' => 'mark', 'literal' => 'Review ==flag==.'],
    'markdown plus mark enables mark syntax' => ['format' => 'markdown+mark', 'markdown' => 'Review ==flag==.', 'type' => 'mark', 'text' => 'flag'],
    'commonmark keeps mark syntax literal' => ['format' => 'commonmark', 'markdown' => 'Review ==flag==.', 'absent' => 'mark', 'literal' => 'Review ==flag==.'],
    'commonmark plus mark enables mark syntax' => ['format' => 'commonmark+mark', 'markdown' => 'Review ==flag==.', 'type' => 'mark', 'text' => 'flag'],
    'gfm enables strikeout syntax' => ['format' => 'gfm', 'markdown' => 'Review ~~gone~~.', 'type' => 'strikeout', 'text' => 'gone'],
    'gfm minus strikeout keeps strikeout literal' => ['format' => 'gfm-strikeout', 'markdown' => 'Review ~~gone~~.', 'absent' => 'strikeout', 'literal' => 'Review ~~gone~~.'],
    'gfm plus superscript enables script syntax' => ['format' => 'gfm+superscript', 'markdown' => 'H^2^ packet', 'type' => 'superscript', 'text' => '2'],
    'gfm plus subscript enables script syntax' => ['format' => 'gfm+subscript', 'markdown' => 'H~2~ packet', 'type' => 'subscript', 'text' => '2'],
    'markdown minus subscript keeps script literal' => ['format' => 'markdown-subscript', 'markdown' => 'H~2~ packet', 'absent' => 'subscript', 'literal' => 'H~2~ packet'],
    'commonmark plus bracketed spans maps smallcaps class' => ['format' => 'commonmark+bracketed_spans', 'markdown' => '[caps]{.smallcaps}', 'type' => 'small_caps', 'text' => 'caps'],
    'gfm plus bracketed spans maps underline attributes' => ['format' => 'gfm+bracketed_spans', 'markdown' => '[under]{.underline data-case=profile}', 'type' => 'underline', 'text' => 'under', 'attributes' => ['data-case' => 'profile']],
];

return [
    'maps upstream markdown reader extended inline html element attributes final harvest' =>
        static function (TestRunner $t) use ($elementCases, $findInline, $inlineText, $matchesInlineType, $assertNodeAttrs): void {
            $mapped = 0;

            foreach ($elementCases as $label => $case) {
                $document = (new MarkdownReader())->read('<p>Lead ' . $case['html'] . ' trail.</p>');
                $node = $findInline($document, static fn (AstNode $candidate): bool => $matchesInlineType($candidate, $case['type']));
                $blocks = (new WordPressBlockWriter())->write($document);

                $t->true($node->type !== 'missing', $label);
                $t->same($case['text'], $inlineText($node), $label . ' text');
                $assertNodeAttrs($t, $node, $case, $label);
                $t->contains($case['text'], $blocks, $label . ' wordpress text');
                if ($case['type'] === 'mark') {
                    $t->contains('<mark', $blocks, $label . ' wordpress mark element');
                }
                $mapped++;
            }

            $t->same(32, $mapped);
        },
    'maps upstream markdown reader span style and class semantic fallbacks final harvest' =>
        static function (TestRunner $t) use ($spanCases, $findInline, $inlineText, $matchesInlineType, $assertNodeAttrs): void {
            $mapped = 0;

            foreach ($spanCases as $label => $case) {
                $document = (new MarkdownReader())->read('<p>Lead ' . $case['html'] . ' trail.</p>');
                $node = $findInline($document, static fn (AstNode $candidate): bool => $matchesInlineType($candidate, $case['type']));
                $blocks = (new WordPressBlockWriter())->write($document);

                $t->true($node->type !== 'missing', $label);
                $t->same($case['text'], $inlineText($node), $label . ' text');
                $assertNodeAttrs($t, $node, $case, $label);
                $t->contains($case['text'], $blocks, $label . ' wordpress text');
                if ($case['type'] === 'mark') {
                    $t->contains('<mark', $blocks, $label . ' wordpress mark element');
                }
                $mapped++;
            }

            $t->same(33, $mapped);
        },
    'maps upstream markdown reader extended inline profile gates final harvest' =>
        static function (TestRunner $t) use ($profileCases, $findInline, $inlineText, $matchesInlineType, $assertNodeAttrs): void {
            $mapped = 0;

            foreach ($profileCases as $label => $case) {
                $document = (new MarkdownReader(['format' => $case['format']]))->read($case['markdown']);
                if (isset($case['type'])) {
                    $node = $findInline($document, static fn (AstNode $candidate): bool => $matchesInlineType($candidate, $case['type']));
                    $t->true($node->type !== 'missing', $label);
                    $t->same($case['text'], $inlineText($node), $label . ' text');
                    $assertNodeAttrs($t, $node, $case, $label);
                } else {
                    $node = $findInline($document, static fn (AstNode $candidate): bool => $matchesInlineType($candidate, $case['absent']));
                    $paragraph = $document->children[0] ?? new AstNode('missing');
                    $t->same('missing', $node->type, $label);
                    $t->same($case['literal'], $inlineText($paragraph), $label . ' literal');
                }
                $mapped++;
            }

            $t->same(11, $mapped);
        },
    'records upstream markdown reader extended inline final harvest mapped-case count' =>
        static function (TestRunner $t) use ($elementCases, $spanCases, $profileCases): void {
            $t->same(76, count($elementCases) + count($spanCases) + count($profileCases));
        },
];
