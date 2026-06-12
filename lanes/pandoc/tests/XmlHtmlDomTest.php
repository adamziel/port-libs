<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'loads safe XML documents and preserves namespace attributes' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(
            '<pkg xmlns="urn:packet"><item xml:lang="en">Review &amp; Import</item></pkg>',
            'review packet XML'
        );

        $root = $dom->documentElement;
        $item = $dom->getElementsByTagNameNS('urn:packet', 'item')->item(0);

        $t->true($root instanceof DOMElement);
        $t->same('pkg', $root->localName);
        $t->same('urn:packet', $root->namespaceURI);
        $t->true($item instanceof DOMElement);
        $t->same('en', $item->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        $t->same('Review & Import', $item->textContent);
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument('<pkg><item></pkg>', 'broken XML'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument('<!DOCTYPE pkg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><pkg>&xxe;</pkg>', 'unsafe XML'));
    },
    'allows XML declarations but rejects XML processing instructions' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(
            '<?xml version="1.0" encoding="UTF-8"?><pkg><item>Review packet</item></pkg>',
            'declared review packet XML',
            preserveWhiteSpace: false
        );

        $t->true($dom->documentElement instanceof DOMElement);
        $t->same('pkg', $dom->documentElement->tagName);
        $t->same('Review packet', $dom->documentElement->textContent);
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument(
            '<?xml-stylesheet href="https://example.invalid/review.xsl"?><pkg><item>review</item></pkg>',
            'stylesheet XML'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument(
            '<?xml version="1.0"?><pkg><?review href="file:///etc/passwd"?><item>review</item></pkg>',
            'review PI XML'
        ));
    },
    'queries namespaced XML DOM nodes for package reader handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(<<<'XML'
<pkg:package xmlns:pkg="urn:pkg" xmlns:w="urn:word" xmlns:rel="urn:relationship" rel:id="root">
  <pkg:metadata>
    <w:title xml:lang="en">  Review
      Packet </w:title>
    <w:title xml:lang="fr">Ignored</w:title>
  </pkg:metadata>
  <pkg:body>
    <w:p rel:id="rId1"> First <w:r> run </w:r></w:p>
    <pkg:p>Package paragraph</pkg:p>
  </pkg:body>
