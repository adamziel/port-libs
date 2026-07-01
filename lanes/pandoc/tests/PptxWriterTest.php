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

$upstreamSpeakerNotesAfterMetadataNative = <<<'NATIVE'
Pandoc (Meta {unMeta = fromList [("author",MetaInlines [Str "Jesse",Space,Str "Rosenthal"]),("title",MetaInlines [Str "Testing"])]})
[Div ("",["notes"],[])
 [Para [Str "Some",Space,Str "speaker",Space,Str "notes"]]
,Header 1 ("a-header",[],[]) [Str "A",Space,Str "header"]
,Para [Str "And",Space,Str "a",Space,Str "new",Space,Str "slide."]]
NATIVE;

$upstreamSpeakerNotesAfterSepsNative = <<<'NATIVE'
[Para [Image ("",[],[]) [Str "The",Space,Str "moon"] ("lalune.jpg","fig:")]
,Div ("",["notes"],[])
 [Para [Str "chicken",Space,Str "and",Space,Str "dumplings"]]
,Table ("",[],[]) (Caption Nothing
 [Para [Str "Demonstration",Space,Str "of",Space,Str "simple",Space,Str "table",Space,Str "syntax,",Space,Str "with",Space,Str "alignment"]])
 [(AlignRight,ColWidthDefault)
 ,(AlignLeft,ColWidthDefault)
 ,(AlignCenter,ColWidthDefault)
 ,(AlignDefault,ColWidthDefault)]
 (TableHead ("",[],[])
 [Row ("",[],[])
  [Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
   [Plain [Str "Right"]]
  ,Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
   [Plain [Str "Left"]]
  ,Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
   [Plain [Str "Center"]]
  ,Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
   [Plain [Str "Default"]]]])
 [(TableBody ("",[],[]) (RowHeadColumns 0)
  []
  [Row ("",[],[])
   [Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
    [Plain [Str "12"]]
   ,Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
    [Plain [Str "12"]]
   ,Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
    [Plain [Str "12"]]
   ,Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
    [Plain [Str "12"]]]
  ,Row ("",[],[])
   [Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
    [Plain [Str "123"]]
   ,Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
    [Plain [Str "123"]]
   ,Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
    [Plain [Str "123"]]
   ,Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
    [Plain [Str "123"]]]
  ,Row ("",[],[])
   [Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
    [Plain [Str "1"]]
   ,Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
    [Plain [Str "1"]]
   ,Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
    [Plain [Str "1"]]
   ,Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
    [Plain [Str "1"]]]])]
 (TableFoot ("",[],[])
 [])
,Div ("",["notes"],[])
 [Para [Str "foo",Space,Str "bar"]]
,Div ("",["columns"],[])
 [Div ("",["column"],[])
  [BulletList
   [[Para [Str "some",Space,Str "stuff"]]
   ,[Para [Str "some",Space,Str "more",Space,Str "stuff"]]]
  ,Div ("",["notes"],[])
   [Para [Str "Some",Space,Str "notes",Space,Str "inside",Space,Str "a",Space,Str "column"]]]
 ,Div ("",["column"],[])
  [Para [Str "Some",Space,Str "other",Space,Emph [Str "stuff"]]]]
,Div ("",["notes"],[])
 [Para [Str "Some",Space,Str "notes",Space,Str "outside",Space,Str "the",Space,Str "column"]]]
NATIVE;

$upstreamRawOpenXmlNative = <<<'NATIVE'
[Para [Str "Here",Space,Str "is",Space,Str "some",Space,Str "text,",Space,Str "written",Space,Str "as",Space,Str "a",Space,Str "raw",Space,Str "inline:",Space,RawInline (Format "openxml") "<a:r><a:rPr /><a:t>Here are examples of </a:t></a:r><a:r><a:rPr i=\"1\" /><a:t>italics</a:t></a:r><a:r><a:rPr /><a:t>, </a:t></a:r><a:r><a:rPr b=\"1\" /><a:t>bold</a:t></a:r>"]
,HorizontalRule
,RawBlock (Format "openxml") "<p:sp>\n        <p:nvSpPr>\n          <p:cNvPr id=\"3\" name=\"Content Placeholder 2\" />\n          <p:cNvSpPr>\n            <a:spLocks noGrp=\"1\" />\n          </p:cNvSpPr>\n          <p:nvPr>\n            <p:ph idx=\"1\" />\n          </p:nvPr>\n        </p:nvSpPr>\n        <p:spPr />\n        <p:txBody>\n          <a:bodyPr />\n          <a:lstStyle />\n          <a:p>\n            <a:pPr lvl=\"1\" />\n            <a:r>\n              <a:rPr />\n              <a:t>Bulleted bulleted lists.</a:t>\n            </a:r>\n          </a:p>\n          <a:p>\n            <a:pPr lvl=\"1\" />\n            <a:r>\n              <a:rPr />\n              <a:t>And go to arbitrary depth.</a:t>\n            </a:r>\n          </a:p>\n          <a:p>\n            <a:pPr lvl=\"2\" />\n            <a:r>\n              <a:rPr />\n              <a:t>Like this</a:t>\n            </a:r>\n          </a:p>\n          <a:p>\n            <a:pPr lvl=\"3\" />\n            <a:r>\n              <a:rPr />\n              <a:t>Or this</a:t>\n            </a:r>\n          </a:p>\n          <a:p>\n            <a:pPr lvl=\"2\" />\n            <a:r>\n              <a:rPr />\n              <a:t>Back to here.</a:t>\n            </a:r>\n          </a:p>\n        </p:txBody>\n      </p:sp>"]
NATIVE;

$upstreamInlineFormattingNative = <<<'NATIVE'
[Para [Str "Here",Space,Str "are",Space,Str "examples",Space,Str "of",Space,Emph [Str "italics"],Str ",",Space,Strong [Str "bold"],Str ",",Space,Str "and",Space,Strong [Emph [Str "bold",Space,Str "italics"]],Str "."]
,Para [Str "Here",Space,Str "is",Space,Strikeout [Str "strook-three"],Space,Str "strike-through",Space,Str "and",Space,SmallCaps [Str "small",Space,Str "caps"],Str "."]
,Para [Str "Here",Space,Str "is",Space,Span ("",["underline"],[]) [Str "some",Space,Emph [Str "underlined"],Space,Strong [Str "text"]],Str "."]
,Para [Str "We",Space,Str "can",Space,Str "also",Space,Str "do",Space,Str "subscripts",Space,Str "(H",Subscript [Str "2"],Str "0)",Space,Str "and",Space,Str "super",Superscript [Str "script"],Str "."]
,RawBlock (Format "html") "<!-- Comments don't show up. -->"]
NATIVE;

$upstreamCodeNative = <<<'NATIVE'
[Header 1 ("header-with-inline-code",[],[]) [Str "Header",Space,Str "with",Space,Code ("",[],[]) "inline code"]
,CodeBlock ("",[],[]) "Code at level 0"
,BulletList
 [[Para [Str "Bullet",Space,Str "item",Space,Str "with",Space,Code ("",[],[]) "inline code"]
  ,CodeBlock ("",[],[]) "Code block at level 1"
  ,BulletList
   [[Para [Str "with",Space,Code ("",[],[]) "nested"]
    ,CodeBlock ("",[],[]) "lvl2\nlvl2\nlvl2"
    ,Header 2 ("second-heading-level-with-code",[],[]) [Str "Second",Space,Str "heading",Space,Str "level",Space,Str "with",Space,Code ("",[],[]) "code"]]]]]
,Header 1 ("syntax-highlighting",[],[]) [Str "Syntax",Space,Str "highlighting"]
,CodeBlock ("",["haskell"],[]) "id :: a -> a\nid x = x"
,BulletList
 [[Para [Str "Nested"]
  ,CodeBlock ("",["haskell"],[]) "g :: Int -> Int\ng x = x * 3"]]
,Header 1 ("two-column-slide",[],[]) [Str "Two",Space,Str "column",Space,Str "slide"]
,Div ("",["columns"],[])
 [Div ("",["column"],[("width","50%")])
  [BulletList
   [[Plain [Str "A",Space,Str "total",Space,Str "alternative",Space,Str "for",Space,Code ("",[],[]) "head"]]]]
 ,Div ("",["column"],[("width","50%")])
  [CodeBlock ("",[],[]) "safeHead :: [a] -> Maybe a\nsafeHead [] = Nothing\nsafeHead (x:_) = Just x"]]]
NATIVE;

$upstreamLineBlocksNative = <<<'NATIVE'
[Header 2 ("line-blocks",[],[]) [Str "Line",Space,Str "blocks"]
,LineBlock [[Str "Alpha"],[Emph [Str "Beta"]],[Str "Gamma"]]
,Para [Str "Hard",LineBreak,Str "break"]
,Div ("",["notes"],[])
 [LineBlock [[Str "Note",Space,Str "one"],[Str "Note",Space,Str "two"]]]]
NATIVE;

$upstreamQuotedInlineNative = <<<'NATIVE'
[Para [Str "He",Space,Str "said",Space,Quoted DoubleQuote [Str "hello",Space,Emph [Str "there"]],Str ".",Space,Quoted SingleQuote [Strong [Str "yes"]]]
,Div ("",["notes"],[])
 [Para [Str "Speaker",Space,Quoted SingleQuote [Str "aside"]]]]
