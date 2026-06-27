<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html form action provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="safe" action="https://forms.example.test/review" method="POST" enctype="multipart/form-data" target="_blank"><input name="title" value="Packet"></form>'
                . '<form id="relative" action="/local/review" method="dialog" enctype="text/plain" target="review-frame"><button>Review</button></form>'
                . '<form id="unsafe" action="javascript:steal()" method="TRACE" enctype="application/json"><button>Bad</button></form>'
                . '<form id="mailto" action="mailto:ops@example.test" target=""><button>Mail</button></form>'
                . '<form id="default"><button>Default</button></form>',
            'form action provenance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/form-action-provenance.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $safe = $summary[0];
        $relative = $summary[1];
        $unsafe = $summary[2];
        $mailto = $summary[3];
        $default = $summary[4];

        $t->same('form-action-provenance-review', $safe['formActionReviewPolicy']);
        $t->same('safe', $safe['formActionFormId']);
        $t->same('https://forms.example.test/review', $safe['formActionRaw']);
        $t->same('attribute', $safe['formActionSource']);
        $t->same('absolute', $safe['formActionKind']);
        $t->same('https', $safe['formActionScheme']);
        $t->same(false, $safe['formActionUnsafe']);
        $t->same('POST', $safe['formMethodRaw']);
        $t->same('post', $safe['formEffectiveMethod']);
        $t->same(true, $safe['formMethodValid']);
        $t->same('multipart/form-data', $safe['formEnctypeRaw']);
        $t->same('multipart/form-data', $safe['formEffectiveEnctype']);
        $t->same(true, $safe['formEnctypeValid']);
        $t->same('_blank', $safe['formTargetRaw']);
        $t->same('_blank', $safe['formEffectiveTarget']);
        $t->same(true, $safe['formWouldSubmitNetworkRequest']);
        $t->same(true, $safe['formReviewOnlyNoNetworkRequest']);
        $t->same([], $safe['formActionIssueCodes']);
        $t->same(true, $safe['formActionValid']);

        $t->same('/local/review', $relative['formActionRaw']);
        $t->same('relative', $relative['formActionKind']);
        $t->same(null, $relative['formActionScheme']);
        $t->same('dialog', $relative['formEffectiveMethod']);
        $t->same('text/plain', $relative['formEffectiveEnctype']);
        $t->same('review-frame', $relative['formEffectiveTarget']);
        $t->same(false, $relative['formWouldSubmitNetworkRequest']);
        $t->same([], $relative['formActionIssueCodes']);
        $t->same(true, $relative['formActionValid']);

        $t->same('javascript:steal()', $unsafe['formActionRaw']);
        $t->same('absolute', $unsafe['formActionKind']);
        $t->same('javascript', $unsafe['formActionScheme']);
        $t->same(true, $unsafe['formActionUnsafe']);
        $t->same('TRACE', $unsafe['formMethodRaw']);
        $t->same('get', $unsafe['formEffectiveMethod']);
        $t->same(false, $unsafe['formMethodValid']);
        $t->same('application/json', $unsafe['formEnctypeRaw']);
        $t->same('application/x-www-form-urlencoded', $unsafe['formEffectiveEnctype']);
        $t->same(false, $unsafe['formEnctypeValid']);
        $t->same([
            'unsafe-form-action-url',
            'invalid-form-method',
            'invalid-form-enctype',
        ], $unsafe['formActionIssueCodes']);
        $t->same(false, $unsafe['formActionValid']);

        $t->same('mailto:ops@example.test', $mailto['formActionRaw']);
        $t->same('absolute', $mailto['formActionKind']);
        $t->same('mailto', $mailto['formActionScheme']);
        $t->same('', $mailto['formTargetRaw']);
        $t->same(null, $mailto['formEffectiveTarget']);
        $t->same(['non-http-form-action-url'], $mailto['formActionIssueCodes']);
        $t->same(false, $mailto['formActionValid']);

        $t->same(null, $default['formActionRaw']);
        $t->same('document-url-default', $default['formActionSource']);
        $t->same('missing', $default['formActionKind']);
        $t->same(true, $default['formActionUsesDocumentUrl']);
        $t->same('get', $default['formEffectiveMethod']);
        $t->same(null, $default['formMethodValid']);
        $t->same('application/x-www-form-urlencoded', $default['formEffectiveEnctype']);
        $t->same(null, $default['formEnctypeValid']);
        $t->same(true, $default['formWouldSubmitNetworkRequest']);
        $t->same([], $default['formActionIssueCodes']);
        $t->same(true, $default['formActionValid']);

        $t->contains('action="javascript:steal()"', $html);
        $t->contains('action="mailto:ops@example.test"', $html);
        $t->contains('method="TRACE"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/form-action-provenance.html', $document->children[0]->attr('part'));
    },
];
