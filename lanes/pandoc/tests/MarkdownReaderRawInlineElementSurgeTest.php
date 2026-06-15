<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

/**
 * @return list<string>
 */
$rawHtmlInlines = static function (AstNode $paragraph): array {
    $raw = [];
    foreach ($paragraph->children as $child) {
        if ($child->type === 'raw_html_inline') {
            $raw[] = (string) $child->attr('html', '');
        }
    }

    return $raw;
};

$cases = [
    '01 acronym legacy inline tag' => ['<acronym title="Portable">PORT</acronym>', ['<acronym title="Portable">', '</acronym>']],
    '02 basefont self closing tag' => ['<basefont color="red"/>', ['<basefont color="red"/>']],
    '03 big legacy inline tag' => ['<big>large</big>', ['<big>', '</big>']],
    '04 body structural tag' => ['<body data-source="inline">body</body>', ['<body data-source="inline">', '</body>']],
    '05 caption table tag' => ['<caption>cap</caption>', ['<caption>', '</caption>']],
    '06 center legacy inline tag' => ['<center>centered</center>', ['<center>', '</center>']],
    '07 colgroup table tag' => ['<colgroup span="2"></colgroup>', ['<colgroup span="2">', '</colgroup>']],
    '08 dd definition tag' => ['<dd>definition</dd>', ['<dd>', '</dd>']],
    '09 dir legacy list tag' => ['<dir><li>entry</li></dir>', ['<dir>', '<li>', '</li>', '</dir>']],
    '10 fecolormatrix svg filter tag' => ['<feColorMatrix type="matrix"/>', ['<feColorMatrix type="matrix"/>']],
    '11 dt definition term tag' => ['<dt>term</dt>', ['<dt>', '</dt>']],
    '12 font legacy inline tag' => ['<font color="red">red</font>', ['<font color="red">', '</font>']],
    '13 frame structural tag' => ['<frame src="frame.html">', ['<frame src="frame.html">']],
    '14 frameset structural tag' => ['<frameset cols="*"></frameset>', ['<frameset cols="*">', '</frameset>']],
    '15 head title container tags' => ['<head><title>x</title></head>', ['<head>', '<title>', '</title>', '</head>']],
    '16 html structural tag' => ['<html lang="en"></html>', ['<html lang="en">', '</html>']],
    '17 menuitem legacy command tag' => ['<menuitem label="Save">', ['<menuitem label="Save">']],
    '18 noframes fallback tag' => ['<noframes>fallback</noframes>', ['<noframes>', '</noframes>']],
    '19 strike legacy inline tag' => ['<strike>old</strike>', ['<strike>', '</strike>']],
    '20 title metadata tag' => ['<title>Packet</title>', ['<title>', '</title>']],
    '21 tt legacy inline tag' => ['<tt>mono</tt>', ['<tt>', '</tt>']],
    '22 annotation mathml tag' => ['<annotation encoding="text/plain">note</annotation>', ['<annotation encoding="text/plain">', '</annotation>']],
    '23 femerge svg filter tags' => ['<feMerge><feMergeNode in="blur"/></feMerge>', ['<feMerge>', '<feMergeNode in="blur"/>', '</feMerge>']],
    '24 maction mathml tag' => ['<maction actiontype="toggle">x</maction>', ['<maction actiontype="toggle">', '</maction>']],
    '25 maligngroup mathml tag' => ['<maligngroup/>', ['<maligngroup/>']],
    '26 malignmark mathml tag' => ['<malignmark/>', ['<malignmark/>']],
    '27 merror mathml tag' => ['<merror><mtext>bad</mtext></merror>', ['<merror>', '<mtext>', '</mtext>', '</merror>']],
    '28 mfenced mathml tag' => ['<mfenced><mi>x</mi></mfenced>', ['<mfenced>', '<mi>', '</mi>', '</mfenced>']],
    '29 mfrac mathml tag' => ['<mfrac><mi>a</mi><mi>b</mi></mfrac>', ['<mfrac>', '<mi>', '</mi>', '<mi>', '</mi>', '</mfrac>']],
    '30 mglyph mathml tag' => ['<mglyph alt="x"/>', ['<mglyph alt="x"/>']],
    '31 mlongdiv mathml tag' => ['<mlongdiv><mn>1</mn></mlongdiv>', ['<mlongdiv>', '<mn>', '</mn>', '</mlongdiv>']],
    '32 mmultiscripts mathml tag' => ['<mmultiscripts><mi>x</mi></mmultiscripts>', ['<mmultiscripts>', '<mi>', '</mi>', '</mmultiscripts>']],
    '33 mover mathml tag' => ['<mover><mi>x</mi><mo>^</mo></mover>', ['<mover>', '<mi>', '</mi>', '<mo>', '</mo>', '</mover>']],
    '34 mpadded mathml tag' => ['<mpadded width="+1em">x</mpadded>', ['<mpadded width="+1em">', '</mpadded>']],
    '35 mprescripts mathml tag' => ['<mprescripts/>', ['<mprescripts/>']],
    '36 mroot mathml tag' => ['<mroot><mi>x</mi><mn>3</mn></mroot>', ['<mroot>', '<mi>', '</mi>', '<mn>', '</mn>', '</mroot>']],
    '37 ms mathml string tag' => ['<ms>text</ms>', ['<ms>', '</ms>']],
    '38 mscarries mathml tag' => ['<mscarries><mscarry>1</mscarry></mscarries>', ['<mscarries>', '<mscarry>', '</mscarry>', '</mscarries>']],
    '39 mscarry mathml tag' => ['<mscarry location="n">1</mscarry>', ['<mscarry location="n">', '</mscarry>']],
    '40 msgroup mathml tag' => ['<msgroup><msrow>1</msrow></msgroup>', ['<msgroup>', '<msrow>', '</msrow>', '</msgroup>']],
    '41 msline mathml tag' => ['<msline/>', ['<msline/>']],
    '42 msrow mathml tag' => ['<msrow><mn>1</mn></msrow>', ['<msrow>', '<mn>', '</mn>', '</msrow>']],
    '43 mspace mathml tag' => ['<mspace width="1em"/>', ['<mspace width="1em"/>']],
    '44 mstack mathml tag' => ['<mstack><msrow>1</msrow></mstack>', ['<mstack>', '<msrow>', '</msrow>', '</mstack>']],
    '45 mstyle mathml tag' => ['<mstyle displaystyle="true">x</mstyle>', ['<mstyle displaystyle="true">', '</mstyle>']],
    '46 msubsup mathml tag' => ['<msubsup><mi>x</mi><mn>1</mn><mn>2</mn></msubsup>', ['<msubsup>', '<mi>', '</mi>', '<mn>', '</mn>', '<mn>', '</mn>', '</msubsup>']],
    '47 munder mathml tag' => ['<munder><mi>x</mi><mo>_</mo></munder>', ['<munder>', '<mi>', '</mi>', '<mo>', '</mo>', '</munder>']],
    '48 munderover mathml tag' => ['<munderover><mi>x</mi><mo>_</mo><mo>^</mo></munderover>', ['<munderover>', '<mi>', '</mi>', '<mo>', '</mo>', '<mo>', '</mo>', '</munderover>']],
    '49 none mathml tag' => ['<none/>', ['<none/>']],
    '50 animate svg tag' => ['<animate attributeName="x" from="0" to="1"/>', ['<animate attributeName="x" from="0" to="1"/>']],
];

$tests = [];

foreach ($cases as $name => [$markdown, $expectedRaw]) {
    $tests['maps upstream markdown reader raw inline element completion ' . $name] =
        static function (TestRunner $t) use ($markdown, $expectedRaw, $rawHtmlInlines): void {
            $document = (new MarkdownReader())->read('Lead ' . $markdown . ' trail');
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $rawHtml = $rawHtmlInlines($paragraph);
            $roundTrip = (new MarkdownWriter())->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('paragraph', $paragraph->type);
            $t->same($expectedRaw, $rawHtml);
            foreach ($expectedRaw as $raw) {
                $t->contains($raw, $roundTrip);
                $t->contains($raw, $blocks);
            }
        };
}

$tests['records markdown reader raw inline element completion mapped-case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(50, count($cases));
    };

return $tests;
