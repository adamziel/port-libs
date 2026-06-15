<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$link = static fn (string $url, string $label, array $attrs = []): AstNode => new AstNode(
    'link',
    ['url' => $url] + $attrs,
    [$text($label)]
);
$image = static fn (string $url, string $alt): AstNode => new AstNode(
    'image',
    ['url' => $url, 'alt' => $alt],
    []
);

$markers = [
    'default period marker' => ['#. ', '\\#. '],
    'default paren marker' => ['#) ', '\\#) '],
    'upper alpha period marker' => ['A.  ', 'A\\.  '],
    'upper alpha paren marker' => ['B)  ', 'B\\)  '],
    'lower alpha period marker' => ['a.  ', 'a\\.  '],
    'lower alpha paren marker' => ['z)  ', 'z\\)  '],
    'upper roman single period marker' => ['I.  ', 'I\\.  '],
    'upper roman multi period marker' => ['IV. ', 'IV\\. '],
    'lower roman multi period marker' => ['iv. ', 'iv\\. '],
    'lower roman nine period marker' => ['ix. ', 'ix\\. '],
    'parenthesized decimal marker' => ['(1) ', '\\(1) '],
    'parenthesized multi decimal marker' => ['(12) ', '\\(12) '],
    'parenthesized upper alpha marker' => ['(A)  ', '\\(A)  '],
    'parenthesized lower alpha marker' => ['(z)  ', '\\(z)  '],
    'numbered example marker' => ['(@) ', '\\(@) '],
    'labeled numbered example marker' => ['(@fig-1) ', '\\(@fig-1) '],
];

$suffixes = [
    'literal import',
    'source packet',
    'review handoff',
    'plain paragraph',
];

$tests = [];
foreach ($markers as $markerName => [$sourceMarker, $expectedMarker]) {
    foreach ($suffixes as $suffix) {
        $source = $sourceMarker . $suffix;
        $expected = $expectedMarker . $suffix;
        $testName = preg_replace('/[^a-z0-9]+/', ' ', strtolower($markerName . ' ' . $suffix)) ?? $markerName;

        $tests['maps upstream markdown writer inline escape fancy ordered literal ' . trim($testName)] =
            static function (TestRunner $t) use ($document, $expected, $paragraph, $source, $text): void {
                $input = $document([$paragraph([$text($source)])]);
                $markdown = (new MarkdownWriter())->write($input);

                $t->same($expected, $markdown);

                $unescaped = (new MarkdownReader())->read($source);
                $t->same('ordered_list', $unescaped->children[0]->type);

                $roundTrip = (new MarkdownReader())->read($markdown);
                $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children));
                $t->same($source, $roundTrip->children[0]->attr('text'));
            };
    }
}

$quoteDestinations = [
    'double quoted path segment' => '/review/"packet".md',
    'single quoted path segment' => "/review/'packet'.md",
    'leading double quote path segment' => '/review/"packet.md',
    'trailing double quote path segment' => '/review/packet".md',
    'leading single quote path segment' => "/review/'packet.md",
    'trailing single quote path segment' => "/review/packet'.md",
    'https double quote query value' => 'https://example.test/source?title="packet"',
    'https single quote query value' => "https://example.test/source?title='packet'",
    'mailto double quote local part' => 'mailto:editor"name@example.test',
    'mailto single quote local part' => "mailto:editor'name@example.test",
    'media double quote filename' => 'media/"hero".png',
    'media single quote filename' => "media/'hero'.png",
    'fragment double quote marker' => '#frag"ment',
    'fragment single quote marker' => "#frag'ment",
    'data uri csv quote payload' => 'data:text/plain,"hello"',
];

$escapedDestination = static fn (string $url): string => '<' . str_replace(
    ['\\', '<', '>', '"', "'"],
    ['\\\\', '\\<', '\\>', '\\"', "\\'"],
    $url
) . '>';

foreach ($quoteDestinations as $destinationName => $url) {
    $expectedDestination = $escapedDestination($url);
    $testName = preg_replace('/[^a-z0-9]+/', ' ', strtolower($destinationName)) ?? $destinationName;

    $tests['maps upstream markdown writer inline link quote destination ' . trim($testName)] =
        static function (TestRunner $t) use ($document, $expectedDestination, $link, $paragraph, $url): void {
            $markdown = (new MarkdownWriter())->write($document([$paragraph([$link($url, 'packet')])]));

            $t->same('[packet](' . $expectedDestination . ')', $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $node = $roundTrip->children[0]->children[0];
            $t->same('link', $node->type);
            $t->same($url, $node->attr('url'));
            $t->same('packet', $node->children[0]->attr('text'));
        };

    $tests['maps upstream markdown writer image quote destination ' . trim($testName)] =
        static function (TestRunner $t) use ($document, $expectedDestination, $image, $paragraph, $url): void {
            $markdown = (new MarkdownWriter())->write($document([$paragraph([$image($url, 'packet')])]));

            $t->same('![packet](' . $expectedDestination . ')', $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $node = $roundTrip->children[0]->children[0];
            $t->same('image', $node->type);
            $t->same($url, $node->attr('url'));
            $t->same('packet', $node->attr('alt'));
        };

    $tests['maps upstream markdown writer reference quote destination ' . trim($testName)] =
        static function (TestRunner $t) use ($document, $expectedDestination, $link, $paragraph, $url): void {
            $markdown = (new MarkdownWriter(['referenceLinks' => true]))->write($document([$paragraph([$link($url, 'packet')])]));

            $t->same("[packet]\n\n  [packet]: " . $expectedDestination, $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $node = $roundTrip->children[0]->children[0];
            $t->same('link', $node->type);
            $t->same($url, $node->attr('url'));
            $t->same('packet', $node->children[0]->attr('text'));
        };

    $tests['maps upstream markdown writer titled link quote destination ' . trim($testName)] =
        static function (TestRunner $t) use ($document, $expectedDestination, $link, $paragraph, $url): void {
            $markdown = (new MarkdownWriter())->write($document([$paragraph([$link($url, 'packet', ['title' => 'Source title'])])]));

            $t->same('[packet](' . $expectedDestination . ' "Source title")', $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $node = $roundTrip->children[0]->children[0];
            $t->same('link', $node->type);
            $t->same($url, $node->attr('url'));
            $t->same('Source title', $node->attr('title'));
        };
}

return $tests;