NATIVE;

$upstreamCitationInlineNative = <<<'NATIVE'
[Para [Str "Cited",Space,Cite [Citation {citationId = "doe2026", citationPrefix = [], citationSuffix = [], citationMode = NormalCitation, citationNoteNum = 0, citationHash = 123}] [Str "(",Emph [Str "Doe"],Space,Link ("",[],[]) [Str "2026"] ("https://example.test/ref",""),Str ")"]]
,Div ("",["notes"],[])
 [Para [Str "Note",Space,Cite [Citation {citationId = "note2026", citationPrefix = [], citationSuffix = [], citationMode = NormalCitation, citationNoteNum = 0, citationHash = 456}] [Strong [Str "note"],Space,Str "cite"]]]]
NATIVE;

$upstreamInlineImageAltNative = <<<'NATIVE'
[Para [Str "Inline",Space,Image ("",[],[]) [Emph [Str "figure"],Space,Strong [Str "alt"]] ("missing-inline.png","fig:"),Space,Str "text"]
,Div ("",["notes"],[])
 [Para [Str "Note",Space,Image ("",[],[]) [Str "note",Space,Strong [Str "alt"]] ("missing-note.png","fig:")]]
,Para [Emph [Str "Nested",Space,Image ("",[],[]) [Str "image",Space,Strong [Str "alt"]] ("nested-missing.png","fig:")]]]
NATIVE;

$upstreamMathInlineNative = <<<'NATIVE'
[Para [Str "Inline",Space,Math InlineMath "x^2 + \\frac{a_1}{b}",Space,Str "done"]
,Para [Str "Display",Space,Math DisplayMath "\\sqrt{b^2}"]
,Div ("",["notes"],[])
 [Para [Str "Note",Space,Math InlineMath "\\frac{n}{k}"]]
,Para [Str "Fallback",Space,Math InlineMath "\\frac{a}{"]]
NATIVE;

$upstreamStartNumberingAtNative = <<<'NATIVE'
[Header 2 ("example-numbering-mwe",[],[]) [Str "Example",Space,Str "numbering",Space,Str "MWE"]
,Para [Str "This",Space,Str "is",Space,Str "a",Space,Str "slide",Space,Str "with",Space,Str "examples",Space,Str "in",Space,Str "(1)",Space,Str "and",Space,Str "(2)"]
,OrderedList (1,Example,TwoParens)
 [[Plain [Str "First"]]
 ,[Plain [Str "Second"]]]
,Header 2 ("a-second-slide",[],[]) [Str "A",Space,Str "second",Space,Str "slide"]
,Para [Str "This",Space,Str "second",Space,Str "slide",Space,Str "has",Space,Str "a",Space,Str "third",Space,Str "example",Space,Str "in",Space,Str "(3)."]
,OrderedList (3,Example,TwoParens)
 [[Plain [Str "Third"]]]]
NATIVE;

$upstreamListsNative = <<<'NATIVE'
[Header 1 ("lists",[],[]) [Str "Lists"]
,BulletList
 [[Para [Str "Bulleted",Space,Str "bulleted",Space,Str "lists."]]
 ,[Para [Str "And",Space,Str "go",Space,Str "to",Space,Str "arbitrary",Space,Str "depth."]
  ,BulletList
   [[Para [Str "Like",Space,Str "this"]
    ,BulletList
     [[Plain [Str "Or",Space,Str "this"]]]]
   ,[Para [Str "Back",Space,Str "to",Space,Str "here."]]]]]
,Header 1 ("lists-continued",[],[]) [Str "Lists",Space,Str "(continued)"]
,Para [Str "Lists",Space,Str "can",Space,Str "also",Space,Str "be",Space,Str "numbered:"]
,OrderedList (1,Decimal,Period)
 [[Para [Str "Tomatoes"]]
 ,[Para [Str "Potatoes",Space,Str "of",Space,Str "various",Space,Str "sorts"]
  ,OrderedList (1,LowerAlpha,Period)
   [[Para [Str "sweet",Space,Str "potatoes"]]
   ,[Para [Str "russet",Space,Str "potates"]]]]
 ,[Para [Str "Tornadoes,",Space,Str "for",Space,Str "the",Space,Str "rhyme."]]]]
NATIVE;

$upstreamListLevelNative = <<<'NATIVE'
[Header 1 ("slide",[],[]) [Str "Slide"]
,BulletList
 [[Para [Str "Top-level"]
  ,Para [Str "With",Space,Str "continuation",Space,Str "paragraph"]]
 ,[Para [Str "Then:"]
  ,BulletList
   [[Plain [Str "nested"]]
   ,[Plain [Str "list"]]
   ,[Plain [Str "items"]]]]]
,Header 1 ("slide-1",[],[]) [Str "Slide"]
,Para [Str "Paragraph."]
,OrderedList (1,Decimal,Period)
 [[Para [Str "Top-level"]
  ,Para [Str "Continuation"]
  ,OrderedList (1,Decimal,Period)
   [[Para [Str "Sub-list"]
    ,Para [Str "With",Space,Str "Continuation"]]
   ,[Para [Str "(still",Space,Str "sub-list)"]]]]
 ,[Para [Str "(back",Space,Str "to",Space,Str "top-level)"]]]
,Para [Str "Paragraph."]]
NATIVE;

$upstreamSlideBreaksNative = <<<'NATIVE'
Pandoc (Meta {unMeta = fromList []})
[Para [Str "Break",Space,Str "with",Space,Str "a",Space,Str "new",Space,Str "section-level",Space,Str "header"]
,Header 1 ("below-section-level",[],[]) [Str "Below",Space,Str "section-level"]
,Header 2 ("section-level",[],[]) [Str "Section-level"]
,Para [Str "Third",Space,Str "slide",Space,Str "(with",Space,Str "a",Space,Str "section-level",Space,Str "of",Space,Str "2)"]
,HorizontalRule
,Para [Str "This",Space,Str "is",Space,Str "another",Space,Str "slide."]]
NATIVE;

$upstreamDocumentPropertiesNative = <<<'NATIVE'
Pandoc (Meta {unMeta = fromList [("Company",MetaInlines [Str "My",Space,Str "Company"]),("Second Custom Property",MetaInlines [Str "Second",Space,Str "custom",Space,Str "property",Space,Str "value"]),("abstract",MetaBlocks [Plain [Str "Quite",Space,Str "a",Space,Str "long",Space,Str "description",SoftBreak,Str "spanning",Space,Str "several",Space,Str "lines"]]),("author",MetaList [MetaInlines [Str "A.",Space,Str "M."]]),("category",MetaInlines [Str "My",Space,Str "Category"]),("custom1",MetaInlines [Str "First",Space,Str "custom",Space,Str "property",Space,Str "value"]),("custom3",MetaInlines [Str "Escaping",Space,Str "amp",Space,Str "&",Space,Str "."]),("custom4",MetaInlines [Str "Escaping",Space,Str "LT,GT",Space,Str "<",Space,Str "asdf",Space,Str ">",Space,Str "<"]),("custom5",MetaInlines [Str "Escaping",Space,Str "html",Space,RawInline (Format "html") "<i>",Str "asdf",RawInline (Format "html") "</i>"]),("custom6",MetaInlines [Str "Escaping",Space,Emph [Str "MD"],Space,Str "\225",Space,Str "a"]),("custom9",MetaInlines [Str "Extended",Space,Str "chars:",Space,Str "\8364",Space,Str "\225",Space,Str "\233",Space,Str "\237",Space,Str "\243",Space,Str "\250",Space,Str "$"]),("description",MetaBlocks [Para [Str "Long",Space,Str "description",Space,Str "spanning",SoftBreak,Str "several",Space,Str "lines."],Plain [Str "This",Space,Str "is",Space,Str "\225",Space,Str "second",Space,RawInline (Format "html") "<i>",Str "line",RawInline (Format "html") "</i>",Str "."]]),("keywords",MetaList [MetaInlines [Str "keyword",Space,Str "1"],MetaInlines [Str "keyword",Space,Str "2"]]),("lang",MetaInlines [Str "en-US"]),("nested-custom",MetaList [MetaMap (fromList [("custom 7",MetaInlines [Str "Nested",Space,Str "Custom",Space,Str "value",Space,Str "7"])]),MetaMap (fromList [("custom 8",MetaInlines [Str "Nested",Space,Str "Custom",Space,Str "value",Space,Str "8"])])]),("subject",MetaInlines [Str "This",Space,Str "is",Space,Str "the",Space,Str "subject"]),("subtitle",MetaInlines [Str "This",Space,Str "is",Space,Str "a",Space,Str "subtitle"]),("title",MetaInlines [Str "Testing",Space,Str "custom",Space,Str "properties"])]})
[Para [Str "Testing",Space,Str "document",Space,Str "properties"]]
NATIVE;

$upstreamDocumentPropertiesShortDescNative = <<<'NATIVE'
Pandoc (Meta {unMeta = fromList [("author",MetaList [MetaInlines [Str "A.",Space,Str "M."]]),("description",MetaInlines [Str "Short",Space,RawInline (Format "html") "<i>",Str "description",RawInline (Format "html") "</i>",Space,Str "&."]),("keywords",MetaList [MetaInlines [Str "keyword",Space,Str "1"],MetaInlines [Str "keyword",Space,Str "2"]]),("subject",MetaInlines [Str "This",Space,Str "is",Space,Str "the",Space,Str "subject"]),("title",MetaInlines [Str "Testing",Space,Str "custom",Space,Str "properties"])]})
[Para [Str "Testing",Space,Str "document",Space,Str "properties"]]
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

