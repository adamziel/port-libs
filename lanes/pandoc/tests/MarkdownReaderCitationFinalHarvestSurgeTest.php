<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$firstInline = static function (string $markdown, array $options = []): AstNode {
    $document = (new MarkdownReader($options))->read($markdown);

    return $document->children[0]->children[0] ?? new AstNode('missing');
};

$inlinePlain = static function (mixed $value) use (&$inlinePlain): string {
    if (is_scalar($value)) {
        return trim((string) $value);
    }

    if ($value instanceof AstNode) {
        return $inlinePlain([$value]);
    }

    if (!is_array($value)) {
        return '';
    }

    $text = '';
    foreach ($value as $node) {
        if (!$node instanceof AstNode) {
            $text .= is_scalar($node) ? (string) $node : '';
            continue;
        }

        $text .= match ($node->type) {
            'text', 'code', 'math' => (string) $node->attr('text', ''),
            'space', 'softbreak', 'linebreak' => ' ',
            default => $inlinePlain($node->children),
        };
    }

    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
};

$inlineTypes = static function (mixed $value): array {
    if ($value instanceof AstNode) {
        return [$value->type];
    }

    if (!is_array($value)) {
        return [];
    }

    return array_map(
        static fn (AstNode $node): string => $node->type,
        array_values(array_filter($value, static fn (mixed $node): bool => $node instanceof AstNode))
    );
};

$collectNodes = static function (AstNode $node, string $type) use (&$collectNodes): array {
    $matches = $node->type === $type ? [$node] : [];
    foreach ($node->children as $child) {
        array_push($matches, ...$collectNodes($child, $type));
    }

    return $matches;
};

$unicodeCitationCases = [
    ['accented bare key', '@eclair2026', 'eclair2026', 'author_in_text'],
    ['latin accented bare key', '@café2026', 'café2026', 'author_in_text'],
    ['greek bare key', '@δοκιμή2026', 'δοκιμή2026', 'author_in_text'],
    ['cyrillic bare key', '@источник-2026', 'источник-2026', 'author_in_text'],
    ['cjk bare key', '@資料2026', '資料2026', 'author_in_text'],
    ['devanagari bare key', '@स्रोत2026', 'स्रोत2026', 'author_in_text'],
    ['unicode colon key', '@référence:été2026', 'référence:été2026', 'author_in_text'],
    ['unicode period key', '@éclair.v2', 'éclair.v2', 'author_in_text'],
    ['unicode slash key', '@źródło/sekcja', 'źródło/sekcja', 'author_in_text'],
    ['unicode hyphen key', '@α-β', 'α-β', 'author_in_text'],
    ['unicode hash key', '@mañana#review', 'mañana#review', 'author_in_text'],
    ['unicode plus key', '@data+δοκιμή', 'data+δοκιμή', 'author_in_text'],
    ['unicode percent key', '@source%é', 'source%é', 'author_in_text'],
    ['unicode question key', '@ключ?версия', 'ключ?версия', 'author_in_text'],
    ['unicode angle key', '@文件<版本>', '文件<版本>', 'author_in_text'],
    ['unicode tilde key', '@árvore~folha', 'árvore~folha', 'author_in_text'],
    ['unicode bracketed normal key', '[@café2026]', 'café2026', 'normal'],
    ['unicode bracketed suppress key', '[-@δοκιμή2026]', 'δοκιμή2026', 'suppress_author'],
    ['unicode bracketed prefix locator key', '[see @источник-2026, sec. 2]', 'источник-2026', 'normal'],
    ['unicode braced key still works', '[@{資料 source 2026}]', '資料 source 2026', 'normal'],
];

