<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\Html5DomFragment;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtml5Dom;
use PortLibs\Pandoc\XmlHtmlDom;

$fragment = <<<'HTML'
<article data-source="legacy-html">
  <h2>Source packet</h2>
  <p>Imported<br>line with &amp; entity</p>
  <p data-review="character-references">References: A&NoBreak;B&NewLine;C&Tab;D &hopf; &nbsp &copy</p>
  <p data-review="math-spacing">Math refs: f&ApplyFunction;g&InvisibleTimes;h&MediumSpace;comma&InvisibleComma;zero&ZeroWidthSpace;neg&NegativeThinSpace;</p>
  <p data-review="spacing-references">Spacing refs: non&NonBreakingSpace;thin&ThinSpace;alias&thinsp;thick&ThickSpace;very&VeryThinSpace;hair&hairsp;neg&NegativeVeryThinSpace;&NegativeMediumSpace;&NegativeThickSpace;</p>
  <p data-review="punctuation-references" title="Issue&num;42">Punctuation refs&colon; &lpar;draft&rpar; &lsqb;A&rsqb; &dollar;5&percnt; &commat;review path&sol;to&bsol;asset</p>
  <svg viewBox="0 0 10 10" preserveAspectRatio="xMidYMid meet"><linearGradient id="review-gradient"><stop offset="0"></stop></linearGradient><textPath href="#review-label">Logo</textPath></svg>
  <math><mi definitionURL="#review-x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math>
  <figure><img src="media/review.png?rev=1&amp;post=42" alt="Review image"><figcaption>Review image</figcaption></figure>
  <template data-review="legacy-template"><p>Template <script>drop()</script> &amp; <b>source</b></p></template>
  <textarea data-review="legacy-field">Reviewer <script>alert(1)</script> &amp; <b>note</b></textarea>
  <style disabled>.legacy-note > strong { color: #600; }</style>
  <script type="application/json" data-review="metadata">{"source":"legacy <html> & notes"}</script>
</article>
HTML;

$dom = XmlHtmlDom::loadHtmlFragment($fragment, 'WordPress source HTML fragment');
$html = XmlHtmlDom::serializeHtmlFragment($dom);
$facadeBody = XmlHtml5Dom::parseHtmlFragmentBody(
    '<section data-source="legacy-facade"><p>A&NoBreak;B &hopf; &copy</p>'
    . '<p data-review="punctuation">Issue&num;42&colon; &lpar;ready&rpar;</p>'
    . '<textarea>Reviewer <script>alert(1)</script> &amp; <b>note</b></textarea></section>'
);
if (!$facadeBody instanceof DOMElement) {
    throw new RuntimeException('Expected XmlHtml5Dom facade fragment body to parse');
}
$facadeHtml = XmlHtml5Dom::serializeHtmlFragment($facadeBody);
$reviewXml = XmlHtmlDom::loadXmlDocument(
    '<?xml version="1.0" encoding="UTF-8"?><review><item source="legacy">DOM packet</item></review>',
    'WordPress XML review packet',
    preserveWhiteSpace: false
);
$namespacedReviewFragment = Html5DomFragment::fromXml(
    '<review xmlns="urn:packet"><w:p xmlns:w="urn:word" w:rsidR="001">'
    . '<w:t xml:space="preserve"> Namespaced packet </w:t>'
    . '<r:link xmlns:r="urn:rel" r:id="rId1">media</r:link>'
    . '<plain xmlns="">fallback</plain>'
    . '</w:p></review>'
);
$xmlPolicyReviewFragment = Html5DomFragment::fromXml(
    '<packet><link href="rId1" onload="review-source">media</link>'
    . '<meta name="review" content="ok" style="source-style"/>'
    . '<script type="text/source">if (a &lt; b) { source(); }</script>'
    . '<style data-pandoc-fragment-root="source">.source &gt; note { color: red; }</style></packet>'
);
$shadowAccessibilityFragment = Html5DomFragment::fromHtml(
    '<article><template shadowrootmode="open" aria-label=" Review card " aria-describedby="caption shadow-note" aria-description=" Hidden panel ">'
    . '<p id="caption">Shadow fallback</p><p id="shadow-note">Notes</p></template>'
    . '<template shadowrootmode="closed" aria-labelledby="headline"><h2 id="headline">Headline</h2></template></article>'
);
$document = new AstNode('document', [], [
    new AstNode('raw_html', ['format' => 'html', 'html' => $html]),
    new AstNode('raw_html', ['format' => 'html', 'html' => $facadeHtml]),
]);
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    if (!str_contains($html, '<br>') || str_contains($html, '</br>')) {
        throw new RuntimeException('Expected HTML5 br void serialization');
    }
    if (!str_contains($html, '<img alt="Review image" src="media/review.png?rev=1&amp;post=42">')) {
        throw new RuntimeException('Expected deterministic img attribute escaping and void serialization');
    }
    if (!str_contains($html, '<p data-review="character-references">References: A' . "\u{2060}" . "B\nC\tD " . "\u{1D559}" . ' ' . "\u{00A0}" . ' ©</p>')) {
        throw new RuntimeException('Expected bounded HTML5 named character references to decode before review serialization');
    }
    if (str_contains($html, '&amp;NoBreak;') || str_contains($html, '&amp;hopf;')) {
        throw new RuntimeException('Expected extra HTML5 named character references to avoid literal ampersand fallback');
    }
    if (!str_contains($html, '<p data-review="math-spacing">Math refs: f' . "\u{2061}" . 'g' . "\u{2062}" . 'h' . "\u{205F}" . 'comma' . "\u{2063}" . 'zero' . "\u{200B}" . 'neg' . "\u{200B}" . '</p>')) {
        throw new RuntimeException('Expected bounded HTML5 math and spacing character references to decode before review serialization');
    }
    if (str_contains($html, '&amp;ApplyFunction;') || str_contains($html, '&amp;ZeroWidthSpace;')) {
        throw new RuntimeException('Expected math and spacing HTML5 named character references to avoid literal ampersand fallback');
    }
    if (!str_contains($html, '<p data-review="spacing-references">Spacing refs: non' . "\u{00A0}" . 'thin' . "\u{2009}" . 'alias' . "\u{2009}" . 'thick' . "\u{205F}\u{200A}" . 'very' . "\u{200A}" . 'hair' . "\u{200A}" . 'neg' . "\u{200B}\u{200B}\u{200B}" . '</p>')) {
        throw new RuntimeException('Expected extended HTML5 spacing character references to decode before review serialization');
    }
    if (str_contains($html, '&amp;NonBreakingSpace;') || str_contains($html, '&amp;ThickSpace;') || str_contains($html, '&amp;NegativeMediumSpace;')) {
        throw new RuntimeException('Expected extended HTML5 spacing references to avoid literal ampersand fallback');
    }
    if (!str_contains($html, '<p data-review="punctuation-references" title="Issue#42">Punctuation refs: (draft) [A] $5% @review path/to\\asset</p>')) {
        throw new RuntimeException('Expected bounded HTML5 punctuation character references to decode before review serialization');
    }
    foreach (['&amp;colon;', '&amp;lpar;', '&amp;lsqb;', '&amp;dollar;', '&amp;commat;', '&amp;bsol;'] as $blockedPunctuationReference) {
        if (str_contains($html, $blockedPunctuationReference)) {
            throw new RuntimeException('Expected HTML5 punctuation references to avoid literal ampersand fallback: ' . $blockedPunctuationReference);
        }
    }
    if (!str_contains($html, '<svg preserveAspectRatio="xMidYMid meet" viewBox="0 0 10 10"><linearGradient id="review-gradient"><stop offset="0"></stop></linearGradient><textPath href="#review-label">Logo</textPath></svg>')) {
        throw new RuntimeException('Expected SVG foreign-content casing to survive review handoff');
    }
    if (!str_contains($html, '<math><mi definitionURL="#review-x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math>')) {
        throw new RuntimeException('Expected MathML foreign-content casing to survive review handoff');
    }
    if (!str_contains($html, '<template data-review="legacy-template">&lt;p&gt;Template &lt;script&gt;drop()&lt;/script&gt; &amp;amp; &lt;b&gt;source&lt;/b&gt;&lt;/p&gt;</template>')) {
        throw new RuntimeException('Expected template content to serialize as inert escaped source text');
    }
    if (str_contains($html, '<script>drop()</script>') || str_contains($html, '<b>source</b>')) {
        throw new RuntimeException('Expected template source markup to remain escaped');
    }
    if (!str_contains($html, '<textarea data-review="legacy-field">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;b&gt;note&lt;/b&gt;</textarea>')) {
        throw new RuntimeException('Expected textarea RCDATA to serialize as escaped review text');
    }
    if (!str_contains($html, '<style disabled>.legacy-note > strong { color: #600; }</style>')) {
        throw new RuntimeException('Expected raw text style serialization for review packets');
    }
    if (!str_contains($html, '<script data-review="metadata" type="application/json">{"source":"legacy <html> & notes"}</script>')) {
        throw new RuntimeException('Expected raw text JSON script serialization for review metadata');
    }
    if (!str_contains($facadeHtml, '<section data-source="legacy-facade"><p>A' . "\u{2060}" . 'B ' . "\u{1D559}" . ' ©</p><p data-review="punctuation">Issue#42: (ready)</p><textarea>Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;b&gt;note&lt;/b&gt;</textarea></section>')) {
        throw new RuntimeException('Expected XmlHtml5Dom facade to reuse hardened HTML5 parsing and serialization');
    }
    if (str_contains($facadeHtml, '&amp;NoBreak;') || str_contains($facadeHtml, '&amp;colon;') || str_contains($facadeHtml, '<script>alert(1)</script>')) {
        throw new RuntimeException('Expected XmlHtml5Dom facade handoff to decode extra references and escape RCDATA source markup');
    }
    try {
        XmlHtml5Dom::parseHtmlFragmentBody('<!DOCTYPE html><p>bad</p>');

        throw new RuntimeException('Expected XmlHtml5Dom facade to reject unsafe fragment declarations');
    } catch (InvalidArgumentException) {
    }
    if (($reviewXml->documentElement?->tagName ?? '') !== 'review' || $reviewXml->documentElement->textContent !== 'DOM packet') {
        throw new RuntimeException('Expected XML declaration-bearing review packet to parse safely');
    }
    $namespacedXml = $namespacedReviewFragment->serialize();
    foreach ([
        '<review xmlns="urn:packet">',
        '<w:p xmlns:w="urn:word" w:rsidR="001">',
        '<w:t xmlns:w="urn:word" xml:space="preserve"> Namespaced packet </w:t>',
        '<r:link xmlns:r="urn:rel" r:id="rId1">media</r:link>',
        '<plain xmlns="">fallback</plain>',
    ] as $expectedXml) {
        if (!str_contains($namespacedXml, $expectedXml)) {
            throw new RuntimeException('Expected namespaced XML fragment to retain binding: ' . $expectedXml);
        }
    }
    if (Html5DomFragment::fromXml($namespacedXml)->serialize() !== $namespacedXml) {
        throw new RuntimeException('Expected namespaced XML fragment serialization to round-trip through the safe parser');
    }
    $xmlPolicy = $xmlPolicyReviewFragment->serialize();
    foreach ([
        '<link href="rId1" onload="review-source">media</link>',
        '<meta name="review" content="ok" style="source-style"/>',
        '<script type="text/source">if (a &lt; b) { source(); }</script>',
        '<style data-pandoc-fragment-root="source">.source &gt; note { color: red; }</style>',
    ] as $expectedXml) {
        if (!str_contains($xmlPolicy, $expectedXml)) {
            throw new RuntimeException('Expected XML policy-overlap fragment to preserve package markup: ' . $expectedXml);
        }
    }
    if ($xmlPolicyReviewFragment->diagnosticCodes() !== []) {
        throw new RuntimeException('Expected XML policy-overlap fragment to remain outside HTML sanitizer diagnostics');
    }
    $shadowAccessibilityHtml = $shadowAccessibilityFragment->serialize();
    foreach ([
        'data-pandoc-shadowroot-mode="open"',
        'data-pandoc-shadowroot-aria-label="Review card"',
        'data-pandoc-shadowroot-aria-description="Hidden panel"',
        'data-pandoc-shadowroot-aria-describedby="caption shadow-note"',
        'data-pandoc-shadowroot-mode="closed"',
        'data-pandoc-shadowroot-aria-labelledby="headline"',
    ] as $expectedHtml) {
        if (!str_contains($shadowAccessibilityHtml, $expectedHtml)) {
            throw new RuntimeException('Expected declarative shadow-root accessibility metadata to survive review handoff: ' . $expectedHtml);
        }
    }
    if (str_contains($shadowAccessibilityHtml, '<template') || str_contains($shadowAccessibilityHtml, ' aria-label=')) {
        throw new RuntimeException('Expected declarative shadow-root template wrappers and live ARIA source attributes to stay stripped');
    }
    $shadowAccessibilityBlocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [
        $shadowAccessibilityFragment->toRawHtmlAst(['part' => '/migration/template-shadow-accessibility-review.html']),
    ]));
    if (!str_contains($shadowAccessibilityBlocks, 'data-pandoc-shadowroot-aria-label="Review card"')) {
        throw new RuntimeException('Expected WordPress raw HTML blocks to include shadow-root accessibility metadata');
    }
    try {
        XmlHtmlDom::loadXmlDocument(
            '<?xml-stylesheet href="https://example.invalid/review.xsl"?><review><item>bad</item></review>',
            'unsafe XML review packet'
        );

        throw new RuntimeException('Expected XML processing instruction to be rejected');
    } catch (InvalidArgumentException) {
    }
    if (!str_contains($blocks, '<!-- wp:html -->') || !str_contains($blocks, 'data-source="legacy-html"')) {
        throw new RuntimeException('Expected serialized fragment to hand off as a WordPress HTML block');
    }

    echo "xml/html5 dom handoff self-test ok\n";
    exit(0);
}

echo "XML/HTML5 DOM handoff for WordPress import:\n";
echo "fragmentHtml:\n" . $html . "\n";
echo "facadeFragmentHtml:\n" . $facadeHtml . "\n";
echo "namespacedReviewXml:\n" . $namespacedReviewFragment->serialize() . "\n";
echo "xmlPolicyReviewXml:\n" . $xmlPolicyReviewFragment->serialize() . "\n";
echo "shadowAccessibilityHtml:\n" . $shadowAccessibilityFragment->serialize() . "\n";
echo "wordpressBlocks:\n" . $blocks . "\n";