$upstreamBlankJustSpeakerNotesNative = <<<'NATIVE'
[Header 1 ("first-slide",[],[]) [Str "First",Space,Str "slide"]
,Para [Str "Nothing",Space,Str "to",Space,Str "see",Space,Str "here"]
,Header 1 ("section",[],[]) []
,Div ("",["notes"],[])
 [Para [Str "Some",Space,Str "notes",Space,Str "here:",Space,Str "this",Space,Str "first",Space,Str "slide",Space,Str "should",Space,Str "use",Space,Str "the",Space,Str "Blank",Space,Str "template"]]
,Header 1 ("third-slide",[],[]) [Str "Third",Space,Str "slide"]
,Para [Str "The",Space,Str "second",Space,Str "slide",Space,Str "should",Space,Str "be",Space,Str "blank"]]
NATIVE;

$upstreamBlankNbspBodyNative = <<<'NATIVE'
[Header 1 ("first-slide",[],[]) [Str "First",Space,Str "slide"]
,Para [Str "Uninteresting,",Space,Str "normal"]
,Header 1 ("section",[],[]) []
,Para [Str "\160"]
,Header 1 ("third-slide",[],[]) [Str "Third",Space,Str "slide"]
,Para [Str "Was",Space,Str "the",Space,Str "previous",Space,Str "one",Space,Str "blank?"]]
NATIVE;

$upstreamBlankNbspHeadingNative = <<<'NATIVE'
[Header 1 ("first-slide",[],[]) [Str "First",Space,Str "slide"]
,Para [Str "Uninteresting,",Space,Str "normal"]
,Header 1 ("section",[],[]) [Str "\160"]
,Header 1 ("third-slide",[],[]) [Str "Third",Space,Str "slide"]
,Para [Str "Was",Space,Str "the",Space,Str "previous",Space,Str "one",Space,Str "blank?"]]
NATIVE;

$upstreamImagesNative = <<<'NATIVE'
Pandoc (Meta {unMeta = fromList []})
[Para [Image ("",[],[]) [] ("lalune.jpg","")]
,Para [Image ("",[],[]) [Str "The",Space,Str "Moon"] ("lalune.jpg","fig:")]
,Header 1 ("one-more",[],[]) [Str "One",Space,Str "More"]
,Para [Image ("",[],[]) [Str "The",Space,Str "Moon"] ("lalune.jpg","fig:")]]
NATIVE;

$upstreamContentWithCaptionTextImageNative = <<<'NATIVE'
[Para [Str "Some",Space,Str "text",Space,Str "here"]
,Para [Image ("",[],[]) [Str "Followed",Space,Str "by",Space,Str "a",Space,Str "picture"] ("lalune.jpg","fig:")]]
NATIVE;

$upstreamContentWithCaptionImageTextNative = <<<'NATIVE'
[Para [Image ("",[],[]) [Str "The",Space,Str "picture",Space,Str "first"] ("lalune.jpg","fig:")]
,Para [Str "Then",Space,Str "some",Space,Str "text",Space,Str "here"]]
NATIVE;

$upstreamContentWithCaptionHeadingTextImageNative = <<<'NATIVE'
[Header 1 ("a-slide",[],[]) [Str "A",Space,Str "slide"]
,Para [Str "Some",Space,Str "text",Space,Str "here"]
,Para [Image ("",[],[]) [Str "Followed",Space,Str "by",Space,Str "a",Space,Str "picture"] ("lalune.jpg","fig:")]]
NATIVE;

$upstreamTwoColumnAllTextNative = <<<'NATIVE'
Pandoc (Meta {unMeta = fromList []})
[Header 1 ("two-column-layout",[],[]) [Str "Two-Column",Space,Str "Layout"]
,Div ("",["columns"],[])
 [Div ("",["column"],[])
  [Para [Str "One",Space,Str "paragraph."]
  ,Para [Str "Another",Space,Str "paragraph."]]
 ,Div ("",["column"],[])
  [Para [Str "Second",Space,Str "column",Space,Str "paragraph."]
  ,Para [Str "Another",Space,Str "second",Space,Str "paragraph."]]]]
NATIVE;

$upstreamTwoColumnTextImageNative = <<<'NATIVE'
[Header 1 ("slide-1",[],[]) [Str "Slide",Space,Str "1"]
,Div ("",["columns"],[])
 [Div ("",["column"],[])
  [Para [Image ("",[],[]) [Str "an",Space,Str "image"] ("lalune.jpg","fig:")]]
 ,Div ("",["column"],[])
  [Para [Str "This",Space,Str "should",Space,Str "use",Space,Str "Two",Space,Str "Content,",Emph [Str "not"],Space,Str "Comparison!"]]]
,Header 1 ("slide-2",[],[]) [Str "Slide",Space,Str "2"]
,Div ("",["columns"],[])
 [Div ("",["column"],[])
  [Para [Str "This",Space,Str "should",Space,Str "also",Space,Str "use",Space,Str "Two",Space,Str "Content"]]
 ,Div ("",["column"],[])
  [Para [Image ("",[],[]) [Str "an",Space,Str "image"] ("lalune.jpg","fig:")]]]]
NATIVE;

$upstreamSingleColumnTextNative = <<<'NATIVE'
[Header 1 ("single-column",[],[]) [Str "Single",Space,Str "column"]
,Div ("",["columns"],[])
 [Div ("",["column"],[])
  [Para [Str "One",Space,Str "paragraph."]
  ,Para [Str "Another",Space,Str "paragraph."]]]]
NATIVE;

$upstreamSingleColumnImageNative = <<<'NATIVE'
[Header 1 ("single-column",[],[]) [Str "Single",Space,Str "column"]
,Div ("",["columns"],[])
 [Div ("",["column"],[])
  [Figure ("",[],[]) (Caption Nothing [Plain [Str "an",Space,Str "image"]])
   [Plain [Image ("",[],[]) [Str "an",Space,Str "image"] ("lalune.jpg","")]]]]]
NATIVE;

$upstreamComparisonBothColumnsNative = <<<'NATIVE'
[Header 1 ("a-slide",[],[]) [Str "A",Space,Str "slide"]
,Div ("",["columns"],[])
 [Div ("",["column"],[])
  [Para [Str "A",Space,Str "paragraph",Space,Str "here"]
  ,Table ("",[],[]) (Caption Nothing
   [])
   [(AlignDefault,ColWidth 0.125)
   ,(AlignDefault,ColWidth 0.125)]
   (TableHead ("",[],[])
   [])
   [(TableBody ("",[],[]) (RowHeadColumns 0)
    []
    [Row ("",[],[])
     [Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
      [Plain [Str "plus"]]
     ,Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
      [Plain [Str "a",Space,Str "table"]]]])]
   (TableFoot ("",[],[])
   [])
  ,Para [Str "Then",Space,Str "some",Space,Str "more",Space,Str "text"]]
 ,Div ("",["column"],[])
  [Para [Str "A",Space,Str "paragraph",Space,Str "here"]
  ,Para [Image ("",[],[]) [Str "Plus",Space,Str "an",Space,Str "image"] ("lalune.jpg","fig:")]]]]
NATIVE;