$affixInlineCases = [
    ['emphasis prefix', '[*see* @doe, p. 7]', 0, 'prefix', 'see', ['emph'], null, null],
    ['strong prefix', '[**compare** @doe, p. 7]', 0, 'prefix', 'compare', ['strong'], null, null],
    ['code prefix', '[`audit` @doe, p. 7]', 0, 'prefix', 'audit', ['code'], null, null],
    ['link prefix', '[see [packet](url) @doe, p. 7]', 0, 'prefix', 'see packet', ['text', 'link'], null, null],
    ['autolink prefix', '[see <https://example.test> @doe, p. 7]', 0, 'prefix', 'see https://example.test', ['text', 'link'], null, null],
    ['math prefix', '[formula $x+1$ @doe, p. 7]', 0, 'prefix', 'formula x+1', ['text', 'math'], null, null],
    ['strikeout prefix', '[~~old~~ @doe, p. 7]', 0, 'prefix', 'old', ['strikeout'], null, null],
    ['escaped punctuation prefix', '[\\*literal\\* @doe, p. 7]', 0, 'prefix', '*literal*', ['text'], null, null],
    ['entity prefix', '[source &amp; packet @doe, p. 7]', 0, 'prefix', 'source & packet', ['text'], null, null],
    ['wikilink prefix', '[[[Packet|/packet]] @doe, p. 7]', 0, 'prefix', 'Packet', ['link'], null, null, ['format' => 'markdown+wikilinks_title_before_pipe']],
    ['emphasis locator', '[@doe, *chapter* 2]', 0, 'locator', 'chapter 2', ['emph', 'text'], 'chapter', '2'],
    ['strong locator', '[@doe, **sec.** 4]', 0, 'locator', 'sec. 4', ['strong', 'text'], 'section', '4'],
    ['code locator', '[@doe, `p.` 8]', 0, 'locator', 'p. 8', ['code', 'text'], 'page', '8'],
    ['link locator', '[@doe, [appendix](url) B]', 0, 'locator', 'appendix B', ['link', 'text'], 'appendix', 'B'],
    ['autolink locator', '[@doe, <https://example.test/loc> packet]', 0, 'locator', 'https://example.test/loc packet', ['link', 'text'], 'page', 'https://example.test/loc packet'],
    ['math locator', '[@doe, $x+1$]', 0, 'locator', 'x+1', ['math'], 'page', 'x+1'],
    ['strikeout locator', '[@doe, ~~old~~ appendix]', 0, 'locator', 'old appendix', ['strikeout', 'text'], 'page', 'old appendix'],
    ['entity locator', '[@doe, source &amp; packet]', 0, 'locator', 'source & packet', ['text'], 'page', 'source & packet'],
    ['forced markup locator', '[@doe, {*custom* suffix}]', 0, 'locator', 'custom suffix', ['emph', 'text'], 'page', 'custom suffix'],
    ['bare emphasis suffix', '@doe [*chapter* 2]', 0, 'suffix', 'chapter 2', ['emph', 'text'], null, null],
    ['bare code suffix', '@doe [`p.` 8]', 0, 'suffix', 'p. 8', ['code', 'text'], null, null],
    ['group first markup affixes', '[see *review* @doe, **p.** 4; compare `audit` -@roe, sec. 5]', 0, 'prefix', 'see review', ['text', 'emph'], null, null],
    ['group first markup locator', '[see *review* @doe, **p.** 4; compare `audit` -@roe, sec. 5]', 0, 'locator', 'p. 4', ['strong', 'text'], 'page', '4'],
    ['group second markup prefix', '[see *review* @doe, **p.** 4; compare `audit` -@roe, sec. 5]', 1, 'prefix', 'compare audit', ['text', 'code'], null, null],
    ['braced key link locator', '[*see* @{source key}, [appendix](url) B]', 0, 'locator', 'appendix B', ['link', 'text'], 'appendix', 'B'],
];

