<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$firstNodeOfType = static function (AstNode $node, string $type) use (&$firstNodeOfType): ?AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $found = $firstNodeOfType($child, $type);
        if ($found !== null) {
            return $found;
        }
    }

    return null;
};

$titleTargetSource = static function (string $delimiter, string $source): string {
    if ($delimiter === '"') {
        return '"' . str_replace('"', '\\"', $source) . '"';
    }

    if ($delimiter === "'") {
        return "'" . str_replace("'", "\\'", $source) . "'";
    }

    return '(' . str_replace(')', '\\)', $source) . ')';
};

$htmlAttr = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$titleCases = [
    'plain' => ['Review title', 'Review title'],
    'spaced' => ['Review title with spaces', 'Review title with spaces'],
    'amp entity' => ['AT&amp;T source', 'AT&T source'],
    'copyright entity' => ['Copyright &copy; 2026', "Copyright \u{00A9} 2026"],
    'hex entity' => ['Greek &#x3bb; title', "Greek \u{03BB} title"],
    'decimal entity' => ['Decimal &#955; title', "Decimal \u{03BB} title"],
    'double quote punctuation' => ['Quote " marker', 'Quote " marker'],
    'apostrophe punctuation' => ["Reader's note", "Reader's note"],
    'colon punctuation' => ['Field: draft ready', 'Field: draft ready'],
    'close paren punctuation' => ['Close ) marker', 'Close ) marker'],
    'bracket punctuation' => ['Bracket [review] note', 'Bracket [review] note'],
    'brace punctuation' => ['Brace {review=true} note', 'Brace {review=true} note'],
    'angle entity' => ['Angle &lt;review&gt; note', 'Angle <review> note'],
    'query entity' => ['Path /docs?q=1&amp;page=2', 'Path /docs?q=1&page=2'],
    'asterisk punctuation' => ['Literal *stars* stay', 'Literal *stars* stay'],
    'underscore punctuation' => ['Literal _under_ stay', 'Literal _under_ stay'],
    'backtick punctuation' => ['Literal `code` stay', 'Literal `code` stay'],
    'dash entity' => ['Dash &mdash; marker', "Dash \u{2014} marker"],
    'emoji entity' => ['Emoji &#x1f600; note', "Emoji \u{1F600} note"],
    'replacement entity' => ['Replacement &#0; char', "Replacement \u{FFFD} char"],
];

$delimiters = [
    'double quoted' => '"',
    'single quoted' => "'",
    'parenthesized' => '(',
];

$tests = [];
foreach ($titleCases as $name => [$sourceTitle, $expectedTitle]) {
    foreach ($delimiters as $delimiterName => $delimiter) {
        $tests['maps upstream markdown title-only inline target ' . $delimiterName . ' ' . $name] =
            static function (TestRunner $t) use (
                $delimiter,
                $delimiterName,
                $expectedTitle,
                $firstNodeOfType,
                $htmlAttr,
                $name,
                $sourceTitle,
                $titleTargetSource
            ): void {
                $target = $titleTargetSource($delimiter, $sourceTitle);
                $label = 'empty target ' . preg_replace('/[^a-z0-9]+/', '-', strtolower($delimiterName . ' ' . $name));

                $inline = (new MarkdownReader())->read('Inline [' . $label . '](' . $target . '){#target .empty data-kind="title-only"} after.');
                $inlineLink = $firstNodeOfType($inline, 'link') ?? new AstNode('missing');

                $t->same('link', $inlineLink->type, $delimiterName . ' ' . $name . ' inline type');
                $t->same('', $inlineLink->attr('url'), $delimiterName . ' ' . $name . ' inline url');
                $t->same($expectedTitle, $inlineLink->attr('title'), $delimiterName . ' ' . $name . ' inline title');
                $t->same('target', $inlineLink->attr('id'), $delimiterName . ' ' . $name . ' inline id');
                $t->same(['empty'], $inlineLink->attr('classes'), $delimiterName . ' ' . $name . ' inline classes');
                $t->same(['data-kind' => 'title-only'], $inlineLink->attr('attributes'), $delimiterName . ' ' . $name . ' inline attributes');
                $t->same($label, $inlineLink->children[0]->attr('text'), $delimiterName . ' ' . $name . ' inline label');

                $reference = (new MarkdownReader())->read('[' . $label . "]\n\n[" . $label . ']: ' . $target);
                $referenceLink = $firstNodeOfType($reference, 'link') ?? new AstNode('missing');

                $t->same('link', $referenceLink->type, $delimiterName . ' ' . $name . ' reference type');
                $t->same('', $referenceLink->attr('url'), $delimiterName . ' ' . $name . ' reference url');
                $t->same($expectedTitle, $referenceLink->attr('title'), $delimiterName . ' ' . $name . ' reference title');
                $t->same($label, $referenceLink->children[0]->attr('text'), $delimiterName . ' ' . $name . ' reference label');

                $imageDocument = (new MarkdownReader())->read('Inline ![' . $label . '](' . $target . ') after.');
                $image = $firstNodeOfType($imageDocument, 'image') ?? new AstNode('missing');

                $t->same('image', $image->type, $delimiterName . ' ' . $name . ' image type');
                $t->same('', $image->attr('url'), $delimiterName . ' ' . $name . ' image url');
                $t->same($expectedTitle, $image->attr('title'), $delimiterName . ' ' . $name . ' image title');
                $t->same($label, $image->attr('alt'), $delimiterName . ' ' . $name . ' image alt');

                $blocks = (new WordPressBlockWriter())->write($reference);
                $t->contains(
                    '<a href="" title="' . $htmlAttr($expectedTitle) . '">' . $label . '</a>',
                    $blocks,
                    $delimiterName . ' ' . $name . ' wordpress link'
                );
            };
    }
}

$tests['records markdown inline target surge mapped-case count'] = static function (TestRunner $t) use ($delimiters, $titleCases): void {
    $t->same(60, count($titleCases) * count($delimiters));
};

return $tests;
