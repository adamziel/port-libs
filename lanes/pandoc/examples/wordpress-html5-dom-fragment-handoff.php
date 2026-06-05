<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\Html5DomFragment;
use PortLibs\Pandoc\WordPressBlockWriter;

$source = <<<'HTML'
<base href="https://source.example.test/import/posts/post-42.html?draft=1">
<article id="legacy-post-42" data-source="html-export">
  <h1>Imported source packet</h1>
  <!--review--->
  <p>AT&amp;T &lt;review&gt; text<br>keeps its line break with a <a href=" ../media/source.html#note&#10;">source note</a>.</p>
  <figure><img src=" cover.png&#13;" srcset=" cover.png 1x, ../media/cover@2x.png 2x, javascript:alert(1) 3x" alt="Cover"><figcaption>Cover image</figcaption></figure>
  <picture><source srcset="hero.avif 1x, javascript:alert(1) 2x" media="(min-width: 48em)" type="image/avif"><source srcset="mailto:bad@example.test 1x" media="(max-width: 47em)"><img src="fallback.jpg" alt="Responsive cover"></picture>
</article>
HTML;

$fragment = Html5DomFragment::fromHtml($source);
$document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
    $fragment->toRawHtmlAst(['part' => '/migration/legacy-post-42.html']),
]);
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    foreach (['Imported source packet', 'AT&T <review> text', 'source note', 'Cover image'] as $textSnippet) {
        if (!str_contains($fragment->textContent(), $textSnippet)) {
            throw new RuntimeException('HTML5 DOM fragment self-test missing reviewer text: ' . $textSnippet);
        }
    }
    if ($fragment->baseUrl() !== 'https://source.example.test/import/posts/post-42.html?draft=1') {
        throw new RuntimeException('HTML5 DOM fragment self-test missing base URL');
    }
    foreach ([
        '<a href="https://source.example.test/import/media/source.html#note">source note</a>',
        '<img src="https://source.example.test/import/posts/cover.png" srcset="https://source.example.test/import/posts/cover.png 1x, https://source.example.test/import/media/cover@2x.png 2x" alt="Cover">',
        '<source srcset="https://source.example.test/import/posts/hero.avif 1x" media="(min-width: 48em)" type="image/avif">',
        '<img src="https://source.example.test/import/posts/fallback.jpg" alt="Responsive cover">',
        '<!--review- -->',
        '<!-- wp:html -->',
    ] as $expected) {
        if (!str_contains($blocks, $expected)) {
            throw new RuntimeException('HTML5 DOM fragment self-test missing expected snippet: ' . $expected);
        }
    }
    foreach (['<base', 'javascript:', 'mailto:bad@example.test', '(max-width: 47em)', '--->'] as $blocked) {
        if (str_contains($blocks, $blocked)) {
            throw new RuntimeException('HTML5 DOM fragment self-test retained blocked content: ' . $blocked);
        }
    }
    $normalizedUrlDiagnostics = array_values(array_filter(
        $fragment->diagnosticCodes(),
        static fn (string $code): bool => $code === 'normalized-url'
    ));
    if (count($normalizedUrlDiagnostics) !== 2) {
        throw new RuntimeException('HTML5 DOM fragment self-test expected two normalized URL diagnostics');
    }
    if (!in_array('empty-source', $fragment->diagnosticCodes(), true)) {
        throw new RuntimeException('HTML5 DOM fragment self-test expected empty picture source diagnostic');
    }

    echo "html5 dom fragment handoff self-test ok\n";
    exit(0);
}

echo "HTML5 DOM fragment handoff for WordPress review:\n";
echo 'text=' . $fragment->textContent() . "\n";
echo 'base=' . ($fragment->baseUrl() ?? '') . "\n";
echo 'summary=' . json_encode($fragment->summary(), JSON_UNESCAPED_SLASHES) . "\n";
echo "blocks:\n" . $blocks . "\n";
