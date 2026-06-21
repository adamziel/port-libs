<?php

declare(strict_types=1);

use PortLibs\Pandoc\OpmlReader;
use PortLibs\Pandoc\PandocConverter;

return [
    'maps opml metadata outlines markdown notes and link headings into ast blocks' => static function (TestRunner $t): void {
        $opml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<opml version="2.0">
  <head>
    <title>Migration Outline</title>
    <ownerName>Editor One</ownerName>
    <dateModified>Sun, 21 Jun 2026 02:20:00 GMT</dateModified>
  </head>
  <body>
    <outline text="Project &lt;em&gt;Plan&lt;/em&gt;" _note="Intro **note**."/>
    <outline text="Reference" type="link" url="https://example.test/ref">
      <outline text="Child item" _note="- Check one&#10;- Check two"/>
    </outline>
  </body>
</opml>
XML;

        $document = PandocConverter::read($opml, 'opml');
        $blocks = PandocConverter::write($document, 'blocks');
        $markdown = PandocConverter::write($document, 'markdown');
        $meta = $document->attr('meta');

        $t->same('opml', $document->attr('sourceFormat'));
        $t->same(OpmlReader::class, $meta['reader']);
        $t->same('bounded-opml-reader', $meta['readerScope']);
        $t->same('Migration Outline', $meta['title']);
        $t->same(['Editor One'], $meta['authors']);
        $t->same('Sun, 21 Jun 2026 02:20:00 GMT', $meta['date']);
        $t->same(3, $meta['opmlOutlineCount']);
        $t->same(1, $meta['opmlLinkOutlineCount']);
        $t->same(2, $meta['opmlNoteCount']);
        $t->same('heading', $document->children[0]->type);
        $t->same(1, $document->children[0]->attr('level'));
        $t->same('Project Plan', $document->children[0]->attr('text'));
        $t->same('emph', $document->children[0]->children[1]->type);
        $t->same('paragraph', $document->children[1]->type);
        $t->same('Reference', $document->children[2]->attr('text'));
        $t->same('link', $document->children[2]->children[0]->type);
        $t->same('https://example.test/ref', $document->children[2]->children[0]->attr('url'));
        $t->same(2, $document->children[3]->attr('level'));
        $t->same('bullet_list', $document->children[4]->type);
        $t->contains('<h1>Project <em>Plan</em></h1>', $blocks);
        $t->contains('<p>Intro <strong>note</strong>.</p>', $blocks);
        $t->contains('<h1><a href="https://example.test/ref">Reference</a></h1>', $blocks);
        $t->contains('<h2>Child item</h2>', $blocks);
        $t->contains('<ul><li>Check one</li><li>Check two</li></ul>', $blocks);
        $t->contains('# Project *Plan*', $markdown);
        $t->contains('# [Reference](https://example.test/ref)', $markdown);
        $t->contains('## Child item', $markdown);
    },
    'rejects malformed opml instead of falling back to markdown text' => static function (TestRunner $t): void {
        $t->throws(\InvalidArgumentException::class, static function (): void {
            (new OpmlReader())->read('<opml><body><outline text="Unclosed"></body></opml>');
        });
    },
];
