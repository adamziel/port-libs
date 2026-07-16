<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html part and exportparts duplicate mappings for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="host" part="card title card invalid=name" exportparts="title:panel-title, title:headline, icon:panel-title, badge, bad:mapping:extra"><button id="action" part="action action primary">Save</button></section>',
            'part and exportparts duplicate review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/part-export-duplicate-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $host = $summary[0];
        $button = $host['children'][0];

        $t->same('html-part-token-list-review', $host['partReviewPolicy']);
        $t->same('card title card invalid=name', $host['partRaw']);
        $t->same(['card', 'title', 'card', 'invalid=name'], $host['partTokens']);
        $t->same(['card', 'title'], $host['partNames']);
        $t->same(['invalid=name'], $host['invalidPartTokens']);
        $t->same(['card'], $host['duplicatePartTokens']);
        $t->same(['invalid-part-token', 'duplicate-part-token'], $host['partIssueCodes']);
        $t->same(false, $host['partValid']);

        $t->same('html-exportparts-mapping-review', $host['exportPartsReviewPolicy']);
        $t->same('title:panel-title, title:headline, icon:panel-title, badge, bad:mapping:extra', $host['exportPartsRaw']);
        $t->same(['title', 'icon', 'badge'], $host['exportPartNames']);
        $t->same(['panel-title', 'headline', 'badge'], $host['exportPartAliases']);
        $t->same(['bad:mapping:extra'], $host['invalidExportParts']);
        $t->same(['title'], $host['duplicateExportPartNames']);
        $t->same(['panel-title'], $host['duplicateExportPartAliases']);
        $t->same(
            ['invalid-exportparts-mapping', 'duplicate-exportparts-source', 'duplicate-exportparts-alias'],
            $host['exportPartsIssueCodes']
        );
        $t->same(false, $host['exportPartsValid']);
        $t->same([
            ['raw' => 'title:panel-title', 'source' => 'title', 'alias' => 'panel-title', 'renamed' => true, 'valid' => true],
            ['raw' => 'title:headline', 'source' => 'title', 'alias' => 'headline', 'renamed' => true, 'valid' => true],
            ['raw' => 'icon:panel-title', 'source' => 'icon', 'alias' => 'panel-title', 'renamed' => true, 'valid' => true],
            ['raw' => 'badge', 'source' => 'badge', 'alias' => 'badge', 'renamed' => false, 'valid' => true],
            ['raw' => 'bad:mapping:extra', 'source' => 'bad', 'alias' => 'mapping', 'renamed' => false, 'valid' => false],
        ], $host['exportParts']);

        $t->same('action action primary', $button['partRaw']);
        $t->same(['action', 'primary'], $button['partNames']);
        $t->same([], $button['invalidPartTokens']);
        $t->same(['action'], $button['duplicatePartTokens']);
        $t->same(['duplicate-part-token'], $button['partIssueCodes']);
        $t->same(true, $button['partValid']);

        $t->same(
            '<section exportparts="title:panel-title, title:headline, icon:panel-title, badge, bad:mapping:extra" id="host" part="card title card invalid=name"><button id="action" part="action action primary">Save</button></section>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/part-export-duplicate-review.html', $document->children[0]->attr('part'));
    },
];