$upstreamComparisonExtraImageNative = <<<'NATIVE'
[Header 1 ("a-slide",[],[]) [Str "A",Space,Str "slide"]
,Div ("",["columns"],[])
 [Div ("",["column"],[])
  [Para [Str "A",Space,Str "paragraph",Space,Str "here"]
  ,Table ("",[],[]) (Caption Nothing
   [])
   [(AlignDefault,ColWidth 0.125)
   ,(AlignDefault,ColWidth 0.125)]
   (TableHead ("",[],[])
   [])
   [(TableBody ("",[],[]) (RowHeadColumns 0)
    []
    [Row ("",[],[])
     [Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
      [Plain [Str "plus"]]
     ,Cell ("",[],[]) AlignDefault (RowSpan 1) (ColSpan 1)
      [Plain [Str "a",Space,Str "table"]]]])]
   (TableFoot ("",[],[])
   [])
  ,Para [Str "Then",Space,Str "some",Space,Str "more",Space,Str "text"]]
 ,Div ("",["column"],[])
  [Para [Str "A",Space,Str "paragraph",Space,Str "here"]
  ,Para [Image ("",[],[]) [Str "Plus",Space,Str "an",Space,Str "image"] ("lalune.jpg","fig:")]
  ,Para [Image ("",[],[]) [Str "And",Space,Str "another",Space,Str "image"] ("lalune.jpg","fig:")]]]]
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
            'docProps/custom.xml',
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
        $t->contains('officedocument.custom-properties+xml', $contentTypes);
        $t->contains('Extension="png" ContentType="image/png"', $contentTypes);

        $rootRelationships = $package->read('_rels/.rels');
        $t->contains('relationships/custom-properties', $rootRelationships);
        $t->contains('Target="docProps/custom.xml"', $rootRelationships);

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

    'maps leading speaker notes after metadata onto a metadata title slide' => static function (TestRunner $t) use ($upstreamSpeakerNotesAfterMetadataNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamSpeakerNotesAfterMetadataNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));
        $names = $package->names();

        $t->true(in_array('ppt/slides/slide1.xml', $names, true), 'Expected metadata title slide');
        $t->true(in_array('ppt/slides/slide2.xml', $names, true), 'Expected content slide after metadata notes');
        $t->true(!in_array('ppt/slides/slide3.xml', $names, true), 'Fixture should produce exactly two slides');
        $t->true(in_array('ppt/notesSlides/notesSlide1.xml', $names, true), 'Expected notes part for metadata title slide');
        $t->true(!in_array('ppt/notesSlides/notesSlide2.xml', $names, true), 'Only the metadata title slide should have speaker notes');

        $slide1 = $package->read('ppt/slides/slide1.xml');
        $t->contains('Testing', $slide1);
        $t->contains('Jesse Rosenthal', $slide1);
        $t->contains('type="subTitle"', $slide1);

        $slide2 = $package->read('ppt/slides/slide2.xml');
        $t->contains('A header', $slide2);
        $t->contains('And a new slide.', $slide2);

        $slide1Rels = $package->read('ppt/slides/_rels/slide1.xml.rels');
        $slide2Rels = $package->read('ppt/slides/_rels/slide2.xml.rels');
        $t->contains('relationships/notesSlide', $slide1Rels);
        $t->contains('../notesSlides/notesSlide1.xml', $slide1Rels);
        $t->true(!str_contains($slide2Rels, 'relationships/notesSlide'), 'Content slide should not point at the title-slide notes');

        $notes1 = $package->read('ppt/notesSlides/notesSlide1.xml');
        $t->contains('Some speaker notes', $notes1);
        $t->contains('<Slides>2</Slides>', $package->read('docProps/app.xml'));
        $t->contains('<Notes>1</Notes>', $package->read('docProps/app.xml'));
    },

    'maps upstream speaker notes after separators including nested column notes' => static function (TestRunner $t) use ($upstreamSpeakerNotesAfterSepsNative, $mediaOptions): void {
        $imageOptions = $mediaOptions;
        $imageOptions['mediaResources']['lalune.jpg'] = [
            'data' => "\xff\xd8fake-jpeg",
            'mimeType' => 'image/jpeg',
        ];

        $document = (new NativeReader())->read($upstreamSpeakerNotesAfterSepsNative);
        $package = ZipPackage::fromString((new PptxWriter($imageOptions))->write($document));
        $names = $package->names();

        $t->true(in_array('ppt/slides/slide1.xml', $names, true), 'Expected image-only slide');
        $t->true(in_array('ppt/slides/slide2.xml', $names, true), 'Expected table and columns slide');
        $t->true(!in_array('ppt/slides/slide3.xml', $names, true), 'Speaker-notes-afterseps fixture should produce exactly two slides');
        $t->true(in_array('ppt/notesSlides/notesSlide1.xml', $names, true), 'Expected notes part for image slide');
        $t->true(in_array('ppt/notesSlides/notesSlide2.xml', $names, true), 'Expected notes part for table/columns slide');
        $t->true(!in_array('ppt/notesSlides/notesSlide3.xml', $names, true), 'Only two slides should have speaker notes');

        $slide1Rels = $package->read('ppt/slides/_rels/slide1.xml.rels');
        $slide2Rels = $package->read('ppt/slides/_rels/slide2.xml.rels');
        $t->contains('../notesSlides/notesSlide1.xml', $slide1Rels);
        $t->contains('../notesSlides/notesSlide2.xml', $slide2Rels);

        $slide2 = $package->read('ppt/slides/slide2.xml');
        $t->contains('Right', $slide2);
        $t->contains('some stuff', $slide2);
        $t->contains('Some other', $slide2);
        $t->true(!str_contains($slide2, 'Some notes inside a column'), 'Nested column speaker notes must not render as slide body text');
        $t->true(!str_contains($slide2, 'Some notes outside the column'), 'Trailing speaker notes must not render as slide body text');

        $notes1 = $package->read('ppt/notesSlides/notesSlide1.xml');
        $t->contains('chicken', $notes1);
        $t->contains('dumplings', $notes1);

        $notes2 = $package->read('ppt/notesSlides/notesSlide2.xml');
        $t->contains('foo bar', $notes2);
        $t->contains('Some notes inside a column', $notes2);
        $t->contains('Some notes outside the column', $notes2);

        $app = $package->read('docProps/app.xml');
        $t->contains('<Slides>2</Slides>', $app);
        $t->contains('<Notes>2</Notes>', $app);
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

    'maps upstream inline formatting fixture into run properties' => static function (TestRunner $t) use ($upstreamInlineFormattingNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamInlineFormattingNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));
        $slide = $package->read('ppt/slides/slide1.xml');
        $slideText = trim(preg_replace('/\s+/u', ' ', strip_tags($slide)) ?? '');

        $t->contains('Here are examples of italics, bold, and bold italics.', $slideText);
        $t->contains('Here is strook-three strike-through and small caps.', $slideText);
        $t->contains('We can also do subscripts (H20) and superscript.', $slideText);
        $t->contains('i="1"', $slide);
        $t->contains('b="1"', $slide);
        $t->contains('b="1" i="1"', $slide);
        $t->contains('strike="sngStrike"', $slide);
        $t->contains('cap="small"', $slide);
        $t->same(1, substr_count($slide, 'cap="small"'));
        $t->contains('baseline="-25000"', $slide);
        $t->contains('baseline="30000"', $slide);
        $t->true(!str_contains($slide, "Comments don't show up."), 'Raw HTML comments must not render as slide text');
        $t->contains('<Slides>1</Slides>', $package->read('docProps/app.xml'));
    },

    'maps upstream line blocks and hard line breaks to DrawingML breaks' => static function (TestRunner $t) use ($upstreamLineBlocksNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamLineBlocksNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));
        $names = $package->names();

        $t->true(in_array('ppt/slides/slide1.xml', $names, true), 'Expected line-block slide');
        $t->true(!in_array('ppt/slides/slide2.xml', $names, true), 'Line-block fixture should produce exactly one visible slide');
        $t->true(in_array('ppt/notesSlides/notesSlide1.xml', $names, true), 'Expected notes part for line-block speaker notes');

        $slide = $package->read('ppt/slides/slide1.xml');
        $t->contains('<a:t>Line blocks</a:t>', $slide);
        $t->contains('<a:t>Alpha</a:t></a:r><a:br/><a:r><a:rPr lang="en-US" i="1"/><a:t>Beta</a:t></a:r><a:br/><a:r><a:rPr lang="en-US"/><a:t>Gamma</a:t>', $slide);
        $t->contains('<a:t>Hard</a:t></a:r><a:br/><a:r><a:rPr lang="en-US"/><a:t>break</a:t>', $slide);
        $t->same(3, substr_count($slide, '<a:br/>'));
        $t->true(!str_contains($slide, '<a:t>AlphaBetaGamma</a:t>'), 'LineBlock lines must not be collapsed into one text run');

        $notes = $package->read('ppt/notesSlides/notesSlide1.xml');
        $t->contains('<a:t>Note one</a:t></a:r><a:br/><a:r><a:rPr lang="en-US"/><a:t>Note two</a:t>', $notes);
        $t->same(1, substr_count($notes, '<a:br/>'));
        $t->contains('<Notes>1</Notes>', $package->read('docProps/app.xml'));
    },

    'maps upstream quoted inlines to curly quote runs' => static function (TestRunner $t) use ($upstreamQuotedInlineNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamQuotedInlineNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));
        $slide = $package->read('ppt/slides/slide1.xml');
        $notes = $package->read('ppt/notesSlides/notesSlide1.xml');
        $slideText = trim(preg_replace('/\s+/u', ' ', strip_tags($slide)) ?? '');
        $notesText = trim(preg_replace('/\s+/u', ' ', strip_tags($notes)) ?? '');

        $t->contains("He said \u{201C}hello there\u{201D}. \u{2018}yes\u{2019}", $slideText);
        $t->contains("\u{201C}hello ", $slide);
        $t->contains("<a:t>\u{201D}</a:t>", $slide);
        $t->contains("<a:t>\u{2018}</a:t>", $slide);
        $t->contains("<a:t>\u{2019}</a:t>", $slide);
        $t->contains('i="1"', $slide);
        $t->contains('b="1"', $slide);
        $t->true(!str_contains($slide, 'DoubleQuote'), 'Quoted constructor names must not leak into slide XML');
        $t->true(!str_contains($slide, 'SingleQuote'), 'Quoted constructor names must not leak into slide XML');

        $t->contains("Speaker \u{2018}aside\u{2019}", $notesText);
        $t->contains('<Notes>1</Notes>', $package->read('docProps/app.xml'));
    },

    'maps upstream citation display inlines without citation metadata leakage' => static function (TestRunner $t) use ($upstreamCitationInlineNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamCitationInlineNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));
        $slide = $package->read('ppt/slides/slide1.xml');
        $slideRelationships = $package->read('ppt/slides/_rels/slide1.xml.rels');
        $notes = $package->read('ppt/notesSlides/notesSlide1.xml');
        $slideText = trim(preg_replace('/\s+/u', ' ', strip_tags($slide)) ?? '');
        $notesText = trim(preg_replace('/\s+/u', ' ', strip_tags($notes)) ?? '');

        $t->contains('Cited (Doe 2026)', $slideText);
        $t->contains('i="1"', $slide);
        $t->contains('<a:hlinkClick r:id="rIdHyperlink1"/>', $slide);
        $t->contains('relationships/hyperlink', $slideRelationships);
        $t->contains('Target="https://example.test/ref"', $slideRelationships);
        $t->contains('TargetMode="External"', $slideRelationships);
        $t->true(!str_contains($slide, 'doe2026'), 'Citation IDs must not leak into slide text or XML');
        $t->true(!str_contains($slide, 'Citation'), 'Citation constructor names must not leak into slide XML');

        $t->contains('Note note cite', $notesText);
        $t->contains('b="1"', $notes);
        $t->true(!str_contains($notes, 'note2026'), 'Speaker-note citation IDs must not leak into notes XML');
        $t->true(!str_contains($notes, 'Citation'), 'Speaker-note citation constructors must not leak into notes XML');
        $t->contains('<Notes>1</Notes>', $package->read('docProps/app.xml'));
    },

    'maps upstream inline image alt inlines when rendered as text' => static function (TestRunner $t) use ($upstreamInlineImageAltNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamInlineImageAltNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));
        $names = $package->names();
        $slide = $package->read('ppt/slides/slide1.xml');
        $slideRelationships = $package->read('ppt/slides/_rels/slide1.xml.rels');
        $notes = $package->read('ppt/notesSlides/notesSlide1.xml');
        $slideText = trim(preg_replace('/\s+/u', ' ', strip_tags($slide)) ?? '');
        $notesText = trim(preg_replace('/\s+/u', ' ', strip_tags($notes)) ?? '');
        $mediaPartNames = array_values(array_filter($names, static fn (string $name): bool => str_starts_with($name, 'ppt/media/')));

        $t->contains('Inline figure alt text', $slideText);
        $t->contains('Nested image alt', $slideText);
        $t->contains('i="1"', $slide);
        $t->contains('b="1"', $slide);
        $t->contains('b="1" i="1"', $slide);
        $t->same([], $mediaPartNames);
        $t->true(!str_contains($slideRelationships, 'relationships/image'), 'Alt-text fallback should not create slide image relationships');
        $t->true(!str_contains($slide, 'missing-inline.png'), 'Missing image target must not leak into slide XML');
        $t->true(!str_contains($slide, 'nested-missing.png'), 'Nested missing image target must not leak into slide XML');

        $t->contains('Note note alt', $notesText);
        $t->contains('b="1"', $notes);
        $t->true(!str_contains($notes, 'missing-note.png'), 'Missing note image target must not leak into notes XML');
        $t->contains('<Notes>1</Notes>', $package->read('docProps/app.xml'));
    },

    'maps upstream math inlines to bounded PowerPoint OMML with TeX fallbacks' => static function (TestRunner $t) use ($upstreamMathInlineNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamMathInlineNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));
        $slide = $package->read('ppt/slides/slide1.xml');
        $notes = $package->read('ppt/notesSlides/notesSlide1.xml');

        $slideDom = new DOMDocument();
        $t->true($slideDom->loadXML($slide, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING), 'Slide XML with math alternate content must remain well-formed');

        $t->contains('<mc:AlternateContent xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006">', $slide);
        $t->contains('<mc:Choice xmlns:a14="http://schemas.microsoft.com/office/drawing/2010/main" Requires="a14">', $slide);
        $t->contains('<a14:m><m:oMath xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">', $slide);
        $t->contains('<m:sSup><m:e><m:r><m:t>x</m:t></m:r></m:e><m:sup><m:r><m:t>2</m:t></m:r></m:sup></m:sSup>', $slide);
        $t->contains('<m:f><m:num><m:sSub><m:e><m:r><m:t>a</m:t></m:r></m:e><m:sub><m:r><m:t>1</m:t></m:r></m:sub></m:sSub></m:num><m:den><m:r><m:t>b</m:t></m:r></m:den></m:f>', $slide);
        $t->contains('<m:rad><m:radPr><m:degHide m:val="1"/></m:radPr><m:e><m:sSup><m:e><m:r><m:t>b</m:t></m:r></m:e><m:sup><m:r><m:t>2</m:t></m:r></m:sup></m:sSup></m:e></m:rad>', $slide);
        $t->contains('<a:t>\\frac{a}{</a:t>', $slide);
        $t->true(!str_contains($slide, '<annotation'), 'MathML annotations should not leak into slide XML');

        $t->contains('<a:t>\\frac{n}{k}</a:t>', $notes);
        $t->true(!str_contains($notes, '<a14:m>'), 'Speaker-note math should fall back to plain TeX like upstream');
        $t->contains('<Notes>1</Notes>', $package->read('docProps/app.xml'));
    },

    'maps upstream code fixture into monospace runs' => static function (TestRunner $t) use ($upstreamCodeNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamCodeNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));
        $names = $package->names();

        foreach (['ppt/slides/slide1.xml', 'ppt/slides/slide2.xml', 'ppt/slides/slide3.xml'] as $partName) {
            $t->true(in_array($partName, $names, true), "Missing code fixture part {$partName}");
        }

        $slide1 = $package->read('ppt/slides/slide1.xml');
        $slide2 = $package->read('ppt/slides/slide2.xml');
        $slide3 = $package->read('ppt/slides/slide3.xml');
        $t->contains('<a:t>Header with </a:t>', $slide1);
        $t->contains('<a:latin typeface="Courier"/></a:rPr><a:t>inline code</a:t>', $slide1);
        $t->contains('<a:t>Code at level 0</a:t>', $slide1);
        $t->contains('<a:t>Bullet item with </a:t>', $slide1);
        $t->contains('<a:t>Code block at level 1</a:t>', $slide1);
        $t->contains('<a:t>nested</a:t>', $slide1);
        $t->contains("lvl2\nlvl2\nlvl2", $slide1);
        $t->true(substr_count($slide1, 'typeface="Courier"') >= 6, 'Expected Courier runs for title, code blocks, and list inline code');
        $t->contains('id :: a -&gt; a', $slide2);
        $t->contains('g :: Int -&gt; Int', $slide2);
        $t->true(substr_count($slide2, 'typeface="Courier"') >= 2, 'Expected Courier runs for syntax-highlight code fixture content');
        $t->contains('<a:t>A total alternative for </a:t>', $slide3);
        $t->contains('<a:latin typeface="Courier"/></a:rPr><a:t>head</a:t>', $slide3);
        $t->contains('safeHead :: [a] -&gt; Maybe a', $slide3);
        $t->contains('<Slides>3</Slides>', $package->read('docProps/app.xml'));
    },

    'maps upstream example numbering list starts into pptx autonumbering' => static function (TestRunner $t) use ($upstreamStartNumberingAtNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamStartNumberingAtNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));
        $names = $package->names();

        $t->true(in_array('ppt/slides/slide1.xml', $names, true), 'Expected first example-numbering slide');
        $t->true(in_array('ppt/slides/slide2.xml', $names, true), 'Expected second example-numbering slide');
        $t->true(!in_array('ppt/slides/slide3.xml', $names, true), 'Example numbering fixture should produce exactly two slides');

        $slide1 = $package->read('ppt/slides/slide1.xml');
        $t->contains('<a:t>Example numbering MWE</a:t>', $slide1);
        $t->contains('<a:t>This is a slide with examples in (1) and (2)</a:t>', $slide1);
        $t->same(2, substr_count($slide1, '<a:buAutoNum type="arabicParenBoth"/>'));
        $t->true(!str_contains($slide1, 'arabicPeriod'), 'Example lists must not render as period-delimited decimal lists');
        $t->true(!str_contains($slide1, 'startAt="1"'), 'Default example list start should not be forced into slide XML');
        $t->true(!str_contains($slide1, 'startAt="2"'), 'Continuation example list item should rely on auto-increment');

        $slide2 = $package->read('ppt/slides/slide2.xml');
        $t->contains('<a:t>A second slide</a:t>', $slide2);
        $t->contains('<a:t>This second slide has a third example in (3).</a:t>', $slide2);
        $t->contains('<a:buAutoNum type="arabicParenBoth" startAt="3"/>', $slide2);
        $t->true(!str_contains($slide2, 'arabicPeriod'), 'Started example list must keep two-parentheses numbering');
        $t->contains('<Slides>2</Slides>', $package->read('docProps/app.xml'));
    },

    'maps upstream list fixture nested ordered list margins' => static function (TestRunner $t) use ($upstreamListsNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamListsNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));
        $names = $package->names();

        $t->true(in_array('ppt/slides/slide1.xml', $names, true), 'Expected first lists slide');
        $t->true(in_array('ppt/slides/slide2.xml', $names, true), 'Expected second lists slide');
        $t->true(!in_array('ppt/slides/slide3.xml', $names, true), 'Lists fixture should produce exactly two slides');

        $slide1 = $package->read('ppt/slides/slide1.xml');
        $t->contains('<a:t>Bulleted bulleted lists.</a:t>', $slide1);
        $t->contains('<a:pPr lvl="1"><a:buChar char="&#8226;"/></a:pPr><a:r><a:rPr lang="en-US"/><a:t>Like this</a:t></a:r>', $slide1);
        $t->contains('<a:pPr lvl="2"><a:buChar char="&#8226;"/></a:pPr><a:r><a:rPr lang="en-US"/><a:t>Or this</a:t></a:r>', $slide1);

        $slide2 = $package->read('ppt/slides/slide2.xml');
        $t->contains('<a:t>Lists can also be numbered:</a:t>', $slide2);
        $t->same(3, substr_count($slide2, '<a:pPr lvl="0" indent="-342900" marL="342900"><a:buAutoNum type="arabicPeriod"/></a:pPr>'));
        $t->same(2, substr_count($slide2, '<a:pPr lvl="1" indent="-342900" marL="685800"><a:buAutoNum type="alphaLcPeriod"/></a:pPr>'));
        $t->true(!str_contains($slide2, 'startAt="1"'), 'Default ordered-list starts should not be forced into list XML');
        $t->contains('<Slides>2</Slides>', $package->read('docProps/app.xml'));
    },

    'maps upstream list-level fixture continuation paragraphs' => static function (TestRunner $t) use ($upstreamListLevelNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamListLevelNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));
        $names = $package->names();

        $t->true(in_array('ppt/slides/slide1.xml', $names, true), 'Expected first list-level slide');
        $t->true(in_array('ppt/slides/slide2.xml', $names, true), 'Expected second list-level slide');
        $t->true(!in_array('ppt/slides/slide3.xml', $names, true), 'List-level fixture should produce exactly two slides');

        $slide1 = $package->read('ppt/slides/slide1.xml');
        $t->contains('<a:t>Top-level</a:t>', $slide1);
        $t->contains('<a:pPr lvl="1" indent="0" marL="342900"><a:buNone/></a:pPr><a:r><a:rPr lang="en-US"/><a:t>With continuation paragraph</a:t></a:r>', $slide1);
        $t->contains('<a:pPr lvl="1"><a:buChar char="&#8226;"/></a:pPr><a:r><a:rPr lang="en-US"/><a:t>nested</a:t></a:r>', $slide1);

        $slide2 = $package->read('ppt/slides/slide2.xml');
        $t->contains('<a:pPr lvl="0" indent="-342900" marL="342900"><a:buAutoNum type="arabicPeriod"/></a:pPr><a:r><a:rPr lang="en-US"/><a:t>Top-level</a:t></a:r>', $slide2);
        $t->contains('<a:pPr lvl="1" indent="0" marL="342900"><a:buNone/></a:pPr><a:r><a:rPr lang="en-US"/><a:t>Continuation</a:t></a:r>', $slide2);
        $t->contains('<a:pPr lvl="1" indent="-342900" marL="685800"><a:buAutoNum type="arabicPeriod"/></a:pPr><a:r><a:rPr lang="en-US"/><a:t>Sub-list</a:t></a:r>', $slide2);
        $t->contains('<a:pPr lvl="2" indent="0" marL="685800"><a:buNone/></a:pPr><a:r><a:rPr lang="en-US"/><a:t>With Continuation</a:t></a:r>', $slide2);
        $t->true(!str_contains($slide2, 'startAt="1"'), 'Default nested ordered-list starts should remain implicit');
        $t->contains('<Slides>2</Slides>', $package->read('docProps/app.xml'));
    },

    'maps upstream slide-breaks fixture slide levels and toc' => static function (TestRunner $t) use ($upstreamSlideBreaksNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamSlideBreaksNative);

        $defaultPackage = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));
        $defaultNames = $defaultPackage->names();
        foreach (['ppt/slides/slide1.xml', 'ppt/slides/slide2.xml', 'ppt/slides/slide3.xml', 'ppt/slides/slide4.xml'] as $partName) {
            $t->true(in_array($partName, $defaultNames, true), "Default slide-breaks fixture missing {$partName}");
        }
        $t->true(!in_array('ppt/slides/slide5.xml', $defaultNames, true), 'Default slide-breaks fixture should produce exactly four slides');

        $defaultSlide1 = $defaultPackage->read('ppt/slides/slide1.xml');
        $defaultSlide2 = $defaultPackage->read('ppt/slides/slide2.xml');
        $defaultSlide3 = $defaultPackage->read('ppt/slides/slide3.xml');
        $defaultSlide4 = $defaultPackage->read('ppt/slides/slide4.xml');
        $t->contains('<a:t>Break with a new section-level header</a:t>', $defaultSlide1);
        $t->true(!str_contains($defaultSlide1, 'type="title"'), 'Pre-heading content slide should not use first heading as a title');
        $t->true(!str_contains($defaultSlide1, '<a:t>Below section-level</a:t>'), 'Pre-heading slide must not duplicate the first heading');
        $t->contains('<a:t>Below section-level</a:t>', $defaultSlide2);
        $t->contains('<a:t>Section-level</a:t>', $defaultSlide3);
        $t->contains('<a:t>Third slide (with a section-level of 2)</a:t>', $defaultSlide3);
        $t->contains('<a:t>This is another slide.</a:t>', $defaultSlide4);
        $t->contains('<Slides>4</Slides>', $defaultPackage->read('docProps/app.xml'));

        $slideLevelOnePackage = ZipPackage::fromString((new PptxWriter($mediaOptions + ['writerSlideLevel' => 1]))->write($document));
        $slideLevelOneNames = $slideLevelOnePackage->names();
        foreach (['ppt/slides/slide1.xml', 'ppt/slides/slide2.xml', 'ppt/slides/slide3.xml'] as $partName) {
            $t->true(in_array($partName, $slideLevelOneNames, true), "Slide-level-1 fixture missing {$partName}");
        }
        $t->true(!in_array('ppt/slides/slide4.xml', $slideLevelOneNames, true), 'Slide-level-1 fixture should produce exactly three slides');
        $slideLevelOneSlide2 = $slideLevelOnePackage->read('ppt/slides/slide2.xml');
        $t->contains('<a:t>Below section-level</a:t>', $slideLevelOneSlide2);
        $t->contains('<a:t>Section-level</a:t>', $slideLevelOneSlide2);
        $t->contains('<a:t>Third slide (with a section-level of 2)</a:t>', $slideLevelOneSlide2);
        $t->contains('<Slides>3</Slides>', $slideLevelOnePackage->read('docProps/app.xml'));

        $tocPackage = ZipPackage::fromString((new PptxWriter($mediaOptions + ['writerTableOfContents' => true]))->write($document));
        $tocNames = $tocPackage->names();
        foreach (['ppt/slides/slide1.xml', 'ppt/slides/slide2.xml', 'ppt/slides/slide3.xml', 'ppt/slides/slide4.xml', 'ppt/slides/slide5.xml'] as $partName) {
            $t->true(in_array($partName, $tocNames, true), "TOC slide-breaks fixture missing {$partName}");
        }
        $t->true(!in_array('ppt/slides/slide6.xml', $tocNames, true), 'TOC slide-breaks fixture should produce exactly five slides');
        $tocSlide = $tocPackage->read('ppt/slides/slide1.xml');
        $t->contains('<a:t>Table of Contents</a:t>', $tocSlide);
        $t->contains('<a:pPr lvl="0"/>', $tocSlide);
        $t->contains('<a:t>Below section-level</a:t>', $tocSlide);
        $t->contains('<a:pPr lvl="1"/>', $tocSlide);
        $t->contains('<a:t>Section-level</a:t>', $tocSlide);
        $t->contains('ppaction://hlinksldjump', $tocSlide);
        $tocRels = $tocPackage->read('ppt/slides/_rels/slide1.xml.rels');
        $t->contains('Target="slide3.xml"', $tocRels);
        $t->contains('Target="slide4.xml"', $tocRels);
        $t->contains('<a:t>Break with a new section-level header</a:t>', $tocPackage->read('ppt/slides/slide2.xml'));
        $t->contains('<Slides>5</Slides>', $tocPackage->read('docProps/app.xml'));
    },

    'maps upstream document properties into core and custom parts' => static function (TestRunner $t) use ($upstreamDocumentPropertiesNative, $upstreamDocumentPropertiesShortDescNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamDocumentPropertiesNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));
        $names = $package->names();

        $t->true(in_array('docProps/custom.xml', $names, true), 'Expected custom document properties part');

        $contentTypes = $package->read('[Content_Types].xml');
        $t->contains('/docProps/custom.xml', $contentTypes);
        $t->contains('application/vnd.openxmlformats-officedocument.custom-properties+xml', $contentTypes);

        $rootRelationships = $package->read('_rels/.rels');
        $t->contains('relationships/custom-properties', $rootRelationships);
        $t->contains('Target="docProps/custom.xml"', $rootRelationships);

        $core = $package->read('docProps/core.xml');
        $t->contains('<dc:title>Testing custom properties</dc:title>', $core);
        $t->contains('<dc:creator>A. M.</dc:creator>', $core);
        $t->contains('<cp:keywords>keyword 1, keyword 2</cp:keywords>', $core);
        $t->contains('<dc:subject>This is the subject</dc:subject>', $core);
        $t->contains('Long description spanning several lines.', $core);
        $t->contains("_x000d_\nThis is \u{00E1} second line.", $core);
        $t->contains('<cp:category>My Category</cp:category>', $core);
        foreach (['MetaInlines', 'MetaBlocks', 'MetaList'] as $constructor) {
            $t->true(!str_contains($core, $constructor), "Core properties leaked {$constructor}");
        }

        $custom = $package->read('docProps/custom.xml');
        $t->contains('name="Company"><vt:lpwstr>My Company</vt:lpwstr>', $custom);
        $t->contains('name="Second Custom Property"><vt:lpwstr>Second custom property value</vt:lpwstr>', $custom);
        $t->contains('name="abstract"><vt:lpwstr>Quite a long description spanning several lines</vt:lpwstr>', $custom);
        $t->contains('name="custom1"><vt:lpwstr>First custom property value</vt:lpwstr>', $custom);
        $t->contains('name="custom3"><vt:lpwstr>Escaping amp &amp; .</vt:lpwstr>', $custom);
        $t->contains('name="custom4"><vt:lpwstr>Escaping LT,GT &lt; asdf &gt; &lt;</vt:lpwstr>', $custom);
        $t->contains('name="custom5"><vt:lpwstr>Escaping html asdf</vt:lpwstr>', $custom);
        $t->contains("name=\"custom6\"><vt:lpwstr>Escaping MD \u{00E1} a</vt:lpwstr>", $custom);
        $t->contains("name=\"custom9\"><vt:lpwstr>Extended chars: \u{20AC} \u{00E1} \u{00E9} \u{00ED} \u{00F3} \u{00FA} $</vt:lpwstr>", $custom);
        $t->contains('name="nested-custom"><vt:lpwstr></vt:lpwstr>', $custom);
        $t->contains('name="subtitle"><vt:lpwstr>This is a subtitle</vt:lpwstr>', $custom);
        foreach (['MetaInlines', 'MetaBlocks', 'MetaList', '&lt;i&gt;'] as $leak) {
            $t->true(!str_contains($custom, $leak), "Custom properties leaked {$leak}");
        }

        $shortDocument = (new NativeReader())->read($upstreamDocumentPropertiesShortDescNative);
        $shortPackage = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($shortDocument));
        $shortCore = $shortPackage->read('docProps/core.xml');
        $shortCustom = $shortPackage->read('docProps/custom.xml');

        $t->contains('<dc:description>Short description &amp;.</dc:description>', $shortCore);
        $t->true(!str_contains($shortCore, '<cp:category>'), 'Short description fixture must not emit an empty category element');
        $t->true(!str_contains($shortCustom, '<property '), 'Fixture without custom metadata should still emit an empty custom properties part');
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

    'maps upstream blank layout fixtures to shape-free slides' => static function (TestRunner $t) use ($upstreamBlankJustSpeakerNotesNative, $upstreamBlankNbspBodyNative, $upstreamBlankNbspHeadingNative, $mediaOptions): void {
        $cases = [
            'speaker notes only' => [
                'native' => $upstreamBlankJustSpeakerNotesNative,
                'notes' => true,
            ],
            'nbsp body only' => [
                'native' => $upstreamBlankNbspBodyNative,
                'notes' => false,
            ],
            'nbsp heading only' => [
                'native' => $upstreamBlankNbspHeadingNative,
                'notes' => false,
            ],
        ];

        foreach ($cases as $label => $case) {
            $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write((new NativeReader())->read($case['native'])));
            $names = $package->names();

            foreach (['ppt/slides/slide1.xml', 'ppt/slides/slide2.xml', 'ppt/slides/slide3.xml'] as $partName) {
                $t->true(in_array($partName, $names, true), "{$label}: missing {$partName}");
            }
            $t->true(!in_array('ppt/slides/slide4.xml', $names, true), "{$label}: blank fixture should produce exactly three slides");

            $slide1 = $package->read('ppt/slides/slide1.xml');
            $slide2 = $package->read('ppt/slides/slide2.xml');
            $slide3 = $package->read('ppt/slides/slide3.xml');
            $t->contains('<a:t>First slide</a:t>', $slide1);
            $t->contains('<a:t>Third slide</a:t>', $slide3);
            $t->true(!str_contains($slide2, '<p:sp>'), "{$label}: blank slide must not emit title/content shapes");
            $t->true(!str_contains($slide2, '<p:pic>'), "{$label}: blank slide must not emit pictures");
            $t->true(!str_contains($slide2, '<a:t>'), "{$label}: blank slide must not emit visible text runs");
            $t->true(!str_contains($slide2, 'Slide 2'), "{$label}: blank heading must not fall back to a generated title");
            $t->true(!str_contains($slide2, "\u{00A0}"), "{$label}: NBSP-only content must not render as visible slide text");

            $slide2Rels = $package->read('ppt/slides/_rels/slide2.xml.rels');
            if ($case['notes'] === true) {
                $t->contains('relationships/notesSlide', $slide2Rels);
                $notes = $package->read('ppt/notesSlides/notesSlide1.xml');
                $t->contains('Some', $notes);
                $t->contains('Blank', $notes);
                $t->contains('<Notes>1</Notes>', $package->read('docProps/app.xml'));
            } else {
                $t->true(!str_contains($slide2Rels, 'relationships/notesSlide'), "{$label}: non-note blank slide must not emit notes relationships");
            }
            $t->contains('<Slides>3</Slides>', $package->read('docProps/app.xml'));
        }
    },

    'maps upstream image-only and figure-caption slides' => static function (TestRunner $t) use ($upstreamImagesNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamImagesNative);
        $imageOptions = array_replace($mediaOptions, [
            'mediaResources' => [
                'lalune.jpg' => ['data' => "\xff\xd8moon", 'mimeType' => 'image/jpeg'],
            ],
        ]);
        $package = ZipPackage::fromString((new PptxWriter($imageOptions))->write($document));
        $names = $package->names();

        foreach ([
            'ppt/slides/slide1.xml',
            'ppt/slides/slide2.xml',
            'ppt/slides/slide3.xml',
            'ppt/media/image1.jpg',
        ] as $partName) {
            $t->true(in_array($partName, $names, true), "Missing upstream images fixture part {$partName}");
        }
        $t->true(!in_array('ppt/slides/slide4.xml', $names, true), 'Images fixture should produce exactly three slides');
        $t->true(!in_array('ppt/media/image2.jpg', $names, true), 'Repeated fixture image should reuse one media part');

        $slide1 = $package->read('ppt/slides/slide1.xml');
        $t->contains('<p:pic>', $slide1);
        $t->true(!str_contains($slide1, 'type="title"'), 'Pre-heading image-only slide must not inherit the first heading title');
        $t->true(!str_contains($slide1, '<a:t>One More</a:t>'), 'First image-only slide must not render the later heading title');
        $t->true(!str_contains($slide1, '<a:t>The Moon</a:t>'), 'Empty-alt image must not create a visible caption');

        $slide2 = $package->read('ppt/slides/slide2.xml');
        $t->contains('<p:pic>', $slide2);
        $t->contains('<a:t>The Moon</a:t>', $slide2);
        $t->true(!str_contains($slide2, 'type="title"'), 'Pre-heading figure slide must stay titleless');
        $t->true(!str_contains($slide2, '<a:t>One More</a:t>'), 'Figure slide must not render the later heading title');

        $slide3 = $package->read('ppt/slides/slide3.xml');
        $t->contains('<a:t>One More</a:t>', $slide3);
        $t->contains('<p:pic>', $slide3);
        $t->contains('<a:t>The Moon</a:t>', $slide3);
        $t->contains('<Slides>3</Slides>', $package->read('docProps/app.xml'));
    },

    'maps upstream content-with-caption slide grouping' => static function (TestRunner $t) use ($upstreamContentWithCaptionTextImageNative, $upstreamContentWithCaptionImageTextNative, $upstreamContentWithCaptionHeadingTextImageNative, $mediaOptions): void {
        $imageOptions = array_replace($mediaOptions, [
            'mediaResources' => [
                'lalune.jpg' => ['data' => "\xff\xd8moon", 'mimeType' => 'image/jpeg'],
            ],
        ]);

        $textImage = ZipPackage::fromString((new PptxWriter($imageOptions))->write((new NativeReader())->read($upstreamContentWithCaptionTextImageNative)));
        $textImageSlide = $textImage->read('ppt/slides/slide1.xml');
        $t->true(!in_array('ppt/slides/slide2.xml', $textImage->names(), true), 'Text-then-figure fixture should stay on one slide');
        $t->true(!str_contains($textImageSlide, 'type="title"'), 'No-title text-then-figure fixture should not emit an Untitled title placeholder');
        $t->contains('<a:t>Some text here</a:t>', $textImageSlide);
        $t->contains('<p:pic>', $textImageSlide);
        $t->contains('<a:t>Followed by a picture</a:t>', $textImageSlide);
        $t->contains('<Slides>1</Slides>', $textImage->read('docProps/app.xml'));

        $imageText = ZipPackage::fromString((new PptxWriter($imageOptions))->write((new NativeReader())->read($upstreamContentWithCaptionImageTextNative)));
        $imageTextSlide1 = $imageText->read('ppt/slides/slide1.xml');
        $imageTextSlide2 = $imageText->read('ppt/slides/slide2.xml');
        $t->true(!in_array('ppt/slides/slide3.xml', $imageText->names(), true), 'Image-then-text fixture should split into exactly two slides');
        $t->contains('<p:pic>', $imageTextSlide1);
        $t->contains('<a:t>The picture first</a:t>', $imageTextSlide1);
        $t->true(!str_contains($imageTextSlide1, '<a:t>Then some text here</a:t>'), 'Image slide should not absorb following text');
        $t->contains('<a:t>Then some text here</a:t>', $imageTextSlide2);
        $t->true(!str_contains($imageTextSlide2, '<p:pic>'), 'Second image-text slide should contain only the following text');
        $t->contains('<Slides>2</Slides>', $imageText->read('docProps/app.xml'));

        $headingTextImage = ZipPackage::fromString((new PptxWriter($imageOptions))->write((new NativeReader())->read($upstreamContentWithCaptionHeadingTextImageNative)));
        $headingSlide = $headingTextImage->read('ppt/slides/slide1.xml');
        $t->true(!in_array('ppt/slides/slide2.xml', $headingTextImage->names(), true), 'Heading text figure fixture should stay on one slide');
        $t->contains('type="title"', $headingSlide);
        $t->contains('<a:t>A slide</a:t>', $headingSlide);
        $t->contains('<a:t>Some text here</a:t>', $headingSlide);
        $t->contains('<p:pic>', $headingSlide);
        $t->contains('<a:t>Followed by a picture</a:t>', $headingSlide);
    },

    'maps upstream two-column text layout into separate content placeholders' => static function (TestRunner $t) use ($upstreamTwoColumnAllTextNative, $mediaOptions): void {
        $document = (new NativeReader())->read($upstreamTwoColumnAllTextNative);
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write($document));

        $t->true(!in_array('ppt/slides/slide2.xml', $package->names(), true), 'Two-column all-text fixture should produce one slide');

        $slide = $package->read('ppt/slides/slide1.xml');
        $t->contains('<a:t>Two-Column Layout</a:t>', $slide);
        $t->contains('idx="1"', $slide);
        $t->contains('idx="2"', $slide);
        $t->same(3, substr_count($slide, '<p:sp>'));
        $t->true(preg_match('/idx="1".*<a:t>One paragraph\\.<\\/a:t>.*<a:t>Another paragraph\\.<\\/a:t>/s', $slide) === 1, 'First column paragraphs should share one placeholder');
        $t->true(preg_match('/idx="2".*<a:t>Second column paragraph\\.<\\/a:t>.*<a:t>Another second paragraph\\.<\\/a:t>/s', $slide) === 1, 'Second column paragraphs should share one placeholder');
        $t->contains('<Slides>1</Slides>', $package->read('docProps/app.xml'));
    },

    'maps upstream two-column text and image layout into column regions' => static function (TestRunner $t) use ($upstreamTwoColumnTextImageNative, $mediaOptions): void {
        $imageOptions = array_replace($mediaOptions, [
            'mediaResources' => [
                'lalune.jpg' => ['data' => "\xff\xd8moon", 'mimeType' => 'image/jpeg'],
            ],
        ]);
        $package = ZipPackage::fromString((new PptxWriter($imageOptions))->write((new NativeReader())->read($upstreamTwoColumnTextImageNative)));

        $names = $package->names();
        $t->true(in_array('ppt/slides/slide1.xml', $names, true), 'Expected first two-column slide');
        $t->true(in_array('ppt/slides/slide2.xml', $names, true), 'Expected second two-column slide');
        $t->true(!in_array('ppt/slides/slide3.xml', $names, true), 'Two-column text/image fixture should produce exactly two slides');
        $t->true(in_array('ppt/media/image1.jpg', $names, true), 'Expected packed column image');
        $t->true(!in_array('ppt/media/image2.jpg', $names, true), 'Repeated column image should reuse one media part');

        $slide1 = $package->read('ppt/slides/slide1.xml');
        $t->contains('<p:pic>', $slide1);
        $t->contains('idx="2"', $slide1);
        $t->contains('<a:t>This should use Two Content,</a:t>', $slide1);
        $t->contains('<a:t>not</a:t>', $slide1);
        $t->contains('<a:t> Comparison!</a:t>', $slide1);
        $t->true(strpos($slide1, '<p:pic>') < strpos($slide1, 'idx="2"'), 'Slide 1 should keep the image in the left column before right-column text');

        $slide2 = $package->read('ppt/slides/slide2.xml');
        $t->contains('idx="1"', $slide2);
        $t->contains('<a:t>This should also use Two Content</a:t>', $slide2);
        $t->contains('<p:pic>', $slide2);
        $t->true(strpos($slide2, 'idx="1"') < strpos($slide2, '<p:pic>'), 'Slide 2 should keep text in the left column before the right-column image');
        $t->contains('<Slides>2</Slides>', $package->read('docProps/app.xml'));
    },

    'maps upstream single-column text layout into one content placeholder' => static function (TestRunner $t) use ($upstreamSingleColumnTextNative, $mediaOptions): void {
        $package = ZipPackage::fromString((new PptxWriter($mediaOptions))->write((new NativeReader())->read($upstreamSingleColumnTextNative)));

        $t->true(!in_array('ppt/slides/slide2.xml', $package->names(), true), 'Single-column text fixture should produce one slide');

        $slide = $package->read('ppt/slides/slide1.xml');
        $t->contains('<a:t>Single column</a:t>', $slide);
        $t->contains('idx="1"', $slide);
        $t->true(!str_contains($slide, 'idx="2"'), 'Single-column text fixture must not emit a second column placeholder');
        $t->same(2, substr_count($slide, '<p:sp>'));
        $t->true(preg_match('/idx="1".*<a:t>One paragraph\\.<\\/a:t>.*<a:t>Another paragraph\\.<\\/a:t>/s', $slide) === 1, 'Single-column paragraphs should share one placeholder');
        $t->contains('<Slides>1</Slides>', $package->read('docProps/app.xml'));
    },

    'maps upstream single-column figure layout into picture and caption' => static function (TestRunner $t) use ($upstreamSingleColumnImageNative, $mediaOptions): void {
        $imageOptions = array_replace($mediaOptions, [
            'mediaResources' => [
                'lalune.jpg' => ['data' => "\xff\xd8moon", 'mimeType' => 'image/jpeg'],
            ],
        ]);
        $package = ZipPackage::fromString((new PptxWriter($imageOptions))->write((new NativeReader())->read($upstreamSingleColumnImageNative)));
        $names = $package->names();

        $t->true(in_array('ppt/media/image1.jpg', $names, true), 'Expected packed single-column figure image');
        $t->true(!in_array('ppt/slides/slide2.xml', $names, true), 'Single-column image fixture should produce one slide');

        $slide = $package->read('ppt/slides/slide1.xml');
        $t->contains('<a:t>Single column</a:t>', $slide);
        $t->contains('<p:pic>', $slide);
        $t->contains('idx="1"', $slide);
        $t->contains('<a:t>an image</a:t>', $slide);
        $t->true(strpos($slide, '<p:pic>') < strrpos($slide, '<a:t>an image</a:t>'), 'Figure caption should render after the column picture');
        $t->contains('<Slides>1</Slides>', $package->read('docProps/app.xml'));
    },

    'maps upstream comparison layout mixed columns into real table and image shapes' => static function (TestRunner $t) use ($upstreamComparisonBothColumnsNative, $upstreamComparisonExtraImageNative, $mediaOptions): void {
        $imageOptions = array_replace($mediaOptions, [
            'mediaResources' => [
                'lalune.jpg' => ['data' => "\xff\xd8moon", 'mimeType' => 'image/jpeg'],
            ],
        ]);

        $bothColumns = ZipPackage::fromString((new PptxWriter($imageOptions))->write((new NativeReader())->read($upstreamComparisonBothColumnsNative)));
        $bothSlide = $bothColumns->read('ppt/slides/slide1.xml');
        $t->true(!in_array('ppt/slides/slide2.xml', $bothColumns->names(), true), 'Comparison both-columns fixture should produce one slide');
        $t->contains('<a:t>A slide</a:t>', $bothSlide);
        $t->contains('<p:graphicFrame>', $bothSlide);
        $t->contains('<a:t>plus</a:t>', $bothSlide);
        $t->contains('<a:t>a table</a:t>', $bothSlide);
        $t->contains('<a:t>Then some more text</a:t>', $bothSlide);
        $t->contains('<p:pic>', $bothSlide);
        $t->contains('<a:t>Plus an image</a:t>', $bothSlide);
        $t->true(strpos($bothSlide, '<p:graphicFrame>') < strpos($bothSlide, '<p:pic>'), 'Table column should render before the image column');
        $t->true(in_array('ppt/media/image1.jpg', $bothColumns->names(), true), 'Expected comparison image media part');
        $t->true(!in_array('ppt/media/image2.jpg', $bothColumns->names(), true), 'Single repeated source should not create extra media in both-columns fixture');

        $extraImage = ZipPackage::fromString((new PptxWriter($imageOptions))->write((new NativeReader())->read($upstreamComparisonExtraImageNative)));
        $extraSlide = $extraImage->read('ppt/slides/slide1.xml');
        $t->same(2, substr_count($extraSlide, '<p:pic>'));
        $t->contains('<a:t>And another image</a:t>', $extraSlide);
        $t->true(!in_array('ppt/media/image2.jpg', $extraImage->names(), true), 'Repeated comparison images should reuse one media part');
        $t->contains('<Slides>1</Slides>', $extraImage->read('docProps/app.xml'));
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
