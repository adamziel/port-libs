<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html form dirname directionality for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="direction" dir="rtl">'
                . '<input id="title" name="title" dirname="title.dir" value="Review">'
                . '<textarea id="notes" name="notes" dirname="notes.dir" dir="auto">English notes</textarea>'
                . '<input id="bad" name="bad" dirname="bad name" dir="sideways" value="Bad">'
                . '<input id="collision" name="same" dirname="same" dir="ltr" value="Same">'
                . '</form>'
                . '<input id="default" name="fallback" dirname="fallback.dir" value="Fallback">',
            'form dirname directionality review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/form-dirname-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $controls = [];
        foreach ($form['children'] as $control) {
            $controls[(string) $control['elementId']] = $control;
        }

        $title = $controls['title'];
        $notes = $controls['notes'];
        $bad = $controls['bad'];
        $collision = $controls['collision'];
        $default = $summary[1];

        $t->same('form-control-dirname-directionality-review', $title['dirnameReviewPolicy']);
        $t->same('title.dir', $title['dirnameRaw']);
        $t->same('title.dir', $title['dirname']);
        $t->same('title.dir', $title['dirnameName']);
        $t->same('title.dir', $title['dirnameSubmitName']);
        $t->same(true, $title['dirnameValid']);
        $t->same('title', $title['dirnameControlName']);
        $t->same('input', $title['dirnameControlElement']);
        $t->same('text', $title['dirnameControlType']);
        $t->same('direction', $title['dirnameFormOwnerId']);
        $t->same(true, $title['dirnameFormOwnerFound']);
        $t->same(false, $title['dirnameEffectiveDisabled']);
        $t->same('rtl', $title['dirnameDirectionState']);
        $t->same('rtl', $title['dirnameDirection']);
        $t->same('rtl', $title['dirnameSubmittedDirection']);
        $t->same('ancestor-dir', $title['dirnameDirectionSource']);
        $t->same('form', $title['dirnameDirectionSourceElement']);
        $t->same('direction', $title['dirnameDirectionSourceElementId']);
        $t->same(true, $title['dirnameDirectionInherited']);
        $t->same(false, $title['dirnameDirectionDefaulted']);
        $t->same(false, $title['dirnameDirectionNeutralDefaulted']);
        $t->same(true, $title['dirnameWouldSubmitDirection']);
        $t->same(true, $title['dirnameReviewOnlyNoFormSubmission']);
        $t->same([], $title['dirnameIssueCodes']);

        $t->same('textarea', $notes['dirnameControlElement']);
        $t->same(null, $notes['dirnameControlType']);
        $t->same('auto', $notes['dirnameDirectionState']);
        $t->same('auto', $notes['dirnameDirectionRaw']);
        $t->same('ltr', $notes['dirnameDirection']);
        $t->same('ltr', $notes['dirnameDirectionResolved']);
        $t->same('self-dir', $notes['dirnameDirectionSource']);
        $t->same(false, $notes['dirnameDirectionInherited']);
        $t->same([], $notes['dirnameIssueCodes']);

        $t->same('bad name', $bad['dirnameRaw']);
        $t->same('bad name', $bad['dirnameName']);
        $t->same(false, $bad['dirnameValid']);
        $t->same('rtl', $bad['dirnameDirection']);
        $t->same(true, $bad['dirnameDirectionInherited']);
        $t->same(false, $bad['dirnameWouldSubmitDirection']);
        $t->same([
            ['code' => 'invalid-form-control-dirname', 'dirnameRaw' => 'bad name'],
        ], $bad['dirnameIssues']);
        $t->same(['invalid-form-control-dirname'], $bad['dirnameIssueCodes']);

        $t->same('same', $collision['dirnameName']);
        $t->same('same', $collision['dirnameControlName']);
        $t->same(true, $collision['dirnameValid']);
        $t->same('ltr', $collision['dirnameDirection']);
        $t->same(['dirname-name-collides-with-control-name'], $collision['dirnameIssueCodes']);

        $t->same('fallback.dir', $default['dirnameName']);
        $t->same('ltr', $default['dirnameDirectionState']);
        $t->same('ltr', $default['dirnameDirection']);
        $t->same('html-default', $default['dirnameDirectionSource']);
        $t->same(null, $default['dirnameDirectionSourceElement']);
        $t->same(false, $default['dirnameDirectionInherited']);
        $t->same(true, $default['dirnameDirectionDefaulted']);
        $t->same(null, $default['dirnameFormOwnerId']);
        $t->same(false, $default['dirnameFormOwnerFound']);
        $t->same(false, $default['dirnameWouldSubmitDirection']);
        $t->same([], $default['dirnameIssueCodes']);

        $t->contains('dirname="title.dir"', $html);
        $t->contains('dirname="bad name"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/form-dirname-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
