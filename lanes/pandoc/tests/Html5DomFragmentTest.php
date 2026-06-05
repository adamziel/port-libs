<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\Html5DomFragment;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'repairs HTML5 fragments and serializes a deterministic normalized tree' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml('<section><p>One<br>Two<p>Three &amp; four</section>');
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();

        $t->same('<section><p>One<br>Two</p><p>Three &amp; four</p></section>', $fragment->serialize());
        $t->same('OneTwoThree & four', $fragment->textContent());
        $t->same('html', $summary['mode']);
        $t->same(1, $summary['topLevelNodes']);
        $t->same(4, $summary['elements']);
        $t->same(3, $summary['textNodes']);
        $t->same(['br', 'p', 'section'], $summary['elementNames']);
        $t->same('element', $nodes[0]['type']);
        $t->same('section', $nodes[0]['name']);
        $t->same(2, count($nodes[0]['children']));
        $t->same('p', $nodes[0]['children'][0]['name']);
        $t->same('br', $nodes[0]['children'][0]['children'][1]['name']);
        $t->same(['libxml-repair'], $fragment->diagnosticCodes());
    },
    'normalizes unsafe comment boundaries before sanitized raw html handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<!--review---><p>Imported comment boundary</p><!--source -- boundary--><!--triple---tail--->'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/comment-boundary-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $html = $fragment->serialize();

        $t->same('<!--review- --><p>Imported comment boundary</p><!--source - - boundary--><!--triple- - -tail- -->', $html);
        $t->contains($html, $blocks);
        $t->same(4, $summary['topLevelNodes']);
        $t->same(1, $summary['elements']);
        $t->same(3, $summary['comments']);
        $t->same(['p'], $summary['elementNames']);
        $t->same('comment', $nodes[0]['type']);
        $t->same('review-', $nodes[0]['text']);
        $t->same('p', $nodes[1]['name']);
        $t->same('comment', $nodes[2]['type']);
        $t->same('source -- boundary', $nodes[2]['text']);
        $t->same('triple---tail-', $nodes[3]['text']);
        $t->same('/migration/comment-boundary-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '--->'), 'Expected trailing hyphen comments to be padded before the closing delimiter');
        $t->true(!str_contains($html, 'source -- boundary'), 'Expected interior comment delimiters to be split before serialization');
    },
    'filters active tags unsafe attributes and unsafe URLs before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<p onclick="alert(1)">'
            . '<a href=" javascript:alert(1) " data-source="legacy" onmouseover="x">Bad</a>'
            . '<img src="https://example.test/a.png" alt="A" srcset="javascript:bad">'
            . '<script>alert(1)</script>'
            . '<span style="color:red" data-review="yes">Safe</span>'
            . '</p>'
        );
        $summary = $fragment->summary();
        $ast = $fragment->toRawHtmlAst(['source' => 'wordpress-import']);

        $t->same('<p><a data-source="legacy">Bad</a><img src="https://example.test/a.png" alt="A"><span data-review="yes">Safe</span></p>', $fragment->serialize());
        $t->same('BadSafe', $fragment->textContent());
        $t->same(['a', 'img', 'p', 'span'], $summary['elementNames']);
        $t->same(['script'], $summary['blockedTags']);
        $t->same(['href', 'onclick', 'onmouseover', 'srcset', 'style'], $summary['filteredAttributes']);
        $t->same(6, $summary['diagnostics']);
        $t->same(['unsafe-attribute', 'unsafe-url', 'unsafe-attribute', 'unsafe-url', 'blocked-tag', 'unsafe-attribute'], $fragment->diagnosticCodes());
        $t->same('raw_html', $ast->type);
        $t->same('html', $ast->attr('format'));
        $t->same('wordpress-import', $ast->attr('source'));
        $t->same($fragment->serialize(), $ast->attr('html'));
        $t->same(6, count($ast->attr('diagnostics')));
    },
    'filters non-fetch media URLs while preserving reviewer mail and phone links' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<p>'
            . '<a href="mailto:review@example.test">Mail reviewer</a>'
            . '<a href="tel:+15550100">Call reviewer</a>'
            . '<img src="mailto:cover@example.test" alt="Mail image">'
            . '<img src="/media/cover.png" alt="Safe image">'
            . '<video poster="tel:+15550100"><source src="mailto:video@example.test" type="video/mp4"><source src="https://cdn.example.test/video.mp4" type="video/mp4"></video>'
            . '</p>'
        );
        $summary = $fragment->summary();
        $html = $fragment->serialize();

        $t->contains('<a href="mailto:review@example.test">Mail reviewer</a>', $html);
        $t->contains('<a href="tel:+15550100">Call reviewer</a>', $html);
        $t->contains('<img alt="Mail image">', $html);
        $t->contains('<img src="/media/cover.png" alt="Safe image">', $html);
        $t->contains('<video><source type="video/mp4"><source src="https://cdn.example.test/video.mp4" type="video/mp4"></video>', $html);
        $t->same(['poster', 'src'], $summary['filteredAttributes']);
        $diagnosticCodes = $fragment->diagnosticCodes();
        $unsafeUrlDiagnostics = array_values(array_filter($diagnosticCodes, static fn (string $code): bool => $code === 'unsafe-url'));
        $t->same(3, count($unsafeUrlDiagnostics));
        $t->true(in_array('libxml-repair', $diagnosticCodes, true), 'Expected libxml repair diagnostics for HTML5 media elements');
        $t->true(!str_contains($html, 'src="mailto:'), 'Expected mailto media src URLs to be removed');
        $t->true(!str_contains($html, 'poster="tel:'), 'Expected tel poster URLs to be removed');
    },
    'filters extended URL attributes and ping side effects before review handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<section>'
            . '<a href="/review" ping="https://tracker.example.test/ping javascript:alert(1)">Tracked review link</a>'
            . '<div action="/safe-submit" formaction="javascript:alert(1)" longdesc="https://example.test/longdesc" background="mailto:review@example.test">URL attrs</div>'
            . '<figure longdesc=" javascript:alert(1) " background="/media/bg.png"><img src="/media/cover.png" longdesc="/media/cover-longdesc.html" alt="Cover"></figure>'
            . '</section>'
        );
        $summary = $fragment->summary();
        $html = $fragment->serialize();
        $nodes = $fragment->nodes();

        $t->contains('<a href="/review">Tracked review link</a>', $html);
        $t->contains('<div action="/safe-submit" longdesc="https://example.test/longdesc">URL attrs</div>', $html);
        $t->contains('<figure background="/media/bg.png"><img src="/media/cover.png" longdesc="/media/cover-longdesc.html" alt="Cover"></figure>', $html);
        $t->same(['background', 'formaction', 'longdesc', 'ping'], $summary['filteredAttributes']);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $t->same(['unsafe-attribute', 'unsafe-url', 'unsafe-url', 'unsafe-url'], $policyDiagnostics);
        $t->same('/safe-submit', $nodes[0]['children'][1]['attrs']['action']);
        $t->same('https://example.test/longdesc', $nodes[0]['children'][1]['attrs']['longdesc']);
        $t->same('/media/bg.png', $nodes[0]['children'][2]['attrs']['background']);
        $t->true(!str_contains($html, 'ping='), 'Expected ping side-effect URLs to be stripped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected javascript URLs to be stripped from extended attributes');
        $t->true(!str_contains($html, 'background="mailto:'), 'Expected mailto image-fetch URL to be stripped');
    },
    'normalizes control-separated URL attributes before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href=" https://source.example.test/import/posts/post.html ">'
            . '<p>'
            . '<a href=" h&#9;ttps://example.test/review ">Absolute source</a>'
            . '<a href=" ../media/source.html#note&#10;">Relative source</a>'
            . '<img src=" ./cover.png&#13;" srcset=" ./cover.png 1x, ../media/cover@2x.png 2x " alt="Cover">'
            . '</p>'
            . '<blockquote cite=" ?review=1&#10;">Quoted source</blockquote>'
            . '<a href="java&#10;script:alert(1)">Bad source</a>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/url-normalization-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $html = $fragment->serialize();

        $expected = '<p><a href="https://example.test/review">Absolute source</a><a href="https://source.example.test/import/media/source.html#note">Relative source</a><img src="https://source.example.test/import/posts/cover.png" srcset="https://source.example.test/import/posts/cover.png 1x, https://source.example.test/import/media/cover@2x.png 2x" alt="Cover"></p><blockquote cite="https://source.example.test/import/posts/post.html?review=1">Quoted source</blockquote><a>Bad source</a>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same(['base'], $summary['blockedTags']);
        $t->same(['href'], $summary['filteredAttributes']);
        $t->same(6, $summary['diagnostics']);
        $t->same(['blocked-tag', 'normalized-url', 'normalized-url', 'normalized-url', 'normalized-url', 'unsafe-url'], $fragment->diagnosticCodes());
        $t->same('https://example.test/review', $nodes[0]['children'][0]['attrs']['href']);
        $t->same('https://source.example.test/import/media/source.html#note', $nodes[0]['children'][1]['attrs']['href']);
        $t->same('https://source.example.test/import/posts/cover.png', $nodes[0]['children'][2]['attrs']['src']);
        $t->same('https://source.example.test/import/posts/post.html?review=1', $nodes[1]['attrs']['cite']);
        $t->same('/migration/url-normalization-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, "h\t"), 'Expected control-separated safe schemes to be canonicalized');
        $t->true(!str_contains($html, "\n"), 'Expected newline-containing URL attributes to be canonicalized');
        $t->true(!str_contains($html, 'javascript:'), 'Expected control-separated unsafe schemes to be stripped');
    },
    'unwraps visible form content while dropping active controls before review handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<form action="/submit" onsubmit="alert(1)">'
            . '<p>Name <input name="name" value="Ada"><button formaction="javascript:alert(1)">Send review</button></p>'
            . '<p><select name="status"><option selected>Draft</option><option>Final</option></select></p>'
            . '<textarea name="notes">Visible reviewer note</textarea>'
            . '</form><p>after</p>'
        );
        $summary = $fragment->summary();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/form-review-fragment.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('<p>Name Send review</p><p>DraftFinal</p>Visible reviewer note<p>after</p>', $html);
        $t->same('Name Send reviewDraftFinalVisible reviewer noteafter', $fragment->textContent());
        $t->same(['p'], $summary['elementNames']);
        $t->same(['button', 'form', 'input', 'option', 'select', 'textarea'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(7, $summary['diagnostics']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag'], $fragment->diagnosticCodes());
        $t->contains('Visible reviewer note', $blocks);
        $t->same('/migration/form-review-fragment.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<form'), 'Expected form wrapper to be stripped');
        $t->true(!str_contains($html, '<input'), 'Expected input control to be dropped');
        $t->true(!str_contains($html, '<button'), 'Expected button wrapper to be stripped');
        $t->true(!str_contains($html, '<select'), 'Expected select wrapper to be stripped');
        $t->true(!str_contains($html, '<textarea'), 'Expected textarea wrapper to be stripped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected form-side javascript URLs to be stripped');
    },
    'unwraps active embed fallback content while dropping unsafe containers' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<iframe src="javascript:alert(1)">Fallback <b>caption</b><script>drop()</script></iframe>'
            . '<object data="javascript:alert(1)" type="application/x-shockwave-flash"><param name="movie" value="legacy.swf"><p>Object fallback <a href="/review">review</a></p></object>'
            . '<applet code="Legacy.class"><span>Applet fallback</span></applet><p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/embed-fallback-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = 'Fallback <b>caption</b><p>Object fallback <a href="/review">review</a></p><span>Applet fallback</span><p>after</p>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Fallback captionObject fallback reviewApplet fallbackafter', $fragment->textContent());
        $t->same(['a', 'b', 'p', 'span'], $summary['elementNames']);
        $t->same(['applet', 'iframe', 'object', 'param', 'script'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(5, $summary['diagnostics']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag'], $fragment->diagnosticCodes());
        $t->same('text', $nodes[0]['type']);
        $t->same('Fallback ', $nodes[0]['text']);
        $t->same('b', $nodes[1]['name']);
        $t->same('p', $nodes[2]['name']);
        $t->same('span', $nodes[3]['name']);
        $t->same('p', $nodes[4]['name']);
        $t->same('/migration/embed-fallback-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<iframe'), 'Expected iframe wrapper to be stripped');
        $t->true(!str_contains($html, '<object'), 'Expected object wrapper to be stripped');
        $t->true(!str_contains($html, '<applet'), 'Expected applet wrapper to be stripped');
        $t->true(!str_contains($html, '<param'), 'Expected object param metadata to be dropped');
        $t->true(!str_contains($html, '<script'), 'Expected active fallback script to be dropped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe embed URLs to be stripped with their wrappers');
        $t->true(!str_contains($blocks, '<iframe'), 'Expected WordPress blocks to omit iframe wrapper');
        $t->true(!str_contains($blocks, '<object'), 'Expected WordPress blocks to omit object wrapper');
        $t->true(!str_contains($blocks, '<applet'), 'Expected WordPress blocks to omit applet wrapper');
    },
    'unwraps noscript fallback content while dropping unsafe container before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<noscript><p>Script-disabled fallback <a href="/review">review</a><a href="javascript:alert(1)">bad</a></p>'
            . '<img src="/uploads/fallback.png" alt="Fallback"><script>drop()</script></noscript><p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/noscript-fallback-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<p>Script-disabled fallback <a href="/review">review</a><a>bad</a></p><img src="/uploads/fallback.png" alt="Fallback"><p>after</p>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Script-disabled fallback reviewbadafter', $fragment->textContent());
        $t->same(['a', 'img', 'p'], $summary['elementNames']);
        $t->same(['noscript', 'script'], $summary['blockedTags']);
        $t->same(['href'], $summary['filteredAttributes']);
        $t->same(3, $summary['diagnostics']);
        $t->same(['blocked-tag', 'unsafe-url', 'blocked-tag'], $fragment->diagnosticCodes());
        $t->same('p', $nodes[0]['name']);
        $t->same('img', $nodes[1]['name']);
        $t->same('p', $nodes[2]['name']);
        $t->same('/migration/noscript-fallback-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<noscript'), 'Expected noscript wrapper to be stripped');
        $t->true(!str_contains($html, '<script'), 'Expected nested active script to be dropped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe noscript fallback URL to be stripped');
        $t->true(!str_contains($blocks, '<noscript'), 'Expected WordPress blocks to omit noscript wrapper');
    },
    'unwraps template inert content while keeping reviewer-visible children' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<template data-source="legacy-hidden"><p>Template fallback <a href="/review">review</a><a href="javascript:alert(1)">bad</a></p>'
            . '<img src="/uploads/template.png" alt="Template"><script>drop()</script></template><p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/template-fallback-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<p>Template fallback <a href="/review">review</a><a>bad</a></p><img src="/uploads/template.png" alt="Template"><p>after</p>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Template fallback reviewbadafter', $fragment->textContent());
        $t->same(['a', 'img', 'p'], $summary['elementNames']);
        $t->same(['script', 'template'], $summary['blockedTags']);
        $t->same(['href'], $summary['filteredAttributes']);
        $t->same(4, $summary['diagnostics']);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $t->same(['blocked-tag', 'unsafe-url', 'blocked-tag'], $policyDiagnostics);
        $t->same('p', $nodes[0]['name']);
        $t->same('img', $nodes[1]['name']);
        $t->same('p', $nodes[2]['name']);
        $t->same('/migration/template-fallback-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<template'), 'Expected template wrapper to be stripped');
        $t->true(!str_contains($html, '<script'), 'Expected nested active script to be dropped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe template fallback URL to be stripped');
        $t->true(!str_contains($blocks, '<template'), 'Expected WordPress blocks to omit template wrapper');
    },
    'filters mixed unsafe srcset candidates before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<p>'
            . '<img src="https://cdn.example.test/cover.png" srcset="https://cdn.example.test/cover.png 1x, /media/cover@2x.png 2x, ./cover-wide.webp 640w" alt="Safe">'
            . '<img src="https://cdn.example.test/mixed.png" srcset="https://cdn.example.test/mixed.png 1x, javascript:alert(1) 2x" alt="Mixed">'
            . '<img src="/media/fallback.png" srcset="/media/hero.webp 1x, mailto:review@example.test 2x" alt="Fallback">'
            . '</p>'
        );
        $summary = $fragment->summary();
        $html = $fragment->serialize();

        $t->contains('<img src="https://cdn.example.test/cover.png" srcset="https://cdn.example.test/cover.png 1x, /media/cover@2x.png 2x, ./cover-wide.webp 640w" alt="Safe">', $html);
        $t->contains('<img src="https://cdn.example.test/mixed.png" srcset="https://cdn.example.test/mixed.png 1x" alt="Mixed">', $html);
        $t->contains('<img src="/media/fallback.png" srcset="/media/hero.webp 1x" alt="Fallback">', $html);
        $t->same(['srcset'], $summary['filteredAttributes']);
        $t->same(['unsafe-url', 'unsafe-url'], $fragment->diagnosticCodes());
    },
    'normalizes srcset width and density descriptors while dropping invalid candidates' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<p>'
            . '<img src="/media/hero.png" srcset=" ./hero-0640.webp 0640w, https://cdn.example.test/hero@2x.webp 02.00x, /media/fallback.jpg, javascript:alert(1) 4x, /media/zero.webp 0w, /media/mixed.webp 1x 640w, /media/uppercase.webp 1.50X " alt="Hero">'
            . '</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();

        $expectedSrcset = './hero-0640.webp 640w, https://cdn.example.test/hero@2x.webp 2x, /media/fallback.jpg, /media/uppercase.webp 1.5x';
        $t->contains('<img src="/media/hero.png" srcset="' . $expectedSrcset . '" alt="Hero">', $html);
        $t->same($expectedSrcset, $nodes[0]['children'][0]['attrs']['srcset']);
        $t->same(['srcset'], $summary['filteredAttributes']);
        $t->same(3, $summary['diagnostics']);
        $t->same(['unsafe-url', 'invalid-srcset-descriptor', 'invalid-srcset-descriptor'], $fragment->diagnosticCodes());
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe srcset URL candidate to be removed');
        $t->true(!str_contains($html, 'zero.webp'), 'Expected zero-width srcset candidate to be removed');
        $t->true(!str_contains($html, 'mixed.webp'), 'Expected mixed descriptor srcset candidate to be removed');
    },
    'serializes html5 boolean attributes without redundant values for review media' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<details open="open"><summary>Review packet</summary>'
            . '<video controls="" muted playsinline loop poster="/media/cover.jpg"><source src="/media/review.mp4" type="video/mp4"></video>'
            . '</details>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/media-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<details open><summary>Review packet</summary><video controls muted playsinline loop poster="/media/cover.jpg"><source src="/media/review.mp4" type="video/mp4"></video></details>';
        $t->same($expected, $fragment->serialize());
        $t->contains($expected, $blocks);
        $t->same(['details', 'source', 'summary', 'video'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(['open' => 'open'], $nodes[0]['attrs']);
        $t->same([
            'controls' => '',
            'muted' => '',
            'playsinline' => '',
            'loop' => '',
            'poster' => '/media/cover.jpg',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same('/migration/media-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($fragment->serialize(), 'open="open"'), 'Expected open to serialize as an HTML5 boolean attribute');
        $t->true(!str_contains($fragment->serialize(), 'controls=""'), 'Expected controls to serialize as an HTML5 boolean attribute');
    },
    'parses XML fragments strictly and rejects DTD entity expansion inputs' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromXml('<root xml:lang="en"><br/><custom data-id="42">A &amp; B</custom></root><note/>');
        $summary = $fragment->summary();

        $t->same('<root xml:lang="en"><br/><custom data-id="42">A &amp; B</custom></root><note/>', $fragment->serialize());
        $t->same('A & B', $fragment->textContent());
        $t->same('xml', $summary['mode']);
        $t->same(2, $summary['topLevelNodes']);
        $t->same(4, $summary['elements']);
        $t->same(['br', 'custom', 'note', 'root'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same([], $fragment->diagnosticCodes());
        $t->throws(\InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromXml('<root><unclosed></root>'));
        $t->throws(\InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromXml('<!DOCTYPE root [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><root>&xxe;</root>'));
    },
    'normalizes svg and mathml foreign content for raw html review packets' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<aside><svg viewBox="0 0 10 10" preserveAspectRatio="xMidYMid meet"><linearGradient id="g"><stop offset="0"></stop></linearGradient><textPath href="#label">Logo</textPath></svg>'
                . '<math><mi definitionURL="#x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math></aside>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();

        $t->contains('<svg viewBox="0 0 10 10" preserveAspectRatio="xMidYMid meet"><linearGradient id="g"><stop offset="0"></stop></linearGradient><textPath href="#label">Logo</textPath></svg>', $fragment->serialize());
        $t->contains('<math><mi definitionURL="#x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math>', $fragment->serialize());
        $t->same('Logoxx', $fragment->textContent());
        $t->true(in_array('linearGradient', $summary['elementNames'], true), 'Expected adjusted SVG linearGradient element in summary');
        $t->true(in_array('textPath', $summary['elementNames'], true), 'Expected adjusted SVG textPath element in summary');
        $t->same('svg', $nodes[0]['children'][0]['name']);
        $t->same([
            'viewBox' => '0 0 10 10',
            'preserveAspectRatio' => 'xMidYMid meet',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same('linearGradient', $nodes[0]['children'][0]['children'][0]['name']);
        $t->same('definitionURL', array_key_first($nodes[0]['children'][1]['children'][0]['attrs']));
        $t->same([], $summary['blockedTags']);
    },
    'keeps html integration point descendants lowercase in sanitized fragments' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article><svg><foreignObject><div viewBox="html attr"><linearGradient>HTML child</linearGradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject></svg>'
                . '<math><annotation-xml encoding="text/html"><div viewBox="math html"><textPath>HTML text</textPath></div></annotation-xml><annotation-xml encoding="MathML-Content"><ci definitionURL="#x">x</ci></annotation-xml></math></article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $article = $nodes[0];
        $foreignObject = $article['children'][0]['children'][0];
        $foreignDiv = $foreignObject['children'][0];
        $mathHtmlAnnotation = $article['children'][1]['children'][0];
        $mathHtmlDiv = $mathHtmlAnnotation['children'][0];
        $mathContentAnnotation = $article['children'][1]['children'][1];
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/foreign-content-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('foreignObject', $foreignObject['name']);
        $t->same(['viewbox' => 'html attr'], $foreignDiv['attrs']);
        $t->same('lineargradient', $foreignDiv['children'][0]['name']);
        $t->same('linearGradient', $foreignDiv['children'][1]['children'][0]['name']);
        $t->same(['viewbox' => 'math html'], $mathHtmlDiv['attrs']);
        $t->same('textpath', $mathHtmlDiv['children'][0]['name']);
        $t->same(['definitionURL' => '#x'], $mathContentAnnotation['children'][0]['attrs']);
        $t->contains('<foreignObject><div viewbox="html attr"><lineargradient>HTML child</lineargradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject>', $html);
        $t->contains('<annotation-xml encoding="text/html"><div viewbox="math html"><textpath>HTML text</textpath></div></annotation-xml>', $blocks);
        $t->same('/migration/foreign-content-review.html', $document->children[0]->attr('part'));
        $t->same([], $summary['blockedTags']);
    },
    'hands normalized HTML fragments to WordPress raw HTML blocks without browser or Pandoc execution' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml('<h1 id="review">Import</h1><p>Manual<br>break &amp; reviewer note</p>');
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/review-fragment.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<!-- wp:html -->', $blocks);
        $t->contains('<h1 id="review">Import</h1><p>Manual<br>break &amp; reviewer note</p>', $blocks);
        $t->contains('<!-- /wp:html -->', $blocks);
        $t->same('<h1 id="review">Import</h1><p>Manual<br>break &amp; reviewer note</p>', $document->children[0]->attr('html'));
        $t->same('/migration/review-fragment.html', $document->children[0]->attr('part'));
        $t->same([], $document->children[0]->attr('diagnostics'));
    },
    'preserves textarea rcdata as escaped visible reviewer text during sanitizer unwrap' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<textarea data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b></textarea><p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/textarea-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;b&gt;note&lt;/b&gt;<p>after</p>', $fragment->serialize());
        $t->same('Reviewer <script>alert(1)</script> & <b>note</b>after', $fragment->textContent());
        $t->same(['p'], $summary['elementNames']);
        $t->same(['textarea'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(['blocked-tag'], $fragment->diagnosticCodes());
        $t->same('text', $nodes[0]['type']);
        $t->same('Reviewer <script>alert(1)</script> & <b>note</b>', $nodes[0]['text']);
        $t->contains('Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;b&gt;note&lt;/b&gt;', $blocks);
        $t->true(!str_contains($fragment->serialize(), '<script>'), 'Expected textarea-like source tags to remain escaped text');
        $t->true(!str_contains($fragment->serialize(), '<b>note</b>'), 'Expected textarea-like inline tags to remain escaped text');
    },
    'unwraps obsolete raw text fallback containers as escaped reviewer text' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<xmp data-source="legacy">Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea></xmp>'
                . '<noembed>Fallback <img src=x> & source</noembed>'
                . '<noframes>Frame fallback <a href="/edit">edit</a></noframes><p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/raw-text-fallback-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = 'Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;textarea&gt;&lt;b&gt;note&lt;/b&gt;&lt;/textarea&gt;'
            . 'Fallback &lt;img src=x&gt; &amp; source'
            . 'Frame fallback &lt;a href="/edit"&gt;edit&lt;/a&gt;<p>after</p>';
        $t->same($expected, $fragment->serialize());
        $t->same('Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea>Fallback <img src=x> & sourceFrame fallback <a href="/edit">edit</a>after', $fragment->textContent());
        $t->same(['p'], $summary['elementNames']);
        $t->same(['noembed', 'noframes', 'xmp'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag'], $policyDiagnostics);
        $t->same('text', $nodes[0]['type']);
        $t->same('Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea>', $nodes[0]['text']);
        $t->same('text', $nodes[1]['type']);
        $t->same('Fallback <img src=x> & source', $nodes[1]['text']);
        $t->same('/migration/raw-text-fallback-review.html', $document->children[0]->attr('part'));
        $t->contains('Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;textarea&gt;&lt;b&gt;note&lt;/b&gt;&lt;/textarea&gt;', $blocks);
        $t->true(!str_contains($fragment->serialize(), '<xmp'), 'Expected obsolete raw text xmp wrapper to be stripped');
        $t->true(!str_contains($fragment->serialize(), '<noembed'), 'Expected noembed wrapper to be stripped');
        $t->true(!str_contains($fragment->serialize(), '<noframes'), 'Expected noframes wrapper to be stripped');
        $t->true(!str_contains($fragment->serialize(), '<textarea>'), 'Expected raw text textarea-looking source to stay escaped');
        $t->true(!str_contains($fragment->serialize(), '<script>alert(1)</script>'), 'Expected raw text script-looking source to stay escaped');
        $t->true(!str_contains($fragment->serialize(), '<img src=x>'), 'Expected fallback image-looking source to stay escaped');
    },
    'unwraps html plaintext as escaped reviewer text through fragment end' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<plaintext data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b></plaintext><p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/plaintext-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expectedText = 'Reviewer <script>alert(1)</script> &amp; <b>note</b></plaintext><p>after</p>';
        $expectedHtml = 'Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;b&gt;note&lt;/b&gt;&lt;/plaintext&gt;&lt;p&gt;after&lt;/p&gt;';

        $t->same($expectedHtml, $fragment->serialize());
        $t->same($expectedText, $fragment->textContent());
        $t->same('text', $nodes[0]['type']);
        $t->same($expectedText, $nodes[0]['text']);
        $t->same([], $summary['elementNames']);
        $t->same(['plaintext'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $t->same(['blocked-tag'], $policyDiagnostics);
        $t->same('/migration/plaintext-review.html', $document->children[0]->attr('part'));
        $t->contains($expectedHtml, $blocks);
        $t->true(!str_contains($fragment->serialize(), '<plaintext'), 'Expected plaintext wrapper to be stripped from sanitized output');
        $t->true(!str_contains($fragment->serialize(), '<script>alert(1)</script>'), 'Expected plaintext script-looking source to stay escaped');
        $t->true(!str_contains($fragment->serialize(), '<p>after</p>'), 'Expected following paragraph source to stay plaintext text');
    },
    'foster-parents invalid table children before sanitized WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<table class="legacy"><caption>Review rows</caption><p data-review="loose">Loose note</p><tr><td>A</td></tr>orphan text<tr><td>B</td></tr></table><p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/table-foster-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<p data-review="loose">Loose note</p>orphan text<table class="legacy"><caption>Review rows</caption><tr><td>A</td></tr><tr><td>B</td></tr></table><p>after</p>';
        $t->same($expected, $fragment->serialize());
        $t->contains($expected, $blocks);
        $t->same('p', $nodes[0]['name']);
        $t->same('text', $nodes[1]['type']);
        $t->same('table', $nodes[2]['name']);
        $t->same('caption', $nodes[2]['children'][0]['name']);
        $t->same('tr', $nodes[2]['children'][1]['name']);
        $t->same('tr', $nodes[2]['children'][2]['name']);
        $t->same(2, $summary['diagnostics']);
        $t->same(['table-foster-parented-content', 'table-foster-parented-content'], $fragment->diagnosticCodes());
        $t->same('/migration/table-foster-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($fragment->serialize(), '</caption><p data-review="loose">'), 'Expected paragraph to move outside table');
        $t->true(!str_contains($fragment->serialize(), '</tr>orphan text<tr>'), 'Expected loose text to move outside table rows');
    },
    'resolves safe relative URLs from trusted HTML base metadata' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://example.test/import/posts/source.html?draft=1">'
            . '<article><a href="../media/doc.html#section">doc</a>'
            . '<img src="./cover.png" srcset="./cover.png 1x, ../media/cover@2x.png 2x, javascript:alert(1) 3x" alt="Cover">'
            . '<a href="#note">note</a><blockquote cite="?review=1">quoted</blockquote>'
            . '<a href="mailto:review@example.test">mail</a></article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $ast = $fragment->toRawHtmlAst(['part' => '/migration/base-url-review.html']);
        $html = $fragment->serialize();

        $t->same('https://example.test/import/posts/source.html?draft=1', $fragment->baseUrl());
        $t->same('https://example.test/import/posts/source.html?draft=1', $summary['baseUrl']);
        $t->same(['base'], $summary['blockedTags']);
        $t->same(['srcset'], $summary['filteredAttributes']);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $t->same(['blocked-tag', 'unsafe-url'], $policyDiagnostics);
        $t->contains('<a href="https://example.test/import/media/doc.html#section">doc</a>', $html);
        $t->contains('<img src="https://example.test/import/posts/cover.png" srcset="https://example.test/import/posts/cover.png 1x, https://example.test/import/media/cover@2x.png 2x" alt="Cover">', $html);
        $t->contains('<a href="https://example.test/import/posts/source.html?draft=1#note">note</a>', $html);
        $t->contains('<blockquote cite="https://example.test/import/posts/source.html?review=1">quoted</blockquote>', $html);
        $t->contains('<a href="mailto:review@example.test">mail</a>', $html);
        $t->same('https://example.test/import/media/doc.html#section', $nodes[0]['children'][0]['attrs']['href']);
        $t->same('https://example.test/import/posts/cover.png', $nodes[0]['children'][1]['attrs']['src']);
        $t->same('https://example.test/import/posts/cover.png 1x, https://example.test/import/media/cover@2x.png 2x', $nodes[0]['children'][1]['attrs']['srcset']);
        $t->same('https://example.test/import/posts/source.html?draft=1#note', $nodes[0]['children'][2]['attrs']['href']);
        $t->same('https://example.test/import/posts/source.html?review=1', $nodes[0]['children'][3]['attrs']['cite']);
        $t->same('mailto:review@example.test', $nodes[0]['children'][4]['attrs']['href']);
        $t->same('/migration/base-url-review.html', $ast->attr('part'));
        $t->same('https://example.test/import/posts/source.html?draft=1', $ast->attr('baseUrl'));
        $t->true(!str_contains($html, '<base'), 'Expected base element to be dropped from sanitized output');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe srcset candidate to be dropped');

        $relativeBase = Html5DomFragment::fromHtml(
            '<base href="../assets/"><img src="cover.png" srcset="cover.png 1x, ./cover@2x.png 2x" alt="Cover">',
            'https://example.test/import/posts/source.html'
        );
        $relativeHtml = $relativeBase->serialize();

        $t->same('https://example.test/import/assets/', $relativeBase->baseUrl());
        $t->same(['blocked-tag'], $relativeBase->diagnosticCodes());
        $t->same('<img src="https://example.test/import/assets/cover.png" srcset="https://example.test/import/assets/cover.png 1x, https://example.test/import/assets/cover@2x.png 2x" alt="Cover">', $relativeHtml);
        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromHtml('<a href="/review">review</a>', 'file:///tmp/source.html'));
    },
    'rejects unsafe fragment declarations before libxml can repair them away' => static function (TestRunner $t): void {
        $safe = Html5DomFragment::fromHtml('<p data-source="review">Safe &amp; bounded</p>');

        $t->same('<p data-source="review">Safe &amp; bounded</p>', $safe->serialize());
        $t->same([], $safe->diagnosticCodes());
        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromHtml("<p>bad\0packet</p>"));
        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromHtml('<!DOCTYPE html><p>bad</p>'));
        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromHtml('<!ENTITY reviewer SYSTEM "file:///etc/passwd"><p>&reviewer;</p>'));
        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromHtml('<?xml-stylesheet href="https://example.invalid/review.xsl"?><p>bad</p>'));
        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromXml('<?xml-stylesheet href="https://example.invalid/review.xsl"?><root/>'));
    },
];
