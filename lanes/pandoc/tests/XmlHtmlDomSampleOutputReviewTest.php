<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html sample output context for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p>Shell <samp id="plain">Saved file</samp>, echo <samp id="echo">Press <kbd id="yes">Y</kbd><kbd id="blank"></kbd></samp>, choose <kbd id="menu"><samp id="derived">File</samp> Open</kbd>, empty <samp id="empty"></samp>.</p>',
            'sample output review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/sample-output-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $byId = [];
        $collect = static function (array $nodes) use (&$collect, &$byId): void {
            foreach ($nodes as $node) {
                $id = $node['attributes']['id'] ?? null;
                if (is_string($id) && $id !== '') {
                    $byId[$id] = $node;
                }
                if (isset($node['children']) && is_array($node['children'])) {
                    $collect($node['children']);
                }
            }
        };
        $collect($summary);

        $plain = $byId['plain'];
        $echo = $byId['echo'];
        $yes = $byId['yes'];
        $blank = $byId['blank'];
        $menu = $byId['menu'];
        $derived = $byId['derived'];
        $empty = $byId['empty'];

        $t->same('p', $summary[0]['name']);
        $t->same('samp', $plain['name']);
        $t->same('sample-output', $plain['textSemantic']);
        $t->same('html-sample-output-review', $plain['sampleOutputReviewPolicy']);
        $t->same('Saved file', $plain['sampleOutputText']);
        $t->same('Saved file', $plain['sampleOutputRawText']);
        $t->same('sample-output', $plain['sampleOutputContext']);
        $t->same('p', $plain['sampleOutputParentElement']);
        $t->same(false, $plain['sampleOutputHasNestedKeyboardInput']);
        $t->same(0, $plain['sampleOutputNestedKeyboardInputCount']);
        $t->same([], $plain['sampleOutputNestedKeyboardInputTexts']);
        $t->same(false, $plain['sampleOutputEchoesKeyboardInput']);
        $t->same(false, $plain['sampleOutputDerivedFromKeyboardInput']);
        $t->same(true, $plain['sampleOutputValid']);
        $t->same([], $plain['sampleOutputIssueCodes']);

        $t->same('echoed-keyboard-input', $echo['sampleOutputContext']);
        $t->same('Press Y', $echo['sampleOutputText']);
        $t->same(true, $echo['sampleOutputHasNestedKeyboardInput']);
        $t->same(2, $echo['sampleOutputNestedKeyboardInputCount']);
        $t->same(['Y', ''], $echo['sampleOutputNestedKeyboardInputTexts']);
        $t->same(true, $echo['sampleOutputEchoesKeyboardInput']);
        $t->same(false, $echo['sampleOutputDerivedFromKeyboardInput']);
        $t->same(false, $echo['sampleOutputValid']);
        $t->same(['empty-sample-output-keyboard-input'], $echo['sampleOutputIssueCodes']);
        $t->same([['code' => 'empty-sample-output-keyboard-input', 'index' => 1]], $echo['sampleOutputIssues']);
        $t->same('echoed-input', $yes['keyboardInputContext']);
        $t->same('Y', $yes['keyboardInputText']);
        $t->same(['empty-keyboard-input'], $blank['keyboardInputIssueCodes']);

        $t->same('system-output-derived-input', $menu['keyboardInputContext']);
        $t->same(['File'], $menu['keyboardInputNestedOutputTexts']);
        $t->same('keyboard-derived-output', $derived['sampleOutputContext']);
        $t->same('kbd', $derived['sampleOutputParentElement']);
        $t->same('File', $derived['sampleOutputText']);
        $t->same(false, $derived['sampleOutputHasNestedKeyboardInput']);
        $t->same(true, $derived['sampleOutputDerivedFromKeyboardInput']);
        $t->same(true, $derived['sampleOutputValid']);

        $t->same('', $empty['sampleOutputText']);
        $t->same('sample-output', $empty['sampleOutputContext']);
        $t->same(false, $empty['sampleOutputValid']);
        $t->same(['empty-sample-output'], $empty['sampleOutputIssueCodes']);
        $t->same([['code' => 'empty-sample-output']], $empty['sampleOutputIssues']);

        $t->same('<p>Shell <samp id="plain">Saved file</samp>, echo <samp id="echo">Press <kbd id="yes">Y</kbd><kbd id="blank"></kbd></samp>, choose <kbd id="menu"><samp id="derived">File</samp> Open</kbd>, empty <samp id="empty"></samp>.</p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/sample-output-review.html', $document->children[0]->attr('part'));
    },
];
