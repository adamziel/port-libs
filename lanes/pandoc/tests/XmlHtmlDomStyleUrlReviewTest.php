<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html style url references for reviewer handoff' => static function (TestRunner $t): void {
        $styleRaw = 'background-image: url("../images/bg.png?rev=1#cover"); mask: url(#badge); border-image: image-set(url("https://cdn.example.test/frame.png") 1x, url(data:image/png;base64,AAA=) 2x); cursor: url(), auto; list-style-image: url(javascript:alert(1)); filter: url(\\6a avascript:alert(1))';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="styled" style="' . htmlspecialchars($styleRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Styled</section>'
                . '<p id="plain" style="color: red">Plain</p>',
            'style url reference review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/style-url-reference-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $styled = $summary[0];
        $plain = $summary[1];

        $t->same('html-style-url-reference-review', $styled['styleUrlReviewPolicy']);
        $t->same(7, $styled['styleUrlReferenceCount']);
        $t->same(['background-image', 'mask', 'border-image', 'cursor', 'list-style-image', 'filter'], $styled['styleUrlReferenceProperties']);
        $t->same(['relative', 'fragment', 'absolute', 'empty'], $styled['styleUrlReferenceKinds']);
        $t->same(['https', 'data', 'javascript'], $styled['styleUrlReferenceSchemes']);
        $t->same(3, $styled['styleUnsafeUrlReferenceCount']);
        $t->same(['unsafe-style-url-reference', 'empty-style-url-reference'], $styled['styleUrlIssueCodes']);
        $t->same(false, $styled['styleUrlValid']);

        $relative = $styled['styleUrlReferences'][0];
        $t->same(0, $relative['declarationIndex']);
        $t->same('background-image', $relative['property']);
        $t->same('url("../images/bg.png?rev=1#cover")', $relative['raw']);
        $t->same('../images/bg.png?rev=1#cover', $relative['url']);
        $t->same(true, $relative['quoted']);
        $t->same('relative', $relative['kind']);
        $t->same(false, $relative['unsafe']);

        $fragment = $styled['styleUrlReferences'][1];
        $t->same('mask', $fragment['property']);
        $t->same('#badge', $fragment['url']);
        $t->same('fragment', $fragment['kind']);
        $t->same(false, $fragment['unsafe']);

        $remote = $styled['styleUrlReferences'][2];
        $t->same('border-image', $remote['property']);
        $t->same('https://cdn.example.test/frame.png', $remote['url']);
        $t->same('https', $remote['scheme']);
        $t->same(false, $remote['unsafe']);

        $data = $styled['styleUrlReferences'][3];
        $t->same('data:image/png;base64,AAA=', $data['url']);
        $t->same('data', $data['scheme']);
        $t->same(true, $data['unsafe']);
        $t->same(['unsafe-style-url-reference'], $data['issueCodes']);

        $empty = $styled['styleUrlReferences'][4];
        $t->same('cursor', $empty['property']);
        $t->same(null, $empty['url']);
        $t->same('empty', $empty['kind']);
        $t->same(['empty-style-url-reference'], $empty['issueCodes']);

        $script = $styled['styleUrlReferences'][5];
        $t->same('list-style-image', $script['property']);
        $t->same('javascript:alert(1)', $script['url']);
        $t->same('javascript', $script['scheme']);
        $t->same(true, $script['unsafe']);

        $escapedScript = $styled['styleUrlReferences'][6];
        $t->same('filter', $escapedScript['property']);
        $t->same('\\6a avascript:alert(1)', $escapedScript['url']);
        $t->same('javascript:alert(1)', $escapedScript['urlDecoded']);
        $t->same('javascript', $escapedScript['scheme']);
        $t->same(true, $escapedScript['unsafe']);

        $t->same(0, $plain['styleUrlReferenceCount']);
        $t->true(!array_key_exists('styleUrlReviewPolicy', $plain));
        $t->same(
            '<section id="styled" style="' . htmlspecialchars($styleRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Styled</section>'
                . '<p id="plain" style="color: red">Plain</p>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/style-url-reference-review.html', $document->children[0]->attr('part'));
    },
];
