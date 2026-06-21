<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\OpmlWriter;
use PortLibs\Pandoc\PandocConverter;

return [
    'writes metadata nested section outlines html heading text and markdown notes' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'meta' => [
                'title' => 'Pandoc Test Suite',
                'authors' => ['John MacFarlane', 'Anonymous'],
                'date' => '2006-07-17',
            ],
        ], [
            new AstNode('heading', ['level' => 1, 'text' => 'Headers'], [
                new AstNode('text', ['text' => 'Headers']),
            ]),
            new AstNode('heading', ['level' => 2, 'text' => 'Level 2 with embedded link'], [
                new AstNode('text', ['text' => 'Level 2 with an ']),
                new AstNode('link', ['url' => '/url', 'title' => ''], [
                    new AstNode('text', ['text' => 'embedded link']),
                ]),
            ]),
            new AstNode('paragraph', ['text' => 'with no blank line'], [
                new AstNode('text', ['text' => 'with no blank line']),
            ]),
            new AstNode('heading', ['level' => 2, 'text' => 'Level 2 with emphasis'], [
                new AstNode('text', ['text' => 'Level 2 with ']),
                new AstNode('emph', [], [
                    new AstNode('text', ['text' => 'emphasis']),
                ]),
            ]),
            new AstNode('paragraph', ['text' => 'There should be a hard line break here.'], [
                new AstNode('text', ['text' => 'There should be a hard line break']),
                new AstNode('linebreak'),
                new AstNode('text', ['text' => 'here.']),
            ]),
        ]);

        $opml = (new OpmlWriter())->write($document);
        $roundTrip = PandocConverter::read($opml, 'opml');
        $roundTripMeta = $roundTrip->attr('meta');

        $t->contains('<?xml version="1.0" encoding="UTF-8"?>', $opml);
        $t->contains('<title>Pandoc Test Suite</title>', $opml);
        $t->contains('<dateModified>Mon, 17 Jul 2006 00:00:00 UTC</dateModified>', $opml);
        $t->contains('<ownerName>John MacFarlane; Anonymous</ownerName>', $opml);
        $t->contains('<outline text="Headers">', $opml);
        $t->contains('  <outline text="Level 2 with an &lt;a href=&quot;/url&quot;&gt;embedded link&lt;/a&gt;" _note="with no blank line">', $opml);
        $t->contains('  <outline text="Level 2 with &lt;em&gt;emphasis&lt;/em&gt;" _note="There should be a hard line break\\&#10;here.">', $opml);
        $t->same('opml', $roundTrip->attr('sourceFormat'));
        $t->same('Pandoc Test Suite', $roundTripMeta['title']);
        $t->same(['John MacFarlane; Anonymous'], $roundTripMeta['authors']);
        $t->same(3, $roundTripMeta['opmlOutlineCount']);
        $t->same(2, $roundTripMeta['opmlNoteCount']);
        $t->same('Level 2 with an embedded link', $roundTrip->children[1]->attr('text'));
        $t->same('link', $roundTrip->children[1]->children[1]->type);
        $t->same('/url', $roundTrip->children[1]->children[1]->attr('url'));
        $t->same('Level 2 with emphasis', $roundTrip->children[3]->attr('text'));
        $t->same('emph', $roundTrip->children[3]->children[1]->type);
    },
    'writes body only when standalone is disabled' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('heading', ['level' => 1, 'text' => 'Root']),
        ]);

        $opml = (new OpmlWriter(['standalone' => false]))->write($document);

        $t->same("<outline text=\"Root\">\n</outline>", $opml);
    },
    'serializes markdown notes with pandoc opml writer-compatible markers' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('heading', ['level' => 1, 'text' => 'Root'], [
                new AstNode('text', ['text' => 'Root']),
            ]),
            new AstNode('horizontal_rule'),
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [
                    new AstNode('plain', [], [
                        new AstNode('text', ['text' => 'tight bullet']),
                    ]),
                ]),
                new AstNode('list_item', [], [
                    new AstNode('plain', [], [
                        new AstNode('raw_tex_inline', ['tex' => '\\cite[22-23]{smith.1899}']),
                    ]),
                ]),
            ]),
            new AstNode('ordered_list', [], [
                new AstNode('list_item', [], [
                    new AstNode('plain', [], [
                        new AstNode('text', ['text' => 'tight ordered']),
                    ]),
                ]),
            ]),
            new AstNode('definition_list', [], [
                new AstNode('definition_item', [], [
                    new AstNode('plain', [], [
                        new AstNode('text', ['text' => 'apple']),
                    ]),
                    new AstNode('definition', [], [
                        new AstNode('plain', [], [
                            new AstNode('text', ['text' => 'red fruit']),
                        ]),
                    ]),
                ]),
            ]),
            new AstNode('div', [], [
                new AstNode('paragraph', [], [
                    new AstNode('text', ['text' => 'inside div']),
                ]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('link', ['url' => '', 'title' => ''], [
                    new AstNode('text', ['text' => 'Empty']),
                ]),
                new AstNode('text', ['text' => '. ']),
                new AstNode('link', ['url' => '/url/', 'title' => 'title with "quotes" in it'], [
                    new AstNode('text', ['text' => 'URL and title']),
                ]),
                new AstNode('text', ['text' => '.']),
            ]),
        ]);

        $opml = (new OpmlWriter(['columns' => 80]))->write($document);

        $t->contains(str_repeat('-', 80), $opml);
        $t->contains('- tight bullet', $opml);
        $t->contains('- `\\cite[22-23]{smith.1899}`{=tex}', $opml);
        $t->contains('1.  tight ordered', $opml);
        $t->contains('apple&#10;: red fruit', $opml);
        $t->contains('::: {}&#10;inside div&#10;:::', $opml);
        $t->contains('[Empty]().', $opml);
        $t->contains('[URL and title](/url/ &quot;title with &quot;quotes&quot; in it&quot;).', $opml);
    },
];
