<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

/**
 * @return list<string>
 */
$rawHtmlInlineTokens = static function (AstNode $cell): array {
    $tokens = [];
    foreach ($cell->children as $child) {
        if ($child->type === 'raw_html_inline') {
            $tokens[] = (string) $child->attr('html', '');
        }
    }

    return $tokens;
};

$cases = [
    '01 span double quoted data attribute' => ['span', 'data-review', 'alpha|beta', 'span label'],
    '02 abbr title attribute' => ['abbr', 'title', 'Portable|Document', 'PDF'],
    '03 time datetime attribute' => ['time', 'datetime', '2026-06-15|22:14', 'review time'],
    '04 data value attribute' => ['data', 'value', '42|ready', 'answer'],
    '05 cite data source attribute' => ['cite', 'data-source', 'batch|source', 'Source'],
    '06 q cite attribute' => ['q', 'cite', 'https://example.test/a|b', 'quote'],
    '07 dfn id attribute' => ['dfn', 'id', 'term|review', 'term'],
    '08 bdi dir attribute' => ['bdi', 'dir', 'ltr|rtl', 'source'],
    '09 bdo data direction attribute' => ['bdo', 'data-direction', 'rtl|source', 'abc'],
    '10 mark title attribute' => ['mark', 'title', 'flag|review', 'flag'],
    '11 small data note attribute' => ['small', 'data-note', 'fine|print', 'fine print'],
    '12 sub data formula attribute' => ['sub', 'data-formula', 'H|2', '2'],
    '13 sup data formula attribute' => ['sup', 'data-formula', 'x|3', '3'],
    '14 ins datetime attribute' => ['ins', 'datetime', '2026-06-15|added', 'added'],
    '15 del datetime attribute' => ['del', 'datetime', '2026-06-15|removed', 'removed'],
    '16 u data edit attribute' => ['u', 'data-edit', 'under|line', 'under'],
    '17 i data style attribute' => ['i', 'data-style', 'italic|source', 'italic'],
    '18 b data style attribute' => ['b', 'data-style', 'bold|source', 'bold'],
    '19 code data lang attribute' => ['code', 'data-lang', 'php|inline', '$x'],
    '20 kbd data key attribute' => ['kbd', 'data-key', 'Ctrl|S', 'Ctrl'],
    '21 samp data output attribute' => ['samp', 'data-output', 'ok|done', 'output'],
    '22 var data variable attribute' => ['var', 'data-variable', 'x|y', 'x'],
    '23 button type attribute' => ['button', 'type', 'button|review', 'Approve'],
    '24 label for attribute' => ['label', 'for', 'field|review', 'Label'],
    '25 select name attribute' => ['select', 'name', 'choice|review', 'One'],
    '26 option value attribute' => ['option', 'value', 'one|review', 'One'],
    '27 ruby data reading attribute' => ['ruby', 'data-reading', 'kan|reading', 'kan'],
    '28 rt data reading attribute' => ['rt', 'data-reading', 'source|review', 'reading'],
    '29 math data formula attribute' => ['math', 'data-formula', 'x|y', 'x'],
    '30 mi data symbol attribute' => ['mi', 'data-symbol', 'x|review', 'x'],
    '31 mo data operator attribute' => ['mo', 'data-operator', '=|review', '='],
    '32 mn data number attribute' => ['mn', 'data-number', '1|review', '1'],
    '33 svg viewBox attribute' => ['svg', 'viewBox', '0 0 1|1', 'svg'],
    '34 text data label attribute' => ['text', 'data-label', 'axis|title', 'Axis'],
    '35 g data group attribute' => ['g', 'data-group', 'series|one', 'group'],
    '36 a href attribute' => ['a', 'href', 'https://example.test/a|b', 'link'],
    '37 meter data value attribute' => ['meter', 'data-value', 'import|review', 'Meter'],
    '38 section data section attribute' => ['section', 'data-section', 'audit|queue', 'Section'],
    '39 article data article attribute' => ['article', 'data-article', 'news|review', 'Article'],
    '40 aside data note attribute' => ['aside', 'data-note', 'side|note', 'Aside'],
    '41 header data region attribute' => ['header', 'data-region', 'top|review', 'Header'],
    '42 footer data region attribute' => ['footer', 'data-region', 'bottom|review', 'Footer'],
    '43 main data role attribute' => ['main', 'data-role', 'main|content', 'Main'],
    '44 nav aria label attribute' => ['nav', 'aria-label', 'primary|nav', 'Nav'],
    '45 progress data state attribute' => ['progress', 'data-state', 'intro|review', 'Progress'],
    '46 slot name attribute' => ['slot', 'name', 'quote|review', 'Slot'],
    '47 em data emphasis attribute' => ['em', 'data-emphasis', 'source|em', 'Em'],
    '48 strong data emphasis attribute' => ['strong', 'data-emphasis', 'source|strong', 'Strong'],
    '49 s data edit attribute' => ['s', 'data-edit', 'stale|copy', 'Stale'],
    '50 search data query attribute' => ['search', 'data-query', 'posts|media', 'Search'],
];

$tests = [
    'records markdown reader pipe table html surge mapped-case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(50, count($cases));
    },
];

foreach ($cases as $label => [$tag, $attribute, $value, $text]) {
    $tests["maps upstream markdown reader pipe table html attribute {$label}"] =
        static function (TestRunner $t) use ($rawHtmlInlineTokens, $tag, $attribute, $value, $text, $label): void {
            $html = '<' . $tag . ' ' . $attribute . '="' . $value . '">' . $text . '</' . $tag . '>';
            $document = (new MarkdownReader())->read(implode("\n", [
                '| Markup | State |',
                '| --- | --- |',
                '| ' . $html . ' | ok |',
            ]));

            $table = $document->children[0] ?? new AstNode('missing');
            $head = $table->children[0] ?? new AstNode('missing');
            $body = $table->children[1] ?? new AstNode('missing');
            $row = $body->children[0] ?? new AstNode('missing');
            $cell = $row->children[0] ?? new AstNode('missing');
            $state = $row->children[1] ?? new AstNode('missing');
            $rawTokens = $rawHtmlInlineTokens($cell);

            $t->same('table', $table->type, $label);
            $t->same(['default', 'default'], $table->attr('alignments'), $label);
            $t->same('Markup', $head->children[0]->children[0]->attr('text'), $label);
            $t->same($html, $cell->attr('text'), $label);
            $t->same('ok', $state->attr('text'), $label);
            $rawSource = implode('', $rawTokens);
            $t->true($rawTokens !== [], $label . ' should preserve raw HTML inline tokens');
            $t->contains('<' . $tag . ' ' . $attribute . '="' . $value . '">', $rawSource, $label);
            $t->contains('</' . $tag . '>', $rawSource, $label);
        };
}

return $tests;
