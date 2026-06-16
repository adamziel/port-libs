<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$inlineDocument = static fn (array $children): AstNode => $document([$paragraph($children)]);
$link = static fn (string $url, string $label): AstNode => new AstNode('link', ['url' => $url], [$text($label)]);
$image = static fn (string $url, string $label): AstNode => new AstNode('image', ['url' => $url, 'alt' => $label]);
$write = static fn (AstNode $doc, array $options = []): string => (new MarkdownWriter($options))->write($doc);
$read = static fn (string $markdown): AstNode => (new MarkdownReader())->read($markdown);
$slug = static function (string $value): string {
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? $value);

    return trim($slug, '-') ?: 'case';
};

$collectNodes = null;
$collectNodes = static function (AstNode $node, string $type) use (&$collectNodes): array {
    $matches = [];
    if ($node->type === $type) {
        $matches[] = $node;
    }

    foreach ($node->children as $child) {
        array_push($matches, ...$collectNodes($child, $type));
    }

    return $matches;
};

$labelCases = [
    'balanced packet' => ['Review [packet]', 'Review [packet]'],
    'balanced draft' => ['Source [draft]', 'Source [draft]'],
    'balanced nested' => ['Review [packet [draft]]', 'Review [packet [draft]]'],
    'two balanced groups' => ['A [one] B [two]', 'A [one] B [two]'],
    'close bracket' => ['Review ] packet', 'Review \\] packet'],
    'double close bracket' => ['Review ]] packet', 'Review \\]\\] packet'],
    'open bracket' => ['Review [ packet', 'Review \\[ packet'],
    'double open bracket' => ['Review [[ packet', 'Review \\[\\[ packet'],
    'balanced plus close' => ['Review [packet] ] tail', 'Review [packet] \\] tail'],
    'close plus balanced' => ['Review ] [packet] tail', 'Review \\] [packet] tail'],
    'nested plus close' => ['Review [packet [draft]] ] tail', 'Review [packet [draft]] \\] tail'],
    'open plus balanced' => ['Review [ packet [draft]', 'Review \\[ packet [draft]'],
    'adjacent balanced groups' => ['Review [a][b] packet', 'Review [a][b] packet'],
    'spaced nested groups' => ['Review [a [b] c] packet', 'Review [a [b] c] packet'],
    'trailing open after balanced' => ['Review [a] [ packet', 'Review [a] \\[ packet'],
    'leading close before nested' => ['Review ] [a [b]] packet', 'Review \\] [a [b]] packet'],
    'numeric bracket label' => ['Case [2026] packet', 'Case [2026] packet'],
    'path bracket label' => ['Path [a/b] packet', 'Path [a/b] packet'],
    'query bracket label' => ['Query [a?b=1] packet', 'Query [a?b=1] packet'],
    'colon bracket label' => ['Term [key: value] packet', 'Term [key: value] packet'],
];

$tests = [];

foreach ($labelCases as $name => [$label, $labelMarkdown]) {
    $tests['maps upstream markdown writer direct link bracket label ' . $name] =
        static function (TestRunner $t) use ($collectNodes, $inlineDocument, $label, $labelMarkdown, $link, $read, $slug, $write, $name): void {
            $url = '/direct-link-' . $slug($name);
            $markdown = $write($inlineDocument([$link($url, $label)]));

            $t->same('[' . $labelMarkdown . '](' . $url . ')', $markdown);

            $nodes = $collectNodes($read($markdown), 'link');
            $node = $nodes[0] ?? new AstNode('missing');
            $t->same('link', $node->type, $name);
            $t->same($url, $node->attr('url'), $name);
            $t->same($label, $node->children[0]->attr('text'), $name);
        };

    $tests['maps upstream markdown writer direct image bracket label ' . $name] =
        static function (TestRunner $t) use ($collectNodes, $image, $inlineDocument, $label, $labelMarkdown, $read, $slug, $write, $name): void {
            $url = 'media/direct-image-' . $slug($name) . '.png';
            $markdown = $write($inlineDocument([$image($url, $label)]));

            $t->same('![' . $labelMarkdown . '](' . $url . ')', $markdown);

            $nodes = $collectNodes($read($markdown), 'image');
            $node = $nodes[0] ?? new AstNode('missing');
            $t->same('image', $node->type, $name);
            $t->same($url, $node->attr('url'), $name);
            $t->same($label, $node->attr('alt'), $name);
        };
}

