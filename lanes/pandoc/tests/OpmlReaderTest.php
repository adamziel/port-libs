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
    'maps the upstream opml reader fixture structure' => static function (TestRunner $t): void {
        $opml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<opml version="2.0">
  <head>
    <title>States</title>
    <dateModified>Thu, 14 Jul 2005 23:41:05 GMT</dateModified>
    <ownerName>Dave Winer</ownerName>
  </head>
  <body>
    <outline text="United States">
      <outline text="Far West">
        <outline text="Alaska"/>
        <outline text="California"/>
        <outline text="Hawaii"/>
          <outline text="&lt;strong&gt;Nevada&lt;/strong&gt;" _note="I lived here *once*.&#10;&#10;Loved it.">
          <outline text="Reno" created="Tue, 12 Jul 2005 23:56:35 GMT" type="link" url="http://www.reno.gov"/>
          <outline text="Las Vegas" created="Tue, 12 Jul 2005 23:56:37 GMT"/>
          <outline text="Ely" created="Tue, 12 Jul 2005 23:56:39 GMT"/>
          <outline text="Gerlach" created="Tue, 12 Jul 2005 23:56:47 GMT"/>
          </outline>
        <outline text="Oregon"/>
        <outline text="Washington"/>
        </outline>
      <outline text="Great Plains">
        <outline text="Kansas"/>
        <outline text="Nebraska"/>
        <outline text="North Dakota"/>
        <outline text="Oklahoma"/>
        <outline text="South Dakota"/>
        </outline>
      <outline text="Mid-Atlantic">
        <outline text="Delaware"/>
        <outline text="Maryland"/>
        <outline text="New Jersey"/>
        <outline text="New York"/>
        <outline text="Pennsylvania"/>
        </outline>
      <outline text="Midwest">
        <outline text="Illinois"/>
        <outline text="Indiana"/>
        <outline text="Iowa"/>
        <outline text="Kentucky"/>
        <outline text="Michigan"/>
        <outline text="Minnesota"/>
        <outline text="Missouri"/>
        <outline text="Ohio"/>
        <outline text="West Virginia"/>
        <outline text="Wisconsin"/>
        </outline>
      <outline text="Mountains">
        <outline text="Colorado"/>
        <outline text="Idaho"/>
        <outline text="Montana"/>
        <outline text="Utah"/>
        <outline text="Wyoming"/>
        </outline>
      <outline text="New England">
        <outline text="Connecticut"/>
        <outline text="Maine"/>
        <outline text="Massachusetts"/>
        <outline text="New Hampshire"/>
        <outline text="Rhode Island"/>
        <outline text="Vermont"/>
        </outline>
      <outline text="South">
        <outline text="Alabama"/>
        <outline text="Arkansas"/>
        <outline text="Florida"/>
        <outline text="Georgia"/>
        <outline text="Louisiana"/>
        <outline text="Mississippi"/>
        <outline text="North Carolina"/>
        <outline text="South Carolina"/>
        <outline text="Tennessee"/>
        <outline text="Virginia"/>
      </outline>
      <outline text="Southwest">
        <outline text="Arizona"/>
        <outline text="New Mexico"/>
        <outline text="Texas"/>
      </outline>
    </outline>
  </body>
</opml>
XML;

        $document = PandocConverter::read($opml, 'opml');
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $typeCounts = [];
        foreach ($document->children as $child) {
            $typeCounts[$child->type] = ($typeCounts[$child->type] ?? 0) + 1;
        }

        $t->same('States', $meta['title']);
        $t->same(['Dave Winer'], $meta['authors']);
        $t->same('Thu, 14 Jul 2005 23:41:05 GMT', $meta['date']);
        $t->same(63, $meta['opmlOutlineCount']);
        $t->same(1, $meta['opmlLinkOutlineCount']);
        $t->same(1, $meta['opmlNoteCount']);
        $t->same(63, $typeCounts['heading']);
        $t->same(2, $typeCounts['paragraph']);
        $t->same('United States', $document->children[0]->attr('text'));
        $t->same('Nevada', $document->children[5]->attr('text'));
        $t->same('strong', $document->children[5]->children[0]->type);
        $t->same('I lived here once.', $document->children[6]->attr('text'));
        $t->same('Loved it.', $document->children[7]->attr('text'));
        $t->same('Reno', $document->children[8]->attr('text'));
        $t->same('link', $document->children[8]->children[0]->type);
        $t->same('http://www.reno.gov', $document->children[8]->children[0]->attr('url'));
        $t->contains('<h3><strong>Nevada</strong></h3>', $blocks);
        $t->contains('<h4><a href="http://www.reno.gov">Reno</a></h4>', $blocks);
    },
    'rejects malformed opml instead of falling back to markdown text' => static function (TestRunner $t): void {
        $t->throws(\InvalidArgumentException::class, static function (): void {
            (new OpmlReader())->read('<opml><body><outline text="Unclosed"></body></opml>');
        });
    },
];
