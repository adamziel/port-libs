<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
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

        $semanticMeta = array_intersect_key($meta, array_flip([
            'title',
            'titleInlines',
            'author',
            'authorInlines',
            'date',
            'dateInlines',
        ]));
        $semanticDocument = new AstNode('document', ['meta' => $semanticMeta], $document->children);
        $canonicalNative = PandocConverter::write($semanticDocument, 'native', ['standalone' => true]);
        $expectedNative = rtrim(<<<'NATIVE'
Pandoc
  Meta { unMeta = fromList [ ( "author" , MetaList [ MetaInlines [ Str "Dave" , Space , Str "Winer" ] ] ) , ( "date" , MetaInlines [ Str "Thu," , Space , Str "14" , Space , Str "Jul" , Space , Str "2005" , Space , Str "23:41:05" , Space , Str "GMT" ] ) , ( "title" , MetaInlines [ Str "States" ] ) ] }
  [ Header 1 ( "" , [  ] , [  ] ) [ Str "United" , Space , Str "States" ]
  , Header 2 ( "" , [  ] , [  ] ) [ Str "Far" , Space , Str "West" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Alaska" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "California" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Hawaii" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Strong [ Str "Nevada" ] ]
  , Para [ Str "I" , Space , Str "lived" , Space , Str "here" , Space , Emph [ Str "once" ] , Str "." ]
  , Para [ Str "Loved" , Space , Str "it." ]
  , Header 4 ( "" , [  ] , [  ] ) [ Link ( "" , [  ] , [  ] ) [ Str "Reno" ] ( "http://www.reno.gov" , "" ) ]
  , Header 4 ( "" , [  ] , [  ] ) [ Str "Las" , Space , Str "Vegas" ]
  , Header 4 ( "" , [  ] , [  ] ) [ Str "Ely" ]
  , Header 4 ( "" , [  ] , [  ] ) [ Str "Gerlach" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Oregon" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Washington" ]
  , Header 2 ( "" , [  ] , [  ] ) [ Str "Great" , Space , Str "Plains" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Kansas" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Nebraska" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "North" , Space , Str "Dakota" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Oklahoma" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "South" , Space , Str "Dakota" ]
  , Header 2 ( "" , [  ] , [  ] ) [ Str "Mid-Atlantic" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Delaware" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Maryland" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "New" , Space , Str "Jersey" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "New" , Space , Str "York" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Pennsylvania" ]
  , Header 2 ( "" , [  ] , [  ] ) [ Str "Midwest" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Illinois" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Indiana" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Iowa" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Kentucky" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Michigan" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Minnesota" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Missouri" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Ohio" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "West" , Space , Str "Virginia" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Wisconsin" ]
  , Header 2 ( "" , [  ] , [  ] ) [ Str "Mountains" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Colorado" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Idaho" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Montana" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Utah" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Wyoming" ]
  , Header 2 ( "" , [  ] , [  ] ) [ Str "New" , Space , Str "England" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Connecticut" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Maine" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Massachusetts" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "New" , Space , Str "Hampshire" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Rhode" , Space , Str "Island" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Vermont" ]
  , Header 2 ( "" , [  ] , [  ] ) [ Str "South" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Alabama" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Arkansas" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Florida" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Georgia" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Louisiana" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Mississippi" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "North" , Space , Str "Carolina" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "South" , Space , Str "Carolina" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Tennessee" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Virginia" ]
  , Header 2 ( "" , [  ] , [  ] ) [ Str "Southwest" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Arizona" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "New" , Space , Str "Mexico" ]
  , Header 3 ( "" , [  ] , [  ] ) [ Str "Texas" ]
  ]
NATIVE, "\n");

        $t->same($expectedNative, $canonicalNative);
    },
    'rejects malformed opml instead of falling back to markdown text' => static function (TestRunner $t): void {
        $t->throws(\InvalidArgumentException::class, static function (): void {
            (new OpmlReader())->read('<opml><body><outline text="Unclosed"></body></opml>');
        });
    },
];
