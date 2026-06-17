<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html draggable auto state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="auto" draggable="auto"><p id="true" draggable="TRUE">Move</p><p id="false" draggable="false">Lock</p></article>'
                . '<section id="empty" draggable>Empty</section><aside id="invalid" draggable="maybe">Invalid</aside><p id="plain">Plain</p>',
            'draggable auto review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/draggable-auto-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $true = $article['children'][0];
        $false = $article['children'][1];
        $empty = $summary[1];
        $invalid = $summary[2];
        $plain = $summary[3];

        $t->same('article', $article['name']);
        $t->same('auto', $article['draggableRaw']);
        $t->same('auto', $article['draggable']);
        $t->same(true, $article['draggableValid']);

        $t->same('TRUE', $true['draggableRaw']);
        $t->same(true, $true['draggable']);
        $t->same(true, $true['draggableValid']);

        $t->same('false', $false['draggableRaw']);
        $t->same(false, $false['draggable']);
        $t->same(true, $false['draggableValid']);

        $t->same('', $empty['draggableRaw']);
        $t->same(null, $empty['draggable']);
        $t->same(false, $empty['draggableValid']);

        $t->same('maybe', $invalid['draggableRaw']);
        $t->same(null, $invalid['draggable']);
        $t->same(false, $invalid['draggableValid']);

        $t->true(!array_key_exists('draggableRaw', $plain));
        $t->true(!array_key_exists('draggable', $plain));
        $t->true(!array_key_exists('draggableValid', $plain));

        $t->same(
            '<article draggable="auto" id="auto"><p draggable="TRUE" id="true">Move</p><p draggable="false" id="false">Lock</p></article>'
                . '<section draggable="" id="empty">Empty</section><aside draggable="maybe" id="invalid">Invalid</aside><p id="plain">Plain</p>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/draggable-auto-review.html', $document->children[0]->attr('part'));
    },
];
