<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PptxReader;
use PortLibs\Pandoc\PptxWriter;
use PortLibs\Pandoc\ZipPackage;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
$heading = static fn (string $value): AstNode => new AstNode('heading', ['level' => 2], [$text($value)]);
$listItem = static fn (string $value): AstNode => new AstNode('list_item', [], [$plain([$text($value)])]);
$cell = static fn (string $value, array $attrs = []): AstNode => new AstNode('table_cell', ['text' => $value] + $attrs, [$plain([$text($value)])]);
$row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);

$document = static function () use ($text, $paragraph, $heading, $listItem, $cell, $row): AstNode {
    return new AstNode('document', [
        'meta' => [
            'title' => 'Quarterly Plan',
            'author' => 'Ada Lovelace',
            'keywords' => ['slides', 'pptx'],
        ],
    ], [
        $heading('Roadmap'),
        $paragraph([
            $text('Ship '),
            new AstNode('strong', [], [$text('PPTX')]),
            $text(' writer '),
            new AstNode('link', ['url' => 'https://example.test/spec'], [$text('spec')]),
        ]),
        new AstNode('bullet_list', [], [
            $listItem('Native package assembly'),
            $listItem('Reader round trip'),
        ]),
        new AstNode('ordered_list', ['start' => 3], [
            $listItem('Draft'),
            $listItem('Review'),
        ]),
        new AstNode('table', ['caption' => 'Metrics'], [
            new AstNode('table_head', [], [
                $row([$cell('Metric'), $cell('Value')]),
            ]),
            new AstNode('table_body', [], [
                $row([$cell('Coverage'), $cell('PPTX')]),
            ]),
        ]),
        $paragraph([
            new AstNode('image', ['url' => 'images/pixel.png', 'alt' => 'Architecture diagram', 'title' => 'Architecture']),
        ]),
        $heading('Second'),
        $paragraph([$text('Follow-up slide')]),
    ]);
};

$mediaOptions = [
    'modified' => '2026-07-01T00:00:00Z',
    'mediaResources' => [
        'images/pixel.png' => [
            'data' => "\x89PNG\r\n\x1a\nfake",
            'mimeType' => 'image/png',
        ],
    ],
];

$upstreamSpeakerNotesNative = <<<'NATIVE'
[Para [Str "Here",Space,Str "is",Space,Str "a",Space,Str "slide."]
,Div ("",["notes"],[])
 [Para [Str "Here",Space,Str "is",Space,Str "a",Space,Str "note."]
 ,Para [Str "Here",Space,Str "is",Space,Emph [Str "some"],Space,Strong [Str "other"],Space,Str "formatting."]]
,HorizontalRule
,Para [Str "A",Space,Str "page",Space,Str "with",Space,Str "no",Space,Str "speaker",Space,Str "notes"]
,HorizontalRule
,Div ("",["notes"],[])
 [Para [Str "The",Space,Str "first",Space,Str "note",Space,Str "div"]]
,Para [Str "A",Space,Str "page",Space,Str "with",Space,Str "two",Space,Str "notes."]
,Div ("",["notes"],[])
 [Para [Str "The",Space,Str "second",Space,Str "note",Space,Str "div"]]
,HorizontalRule
,Para [Str "Strip",Space,Str "links",Space,Str "and",Space,Str "footnotes."]
,Div ("",["notes"],[])
 [Para [Str "No",Space,Link ("",[],[]) [Str "link"] ("https://www.google.com",""),Space,Str "here."]
 ,Para [Str "No",Space,Str "note",Space,Str "here.",Note [Para [Str "You'll",Space,Str "never",Space,Str "read",Space,Str "this"]]]]]
NATIVE;

$metadataSpeakerNotesNative = <<<'NATIVE'
Pandoc (Meta {unMeta = fromList [("author",MetaInlines [Str "Jesse",Space,Str "Rosenthal"]),("notes",MetaBlocks [Para [Str "These",Space,Str "are",Space,Str "speaker",Space,Str "notes",Space,Str "from",Space,Str "metadata."]]),("title",MetaInlines [Str "Testing"])]})
[Header 1 ("a-header",[],[]) [Str "A",Space,Str "header"]
,Para [Str "And",Space,Str "a",Space,Str "new",Space,Str "slide."]]
NATIVE;

