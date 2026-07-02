<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html drag and drop issue metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="valid" draggable="auto" dropzone="copy string:text/plain file:image/png">Drop</section>'
                . '<a id="link" href="/asset" draggable="auto">Asset</a>'
                . '<section id="empty" draggable dropzone>Empty</section>'
                . '<section id="invalid" draggable="maybe" dropzone="execute string:javascript file:bad mime">Bad</section>'
                . '<section id="multi" dropzone="copy move string:text/html">Multi</section>',
            'drag drop issue review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/drag-drop-issue-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $valid = $summary[0];
        $link = $summary[1];
        $empty = $summary[2];
        $invalid = $summary[3];
        $multi = $summary[4];

        $t->same('html-draggable-state-review', $valid['draggableReviewPolicy']);
        $t->same('ok', $valid['draggableReviewStatus']);
        $t->same([], $valid['draggableIssueCodes']);
        $t->same(0, $valid['draggableIssueCount']);
        $t->same(false, $valid['effectiveDraggable']);
        $t->same('element-default', $valid['draggableAutoReason']);
        $t->same(true, $valid['draggableReviewOnlyNoDragDropEngine']);

        $t->same(true, $link['effectiveDraggable']);
        $t->same('hyperlink-element', $link['draggableAutoReason']);
        $t->same('ok', $link['draggableReviewStatus']);

        $t->same('review', $empty['draggableReviewStatus']);
        $t->same(['invalid-html-draggable-token'], $empty['draggableIssueCodes']);
        $t->same(1, $empty['draggableIssueCount']);
        $t->same(true, $empty['draggableInvalidValueDefaulted']);

        $t->same('review', $invalid['draggableReviewStatus']);
        $t->same(['invalid-html-draggable-token'], $invalid['draggableIssueCodes']);
        $t->same('auto-default', $invalid['draggableSource']);

        $t->same('html-dropzone-attribute-review', $valid['dropZoneReviewPolicy']);
        $t->same('ok', $valid['dropZoneReviewStatus']);
        $t->same([], $valid['dropZoneIssueCodes']);
        $t->same(0, $valid['dropZoneIssueCount']);
        $t->same(true, $valid['dropZoneValid']);
        $t->same(['copy'], $valid['dropZoneEffects']);
        $t->same(['text/plain'], $valid['dropZoneStringTypes']);
        $t->same(['image/png'], $valid['dropZoneFileTypes']);
        $t->same(true, $valid['dropZoneReviewOnlyNoDragDropEngine']);

        $t->same('review', $empty['dropZoneReviewStatus']);
        $t->same(['empty-html-dropzone-token-list'], $empty['dropZoneIssueCodes']);
        $t->same(1, $empty['dropZoneIssueCount']);
        $t->same(false, $empty['dropZoneValid']);

        $t->same('review', $invalid['dropZoneReviewStatus']);
        $t->same(['invalid-html-dropzone-token'], $invalid['dropZoneIssueCodes']);
        $t->same(1, $invalid['dropZoneIssueCount']);
        $t->same(['execute', 'string:javascript', 'file:bad', 'mime'], $invalid['invalidDropZoneTokens']);

        $t->same('review', $multi['dropZoneReviewStatus']);
        $t->same(['multiple-html-dropzone-effects'], $multi['dropZoneIssueCodes']);
        $t->same(1, $multi['dropZoneIssueCount']);
        $t->same(true, $multi['dropZoneMultipleEffects']);
        $t->same(['copy', 'move'], $multi['dropZoneEffects']);

        $t->same(
            '<section draggable="auto" dropzone="copy string:text/plain file:image/png" id="valid">Drop</section>'
                . '<a draggable="auto" href="/asset" id="link">Asset</a>'
                . '<section draggable="" dropzone="" id="empty">Empty</section>'
                . '<section draggable="maybe" dropzone="execute string:javascript file:bad mime" id="invalid">Bad</section>'
                . '<section dropzone="copy move string:text/html" id="multi">Multi</section>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/drag-drop-issue-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
