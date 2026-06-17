<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes inherited html autocapitalize state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="editor" autocapitalize="words"><p id="body"><span id="invalid" autocapitalize="maybe">Invalid</span><input id="name" autocapitalize="characters" value="Ada"><textarea id="off" autocapitalize="off">Lock</textarea></p></article>'
                . '<section id="plain"><p id="plain-child">Plain</p></section>',
            'autocapitalize inheritance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/autocapitalize-inheritance-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $body = $article['children'][0];
        $invalid = $body['children'][0];
        $input = $body['children'][1];
        $textarea = $body['children'][2];
        $plainChild = $summary[1]['children'][0];

        $t->same('article', $article['name']);
        $t->same('words', $article['autocapitalizeRaw']);
        $t->same('words', $article['autocapitalize']);
        $t->same(true, $article['autocapitalizeValid']);
        $t->same('words', $article['effectiveAutocapitalizeRaw']);
        $t->same('words', $article['effectiveAutocapitalize']);
        $t->same(false, $article['autocapitalizeInherited']);
        $t->same('self-autocapitalize', $article['autocapitalizeSource']);

        $t->true(!array_key_exists('autocapitalizeRaw', $body));
        $t->same('words', $body['effectiveAutocapitalize']);
        $t->same(true, $body['autocapitalizeInherited']);
        $t->same('article', $body['autocapitalizeSourceElement']);
        $t->same('editor', $body['autocapitalizeSourceElementId']);

        $t->same('maybe', $invalid['autocapitalizeRaw']);
        $t->same(null, $invalid['autocapitalize']);
        $t->same(false, $invalid['autocapitalizeValid']);
        $t->same('words', $invalid['effectiveAutocapitalize']);
        $t->same(true, $invalid['autocapitalizeInherited']);
        $t->same('editor', $invalid['autocapitalizeSourceElementId']);

        $t->same('characters', $input['autocapitalizeRaw']);
        $t->same('characters', $input['autocapitalize']);
        $t->same(true, $input['autocapitalizeValid']);
        $t->same('characters', $input['effectiveAutocapitalize']);
        $t->same(false, $input['autocapitalizeInherited']);
        $t->same('self-autocapitalize', $input['autocapitalizeSource']);

        $t->same('off', $textarea['autocapitalizeRaw']);
        $t->same('none', $textarea['autocapitalize']);
        $t->same('none', $textarea['effectiveAutocapitalize']);
        $t->same(false, $textarea['autocapitalizeInherited']);

        $t->true(!array_key_exists('effectiveAutocapitalize', $plainChild));
        $t->same(
            '<article autocapitalize="words" id="editor"><p id="body"><span autocapitalize="maybe" id="invalid">Invalid</span><input autocapitalize="characters" id="name" value="Ada"><textarea autocapitalize="off" id="off">Lock</textarea></p></article><section id="plain"><p id="plain-child">Plain</p></section>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/autocapitalize-inheritance-review.html', $document->children[0]->attr('part'));
    },
];
