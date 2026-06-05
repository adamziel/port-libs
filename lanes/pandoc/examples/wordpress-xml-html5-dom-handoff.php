<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\Html5DomFragment;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

$fragment = <<<'HTML'
<article data-source="legacy-html">
  <h2>Source packet</h2>
  <p>Imported<br>line with &amp; entity</p>
  <svg viewBox="0 0 10 10" preserveAspectRatio="xMidYMid meet"><linearGradient id="review-gradient"><stop offset="0"></stop></linearGradient><textPath href="#review-label">Logo</textPath></svg>
  <math><mi definitionURL="#review-x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math>
  <figure><img src="media/review.png?rev=1&amp;post=42" alt="Review image"><figcaption>Review image</figcaption></figure>
  <textarea data-review="legacy-field">Reviewer <script>alert(1)</script> &amp; <b>note</b></textarea>
  <style disabled>.legacy-note > strong { color: #600; }</style>
  <script type="application/json" data-review="metadata">{"source":"legacy <html> & notes"}</script>
</article>
HTML;

$dom = XmlHtmlDom::loadHtmlFragment($fragment, 'WordPress source HTML fragment');
$html = XmlHtmlDom::serializeHtmlFragment($dom);
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
$document = new AstNode('document', [], [
    new AstNode('raw_html', ['format' => 'html', 'html' => $html]),
]);
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    if (!str_contains($html, '<br>') || str_contains($html, '</br>')) {
        throw new RuntimeException('Expected HTML5 br void serialization');
    }
    if (!str_contains($html, '<img alt="Review image" src="media/review.png?rev=1&amp;post=42">')) {
        throw new RuntimeException('Expected deterministic img attribute escaping and void serialization');
    }
    if (!str_contains($html, '<svg preserveAspectRatio="xMidYMid meet" viewBox="0 0 10 10"><linearGradient id="review-gradient"><stop offset="0"></stop></linearGradient><textPath href="#review-label">Logo</textPath></svg>')) {
        throw new RuntimeException('Expected SVG foreign-content casing to survive review handoff');
    }
    if (!str_contains($html, '<math><mi definitionURL="#review-x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math>')) {
        throw new RuntimeException('Expected MathML foreign-content casing to survive review handoff');
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
echo "namespacedReviewXml:\n" . $namespacedReviewFragment->serialize() . "\n";
echo "xmlPolicyReviewXml:\n" . $xmlPolicyReviewFragment->serialize() . "\n";
echo "wordpressBlocks:\n" . $blocks . "\n";
