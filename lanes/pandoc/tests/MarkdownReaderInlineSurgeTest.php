<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'maps commonmark raw html inline tags comments declarations and cdata surge' => static function (TestRunner $t): void {
        $cases = [
            ['source' => 'before <em>raw</em> after', 'raw' => ['<em>', '</em>']],
            ['source' => 'before <strong data-x="1">raw</strong> after', 'raw' => ['<strong data-x="1">', '</strong>']],
            ['source' => 'before <span title="a > b">raw</span> after', 'raw' => ['<span title="a > b">', '</span>']],
            ['source' => 'before <section data_one=alpha data-two=\'beta\'>raw</section> after', 'raw' => ['<section data_one=alpha data-two=\'beta\'>', '</section>']],
            ['source' => 'before <br> after', 'raw' => ['<br>']],
            ['source' => 'before <br/> after', 'raw' => ['<br/>']],
            ['source' => 'before <img src="cover.png" alt="Cover"> after', 'raw' => ['<img src="cover.png" alt="Cover">']],
            ['source' => 'before <input disabled data-source=batch-42> after', 'raw' => ['<input disabled data-source=batch-42>']],
            ['source' => 'before <b class="rule"/> after', 'raw' => ['<b class="rule"/>']],
            ['source' => 'before </span> after', 'raw' => ['</span>']],
            ['source' => 'before </section > after', 'raw' => ['</section >']],
            ['source' => 'before <!-- raw -- comment --> after', 'raw' => ['<!-- raw -- comment -->']],
            ['source' => 'before <!-- a *b* &copy; --> after', 'raw' => ['<!-- a *b* &copy; -->']],
            ['source' => 'before <?review import?> after', 'raw' => ['<?review import?>']],
            ['source' => 'before <?xml-stylesheet href="style.css"?> after', 'raw' => ['<?xml-stylesheet href="style.css"?>']],
            ['source' => 'before <!DOCTYPE html> after', 'raw' => ['<!DOCTYPE html>']],
            ['source' => 'before <!review data-source="batch-42"> after', 'raw' => ['<!review data-source="batch-42">']],
            ['source' => 'before <![CDATA[<x>&copy;</x>]]> after', 'raw' => ['<![CDATA[<x>&copy;</x>]]>']],
            ['source' => 'before <del>deleted</del> after', 'raw' => ['<del>', '</del>']],
            ['source' => 'before <ins datetime="2026-06-15">inserted</ins> after', 'raw' => ['<ins datetime="2026-06-15">', '</ins>']],
            ['source' => 'before <kbd>Ctrl</kbd> after', 'raw' => ['<kbd>', '</kbd>']],
            ['source' => 'before <mark data-review="yes">highlight</mark> after', 'raw' => ['<mark data-review="yes">', '</mark>']],
            ['source' => 'before <abbr title="Hypertext Markup Language">HTML</abbr> after', 'raw' => ['<abbr title="Hypertext Markup Language">', '</abbr>']],
            ['source' => 'before <cite>Source</cite> after', 'raw' => ['<cite>', '</cite>']],
            ['source' => 'before <q cite="url">quote</q> after', 'raw' => ['<q cite="url">', '</q>']],
            ['source' => 'before <samp>output</samp> after', 'raw' => ['<samp>', '</samp>']],
            ['source' => 'before <var>x</var> after', 'raw' => ['<var>', '</var>']],
            ['source' => 'before <small>fine print</small> after', 'raw' => ['<small>', '</small>']],
            ['source' => 'before <sup>2</sup> after', 'raw' => ['<sup>', '</sup>']],
            ['source' => 'before <sub>n</sub> after', 'raw' => ['<sub>', '</sub>']],
            ['source' => 'before <time datetime="2026-06-15">today</time> after', 'raw' => ['<time datetime="2026-06-15">', '</time>']],
            ['source' => 'before <data value="42">forty two</data> after', 'raw' => ['<data value="42">', '</data>']],
            ['source' => 'before <ruby>kan<rt>kan</rt></ruby> after', 'raw' => ['<ruby>', '<rt>', '</rt>', '</ruby>']],
            ['source' => 'before <bdi dir="rtl">abc</bdi> after', 'raw' => ['<bdi dir="rtl">', '</bdi>']],
            ['source' => 'before <bdo dir="ltr">abc</bdo> after', 'raw' => ['<bdo dir="ltr">', '</bdo>']],
            ['source' => 'before <wbr> after', 'raw' => ['<wbr>']],
            ['source' => 'before <meter value="0.5">half</meter> after', 'raw' => ['<meter value="0.5">', '</meter>']],
            ['source' => 'before <progress max="10" value="7">7</progress> after', 'raw' => ['<progress max="10" value="7">', '</progress>']],
            ['source' => 'before <label for=reviewer>Reviewer</label> after', 'raw' => ['<label for=reviewer>', '</label>']],
            ['source' => 'before <button type="button">Go</button> after', 'raw' => ['<button type="button">', '</button>']],
            ['source' => 'before <select><option>One</option></select> after', 'raw' => ['<select>', '<option>', '</option>', '</select>']],
            ['source' => 'before <audio controls>play</audio> after', 'raw' => ['<audio controls>', '</audio>']],
            ['source' => 'before <video controls></video> after', 'raw' => ['<video controls>', '</video>']],
            ['source' => 'before <source src="clip.webm" type="video/webm"> after', 'raw' => ['<source src="clip.webm" type="video/webm">']],
            ['source' => 'before <track kind="captions" src="captions.vtt"> after', 'raw' => ['<track kind="captions" src="captions.vtt">']],
            ['source' => 'before <map name="m"></map> after', 'raw' => ['<map name="m">', '</map>']],
            ['source' => 'before <area href="/x" alt="x"> after', 'raw' => ['<area href="/x" alt="x">']],
            ['source' => 'before <details open><summary>Title</summary>Body</details> after', 'raw' => ['<details open>', '<summary>', '</summary>', '</details>']],
            ['source' => 'before <dialog open>Hi</dialog> after', 'raw' => ['<dialog open>', '</dialog>']],
            ['source' => 'before <dfn data-source="batch-55" data-kind=inline/> after', 'raw' => ['<dfn data-source="batch-55" data-kind=inline/>']],
        ];

        $reader = new MarkdownReader();
        $mapped = 0;
        foreach ($cases as $index => $case) {
            $document = $reader->read($case['source']);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $rawNodes = array_values(array_filter(
                $paragraph->children,
                static fn (AstNode $node): bool => $node->type === 'raw_html_inline'
            ));
            $rawHtml = array_map(static fn (AstNode $node): string => (string) $node->attr('html'), $rawNodes);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('paragraph', $paragraph->type, 'case ' . $index . ' should stay an inline paragraph');
            $t->same($case['raw'], $rawHtml, 'case ' . $index . ' raw inline HTML tokens');
            foreach ($case['raw'] as $html) {
                $t->contains($html, $blocks, 'case ' . $index . ' WordPress output should preserve raw inline HTML');
            }
            $mapped++;
        }

        $t->same(50, $mapped);
    },
    'keeps raw html inline from stealing autolinks labels and entity-decoded link titles' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n\n", [
            'Autolink <https://example.test/a?b=1&c=2> and email <me@example.test> with <em>&copy;</em> plus [link](/u?x=1&y=2 "A &amp; B").',
            '[<em>literal</em>](url) keeps raw HTML literal in the label.',
            'Invalid < em> and <1> and </ div> stay text.',
        ]));
        $autolinkParagraph = $document->children[0] ?? new AstNode('missing');
        $labelParagraph = $document->children[1] ?? new AstNode('missing');
        $invalidParagraph = $document->children[2] ?? new AstNode('missing');
        $blocks = (new WordPressBlockWriter())->write($document);

        $uri = $autolinkParagraph->children[1] ?? new AstNode('missing');
        $email = $autolinkParagraph->children[3] ?? new AstNode('missing');
        $rawOpen = $autolinkParagraph->children[5] ?? new AstNode('missing');
        $copyright = $autolinkParagraph->children[6] ?? new AstNode('missing');
        $rawClose = $autolinkParagraph->children[7] ?? new AstNode('missing');
        $link = $autolinkParagraph->children[9] ?? new AstNode('missing');
        $labelLink = $labelParagraph->children[0] ?? new AstNode('missing');

        $t->same('link', $uri->type);
        $t->same('https://example.test/a?b=1&c=2', $uri->attr('url'));
        $t->same(['uri'], $uri->attr('classes'));
        $t->same('link', $email->type);
        $t->same('mailto:me@example.test', $email->attr('url'));
        $t->same(['email'], $email->attr('classes'));
        $t->same('raw_html_inline', $rawOpen->type);
        $t->same('<em>', $rawOpen->attr('html'));
        $t->same('text', $copyright->type);
        $t->same("\u{00A9}", $copyright->attr('text'));
        $t->same('raw_html_inline', $rawClose->type);
        $t->same('</em>', $rawClose->attr('html'));
        $t->same('link', $link->type);
        $t->same('/u?x=1&y=2', $link->attr('url'));
        $t->same('A & B', $link->attr('title'));
        $t->same('link', $labelLink->type);
        $t->same('<em>literal</em>', $labelLink->children[0]->attr('text'));
        $t->same('Invalid < em> and <1> and </ div> stay text.', $invalidParagraph->children[0]->attr('text'));
        $t->contains('<a href="https://example.test/a?b=1&amp;c=2">https://example.test/a?b=1&amp;c=2</a>', $blocks);
        $t->contains('<a href="mailto:me@example.test">me@example.test</a>', $blocks);
        $t->contains('with <em>©</em> plus <a href="/u?x=1&amp;y=2" title="A &amp; B">link</a>', $blocks);
        $t->contains('<a href="url">&lt;em&gt;literal&lt;/em&gt;</a>', $blocks);
        $t->contains('<p>Invalid &lt; em&gt; and &lt;1&gt; and &lt;/ div&gt; stay text.</p>', $blocks);
    },
];
