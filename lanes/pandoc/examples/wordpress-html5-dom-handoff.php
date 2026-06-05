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
  <p><a href="/wp-admin/post.php?post=42" ping="https://tracker.example.test/ping javascript:alert(1)" data-source="legacy">Tracked source link</a></p>
  <div action="/safe-submit" formaction="javascript:alert(1)" longdesc="https://example.test/longdesc" background="mailto:review@example.test">Extended URL attributes</div>
  <p><img src="https://example.test/preview.png" srcset="https://example.test/preview.png 1x, /uploads/preview@2x.png 02.00x, javascript:alert(1) 3x, /uploads/bad.png 0w" alt="Preview"></p>
  <p><a href="mailto:review@example.test">Mail reviewer</a><img src="mailto:review@example.test" alt="Unsafe media link"></p>
  <form action="/submit" onsubmit="alert(1)"><p>Reviewer choice <input name="status" value="draft"><button formaction="javascript:alert(1)">Keep visible label</button></p><p><select><option>Draft</option><option>Final</option></select></p><textarea>Visible reviewer note</textarea></form>
  <iframe src="javascript:alert(1)">Iframe fallback <b>caption</b><script>drop()</script></iframe>
  <object data="javascript:alert(1)" type="application/x-shockwave-flash"><param name="movie" value="legacy.swf"><p>Object fallback <a href="/review">review</a></p></object>
  <applet code="Legacy.class"><span>Applet fallback</span></applet>
  <xmp data-source="legacy-raw">Reviewer <script>alert(1)</script> &amp; <b>source</b></xmp>
  <table class="legacy-table"><caption>Review rows</caption><p data-review="loose-table">Loose table note</p><tr><td>Cell A</td></tr>orphan table text<tr><td>Cell B</td></tr></table>
  <details open="open"><summary>Media review</summary><video controls="" muted playsinline loop poster="tel:+15550100"><source src="mailto:review@example.test" type="video/mp4"><source src="/uploads/preview.mp4" type="video/mp4"></video></details>
  <figure class="foreign-content"><svg><foreignObject><div viewBox="html attr"><linearGradient>HTML child</linearGradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject></svg><math><annotation-xml encoding="text/html"><div viewBox="math html"><textPath>HTML text</textPath></div></annotation-xml><annotation-xml encoding="MathML-Content"><ci definitionURL="#x">x</ci></annotation-xml></math></figure>
  <script>alert("legacy embed")</script>
</section>
<plaintext data-source="legacy-plaintext">Plain reviewer <script>alert(1)</script> &amp; <b>tail</b></plaintext><p>suppressed tail</p>
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
        '<a href="/wp-admin/post.php?post=42" data-source="legacy">Tracked source link</a>',
        '<div action="/safe-submit" longdesc="https://example.test/longdesc">Extended URL attributes</div>',
        '<img src="https://example.test/preview.png" srcset="https://example.test/preview.png 1x, /uploads/preview@2x.png 2x" alt="Preview">',
        '<a href="mailto:review@example.test">Mail reviewer</a><img alt="Unsafe media link">',
        '<p>Reviewer choice Keep visible label</p><p>DraftFinal</p>Visible reviewer note',
        'Iframe fallback <b>caption</b>',
        '<p>Object fallback <a href="/review">review</a></p>',
        '<span>Applet fallback</span>',
        'Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;b&gt;source&lt;/b&gt;',
        '<p data-review="loose-table">Loose table note</p>orphan table text<table class="legacy-table"><caption>Review rows</caption><tr><td>Cell A</td></tr><tr><td>Cell B</td></tr></table>',
        '<details open><summary>Media review</summary><video controls muted playsinline loop><source type="video/mp4"><source src="/uploads/preview.mp4" type="video/mp4"></video></details>',
        '<foreignObject><div viewbox="html attr"><lineargradient>HTML child</lineargradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject>',
        '<annotation-xml encoding="text/html"><div viewbox="math html"><textpath>HTML text</textpath></div></annotation-xml>',
        'Plain reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;b&gt;tail&lt;/b&gt;&lt;/plaintext&gt;&lt;p&gt;suppressed tail&lt;/p&gt;',
        '<!-- wp:html -->',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('HTML5 DOM handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    foreach (['onclick=', 'ping=', 'formaction=', 'background="mailto:', 'javascript:', 'src="mailto:', 'poster="tel:', '<form', '<input', '<button', '<select', '<textarea', '<iframe', '<object', '<applet', '<param', '<xmp', '<plaintext', '<script>', '<p>suppressed tail</p>', 'open="open"', 'controls=""', 'viewBox="html attr"', '<textPath>HTML text</textPath>'] as $blocked) {
        if (str_contains($blocks, $blocked)) {
            throw new RuntimeException('HTML5 DOM handoff self-test retained blocked content: ' . $blocked);
        }
    }

    if ($fragment->summary()['blockedTags'] !== ['applet', 'button', 'form', 'iframe', 'input', 'object', 'option', 'param', 'plaintext', 'script', 'select', 'textarea', 'xmp']) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not report blocked form/embed/script/plaintext tags');
    }
    if (!in_array('srcset', $fragment->summary()['filteredAttributes'], true)) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not report filtered srcset attribute');
    }
    foreach (['ping', 'formaction', 'background'] as $filteredAttribute) {
        if (!in_array($filteredAttribute, $fragment->summary()['filteredAttributes'], true)) {
            throw new RuntimeException('HTML5 DOM handoff self-test did not report filtered ' . $filteredAttribute . ' attribute');
        }
    }
    $tableFosterDiagnostics = array_values(array_filter(
        $fragment->diagnosticCodes(),
        static fn (string $code): bool => $code === 'table-foster-parented-content'
    ));
    if (count($tableFosterDiagnostics) !== 2) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not report foster-parented table content');
    }
    foreach ([
        '<!DOCTYPE html><p>legacy doctype</p>',
        '<!ENTITY reviewer SYSTEM "file:///etc/passwd"><p>&reviewer;</p>',
        '<?xml-stylesheet href="https://example.invalid/review.xsl"?><p>stylesheet</p>',
    ] as $unsafeFragment) {
        try {
            Html5DomFragment::fromHtml($unsafeFragment);
        } catch (InvalidArgumentException) {
            continue;
        }

        throw new RuntimeException('HTML5 DOM handoff self-test accepted unsafe fragment declaration');
    }

    echo "wordpress-html5-dom-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