$upstreamRawOpenXmlNative = <<<'NATIVE'
[Para [Str "Here",Space,Str "is",Space,Str "some",Space,Str "text,",Space,Str "written",Space,Str "as",Space,Str "a",Space,Str "raw",Space,Str "inline:",Space,RawInline (Format "openxml") "<a:r><a:rPr /><a:t>Here are examples of </a:t></a:r><a:r><a:rPr i=\"1\" /><a:t>italics</a:t></a:r><a:r><a:rPr /><a:t>, </a:t></a:r><a:r><a:rPr b=\"1\" /><a:t>bold</a:t></a:r>"]
,HorizontalRule
,RawBlock (Format "openxml") "<p:sp>\n        <p:nvSpPr>\n          <p:cNvPr id=\"3\" name=\"Content Placeholder 2\" />\n          <p:cNvSpPr>\n            <a:spLocks noGrp=\"1\" />\n          </p:cNvSpPr>\n          <p:nvPr>\n            <p:ph idx=\"1\" />\n          </p:nvPr>\n        </p:nvSpPr>\n        <p:spPr />\n        <p:txBody>\n          <a:bodyPr />\n          <a:lstStyle />\n          <a:p>\n            <a:pPr lvl=\"1\" />\n            <a:r>\n              <a:rPr />\n              <a:t>Bulleted bulleted lists.</a:t>\n            </a:r>\n          </a:p>\n          <a:p>\n            <a:pPr lvl=\"1\" />\n            <a:r>\n              <a:rPr />\n              <a:t>And go to arbitrary depth.</a:t>\n            </a:r>\n          </a:p>\n          <a:p>\n            <a:pPr lvl=\"2\" />\n            <a:r>\n              <a:rPr />\n              <a:t>Like this</a:t>\n            </a:r>\n          </a:p>\n          <a:p>\n            <a:pPr lvl=\"3\" />\n            <a:r>\n              <a:rPr />\n              <a:t>Or this</a:t>\n            </a:r>\n          </a:p>\n          <a:p>\n            <a:pPr lvl=\"2\" />\n            <a:r>\n              <a:rPr />\n              <a:t>Back to here.</a:t>\n            </a:r>\n          </a:p>\n        </p:txBody>\n      </p:sp>"]
NATIVE;

$upstreamEndnotesNative = <<<'NATIVE'
Pandoc (Meta {unMeta = fromList []})
[Para [Str "Here",Space,Str "is",Space,Str "one",Space,Str "note.",Note [Para [Str "Here",Space,Str "is",Space,Str "the",Space,Str "note."]],Space,Str "And",Space,Str "one",Space,Str "more",Space,Str "note.",Note [Para [Str "And",Space,Str "another",Space,Str "note."]]]]
NATIVE;

$upstreamRemoveEmptySlidesNative = <<<'NATIVE'
[Para [Str "Content"]
,Para [Image ("",[],[]) [] ("lalune.jpg",""),Space,RawInline (Format "html") "<!--  -->"]
,HorizontalRule
,HorizontalRule
,Para [Str "More",Space,Str "content"]]
NATIVE;

$upstreamSlideLevelZeroNative = <<<'NATIVE'
[Header 1 ("hello",[],[]) [Str "Hello"]
,Para [Image ("",[],[]) [Str "An",Space,Str "image"] ("lalune.jpg","fig:")]]
NATIVE;

