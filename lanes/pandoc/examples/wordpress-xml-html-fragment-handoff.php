<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\XmlHtmlDomFragment;

$sourceHtml = <<<'HTML'
<section data-post="42" onclick="steal()">
  <h2>Legacy HTML packet</h2>
  <p>Reviewer &amp; editor<br/>handoff <a href="https://example.test/source?post=42&amp;stage=review">source</a>.</p>
  <img src="/uploads/legacy-hero.jpg" alt="Legacy hero" onerror="bad()">
  <script>alert("unsafe")</script>
</section>
HTML;

$fragment = XmlHtmlDomFragment::parseHtml($sourceHtml);
$wordpressBlock = '<!-- wp:html -->' . "\n" . $fragment->serializeHtml() . "\n" . '<!-- /wp:html -->';

if (in_array('--self-test', $argv, true)) {
    foreach ([
        '<section data-post="42">',
        '<br>',
        '<a href="https://example.test/source?post=42&amp;stage=review">source</a>',
        '<img src="/uploads/legacy-hero.jpg" alt="Legacy hero">',
        '<!-- wp:html -->',
    ] as $needle) {
        if (!str_contains($wordpressBlock, $needle)) {
            throw new RuntimeException('HTML fragment handoff self-test missing: ' . $needle);
        }
    }

    foreach (['onclick=', 'onerror=', '<script', 'javascript:'] as $needle) {
        if (str_contains($wordpressBlock, $needle)) {
            throw new RuntimeException('HTML fragment handoff self-test retained unsafe fragment: ' . $needle);
        }
    }

    if (count($fragment->diagnostics()) !== 3) {
        throw new RuntimeException('HTML fragment handoff self-test expected three dropped-content diagnostics');
    }

    echo "xml/html fragment handoff self-test ok\n";
    exit(0);
}

echo json_encode([
    'elementNames' => $fragment->elementNames(),
    'diagnostics' => $fragment->diagnostics(),
    'wordpressBlock' => $wordpressBlock,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
