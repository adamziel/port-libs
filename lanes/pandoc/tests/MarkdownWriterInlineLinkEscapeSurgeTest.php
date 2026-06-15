<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);

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

return $tests;
