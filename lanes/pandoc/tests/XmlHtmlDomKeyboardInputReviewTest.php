<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html keyboard input nesting for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p>Press <kbd id="shortcut"><kbd id="ctrl">Ctrl</kbd>+<kbd id="save">S</kbd></kbd>, type <kbd id="command">:wq</kbd>, echo <samp id="shell">Enter <kbd id="echo">yes</kbd></samp>, choose <kbd id="derived"><samp>File</samp> Open</kbd>, empty <kbd id="empty"></kbd>, broken <kbd id="broken"><kbd id="blank"></kbd>+<kbd id="enter">Enter</kbd></kbd>.</p>',
            'keyboard input review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/keyboard-input-review.html']),
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

        $shortcut = $byId['shortcut'];
        $ctrl = $byId['ctrl'];
        $save = $byId['save'];
        $command = $byId['command'];
        $echo = $byId['echo'];
        $derived = $byId['derived'];
        $empty = $byId['empty'];
        $broken = $byId['broken'];
        $blank = $byId['blank'];

        $t->same('p', $summary[0]['name']);
        $t->same('kbd', $shortcut['name']);
        $t->same('keyboard-input', $shortcut['textSemantic']);
        $t->same('html-keyboard-input-review', $shortcut['keyboardInputReviewPolicy']);
        $t->same('Ctrl+S', $shortcut['keyboardInputText']);
        $t->same('Ctrl+S', $shortcut['keyboardInputRawText']);
        $t->same('Ctrl+S', $shortcut['keyboardInputCommandText']);
        $t->same('keyboard-shortcut', $shortcut['keyboardInputContext']);
        $t->same('p', $shortcut['keyboardInputParentElement']);
        $t->same(true, $shortcut['keyboardInputHasNestedKeys']);
        $t->same(2, $shortcut['keyboardInputNestedKeyCount']);
        $t->same(['Ctrl', 'S'], $shortcut['keyboardInputNestedKeyTexts']);
        $t->same(['Ctrl', 'S'], $shortcut['keyboardInputKeySequence']);
        $t->same('Ctrl+S', $shortcut['keyboardInputShortcutText']);
        $t->same(false, $shortcut['keyboardInputDerivedFromSystemOutput']);
        $t->same(0, $shortcut['keyboardInputNestedOutputCount']);
        $t->same(true, $shortcut['keyboardInputValid']);
        $t->same([], $shortcut['keyboardInputIssueCodes']);

        $t->same('nested-key', $ctrl['keyboardInputContext']);
        $t->same('kbd', $ctrl['keyboardInputParentElement']);
        $t->same('Ctrl', $ctrl['keyboardInputText']);
        $t->same([], $ctrl['keyboardInputNestedKeyTexts']);
        $t->same(null, $ctrl['keyboardInputShortcutText']);
        $t->same('nested-key', $save['keyboardInputContext']);
        $t->same('S', $save['keyboardInputText']);

        $t->same('keyboard-input', $command['keyboardInputContext']);
        $t->same(':wq', $command['keyboardInputCommandText']);
        $t->same(false, $command['keyboardInputHasNestedKeys']);
        $t->same([], $command['keyboardInputIssues']);

        $t->same('samp', $byId['shell']['name']);
        $t->same('sample-output', $byId['shell']['textSemantic']);
        $t->same('echoed-input', $echo['keyboardInputContext']);
        $t->same('samp', $echo['keyboardInputParentElement']);
        $t->same('yes', $echo['keyboardInputText']);

        $t->same('system-output-derived-input', $derived['keyboardInputContext']);
        $t->same(true, $derived['keyboardInputDerivedFromSystemOutput']);
        $t->same(1, $derived['keyboardInputNestedOutputCount']);
        $t->same(['File'], $derived['keyboardInputNestedOutputTexts']);

        $t->same('', $empty['keyboardInputText']);
        $t->same(null, $empty['keyboardInputCommandText']);
        $t->same(false, $empty['keyboardInputValid']);
        $t->same(['empty-keyboard-input'], $empty['keyboardInputIssueCodes']);
        $t->same([['code' => 'empty-keyboard-input']], $empty['keyboardInputIssues']);

        $t->same('keyboard-shortcut', $broken['keyboardInputContext']);
        $t->same(['', 'Enter'], $broken['keyboardInputNestedKeyTexts']);
        $t->same('+Enter', $broken['keyboardInputShortcutText']);
        $t->same(false, $broken['keyboardInputValid']);
        $t->same(['empty-nested-keyboard-key'], $broken['keyboardInputIssueCodes']);
        $t->same([['code' => 'empty-nested-keyboard-key', 'index' => 0]], $broken['keyboardInputIssues']);
        $t->same('nested-key', $blank['keyboardInputContext']);
        $t->same(['empty-keyboard-input'], $blank['keyboardInputIssueCodes']);

        $t->same('<p>Press <kbd id="shortcut"><kbd id="ctrl">Ctrl</kbd>+<kbd id="save">S</kbd></kbd>, type <kbd id="command">:wq</kbd>, echo <samp id="shell">Enter <kbd id="echo">yes</kbd></samp>, choose <kbd id="derived"><samp>File</samp> Open</kbd>, empty <kbd id="empty"></kbd>, broken <kbd id="broken"><kbd id="blank"></kbd>+<kbd id="enter">Enter</kbd></kbd>.</p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/keyboard-input-review.html', $document->children[0]->attr('part'));
    },
];
