<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\Html5DomFragment;
use PortLibs\Pandoc\WordPressBlockWriter;

$sourceHtml = <<<'HTML'
<section class="import-review">
  <h1 id="packet">Imported packet</h1>
  <p onclick="alert(1)">Manual<br>break before reviewer note.</p>
  <p><a href="javascript:alert(1)" data-source="legacy">Unsafe source link</a></p>
  <p><img src="https://example.test/preview.png" srcset="https://example.test/preview.png 1x, javascript:alert(1) 2x" alt="Preview"></p>
  <script>alert("legacy embed")</script>
</section>
HTML;

$fragment = Html5DomFragment::fromHtml($sourceHtml);
$document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
    $fragment->toRawHtmlAst(['part' => '/migration/review-fragment.html']),
]);
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    foreach ([
        '<section class="import-review">',
        '<p>Manual<br>break before reviewer note.</p>',
        '<a data-source="legacy">Unsafe source link</a>',
        '<img src="https://example.test/preview.png" alt="Preview">',
        '<!-- wp:html -->',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('HTML5 DOM handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    foreach (['onclick=', 'javascript:', '<script>'] as $blocked) {
        if (str_contains($blocks, $blocked)) {
            throw new RuntimeException('HTML5 DOM handoff self-test retained blocked content: ' . $blocked);
        }
    }

    if ($fragment->summary()['blockedTags'] !== ['script']) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not report blocked script tag');
    }
    if (!in_array('srcset', $fragment->summary()['filteredAttributes'], true)) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not report filtered srcset attribute');
    }

    echo "wordpress-html5-dom-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