$spanBoundaryCases = [
    ['citation-looking text span', '[see @doe]{.source}', 'doe', 'author_in_text', ['text', 'citation'], ['source'], null, []],
    ['normal citation span', '[@doe]{.citation}', 'doe', 'author_in_text', ['citation'], ['citation'], null, []],
    ['suppress nested citation span', '[[-@doe] source]{.citation}', 'doe', 'suppress_author', ['citation', 'text'], ['citation'], null, []],
    ['braced citation span', '[@{source key}]{.citation}', 'source key', 'author_in_text', ['citation'], ['citation'], null, []],
    ['marked prefix citation span', '[*see* @doe]{#src .citation data-source="batch-1"}', 'doe', 'author_in_text', ['emph', 'text', 'citation'], ['citation'], 'src', ['data-source' => 'batch-1']],
    ['two bare citations in span', '[text @doe and @roe]{.source}', 'doe', 'author_in_text', ['text', 'citation', 'text', 'citation'], ['source'], null, []],
    ['unicode citation span', '[see @éclair2026]{.source}', 'éclair2026', 'author_in_text', ['text', 'citation'], ['source'], null, []],
    ['code before citation span', '[code `@literal` and @doe]{.source}', 'doe', 'author_in_text', ['text', 'code', 'text', 'citation'], ['source'], null, []],
    ['link before citation span', '[link [packet](url) and @doe]{.source}', 'doe', 'author_in_text', ['text', 'link', 'text', 'citation'], ['source'], null, []],
    ['math before citation span', '[math $x+1$ and @doe]{.source}', 'doe', 'author_in_text', ['text', 'math', 'text', 'citation'], ['source'], null, []],
    ['attribute-only citation span', '[plain @doe]{key=value}', 'doe', 'author_in_text', ['text', 'citation'], [], null, ['key' => 'value']],
    ['id-only citation span', '[plain @doe]{#id}', 'doe', 'author_in_text', ['text', 'citation'], [], 'id', []],
    ['multi-class citation span', '[plain @doe]{.a .b}', 'doe', 'author_in_text', ['text', 'citation'], ['a', 'b'], null, []],
    ['multi-attribute citation span', '[plain @doe]{data-one="1" data-two=two}', 'doe', 'author_in_text', ['text', 'citation'], [], null, ['data-one' => '1', 'data-two' => 'two']],
    ['semicolon citations span', '[@doe; @roe]{.source}', 'doe', 'author_in_text', ['citation', 'text', 'citation'], ['source'], null, []],
    ['nested normal bracketed citation span', '[[@doe]]{.source}', 'doe', 'normal', ['citation'], ['source'], null, []],
    ['nested suppress bracketed citation span', '[[-@doe]]{.source}', 'doe', 'suppress_author', ['citation'], ['source'], null, []],
    ['text before nested citation span', '[see [@doe]]{.source}', 'doe', 'normal', ['text', 'citation'], ['source'], null, []],
    ['bare citation suffix in span', '[see @doe [suffix]]{.source}', 'doe', 'author_in_text', ['text', 'citation'], ['source'], null, []],
    ['braced unicode citation span', '[@{資料 source}]{.source}', '資料 source', 'author_in_text', ['citation'], ['source'], null, []],
];

$profileCases = [
    ['commonmark bare disabled', ['format' => 'commonmark'], '@doe', 'text', 0],
    ['commonmark bracket disabled', ['format' => 'commonmark'], '[@doe]', 'text', 0],
    ['gfm bare disabled', ['format' => 'gfm'], '@doe', 'text', 0],
    ['markdown strict bracket disabled', ['format' => 'markdown_strict'], '[@doe]', 'text', 0],
    ['markdown minus citations disabled', ['format' => 'markdown-citations'], '@doe', 'text', 0],
    ['markdown minus plus citations enabled', ['format' => 'markdown-citations+citations'], '@doe', 'citation', 1],
    ['gfm plus citations enabled', ['format' => 'gfm+citations'], '@doe', 'citation', 1],
    ['commonmark plus citations enabled', ['format' => 'commonmark+citations'], '[@doe]', 'citation', 1],
    ['span keeps citation disabled profile literal', ['format' => 'markdown-citations'], '[@doe]{.source}', 'span', 0],
    ['span reparses citation when profile reenables', ['format' => 'markdown-citations+citations'], '[@doe]{.source}', 'span', 1],
    ['extension list disables citations', ['extensions' => ['-citations']], '@doe', 'text', 0],
    ['extension map disables citations', ['extensions' => ['citations' => false]], '[@doe]', 'text', 0],
    ['multimarkdown keeps citations disabled', ['format' => 'markdown_mmd'], '@doe', 'text', 0],
];