$unicodePairs = [
    'latin cafe' => ["CAF\u{00C9}", "caf\u{00E9}"],
    'latin resume' => ["R\u{00C9}SUM\u{00C9}", "r\u{00E9}sum\u{00E9}"],
    'latin angstrom' => ["\u{00C5}NGSTR\u{00D6}M", "\u{00E5}ngstr\u{00F6}m"],
    'latin munchen' => ["M\u{00DC}NCHEN", "m\u{00FC}nchen"],
    'latin cesky' => ["\u{010C}ESK\u{00DD}", "\u{010D}esk\u{00FD}"],
    'latin seker' => ["\u{015E}EKER", "\u{015F}eker"],
    'latin oresund' => ["\u{00D8}RESUND", "\u{00F8}resund"],
    'latin thorn' => ["\u{00DE}ING", "\u{00FE}ing"],
    'greek dokimi' => ["\u{0394}\u{039F}\u{039A}\u{0399}\u{039C}\u{0397}", "\u{03B4}\u{03BF}\u{03BA}\u{03B9}\u{03BC}\u{03B7}"],
    'greek logo' => ["\u{039B}\u{03CC}\u{0393}\u{039F}", "\u{03BB}\u{03CC}\u{03B3}\u{03BF}"],
    'cyrillic dokument' => ["\u{0414}\u{041E}\u{041A}\u{0423}\u{041C}\u{0415}\u{041D}\u{0422}", "\u{0434}\u{043E}\u{043A}\u{0443}\u{043C}\u{0435}\u{043D}\u{0442}"],
    'cyrillic tekst' => ["\u{0422}\u{0415}\u{041A}\u{0421}\u{0422}", "\u{0442}\u{0435}\u{043A}\u{0441}\u{0442}"],
];

foreach ($unicodePairs as $name => [$firstLabel, $secondLabel]) {
    $tests['maps upstream markdown writer unicode folded duplicate reference labels ' . $name] =
        static function (TestRunner $t) use ($collectNodes, $inlineDocument, $link, $read, $slug, $space, $write, $firstLabel, $secondLabel, $name): void {
            $firstUrl = '/unicode-first-' . $slug($name);
            $secondUrl = '/unicode-second-' . $slug($name);
            $markdown = $write($inlineDocument([
                $link($firstUrl, $firstLabel),
                $space(),
                $link($secondUrl, $secondLabel),
            ]), ['referenceLinks' => true]);

            $t->contains('  [' . $firstLabel . ']: ' . $firstUrl, $markdown, $name);
            $t->contains('  [1]: ' . $secondUrl, $markdown, $name);

            $links = $collectNodes($read($markdown), 'link');
            $t->same($firstUrl, $links[0]->attr('url'), $name . ' first url');
            $t->same($secondUrl, $links[1]->attr('url'), $name . ' second url');
            $t->same($firstLabel, $links[0]->children[0]->attr('text'), $name . ' first label');
            $t->same($secondLabel, $links[1]->children[0]->attr('text'), $name . ' second label');
        };
}

$longLabelCases = [
    'latin acute e 500' => str_repeat("\u{00E9}", 500),
    'latin odiaeresis 500' => str_repeat("\u{00F6}", 500),
    'latin thorn 500' => str_repeat("\u{00FE}", 500),
    'latin macron a 500' => str_repeat("\u{0101}", 500),
    'greek alpha 500' => str_repeat("\u{03B1}", 500),
    'greek delta 500' => str_repeat("\u{03B4}", 500),
    'cyrillic de 500' => str_repeat("\u{0434}", 500),
    'cyrillic te 500' => str_repeat("\u{0442}", 500),
    'cjk source 500' => str_repeat("\u{6587}", 500),
    'hiragana source 500' => str_repeat("\u{3042}", 500),
];

