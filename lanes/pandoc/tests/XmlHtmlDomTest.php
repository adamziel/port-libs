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
    'summarizes html details disclosure state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<details id="release" open><summary>Release <strong>notes</strong></summary><p>Accepted</p></details><details id="audit"><p>No explicit summary</p></details>',
            'details disclosure review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $release = $summary[0];
        $audit = $summary[1];

        $t->same('details', $release['name']);
        $t->same('details', $release['disclosure']);
        $t->same(true, $release['open']);
        $t->same(true, $release['explicitSummary']);
        $t->same('Release notes', $release['summaryLabel']);
        $t->same('summary', $release['children'][0]['name']);
        $t->same('Release notes', $release['children'][0]['text']);
        $t->same('Accepted', $release['children'][1]['text']);
        $t->same('details', $audit['disclosure']);
        $t->same(false, $audit['open']);
        $t->same(false, $audit['explicitSummary']);
        $t->same('Details', $audit['summaryLabel']);
        $t->same('No explicit summary', $audit['children'][0]['text']);
        $t->same('<details id="release" open><summary>Release <strong>notes</strong></summary><p>Accepted</p></details><details id="audit"><p>No explicit summary</p></details>', $html);
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
                . '<audio src="chapter.mp3" controls><source src="chapter.ogg" type="audio/ogg"></audio>',
            'embedded media review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $picture = $summary[0];
        $image = $picture['image'];
        $video = $summary[1];
        $audio = $summary[2];

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
        $t->same('<picture><source media="(min-width: 60em)" srcset="hero.avif 1x, hero@2x.avif 2x" type="image/avif"><source srcset="hero.webp 800w" type="image/webp"><img alt="Hero &amp; Source" decoding="async" loading="lazy" sizes="100vw" src="hero.jpg" srcset="hero-small.jpg 400w, hero-large.jpg 1200w"></picture><video controls poster="poster.jpg" preload="metadata"><source src="clip.webm" type="video/webm"><source media="screen" src="clip.mp4" type="video/mp4"><track default kind="captions" label="English" src="captions.vtt" srclang="en"></video><audio controls src="chapter.mp3"><source src="chapter.ogg" type="audio/ogg"></audio>', $html);
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
