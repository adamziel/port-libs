<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$softbreak = static fn (): AstNode => new AstNode('softbreak');
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$emph = static fn (array $children): AstNode => new AstNode('emph', [], $children);
$strong = static fn (array $children): AstNode => new AstNode('strong', [], $children);
$link = static fn (array $children): AstNode => new AstNode('link', ['url' => '/softbreak-space'], $children);

$markers = [
    'atx heading marker' => '# imported heading literal',
    'compact atx heading marker' => '##',
    'default fancy period marker' => '#. imported ordered literal',
    'default fancy paren marker' => '#) imported ordered literal',
    'decimal period list marker' => '1. imported ordered literal',
    'decimal paren list marker' => '12) imported ordered literal',
    'upper alpha period fancy marker' => 'A.  imported ordered literal',
    'upper alpha paren fancy marker' => 'B)  imported ordered literal',
    'lower alpha period fancy marker' => 'a.  imported ordered literal',
    'lower alpha paren fancy marker' => 'z)  imported ordered literal',
    'upper roman single period marker' => 'I.  imported roman literal',
    'upper roman multi period marker' => 'IV. imported roman literal',
    'lower roman multi period marker' => 'iv. imported roman literal',
    'lower roman nine period marker' => 'ix. imported roman literal',
    'parenthesized decimal marker' => '(1) imported ordered literal',
    'parenthesized multi decimal marker' => '(12) imported ordered literal',
    'parenthesized upper alpha marker' => '(A)  imported alpha literal',
    'parenthesized lower alpha marker' => '(z)  imported alpha literal',
    'numbered example marker' => '(@) imported numbered example literal',
    'labeled numbered example marker' => '(@fig-1) imported numbered example literal',
    'dash bullet marker' => '- imported bullet literal',
    'plus bullet marker' => '+ imported bullet literal',
    'definition colon marker' => ': imported definition literal',
];

$wrappers = [
    'paragraph text' => [
        'children' => static fn (string $marker): array => [$text('Lead'), $softbreak(), $text($marker)],
        'expected' => static fn (string $marker): string => 'Lead ' . $marker,
        'roundTrip' => true,
    ],
    'emphasis text' => [
        'children' => static fn (string $marker): array => [$emph([$text('Lead'), $softbreak(), $text($marker)])],
        'expected' => static fn (string $marker): string => '*Lead ' . $marker . '*',
    ],
    'strong text' => [
        'children' => static fn (string $marker): array => [$strong([$text('Lead'), $softbreak(), $text($marker)])],
        'expected' => static fn (string $marker): string => '**Lead ' . $marker . '**',
    ],
    'link label text' => [
        'children' => static fn (string $marker): array => [$link([$text('Lead'), $softbreak(), $text($marker)])],
        'expected' => static fn (string $marker): string => '[Lead ' . $marker . '](/softbreak-space)',
    ],
];

$tests = [];
$mappedCaseCount = count($markers) * count($wrappers);

$tests['records markdown writer softbreak escape surge mapped case count'] =
    static function (TestRunner $t) use ($mappedCaseCount): void {
        $t->same(92, $mappedCaseCount);
    };

foreach ($markers as $markerName => $marker) {
    foreach ($wrappers as $wrapperName => $wrapper) {
        $tests["maps upstream markdown writer softbreak space escape {$wrapperName} {$markerName}"] =
            static function (TestRunner $t) use ($document, $marker, $paragraph, $wrapper): void {
                $children = $wrapper['children']($marker);
                $markdown = (new MarkdownWriter(['softBreak' => 'space']))->write($document([$paragraph($children)]));

                $t->same($wrapper['expected']($marker), $markdown);

                if (($wrapper['roundTrip'] ?? false) === true) {
                    $roundTrip = (new MarkdownReader())->read($markdown);
                    $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children));
                    $t->same('Lead ' . $marker, $roundTrip->children[0]->attr('text'));
                }
            };
    }
}

return $tests;
