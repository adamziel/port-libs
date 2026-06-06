<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\Html5DomFragment;
use PortLibs\Pandoc\WordPressBlockWriter;

$srcdoc = htmlspecialchars(
    '<base href="./embedded/"><article><h2>Embedded srcdoc packet</h2><a href="note.html">frame note</a><img src="frame.png" alt="Frame"><script>drop()</script></article>',
    ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
    'UTF-8'
);

$source = <<<HTML
<template><base href="https://inactive.example/assets/"><a href="template-note.html">Template fallback note</a></template>
<base href="https://source.example.test/import/posts/post-42.html?draft=1">
<article id="legacy-post-42" data-source="html-export">
  <h1>Imported source packet</h1>
  <!--review--->
  <p>AT&amp;T &lt;review&gt; text<br>keeps its line break with a <a href=" ../media/source.html#note&#10;">source note</a>.</p>
  <iframe srcdoc="$srcdoc"></iframe>
  <svg><desc><![CDATA[Legacy <source> & review notes]]></desc><defs><clipPath id="review-clip"><path d="M0 0"></path></clipPath></defs><g clip-path=" url( #review-clip ) " filter="url(javascript:alert(1))" mask="url(./masks/review.svg#mask)"><path d="M0 0" fill="url(#paint)" stroke="url( java&#10;script:alert(1) )"></path></g></svg>
  <figure><img src=" cover.png&#13;" srcset=" h&#9;ttps://cdn.example.test/cover.png?x=1&amp;y=2 01.00x, cover.png 1x, ../media/cover@2x.png 2x, javascript:alert(1) 3x" alt="Cover"><figcaption>Cover image</figcaption></figure>
  <picture><source srcset="data:image/png;base64,iVBORw0KGgo= 1x, data:text/html;base64,PHNjcmlwdD4= 2x" type="image/png"><source srcset="hero.avif 1x, javascript:alert(1) 2x" media="(min-width: 48em)" type="image/avif"><source srcset="mailto:bad@example.test 1x" media="(max-width: 47em)"><img src="fallback.jpg" alt="Responsive cover"></picture>
  <form><p><input type="submit" value="Send review"><input type="button" value="Preview packet"><input type="image" src="javascript:alert(1)" alt="Image submit"><input type="text" value="Hidden draft"></p></form>
</article>
HTML;

$fragment = Html5DomFragment::fromHtml($source);
$document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
    $fragment->toRawHtmlAst(['part' => '/migration/legacy-post-42.html']),
]);
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    foreach (['Template fallback note', 'Imported source packet', 'AT&T <review> text', 'source note', 'Embedded srcdoc packet', 'frame note', 'Cover image', 'Send review', 'Preview packet', 'Image submit'] as $textSnippet) {
        if (!str_contains($fragment->textContent(), $textSnippet)) {
            throw new RuntimeException('HTML5 DOM fragment self-test missing reviewer text: ' . $textSnippet);
        }
    }
    if ($fragment->baseUrl() !== 'https://source.example.test/import/posts/post-42.html?draft=1') {
        throw new RuntimeException('HTML5 DOM fragment self-test missing base URL');
    }
    foreach ([
        '<a href="https://source.example.test/import/posts/template-note.html">Template fallback note</a>',
        '<a href="https://source.example.test/import/media/source.html#note">source note</a>',
        '<article><h2>Embedded srcdoc packet</h2><a href="https://source.example.test/import/posts/embedded/note.html">frame note</a><img src="https://source.example.test/import/posts/embedded/frame.png" alt="Frame"></article>',
        '<svg><desc>Legacy &lt;source&gt; &amp; review notes</desc><defs><clipPath id="review-clip"><path d="M0 0"></path></clipPath></defs><g clip-path="url(#review-clip)" mask="url(https://source.example.test/import/posts/masks/review.svg#mask)"><path d="M0 0" fill="url(#paint)"></path></g></svg>',
        '<img src="https://source.example.test/import/posts/cover.png" srcset="https://cdn.example.test/cover.png?x=1&amp;y=2 1x, https://source.example.test/import/posts/cover.png 1x, https://source.example.test/import/media/cover@2x.png 2x" alt="Cover">',
        '<source srcset="data:image/png;base64,iVBORw0KGgo= 1x" type="image/png">',
        '<source srcset="https://source.example.test/import/posts/hero.avif 1x" media="(min-width: 48em)" type="image/avif">',
        '<img src="https://source.example.test/import/posts/fallback.jpg" alt="Responsive cover">',
        '<p>Send reviewPreview packetImage submit</p>',
        '<!--review- -->',
        '<!-- wp:html -->',
    ] as $expected) {
        if (!str_contains($blocks, $expected)) {
            throw new RuntimeException('HTML5 DOM fragment self-test missing expected snippet: ' . $expected);
        }
    }
    foreach (['<base', '<iframe', 'srcdoc=', '<script', '<input', 'javascript:', 'inactive.example', 'mailto:bad@example.test', 'data:text/html', '(max-width: 47em)', '<![CDATA[', '--->', 'Hidden draft'] as $blocked) {
        if (str_contains($blocks, $blocked)) {
            throw new RuntimeException('HTML5 DOM fragment self-test retained blocked content: ' . $blocked);
        }
    }
    $normalizedUrlDiagnostics = array_values(array_filter(
        $fragment->diagnosticCodes(),
        static fn (string $code): bool => $code === 'normalized-url'
    ));
    if (count($normalizedUrlDiagnostics) !== 5) {
        throw new RuntimeException('HTML5 DOM fragment self-test expected five normalized URL diagnostics');
    }
    if (!in_array('empty-source', $fragment->diagnosticCodes(), true)) {
        throw new RuntimeException('HTML5 DOM fragment self-test expected empty picture source diagnostic');
    }
    foreach (['filter=', 'stroke='] as $blocked) {
        if (str_contains($blocks, $blocked)) {
            throw new RuntimeException('HTML5 DOM fragment self-test retained unsafe SVG resource attribute: ' . $blocked);
        }
    }

    echo "html5 dom fragment handoff self-test ok\n";
    exit(0);
}

echo "HTML5 DOM fragment handoff for WordPress review:\n";
echo 'text=' . $fragment->textContent() . "\n";
echo 'base=' . ($fragment->baseUrl() ?? '') . "\n";
echo 'summary=' . json_encode($fragment->summary(), JSON_UNESCAPED_SLASHES) . "\n";
echo "blocks:\n" . $blocks . "\n";