$upstreamBackgroundImageNative = <<<'NATIVE'
[Header 1 ("section-header-with-background-image",[],[("background-image","movie.jpg")]) [Str "Section",Space,Str "Header",Space,Str "(with",Space,Str "background",Space,Str "image)"]
,Header 2 ("slide-1",[],[("background-image","lalune.jpg")]) [Str "Slide",Space,Str "1"]
,Para [Str "This",Space,Str "slide",Space,Str "has",Space,Str "a",Space,Str "moon",Space,Str "background."]
,Header 2 ("slide-2",[],[("background-image","movie.jpg")]) [Str "Slide",Space,Str "2"]
,Para [Str "This",Space,Str "slide",Space,Str "has",Space,Str "a",Space,Str "movie",Space,Str "background."]
,Header 2 ("slide-3",[],[("background-image","movie.jpg")]) [Str "Slide",Space,Str "3"]
,Div ("",["columns"],[])
 [Div ("",["column"],[("width","0.5")])
  [Para [Str "One"]]
 ,Div ("",["column"],[("width","0.5")])
  [Para [Str "Two"]]]
,Header 2 ("slide-4",[],[("background-image","movie.jpg")]) [Str "Slide",Space,Str "4"]
,Para [Str "This",Space,Str "slide",Space,Str "has",Space,Str "a",Space,Str "movie",Space,Str "background",Space,Str "and",Space,Str "a",Space,Str "moon",Space,Str "picture."]
,Para [Image ("",[],[]) [Str "An",Space,Str "image"] ("lalune.jpg","fig:")]
,Header 2 ("section",[],[("background-image","lalune.jpg")]) []
,Div ("",["notes"],[])
 [Para [Str "This",Space,Str "slide",Space,Str "has",Space,Str "a",Space,Str "moon",Space,Str "background",Space,Str "and",Space,Str "speaker",Space,Str "notes."]]]
NATIVE;

$collectText = static function (AstNode $node) use (&$collectText): string {
    $text = '';
    if (isset($node->attrs['text']) && is_scalar($node->attrs['text'])) {
        $text .= (string) $node->attrs['text'];
    }
    if ($node->type === 'image') {
        $text .= (string) $node->attr('alt', '');
    }
    foreach ($node->children as $child) {
        $text .= ' ' . $collectText($child);
    }

    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
};

$findNodes = static function (AstNode $node, string $type) use (&$findNodes): array {
    $nodes = $node->type === $type ? [$node] : [];
    foreach ($node->children as $child) {
        foreach ($findNodes($child, $type) as $found) {
            $nodes[] = $found;
        }
    }

    return $nodes;
};

