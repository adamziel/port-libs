<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

$fragment = <<<'HTML'
<article data-source="legacy-html">
  <h2>Source packet</h2>
  <p>Imported<br>line with &amp; entity</p>
  <svg viewBox="0 0 10 10" preserveAspectRatio="xMidYMid meet"><linearGradient id="review-gradient"><stop offset="0"></stop></linearGradient><textPath href="#review-label">Logo</textPath></svg>
  <math><mi definitionURL="#review-x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math>
  <figure><img src="media/review.png?rev=1&amp;post=42" alt="Review image"><figcaption>Review image</figcaption></figure>
  <style disabled>.legacy-note > strong { color: #600; }</style>
  <script type="application/json" data-review="metadata">{"source":"legacy <html> & notes"}</script>
</article>
HTML;

$dom = XmlHtmlDom::loadHtmlFragment($fragment, 'WordPress source HTML fragment');
$html = XmlHtmlDom::serializeHtmlFragment($dom);
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
    if (!str_contains($html, '<style disabled>.legacy-note > strong { color: #600; }</style>')) {
        throw new RuntimeException('Expected raw text style serialization for review packets');
    }
    if (!str_contains($html, '<script data-review="metadata" type="application/json">{"source":"legacy <html> & notes"}</script>')) {
        throw new RuntimeException('Expected raw text JSON script serialization for review metadata');
    }
    if (!str_contains($blocks, '<!-- wp:html -->') || !str_contains($blocks, 'data-source="legacy-html"')) {
        throw new RuntimeException('Expected serialized fragment to hand off as a WordPress HTML block');
    }

    echo "xml/html5 dom handoff self-test ok\n";
    exit(0);
}

echo "XML/HTML5 DOM handoff for WordPress import:\n";
echo "fragmentHtml:\n" . $html . "\n";
echo "wordpressBlocks:\n" . $blocks . "\n";