$tests = [];

foreach ($unicodeCitationCases as [$name, $markdown, $id, $mode]) {
    $tests["maps final harvest unicode citation key {$name}"] =
        static function (TestRunner $t) use ($firstInline, $markdown, $id, $mode): void {
            $node = $firstInline($markdown);
            $citation = $node->type === 'citation_group' ? ($node->children[0] ?? new AstNode('missing')) : $node;

            $t->same('citation', $citation->type);
            $t->same($id, $citation->attr('id'));
            $t->same($mode, $citation->attr('mode'));
        };
}

foreach ($affixInlineCases as $case) {
    [$name, $markdown, $childIndex, $attr, $plain, $types, $locatorLabel, $locatorValue] = array_pad($case, 8, null);
    $options = is_array($case[8] ?? null) ? $case[8] : [];
    $tests["preserves final harvest citation inline affix {$name}"] =
        static function (TestRunner $t) use ($firstInline, $inlinePlain, $inlineTypes, $markdown, $childIndex, $attr, $plain, $types, $locatorLabel, $locatorValue, $options): void {
            $node = $firstInline($markdown, $options);
            $citation = $node->type === 'citation_group' ? ($node->children[$childIndex] ?? new AstNode('missing')) : $node;
            $value = $citation->attr($attr);

            $t->same('citation', $citation->type);
            $t->true(is_array($value), "{$attr} should preserve parsed inline nodes");
            $t->same($plain, $inlinePlain($value));
            $t->same($types, $inlineTypes($value));
            if ($locatorLabel !== null) {
                $t->same($locatorLabel, $citation->attr('locatorLabel'));
                $t->same($locatorValue, $citation->attr('locatorValue'));
            }
        };
}

foreach ($spanBoundaryCases as [$name, $markdown, $id, $mode, $childTypes, $classes, $spanId, $attributes]) {
    $tests["prefers final harvest bracketed span boundary {$name}"] =
        static function (TestRunner $t) use ($firstInline, $collectNodes, $markdown, $id, $mode, $childTypes, $classes, $spanId, $attributes): void {
            $span = $firstInline($markdown);
            $citations = $collectNodes($span, 'citation');
            $citation = $citations[0] ?? new AstNode('missing');

            $t->same('span', $span->type);
            $t->same($childTypes, array_map(static fn (AstNode $node): string => $node->type, $span->children));
            $t->same($classes, $span->attr('classes', []));
            $t->same($spanId, $span->attr('id'));
            $t->same($attributes, $span->attr('attributes', []));
            $t->same('citation', $citation->type);
            $t->same($id, $citation->attr('id'));
            $t->same($mode, $citation->attr('mode'));
        };
}

foreach ($profileCases as [$name, $options, $markdown, $firstType, $citationCount]) {
    $tests["respects final harvest citation profile {$name}"] =
        static function (TestRunner $t) use ($firstInline, $collectNodes, $options, $markdown, $firstType, $citationCount): void {
            $first = $firstInline($markdown, $options);
            $document = (new MarkdownReader($options))->read($markdown);

            $t->same($firstType, $first->type);
            $t->same($citationCount, count($collectNodes($document, 'citation')));
        };
}

$tests['records markdown reader citation final harvest mapped-case count'] =
    static function (TestRunner $t) use ($unicodeCitationCases, $affixInlineCases, $spanBoundaryCases, $profileCases): void {
        $t->same(78, count($unicodeCitationCases) + count($affixInlineCases) + count($spanBoundaryCases) + count($profileCases));
    };

return $tests;