foreach ($longLabelCases as $name => $label) {
    $tests['maps upstream markdown writer multibyte reference label length ' . $name] =
        static function (TestRunner $t) use ($collectNodes, $inlineDocument, $label, $link, $read, $slug, $write, $name): void {
            $url = '/long-label-' . $slug($name);
            $markdown = $write($inlineDocument([$link($url, $label)]), ['referenceLinks' => true]);

            $t->true(!str_contains($markdown, '  [1]: ' . $url), $name . ' should not use byte-count numeric fallback');
            $t->contains('  [' . $label . ']: ' . $url, $markdown, $name);

            $links = $collectNodes($read($markdown), 'link');
            $t->same($url, $links[0]->attr('url'), $name);
            $t->same($label, $links[0]->children[0]->attr('text'), $name);
        };
}

foreach ($labelCases as $name => [$label, $labelMarkdown]) {
    $tests['maps upstream markdown writer shortcut reference bracket label ' . $name] =
        static function (TestRunner $t) use ($collectNodes, $inlineDocument, $label, $labelMarkdown, $link, $read, $slug, $write, $name): void {
            $url = '/reference-link-' . $slug($name);
            $markdown = $write($inlineDocument([$link($url, $label)]), ['referenceLinks' => true]);

            $t->same('[' . $labelMarkdown . "]\n\n  [" . $labelMarkdown . ']: ' . $url, $markdown, $name);

            $links = $collectNodes($read($markdown), 'link');
            $t->same($url, $links[0]->attr('url'), $name);
            $t->same($label, $links[0]->children[0]->attr('text'), $name);
        };
}

$imageReferenceCases = array_slice($labelCases, 0, 15, true);
foreach ($imageReferenceCases as $name => [$label, $labelMarkdown]) {
    $tests['maps upstream markdown writer image shortcut reference bracket label ' . $name] =
        static function (TestRunner $t) use ($collectNodes, $image, $inlineDocument, $label, $labelMarkdown, $read, $slug, $write, $name): void {
            $url = 'media/reference-image-' . $slug($name) . '.png';
            $markdown = $write($inlineDocument([$image($url, $label)]), ['referenceLinks' => true]);

            $t->same('![' . $labelMarkdown . "]\n\n  [" . $labelMarkdown . ']: ' . $url, $markdown, $name);

            $images = $collectNodes($read($markdown), 'image');
            $t->same($url, $images[0]->attr('url'), $name);
            $t->same($label, $images[0]->attr('alt'), $name);
        };
}

$explicitSuffixCases = array_slice($labelCases, 0, 10, true);
foreach ($explicitSuffixCases as $name => [$label, $labelMarkdown]) {
    $tests['maps upstream markdown writer full reference suffix bracket label ' . $name] =
        static function (TestRunner $t) use ($collectNodes, $inlineDocument, $label, $labelMarkdown, $link, $read, $slug, $space, $text, $write, $name): void {
            $url = '/suffix-reference-' . $slug($name);
            $markdown = $write($inlineDocument([
                $link($url, $label),
                $space(),
                $link($url, 'again ' . $slug($name)),
                $space(),
                $text('[tail]'),
            ]), ['referenceLinks' => true]);

            $t->contains('[' . $labelMarkdown . ']', $markdown, $name . ' primary reference');
            $t->contains('[again ' . $slug($name) . '][' . $labelMarkdown . ']', $markdown, $name . ' explicit suffix');
            $t->contains('  [' . $labelMarkdown . ']: ' . $url, $markdown, $name . ' definition');

            $links = $collectNodes($read($markdown), 'link');
            $t->same($url, $links[0]->attr('url'), $name . ' first url');
            $t->same($url, $links[1]->attr('url'), $name . ' second url');
        };
}

$tests['records markdown writer link image reference label surge mapped-case count'] =
    static function (TestRunner $t) use ($explicitSuffixCases, $imageReferenceCases, $labelCases, $longLabelCases, $unicodePairs): void {
        $mapped = (count($labelCases) * 3)
            + count($unicodePairs)
            + count($longLabelCases)
            + count($imageReferenceCases)
            + count($explicitSuffixCases);

        $t->same(107, $mapped);
    };

return $tests;
