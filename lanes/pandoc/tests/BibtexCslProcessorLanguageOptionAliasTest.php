<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries legacy biblatex language option aliases in csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{langid-option-packet,
  author          = {Garcia, Nia},
  title           = {Langid Option Packet},
  publisher       = {Review Press},
  date            = {2026},
  langid          = {spanish},
  langid-options  = {variant=mexican, hyphenation=traditional}
}

@book{language-option-packet,
  author           = {Roe, Pat},
  title            = {Language Option Packet},
  publisher        = {Migration Desk},
  date             = {2025},
  language         = {french},
  language-options = {autolang=other, clearlang=true}
}

@book{hyphenation-option-packet,
  author              = {Ng, Ada},
  title               = {Hyphenation Option Packet},
  publisher           = {Archive Desk},
  date                = {2024},
  hyphenation         = {ngerman},
  hyphenation-options = {variant=oldorthography, sentencecase=false}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $langid = $items['langid-option-packet'];
        $language = $items['language-option-packet'];
        $hyphenation = $items['hyphenation-option-packet'];

        $t->same('spanish', $langid['language']);
        $t->same(['variant=mexican', 'hyphenation=traditional'], $langid['biblatex-language-options']);
        $t->same('variant=mexican, hyphenation=traditional', $langid['rawBibtex']['fields']['langid-options']);
        $t->same('french', $language['language']);
        $t->same(['autolang=other', 'clearlang=true'], $language['biblatex-language-options']);
        $t->same('autolang=other, clearlang=true', $language['rawBibtex']['fields']['language-options']);
        $t->same('ngerman', $hyphenation['language']);
        $t->same(['variant=oldorthography', 'sentencecase=false'], $hyphenation['biblatex-language-options']);
        $t->same('variant=oldorthography, sentencecase=false', $hyphenation['rawBibtex']['fields']['hyphenation-options']);
        $t->contains(
            'BibLaTeX language options: variant=mexican; hyphenation=traditional',
            $processor->renderBibliographyText($langid)
        );
        $t->contains(
            'BibLaTeX language options: autolang=other; clearlang=true',
            $processor->renderBibliographyText($language)
        );
        $t->contains(
            'BibLaTeX language options: variant=oldorthography; sentencecase=false',
            $processor->renderBibliographyText($hyphenation)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Language Option Alias Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-language-option-alias-review</id>
    <updated>2026-07-02T01:20:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="language"/>
        <text variable="biblatex-language-options"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="language-list"/>
      <text variable="biblatex-language-option-summary"/>
    </layout>
  </bibliography>
</style>
XML);

        $normalizedLanguage = $styled->item('language-option-packet');
        $t->same('Bounded Legacy BibLaTeX Language Option Alias Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same(['autolang=other', 'clearlang=true'], $normalizedLanguage['biblatexLanguageOptions'] ?? null);
        $t->same('autolang=other; clearlang=true', $normalizedLanguage['biblatexLanguageOptionSummary'] ?? null);
        $t->same(
            '[Garcia | spanish | variant=mexican, hyphenation=traditional; Roe | french | autolang=other, clearlang=true; Ng | ngerman | variant=oldorthography, sentencecase=false]',
            $styled->renderCitationCluster([
                new AstNode('citation', ['id' => 'langid-option-packet', 'text' => '[@langid-option-packet]']),
                new AstNode('citation', ['id' => 'language-option-packet', 'text' => '[@language-option-packet]']),
                new AstNode('citation', ['id' => 'hyphenation-option-packet', 'text' => '[@hyphenation-option-packet]']),
            ])
        );
        $t->same(
            'Language Option Packet :: french :: autolang=other; clearlang=true',
            $styled->renderBibliographyEntry('language-option-packet')
        );

        $document = (new MarkdownReader())->read('Language option aliases cite @langid-option-packet and [@language-option-packet; @hyphenation-option-packet].');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['langid-option-packet', 'language-option-packet', 'hyphenation-option-packet'], $handoff['citedKeys']);
        $t->same(['variant=mexican', 'hyphenation=traditional'], $handoff['items'][0]['biblatex-language-options']);
        $t->same(['autolang=other', 'clearlang=true'], $handoff['bibliography']->children[1]->attr('cslItem')['biblatex-language-options'] ?? null);
        $t->contains(
            '<p>Language option aliases cite Garcia (2026) and [Roe | french | autolang=other, clearlang=true; Ng | ngerman | variant=oldorthography, sentencecase=false].</p>',
            $blocks
        );
        $t->contains(
            '<dt>Roe 2025</dt><dd>Language Option Packet :: french :: autolang=other; clearlang=true</dd>',
            $blocks
        );
    },
];
