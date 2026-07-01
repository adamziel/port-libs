<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html textarea layout and wrap provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="textareas"><textarea id="body" name="body" rows="4" cols="40" wrap="hard" placeholder="Write &lt;summary&gt;">Line 1' . "\n" . 'Line 2</textarea>'
                . '<textarea id="broken" rows="0" cols="wide" wrap="pretty">Bad</textarea>'
                . '<textarea id="plain"></textarea>'
                . '<textarea id="spaced" wrap=" HARD " cols="10">One</textarea>'
                . '<textarea id="hard-missing" wrap="hard">Hard</textarea></form>',
            'textarea layout review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/textarea-layout-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $body = $form['children'][0];
        $broken = $form['children'][1];
        $plain = $form['children'][2];
        $spaced = $form['children'][3];
        $hardMissing = $form['children'][4];

        $t->same('textarea', $body['formControl']);
        $t->same('html-textarea-layout-review', $body['textareaReviewPolicy']);
        $t->same('4', $body['textareaRowsRaw']);
        $t->same(4, $body['textareaRows']);
        $t->same(true, $body['textareaRowsValid']);
        $t->same(4, $body['textareaEffectiveRows']);
        $t->same(false, $body['textareaRowsDefaulted']);
        $t->same('40', $body['textareaColsRaw']);
        $t->same(40, $body['textareaCols']);
        $t->same(true, $body['textareaColsValid']);
        $t->same(40, $body['textareaEffectiveCols']);
        $t->same(false, $body['textareaColsDefaulted']);
        $t->same('hard', $body['textareaWrapRaw']);
        $t->same('hard', $body['textareaWrap']);
        $t->same(true, $body['textareaWrapValid']);
        $t->same('hard', $body['textareaEffectiveWrap']);
        $t->same(false, $body['textareaWrapDefaulted']);
        $t->same(true, $body['textareaHardWrapRequiresCols']);
        $t->same(true, $body['textareaHardWrapHasValidCols']);
        $t->same(strlen("Line 1\nLine 2"), $body['textareaValueByteLength']);
        $t->same(2, $body['textareaValueLineCount']);
        $t->same([], $body['textareaIssueCodes']);
        $t->same(true, $body['textareaLayoutValid']);
        $t->same('Write <summary>', $body['placeholder']);

        $t->same('0', $broken['textareaRowsRaw']);
        $t->same(null, $broken['textareaRows']);
        $t->same(false, $broken['textareaRowsValid']);
        $t->same(2, $broken['textareaEffectiveRows']);
        $t->same(true, $broken['textareaRowsDefaulted']);
        $t->same('wide', $broken['textareaColsRaw']);
        $t->same(null, $broken['textareaCols']);
        $t->same(false, $broken['textareaColsValid']);
        $t->same(20, $broken['textareaEffectiveCols']);
        $t->same(true, $broken['textareaColsDefaulted']);
        $t->same('pretty', $broken['textareaWrapRaw']);
        $t->same(null, $broken['textareaWrap']);
        $t->same(false, $broken['textareaWrapValid']);
        $t->same('soft', $broken['textareaEffectiveWrap']);
        $t->same(true, $broken['textareaWrapDefaulted']);
        $t->same(false, $broken['textareaHardWrapRequiresCols']);
        $t->same(true, $broken['textareaHardWrapHasValidCols']);
        $t->same(['invalid-textarea-rows', 'invalid-textarea-cols', 'invalid-textarea-wrap'], $broken['textareaIssueCodes']);
        $t->same(false, $broken['textareaLayoutValid']);

        $t->same(null, $plain['textareaRowsRaw']);
        $t->same(null, $plain['textareaColsRaw']);
        $t->same(null, $plain['textareaWrapRaw']);
        $t->same(2, $plain['textareaEffectiveRows']);
        $t->same(20, $plain['textareaEffectiveCols']);
        $t->same('soft', $plain['textareaEffectiveWrap']);
        $t->same(0, $plain['textareaValueByteLength']);
        $t->same(0, $plain['textareaValueLineCount']);
        $t->same(true, $plain['textareaLayoutValid']);

        $t->same(' HARD ', $spaced['textareaWrapRaw']);
        $t->same('hard', $spaced['textareaWrap']);
        $t->same(10, $spaced['textareaCols']);
        $t->same(true, $spaced['textareaHardWrapHasValidCols']);
        $t->same([], $spaced['textareaIssueCodes']);

        $t->same('hard', $hardMissing['textareaWrap']);
        $t->same(true, $hardMissing['textareaHardWrapRequiresCols']);
        $t->same(false, $hardMissing['textareaHardWrapHasValidCols']);
        $t->same(['hard-textarea-wrap-without-valid-cols'], $hardMissing['textareaIssueCodes']);
        $t->same(false, $hardMissing['textareaLayoutValid']);

        $t->same(
            '<form id="textareas"><textarea cols="40" id="body" name="body" placeholder="Write &lt;summary&gt;" rows="4" wrap="hard">Line 1' . "\n" . 'Line 2</textarea><textarea cols="wide" id="broken" rows="0" wrap="pretty">Bad</textarea><textarea id="plain"></textarea><textarea cols="10" id="spaced" wrap=" HARD ">One</textarea><textarea id="hard-missing" wrap="hard">Hard</textarea></form>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/textarea-layout-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