return [
    'writes deterministic presentation package parts' => static function (TestRunner $t) use ($document, $mediaOptions): void {
        $bytes = (new PptxWriter($mediaOptions))->write($document());
        $package = ZipPackage::fromString($bytes);
        $names = $package->names();

        foreach ([
            '[Content_Types].xml',
            '_rels/.rels',
            'docProps/core.xml',
            'docProps/app.xml',
            'ppt/presentation.xml',
            'ppt/_rels/presentation.xml.rels',
            'ppt/slides/slide1.xml',
            'ppt/slides/slide2.xml',
            'ppt/slides/_rels/slide1.xml.rels',
            'ppt/slideLayouts/slideLayout1.xml',
            'ppt/slideMasters/slideMaster1.xml',
            'ppt/theme/theme1.xml',
            'ppt/tableStyles.xml',
            'ppt/media/image1.png',
        ] as $partName) {
            $t->true(in_array($partName, $names, true), "Missing PPTX part {$partName}");
        }

        $contentTypes = $package->read('[Content_Types].xml');
        $t->contains('presentationml.presentation.main+xml', $contentTypes);
        $t->contains('presentationml.slide+xml', $contentTypes);
        $t->contains('Extension="png" ContentType="image/png"', $contentTypes);

        $presentation = $package->read('ppt/presentation.xml');
        $t->contains('r:id="rId1"', $presentation);
        $t->contains('r:id="rId2"', $presentation);

        $slide = $package->read('ppt/slides/slide1.xml');
        $t->contains('<a:buChar', $slide);
        $t->contains('<a:buAutoNum type="arabicPeriod" startAt="3"', $slide);
        $t->contains('drawingml/2006/table', $slide);
        $t->contains('<p:pic>', $slide);
        $t->contains('Architecture diagram', $slide);

        $slideRelationships = $package->read('ppt/slides/_rels/slide1.xml.rels');
        $t->contains('relationships/image', $slideRelationships);
        $t->contains('../media/image1.png', $slideRelationships);
        $t->contains('relationships/hyperlink', $slideRelationships);
        $t->contains('TargetMode="External"', $slideRelationships);

        $coreProperties = $package->read('docProps/core.xml');
        $t->contains('<dc:title>Quarterly Plan</dc:title>', $coreProperties);
        $t->contains('<dc:creator>Ada Lovelace</dc:creator>', $coreProperties);
    },

    'round trips generated pptx through native reader' => static function (TestRunner $t) use ($document, $mediaOptions, $collectText, $findNodes): void {
        $bytes = (new PptxWriter($mediaOptions))->write($document());
        $roundTrip = (new PptxReader())->read($bytes);

        $t->same('pptx', $roundTrip->attr('sourceFormat'));
        $pptx = $roundTrip->attr('pptx');
        $t->same(2, $pptx['slideCount']);
        $text = $collectText($roundTrip);
        foreach ([
            'Roadmap',
            'Ship PPTX writer spec',
            'Native package assembly',
            'Reader round trip',
            'Draft',
            'Review',
            'Metric',
            'Coverage',
            'Architecture diagram',
            'Second',
            'Follow-up slide',
        ] as $needle) {
            $t->contains($needle, $text);
        }

        $tables = $findNodes($roundTrip, 'table');
        $t->same(1, count($tables));
        $t->same(true, $tables[0]->attr('pptxTable'));

        $images = $findNodes($roundTrip, 'image');
        $t->same(1, count($images));
        $t->same('ppt/media/image1.png', $images[0]->attr('url'));
        $t->same('Architecture diagram', $images[0]->attr('alt'));
    },

    'registers pptx writer through converter' => static function (TestRunner $t) use ($document, $mediaOptions): void {
        $t->same(true, PandocConverter::canWrite('pptx'));
        $bytes = PandocConverter::write($document(), 'pptx', $mediaOptions);
        $package = ZipPackage::fromString($bytes);

        $t->contains('presentationml.presentation.main+xml', $package->read('[Content_Types].xml'));
        $t->contains('Roadmap', $package->read('ppt/slides/slide1.xml'));
    },

    'maps upstream speaker notes fixture semantics into notes slides' => static function (TestRunner $t) use ($upstreamSpeakerNotesNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamSpeakerNotesNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));
        $names = $package->names();

        foreach ([
            'ppt/notesMasters/notesMaster1.xml',
            'ppt/notesMasters/_rels/notesMaster1.xml.rels',
            'ppt/notesSlides/notesSlide1.xml',
            'ppt/notesSlides/notesSlide2.xml',
            'ppt/notesSlides/notesSlide3.xml',
            'ppt/notesSlides/_rels/notesSlide1.xml.rels',
            'ppt/notesSlides/_rels/notesSlide2.xml.rels',
            'ppt/notesSlides/_rels/notesSlide3.xml.rels',
        ] as $partName) {
            $t->true(in_array($partName, $names, true), "Missing speaker-notes part {$partName}");
        }
        $t->true(!in_array('ppt/notesSlides/notesSlide4.xml', $names, true), 'Only slides with notes should get notesSlide parts');

        $contentTypes = $package->read('[Content_Types].xml');
        $t->contains('presentationml.notesMaster+xml', $contentTypes);
        $t->contains('presentationml.notesSlide+xml', $contentTypes);

        $presentation = $package->read('ppt/presentation.xml');
        $t->contains('<p:notesMasterIdLst>', $presentation);
        $presentationRels = $package->read('ppt/_rels/presentation.xml.rels');
        $t->contains('relationships/notesMaster', $presentationRels);
        $t->contains('notesMasters/notesMaster1.xml', $presentationRels);

        $slide1Rels = $package->read('ppt/slides/_rels/slide1.xml.rels');
        $slide2Rels = $package->read('ppt/slides/_rels/slide2.xml.rels');
        $slide3Rels = $package->read('ppt/slides/_rels/slide3.xml.rels');
        $slide4Rels = $package->read('ppt/slides/_rels/slide4.xml.rels');
        $t->contains('relationships/notesSlide', $slide1Rels);
        $t->contains('../notesSlides/notesSlide1.xml', $slide1Rels);
        $t->true(!str_contains($slide2Rels, 'relationships/notesSlide'), 'Slide without speaker notes must not point at a notes slide');
        $t->contains('../notesSlides/notesSlide2.xml', $slide3Rels);
        $t->contains('../notesSlides/notesSlide3.xml', $slide4Rels);

        $notes1 = $package->read('ppt/notesSlides/notesSlide1.xml');
        $t->contains('Here', $notes1);
        $t->contains('some', $notes1);
        $t->contains('b="1"', $notes1);

        $notes3 = $package->read('ppt/notesSlides/notesSlide3.xml');
        $t->contains('No', $notes3);
        $t->contains('link', $notes3);
        $t->contains('here.', $notes3);
        $t->true(!str_contains($notes3, 'https://www.google.com'), 'Speaker note hyperlinks are stripped like upstream PowerPoint output');
        $t->true(!str_contains($notes3, "You'll never read this"), 'Inline footnotes inside speaker notes stay out of public notes output');

        $notes1Rels = $package->read('ppt/notesSlides/_rels/notesSlide1.xml.rels');
        $t->contains('relationships/notesMaster', $notes1Rels);
        $t->contains('relationships/slide', $notes1Rels);
        $t->contains('../slides/slide1.xml', $notes1Rels);

        $app = $package->read('docProps/app.xml');
        $t->contains('<Slides>4</Slides>', $app);
        $t->contains('<Notes>3</Notes>', $app);
    },

    'maps metadata speaker notes into the generated presentation notes parts' => static function (TestRunner $t) use ($metadataSpeakerNotesNative, $mediaOptions): void {
        $document = (new NativeReader())->read($metadataSpeakerNotesNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));

        $notes = $package->read('ppt/notesSlides/notesSlide1.xml');
        $t->contains('These', $notes);
        $t->contains('speaker', $notes);
        $t->contains('metadata.', $notes);
        $t->contains('relationships/notesSlide', $package->read('ppt/slides/_rels/slide1.xml.rels'));
        $t->contains('<Notes>1</Notes>', $package->read('docProps/app.xml'));
    },

    'passes upstream raw OpenXML fixture through generated slide XML' => static function (TestRunner $t) use ($upstreamRawOpenXmlNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamRawOpenXmlNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));
        $names = $package->names();

        $t->true(in_array('ppt/slides/slide1.xml', $names, true), 'Expected first raw OpenXML slide');
        $t->true(in_array('ppt/slides/slide2.xml', $names, true), 'Expected horizontal-rule split slide');
        $t->true(!in_array('ppt/slides/slide3.xml', $names, true), 'Raw OpenXML fixture should produce exactly two slides');

        $slide1 = $package->read('ppt/slides/slide1.xml');
        $t->contains('<a:t>Here are examples of </a:t>', $slide1);
        $t->contains('<a:rPr i="1" />', $slide1);
        $t->contains('<a:t>italics</a:t>', $slide1);
        $t->contains('<a:rPr b="1" />', $slide1);
        $t->contains('<a:t>bold</a:t>', $slide1);
        $t->true(!str_contains($slide1, '&lt;a:r'), 'Raw inline OpenXML must not be XML-escaped');

        $slide2 = $package->read('ppt/slides/slide2.xml');
        $t->contains('<p:sp>', $slide2);
        $t->contains('<a:t>Bulleted bulleted lists.</a:t>', $slide2);
        $t->contains('<a:t>And go to arbitrary depth.</a:t>', $slide2);
        $t->contains('<a:t>Like this</a:t>', $slide2);
        $t->contains('<a:t>Or this</a:t>', $slide2);
        $t->contains('<a:t>Back to here.</a:t>', $slide2);
        $t->true(!str_contains($slide2, '&lt;p:sp'), 'Raw block OpenXML must not be XML-escaped');
        $t->contains('<Slides>2</Slides>', $package->read('docProps/app.xml'));
    },

    'maps upstream inline notes into a public endnotes slide' => static function (TestRunner $t) use ($upstreamEndnotesNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamEndnotesNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));
        $names = $package->names();

        $t->true(in_array('ppt/slides/slide1.xml', $names, true), 'Expected content slide');
        $t->true(in_array('ppt/slides/slide2.xml', $names, true), 'Expected generated endnotes slide');
        $t->true(!in_array('ppt/slides/slide3.xml', $names, true), 'Endnotes fixture should produce exactly two slides');

        $slide1 = $package->read('ppt/slides/slide1.xml');
        $slide1Text = trim(preg_replace('/\s+/u', ' ', strip_tags($slide1)) ?? '');
        $t->contains('Here is one note.', $slide1Text);
        $t->contains('And one more note.', $slide1Text);
        $t->contains('baseline="30000"', $slide1);
        $t->contains('ppaction://hlinksldjump', $slide1);
        $t->true(!str_contains($slide1Text, 'Here is the note.'), 'Inline note body should move to the public Notes slide');
        $t->true(!str_contains($slide1Text, 'And another note.'), 'Second inline note body should move to the public Notes slide');

        $slide1Rels = $package->read('ppt/slides/_rels/slide1.xml.rels');
        $t->contains('relationships/slide', $slide1Rels);
        $t->contains('Target="slide2.xml"', $slide1Rels);

        $slide2 = $package->read('ppt/slides/slide2.xml');
        $slide2Text = trim(preg_replace('/\s+/u', ' ', strip_tags($slide2)) ?? '');
        $t->contains('Notes', $slide2Text);
        $t->contains('1. Here is the note.', $slide2Text);
        $t->contains('2. And another note.', $slide2Text);
        $t->contains('<Slides>2</Slides>', $package->read('docProps/app.xml'));
    },

    'drops upstream blank raw-html and unresolved empty-alt image content' => static function (TestRunner $t) use ($upstreamRemoveEmptySlidesNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamRemoveEmptySlidesNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));
        $names = $package->names();

        $t->true(in_array('ppt/slides/slide1.xml', $names, true), 'Expected first non-empty slide');
        $t->true(in_array('ppt/slides/slide2.xml', $names, true), 'Expected second non-empty slide after separators');
        $t->true(!in_array('ppt/slides/slide3.xml', $names, true), 'Consecutive separators and blank-only content must not produce another slide');

        $slide1 = $package->read('ppt/slides/slide1.xml');
        $slide1Text = trim(preg_replace('/\s+/u', ' ', strip_tags($slide1)) ?? '');
        $t->contains('Content', $slide1Text);
        $t->true(!str_contains($slide1Text, 'Image:'), 'Missing image with empty alt should not render URL fallback text');
        $t->true(!str_contains($slide1, '&lt;!--'), 'Raw HTML comment should not be escaped into slide XML');
        $t->true(!str_contains($slide1, '<!--'), 'Raw HTML comment should not be emitted into slide XML');

        $slide2Text = trim(preg_replace('/\s+/u', ' ', strip_tags($package->read('ppt/slides/slide2.xml'))) ?? '');
        $t->contains('More content', $slide2Text);
        $t->contains('<Slides>2</Slides>', $package->read('docProps/app.xml'));
    },

    'uses the first heading as the slide title when slide level is zero' => static function (TestRunner $t) use ($upstreamSlideLevelZeroNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamSlideLevelZeroNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions + ['writerSlideLevel' => 0]))->write($document));
        $names = $package->names();

        $t->true(in_array('ppt/slides/slide1.xml', $names, true), 'Expected generated slide');
        $t->true(!in_array('ppt/slides/slide2.xml', $names, true), 'Slide-level-0 fixture should produce one slide');

        $slide = $package->read('ppt/slides/slide1.xml');
        $slideText = trim(preg_replace('/\s+/u', ' ', strip_tags($slide)) ?? '');
        $t->contains('Hello', $slideText);
        $t->contains('An image', $slideText);
        $t->true(!str_contains($slideText, 'Untitled'), 'First heading should replace the metadata fallback title');
        $t->same(1, substr_count($slideText, 'Hello'));
        $t->contains('<Slides>1</Slides>', $package->read('docProps/app.xml'));
    },

    'maps upstream background-image heading attributes into slide backgrounds' => static function (TestRunner $t) use ($upstreamBackgroundImageNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamBackgroundImageNative);
        $backgroundOptions = array_replace($mediaOptions, [
            'mediaResources' => [
                'movie.jpg' => ['data' => "\xff\xd8movie", 'mimeType' => 'image/jpeg'],
                'lalune.jpg' => ['data' => "\xff\xd8moon", 'mimeType' => 'image/jpeg'],
            ],
        ]);
        $package = ZipPackage::fromString((new PptxWriter($backgroundOptions))->write($document));
        $names = $package->names();

        foreach ([
            'ppt/slides/slide1.xml',
            'ppt/slides/slide2.xml',
            'ppt/slides/slide3.xml',
            'ppt/slides/slide4.xml',
            'ppt/slides/slide5.xml',
            'ppt/slides/slide6.xml',
            'ppt/media/image1.jpg',
            'ppt/media/image2.jpg',
            'ppt/notesSlides/notesSlide1.xml',
        ] as $partName) {
            $t->true(in_array($partName, $names, true), "Missing background-image fixture part {$partName}");
        }
        $t->true(!in_array('ppt/media/image3.jpg', $names, true), 'Repeated background images should reuse media parts by source');

        $contentTypes = $package->read('[Content_Types].xml');
        $t->contains('Extension="jpg" ContentType="image/jpeg"', $contentTypes);

        $slide1 = $package->read('ppt/slides/slide1.xml');
        $t->contains('<p:bg>', $slide1);
        $t->contains('<a:blipFill dpi="0" rotWithShape="1">', $slide1);
        $t->contains('<a:lum/>', $slide1);
        $t->contains('<a:effectLst/>', $slide1);

        $slide1Rels = $package->read('ppt/slides/_rels/slide1.xml.rels');
        $slide2Rels = $package->read('ppt/slides/_rels/slide2.xml.rels');
        $slide3Rels = $package->read('ppt/slides/_rels/slide3.xml.rels');
        $slide5Rels = $package->read('ppt/slides/_rels/slide5.xml.rels');
        $slide6Rels = $package->read('ppt/slides/_rels/slide6.xml.rels');
        $t->contains('relationships/image', $slide1Rels);
        $t->contains('Target="../media/image1.jpg"', $slide1Rels);
        $t->contains('Target="../media/image2.jpg"', $slide2Rels);
        $t->contains('Target="../media/image1.jpg"', $slide3Rels);
        $t->same(2, substr_count($slide5Rels, 'relationships/image'));
        $t->contains('Target="../media/image1.jpg"', $slide5Rels);
        $t->contains('Target="../media/image2.jpg"', $slide5Rels);
        $t->contains('relationships/notesSlide', $slide6Rels);
        $t->contains('Target="../media/image2.jpg"', $slide6Rels);

        $slide5 = $package->read('ppt/slides/slide5.xml');
        $t->contains('<p:bg>', $slide5);
        $t->contains('<p:pic>', $slide5);

        $notes = $package->read('ppt/notesSlides/notesSlide1.xml');
        $t->contains('speaker', $notes);
        $t->contains('<Slides>6</Slides>', $package->read('docProps/app.xml'));
        $t->contains('<Notes>1</Notes>', $package->read('docProps/app.xml'));
    },

    'rejects non-document roots' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => (new PptxWriter())->write(new AstNode('paragraph')));
    },
];