</pkg:package>
XML, 'package reader XML');
        $root = XmlHtmlDom::rootElement($dom, 'package', 'urn:pkg');
        $metadata = $root instanceof DOMElement ? XmlHtmlDom::firstChildElement($root, 'metadata', 'urn:pkg') : null;
        $body = $root instanceof DOMElement ? XmlHtmlDom::firstChildElement($root, 'body', 'urn:pkg') : null;
        $titles = $root instanceof DOMElement ? XmlHtmlDom::descendantElements($root, 'title', 'urn:word') : [];
        $paragraph = $body instanceof DOMElement ? XmlHtmlDom::firstDescendantElement($body, 'p', 'urn:word') : null;

        $t->true($root instanceof DOMElement);
        $t->true(XmlHtmlDom::elementMatches($root, 'package', 'urn:pkg'));
        $t->true(XmlHtmlDom::elementMatches($root, null, 'urn:pkg'));
        $t->true(!XmlHtmlDom::elementMatches($root, 'package', 'urn:word'));
        $t->same($root, XmlHtmlDom::rootElement($dom, null, 'urn:pkg'));
        $t->same(null, XmlHtmlDom::rootElement($dom, 'package', 'urn:word'));
        $t->true($metadata instanceof DOMElement);
        $t->true($body instanceof DOMElement);
        $t->same(2, count($titles));
        $t->same('Review Packet', XmlHtmlDom::normalizedText($titles[0]));
        $t->same('en', XmlHtmlDom::attribute($titles[0], 'lang', 'http://www.w3.org/XML/1998/namespace'));
        $t->same('root', XmlHtmlDom::attribute($root, 'id', 'urn:relationship'));
        $t->same(null, XmlHtmlDom::attribute($root, 'missing', 'urn:relationship'));
        $t->same(0, count($root instanceof DOMElement ? XmlHtmlDom::childElements($root, 'p', 'urn:word') : []));
        $t->true($paragraph instanceof DOMElement);
        $t->same('rId1', $paragraph instanceof DOMElement ? XmlHtmlDom::attribute($paragraph, 'id', 'urn:relationship') : null);
        $t->same('First run', $paragraph instanceof DOMElement ? XmlHtmlDom::normalizedText($paragraph) : null);
    },
    'recovers HTML5 fragments with list autoclose and void elements' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p data-id="42">Intro<br>Next<img src="cover.png?x=1&amp;y=2" alt="Cover"></p><ul><li>One<li>Two</ul>',
            'review HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same(2, count($summary));
        $t->same('p', $summary[0]['name']);
        $t->same(['data-id' => '42'], $summary[0]['attributes']);
        $t->same('br', $summary[0]['children'][1]['name']);
        $t->same('img', $summary[0]['children'][3]['name']);
        $t->same(['alt' => 'Cover', 'src' => 'cover.png?x=1&y=2'], $summary[0]['children'][3]['attributes']);
        $t->same('ul', $summary[1]['name']);
        $t->same('li', $summary[1]['children'][0]['name']);
        $t->same('One', $summary[1]['children'][0]['text']);
        $t->same('Two', $summary[1]['children'][1]['text']);
        $t->same('<p data-id="42">Intro<br>Next<img alt="Cover" src="cover.png?x=1&amp;y=2"></p><ul><li>One</li><li>Two</li></ul>', $html);
    },
    'summarizes html break and separator elements for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p>Alpha<br id="hard">Beta<wbr data-source="wrap">Gamma</p><hr id="rule" class="review-separator">',
            'break element review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/break-elements-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $hardBreak = $paragraph['children'][1];
        $wordBreak = $paragraph['children'][3];
        $rule = $summary[1];

        $t->same('p', $paragraph['name']);
        $t->same('AlphaBetaGamma', $paragraph['text']);
        $t->same('br', $hardBreak['name']);
        $t->same('line-break', $hardBreak['breakElement']);
        $t->same('br', $hardBreak['breakTag']);
        $t->same("\n", $hardBreak['textEquivalent']);
        $t->same(true, $hardBreak['hardBreak']);
        $t->same('hard', $hardBreak['elementId']);
        $t->same('wbr', $wordBreak['name']);
        $t->same('word-break-opportunity', $wordBreak['breakElement']);
        $t->same('', $wordBreak['textEquivalent']);
        $t->same(true, $wordBreak['softBreakOpportunity']);
        $t->same(['source' => 'wrap'], $wordBreak['dataset']);
        $t->same('hr', $rule['name']);
        $t->same('thematic-break', $rule['breakElement']);
        $t->same(true, $rule['blockSeparator']);
        $t->same(['review-separator'], $rule['classList']);
        $t->same('<p>Alpha<br id="hard">Beta<wbr data-source="wrap">Gamma</p><hr class="review-separator" id="rule">', $html);
        $t->contains('<wbr data-source="wrap">', $blocks);
        $t->contains('<hr class="review-separator" id="rule">', $blocks);
        $t->same('/migration/break-elements-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html global attributes and dataset state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="packet" class="alpha  beta alpha" lang="en-US" dir="RTL" title="Review &amp; Source" data-review-id="A-42" data-package-part="word/document.xml" hidden="until-found" translate="no" contenteditable="plaintext-only" draggable="true" spellcheck="false" tabindex="-1" role="doc-chapter region" aria-label="Packet Section"><p class="child">Body</p></section>'
                . '<p data-review-stage="preflight" dir="sideways" translate="maybe" contenteditable="maybe" draggable="maybe" spellcheck="maybe">Fallback</p>'
                . '<table id="review-table" class="data-grid" data-package-part="word/tables.xml"><tr><td>Cell</td></tr></table>',
            'global attribute review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $section = $summary[0];
        $fallback = $summary[1];
        $table = $summary[2];

        $t->same('packet', $section['elementId']);
        $t->same('alpha  beta alpha', $section['classRaw']);
        $t->same(['alpha', 'beta', 'alpha'], $section['classList']);
        $t->same('en-US', $section['languageRaw']);
        $t->same('en-US', $section['language']);
        $t->same('RTL', $section['dirRaw']);
        $t->same('rtl', $section['direction']);
        $t->same('Review & Source', $section['titleAttribute']);
        $t->same('until-found', $section['hiddenRaw']);
        $t->same('until-found', $section['hiddenState']);
        $t->same('no', $section['translateRaw']);
        $t->same(false, $section['translate']);
        $t->same('plaintext-only', $section['contentEditable']);
        $t->same(true, $section['draggable']);
        $t->same(false, $section['spellcheck']);
        $t->same('-1', $section['tabIndexRaw']);
        $t->same(-1, $section['tabIndex']);
        $t->same('doc-chapter region', $section['roleRaw']);
        $t->same(['doc-chapter', 'region'], $section['roles']);
        $t->same(['aria-label' => 'Packet Section'], $section['ariaAttributes']);
        $t->same([
            'data-package-part' => 'word/document.xml',
            'data-review-id' => 'A-42',
        ], $section['dataAttributes']);
        $t->same([
            'packagePart' => 'word/document.xml',
            'reviewId' => 'A-42',
        ], $section['dataset']);
        $t->same('child', $section['children'][0]['classRaw']);
        $t->same(['child'], $section['children'][0]['classList']);

        $t->same('sideways', $fallback['dirRaw']);
        $t->same(null, $fallback['direction']);
        $t->same('maybe', $fallback['translateRaw']);
        $t->same(null, $fallback['translate']);
        $t->same(null, $fallback['contentEditable']);
        $t->same(null, $fallback['draggable']);
        $t->same(null, $fallback['spellcheck']);
        $t->same(['reviewStage' => 'preflight'], $fallback['dataset']);

        $t->same('review-table', $table['elementId']);
        $t->same(['data-grid'], $table['classList']);
        $t->same(['packagePart' => 'word/tables.xml'], $table['dataset']);
        $t->same('table', $table['tablePart']);
        $t->same(
            '<section aria-label="Packet Section" class="alpha  beta alpha" contenteditable="plaintext-only" data-package-part="word/document.xml" data-review-id="A-42" dir="RTL" draggable="true" hidden="until-found" id="packet" lang="en-US" role="doc-chapter region" spellcheck="false" tabindex="-1" title="Review &amp; Source" translate="no"><p class="child">Body</p></section>'
                . '<p contenteditable="maybe" data-review-stage="preflight" dir="sideways" draggable="maybe" spellcheck="maybe" translate="maybe">Fallback</p>'
                . '<table class="data-grid" data-package-part="word/tables.xml" id="review-table"><tr><td>Cell</td></tr></table>',
            $html
        );
    },
    'summarizes html focus navigation attributes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="focus-region" accesskey="s x s" autofocus="autofocus" tabindex="3"><button id="save" accesskey="k Enter" tabindex="-2">Save</button></section>'
                . '<p id="invalid-focus" accesskey="wide key" tabindex="bogus">No focus</p>',
            'focus navigation review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/focus-navigation-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $section = $summary[0];
        $button = $section['children'][0];
        $invalid = $summary[1];

        $t->same('focus-region', $section['elementId']);
        $t->same('s x s', $section['accessKeyRaw']);
        $t->same(['s', 'x', 's'], $section['accessKeyTokens']);
        $t->same(['s', 'x'], $section['accessKeys']);
        $t->same([], $section['invalidAccessKeyTokens']);
        $t->same(true, $section['accessKeyValid']);
        $t->same('autofocus', $section['autofocusRaw']);
        $t->same(true, $section['autofocus']);
        $t->same('3', $section['tabIndexRaw']);
        $t->same(3, $section['tabIndex']);
        $t->same(true, $section['tabIndexValid']);

        $t->same('button', $button['name']);
        $t->same('button', $button['formControl']);
        $t->same('save', $button['elementId']);
        $t->same('k Enter', $button['accessKeyRaw']);
        $t->same(['k', 'Enter'], $button['accessKeyTokens']);
        $t->same(['k'], $button['accessKeys']);
        $t->same(['Enter'], $button['invalidAccessKeyTokens']);
        $t->same(false, $button['accessKeyValid']);
        $t->same('-2', $button['tabIndexRaw']);
        $t->same(-2, $button['tabIndex']);
        $t->same(true, $button['tabIndexValid']);

        $t->same('invalid-focus', $invalid['elementId']);
        $t->same('wide key', $invalid['accessKeyRaw']);
        $t->same(['wide', 'key'], $invalid['accessKeyTokens']);
        $t->same([], $invalid['accessKeys']);
        $t->same(['wide', 'key'], $invalid['invalidAccessKeyTokens']);
        $t->same(false, $invalid['accessKeyValid']);
        $t->same('bogus', $invalid['tabIndexRaw']);
        $t->same(null, $invalid['tabIndex']);
        $t->same(false, $invalid['tabIndexValid']);

        $t->same('<section accesskey="s x s" autofocus id="focus-region" tabindex="3"><button accesskey="k Enter" id="save" tabindex="-2">Save</button></section><p accesskey="wide key" id="invalid-focus" tabindex="bogus">No focus</p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/focus-navigation-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html input hint attributes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="entry" autocapitalize="on"><input id="amount" inputmode="Decimal" enterkeyhint="Done" autocapitalize="characters">'
                . '<textarea id="message" inputmode="search" enterkeyhint="send" autocapitalize="off">Note</textarea></form>'
                . '<p id="fallback" inputmode="kana" enterkeyhint="compose" autocapitalize="maybe">Fallback</p>',
            'input hint review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/input-hints-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $input = $form['children'][0];
        $textarea = $form['children'][1];
        $fallback = $summary[1];

        $t->same('entry', $form['elementId']);
        $t->same('on', $form['autocapitalizeRaw']);
        $t->same('sentences', $form['autocapitalize']);
        $t->same(true, $form['autocapitalizeValid']);

        $t->same('input', $input['formControl']);
        $t->same('Decimal', $input['inputModeRaw']);
        $t->same('decimal', $input['inputMode']);
        $t->same(true, $input['inputModeValid']);
        $t->same('Done', $input['enterKeyHintRaw']);
        $t->same('done', $input['enterKeyHint']);
        $t->same(true, $input['enterKeyHintValid']);
        $t->same('characters', $input['autocapitalizeRaw']);
        $t->same('characters', $input['autocapitalize']);
        $t->same(true, $input['autocapitalizeValid']);

        $t->same('textarea', $textarea['formControl']);
        $t->same('search', $textarea['inputMode']);
        $t->same(true, $textarea['inputModeValid']);
        $t->same('send', $textarea['enterKeyHint']);
        $t->same(true, $textarea['enterKeyHintValid']);
        $t->same('none', $textarea['autocapitalize']);
        $t->same(true, $textarea['autocapitalizeValid']);

        $t->same('kana', $fallback['inputModeRaw']);
        $t->same(null, $fallback['inputMode']);
        $t->same(false, $fallback['inputModeValid']);
        $t->same('compose', $fallback['enterKeyHintRaw']);
        $t->same(null, $fallback['enterKeyHint']);
        $t->same(false, $fallback['enterKeyHintValid']);
        $t->same('maybe', $fallback['autocapitalizeRaw']);
        $t->same(null, $fallback['autocapitalize']);
        $t->same(false, $fallback['autocapitalizeValid']);

        $t->same('<form autocapitalize="on" id="entry"><input autocapitalize="characters" enterkeyhint="Done" id="amount" inputmode="Decimal"><textarea autocapitalize="off" enterkeyhint="send" id="message" inputmode="search">Note</textarea></form><p autocapitalize="maybe" enterkeyhint="compose" id="fallback" inputmode="kana">Fallback</p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/input-hints-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html list marker and item ordinal metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<ol id="steps" start="3" reversed type="A"><li value="7">Inspect<li>Repair<ol start="-2" type="i"><li value="-1">Nested</ol></ol>'
                . '<ul id="bullets" type="square"><li>Loose</li></ul><menu id="actions"><li value="4">Action</li></menu>'
                . '<ol id="invalid" start="abc"><li value="bad">Invalid</li></ol>',
            'list metadata review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $steps = $summary[0];
        $inspect = $steps['children'][0];
        $repair = $steps['children'][1];
        $nested = $repair['children'][1];
        $nestedItem = $nested['children'][0];
        $bullets = $summary[1];
        $loose = $bullets['children'][0];
        $menu = $summary[2];
        $action = $menu['children'][0];
        $invalid = $summary[3];
        $invalidItem = $invalid['children'][0];

        $t->same('ordered', $steps['list']);
        $t->same(true, $steps['reversed']);
        $t->same('3', $steps['startRaw']);
        $t->same(3, $steps['start']);
        $t->same('A', $steps['markerType']);
        $t->same(true, $inspect['listItem']);
        $t->same('7', $inspect['valueRaw']);
        $t->same(7, $inspect['value']);
        $t->same('ordered', $nested['list']);
        $t->same(false, $nested['reversed']);
        $t->same('-2', $nested['startRaw']);
        $t->same(-2, $nested['start']);
        $t->same('i', $nested['markerType']);
        $t->same('-1', $nestedItem['valueRaw']);
        $t->same(-1, $nestedItem['value']);
        $t->same('unordered', $bullets['list']);
        $t->same('square', $bullets['markerType']);
        $t->same(true, $loose['listItem']);
        $t->same(null, $loose['valueRaw']);
        $t->same(null, $loose['value']);
        $t->same('menu', $menu['list']);
        $t->same('4', $action['valueRaw']);
        $t->same(4, $action['value']);
        $t->same('ordered', $invalid['list']);
        $t->same('abc', $invalid['startRaw']);
        $t->same(1, $invalid['start']);
        $t->same('bad', $invalidItem['valueRaw']);
        $t->same(null, $invalidItem['value']);
        $t->same('<ol id="steps" reversed start="3" type="A"><li value="7">Inspect</li><li>Repair<ol start="-2" type="i"><li value="-1">Nested</li></ol></li></ol><ul id="bullets" type="square"><li>Loose</li></ul><menu id="actions"><li value="4">Action</li></menu><ol id="invalid" start="abc"><li value="bad">Invalid</li></ol>', $html);
    },
    'summarizes html heading and sectioning outline metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="story"><header><h1>Primary <em>Title</em></h1></header><section id="chapter"><h2>Chapter</h2><p>Body</p></section></article>'
                . '<nav aria-label="Contents"><div><h3>Navigation</h3></div><a href="#chapter">Chapter</a></nav>'
                . '<aside id="notes"><section id="nested-note"><h4>Nested note</h4></section></aside>'
                . '<main id="main"><p>No title</p></main>',
            'outline review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/outline-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $articleHeading = $article['children'][0]['children'][0];
        $section = $article['children'][1];
        $sectionHeading = $section['children'][0];
        $nav = $summary[1];
        $navHeading = $nav['children'][0]['children'][0];
        $aside = $summary[2];
        $nestedSection = $aside['children'][0];
        $main = $summary[3];

        $t->same('article', $article['name']);
        $t->same('article', $article['documentOutline']);
        $t->same('article', $article['outlineRoot']);
        $t->same('Primary Title', $article['sectionHeadingText']);
        $t->same('h1', $article['sectionHeadingTag']);
        $t->same(1, $article['sectionHeadingLevel']);
        $t->same('heading', $articleHeading['documentOutline']);
        $t->same(true, $articleHeading['heading']);
        $t->same('h1', $articleHeading['headingTag']);
        $t->same(1, $articleHeading['headingLevel']);
        $t->same('Primary Title', $articleHeading['headingText']);

        $t->same('section', $section['documentOutline']);
        $t->same('section', $section['outlineRoot']);
        $t->same('Chapter', $section['sectionHeadingText']);
        $t->same('h2', $section['sectionHeadingTag']);
        $t->same(2, $section['sectionHeadingLevel']);
        $t->same('heading', $sectionHeading['documentOutline']);
        $t->same(2, $sectionHeading['headingLevel']);
        $t->same('Chapter', $sectionHeading['headingText']);

        $t->same('navigation', $nav['documentOutline']);
        $t->same('nav', $nav['outlineRoot']);
        $t->same('Navigation', $nav['sectionHeadingText']);
        $t->same('h3', $nav['sectionHeadingTag']);
        $t->same(3, $nav['sectionHeadingLevel']);
        $t->same(['aria-label' => 'Contents'], $nav['ariaAttributes']);
        $t->same('heading', $navHeading['documentOutline']);
        $t->same(3, $navHeading['headingLevel']);

        $t->same('aside', $aside['documentOutline']);
        $t->same('aside', $aside['outlineRoot']);
        $t->same(null, $aside['sectionHeadingText']);
        $t->same(null, $aside['sectionHeadingTag']);
        $t->same(null, $aside['sectionHeadingLevel']);
        $t->same('section', $nestedSection['documentOutline']);
        $t->same('Nested note', $nestedSection['sectionHeadingText']);
        $t->same(4, $nestedSection['sectionHeadingLevel']);

        $t->same('main', $main['documentOutline']);
        $t->same('main', $main['outlineRoot']);
        $t->same(null, $main['sectionHeadingText']);
        $t->same(null, $main['sectionHeadingLevel']);
        $t->same('<article id="story"><header><h1>Primary <em>Title</em></h1></header><section id="chapter"><h2>Chapter</h2><p>Body</p></section></article><nav aria-label="Contents"><div><h3>Navigation</h3></div><a href="#chapter">Chapter</a></nav><aside id="notes"><section id="nested-note"><h4>Nested note</h4></section></aside><main id="main"><p>No title</p></main>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/outline-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html text-level semantic elements for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><abbr title="HyperText Markup Language">HTML</abbr> <dfn title="Review term">term</dfn> <mark>note</mark> '
                . '<code>printf()</code> <kbd>Ctrl+S</kbd> <samp>saved</samp> <var>x</var> <small>fine print</small> '
                . '<sub>2</sub><sup>n</sup> <bdi dir="auto">Review ID</bdi> <bdo dir="rtl">source</bdo> <u>spelling</u> <s>old</s></p>',
            'text-level semantic review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/text-semantics-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $abbr = $paragraph['children'][0];
        $dfn = $paragraph['children'][2];
        $mark = $paragraph['children'][4];
        $code = $paragraph['children'][6];
        $kbd = $paragraph['children'][8];
        $samp = $paragraph['children'][10];
        $var = $paragraph['children'][12];
        $small = $paragraph['children'][14];
        $sub = $paragraph['children'][16];
        $sup = $paragraph['children'][17];
        $bdi = $paragraph['children'][19];
        $bdo = $paragraph['children'][21];
        $u = $paragraph['children'][23];
        $s = $paragraph['children'][25];

        $t->same('p', $paragraph['name']);
        $t->same('HTML term note printf() Ctrl+S saved x fine print 2n Review ID source spelling old', $paragraph['text']);
        $t->same('abbreviation', $abbr['textSemantic']);
        $t->same('HyperText Markup Language', $abbr['abbreviationTitle']);
        $t->same('definition', $dfn['textSemantic']);
        $t->same('term', $dfn['definitionTerm']);
        $t->same('Review term', $dfn['definitionTitle']);
        $t->same('mark', $mark['textSemantic']);
        $t->same('code', $code['textSemantic']);
        $t->same('keyboard-input', $kbd['textSemantic']);
        $t->same('sample-output', $samp['textSemantic']);
        $t->same('variable', $var['textSemantic']);
        $t->same('side-comment', $small['textSemantic']);
        $t->same('subscript', $sub['textSemantic']);
        $t->same('superscript', $sup['textSemantic']);
        $t->same('bidirectional-isolate', $bdi['textSemantic']);
        $t->same('auto', $bdi['textDirection']);
        $t->same('bidirectional-override', $bdo['textSemantic']);
        $t->same('rtl', $bdo['textDirection']);
        $t->same('unarticulated-annotation', $u['textSemantic']);
        $t->same('struck-text', $s['textSemantic']);
        $t->same('<p><abbr title="HyperText Markup Language">HTML</abbr> <dfn title="Review term">term</dfn> <mark>note</mark> <code>printf()</code> <kbd>Ctrl+S</kbd> <samp>saved</samp> <var>x</var> <small>fine print</small> <sub>2</sub><sup>n</sup> <bdi dir="auto">Review ID</bdi> <bdo dir="rtl">source</bdo> <u>spelling</u> <s>old</s></p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/text-semantics-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html ruby annotation provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><ruby id="term">base<rp>(</rp><rt>annotation</rt><rp>)</rp><rtc><rt>alternate</rt><rt>source</rt></rtc><rb>tail</rb><rt>tail-note</rt></ruby></p>',
            'ruby annotation review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/ruby-annotations-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $ruby = $paragraph['children'][0];
        $fallbackOpen = $ruby['children'][1];
        $firstAnnotation = $ruby['children'][2];
        $fallbackClose = $ruby['children'][3];
        $container = $ruby['children'][4];
        $containerAnnotation = $container['children'][0];
        $base = $ruby['children'][5];
        $tailAnnotation = $ruby['children'][6];

        $t->same('p', $paragraph['name']);
        $t->same('base(annotation)alternatesourcetailtail-note', $paragraph['text']);
        $t->same('ruby', $ruby['name']);
        $t->same('ruby', $ruby['ruby']);
        $t->same('term', $ruby['elementId']);
        $t->same('base(annotation)alternatesourcetailtail-note', $ruby['rubyText']);
        $t->same(['base', 'tail'], $ruby['rubyBaseTexts']);
        $t->same(2, $ruby['rubyBaseCount']);
        $t->same(['annotation', 'alternate', 'source', 'tail-note'], $ruby['rubyAnnotationTexts']);
        $t->same(4, $ruby['rubyAnnotationCount']);
        $t->same([
            ['container' => null, 'text' => 'annotation'],
            ['container' => 'rtc', 'text' => 'alternate'],
            ['container' => 'rtc', 'text' => 'source'],
            ['container' => null, 'text' => 'tail-note'],
        ], $ruby['rubyAnnotations']);
        $t->same(['(', ')'], $ruby['rubyFallbackTexts']);
        $t->same(2, $ruby['rubyFallbackCount']);

        $t->same('fallback-parenthesis', $fallbackOpen['rubyPart']);
        $t->same('(', $fallbackOpen['rubyFallbackText']);
        $t->same('annotation', $firstAnnotation['rubyPart']);
        $t->same('annotation', $firstAnnotation['rubyAnnotationText']);
        $t->same(')', $fallbackClose['rubyFallbackText']);
        $t->same('annotation-container', $container['rubyPart']);
        $t->same(['alternate', 'source'], $container['rubyAnnotationTexts']);
        $t->same(2, $container['rubyAnnotationCount']);
        $t->same('annotation', $containerAnnotation['rubyPart']);
        $t->same('alternate', $containerAnnotation['rubyAnnotationText']);
        $t->same('base', $base['rubyPart']);
        $t->same('tail', $base['rubyBaseText']);
        $t->same('tail-note', $tailAnnotation['rubyAnnotationText']);
        $t->same('<p><ruby id="term">base<rp>(</rp><rt>annotation</rt><rp>)</rp><rtc><rt>alternate</rt><rt>source</rt></rtc><rb>tail</rb><rt>tail-note</rt></ruby></p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/ruby-annotations-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html time datetime provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article><time datetime=" 2026-06-11 ">June 11</time>'
                . '<time datetime="2026-06-11 18:45:30Z">Published</time>'
                . '<time datetime="2026-06-11T12:30">Local</time>'
                . '<time datetime="2026-W24">Week 24</time>'
                . '<time datetime="PT2H30M">Duration</time>'
                . '<time>2026-06</time>'
                . '<time datetime="2026-02-30">Bad date</time>'
                . '<time></time></article>',
            'time datetime review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/time-datetime-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $date = $article['children'][0];
        $global = $article['children'][1];
        $local = $article['children'][2];
        $week = $article['children'][3];
        $duration = $article['children'][4];
        $textFallback = $article['children'][5];
        $invalid = $article['children'][6];
        $missing = $article['children'][7];

        $t->same('article', $article['name']);
        $t->same('June 11PublishedLocalWeek 24Duration2026-06Bad date', $article['text']);
        $t->same('time', $date['time']);
        $t->same('June 11', $date['timeText']);
        $t->same(' 2026-06-11 ', $date['timeDatetimeRaw']);
        $t->same('datetime-attribute', $date['timeDatetimeSource']);
        $t->same('2026-06-11', $date['timeDatetime']);
        $t->same('date', $date['timeDatetimeKind']);
        $t->same(true, $date['timeDatetimeValid']);
        $t->same('2026-06-11T18:45:30Z', $global['timeDatetime']);
        $t->same('global-datetime', $global['timeDatetimeKind']);
        $t->same(true, $global['timeDatetimeValid']);
        $t->same('2026-06-11T12:30', $local['timeDatetime']);
        $t->same('local-datetime', $local['timeDatetimeKind']);
        $t->same(true, $local['timeDatetimeValid']);
        $t->same('2026-W24', $week['timeDatetime']);
        $t->same('week', $week['timeDatetimeKind']);
        $t->same(true, $week['timeDatetimeValid']);
        $t->same('PT2H30M', $duration['timeDatetime']);
        $t->same('duration', $duration['timeDatetimeKind']);
        $t->same(true, $duration['timeDatetimeValid']);
        $t->same('2026-06', $textFallback['timeText']);
        $t->same(null, $textFallback['timeDatetimeRaw']);
        $t->same('text', $textFallback['timeDatetimeSource']);
        $t->same('2026-06', $textFallback['timeDatetime']);
        $t->same('month', $textFallback['timeDatetimeKind']);
        $t->same(true, $textFallback['timeDatetimeValid']);
        $t->same('2026-02-30', $invalid['timeDatetimeRaw']);
        $t->same('datetime-attribute', $invalid['timeDatetimeSource']);
        $t->same(null, $invalid['timeDatetime']);
        $t->same('invalid', $invalid['timeDatetimeKind']);
        $t->same(false, $invalid['timeDatetimeValid']);
        $t->same('', $missing['timeText']);
        $t->same('missing', $missing['timeDatetimeSource']);
        $t->same('missing', $missing['timeDatetimeKind']);
        $t->same(false, $missing['timeDatetimeValid']);
        $t->same('<article><time datetime=" 2026-06-11 ">June 11</time><time datetime="2026-06-11 18:45:30Z">Published</time><time datetime="2026-06-11T12:30">Local</time><time datetime="2026-W24">Week 24</time><time datetime="PT2H30M">Duration</time><time>2026-06</time><time datetime="2026-02-30">Bad date</time><time></time></article>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/time-datetime-review.html', $document->children[0]->attr('part'));
    },
    'serializes entities comments and boolean attributes for HTML blocks' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            'Text&nbsp;<span title="A &quot;quote&quot; &amp; source">source &lt;em&gt;</span><!--review--><input checked>',
            'entity HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same("Text\u{00A0}", $summary[0]['text']);
        $t->same('span', $summary[1]['name']);
        $t->same(['title' => 'A "quote" & source'], $summary[1]['attributes']);
        $t->same('source <em>', $summary[1]['text']);
        $t->same('comment', $summary[2]['type']);
        $t->same('review', $summary[2]['text']);
        $t->same('input', $summary[3]['name']);
        $t->same(['checked' => 'checked'], $summary[3]['attributes']);
        $t->same("Text\u{00A0}<span title=\"A &quot;quote&quot; &amp; source\">source &lt;em&gt;</span><!--review--><input checked>", $html);
    },
    'decodes bounded html5 math spacing references before raw block serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p data-math="&af;&it;&ic;">f&ApplyFunction;g&InvisibleTimes;h&MediumSpace;comma&InvisibleComma;zero&ZeroWidthSpace;neg&NegativeThinSpace;</p>'
                . '<p data-spacing="&NonBreakingSpace;&ThinSpace;&ThickSpace;&VeryThinSpace;&hairsp;">Spaces: non&NonBreakingSpace;thin&ThinSpace;alias&thinsp;thick&ThickSpace;very&VeryThinSpace;hair&hairsp;neg&NegativeVeryThinSpace;&NegativeMediumSpace;&NegativeThickSpace;</p>',
            'math spacing entity HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same(2, count($summary));
        $t->same('p', $summary[0]['name']);
        $t->same(['data-math' => "\u{2061}\u{2062}\u{2063}"], $summary[0]['attributes']);
        $t->same("f\u{2061}g\u{2062}h\u{205F}comma\u{2063}zero\u{200B}neg\u{200B}", $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same("f\u{2061}g\u{2062}h\u{205F}comma\u{2063}zero\u{200B}neg\u{200B}", $summary[0]['children'][0]['text']);
        $t->same('p', $summary[1]['name']);
        $t->same(['data-spacing' => "\u{00A0}\u{2009}\u{205F}\u{200A}\u{200A}\u{200A}"], $summary[1]['attributes']);
        $t->same("Spaces: non\u{00A0}thin\u{2009}alias\u{2009}thick\u{205F}\u{200A}very\u{200A}hair\u{200A}neg\u{200B}\u{200B}\u{200B}", $summary[1]['text']);
        $t->same("Spaces: non\u{00A0}thin\u{2009}alias\u{2009}thick\u{205F}\u{200A}very\u{200A}hair\u{200A}neg\u{200B}\u{200B}\u{200B}", $summary[1]['children'][0]['text']);
        $t->same('<p data-math="' . "\u{2061}\u{2062}\u{2063}" . '">f' . "\u{2061}" . 'g' . "\u{2062}" . 'h' . "\u{205F}" . 'comma' . "\u{2063}" . 'zero' . "\u{200B}" . 'neg' . "\u{200B}" . '</p><p data-spacing="' . "\u{00A0}\u{2009}\u{205F}\u{200A}\u{200A}\u{200A}" . '">Spaces: non' . "\u{00A0}" . 'thin' . "\u{2009}" . 'alias' . "\u{2009}" . 'thick' . "\u{205F}\u{200A}" . 'very' . "\u{200A}" . 'hair' . "\u{200A}" . 'neg' . "\u{200B}\u{200B}\u{200B}" . '</p>', $html);
        $t->true(!str_contains($html, '&amp;ApplyFunction;'), 'Expected ApplyFunction to decode before raw HTML handoff');
        $t->true(!str_contains($html, '&amp;ZeroWidthSpace;'), 'Expected ZeroWidthSpace to decode before raw HTML handoff');
        $t->true(!str_contains($html, '&amp;NonBreakingSpace;'), 'Expected NonBreakingSpace to decode before raw HTML handoff');
        $t->true(!str_contains($html, '&amp;ThickSpace;'), 'Expected ThickSpace to decode before raw HTML handoff');
        $t->true(!str_contains($html, '&amp;NegativeMediumSpace;'), 'Expected negative spacing aliases to decode before raw HTML handoff');
    },
    'decodes safe semicolon html5 named references before raw block serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p data-math="&NotEqualTilde;&DoubleLongRightArrow;&realine;">'
                . '&CounterClockwiseContourIntegral;&LeftTriangleBar;&NotNestedGreaterGreater;&angmsdaa;&bnequiv;&nparsl;&suphsol;&rarrfs;&nGg;&gesles;&lesg;&angzarr;'
                . '</p><p data-core="&quot;&amp;&lt;">core &quot;&amp;&lt;</p>'
                . '<script type="application/json">{"literal":"&NotEqualTilde;"}</script>',
            'broad html5 named entity fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $attribute = "\u{2242}\u{0338}\u{27F9}\u{211B}";
        $text = "\u{2233}\u{29CF}\u{2AA2}\u{0338}\u{29A8}\u{2261}\u{20E5}\u{2AFD}\u{20E5}\u{27C9}\u{291E}\u{22D9}\u{0338}\u{2A94}\u{22DA}\u{FE00}\u{237C}";

        $t->same($attribute, $summary[0]['attributes']['data-math']);
        $t->same($text, $summary[0]['text']);
        $t->same(['data-core' => '"&<'], $summary[1]['attributes']);
        $t->same('core "&<', $summary[1]['text']);
        $t->same('{"literal":"&NotEqualTilde;"}', $summary[2]['text']);
        $t->same('<p data-math="' . $attribute . '">' . $text . '</p><p data-core="&quot;&amp;&lt;">core "&amp;&lt;</p><script type="application/json">{"literal":"&NotEqualTilde;"}</script>', $html);
        foreach (['NotEqualTilde', 'CounterClockwiseContourIntegral', 'NotNestedGreaterGreater', 'bnequiv', 'angzarr'] as $entityName) {
            $t->true(!str_contains($html, '&amp;' . $entityName . ';'), 'Expected HTML5 reference ' . $entityName . ' to decode before raw HTML handoff');
        }
        $t->contains('{"literal":"&NotEqualTilde;"}', $html);
    },
    'normalizes unsafe html comment boundaries before raw block serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<!--review---><p>Imported comment boundary</p><!--source -- boundary--><!--triple---tail--->',
            'comment boundary HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('comment', $summary[0]['type']);
        $t->same('review-', $summary[0]['text']);
        $t->same('p', $summary[1]['name']);
        $t->same('comment', $summary[2]['type']);
        $t->same('source -- boundary', $summary[2]['text']);
        $t->same('comment', $summary[3]['type']);
        $t->same('triple---tail-', $summary[3]['text']);
        $t->same('<!--review- --><p>Imported comment boundary</p><!--source - - boundary--><!--triple- - -tail- -->', $html);
        $t->true(!str_contains($html, '--->'), 'Expected trailing hyphen comments to be padded before the closing delimiter');
        $t->true(!str_contains($html, 'source -- boundary'), 'Expected interior comment delimiters to be split before serialization');
        $t->true(!str_contains($html, 'triple---tail'), 'Expected overlapping comment delimiters to be split before serialization');
    },
    'serializes raw text elements and expanded html5 boolean attributes' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script defer src="review.js">if (a < b && c > d) { window.review = "&"; }</script>'
                . '<style disabled>.legacy > .target::before { content: "&"; }</style>',
            'raw text HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('script', $summary[0]['name']);
        $t->same(['defer' => 'defer', 'src' => 'review.js'], $summary[0]['attributes']);
        $t->same('if (a < b && c > d) { window.review = "&"; }', $summary[0]['text']);
        $t->same('style', $summary[1]['name']);
        $t->same(['disabled' => 'disabled'], $summary[1]['attributes']);
        $t->same('.legacy > .target::before { content: "&"; }', $summary[1]['text']);
        $t->same('<script defer src="review.js">if (a < b && c > d) { window.review = "&"; }</script><style disabled>.legacy > .target::before { content: "&"; }</style>', $html);
    },
    'preflights html declarations outside protected raw text serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script type="application/json">{"doctype":"<!DOCTYPE html>","pi":"<?review href=\"file\"?>"}</script>'
                . '<style>body:before{content:"<!ENTITY reviewer SYSTEM file>"}</style>'
                . '<textarea><!ENTITY reviewer SYSTEM "file:///etc/passwd"></textarea>'
                . '<template><?xml-stylesheet href="file"?></template>'
                . '<iframe><!DOCTYPE html></iframe>',
            'raw text declaration HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('script', $summary[0]['name']);
        $t->same('{"doctype":"<!DOCTYPE html>","pi":"<?review href=\"file\"?>"}', $summary[0]['text']);
        $t->same('body:before{content:"<!ENTITY reviewer SYSTEM file>"}', $summary[1]['text']);
        $t->same('<!ENTITY reviewer SYSTEM "file:///etc/passwd">', $summary[2]['text']);
        $t->same('<?xml-stylesheet href="file"?>', $summary[3]['text']);
        $t->same('<!DOCTYPE html>', $summary[4]['text']);
        $t->same(
            '<script type="application/json">{"doctype":"<!DOCTYPE html>","pi":"<?review href=\"file\"?>"}</script>'
                . '<style>body:before{content:"<!ENTITY reviewer SYSTEM file>"}</style>'
                . '<textarea>&lt;!ENTITY reviewer SYSTEM "file:///etc/passwd"&gt;</textarea>'
                . '<template>&lt;?xml-stylesheet href="file"?&gt;</template>'
                . '<iframe>&lt;!DOCTYPE html&gt;</iframe>',
            $html
        );
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<p>bad</p><!DOCTYPE html>', 'unsafe HTML fragment'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<p><?review href="file"?></p>', 'unsafe HTML fragment'));
    },
    'summarizes html select option state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<select name="review-status" multiple><option value="draft">Draft<option selected value="review">Review<optgroup label="Archive" disabled><option value="a1">Archive One<option selected>Archive Two</optgroup></select><p>after</p>',
            'select review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('select', $summary[0]['name']);
        $t->same('select', $summary[0]['formControl']);
        $t->same(['multiple' => 'multiple', 'name' => 'review-status'], $summary[0]['attributes']);
        $t->same(['review', 'Archive Two'], $summary[0]['selectedValues']);
        $t->same([
            ['value' => 'draft', 'label' => 'Draft', 'text' => 'Draft', 'selected' => false, 'disabled' => false],
            ['value' => 'review', 'label' => 'Review', 'text' => 'Review', 'selected' => true, 'disabled' => false],
            ['value' => 'a1', 'label' => 'Archive One', 'text' => 'Archive One', 'selected' => false, 'disabled' => true, 'group' => 'Archive', 'groupDisabled' => true],
            ['value' => 'Archive Two', 'label' => 'Archive Two', 'text' => 'Archive Two', 'selected' => true, 'disabled' => true, 'group' => 'Archive', 'groupDisabled' => true],
        ], $summary[0]['selectOptions']);
        $t->same('<select multiple name="review-status"><option value="draft">Draft</option><option selected value="review">Review</option><optgroup disabled label="Archive"><option value="a1">Archive One</option><option selected>Archive Two</option></optgroup></select><p>after</p>', $html);
    },
    'summarizes html input textarea and button state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="review-form"><input name="title" value="Draft &amp; Source"><input type="checkbox" name="publish" checked disabled><textarea name="notes" readonly>Reviewer &amp; editor' . "\n" . 'note</textarea><button name="action" value="publish">Publish <strong>now</strong></button><button type="reset" disabled>Clear</button></form>',
            'form control review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $form = $summary[0];
        $textInput = $form['children'][0];
        $checkbox = $form['children'][1];
        $textarea = $form['children'][2];
        $submitButton = $form['children'][3];
        $resetButton = $form['children'][4];

        $t->same('form', $form['name']);
        $t->same(['id' => 'review-form'], $form['attributes']);
        $t->same('input', $textInput['formControl']);
        $t->same('text', $textInput['inputType']);
        $t->same('Draft & Source', $textInput['value']);
        $t->same(false, $textInput['checked']);
        $t->same(false, $textInput['disabled']);
        $t->same('input', $checkbox['formControl']);
        $t->same('checkbox', $checkbox['inputType']);
        $t->same('', $checkbox['value']);
        $t->same(true, $checkbox['checked']);
        $t->same(true, $checkbox['disabled']);
        $t->same('textarea', $textarea['formControl']);
        $t->same("Reviewer & editor\nnote", $textarea['value']);
        $t->same(false, $textarea['disabled']);
        $t->same(true, $textarea['readonly']);
        $t->same('button', $submitButton['formControl']);
        $t->same('submit', $submitButton['buttonType']);
        $t->same('publish', $submitButton['value']);
        $t->same('Publish now', $submitButton['label']);
        $t->same(false, $submitButton['disabled']);
        $t->same('button', $resetButton['formControl']);
        $t->same('reset', $resetButton['buttonType']);
        $t->same('', $resetButton['value']);
        $t->same('Clear', $resetButton['label']);
        $t->same(true, $resetButton['disabled']);
        $t->same('<form id="review-form"><input name="title" value="Draft &amp; Source"><input checked disabled name="publish" type="checkbox"><textarea name="notes" readonly>Reviewer &amp; editor' . "\n" . 'note</textarea><button name="action" value="publish">Publish <strong>now</strong></button><button disabled type="reset">Clear</button></form>', $html);
    },
    'summarizes html form submission state and submitter overrides for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="remote-review" action="https://forms.example.invalid/submit" method="POST" enctype="multipart/form-data" target="_blank" autocomplete="off" accept-charset="UTF-8 ISO-8859-1" novalidate><input name="title" value="Packet"><input type="image" src="submit.png" formaction="/image-submit" formmethod="POST" formenctype="multipart/form-data" formtarget="_parent" formnovalidate><button type="submit" formaction="/local-submit" formmethod="dialog" formenctype="text/plain" formtarget="_self" formnovalidate>Send</button></form>'
                . '<form id="invalid-method" method="TRACE" enctype="application/json" autocomplete="maybe"><button>Default</button></form>',
            'form submission review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $form = $summary[0];
        $imageSubmitter = $form['children'][1];
        $buttonSubmitter = $form['children'][2];
        $fallbackForm = $summary[1];
        $defaultButton = $fallbackForm['children'][0];

        $t->same('form', $form['name']);
        $t->same('form', $form['formSubmission']);
        $t->same('https://forms.example.invalid/submit', $form['action']);
        $t->same('post', $form['method']);
        $t->same('multipart/form-data', $form['enctype']);
        $t->same('_blank', $form['target']);
        $t->same('off', $form['autocomplete']);
        $t->same(true, $form['novalidate']);
        $t->same('UTF-8 ISO-8859-1', $form['acceptCharsetRaw']);
        $t->same(['UTF-8', 'ISO-8859-1'], $form['acceptCharsets']);
        $t->same('image', $imageSubmitter['inputType']);
        $t->same([
            'form' => null,
            'formAction' => '/image-submit',
            'formMethod' => 'post',
            'formEnctype' => 'multipart/form-data',
            'formTarget' => '_parent',
            'formNoValidate' => true,
        ], $imageSubmitter['submitter']);
        $t->same('submit', $buttonSubmitter['buttonType']);
        $t->same([
            'form' => null,
            'formAction' => '/local-submit',
            'formMethod' => 'dialog',
            'formEnctype' => 'text/plain',
            'formTarget' => '_self',
            'formNoValidate' => true,
        ], $buttonSubmitter['submitter']);
        $t->same('get', $fallbackForm['method']);
        $t->same('application/x-www-form-urlencoded', $fallbackForm['enctype']);
        $t->same('on', $fallbackForm['autocomplete']);
        $t->same(false, $fallbackForm['novalidate']);
        $t->same(null, $fallbackForm['acceptCharsetRaw']);
        $t->same([], $fallbackForm['acceptCharsets']);
        $t->same([
            'form' => null,
            'formAction' => null,
            'formMethod' => null,
            'formEnctype' => null,
            'formTarget' => null,
            'formNoValidate' => false,
        ], $defaultButton['submitter']);
        $t->same('<form accept-charset="UTF-8 ISO-8859-1" action="https://forms.example.invalid/submit" autocomplete="off" enctype="multipart/form-data" id="remote-review" method="POST" novalidate target="_blank"><input name="title" value="Packet"><input formaction="/image-submit" formenctype="multipart/form-data" formmethod="POST" formnovalidate formtarget="_parent" src="submit.png" type="image"><button formaction="/local-submit" formenctype="text/plain" formmethod="dialog" formnovalidate formtarget="_self" type="submit">Send</button></form><form autocomplete="maybe" enctype="application/json" id="invalid-method" method="TRACE"><button>Default</button></form>', $html);
    },
    'summarizes html output control state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="calc-form"><input id="source-a" name="a" value="5"><button id="source-b" type="button">Add</button><label for="checksum">Checksum</label><label>Total <output id="checksum" name="checksum" for="source-a  source-b missing">Ready <strong>hash</strong></output></label></form>',
            'output control review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $form = $summary[0];
        $output = $form['children'][3]['children'][1];

        $t->same('output', $output['name']);
        $t->same('output', $output['formControl']);
        $t->same(['Checksum', 'Total Ready hash'], $output['labels']);
        $t->same('Ready hash', $output['text']);
        $t->same('Ready hash', $output['value']);
        $t->same('source-a  source-b missing', $output['forRaw']);
        $t->same(['source-a', 'source-b', 'missing'], $output['forIds']);
        $t->same('<form id="calc-form"><input id="source-a" name="a" value="5"><button id="source-b" type="button">Add</button><label for="checksum">Checksum</label><label>Total <output for="source-a  source-b missing" id="checksum" name="checksum">Ready <strong>hash</strong></output></label></form>', $html);
    },
    'summarizes html form labels datalist and inherited disabled state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="import-form"><label for="format">Format</label><input id="format" name="format" list="format-options" required placeholder="Choose format"><datalist id="format-options"><option value="docx" label="Word"></option><option value="epub">EPUB</option><option>ODT</option></datalist><fieldset disabled><legend>Batch <button id="legend-action">Keep enabled</button></legend><label>Confirm <input id="confirm" name="confirm" type="checkbox" checked></label><select id="state" name="state" required><option value="draft">Draft</option></select><textarea id="notes" name="notes" placeholder="Reviewer note">Ready</textarea><button id="submit" type="submit" name="save" value="1">Save</button></fieldset></form>',
            'form label and datalist review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $form = $summary[0];
        $formatInput = $form['children'][1];
        $datalist = $form['children'][2];
        $fieldset = $form['children'][3];
        $legendButton = $fieldset['children'][0]['children'][1];
        $confirmInput = $fieldset['children'][1]['children'][1];
        $stateSelect = $fieldset['children'][2];
        $notes = $fieldset['children'][3];
        $submitButton = $fieldset['children'][4];
        $expectedOptions = [
            ['value' => 'docx', 'label' => 'Word', 'text' => '', 'disabled' => false],
            ['value' => 'epub', 'label' => 'EPUB', 'text' => 'EPUB', 'disabled' => false],
            ['value' => 'ODT', 'label' => 'ODT', 'text' => 'ODT', 'disabled' => false],
        ];

        $t->same('form', $form['name']);
        $t->same('input', $formatInput['formControl']);
        $t->same(['Format'], $formatInput['labels']);
        $t->same(true, $formatInput['required']);
        $t->same('Choose format', $formatInput['placeholder']);
        $t->same(false, $formatInput['effectiveDisabled']);
        $t->same('format-options', $formatInput['list']);
        $t->same($expectedOptions, $formatInput['datalistOptions']);
        $t->same('datalist', $datalist['formControl']);
        $t->same($expectedOptions, $datalist['datalistOptions']);
        $t->same(['disabled' => 'disabled'], $fieldset['attributes']);
        $t->same('button', $legendButton['formControl']);
        $t->same(false, $legendButton['effectiveDisabled']);
        $t->same('input', $confirmInput['formControl']);
        $t->same(['Confirm'], $confirmInput['labels']);
        $t->same(true, $confirmInput['checked']);
        $t->same(false, $confirmInput['disabled']);
        $t->same(true, $confirmInput['effectiveDisabled']);
        $t->same('select', $stateSelect['formControl']);
        $t->same(true, $stateSelect['required']);
        $t->same(true, $stateSelect['effectiveDisabled']);
        $t->same('textarea', $notes['formControl']);
        $t->same('Reviewer note', $notes['placeholder']);
        $t->same(true, $notes['effectiveDisabled']);
        $t->same('button', $submitButton['formControl']);
        $t->same(true, $submitButton['effectiveDisabled']);
        $t->same('<form id="import-form"><label for="format">Format</label><input id="format" list="format-options" name="format" placeholder="Choose format" required><datalist id="format-options"><option label="Word" value="docx"></option><option value="epub">EPUB</option><option>ODT</option></datalist><fieldset disabled><legend>Batch <button id="legend-action">Keep enabled</button></legend><label>Confirm <input checked id="confirm" name="confirm" type="checkbox"></label><select id="state" name="state" required><option value="draft">Draft</option></select><textarea id="notes" name="notes" placeholder="Reviewer note">Ready</textarea><button id="submit" name="save" type="submit" value="1">Save</button></fieldset></form>', $html);
    },
    'summarizes html progress and meter state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<label for="upload-progress">Upload</label><progress id="upload-progress" value="3" max="4">75%</progress><progress id="pending">Pending</progress><label>Quality <meter id="quality" value="0.82" min="0" max="1" low="0.4" high="0.9" optimum="0.95">82%</meter></label><meter id="clamped" value="12" min="2" max="10">Too high</meter>',
            'progress meter review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $progress = $summary[1];
        $pending = $summary[2];
        $quality = $summary[3]['children'][1];
        $clamped = $summary[4];

        $t->same('progress', $progress['measurement']);
        $t->same(['Upload'], $progress['labels']);
        $t->same(3.0, $progress['value']);
        $t->same(4.0, $progress['max']);
        $t->same(0.75, $progress['position']);
        $t->same(false, $progress['indeterminate']);
        $t->same(null, $pending['value']);
        $t->same(null, $pending['position']);
        $t->same(true, $pending['indeterminate']);
        $t->same('meter', $quality['measurement']);
        $t->same(['Quality 82%'], $quality['labels']);
        $t->same(0.82, $quality['value']);
        $t->same(0.0, $quality['min']);
        $t->same(1.0, $quality['max']);
        $t->same(0.4, $quality['low']);
        $t->same(0.9, $quality['high']);
        $t->same(0.95, $quality['optimum']);
        $t->same('meter', $clamped['measurement']);
        $t->same(10.0, $clamped['value']);
        $t->same(2.0, $clamped['min']);
        $t->same(10.0, $clamped['max']);
        $t->same('<label for="upload-progress">Upload</label><progress id="upload-progress" max="4" value="3">75%</progress><progress id="pending">Pending</progress><label>Quality <meter high="0.9" id="quality" low="0.4" max="1" min="0" optimum="0.95" value="0.82">82%</meter></label><meter id="clamped" max="10" min="2" value="12">Too high</meter>', $html);
    },
    'summarizes html disclosure state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<details id="packet" open><summary>Package <span>review</span></summary><p>Body</p></details>'
                . '<details id="missing-summary"><p>No summary</p></details>',
            'disclosure review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $details = $summary[0];
        $detailsSummary = $details['children'][0];
        $missingSummary = $summary[1];

        $t->same('details', $details['name']);
        $t->same('details', $details['disclosure']);
        $t->same(true, $details['open']);
        $t->same('Package review', $details['summaryText']);
        $t->same(1, $details['summaryElementCount']);
        $t->same('summary', $detailsSummary['name']);
        $t->same('summary', $detailsSummary['disclosure']);
        $t->same('Package review', $detailsSummary['label']);
        $t->same(false, $missingSummary['open']);
        $t->same(null, $missingSummary['summaryText']);
        $t->same(0, $missingSummary['summaryElementCount']);
        $t->same('<details id="packet" open><summary>Package <span>review</span></summary><p>Body</p></details><details id="missing-summary"><p>No summary</p></details>', $html);
    },
    'summarizes html dialog and popover state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<dialog id="confirm" open popover="manual" aria-modal="true"><form method="dialog"><button value="ok">OK</button><button popovertarget="details-popover" popovertargetaction="show">More</button></form></dialog>'
                . '<aside id="details-popover" popover="auto">Extra</aside>'
                . '<button popovertarget="bad target" popovertargetaction="dismiss">Bad</button>',
            'dialog popover review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $dialog = $summary[0];
        $form = $dialog['children'][0];
        $okButton = $form['children'][0];
        $moreButton = $form['children'][1];
        $popover = $summary[1];
        $invalidButton = $summary[2];

        $t->same('dialog', $dialog['name']);
        $t->same('dialog', $dialog['dialog']);
        $t->same(true, $dialog['dialogOpen']);
        $t->same('OKMore', $dialog['dialogText']);
        $t->same('manual', $dialog['popoverRaw']);
        $t->same('manual', $dialog['popoverState']);
        $t->same(true, $dialog['popoverValid']);
        $t->same(['aria-modal' => 'true'], $dialog['ariaAttributes']);
        $t->same('form', $form['formSubmission']);
        $t->same('dialog', $form['method']);
        $t->same('button', $okButton['formControl']);
        $t->same('button', $moreButton['formControl']);
        $t->same('details-popover', $moreButton['popoverTargetRaw']);
        $t->same('details-popover', $moreButton['popoverTarget']);
        $t->same(true, $moreButton['popoverTargetValid']);
        $t->same('show', $moreButton['popoverTargetActionRaw']);
        $t->same('show', $moreButton['popoverTargetAction']);
        $t->same(true, $moreButton['popoverTargetActionValid']);
        $t->same('auto', $popover['popoverRaw']);
        $t->same('auto', $popover['popoverState']);
        $t->same(true, $popover['popoverValid']);
        $t->same('bad target', $invalidButton['popoverTargetRaw']);
        $t->same(null, $invalidButton['popoverTarget']);
        $t->same(false, $invalidButton['popoverTargetValid']);
        $t->same('dismiss', $invalidButton['popoverTargetActionRaw']);
        $t->same(null, $invalidButton['popoverTargetAction']);
        $t->same(false, $invalidButton['popoverTargetActionValid']);
        $t->same('<dialog aria-modal="true" id="confirm" open popover="manual"><form method="dialog"><button value="ok">OK</button><button popovertarget="details-popover" popovertargetaction="show">More</button></form></dialog><aside id="details-popover" popover="auto">Extra</aside><button popovertarget="bad target" popovertargetaction="dismiss">Bad</button>', $html);
    },
    'summarizes html dialog state and method dialog controls for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<dialog id="review-dialog" open aria-labelledby="dialog-title"><h2 id="dialog-title">Review packet</h2>'
                . '<form id="review-close" method="dialog" action="/ignored"><button name="decision" value="approve">Approve</button>'
                . '<button value="cancel" formmethod="post">Cancel remotely</button><input type="submit" name="close" value="close"></form><p>Body</p></dialog>'
                . '<dialog id="closed"><form method="POST"><button value="noop">No close</button></form></dialog>',
            'dialog state review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/dialog-state-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $dialog = $summary[0];
        $dialogForm = $dialog['dialogMethodForms'][0];
        $approve = $dialogForm['submitters'][0];
        $remoteCancel = $dialogForm['submitters'][1];
        $inputClose = $dialogForm['submitters'][2];
        $closed = $summary[1];

        $t->same('dialog', $dialog['name']);
        $t->same('dialog', $dialog['dialog']);
        $t->same(true, $dialog['dialogOpen']);
        $t->same('open', $dialog['dialogState']);
        $t->same('Review packet', $dialog['dialogHeadingText']);
        $t->same('h2', $dialog['dialogHeadingTag']);
        $t->same(2, $dialog['dialogHeadingLevel']);
        $t->same(1, $dialog['dialogMethodFormCount']);
        $t->same('review-close', $dialogForm['id']);
        $t->same('dialog', $dialogForm['methodRaw']);
        $t->same('/ignored', $dialogForm['action']);
        $t->same(['approve', 'close'], $dialog['dialogCloseValues']);

        $t->same('button', $approve['tag']);
        $t->same('decision', $approve['name']);
        $t->same('approve', $approve['value']);
        $t->same('Approve', $approve['label']);
        $t->same('dialog', $approve['effectiveFormMethod']);
        $t->same(true, $approve['dialogCloses']);
        $t->same('post', $remoteCancel['formMethod']);
        $t->same('post', $remoteCancel['effectiveFormMethod']);
        $t->same(false, $remoteCancel['dialogCloses']);
        $t->same('input', $inputClose['tag']);
        $t->same('submit', $inputClose['type']);
        $t->same('close', $inputClose['name']);
        $t->same('close', $inputClose['value']);
        $t->same(false, $inputClose['effectiveDisabled']);

        $t->same('closed', $closed['elementId']);
        $t->same(false, $closed['dialogOpen']);
        $t->same('closed', $closed['dialogState']);
        $t->same(0, $closed['dialogMethodFormCount']);
        $t->same([], $closed['dialogCloseValues']);
        $t->same('<dialog aria-labelledby="dialog-title" id="review-dialog" open><h2 id="dialog-title">Review packet</h2><form action="/ignored" id="review-close" method="dialog"><button name="decision" value="approve">Approve</button><button formmethod="post" value="cancel">Cancel remotely</button><input name="close" type="submit" value="close"></form><p>Body</p></dialog><dialog id="closed"><form method="POST"><button value="noop">No close</button></form></dialog>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/dialog-state-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html insertion and deletion revision metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><ins cite="./changes/insert.html" datetime="2026-06-11 12:30Z">Inserted <em>text</em></ins>'
                . '<del cite="https://example.test/revision#old" datetime="2026-06-10T09:15:30-0500">Removed</del>'
                . '<ins datetime="2026-02-30">Invalid date</ins></p>',
            'revision review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $paragraph = $summary[0];
        $inserted = $paragraph['children'][0];
        $deleted = $paragraph['children'][1];
        $invalid = $paragraph['children'][2];

        $t->same('p', $paragraph['name']);
        $t->same('ins', $inserted['name']);
        $t->same('insertion', $inserted['revision']);
        $t->same('ins', $inserted['revisionTag']);
        $t->same('./changes/insert.html', $inserted['revisionCite']);
        $t->same('2026-06-11 12:30Z', $inserted['revisionDatetimeRaw']);
        $t->same('2026-06-11T12:30Z', $inserted['revisionDatetime']);
        $t->same('global-datetime', $inserted['revisionDatetimeKind']);
        $t->same(true, $inserted['revisionDatetimeValid']);
        $t->same('Inserted text', $inserted['text']);
        $t->same('em', $inserted['children'][1]['name']);
        $t->same('del', $deleted['name']);
        $t->same('deletion', $deleted['revision']);
        $t->same('https://example.test/revision#old', $deleted['revisionCite']);
        $t->same('2026-06-10T09:15:30-05:00', $deleted['revisionDatetime']);
        $t->same('global-datetime', $deleted['revisionDatetimeKind']);
        $t->same(true, $deleted['revisionDatetimeValid']);
        $t->same('ins', $invalid['name']);
        $t->same('2026-02-30', $invalid['revisionDatetimeRaw']);
        $t->same(null, $invalid['revisionDatetime']);
        $t->same('invalid', $invalid['revisionDatetimeKind']);
        $t->same(false, $invalid['revisionDatetimeValid']);
        $t->same(
            '<p><ins cite="./changes/insert.html" datetime="2026-06-11 12:30Z">Inserted <em>text</em></ins><del cite="https://example.test/revision#old" datetime="2026-06-10T09:15:30-0500">Removed</del><ins datetime="2026-02-30">Invalid date</ins></p>',
            $html
        );
    },
    'summarizes html quote citation provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<blockquote cite="https://example.test/source#quote"><p>Quoted <em>source</em></p></blockquote>'
                . '<p>Inline <q cite="./inline.html">quoted <strong>claim</strong></q> and <q>uncited</q> from <cite>Packet Title</cite>.</p>',
            'quote citation review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $blockquote = $summary[0];
        $paragraph = $summary[1];
        $inlineQuote = $paragraph['children'][1];
        $uncitedQuote = $paragraph['children'][3];
        $citedWork = $paragraph['children'][5];

        $t->same('blockquote', $blockquote['name']);
        $t->same('block', $blockquote['quote']);
        $t->same('blockquote', $blockquote['quoteTag']);
        $t->same('https://example.test/source#quote', $blockquote['quoteCite']);
        $t->same('Quoted source', $blockquote['quoteText']);
        $t->same('p', $blockquote['children'][0]['name']);
        $t->same('q', $inlineQuote['name']);
        $t->same('inline', $inlineQuote['quote']);
        $t->same('q', $inlineQuote['quoteTag']);
        $t->same('./inline.html', $inlineQuote['quoteCite']);
        $t->same('quoted claim', $inlineQuote['quoteText']);
        $t->same('strong', $inlineQuote['children'][1]['name']);
        $t->same('q', $uncitedQuote['name']);
        $t->same(null, $uncitedQuote['quoteCite']);
        $t->same('uncited', $uncitedQuote['quoteText']);
        $t->same('cite', $citedWork['name']);
        $t->same('cite', $citedWork['citedWork']);
        $t->same('Packet Title', $citedWork['citedWorkText']);
        $t->same(
            '<blockquote cite="https://example.test/source#quote"><p>Quoted <em>source</em></p></blockquote><p>Inline <q cite="./inline.html">quoted <strong>claim</strong></q> and <q>uncited</q> from <cite>Packet Title</cite>.</p>',
            $html
        );
    },
    'summarizes html quote attribution and cite text rollups for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<blockquote id="packet-quote" cite=" https://example.test/review#source "><p>Imported <q cite=" ./inline.html ">inline <cite>Manual</cite></q> note.</p><footer>Source <cite>Reviewer Handbook</cite></footer></blockquote>'
                . '<p>Standalone <cite data-review="work">Packet Guide</cite></p>',
            'quote attribution review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $blockquote = $summary[0];
        $inlineQuote = $blockquote['children'][0]['children'][1];
        $inlineCitation = $inlineQuote['children'][1];
        $footer = $blockquote['children'][1];
        $footerCitation = $footer['children'][1];
        $standaloneCitation = $summary[1]['children'][1];

        $t->same('block', $blockquote['quote']);
        $t->same(' https://example.test/review#source ', $blockquote['quoteCite']);
        $t->same(' https://example.test/review#source ', $blockquote['quoteCiteRaw']);
        $t->same('https://example.test/review#source', $blockquote['quoteCiteNormalized']);
        $t->same('Imported inline Manual note.Source Reviewer Handbook', $blockquote['quoteText']);
        $t->same('Source Reviewer Handbook', $blockquote['attributionText']);
        $t->same(['Manual', 'Reviewer Handbook'], $blockquote['citationTexts']);
        $t->same(2, $blockquote['citationCount']);
        $t->same('footer', $footer['name']);

        $t->same('inline', $inlineQuote['quote']);
        $t->same(' ./inline.html ', $inlineQuote['quoteCiteRaw']);
        $t->same('./inline.html', $inlineQuote['quoteCiteNormalized']);
        $t->same('inline Manual', $inlineQuote['quoteText']);
        $t->same(null, $inlineQuote['attributionText']);
        $t->same(['Manual'], $inlineQuote['citationTexts']);
        $t->same(1, $inlineQuote['citationCount']);

        $t->same('cite', $inlineCitation['citedWork']);
        $t->same('Manual', $inlineCitation['citedWorkText']);
        $t->same('cite', $inlineCitation['citation']);
        $t->same('Manual', $inlineCitation['citationText']);
        $t->same('Reviewer Handbook', $footerCitation['citationText']);
        $t->same('Packet Guide', $standaloneCitation['citationText']);
        $t->same(['review' => 'work'], $standaloneCitation['dataset']);
        $t->same('<blockquote cite=" https://example.test/review#source " id="packet-quote"><p>Imported <q cite=" ./inline.html ">inline <cite>Manual</cite></q> note.</p><footer>Source <cite>Reviewer Handbook</cite></footer></blockquote><p>Standalone <cite data-review="work">Packet Guide</cite></p>', $html);
    },
    'summarizes html media resource state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<video id="preview" controls muted loop poster="cover.jpg" preload="metadata"><source src="movie.webm" type="video/webm"><source src="movie.mp4" type="video/mp4" media="(min-width: 40em)"><track default kind="captions" label="English" srclang="en" src="captions.vtt">Fallback <a href="movie.mp4">download</a></video>'
                . '<audio id="sample" autoplay preload="bogus" src="sample.mp3"><source src="sample.ogg" type="audio/ogg"><track kind="chapters" src="chapters.vtt" srclang="en" label="Chapters">Audio fallback</audio>',
            'media resource review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $video = $summary[0];
        $audio = $summary[1];

        $t->same('video', $video['media']);
        $t->same(true, $video['controls']);
        $t->same(false, $video['autoplay']);
        $t->same(true, $video['loop']);
        $t->same(true, $video['muted']);
        $t->same('metadata', $video['preload']);
        $t->same('cover.jpg', $video['poster']);
        $t->same([
            ['src' => 'movie.webm', 'type' => 'video/webm'],
            ['src' => 'movie.mp4', 'type' => 'video/mp4', 'media' => '(min-width: 40em)'],
        ], $video['sources']);
        $t->same([
            ['kind' => 'captions', 'src' => 'captions.vtt', 'srclang' => 'en', 'label' => 'English', 'default' => true],
        ], $video['tracks']);
        $t->same('Fallback download', $video['fallbackText']);
        $t->same('audio', $audio['media']);
        $t->same(false, $audio['controls']);
        $t->same(true, $audio['autoplay']);
        $t->same(false, $audio['loop']);
        $t->same(false, $audio['muted']);
        $t->same('auto', $audio['preload']);
        $t->same([
            ['src' => 'sample.mp3'],
            ['src' => 'sample.ogg', 'type' => 'audio/ogg'],
        ], $audio['sources']);
        $t->same([
            ['kind' => 'chapters', 'src' => 'chapters.vtt', 'srclang' => 'en', 'label' => 'Chapters', 'default' => false],
        ], $audio['tracks']);
        $t->same('Audio fallback', $audio['fallbackText']);
        $t->same('<video controls id="preview" loop muted poster="cover.jpg" preload="metadata"><source src="movie.webm" type="video/webm"><source media="(min-width: 40em)" src="movie.mp4" type="video/mp4"><track default kind="captions" label="English" src="captions.vtt" srclang="en">Fallback <a href="movie.mp4">download</a></video><audio autoplay id="sample" preload="bogus" src="sample.mp3"><source src="sample.ogg" type="audio/ogg"><track kind="chapters" label="Chapters" src="chapters.vtt" srclang="en">Audio fallback</audio>', $html);
    },
    'summarizes html embedded image and media source candidates for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<picture><source media="(min-width: 60em)" type="image/avif" srcset="hero.avif 1x, hero@2x.avif 2x"><source type="image/webp" srcset="hero.webp 800w"><img src="hero.jpg" srcset="hero-small.jpg 400w, hero-large.jpg 1200w" sizes="100vw" alt="Hero &amp; Source" loading="lazy" decoding="async"></picture>'
                . '<video controls poster="poster.jpg" preload="metadata"><source src="clip.webm" type="video/webm"><source src="clip.mp4" type="video/mp4" media="screen"><track kind="captions" srclang="en" label="English" src="captions.vtt" default></video>'
                . '<audio src="chapter.mp3" controls><source src="chapter.ogg" type="audio/ogg"></audio>'
                . '<iframe src="frame.html" srcdoc="&lt;p&gt;Preview&lt;/p&gt;" sandbox="allow-scripts allow-forms" allowfullscreen loading="lazy" referrerpolicy="no-referrer" width="640" height="360">Legacy frame fallback</iframe>'
                . '<embed src="plugin.swf" type="application/x-shockwave-flash" width="320" height="32"></embed>'
                . '<object data="diagram.svg" type="image/svg+xml" name="diagram" width="640" height="480"><param name="quality" value="high"><param name="review-url" value="packet.html" valuetype="ref" type="text/html">Object fallback</object>',
            'embedded media review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $picture = $summary[0];
        $image = $picture['image'];
        $video = $summary[1];
        $audio = $summary[2];
        $iframe = $summary[3];
        $embed = $summary[4];
        $object = $summary[5];

        $t->same('picture', $picture['embeddedResource']);
        $t->same(2, count($picture['pictureSources']));
        $t->same('image/avif', $picture['pictureSources'][0]['type']);
        $t->same('(min-width: 60em)', $picture['pictureSources'][0]['media']);
        $t->same('hero.avif', $picture['pictureSources'][0]['srcsetCandidates'][0]['url']);
        $t->same(['2x'], $picture['pictureSources'][0]['srcsetCandidates'][1]['descriptors']);
        $t->same('image', $image['embeddedResource']);
        $t->same('hero.jpg', $image['src']);
        $t->same('Hero & Source', $image['alt']);
        $t->same('hero-small.jpg', $image['srcsetCandidates'][0]['url']);
        $t->same('1200w', $image['srcsetCandidates'][1]['descriptor']);
        $t->same('100vw', $image['sizes']);
        $t->same('lazy', $image['loading']);
        $t->same('async', $image['decoding']);

        $t->same('video', $video['embeddedResource']);
        $t->same(true, $video['controls']);
        $t->same('poster.jpg', $video['poster']);
        $t->same('metadata', $video['preload']);
        $t->same('clip.webm', $video['mediaSources'][0]['src']);
        $t->same('video/mp4', $video['mediaSources'][1]['type']);
        $t->same('screen', $video['mediaSources'][1]['media']);
        $t->same('captions', $video['tracks'][0]['kind']);
        $t->same('en', $video['tracks'][0]['srclang']);
        $t->same('English', $video['tracks'][0]['label']);
        $t->same('captions.vtt', $video['tracks'][0]['src']);
        $t->same(true, $video['tracks'][0]['default']);

        $t->same('audio', $audio['embeddedResource']);
        $t->same('chapter.mp3', $audio['src']);
        $t->same(true, $audio['controls']);
        $t->same('chapter.ogg', $audio['mediaSources'][0]['src']);
        $t->same('audio/ogg', $audio['mediaSources'][0]['type']);

        $t->same('iframe', $iframe['embeddedResource']);
        $t->same('frame.html', $iframe['src']);
        $t->same('<p>Preview</p>', $iframe['srcdoc']);
        $t->same(['allow-scripts', 'allow-forms'], $iframe['sandboxTokens']);
        $t->same(true, $iframe['allowFullscreen']);
        $t->same('Legacy frame fallback', $iframe['fallbackText']);

        $t->same('embed', $embed['embeddedResource']);
        $t->same('plugin.swf', $embed['src']);
        $t->same('application/x-shockwave-flash', $embed['mimeType']);
        $t->same('320', $embed['width']);

        $t->same('object', $object['embeddedResource']);
        $t->same('diagram.svg', $object['data']);
        $t->same('image/svg+xml', $object['mimeType']);
        $t->same('diagram', $object['nameAttribute']);
        $t->same([
            ['paramName' => 'quality', 'value' => 'high', 'valueType' => null, 'mimeType' => null],
            ['paramName' => 'review-url', 'value' => 'packet.html', 'valueType' => 'ref', 'mimeType' => 'text/html'],
        ], $object['params']);
        $t->same('param', $object['children'][0]['embeddedResource']);
        $t->same('quality', $object['children'][0]['paramName']);
        $t->same('Object fallback', $object['fallbackText']);
        $t->same('<picture><source media="(min-width: 60em)" srcset="hero.avif 1x, hero@2x.avif 2x" type="image/avif"><source srcset="hero.webp 800w" type="image/webp"><img alt="Hero &amp; Source" decoding="async" loading="lazy" sizes="100vw" src="hero.jpg" srcset="hero-small.jpg 400w, hero-large.jpg 1200w"></picture><video controls poster="poster.jpg" preload="metadata"><source src="clip.webm" type="video/webm"><source media="screen" src="clip.mp4" type="video/mp4"><track default kind="captions" label="English" src="captions.vtt" srclang="en"></video><audio controls src="chapter.mp3"><source src="chapter.ogg" type="audio/ogg"></audio><iframe allowfullscreen height="360" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts allow-forms" src="frame.html" srcdoc="&lt;p&gt;Preview&lt;/p&gt;" width="640">Legacy frame fallback</iframe><embed height="32" src="plugin.swf" type="application/x-shockwave-flash" width="320"><object data="diagram.svg" height="480" name="diagram" type="image/svg+xml" width="640"><param name="quality" value="high"></param><param name="review-url" type="text/html" value="packet.html" valuetype="ref"></param>Object fallback</object>', $html);
    },
    'summarizes html hyperlinks and image-map areas for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p>See <a href="chapter.html#intro" target="_blank" rel="noopener noreferrer tag" download="packet.html" hreflang="en" type="text/html" ping="/audit /log" referrerpolicy="no-referrer">Chapter <span>one</span></a></p>'
                . '<map name="figures"><area shape="rect" coords="0,0,10,10" href="diagram.png#hotspot" alt="Diagram hotspot" target="_self" rel="help external"></map>',
            'hyperlink review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $anchor = $summary[0]['children'][1];
        $map = $summary[1];
        $area = $map['children'][0];

        $t->same('a', $anchor['name']);
        $t->same('a', $anchor['hyperlink']);
        $t->same('chapter.html#intro', $anchor['href']);
        $t->same('_blank', $anchor['target']);
        $t->same('noopener noreferrer tag', $anchor['relRaw']);
        $t->same(['noopener', 'noreferrer', 'tag'], $anchor['relTokens']);
        $t->same('packet.html', $anchor['download']);
        $t->same('en', $anchor['hreflang']);
        $t->same('text/html', $anchor['mimeType']);
        $t->same('/audit /log', $anchor['pingRaw']);
        $t->same(['/audit', '/log'], $anchor['pingUrls']);
        $t->same('no-referrer', $anchor['referrerpolicy']);
        $t->same('Chapter one', $anchor['label']);
        $t->same('map', $map['name']);
        $t->same(['name' => 'figures'], $map['attributes']);
        $t->same('area', $area['name']);
        $t->same('area', $area['hyperlink']);
        $t->same('diagram.png#hotspot', $area['href']);
        $t->same('Diagram hotspot', $area['label']);
        $t->same('rect', $area['shape']);
        $t->same('0,0,10,10', $area['coords']);
        $t->same(['help', 'external'], $area['relTokens']);
        $t->same('<p>See <a download="packet.html" href="chapter.html#intro" hreflang="en" ping="/audit /log" referrerpolicy="no-referrer" rel="noopener noreferrer tag" target="_blank" type="text/html">Chapter <span>one</span></a></p><map name="figures"><area alt="Diagram hotspot" coords="0,0,10,10" href="diagram.png#hotspot" rel="help external" shape="rect" target="_self"></map>', $html);
    },
    'summarizes html base link and meta metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<base href="https://example.test/docs/" target="_blank">'
                . '<link rel="preload stylesheet modulepreload" href="review.css" as="style" type="text/css" media="screen and (min-width: 40em)" hreflang="en" crossorigin="anonymous" integrity="sha384-review" referrerpolicy="no-referrer" sizes="any" imagesrcset="cover.avif 1x, cover@2x.avif 2x" imagesizes="100vw" fetchpriority="high">'
                . '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta property="og:title" content="Review Packet"><meta http-equiv="refresh" content="5; url=https://example.test/next?stage=review"><p>Body</p>',
            'document metadata review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/document-metadata-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $base = $summary[0];
        $link = $summary[1];
        $charsetMeta = $summary[2];
        $viewportMeta = $summary[3];
        $propertyMeta = $summary[4];
        $refreshMeta = $summary[5];
        $paragraph = $summary[6];

        $t->same('base', $base['documentMetadata']);
        $t->same('https://example.test/docs/', $base['href']);
        $t->same('_blank', $base['target']);
        $t->same('link', $link['documentMetadata']);
        $t->same('review.css', $link['href']);
        $t->same('preload stylesheet modulepreload', $link['relRaw']);
        $t->same(['preload', 'stylesheet', 'modulepreload'], $link['relTokens']);
        $t->same('style', $link['as']);
        $t->same('screen and (min-width: 40em)', $link['media']);
        $t->same('en', $link['hreflang']);
        $t->same('text/css', $link['mimeType']);
        $t->same('anonymous', $link['crossorigin']);
        $t->same('sha384-review', $link['integrity']);
        $t->same('no-referrer', $link['referrerpolicy']);
        $t->same('any', $link['sizes']);
        $t->same('cover.avif 1x, cover@2x.avif 2x', $link['imageSrcset']);
        $t->same('cover.avif', $link['imageSrcsetCandidates'][0]['url']);
        $t->same(['2x'], $link['imageSrcsetCandidates'][1]['descriptors']);
        $t->same('100vw', $link['imageSizes']);
        $t->same('high', $link['fetchpriority']);
        $t->same('meta', $charsetMeta['documentMetadata']);
        $t->same('UTF-8', $charsetMeta['charset']);
        $t->same('viewport', $viewportMeta['nameAttribute']);
        $t->same('width=device-width, initial-scale=1', $viewportMeta['content']);
        $t->same('og:title', $propertyMeta['property']);
        $t->same('Review Packet', $propertyMeta['content']);
        $t->same('refresh', $refreshMeta['httpEquivRaw']);
        $t->same('refresh', $refreshMeta['httpEquiv']);
        $t->same('5; url=https://example.test/next?stage=review', $refreshMeta['content']);
        $t->same([
            'contentRaw' => '5; url=https://example.test/next?stage=review',
            'delayRaw' => '5',
            'delay' => 5.0,
            'urlRaw' => 'https://example.test/next?stage=review',
            'url' => 'https://example.test/next?stage=review',
        ], $refreshMeta['refresh']);
        $t->same('Body', $paragraph['text']);
        $t->same('<base href="https://example.test/docs/" target="_blank"><link as="style" crossorigin="anonymous" fetchpriority="high" href="review.css" hreflang="en" imagesizes="100vw" imagesrcset="cover.avif 1x, cover@2x.avif 2x" integrity="sha384-review" media="screen and (min-width: 40em)" referrerpolicy="no-referrer" rel="preload stylesheet modulepreload" sizes="any" type="text/css"><meta charset="UTF-8"><meta content="width=device-width, initial-scale=1" name="viewport"><meta content="Review Packet" property="og:title"><meta content="5; url=https://example.test/next?stage=review" http-equiv="refresh"><p>Body</p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/document-metadata-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html figure caption state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<figure id="fig-review"><img src="chart.png" alt="Quarterly chart"><figcaption>Figure <strong>one</strong>: imports</figcaption><p>Fallback note</p><figcaption>Extra caption</figcaption></figure>'
                . '<figcaption data-review="orphan">Orphan caption</figcaption>',
            'figure caption review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $figure = $summary[0];
        $image = $figure['children'][0];
        $caption = $figure['children'][1];
        $extraCaption = $figure['children'][3];
        $orphanCaption = $summary[1];

        $t->same('figure', $figure['name']);
        $t->same('figure', $figure['figurePart']);
        $t->same('Figure one: imports', $figure['captionText']);
        $t->same(2, $figure['captionCount']);
        $t->same('image', $image['embeddedResource']);
        $t->same('chart.png', $image['src']);
        $t->same('Quarterly chart', $image['alt']);
        $t->same('figcaption', $caption['name']);
        $t->same('caption', $caption['figurePart']);
        $t->same('Figure one: imports', $caption['captionText']);
        $t->same('Extra caption', $extraCaption['captionText']);
        $t->same('figcaption', $orphanCaption['name']);
        $t->same('caption', $orphanCaption['figurePart']);
        $t->same('Orphan caption', $orphanCaption['captionText']);
        $t->same(['review' => 'orphan'], $orphanCaption['dataset']);
        $t->same('<figure id="fig-review"><img alt="Quarterly chart" src="chart.png"><figcaption>Figure <strong>one</strong>: imports</figcaption><p>Fallback note</p><figcaption>Extra caption</figcaption></figure><figcaption data-review="orphan">Orphan caption</figcaption>', $html);
    },
    'summarizes html table structure spans and header references for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<table id="review"><caption>Quarterly <strong>review</strong></caption><colgroup span="2"><col span="3"><col span="0"></colgroup><thead><tr><th id="h1" scope="col" abbr="Q1">Quarter</th><th id="h2" scope="bad" colspan="2">Status</th></tr></thead><tbody><tr><th id="r1" scope="row">Batch A</th><td headers="h1 r1" rowspan="0" colspan="3">Ready</td><td colspan="2000" rowspan="-1">Overflow</td></tr></tbody></table>',
            'table structure review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $table = $summary[0];
        $caption = $table['children'][0];
        $colgroup = $table['children'][1];
        $firstColumn = $colgroup['children'][0];
        $invalidColumn = $colgroup['children'][1];
        $thead = $table['children'][2];
        $headRow = $thead['children'][0];
        $quarterHeader = $headRow['children'][0];
        $statusHeader = $headRow['children'][1];
        $tbody = $table['children'][3];
        $bodyRow = $tbody['children'][0];
        $rowHeader = $bodyRow['children'][0];
        $readyCell = $bodyRow['children'][1];
        $overflowCell = $bodyRow['children'][2];

        $t->same('table', $table['tablePart']);
        $t->same('Quarterly review', $table['captionText']);
        $t->same(1, $table['captionCount']);
        $t->same('caption', $caption['tablePart']);
        $t->same('Quarterly review', $caption['captionText']);
        $t->same('column-group', $colgroup['tablePart']);
        $t->same('2', $colgroup['spanRaw']);
        $t->same(2, $colgroup['span']);
        $t->same('column', $firstColumn['tablePart']);
        $t->same('3', $firstColumn['spanRaw']);
        $t->same(3, $firstColumn['span']);
        $t->same('0', $invalidColumn['spanRaw']);
        $t->same(1, $invalidColumn['span']);

        $t->same('header-group', $thead['tablePart']);
        $t->same('body-group', $tbody['tablePart']);
        $t->same('row', $headRow['tablePart']);
        $t->same('row', $bodyRow['tablePart']);

        $t->same('cell', $quarterHeader['tablePart']);
        $t->same('header', $quarterHeader['tableCell']);
        $t->same(1, $quarterHeader['colSpan']);
        $t->same(1, $quarterHeader['rowSpan']);
        $t->same('col', $quarterHeader['scopeRaw']);
        $t->same('col', $quarterHeader['scope']);
        $t->same('Q1', $quarterHeader['abbr']);
        $t->same([], $quarterHeader['headers']);
        $t->same('bad', $statusHeader['scopeRaw']);
        $t->same(null, $statusHeader['scope']);
        $t->same('2', $statusHeader['colSpanRaw']);
        $t->same(2, $statusHeader['colSpan']);

        $t->same('header', $rowHeader['tableCell']);
        $t->same('row', $rowHeader['scope']);
        $t->same('data', $readyCell['tableCell']);
        $t->same('h1 r1', $readyCell['headersRaw']);
        $t->same(['h1', 'r1'], $readyCell['headers']);
        $t->same('3', $readyCell['colSpanRaw']);
        $t->same(3, $readyCell['colSpan']);
        $t->same('0', $readyCell['rowSpanRaw']);
        $t->same(0, $readyCell['rowSpan']);
        $t->same('2000', $overflowCell['colSpanRaw']);
        $t->same(1000, $overflowCell['colSpan']);
        $t->same('-1', $overflowCell['rowSpanRaw']);
        $t->same(1, $overflowCell['rowSpan']);
        $t->same('<table id="review"><caption>Quarterly <strong>review</strong></caption><colgroup span="2"><col span="3"><col span="0"></colgroup><thead><tr><th abbr="Q1" id="h1" scope="col">Quarter</th><th colspan="2" id="h2" scope="bad">Status</th></tr></thead><tbody><tr><th id="r1" scope="row">Batch A</th><td colspan="3" headers="h1 r1" rowspan="0">Ready</td><td colspan="2000" rowspan="-1">Overflow</td></tr></tbody></table>', $html);
    },
    'serializes detached dom nodes and children for reader handoff' => static function (TestRunner $t): void {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $fragment = $dom->createDocumentFragment();
        $section = $dom->createElement('section');
        $section->setAttribute('hidden', 'hidden');
        $paragraph = $dom->createElement('p');
        $paragraph->appendChild($dom->createTextNode('Detached <text> & notes'));
        $section->appendChild($paragraph);
        $section->appendChild($dom->createElement('br'));
        $section->appendChild($dom->createComment('review -- source'));
        $fragment->appendChild($section);

        $t->same('<section hidden><p>Detached &lt;text&gt; &amp; notes</p><br><!--review - - source--></section>', XmlHtmlDom::serializeHtmlNode($fragment));
        $t->same('<p>Detached &lt;text&gt; &amp; notes</p><br><!--review - - source-->', XmlHtmlDom::serializeHtmlChildren($section));
        $t->same('<section hidden><p>Detached &lt;text&gt; &amp; notes</p><br><!--review - - source--></section>', XmlHtmlDom::serializeHtmlNode($section));
        $t->same('<!--detached- -->', XmlHtmlDom::serializeHtmlNode($dom->createComment('detached-')));
        $t->same('<!--detached- - -tail- -->', XmlHtmlDom::serializeHtmlNode($dom->createComment('detached---tail-')));
    },
    'preserves svg and mathml foreign content names in deterministic html serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<svg viewBox="0 0 10 10" preserveAspectRatio="xMidYMid meet"><linearGradient id="g"><stop offset="0"></stop></linearGradient><textPath href="#label">Logo</textPath></svg>'
                . '<math><mi definitionURL="#x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math>',
            'foreign content HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('svg', $summary[0]['name']);
        $t->same([
            'preserveAspectRatio' => 'xMidYMid meet',
            'viewBox' => '0 0 10 10',
        ], $summary[0]['attributes']);
        $t->same('linearGradient', $summary[0]['children'][0]['name']);
        $t->same('textPath', $summary[0]['children'][1]['name']);
        $t->same('math', $summary[1]['name']);
        $t->same('definitionURL', array_key_first($summary[1]['children'][0]['attributes']));
        $t->same('<svg preserveAspectRatio="xMidYMid meet" viewBox="0 0 10 10"><linearGradient id="g"><stop offset="0"></stop></linearGradient><textPath href="#label">Logo</textPath></svg><math><mi definitionURL="#x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math>', $html);
    },
    'keeps svg element-name casing scoped to svg foreign content' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<math><lineargradient data-review="math">m</lineargradient><mtext><linearGradient viewBox="html">html</linearGradient></mtext><svg><linearGradient id="g"></linearGradient></svg></math>',
            'mixed MathML and SVG foreign content fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $mathUnknown = $summary[0]['children'][0];
        $mathHtmlText = $summary[0]['children'][1]['children'][0];
        $nestedSvg = $summary[0]['children'][2];

        $t->same('math', $summary[0]['name']);
        $t->same('lineargradient', $mathUnknown['name']);
        $t->same(['data-review' => 'math'], $mathUnknown['attributes']);
        $t->same('lineargradient', $mathHtmlText['name']);
        $t->same(['viewbox' => 'html'], $mathHtmlText['attributes']);
        $t->same('svg', $nestedSvg['name']);
        $t->same('linearGradient', $nestedSvg['children'][0]['name']);
        $t->same('<math><lineargradient data-review="math">m</lineargradient><mtext><lineargradient viewbox="html">html</lineargradient></mtext><svg><linearGradient id="g"></linearGradient></svg></math>', $html);
        $t->true(!str_contains($html, '<math><linearGradient'), 'Expected MathML non-SVG descendants to keep their parsed names');
    },
    'keeps html integration point descendants out of foreign-content casing' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<svg><foreignObject><div viewBox="html attr"><linearGradient data-review="html child">HTML child</linearGradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject></svg>'
                . '<math><annotation-xml encoding="text/html"><div viewBox="math html"><textPath>HTML text</textPath></div></annotation-xml><annotation-xml encoding="MathML-Content"><ci definitionURL="#x">x</ci></annotation-xml></math>',
            'foreign content integration-point fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $foreignObject = $summary[0]['children'][0];
        $foreignDiv = $foreignObject['children'][0];
        $nestedSvg = $foreignDiv['children'][1];
        $mathHtmlAnnotation = $summary[1]['children'][0];
        $mathHtmlDiv = $mathHtmlAnnotation['children'][0];
        $mathContentAnnotation = $summary[1]['children'][1];

        $t->same('foreignObject', $foreignObject['name']);
        $t->same('div', $foreignDiv['name']);
        $t->same(['viewbox' => 'html attr'], $foreignDiv['attributes']);
        $t->same('lineargradient', $foreignDiv['children'][0]['name']);
        $t->same('svg', $nestedSvg['name']);
        $t->same('linearGradient', $nestedSvg['children'][0]['name']);
        $t->same('annotation-xml', $mathHtmlAnnotation['name']);
        $t->same(['encoding' => 'text/html'], $mathHtmlAnnotation['attributes']);
        $t->same('div', $mathHtmlDiv['name']);
        $t->same(['viewbox' => 'math html'], $mathHtmlDiv['attributes']);
        $t->same('textpath', $mathHtmlDiv['children'][0]['name']);
        $t->same(['definitionURL' => '#x'], $mathContentAnnotation['children'][0]['attributes']);
        $t->same('<svg><foreignObject><div viewbox="html attr"><lineargradient data-review="html child">HTML child</lineargradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject></svg><math><annotation-xml encoding="text/html"><div viewbox="math html"><textpath>HTML text</textpath></div></annotation-xml><annotation-xml encoding="MathML-Content"><ci definitionURL="#x">x</ci></annotation-xml></math>', $html);
    },
    'treats svg desc descendants as html integration point content' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<svg><desc><p viewBox="html attr"><textPath>HTML fallback</textPath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></desc></svg>',
            'svg desc integration-point fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $svg = $summary[0];
        $desc = $svg['children'][0];
        $paragraph = $desc['children'][0];
        $textPath = $paragraph['children'][0];
        $nestedSvg = $paragraph['children'][1];

        $t->same('svg', $svg['name']);
        $t->same('desc', $desc['name']);
        $t->same('p', $paragraph['name']);
        $t->same(['viewbox' => 'html attr'], $paragraph['attributes']);
        $t->same('textpath', $textPath['name']);
        $t->same('svg', $nestedSvg['name']);
        $t->same(['viewBox' => '0 0 1 1'], $nestedSvg['attributes']);
        $t->same('linearGradient', $nestedSvg['children'][0]['name']);
        $t->same('<svg><desc><p viewbox="html attr"><textpath>HTML fallback</textpath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></desc></svg>', $html);
    },
    'treats svg title descendants as html integration point content' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<svg><title><p viewBox="html attr"><textPath>Title fallback</textPath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></title></svg>',
            'svg title integration-point fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $svg = $summary[0];
        $title = $svg['children'][0];
        $paragraph = $title['children'][0];
        $textPath = $paragraph['children'][0];
        $nestedSvg = $paragraph['children'][1];

        $t->same('svg', $svg['name']);
        $t->same('title', $title['name']);
        $t->same('p', $paragraph['name']);
        $t->same(['viewbox' => 'html attr'], $paragraph['attributes']);
        $t->same('textpath', $textPath['name']);
        $t->same('svg', $nestedSvg['name']);
        $t->same(['viewBox' => '0 0 1 1'], $nestedSvg['attributes']);
        $t->same('linearGradient', $nestedSvg['children'][0]['name']);
        $t->same('<svg><title><p viewbox="html attr"><textpath>Title fallback</textpath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></title></svg>', $html);
        $t->true(!str_contains($html, '&lt;p viewBox'), 'Expected SVG title fallback markup to stay parsed instead of escaped as RCDATA');
    },
    'keeps mathml token text integration descendants in html casing' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<math><mtext><span viewBox="html attr"><textPath>HTML text</textPath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></span></mtext>'
                . '<mi><a href="/review">link</a></mi><mo><mglyph src="glyph.png"></mglyph></mo></math>',
            'mathml text integration-point fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $math = $summary[0];
        $mtext = $math['children'][0];
        $span = $mtext['children'][0];
        $nestedSvg = $span['children'][1];
        $mi = $math['children'][1];
        $mo = $math['children'][2];

        $t->same('math', $math['name']);
        $t->same('mtext', $mtext['name']);
        $t->same(['viewbox' => 'html attr'], $span['attributes']);
        $t->same('textpath', $span['children'][0]['name']);
        $t->same('svg', $nestedSvg['name']);
        $t->same(['viewBox' => '0 0 1 1'], $nestedSvg['attributes']);
        $t->same('linearGradient', $nestedSvg['children'][0]['name']);
        $t->same('a', $mi['children'][0]['name']);
        $t->same(['href' => '/review'], $mi['children'][0]['attributes']);
        $t->same('mglyph', $mo['children'][0]['name']);
        $t->same('<math><mtext><span viewbox="html attr"><textpath>HTML text</textpath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></span></mtext><mi><a href="/review">link</a></mi><mo><mglyph src="glyph.png"></mglyph></mo></math>', $html);
    },
    'keeps mathml mglyph and malignmark exceptions in foreign casing' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<math><mi><malignmark definitionURL="#mark"><svg viewBox="0 0 1 1"><linearGradient id="g"></linearGradient></svg></malignmark><mglyph definitionURL="#glyph"></mglyph><span definitionURL="#html">HTML</span></mi></math>',
            'mathml text integration-point exception fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $mi = $summary[0]['children'][0];
        $malignmark = $mi['children'][0];
        $mglyph = $mi['children'][1];
        $span = $mi['children'][2];

        $t->same(['definitionURL' => '#mark'], $malignmark['attributes']);
        $t->same('svg', $malignmark['children'][0]['name']);
        $t->same('linearGradient', $malignmark['children'][0]['children'][0]['name']);
        $t->same(['definitionURL' => '#glyph'], $mglyph['attributes']);
        $t->same(['definitionurl' => '#html'], $span['attributes']);
        $t->same('<math><mi><malignmark definitionURL="#mark"><svg viewBox="0 0 1 1"><linearGradient id="g"></linearGradient></svg></malignmark><mglyph definitionURL="#glyph"></mglyph><span definitionurl="#html">HTML</span></mi></math>', $html);
    },
    'preserves html foreign-content cdata sections as escaped text' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<svg><desc><![CDATA[Reviewer <source> & notes]]></desc><text><![CDATA[A < B & C]]></text></svg>'
                . '<math><annotation encoding="application/x-tex"><![CDATA[x < y & z]]></annotation></math>',
            'foreign content CDATA fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('svg', $summary[0]['name']);
        $t->same('desc', $summary[0]['children'][0]['name']);
        $t->same('Reviewer <source> & notes', $summary[0]['children'][0]['text']);
        $t->same('text', $summary[0]['children'][1]['name']);
        $t->same('A < B & C', $summary[0]['children'][1]['text']);
        $t->same('math', $summary[1]['name']);
        $t->same('annotation', $summary[1]['children'][0]['name']);
        $t->same(['encoding' => 'application/x-tex'], $summary[1]['children'][0]['attributes']);
        $t->same('x < y & z', $summary[1]['children'][0]['text']);
        $t->same('<svg><desc>Reviewer &lt;source&gt; &amp; notes</desc><text>A &lt; B &amp; C</text></svg><math><annotation encoding="application/x-tex">x &lt; y &amp; z</annotation></math>', $html);
        $t->true(!str_contains($html, '<![CDATA['), 'Expected CDATA delimiters to be normalized away before HTML handoff');
        $t->true(!str_contains($html, '<source>'), 'Expected CDATA tag-looking text to stay escaped');
    },
    'serializes html rcdata elements as escaped text not parsed child markup' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<textarea data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b></textarea>'
                . '<title>Packet <em>literal</em> &amp; title</title>',
            'rcdata review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('textarea', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same('Reviewer <script>alert(1)</script> & <b>note</b>', $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same('Reviewer <script>alert(1)</script> & <b>note</b>', $summary[0]['children'][0]['text']);
        $t->same('title', $summary[1]['name']);
        $t->same('Packet <em>literal</em> & title', $summary[1]['text']);
        $t->same('text', $summary[1]['children'][0]['type']);
        $t->same(
            '<textarea data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;b&gt;note&lt;/b&gt;</textarea><title>Packet &lt;em&gt;literal&lt;/em&gt; &amp; title</title>',
            $html
        );
    },
    'keeps unterminated html rcdata source as escaped text through fragment end' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<textarea data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b><p>after</p>',
            'unterminated rcdata review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $expectedText = 'Reviewer <script>alert(1)</script> & <b>note</b><p>after</p>';
        $expectedHtml = '<textarea data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;b&gt;note&lt;/b&gt;&lt;p&gt;after&lt;/p&gt;</textarea>';

        $t->same(1, count($summary));
        $t->same('textarea', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same($expectedText, $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same($expectedText, $summary[0]['children'][0]['text']);
        $t->same($expectedHtml, $html);
        $t->true(!str_contains($html, '<script>alert(1)</script>'), 'Expected unterminated textarea script-looking source to stay escaped');
        $t->true(!str_contains($html, '<p>after</p>'), 'Expected unterminated textarea following source to stay escaped');
    },
    'serializes obsolete html raw text fallback elements as escaped source text' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<xmp data-source="legacy">Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea></xmp>'
                . '<noembed>Fallback <img src=x> & source</noembed>'
                . '<noframes>Frame fallback <a href="/edit">edit</a></noframes><p>after</p>',
            'obsolete raw text review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('xmp', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same('Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea>', $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same('Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea>', $summary[0]['children'][0]['text']);
        $t->same('noembed', $summary[1]['name']);
        $t->same('Fallback <img src=x> & source', $summary[1]['text']);
        $t->same('noframes', $summary[2]['name']);
        $t->same('Frame fallback <a href="/edit">edit</a>', $summary[2]['text']);
        $t->same('<xmp data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;textarea&gt;&lt;b&gt;note&lt;/b&gt;&lt;/textarea&gt;</xmp><noembed>Fallback &lt;img src=x&gt; &amp; source</noembed><noframes>Frame fallback &lt;a href="/edit"&gt;edit&lt;/a&gt;</noframes><p>after</p>', $html);
        $t->true(!str_contains($html, '<textarea>'), 'Expected raw text textarea-looking source to stay escaped');
        $t->true(!str_contains($html, '<script>alert(1)</script>'), 'Expected raw text script-looking source to stay escaped');
    },
    'treats html noscript fallback as escaped raw text for raw handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<noscript data-source="legacy">Fallback <script>alert(1)</script> & source <img src=x></noscript><p>after</p>',
            'noscript raw text review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same(2, count($summary));
        $t->same('noscript', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same('Fallback <script>alert(1)</script> & source <img src=x>', $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same('Fallback <script>alert(1)</script> & source <img src=x>', $summary[0]['children'][0]['text']);
        $t->same('p', $summary[1]['name']);
        $t->same('after', $summary[1]['text']);
        $t->same('<noscript data-source="legacy">Fallback &lt;script&gt;alert(1)&lt;/script&gt; &amp; source &lt;img src=x&gt;</noscript><p>after</p>', $html);
        $t->true(!str_contains($html, '<script>alert(1)</script>'), 'Expected noscript script-looking source to stay escaped');
        $t->true(!str_contains($html, '<img src=x>'), 'Expected noscript image-looking source to stay escaped');
    },
    'treats html iframe fallback as escaped raw text for raw handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<iframe data-source="legacy"><p>Fallback <script>alert(1)</script> &amp; note</p></iframe><p>after</p>',
            'iframe raw text review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same(2, count($summary));
        $t->same('iframe', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same('<p>Fallback <script>alert(1)</script> &amp; note</p>', $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same('<p>Fallback <script>alert(1)</script> &amp; note</p>', $summary[0]['children'][0]['text']);
        $t->same('p', $summary[1]['name']);
        $t->same('<iframe data-source="legacy">&lt;p&gt;Fallback &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; note&lt;/p&gt;</iframe><p>after</p>', $html);
        $t->true(!str_contains($html, '<script>alert(1)</script>'), 'Expected iframe fallback script-looking source to stay escaped');
        $t->true(!str_contains($html, '<p>Fallback'), 'Expected iframe fallback paragraph markup to stay escaped');
    },
    'treats html plaintext as escaped source text through end of fragment' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<plaintext data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b></plaintext><p>after</p>',
            'plaintext review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $expectedText = 'Reviewer <script>alert(1)</script> &amp; <b>note</b></plaintext><p>after</p>';

        $t->same(1, count($summary));
        $t->same('plaintext', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same($expectedText, $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same($expectedText, $summary[0]['children'][0]['text']);
        $t->same('<plaintext data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;b&gt;note&lt;/b&gt;&lt;/plaintext&gt;&lt;p&gt;after&lt;/p&gt;</plaintext>', $html);
        $t->true(!str_contains($html, '<script>alert(1)</script>'), 'Expected plaintext script-looking source to stay escaped');
        $t->true(!str_contains($html, '<p>after</p>'), 'Expected following paragraph source to stay plaintext text');
    },
    'treats html template contents as inert escaped source text for raw handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<template data-source="legacy"><p>Template <script>drop()</script> &amp; <b>note</b></p></template><p>after</p>',
            'template review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', ['source' => 'xml-html5-dom'], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/template-source-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expectedTemplateText = '<p>Template <script>drop()</script> &amp; <b>note</b></p>';
        $expectedHtml = '<template data-source="legacy">&lt;p&gt;Template &lt;script&gt;drop()&lt;/script&gt; &amp;amp; &lt;b&gt;note&lt;/b&gt;&lt;/p&gt;</template><p>after</p>';

        $t->same(2, count($summary));
        $t->same('template', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same($expectedTemplateText, $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same($expectedTemplateText, $summary[0]['children'][0]['text']);
        $t->same('p', $summary[1]['name']);
        $t->same('after', $summary[1]['text']);
        $t->same($expectedHtml, $html);
        $t->contains($expectedHtml, $blocks);
        $t->same('/migration/template-source-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<script>drop()</script>'), 'Expected script-looking template source to stay escaped');
        $t->true(!str_contains($html, '<b>note</b>'), 'Expected inline tag-looking template source to stay escaped');
    },
    'foster-parents invalid table children before deterministic html serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<table class="legacy"><caption>Review rows</caption><p>Loose note</p><tr><td>A</td></tr>orphan text<tr><td>B</td></tr></table><p>after</p>',
            'table foster-parenting review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('p', $summary[0]['name']);
        $t->same('Loose note', $summary[0]['text']);
        $t->same('text', $summary[1]['type']);
        $t->same('orphan text', $summary[1]['text']);
        $t->same('table', $summary[2]['name']);
        $t->same(['class' => 'legacy'], $summary[2]['attributes']);
        $t->same('caption', $summary[2]['children'][0]['name']);
        $t->same('tr', $summary[2]['children'][1]['name']);
        $t->same('tr', $summary[2]['children'][2]['name']);
        $t->same('<p>Loose note</p>orphan text<table class="legacy"><caption>Review rows</caption><tr><td>A</td></tr><tr><td>B</td></tr></table><p>after</p>', $html);
    },
    'hands serialized HTML fragments to WordPress raw HTML blocks' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<aside data-review="source"><p>Imported<br>line &amp; reviewer notes</p></aside>',
            'WordPress review fragment'
        );
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html]),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('<aside data-review="source"><p>Imported<br>line &amp; reviewer notes</p></aside>', $html);
        $t->contains('<!-- wp:html -->', $blocks);
        $t->contains('<aside data-review="source">', $blocks);
        $t->contains('Imported<br>line &amp; reviewer notes', $blocks);
        $t->contains('<!-- /wp:html -->', $blocks);
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment("<p>bad\0html</p>", 'unsafe HTML fragment'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<!DOCTYPE html><p>bad</p>', 'unsafe HTML fragment'));
    },
    'rejects unsafe HTML fragment declarations before serialization handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment('<p data-review="ok">Safe</p>', 'safe HTML fragment');

        $t->same('<p data-review="ok">Safe</p>', XmlHtmlDom::serializeHtmlFragment($dom));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<!ENTITY reviewer SYSTEM "file:///etc/passwd"><p>&reviewer;</p>', 'unsafe HTML fragment'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<?xml-stylesheet href="https://example.invalid/review.xsl"?><p>bad</p>', 'unsafe HTML fragment'));
    },
];
