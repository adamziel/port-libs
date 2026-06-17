<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html part export mapping provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="host" part="card title card" exportparts="title:review-title, action:review-action, missing:external, shared:review-title, invalid name:alias, extra:">'
                . '<span id="title-part" part="title">Title</span>'
                . '<button id="save" part="action primary">Save</button>'
                . '<span id="shared" part="shared">Shared</span>'
                . '</article><section id="clean" part="region"><span id="label" part="label">Label</span></section>',
            'part export review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/part-export-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $host = $summary[0];
        $clean = $summary[1];
        $label = $clean['children'][0];

        $t->same('html-part-exportparts-fragment-review', $host['partExportReviewPolicy']);
        $t->same('article', $host['partExportElement']);
        $t->same('host', $host['partExportElementId']);
        $t->same(['card'], $host['partDuplicateTokens']);
        $t->same(['card', 'title', 'action', 'primary', 'shared'], $host['visiblePartNames']);
        $t->same(6, $host['visiblePartCount']);
        $t->same(['name' => 'card', 'element' => 'article', 'id' => 'host', 'source' => 'self'], $host['visibleParts'][0]);
        $t->same(['name' => 'action', 'element' => 'button', 'id' => 'save', 'source' => 'descendant'], $host['visibleParts'][3]);

        $t->same(6, $host['exportPartMappingCount']);
        $t->same(4, $host['validExportPartMappingCount']);
        $t->same(4, $host['renamedExportPartMappingCount']);
        $t->same(['title', 'action', 'shared'], $host['observedExportPartNames']);
        $t->same(['missing'], $host['unobservedExportPartNames']);
        $t->same(['review-title'], $host['duplicateExportPartAliases']);
        $t->same([
            'duplicate-part-token',
            'invalid-exportparts-mapping',
            'duplicate-exportparts-alias',
            'unobserved-exportparts-source',
        ], $host['partExportIssueCodes']);
        $t->same(false, $host['partExportValid']);

        $t->same(true, $host['exportPartMappings'][0]['sourceObservedInFragment']);
        $t->same(['host', 'title-part'], $host['exportPartMappings'][0]['sourceElementIds']);
        $t->same(['article', 'span'], $host['exportPartMappings'][0]['sourceElementNames']);
        $t->same('missing', $host['exportPartMappings'][2]['source']);
        $t->same(false, $host['exportPartMappings'][2]['sourceObservedInFragment']);
        $t->same(false, $host['exportPartMappings'][4]['valid']);
        $t->same(false, $host['exportPartMappings'][5]['valid']);

        $t->same('html-part-exportparts-fragment-review', $clean['partExportReviewPolicy']);
        $t->same(['region', 'label'], $clean['visiblePartNames']);
        $t->same(0, $clean['exportPartMappingCount']);
        $t->same(true, $clean['partExportValid']);
        $t->same(['label'], $label['visiblePartNames']);

        $t->same(
            '<article exportparts="title:review-title, action:review-action, missing:external, shared:review-title, invalid name:alias, extra:" id="host" part="card title card"><span id="title-part" part="title">Title</span><button id="save" part="action primary">Save</button><span id="shared" part="shared">Shared</span></article><section id="clean" part="region"><span id="label" part="label">Label</span></section>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/part-export-review.html', $document->children[0]->attr('part'));
    },
];
