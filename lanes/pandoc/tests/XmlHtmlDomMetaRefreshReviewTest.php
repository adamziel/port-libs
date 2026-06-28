<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html meta refresh navigation provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<meta http-equiv="refresh" content="0; url=/next">'
                . '<meta http-equiv="refresh" content="bad; url=javascript:alert(1)">'
                . '<meta http-equiv="refresh" content="-1; url=mailto:ops@example.test">'
                . '<meta http-equiv="refresh" content="5; next=/bad">'
                . '<meta http-equiv="refresh" content="10">'
                . '<p>Body</p>',
            'meta refresh navigation review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/meta-refresh-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $safe = $summary[0]['refresh'];
        $unsafe = $summary[1]['refresh'];
        $nonHttp = $summary[2]['refresh'];
        $badAssignment = $summary[3]['refresh'];
        $reload = $summary[4]['refresh'];
        $paragraph = $summary[5];

        $t->same('meta-refresh-navigation-review', $safe['reviewPolicy']);
        $t->same('0', $safe['delayRaw']);
        $t->same(0.0, $safe['delay']);
        $t->same(true, $safe['delayValid']);
        $t->same('/next', $safe['urlRaw']);
        $t->same('/next', $safe['url']);
        $t->same('relative', $safe['urlKind']);
        $t->same(null, $safe['urlScheme']);
        $t->same(false, $safe['urlUnsafe']);
        $t->same(true, $safe['redirectRequested']);
        $t->same(false, $safe['redirectFollowed']);
        $t->same([], $safe['issueCodes']);
        $t->same(true, $safe['valid']);

        $t->same('bad', $unsafe['delayRaw']);
        $t->same(null, $unsafe['delay']);
        $t->same(false, $unsafe['delayValid']);
        $t->same('javascript:alert(1)', $unsafe['url']);
        $t->same('absolute', $unsafe['urlKind']);
        $t->same('javascript', $unsafe['urlScheme']);
        $t->same(true, $unsafe['urlUnsafe']);
        $t->same([
            'invalid-meta-refresh-delay',
            'unsafe-meta-refresh-url',
        ], $unsafe['issueCodes']);
        $t->same(false, $unsafe['valid']);

        $t->same('-1', $nonHttp['delayRaw']);
        $t->same(null, $nonHttp['delay']);
        $t->same('mailto', $nonHttp['urlScheme']);
        $t->same(false, $nonHttp['urlUnsafe']);
        $t->same([
            'invalid-meta-refresh-delay',
            'non-http-meta-refresh-url',
        ], $nonHttp['issueCodes']);

        $t->same('5', $badAssignment['delayRaw']);
        $t->same(5.0, $badAssignment['delay']);
        $t->same(null, $badAssignment['urlRaw']);
        $t->same(null, $badAssignment['url']);
        $t->same('missing', $badAssignment['urlKind']);
        $t->same(false, $badAssignment['redirectRequested']);
        $t->same(['invalid-meta-refresh-url-assignment'], $badAssignment['issueCodes']);

        $t->same('10', $reload['delayRaw']);
        $t->same(10.0, $reload['delay']);
        $t->same(true, $reload['delayValid']);
        $t->same(false, $reload['urlPresent']);
        $t->same(false, $reload['redirectRequested']);
        $t->same(false, $reload['redirectFollowed']);
        $t->same([], $reload['issueCodes']);
        $t->same(true, $reload['valid']);

        $t->same('Body', $paragraph['text']);
        $t->same(
            '<meta content="0; url=/next" http-equiv="refresh">'
                . '<meta content="bad; url=javascript:alert(1)" http-equiv="refresh">'
                . '<meta content="-1; url=mailto:ops@example.test" http-equiv="refresh">'
                . '<meta content="5; next=/bad" http-equiv="refresh">'
                . '<meta content="10" http-equiv="refresh"><p>Body</p>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/meta-refresh-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
