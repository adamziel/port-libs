<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$cslJson = static fn (): string => json_encode([
    [
        'id' => 'smith1899',
        'type' => 'book',
        'title' => 'Migration Patterns',
        'author' => [
            ['family' => 'Smith', 'given' => 'Ada'],
        ],
        'issued' => ['date-parts' => [[1899]]],
        'publisher' => 'Archive Press',
        'DOI' => '10.1234/source',
    ],
    [
        'id' => 'doe2020',
        'type' => 'article-journal',
        'title' => 'Field Notes',
        'container-title' => 'Journal of Imports',
        'author' => [
            ['family' => 'Doe', 'given' => 'Jane'],
            ['family' => 'Roe', 'given' => 'Pat'],
        ],
        'issued' => ['date-parts' => [[2020, 6, 1]]],
        'page' => '55-60',
        'URL' => 'https://example.test/field-notes',
    ],
    [
        'id' => 'wp-team',
        'type' => 'webpage',
        'title' => 'Reviewer Log',
        'author' => [
            ['literal' => 'WordPress Migration Team'],
        ],
        'issued' => ['date-parts' => [['2024']]],
        'URL' => 'https://example.test/reviewer-log',
    ],
    [
        'id' => 'https://example.com/bib?name=foobar&date=2000',
        'type' => 'webpage',
        'title' => 'URL Key Source',
        'issued' => ['date-parts' => [[2000]]],
        'URL' => 'https://example.com/bib?name=foobar&date=2000',
    ],
], JSON_THROW_ON_ERROR);

$citation = static function (string $id, string $text, string $mode = 'normal', array $attrs = []): AstNode {
    return new AstNode('citation', [
        'id' => $id,
        'text' => $text,
        'mode' => $mode,
        ...$attrs,
    ], [
        new AstNode('text', ['text' => $text]),
    ]);
};

return [
    'normalizes csl json records and renders bounded author date citation clusters' => static function (TestRunner $t) use ($cslJson, $citation): void {
        $processor = CitationCslProcessor::fromJson($cslJson());
        $prefixed = $citation('smith1899', '[@smith1899]', 'normal', ['prefix' => 'see', 'suffix' => 'p. 7']);
        $journal = $citation('doe2020', '[@doe2020]');
        $suppressed = $citation('smith1899', '[-@smith1899]', 'suppress_author', ['suffix' => [
            new AstNode('text', ['text' => 'pp.']),
            new AstNode('space'),
            new AstNode('code', ['text' => '8-9']),
        ]]);
        $authorText = $citation('doe2020', '@doe2020', 'author_in_text', ['suffix' => 'ch. 2']);
        $team = $citation('wp-team', '@wp-team', 'author_in_text');
        $missing = $citation('missing', '[@missing]');

        $t->same('(see Smith 1899, p. 7; Doe and Roe 2020; 1899, pp. 8-9)', $processor->renderCitationCluster([$prefixed, $journal, $suppressed]));
        $t->same('Doe and Roe (2020, ch. 2)', $processor->renderCitationCluster([$authorText]));
        $t->same('WordPress Migration Team (2024)', $processor->renderCitationCluster([$team]));
        $t->same('([@missing])', $processor->renderCitationCluster([$missing]));
        $t->same('Migration Patterns', $processor->item('smith1899')['title'] ?? null);
        $t->same('Journal of Imports', $processor->item('doe2020')['containerTitle'] ?? null);
        $t->same('https://example.test/reviewer-log', $processor->item('wp-team')['url'] ?? null);

        $normalized = $processor->normalizeCitation($prefixed);
        $t->same('citation', $normalized->type);
        $t->same('(see Smith 1899, p. 7)', $normalized->attr('rendered'));
        $t->same('Smith', $normalized->attr('cslLabel'));
        $t->same('1899', $normalized->attr('cslYear'));
        $t->same(false, (bool) $normalized->attr('missingCslItem', false));
        $t->same('smith1899', $normalized->attr('cslItem')['id'] ?? null);

        $missingNormalized = $processor->normalizeCitation($missing);
        $t->same('[@missing]', $missingNormalized->attr('rendered'));
        $t->same(true, (bool) $missingNormalized->attr('missingCslItem', false));
    },
    'normalizes csl date variables and name particles for bibliography handoff' => static function (TestRunner $t) use ($citation): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'particle-source',
                'type' => 'webpage',
                'title' => 'Source Packet',
                'author' => [
                    [
                        'family' => 'Cruz',
                        'given' => 'Ana Maria',
                        'non-dropping-particle' => 'de la',
                        'suffix' => 'Jr.',
                        'comma-suffix' => true,
                    ],
                ],
                'issued' => ['date-parts' => [[2026, 6, 4]]],
                'accessed' => ['date-parts' => [['2026', '06', '05']]],
                'URL' => 'https://example.test/source-packet',
            ],
            [
                'id' => 'edited-manual',
                'type' => 'book',
                'title' => 'Edited Migration Manual',
                'editor' => [
                    [
                        'family' => 'Curator',
                        'given' => 'Eli',
                        'suffix' => 'III',
                        'comma-suffix' => false,
                    ],
                ],
                'issued' => ['literal' => 'forthcoming'],
                'publisher' => 'Review Press',
            ],
        ]);

        $particle = $processor->item('particle-source');
        $t->same([2026, 6, 4], $particle['issuedDate']['parts'] ?? null);
        $t->same(2026, $particle['issuedDate']['year'] ?? null);
        $t->same('2026-06-04', $particle['issuedDate']['display'] ?? null);
        $t->same([2026, 6, 5], $particle['accessedDate']['parts'] ?? null);
        $t->same('2026-06-05', $particle['accessedDate']['display'] ?? null);
        $t->same('de la', $particle['authors'][0]['nonDroppingParticle'] ?? null);
        $t->same('Jr.', $particle['authors'][0]['suffix'] ?? null);
        $t->same(true, $particle['authors'][0]['commaSuffix'] ?? null);
        $t->same('(de la Cruz 2026)', $processor->renderCitationCluster([$citation('particle-source', '[@particle-source]')]));
        $t->same('de la Cruz, Ana Maria, Jr. Source Packet. 2026. https://example.test/source-packet. Accessed 2026-06-05.', $processor->renderBibliographyEntry('particle-source'));

        $edited = $processor->item('edited-manual');
        $t->same(null, $edited['issuedDate']['year'] ?? null);
        $t->same([], $edited['issuedDate']['parts'] ?? null);
        $t->same('forthcoming', $edited['issuedDate']['literal'] ?? null);
        $t->same('forthcoming', $edited['issuedDate']['display'] ?? null);
        $t->same('III', $edited['editors'][0]['suffix'] ?? null);
        $t->same(false, $edited['editors'][0]['commaSuffix'] ?? null);
        $t->same('(Curator forthcoming)', $processor->renderCitationCluster([$citation('edited-manual', '[@edited-manual]')]));
        $t->same('Curator, Eli III. Edited Migration Manual. Review Press, forthcoming.', $processor->renderBibliographyEntry('edited-manual'));

        $bibliography = $processor->bibliographyDefinitionList(['particle-source', 'edited-manual']);
        $t->same('de la Cruz 2026', $bibliography->children[0]->children[0]->attr('text'));
        $t->same('Curator forthcoming', $bibliography->children[1]->children[0]->attr('text'));
    },
    'parses bounded bibtex and biblatex entries into csl bibliography items' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@comment{ignored by the bounded bibliography handoff}

@book{smith1899,
  author    = {Smith, Ada},
  title     = {Migration Patterns},
  year      = {1899},
  publisher = {Archive Press},
  doi       = {10.1234/source}
}

@article{doe2020,
  author       = {Doe, Jane and Roe, Pat},
  title        = {Field Notes},
  journaltitle = {Journal of Imports},
  date         = {2020-06-01},
  pages        = {55--60},
  url          = {https://example.test/field-notes},
  urldate      = {2026-06-04}
}

@online{wp-team,
  author = {{WordPress Migration Team}},
  title  = {Reviewer Log},
  date   = {2024},
  url    = {https://example.test/reviewer-log}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(3, count($items));
        $t->same('smith1899', $items[0]['id']);
        $t->same('book', $items[0]['type']);
        $t->same('article-journal', $items[1]['type']);
        $t->same('Journal of Imports', $items[1]['container-title']);
        $t->same('55-60', $items[1]['page']);
        $t->same('webpage', $items[2]['type']);
        $t->same([['literal' => 'WordPress Migration Team']], $items[2]['author']);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $journal = $processor->item('doe2020');
        $t->same([2020, 6, 1], $journal['issuedDate']['parts'] ?? null);
        $t->same('2020-06-01', $journal['issuedDate']['display'] ?? null);
        $t->same([2026, 6, 4], $journal['accessedDate']['parts'] ?? null);
        $t->same('Doe and Roe', $journal === null ? null : $processor->normalizeCitation($citation('doe2020', '[@doe2020]'))->attr('cslLabel'));
        $t->same('(Smith 1899; Doe and Roe 2020; WordPress Migration Team 2024)', $processor->renderCitationCluster([
            $citation('smith1899', '[@smith1899]'),
            $citation('doe2020', '[@doe2020]'),
            $citation('wp-team', '[@wp-team]'),
        ]));
        $t->same('Doe, Jane; Roe, Pat. Field Notes. Journal of Imports. 2020. 55-60. https://example.test/field-notes. Accessed 2026-06-04.', $processor->renderBibliographyEntry('doe2020'));

        $document = (new MarkdownReader())->read('Review cites [see @smith1899; @doe2020, pp. 55-60] and @wp-team.');
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains('Review cites (see Smith 1899; Doe and Roe 2020, pp. 55-60) and WordPress Migration Team (2024).', $markdown);
        $t->contains('Smith 1899' . "\n" . ':   Smith, Ada. Migration Patterns. Archive Press, 1899. DOI 10.1234/source.', $markdown);
        $t->contains('WordPress Migration Team 2024' . "\n" . ':   WordPress Migration Team. Reviewer Log. 2024. https://example.test/reviewer-log.', $markdown);
    },
    'maps bibtex strings particles suffixes and literal dates into csl metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@string{packet = "Packet"}

@misc{particle-source,
  author = {de la Cruz, Ana Maria, Jr. and Eli Curator},
  editor = {Curator, Eli, III},
  title  = "Source " # packet,
  year   = {2026},
  month  = jun,
  day    = {4},
  url    = {https://example.test/source-packet}
}

@book{edited-manual,
  editor    = {Curator, Eli, III},
  title     = {Edited Migration Manual},
  publisher = {Review Press},
  year      = {forthcoming}
}
BIB;

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $particle = $processor->item('particle-source');
        $t->same('Source Packet', $particle['title'] ?? null);
        $t->same([2026, 6, 4], $particle['issuedDate']['parts'] ?? null);
        $t->same('de la', $particle['authors'][0]['nonDroppingParticle'] ?? null);
        $t->same('Cruz', $particle['authors'][0]['family'] ?? null);
        $t->same('Ana Maria', $particle['authors'][0]['given'] ?? null);
        $t->same('Jr.', $particle['authors'][0]['suffix'] ?? null);
        $t->same(true, $particle['authors'][0]['commaSuffix'] ?? null);
        $t->same('Curator', $particle['authors'][1]['family'] ?? null);
        $t->same('(de la Cruz and Curator 2026)', $processor->renderCitationCluster([$citation('particle-source', '[@particle-source]')]));
        $t->same('de la Cruz, Ana Maria, Jr.; Curator, Eli. Source Packet. 2026. https://example.test/source-packet.', $processor->renderBibliographyEntry('particle-source'));

        $edited = $processor->item('edited-manual');
        $t->same('forthcoming', $edited['issuedDate']['literal'] ?? null);
        $t->same('forthcoming', $edited['issuedDate']['display'] ?? null);
        $t->same('III', $edited['editors'][0]['suffix'] ?? null);
        $t->same(true, $edited['editors'][0]['commaSuffix'] ?? null);
        $t->same('(Curator forthcoming)', $processor->renderCitationCluster([$citation('edited-manual', '[@edited-manual]')]));
        $t->same('Curator, Eli, III. Edited Migration Manual. Review Press, forthcoming.', $processor->renderBibliographyEntry('edited-manual'));
    },
    'decodes common tex accents and special letters in bibtex csl handoff' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@article{accented-source,
  author       = {M{\"u}ller, Mia and Garc{\'i}a, Gia and {{S{\o}ren Archive Team}}},
  editor       = {Fran{\c c}ois, Ren{\'e}e},
  title        = {{\'E}tude of Jalape{\~n}o Source Packets},
  journaltitle = {Cr{\`e}me Br{\^u}l{\'e}e Review},
  publisher    = {Rev{\"u} Press},
  date         = {2026-06-05},
  pages        = {7--9},
  url          = {https://example.test/accented}
}
BIB;

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $item = $processor->item('accented-source');
        $t->same('Étude of Jalapeño Source Packets', $item['title'] ?? null);
        $t->same('Crème Brûlée Review', $item['containerTitle'] ?? null);
        $t->same('Revü Press', $item['publisher'] ?? null);
        $t->same('Müller', $item['authors'][0]['family'] ?? null);
        $t->same('Mia', $item['authors'][0]['given'] ?? null);
        $t->same('García', $item['authors'][1]['family'] ?? null);
        $t->same('Søren Archive Team', $item['authors'][2]['literal'] ?? null);
        $t->same('François', $item['editors'][0]['family'] ?? null);
        $t->same('Renée', $item['editors'][0]['given'] ?? null);
        $t->same('(Müller et al. 2026)', $processor->renderCitationCluster([$citation('accented-source', '[@accented-source]')]));
        $t->same('Müller, Mia; García, Gia; Søren Archive Team. Étude of Jalapeño Source Packets. Crème Brûlée Review. Revü Press, 2026. 7-9. https://example.test/accented.', $processor->renderBibliographyEntry('accented-source'));

        $document = (new MarkdownReader())->read('Review keeps @accented-source in source notes.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Review keeps Müller et al. (2026) in source notes.</p>', $blocks);
        $t->contains('<dt>Müller et al. 2026</dt><dd>Müller, Mia; García, Gia; Søren Archive Team. Étude of Jalapeño Source Packets. Crème Brûlée Review. Revü Press, 2026. 7-9. https://example.test/accented.</dd>', $blocks);
    },
    'inherits bounded bibtex crossref fields into child csl items' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@proceedings{conf2026,
  editor    = {Curator, Eli and de la Cruz, Ana Maria},
  title     = {Migration Futures Conference},
  year      = {2026},
  publisher = {Review Press},
  address   = {Portland}
}

@inproceedings{source-audit,
  author   = {Smith, Ada},
  title    = {Packet Audit Trails},
  pages    = {12--18},
  crossref = {conf2026}
}

@incollection{chapter-review,
  author    = {Roe, Pat},
  title     = {Chapter Review Notes},
  booktitle = {Manual Override},
  year      = {2027},
  crossref  = {conf2026}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(3, count($items));
        $t->same('paper-conference', $items[1]['type']);
        $t->same('Migration Futures Conference', $items[1]['container-title']);
        $t->same('Review Press', $items[1]['publisher']);
        $t->same([['family' => 'Curator', 'given' => 'Eli'], ['family' => 'Cruz', 'given' => 'Ana Maria', 'non-dropping-particle' => 'de la']], $items[1]['editor']);
        $t->same('chapter', $items[2]['type']);
        $t->same('Manual Override', $items[2]['container-title']);
        $t->same(2027, $items[2]['issued']['date-parts'][0][0] ?? null);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $audit = $processor->item('source-audit');
        $t->same('Packet Audit Trails', $audit['title'] ?? null);
        $t->same('Migration Futures Conference', $audit['containerTitle'] ?? null);
        $t->same('Review Press', $audit['publisher'] ?? null);
        $t->same([2026], $audit['issuedDate']['parts'] ?? null);
        $t->same('Eli', $audit['editors'][0]['given'] ?? null);
        $t->same('(Smith 2026; Roe 2027)', $processor->renderCitationCluster([
            $citation('source-audit', '[@source-audit]'),
            $citation('chapter-review', '[@chapter-review]'),
        ]));
        $t->same('Smith, Ada. Packet Audit Trails. Migration Futures Conference. Review Press, 2026. 12-18.', $processor->renderBibliographyEntry('source-audit'));

        $document = (new MarkdownReader())->read('Review cites @source-audit and [@chapter-review].');
        $markdown = (new MarkdownWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('Review cites Smith (2026) and (Roe 2027).', $markdown);
        $t->contains('Smith 2026' . "\n" . ':   Smith, Ada. Packet Audit Trails. Migration Futures Conference. Review Press, 2026. 12-18.', $markdown);
        $t->contains('Roe 2027' . "\n" . ':   Roe, Pat. Chapter Review Notes. Manual Override. Review Press, 2027.', $markdown);
    },
    'inherits bounded biblatex xdata fields and preserves reviewer metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@xdata{shared-review-packet,
  publisher = {Migration Desk},
  date      = {2026-06-05},
  keywords  = {wordpress, import, reviewer},
  abstract  = {Reviewer summary for source packet handoff.}
}

@xdata{attachment-review-packet,
  langid = {english},
  file   = {Review PDF:attachments/source-audit.pdf:application/pdf; Source HTML:attachments/source-audit.html:text/html}
}

@inreference{source-glossary,
  author    = {Ng, Nia},
  title     = {Import Glossary},
  booktitle = {Migration Reference},
  url       = {https://example.test/glossary},
  xdata     = {shared-review-packet, attachment-review-packet}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(1, count($items));
        $t->same('source-glossary', $items[0]['id']);
        $t->same('entry-encyclopedia', $items[0]['type']);
        $t->same('Migration Reference', $items[0]['container-title']);
        $t->same('Migration Desk', $items[0]['publisher']);
        $t->same(['date-parts' => [[2026, 6, 5]]], $items[0]['issued']);
        $t->same('english', $items[0]['language']);
        $t->same(['wordpress', 'import', 'reviewer'], $items[0]['keyword']);
        $t->same('Reviewer summary for source packet handoff.', $items[0]['abstract']);
        $t->same([
            ['label' => 'Review PDF', 'path' => 'attachments/source-audit.pdf', 'mediaType' => 'application/pdf'],
            ['label' => 'Source HTML', 'path' => 'attachments/source-audit.html', 'mediaType' => 'text/html'],
        ], $items[0]['sourceFiles']);
        $t->same('shared-review-packet, attachment-review-packet', $items[0]['rawBibtex']['fields']['xdata'] ?? null);
        $t->same('wordpress, import, reviewer', $items[0]['rawBibtex']['fields']['keywords'] ?? null);
        $t->same('Review PDF:attachments/source-audit.pdf:application/pdf; Source HTML:attachments/source-audit.html:text/html', $items[0]['rawBibtex']['fields']['file'] ?? null);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $item = $processor->item('source-glossary');
        $t->same('english', $item['language'] ?? null);
        $t->same(['wordpress', 'import', 'reviewer'], $item['keywords'] ?? null);
        $t->same('Reviewer summary for source packet handoff.', $item['abstract'] ?? null);
        $t->same('attachments/source-audit.pdf', $item['sourceFiles'][0]['path'] ?? null);
        $t->same([2026, 6, 5], $item['issuedDate']['parts'] ?? null);
        $t->same('(Ng 2026)', $processor->renderCitationCluster([$citation('source-glossary', '[@source-glossary]')]));
        $t->same('Ng, Nia. Import Glossary. Migration Reference. Migration Desk, 2026. https://example.test/glossary.', $processor->renderBibliographyEntry('source-glossary'));

        $document = (new MarkdownReader())->read('Glossary entry @source-glossary keeps inherited source packet metadata.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Glossary entry Ng (2026) keeps inherited source packet metadata.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Ng, Nia. Import Glossary. Migration Reference. Migration Desk, 2026. https://example.test/glossary.</dd>', $blocks);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromBibtex('@xdata{a,xdata={b}} @xdata{b,xdata={a}} @online{site,title={Site},xdata={a}}'));
    },
    'applies bounded biblatex source file attachment policy diagnostics' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@xdata{attachment-policy,
  file = {Review PDF:attachments/source-audit.pdf:application/pdf; Reviewer Notes:attachments/reviewer%20notes.html:text/html; Remote PDF:https://example.test/source-audit.pdf:application/pdf; Absolute PDF:/var/private/source-audit.pdf:application/pdf; Traversal PDF:../private/source-audit.pdf:application/pdf; Encoded Traversal:attachments/%2e%2e/private.pdf:application/pdf; Windows PDF:C:\Users\Ada\source-audit.pdf:application/pdf; Backslash PDF:attachments\source-audit.pdf:application/pdf; Bad Percent:attachments/%XX/source.pdf:application/pdf; Encoded Slash:attachments/%2Fsource.pdf:application/pdf; Missing::application/pdf}
}

@online{source-attachment,
  author = {Ng, Nia},
  title  = {Attachment Source},
  date   = {2026-06-05},
  url    = {https://example.test/source-attachment},
  xdata  = {attachment-policy}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(1, count($items));
        $t->same([
            ['label' => 'Review PDF', 'path' => 'attachments/source-audit.pdf', 'mediaType' => 'application/pdf'],
            ['label' => 'Reviewer Notes', 'path' => 'attachments/reviewer notes.html', 'mediaType' => 'text/html'],
        ], $items[0]['sourceFiles']);
        $diagnostics = $items[0]['sourceFileDiagnostics'] ?? [];
        $t->same(9, count($diagnostics));
        $t->same([
            'remote-uri',
            'absolute-path',
            'path-traversal',
            'path-traversal',
            'windows-drive-path',
            'backslash-separator',
            'malformed-percent-escape',
            'unsafe-percent-encoded-path-byte',
            'missing-path',
        ], array_column($diagnostics, 'reason'));
        $t->same('Remote PDF', $diagnostics[0]['label'] ?? null);
        $t->same('https://example.test/source-audit.pdf', $diagnostics[0]['path'] ?? null);
        $t->same(false, $diagnostics[0]['importable'] ?? null);
        $t->same('Missing', $diagnostics[8]['label'] ?? null);
        $t->same('application/pdf', $diagnostics[8]['mediaType'] ?? null);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $item = $processor->item('source-attachment');
        $t->same('attachments/reviewer notes.html', $item['sourceFiles'][1]['path'] ?? null);
        $t->same('path-traversal', $item['sourceFileDiagnostics'][2]['reason'] ?? null);
        $t->same('Encoded Traversal', $item['sourceFileDiagnostics'][3]['label'] ?? null);
        $t->same('(Ng 2026)', $processor->renderCitationCluster([$citation('source-attachment', '[@source-attachment]')]));

        $document = (new MarkdownReader())->read('Attachment source @source-attachment keeps unsafe file paths in review diagnostics.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Attachment source Ng (2026) keeps unsafe file paths in review diagnostics.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Ng, Nia. Attachment Source. 2026. https://example.test/source-attachment.</dd>', $blocks);

        $manual = CitationCslProcessor::fromItems([[
            'id' => 'manual-source',
            'title' => 'Manual Source',
            'sourceFiles' => [
                'attachments/manual.pdf',
                'https://example.test/manual.pdf',
            ],
        ]])->item('manual-source');
        $t->same([['label' => '', 'path' => 'attachments/manual.pdf', 'mediaType' => '']], $manual['sourceFiles'] ?? null);
        $t->same('remote-uri', $manual['sourceFileDiagnostics'][0]['reason'] ?? null);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([['id' => 'bad-diagnostic', 'sourceFileDiagnostics' => 'bad']]));
    },
    'preserves bounded biblatex entry sets and related entry metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@set{migration-review-set,
  title    = {Migration Review Set},
  date     = {2026-06-05},
  entryset = {audit-paper, archived-site, missing-source}
}

@proceedings{conf2026,
  options   = {dataonly},
  title     = {Migration Futures Conference},
  date      = {2026},
  publisher = {Review Press}
}

@inproceedings{audit-paper,
  options  = {dataonly},
  author   = {Smith, Ada},
  title    = {Packet Audit Trails},
  pages    = {12--18},
  crossref = {conf2026}
}

@online{archived-site,
  options = {dataonly},
  author  = {{Archive Team}},
  title   = {Archive Site},
  date    = {2026-05-31},
  url     = {https://example.test/archive-site}
}

@book{related-manual,
  author        = {Curator, Eli},
  title         = {Migration Manual},
  date          = {2024},
  related       = {migration-review-set, missing-related},
  relatedtype   = {companion},
  relatedstring = {Companion review set}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(2, count($items));
        $t->same('migration-review-set', $items[0]['id']);
        $t->same('entry', $items[0]['type']);
        $t->same(['audit-paper', 'archived-site', 'missing-source'], $items[0]['entrySet']);
        $t->same(['missing-source'], $items[0]['missingEntrySetKeys']);
        $t->same('audit-paper', $items[0]['entrySetItems'][0]['id'] ?? null);
        $t->same('paper-conference', $items[0]['entrySetItems'][0]['type'] ?? null);
        $t->same('Migration Futures Conference', $items[0]['entrySetItems'][0]['container-title'] ?? null);
        $t->same('Review Press', $items[0]['entrySetItems'][0]['publisher'] ?? null);
        $t->same('12-18', $items[0]['entrySetItems'][0]['page'] ?? null);
        $t->same(true, $items[0]['entrySetItems'][0]['dataOnly'] ?? null);
        $t->same('archived-site', $items[0]['entrySetItems'][1]['id'] ?? null);
        $t->same('Archive Team', $items[0]['entrySetItems'][1]['author'][0]['literal'] ?? null);

        $t->same('related-manual', $items[1]['id']);
        $t->same(['migration-review-set', 'missing-related'], $items[1]['relatedKeys']);
        $t->same('companion', $items[1]['relatedType']);
        $t->same('Companion review set', $items[1]['relatedString']);
        $t->same('migration-review-set', $items[1]['relatedItems'][0]['id'] ?? null);
        $t->same('Migration Review Set', $items[1]['relatedItems'][0]['title'] ?? null);
        $t->same(['missing-related'], $items[1]['missingRelatedKeys']);
        $t->same('companion', $items[1]['rawBibtex']['fields']['relatedtype'] ?? null);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $set = $processor->item('migration-review-set');
        $manual = $processor->item('related-manual');
        $t->same(['audit-paper', 'archived-site', 'missing-source'], $set['raw']['entrySet'] ?? null);
        $t->same(['missing-source'], $set['raw']['missingEntrySetKeys'] ?? null);
        $t->same('Packet Audit Trails', $set['raw']['entrySetItems'][0]['title'] ?? null);
        $t->same('Migration Review Set', $manual['raw']['relatedItems'][0]['title'] ?? null);
        $t->same('(Migration Review Set 2026; Curator 2024)', $processor->renderCitationCluster([
            $citation('migration-review-set', '[@migration-review-set]'),
            $citation('related-manual', '[@related-manual]'),
        ]));
        $t->same(['audit-paper'], $processor->missingCitationIds((new MarkdownReader())->read('Standalone data-only member [@audit-paper] stays missing.')));

        $document = (new MarkdownReader())->read('Review cites @migration-review-set and related manual @related-manual.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Review cites Migration Review Set (2026) and related manual Curator (2024).</p>', $blocks);
        $t->contains('<dt>Migration Review Set 2026</dt><dd>Migration Review Set. 2026.</dd>', $blocks);
        $t->contains('<dt>Curator 2024</dt><dd>Curator, Eli. Migration Manual. 2024.</dd>', $blocks);
    },
    'maps bounded biblatex translation and original publication metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@book{translated-manual,
  author        = {Garc{\'i}a, Gia},
  translator    = {Curator, Eli and de la Cruz, Ana Maria},
  title         = {Migration Manual},
  origtitle     = {Manual de Migraci{\'o}n},
  date          = {2026},
  origdate      = {2020-05},
  publisher     = {Review Press},
  origpublisher = {Archivo Press},
  origlocation  = {Madrid},
  language      = {english},
  origlanguage  = {spanish}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(1, count($items));
        $t->same('translated-manual', $items[0]['id']);
        $t->same('Migration Manual', $items[0]['title']);
        $t->same('Manual de Migración', $items[0]['original-title']);
        $t->same(['date-parts' => [[2020, 5]]], $items[0]['original-date']);
        $t->same('Archivo Press', $items[0]['original-publisher']);
        $t->same('Madrid', $items[0]['original-publisher-place']);
        $t->same('spanish', $items[0]['original-language']);
        $t->same([
            ['family' => 'Curator', 'given' => 'Eli'],
            ['family' => 'Cruz', 'given' => 'Ana Maria', 'non-dropping-particle' => 'de la'],
        ], $items[0]['translator']);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $item = $processor->item('translated-manual');
        $t->same('Manual de Migración', $item['originalTitle'] ?? null);
        $t->same([2020, 5], $item['originalDate']['parts'] ?? null);
        $t->same('2020-05', $item['originalDate']['display'] ?? null);
        $t->same('Archivo Press', $item['originalPublisher'] ?? null);
        $t->same('Madrid', $item['originalPublisherPlace'] ?? null);
        $t->same('spanish', $item['originalLanguage'] ?? null);
        $t->same('Curator', $item['translators'][0]['family'] ?? null);
        $t->same('Ana Maria', $item['translators'][1]['given'] ?? null);
        $t->same('(García 2026)', $processor->renderCitationCluster([$citation('translated-manual', '[@translated-manual]')]));
        $t->same(
            'García, Gia. Migration Manual. Review Press, 2026. Translated by Curator, Eli; de la Cruz, Ana Maria. Original title: Manual de Migración. Original work published 2020-05. Original publisher: Archivo Press, Madrid. Original language: spanish.',
            $processor->renderBibliographyEntry('translated-manual')
        );

        $document = (new MarkdownReader())->read('Review cites translated source @translated-manual for original publication audit.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Review cites translated source García (2026) for original publication audit.</p>', $blocks);
        $t->contains('<dt>García 2026</dt><dd>García, Gia. Migration Manual. Review Press, 2026. Translated by Curator, Eli; de la Cruz, Ana Maria. Original title: Manual de Migración. Original work published 2020-05. Original publisher: Archivo Press, Madrid. Original language: spanish.</dd>', $blocks);
    },
    'maps bounded biblatex patent legislation and jurisdiction metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@patent{import-patent,
  author    = {M{\"u}ller, Mia},
  holder    = {{WordPress Foundation}},
  title     = {Block Import Review Patent},
  number    = {US-123456},
  type      = {patent},
  location  = {US},
  date      = {2026-06-05},
  eventdate = {2024-01-15},
  status    = {granted},
  url       = {https://example.test/patents/us-123456}
}

@legislation{review-act,
  title        = {WordPress Import Review Act},
  number       = {HB 42},
  type         = {statute},
  organization = {Oregon Legislature},
  location     = {Oregon},
  date         = {2025-05-01},
  eventdate    = {2025-06-01}
}

@jurisdiction{queue-case,
  title     = {Import Queue v. Source Packet},
  number    = {No. 24-100},
  type      = {decision},
  court     = {Migration Review Court},
  location  = {9th Cir.},
  date      = {2024-12-12},
  eventdate = {2025-01-02}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(3, count($items));
        $t->same('patent', $items[0]['type']);
        $t->same('US-123456', $items[0]['number']);
        $t->same('patent', $items[0]['genre']);
        $t->same('US', $items[0]['jurisdiction']);
        $t->same('US', $items[0]['publisher-place']);
        $t->same(['date-parts' => [[2024, 1, 15]]], $items[0]['event-date']);
        $t->same([['literal' => 'WordPress Foundation']], $items[0]['holder']);
        $t->same('legislation', $items[1]['type']);
        $t->same('Oregon Legislature', $items[1]['authority']);
        $t->same('legal_case', $items[2]['type']);
        $t->same('Migration Review Court', $items[2]['authority']);
        $t->same('9th Cir.', $items[2]['jurisdiction']);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $patent = $processor->item('import-patent');
        $t->same('patent', $patent['type'] ?? null);
        $t->same('US-123456', $patent['number'] ?? null);
        $t->same('WordPress Foundation', $patent['holders'][0]['literal'] ?? null);
        $t->same([2024, 1, 15], $patent['eventDate']['parts'] ?? null);
        $t->same('granted', $patent['status'] ?? null);
        $t->same('(Müller 2026; WordPress Import Review Act 2025; Import Queue v. Source Packet 2024)', $processor->renderCitationCluster([
            $citation('import-patent', '[@import-patent]'),
            $citation('review-act', '[@review-act]'),
            $citation('queue-case', '[@queue-case]'),
        ]));
        $t->same('Müller, Mia. Block Import Review Patent. 2026. Patent US-123456. Jurisdiction: US. Holder: WordPress Foundation. Event date 2024-01-15. Status: granted. https://example.test/patents/us-123456.', $processor->renderBibliographyEntry('import-patent'));
        $t->same('WordPress Import Review Act. Oregon Legislature, 2025. Statute HB 42. Authority: Oregon Legislature. Jurisdiction: Oregon. Event date 2025-06-01.', $processor->renderBibliographyEntry('review-act'));
        $t->same('Import Queue v. Source Packet. 2024. Decision No. 24-100. Authority: Migration Review Court. Jurisdiction: 9th Cir. Event date 2025-01-02.', $processor->renderBibliographyEntry('queue-case'));

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legal Review</title>
    <id>https://example.test/styles/bounded-legal-review</id>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" ">
        <text variable="authority"/>
        <text variable="number"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". ">
      <text variable="title"/>
      <text variable="genre"/>
      <text variable="number"/>
      <date variable="event-date"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('[US-123456; Oregon Legislature HB 42; Migration Review Court No. 24-100]', $styled->renderCitationCluster([
            $citation('import-patent', '[@import-patent]'),
            $citation('review-act', '[@review-act]'),
            $citation('queue-case', '[@queue-case]'),
        ]));
        $t->same('WordPress Import Review Act. statute. HB 42. 2025-06-01', $styled->renderBibliographyEntry('review-act'));

        $document = (new MarkdownReader())->read('Review cites @import-patent, @review-act, and @queue-case for legal source audit.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Review cites Müller (2026), WordPress Import Review Act (2025), and Import Queue v. Source Packet (2024) for legal source audit.</p>', $blocks);
        $t->contains('<dt>Müller 2026</dt><dd>Müller, Mia. Block Import Review Patent. 2026. Patent US-123456. Jurisdiction: US. Holder: WordPress Foundation. Event date 2024-01-15. Status: granted. https://example.test/patents/us-123456.</dd>', $blocks);
        $t->contains('<dt>WordPress Import Review Act 2025</dt><dd>WordPress Import Review Act. Oregon Legislature, 2025. Statute HB 42. Authority: Oregon Legislature. Jurisdiction: Oregon. Event date 2025-06-01.</dd>', $blocks);
        $t->contains('<dt>Import Queue v. Source Packet 2024</dt><dd>Import Queue v. Source Packet. 2024. Decision No. 24-100. Authority: Migration Review Court. Jurisdiction: 9th Cir. Event date 2025-01-02.</dd>', $blocks);
    },
    'maps bounded biblatex date ranges into csl date metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@book{range-manual,
  author    = {de la Cruz, Ana Maria},
  title     = {Migration Release Window},
  date      = {2020-05/2021-06},
  origdate  = {2018/2019},
  publisher = {Review Press},
  url       = {https://example.test/range-manual},
  urldate   = {2026-06-04/2026-06-05}
}

@legislation{range-rule,
  title        = {Import Review Rule},
  number       = {Rule 7},
  type         = {regulation},
  organization = {Migration Board},
  date         = {2024/2025},
  eventdate    = {2025-01-01/2025-01-31}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(['date-parts' => [[2020, 5], [2021, 6]]], $items[0]['issued']);
        $t->same(['date-parts' => [[2018], [2019]]], $items[0]['original-date']);
        $t->same(['date-parts' => [[2026, 6, 4], [2026, 6, 5]]], $items[0]['accessed']);
        $t->same(['date-parts' => [[2024], [2025]]], $items[1]['issued']);
        $t->same(['date-parts' => [[2025, 1, 1], [2025, 1, 31]]], $items[1]['event-date']);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $manual = $processor->item('range-manual');
        $rule = $processor->item('range-rule');
        $t->same([2020, 5], $manual['issuedDate']['parts'] ?? null);
        $t->same([[2020, 5], [2021, 6]], $manual['issuedDate']['rangeParts'] ?? null);
        $t->same('2020-05/2021-06', $manual['issuedDate']['display'] ?? null);
        $t->same([[2018], [2019]], $manual['originalDate']['rangeParts'] ?? null);
        $t->same('2018/2019', $manual['originalDate']['display'] ?? null);
        $t->same([[2026, 6, 4], [2026, 6, 5]], $manual['accessedDate']['rangeParts'] ?? null);
        $t->same('2026-06-04/2026-06-05', $manual['accessedDate']['display'] ?? null);
        $t->same([[2025, 1, 1], [2025, 1, 31]], $rule['eventDate']['rangeParts'] ?? null);
        $t->same('(de la Cruz 2020/2021; Import Review Rule 2024/2025)', $processor->renderCitationCluster([
            $citation('range-manual', '[@range-manual]'),
            $citation('range-rule', '[@range-rule]'),
        ]));
        $t->same('de la Cruz, Ana Maria. Migration Release Window. Review Press, 2020/2021. Original work published 2018/2019. https://example.test/range-manual. Accessed 2026-06-04/2026-06-05.', $processor->renderBibliographyEntry('range-manual'));
        $t->same('Import Review Rule. Migration Board, 2024/2025. Regulation Rule 7. Authority: Migration Board. Event date 2025-01-01/2025-01-31.', $processor->renderBibliographyEntry('range-rule'));

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <text variable="title"/>
        <date variable="issued"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" | ">
      <text variable="title"/>
      <date variable="issued"/>
      <date variable="original-date"/>
      <date variable="event-date"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('(Migration Release Window 2020-05/2021-06; Import Review Rule 2024/2025)', $styled->renderCitationCluster([
            $citation('range-manual', '[@range-manual]'),
            $citation('range-rule', '[@range-rule]'),
        ]));
        $t->same('Migration Release Window | 2020-05/2021-06 | 2018/2019', $styled->renderBibliographyEntry('range-manual'));
        $t->same('Import Review Rule | 2024/2025 | 2025-01-01/2025-01-31', $styled->renderBibliographyEntry('range-rule'));

        $document = (new MarkdownReader())->read('Review cites @range-manual and [@range-rule] for source date range audit.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Review cites de la Cruz (2020/2021) and (Import Review Rule 2024/2025) for source date range audit.</p>', $blocks);
        $t->contains('<dt>de la Cruz 2020/2021</dt><dd>de la Cruz, Ana Maria. Migration Release Window. Review Press, 2020/2021. Original work published 2018/2019. https://example.test/range-manual. Accessed 2026-06-04/2026-06-05.</dd>', $blocks);
    },
    'maps bounded biblatex split url date fields into accessed csl metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@online{split-url-date,
  author   = {Ng, Nia},
  title    = {Split URL Date Source},
  date     = {2026},
  url      = {https://example.test/split-url-date},
  urlyear  = {2026},
  urlmonth = jun,
  urlday   = {5}
}

@online{numeric-url-date,
  author   = {{Review Desk}},
  title    = {Numeric URL Date Source},
  year     = {2025},
  url      = {https://example.test/numeric-url-date},
  urlyear  = {2026},
  urlmonth = {7},
  urlday   = {9}
}

@online{whole-url-date,
  author   = {Curator, Eli},
  title    = {Whole URL Date Wins},
  date     = {2024},
  url      = {https://example.test/whole-url-date},
  urldate  = {2026-06-01},
  urlyear  = {2026},
  urlmonth = {7},
  urlday   = {9}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(3, count($items));
        $t->same(['date-parts' => [[2026, 6, 5]]], $items[0]['accessed']);
        $t->same(['date-parts' => [[2026, 7, 9]]], $items[1]['accessed']);
        $t->same(['date-parts' => [[2026, 6, 1]]], $items[2]['accessed']);
        $t->same('June', $items[0]['rawBibtex']['fields']['urlmonth'] ?? null);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $split = $processor->item('split-url-date');
        $numeric = $processor->item('numeric-url-date');
        $whole = $processor->item('whole-url-date');
        $t->same([2026, 6, 5], $split['accessedDate']['parts'] ?? null);
        $t->same('2026-06-05', $split['accessedDate']['display'] ?? null);
        $t->same([2026, 7, 9], $numeric['accessedDate']['parts'] ?? null);
        $t->same('2026-07-09', $numeric['accessedDate']['display'] ?? null);
        $t->same('2026-06-01', $whole['accessedDate']['display'] ?? null);
        $t->same('(Ng 2026; Review Desk 2025; Curator 2024)', $processor->renderCitationCluster([
            $citation('split-url-date', '[@split-url-date]'),
            $citation('numeric-url-date', '[@numeric-url-date]'),
            $citation('whole-url-date', '[@whole-url-date]'),
        ]));
        $t->same('Ng, Nia. Split URL Date Source. 2026. https://example.test/split-url-date. Accessed 2026-06-05.', $processor->renderBibliographyEntry('split-url-date'));

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <date variable="accessed"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <date variable="accessed"/>
      <text variable="URL"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('[Ng | 2026-06-05; Review Desk | 2026-07-09]', $styled->renderCitationCluster([
            $citation('split-url-date', '[@split-url-date]'),
            $citation('numeric-url-date', '[@numeric-url-date]'),
        ]));
        $t->same('Split URL Date Source :: 2026-06-05 :: https://example.test/split-url-date', $styled->renderBibliographyEntry('split-url-date'));

        $manual = CitationCslProcessor::fromItems([[
            'id' => 'manual-accessed-source',
            'title' => 'Manual Accessed Source',
            'accessed' => ['date-parts' => [[2026, 7, 9]]],
        ]])->item('manual-accessed-source');
        $t->same('2026-07-09', $manual['accessedDate']['display'] ?? null);

        $document = (new MarkdownReader())->read('Split URL date @split-url-date and numeric source [@numeric-url-date] preserve access-date parts.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Split URL date Ng (2026) and numeric source (Review Desk 2025) preserve access-date parts.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Ng, Nia. Split URL Date Source. 2026. https://example.test/split-url-date. Accessed 2026-06-05.</dd>', $blocks);
        $t->contains('<dt>Review Desk 2025</dt><dd>Review Desk. Numeric URL Date Source. 2025. https://example.test/numeric-url-date. Accessed 2026-07-09.</dd>', $blocks);
    },
    'applies bounded csl date-part forms affixes and range delimiters' => static function (TestRunner $t) use ($citation): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'date-part-source',
                'type' => 'report',
                'title' => 'Date Part Packet',
                'author' => [
                    ['literal' => 'Date Desk'],
                ],
                'issued' => ['date-parts' => [[2026, 6, 5]]],
                'accessed' => ['date-parts' => [[2026, 6, 6], [2026, 6, 7]]],
                'event-date' => ['date-parts' => [[2024, 1, 15]]],
            ],
            [
                'id' => 'range-year-source',
                'type' => 'report',
                'title' => 'Range Year Packet',
                'author' => [
                    ['family' => 'Ng', 'given' => 'Nia'],
                ],
                'issued' => ['date-parts' => [[2020, 5], [2021, 6]]],
            ],
        ])->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Date Part Review Style</title>
    <id>https://example.test/styles/bounded-date-part-review</id>
    <updated>2026-06-05T06:20:00+00:00</updated>
  </info>
  <locale xml:lang="en-US">
    <terms>
      <term name="month-06" form="short">Jun.</term>
      <term name="month-06" form="long">June</term>
    </terms>
  </locale>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <date variable="issued" delimiter=" ">
          <date-part name="month" form="short" strip-periods="true"/>
          <date-part name="day" form="ordinal"/>
          <date-part name="year" form="short" prefix="'"/>
        </date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <date variable="issued" delimiter=" | ">
        <date-part name="month" form="long" text-case="uppercase"/>
        <date-part name="day" form="numeric-leading-zeros" prefix="day "/>
        <date-part name="year"/>
      </date>
      <date variable="accessed" delimiter=" ">
        <date-part name="month" form="numeric"/>
        <date-part name="day" form="numeric-leading-zeros" range-delimiter=" to "/>
        <date-part name="year" form="short" prefix="'"/>
      </date>
      <date variable="event-date" delimiter="/">
        <date-part name="year" form="short"/>
        <date-part name="month" form="numeric-leading-zeros"/>
        <date-part name="day" form="numeric-leading-zeros"/>
      </date>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $processor->cslStyleSummary();
        $citationDateParts = $summary['citationRendering'][0]['children'][1]['dateParts'] ?? [];
        $bibliographyDateParts = $summary['bibliographyRendering'][0]['dateParts'] ?? [];
        $rangeDateParts = $summary['bibliographyRendering'][1]['dateParts'] ?? [];
        $t->same('Bounded Date Part Review Style', $summary['title'] ?? null);
        $t->same('short', $citationDateParts[0]['form'] ?? null);
        $t->same(true, $citationDateParts[0]['stripPeriods'] ?? null);
        $t->same('ordinal', $citationDateParts[1]['form'] ?? null);
        $t->same("'", $citationDateParts[2]['prefix'] ?? null);
        $t->same(' | ', $summary['bibliographyRendering'][0]['delimiter'] ?? null);
        $t->same('uppercase', $bibliographyDateParts[0]['textCase'] ?? null);
        $t->same('day ', $bibliographyDateParts[1]['prefix'] ?? null);
        $t->same(' to ', $rangeDateParts[1]['rangeDelimiter'] ?? null);

        $t->same("(Date Desk Jun 5th '26; Ng May '20/Jun '21)", $processor->renderCitationCluster([
            $citation('date-part-source', '[@date-part-source]'),
            $citation('range-year-source', '[@range-year-source]'),
        ]));
        $t->same("JUNE | day 05 | 2026 :: 6 06 '26 to 6 07 '26 :: 24/01/15", $processor->renderBibliographyEntry('date-part-source'));
        $t->same('MAY | 2020/JUNE | 2021', $processor->renderBibliographyEntry('range-year-source'));

        $document = (new MarkdownReader())->read('Date part source [@date-part-source] and range [@range-year-source] stay reviewable.');
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains("Date part source (Date Desk Jun 5th '26) and range (Ng May '20/Jun '21) stay reviewable.", $markdown);
        $t->contains("Date Desk 2026" . "\n" . ":   JUNE \\| day 05 \\| 2026 :: 6 06 \\'26 to 6 07 \\'26 :: 24/01/15", $markdown);

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains("<p>Date part source (Date Desk Jun 5th &#039;26) and range (Ng May &#039;20/Jun &#039;21) stay reviewable.</p>", $blocks);
        $t->contains("<dt>Date Desk 2026</dt><dd>JUNE | day 05 | 2026 :: 6 06 &#039;26 to 6 07 &#039;26 :: 24/01/15</dd>", $blocks);
        $t->contains('<dt>Ng 2020/2021</dt><dd>MAY | 2020/JUNE | 2021</dd>', $blocks);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <date variable="issued"><date-part name="month" form="roman"/></date>
    </layout>
  </citation>
</style>
XML));
    },
    'applies bounded csl date element text and numeric forms' => static function (TestRunner $t) use ($citation): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'date-form-source',
                'type' => 'report',
                'title' => 'Date Form Packet',
                'author' => [
                    ['literal' => 'Date Form Desk'],
                ],
                'issued' => ['date-parts' => [[2027, 3, 9]]],
                'accessed' => ['date-parts' => [[2027, 3, 10], [2027, 3, 11]]],
                'event-date' => ['date-parts' => [[2026, 12]]],
            ],
            [
                'id' => 'range-form-source',
                'type' => 'report',
                'title' => 'Range Form Packet',
                'author' => [
                    ['family' => 'Ng', 'given' => 'Nia'],
                ],
                'issued' => ['date-parts' => [[2020, 5], [2021, 6]]],
            ],
        ])->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Date Form Review Style</title>
    <id>https://example.test/styles/bounded-date-form-review</id>
    <updated>2026-06-05T10:38:10+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <date variable="issued" form="text"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <date variable="issued" form="text"/>
      <date variable="accessed" form="numeric"/>
      <date variable="event-date" form="text" prefix="event "/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $processor->cslStyleSummary();
        $t->same('Bounded Date Form Review Style', $summary['title'] ?? null);
        $t->same('text', $summary['citationRendering'][0]['children'][1]['form'] ?? null);
        $t->same('text', $summary['bibliographyRendering'][1]['form'] ?? null);
        $t->same('numeric', $summary['bibliographyRendering'][2]['form'] ?? null);
        $t->same('event ', $summary['bibliographyRendering'][3]['prefix'] ?? null);

        $t->same('(Date Form Desk March 9, 2027; Ng May 2020/June 2021)', $processor->renderCitationCluster([
            $citation('date-form-source', '[@date-form-source]'),
            $citation('range-form-source', '[@range-form-source]'),
        ]));
        $t->same('Date Form Packet :: March 9, 2027 :: 3/10/2027/3/11/2027 :: event December 2026', $processor->renderBibliographyEntry('date-form-source'));
        $t->same('Range Form Packet :: May 2020/June 2021', $processor->renderBibliographyEntry('range-form-source'));

        $document = (new MarkdownReader())->read('Date-form source [@date-form-source] and range [@range-form-source] stay reviewable.');
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains('Date-form source (Date Form Desk March 9, 2027) and range (Ng May 2020/June 2021) stay reviewable.', $markdown);
        $t->contains('Date Form Desk 2027' . "\n" . ':   Date Form Packet :: March 9, 2027 :: 3/10/2027/3/11/2027 :: event December 2026', $markdown);

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Date-form source (Date Form Desk March 9, 2027) and range (Ng May 2020/June 2021) stay reviewable.</p>', $blocks);
        $t->contains('<dt>Date Form Desk 2027</dt><dd>Date Form Packet :: March 9, 2027 :: 3/10/2027/3/11/2027 :: event December 2026</dd>', $blocks);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <date variable="issued" form="roman"/>
    </layout>
  </citation>
</style>
XML));
    },
    'maps bounded biblatex subtitle short title and title addon metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@book{title-review,
  author     = {Curator, Eli},
  title      = {Migration Manual},
  subtitle   = {Reviewer Packet Guide},
  titleaddon = {Draft source notes},
  shorttitle = {Reviewer Guide},
  date       = {2026},
  publisher  = {Review Press}
}

@incollection{chapter-title-review,
  author         = {Ng, Nia},
  title          = {Checklist},
  subtitle       = {Attachment Review},
  booktitle      = {Migration Handbook},
  booksubtitle   = {Import Desk Edition},
  booktitleaddon = {Internal packet supplement},
  date           = {2025},
  pages          = {7--12}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same('Migration Manual: Reviewer Packet Guide', $items[0]['title']);
        $t->same('Reviewer Guide', $items[0]['short-title']);
        $t->same('Draft source notes', $items[0]['title-addon']);
        $t->same('Checklist: Attachment Review', $items[1]['title']);
        $t->same('Migration Handbook: Import Desk Edition', $items[1]['container-title']);
        $t->same('Internal packet supplement', $items[1]['container-title-addon']);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $manual = $processor->item('title-review');
        $chapter = $processor->item('chapter-title-review');
        $t->same('Reviewer Guide', $manual['shortTitle'] ?? null);
        $t->same('Draft source notes', $manual['titleAddon'] ?? null);
        $t->same('Migration Handbook: Import Desk Edition', $chapter['containerTitle'] ?? null);
        $t->same('Internal packet supplement', $chapter['containerTitleAddon'] ?? null);
        $t->same('(Curator 2026; Ng 2025)', $processor->renderCitationCluster([
            $citation('title-review', '[@title-review]'),
            $citation('chapter-title-review', '[@chapter-title-review]'),
        ]));
        $t->same('Curator, Eli. Migration Manual: Reviewer Packet Guide. Draft source notes. Review Press, 2026.', $processor->renderBibliographyEntry('title-review'));
        $t->same('Ng, Nia. Checklist: Attachment Review. Migration Handbook: Import Desk Edition. Internal packet supplement. 2025. 7-12.', $processor->renderBibliographyEntry('chapter-title-review'));

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <choose>
          <if variable="short-title" match="any">
            <text variable="short-title"/>
          </if>
          <else>
            <text variable="title"/>
          </else>
        </choose>
        <text variable="title-addon"/>
        <text variable="container-title-addon"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="title-addon"/>
      <text variable="container-title"/>
      <text variable="container-title-addon"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('[Reviewer Guide | Draft source notes; Checklist: Attachment Review | Internal packet supplement]', $styled->renderCitationCluster([
            $citation('title-review', '[@title-review]'),
            $citation('chapter-title-review', '[@chapter-title-review]'),
        ]));
        $t->same('Migration Manual: Reviewer Packet Guide :: Draft source notes', $styled->renderBibliographyEntry('title-review'));
        $t->same('Checklist: Attachment Review :: Migration Handbook: Import Desk Edition :: Internal packet supplement', $styled->renderBibliographyEntry('chapter-title-review'));

        $document = (new MarkdownReader())->read('Title metadata @title-review and [@chapter-title-review] stays visible.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Title metadata Curator (2026) and (Ng 2025) stays visible.</p>', $blocks);
        $t->contains('<dt>Curator 2026</dt><dd>Curator, Eli. Migration Manual: Reviewer Packet Guide. Draft source notes. Review Press, 2026.</dd>', $blocks);
        $t->contains('<dt>Ng 2025</dt><dd>Ng, Nia. Checklist: Attachment Review. Migration Handbook: Import Desk Edition. Internal packet supplement. 2025. 7-12.</dd>', $blocks);
    },
    'maps bounded biblatex publication details identifiers and eprint metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@article{journal-detail,
  author        = {Doe, Jane},
  title         = {Detailed Field Notes},
  journaltitle  = {Journal of Imports},
  date          = {2026},
  volume        = {12},
  number        = {3},
  pages         = {20--30},
  doi           = {10.5555/detail},
  issn          = {1234-5678},
  eprint        = {2401.01234},
  archiveprefix = {arXiv},
  eprintclass   = {cs.DL}
}

@book{book-detail,
  author       = {Curator, Eli},
  title        = {Review Handbook},
  date         = {2025},
  edition      = {2nd},
  series       = {Source Review Series},
  seriesnumber = {7},
  publisher    = {Review Press},
  isbn         = {978-1-2345-6789-0}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(2, count($items));
        $t->same('12', $items[0]['volume']);
        $t->same('3', $items[0]['issue']);
        $t->same('1234-5678', $items[0]['ISSN']);
        $t->same('arXiv', $items[0]['archive']);
        $t->same('2401.01234', $items[0]['archive_location']);
        $t->same('cs.DL', $items[0]['archive-place']);
        $t->same('2nd', $items[1]['edition']);
        $t->same('Source Review Series', $items[1]['collection-title']);
        $t->same('7', $items[1]['collection-number']);
        $t->same('978-1-2345-6789-0', $items[1]['ISBN']);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $journal = $processor->item('journal-detail');
        $book = $processor->item('book-detail');
        $t->same('12', $journal['volume'] ?? null);
        $t->same('3', $journal['issue'] ?? null);
        $t->same('1234-5678', $journal['issn'] ?? null);
        $t->same('arXiv', $journal['archive'] ?? null);
        $t->same('2401.01234', $journal['archiveLocation'] ?? null);
        $t->same('cs.DL', $journal['archivePlace'] ?? null);
        $t->same('2nd', $book['edition'] ?? null);
        $t->same('Source Review Series', $book['collectionTitle'] ?? null);
        $t->same('7', $book['collectionNumber'] ?? null);
        $t->same('978-1-2345-6789-0', $book['isbn'] ?? null);
        $t->same('(Doe 2026; Curator 2025)', $processor->renderCitationCluster([
            $citation('journal-detail', '[@journal-detail]'),
            $citation('book-detail', '[@book-detail]'),
        ]));
        $t->same('Doe, Jane. Detailed Field Notes. Journal of Imports. Vol. 12, no. 3. 2026. 20-30. DOI 10.5555/detail. ISSN 1234-5678. Archive: arXiv cs.DL 2401.01234.', $processor->renderBibliographyEntry('journal-detail'));
        $t->same('Curator, Eli. Review Handbook. 2nd ed. Source Review Series, no. 7. Review Press, 2025. ISBN 978-1-2345-6789-0.', $processor->renderBibliographyEntry('book-detail'));

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author editor"/>
        <date variable="issued"><date-part name="year"/></date>
        <group delimiter=" ">
          <label variable="volume" form="short"/>
          <text variable="volume"/>
        </group>
        <group delimiter=" ">
          <label variable="issue" form="short"/>
          <text variable="issue"/>
        </group>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <group delimiter=" ">
        <label variable="edition" form="short"/>
        <text variable="edition"/>
      </group>
      <text variable="collection-title"/>
      <group delimiter=" ">
        <label variable="collection-number" form="short"/>
        <text variable="collection-number"/>
      </group>
      <text variable="ISBN"/>
      <text variable="ISSN"/>
      <text variable="archive"/>
      <text variable="archive-place"/>
      <text variable="archive_location"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('(Doe 2026 vol. 12 no. 3; Curator 2025)', $styled->renderCitationCluster([
            $citation('journal-detail', '[@journal-detail]'),
            $citation('book-detail', '[@book-detail]'),
        ]));
        $t->same('Detailed Field Notes :: 1234-5678 :: arXiv :: cs.DL :: 2401.01234', $styled->renderBibliographyEntry('journal-detail'));
        $t->same('Review Handbook :: ed. 2nd :: Source Review Series :: no. 7 :: 978-1-2345-6789-0', $styled->renderBibliographyEntry('book-detail'));

        $document = (new MarkdownReader())->read('Detailed sources @journal-detail and [@book-detail] keep identifiers for review.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Detailed sources Doe (2026) and (Curator 2025) keep identifiers for review.</p>', $blocks);
        $t->contains('<dt>Doe 2026</dt><dd>Doe, Jane. Detailed Field Notes. Journal of Imports. Vol. 12, no. 3. 2026. 20-30. DOI 10.5555/detail. ISSN 1234-5678. Archive: arXiv cs.DL 2401.01234.</dd>', $blocks);
        $t->contains('<dt>Curator 2025</dt><dd>Curator, Eli. Review Handbook. 2nd ed. Source Review Series, no. 7. Review Press, 2025. ISBN 978-1-2345-6789-0.</dd>', $blocks);
    },
    'maps bounded biblatex publisher and location literal lists into csl metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@book{distributed-review,
  author        = {Curator, Eli},
  title         = {Distributed Source Review},
  date          = {2026},
  publisher     = {{Review Press} and {Archive Desk}},
  location      = {{New York} and {London}},
  origpublisher = {{Archivo Press} and {Migration Desk}},
  origlocation  = {{Madrid} and {Barcelona}},
  url           = {https://example.test/distributed-review}
}

@report{institutional-packet,
  author      = {Ng, Nia},
  title       = {Institutional Review Packet},
  date        = {2025},
  institution = {{Migration Board} and {Source Lab}},
  address     = {{Remote} and {Portland}}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(2, count($items));
        $t->same('Review Press; Archive Desk', $items[0]['publisher']);
        $t->same(['Review Press', 'Archive Desk'], $items[0]['publisher-list']);
        $t->same('New York; London', $items[0]['publisher-place']);
        $t->same(['New York', 'London'], $items[0]['publisher-place-list']);
        $t->same(['Archivo Press', 'Migration Desk'], $items[0]['original-publisher-list']);
        $t->same(['Madrid', 'Barcelona'], $items[0]['original-publisher-place-list']);
        $t->same('Migration Board; Source Lab', $items[1]['publisher']);
        $t->same(['Remote', 'Portland'], $items[1]['publisher-place-list']);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $review = $processor->item('distributed-review');
        $packet = $processor->item('institutional-packet');
        $t->same(['Review Press', 'Archive Desk'], $review['publisherList'] ?? null);
        $t->same(['New York', 'London'], $review['publisherPlaceList'] ?? null);
        $t->same(['Archivo Press', 'Migration Desk'], $review['originalPublisherList'] ?? null);
        $t->same(['Madrid', 'Barcelona'], $review['originalPublisherPlaceList'] ?? null);
        $t->same(['Migration Board', 'Source Lab'], $packet['publisherList'] ?? null);
        $t->same(['Remote', 'Portland'], $packet['publisherPlaceList'] ?? null);
        $t->same('(Curator 2026; Ng 2025)', $processor->renderCitationCluster([
            $citation('distributed-review', '[@distributed-review]'),
            $citation('institutional-packet', '[@institutional-packet]'),
        ]));
        $t->same(
            'Curator, Eli. Distributed Source Review. Review Press; Archive Desk, 2026. Publisher places: New York; London. Original publisher: Archivo Press; Migration Desk, Madrid; Barcelona. https://example.test/distributed-review.',
            $processor->renderBibliographyEntry('distributed-review')
        );
        $t->same('Ng, Nia. Institutional Review Packet. Migration Board; Source Lab, 2025. Publisher places: Remote; Portland.', $processor->renderBibliographyEntry('institutional-packet'));

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="publisher-list"/>
        <text variable="publisher-place-list"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="publisher-list"/>
      <text variable="publisher-place-list"/>
      <text variable="original-publisher-list"/>
      <text variable="original-publisher-place-list"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('[Curator | Review Press; Archive Desk | New York; London; Ng | Migration Board; Source Lab | Remote; Portland]', $styled->renderCitationCluster([
            $citation('distributed-review', '[@distributed-review]'),
            $citation('institutional-packet', '[@institutional-packet]'),
        ]));
        $t->same('Distributed Source Review :: Review Press; Archive Desk :: New York; London :: Archivo Press; Migration Desk :: Madrid; Barcelona', $styled->renderBibliographyEntry('distributed-review'));
        $t->same('Institutional Review Packet :: Migration Board; Source Lab :: Remote; Portland', $styled->renderBibliographyEntry('institutional-packet'));

        $manual = CitationCslProcessor::fromItems([[
            'id' => 'manual-publisher-list',
            'title' => 'Manual Publisher List',
            'publisher' => 'Primary Press',
            'publisher-list' => ['Primary Press', 'Secondary Desk'],
            'publisher-place-list' => ['Remote', 'Lisbon'],
        ]])->item('manual-publisher-list');
        $t->same(['Primary Press', 'Secondary Desk'], $manual['publisherList'] ?? null);
        $t->same(['Remote', 'Lisbon'], $manual['publisherPlaceList'] ?? null);

        $document = (new MarkdownReader())->read('Publisher list source @distributed-review and institutional packet [@institutional-packet] keep multi-place metadata.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Publisher list source Curator (2026) and institutional packet (Ng 2025) keep multi-place metadata.</p>', $blocks);
        $t->contains('<dt>Curator 2026</dt><dd>Curator, Eli. Distributed Source Review. Review Press; Archive Desk, 2026. Publisher places: New York; London. Original publisher: Archivo Press; Migration Desk, Madrid; Barcelona. https://example.test/distributed-review.</dd>', $blocks);
        $t->contains('<dt>Ng 2025</dt><dd>Ng, Nia. Institutional Review Packet. Migration Board; Source Lab, 2025. Publisher places: Remote; Portland.</dd>', $blocks);
    },
    'maps bounded biblatex journal abbreviations into csl review metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@article{short-journal-detail,
  author       = {Doe, Jane},
  title        = {Abbreviated Field Notes},
  journaltitle = {Journal of Imported Sources},
  shortjournal = {J. Import. Sources},
  date         = {2026},
  pages        = {12--18},
  issn         = {2468-1357},
  url          = {https://example.test/short-journal}
}

@article{short-journal-title-detail,
  author            = {Ng, Nia},
  title             = {Alternate Abbreviation Packet},
  journaltitle      = {Migration Review Quarterly},
  shortjournaltitle = {Migr. Rev. Q.},
  date              = {2025}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(2, count($items));
        $t->same('J. Import. Sources', $items[0]['container-title-short'] ?? null);
        $t->same('J. Import. Sources', $items[0]['journalAbbreviation'] ?? null);
        $t->same('Migr. Rev. Q.', $items[1]['container-title-short'] ?? null);
        $t->same('Journal of Imported Sources', $items[0]['container-title']);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $journal = $processor->item('short-journal-detail');
        $alternate = $processor->item('short-journal-title-detail');
        $t->same('J. Import. Sources', $journal['containerTitleShort'] ?? null);
        $t->same('J. Import. Sources', $journal['journalAbbreviation'] ?? null);
        $t->same('Migr. Rev. Q.', $alternate['containerTitleShort'] ?? null);
        $t->same('(Doe 2026; Ng 2025)', $processor->renderCitationCluster([
            $citation('short-journal-detail', '[@short-journal-detail]'),
            $citation('short-journal-title-detail', '[@short-journal-title-detail]'),
        ]));
        $t->same(
            'Doe, Jane. Abbreviated Field Notes. Journal of Imported Sources. Journal abbreviation: J. Import. Sources. 2026. 12-18. https://example.test/short-journal. ISSN 2468-1357.',
            $processor->renderBibliographyEntry('short-journal-detail')
        );

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="container-title"/>
        <text variable="container-title-short"/>
        <text variable="journalAbbreviation"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="container-title-short"/>
      <text variable="journal-abbreviation"/>
      <text variable="ISSN"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('[Doe | Journal of Imported Sources | J. Import. Sources | J. Import. Sources; Ng | Migration Review Quarterly | Migr. Rev. Q. | Migr. Rev. Q.]', $styled->renderCitationCluster([
            $citation('short-journal-detail', '[@short-journal-detail]'),
            $citation('short-journal-title-detail', '[@short-journal-title-detail]'),
        ]));
        $t->same('Abbreviated Field Notes :: J. Import. Sources :: J. Import. Sources :: 2468-1357', $styled->renderBibliographyEntry('short-journal-detail'));

        $manual = CitationCslProcessor::fromItems([[
            'id' => 'manual-journal-abbrev',
            'title' => 'Manual Journal Abbreviation',
            'container-title-short' => 'Manual J.',
        ]])->item('manual-journal-abbrev');
        $t->same('Manual J.', $manual['containerTitleShort'] ?? null);
        $t->same('Manual J.', $manual['journalAbbreviation'] ?? null);

        $document = (new MarkdownReader())->read('Short journal source @short-journal-detail keeps abbreviation metadata for review.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Short journal source Doe (2026) keeps abbreviation metadata for review.</p>', $blocks);
        $t->contains('<dt>Doe 2026</dt><dd>Doe, Jane. Abbreviated Field Notes. Journal of Imported Sources. Journal abbreviation: J. Import. Sources. 2026. 12-18. https://example.test/short-journal. ISSN 2468-1357.</dd>', $blocks);
    },
    'maps bounded biblatex page first metadata for page ranges' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@article{range-detail,
  author       = {Doe, Jane},
  title        = {Paged Field Notes},
  journaltitle = {Journal of Imports},
  date         = {2026},
  pages        = {A12--A18}
}

@incollection{single-page-detail,
  author    = {Ng, Nia},
  title     = {Single Page Checklist},
  booktitle = {Migration Handbook},
  date      = {2025},
  page      = {77}
}

@inproceedings{frontmatter-detail,
  author    = {Curator, Eli},
  title     = {Front Matter Review},
  booktitle = {Proceedings},
  date      = {2024},
  pages     = {ii--iv}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same('A12', $items[0]['page-first'] ?? null);
        $t->same('77', $items[1]['page-first'] ?? null);
        $t->same('ii', $items[2]['page-first'] ?? null);
        $t->same('A12-A18', $items[0]['page']);
        $t->same('ii-iv', $items[2]['page']);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $range = $processor->item('range-detail');
        $single = $processor->item('single-page-detail');
        $frontmatter = $processor->item('frontmatter-detail');
        $t->same('A12', $range['pageFirst'] ?? null);
        $t->same('77', $single['pageFirst'] ?? null);
        $t->same('ii', $frontmatter['pageFirst'] ?? null);
        $t->same('(Doe 2026; Ng 2025; Curator 2024)', $processor->renderCitationCluster([
            $citation('range-detail', '[@range-detail]'),
            $citation('single-page-detail', '[@single-page-detail]'),
            $citation('frontmatter-detail', '[@frontmatter-detail]'),
        ]));

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <label variable="page-first" form="short"/>
        <text variable="page-first"/>
        <text variable="page"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <number variable="page-first"/>
      <text variable="page"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('[Doe p. A12 A12-A18; Ng p. 77 77; Curator p. ii ii-iv]', $styled->renderCitationCluster([
            $citation('range-detail', '[@range-detail]'),
            $citation('single-page-detail', '[@single-page-detail]'),
            $citation('frontmatter-detail', '[@frontmatter-detail]'),
        ]));
        $t->same('Paged Field Notes :: A12 :: A12-A18', $styled->renderBibliographyEntry('range-detail'));
        $t->same('Single Page Checklist :: 77 :: 77', $styled->renderBibliographyEntry('single-page-detail'));

        $manual = CitationCslProcessor::fromItems([[
            'id' => 'manual-first-page',
            'title' => 'Manual First Page',
            'page' => '22-29',
        ], [
            'id' => 'explicit-first-page',
            'title' => 'Explicit First Page',
            'page' => 'C1-C3',
            'page-first' => 'C2',
        ]]);
        $t->same('22', $manual->item('manual-first-page')['pageFirst'] ?? null);
        $t->same('C2', $manual->item('explicit-first-page')['pageFirst'] ?? null);

        $document = (new MarkdownReader())->read('Page range source @range-detail and single page [@single-page-detail] keep first-page metadata.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Page range source Doe (2026) and single page (Ng 2025) keep first-page metadata.</p>', $blocks);
        $t->contains('<dt>Doe 2026</dt><dd>Doe, Jane. Paged Field Notes. Journal of Imports. 2026. A12-A18.</dd>', $blocks);
        $t->contains('<dt>Ng 2025</dt><dd>Ng, Nia. Single Page Checklist. Migration Handbook. 2025. 77.</dd>', $blocks);
    },
    'maps bounded biblatex main title and multi volume metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@inbook{volume-chapter,
  author         = {Smith, Ada},
  title          = {Review Checklist},
  booktitle      = {Import Handbook},
  booksubtitle   = {Volume Desk Edition},
  maintitle      = {Migration Source Dossier},
  mainsubtitle   = {Multi-volume Reviewer Set},
  maintitleaddon = {Internal archive packet},
  date           = {2026},
  volume         = {2},
  volumes        = {4},
  part           = {1},
  chapter        = {7},
  pagetotal      = {320},
  pages          = {33--39}
}

@mvbook{dossier-set,
  editor    = {Curator, Eli},
  title     = {Migration Source Dossier},
  subtitle  = {Multi-volume Reviewer Set},
  volumes   = {4},
  publisher = {Review Press},
  date      = {2025}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(2, count($items));
        $t->same('volume-chapter', $items[0]['id']);
        $t->same('chapter', $items[0]['type']);
        $t->same('Review Checklist', $items[0]['title']);
        $t->same('Import Handbook: Volume Desk Edition', $items[0]['container-title']);
        $t->same('Migration Source Dossier: Multi-volume Reviewer Set', $items[0]['main-title']);
        $t->same('Internal archive packet', $items[0]['main-title-addon']);
        $t->same('2', $items[0]['volume']);
        $t->same('4', $items[0]['number-of-volumes']);
        $t->same('1', $items[0]['part']);
        $t->same('7', $items[0]['chapter-number']);
        $t->same('320', $items[0]['number-of-pages']);
        $t->same('4', $items[1]['number-of-volumes']);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $chapter = $processor->item('volume-chapter');
        $set = $processor->item('dossier-set');
        $t->same('Migration Source Dossier: Multi-volume Reviewer Set', $chapter['mainTitle'] ?? null);
        $t->same('Internal archive packet', $chapter['mainTitleAddon'] ?? null);
        $t->same('4', $chapter['numberOfVolumes'] ?? null);
        $t->same('1', $chapter['part'] ?? null);
        $t->same('7', $chapter['chapterNumber'] ?? null);
        $t->same('320', $chapter['numberOfPages'] ?? null);
        $t->same('4', $set['numberOfVolumes'] ?? null);
        $t->same('(Smith 2026; Curator 2025)', $processor->renderCitationCluster([
            $citation('volume-chapter', '[@volume-chapter]'),
            $citation('dossier-set', '[@dossier-set]'),
        ]));
        $t->same(
            'Smith, Ada. Review Checklist. Import Handbook: Volume Desk Edition. Main title: Migration Source Dossier: Multi-volume Reviewer Set. Main title addendum: Internal archive packet. Vol. 2 of 4. Part 1. Chap. 7. 320 pp. 2026. 33-39.',
            $processor->renderBibliographyEntry('volume-chapter')
        );
        $t->same('Curator, Eli. Migration Source Dossier: Multi-volume Reviewer Set. 4 vols. Review Press, 2025.', $processor->renderBibliographyEntry('dossier-set'));

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <choose>
          <if variable="main-title" match="any">
            <text variable="main-title"/>
          </if>
          <else>
            <text variable="title"/>
          </else>
        </choose>
        <text variable="container-title"/>
        <group delimiter=" ">
          <label variable="volume" form="short"/>
          <number variable="volume"/>
        </group>
        <group delimiter=" ">
          <label variable="number-of-volumes" form="short" plural="always"/>
          <number variable="number-of-volumes"/>
        </group>
        <group delimiter=" ">
          <label variable="chapter-number" form="short"/>
          <number variable="chapter-number"/>
        </group>
        <text variable="part" prefix="part "/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="main-title"/>
      <text variable="main-title-addon"/>
      <number variable="number-of-volumes"/>
      <number variable="chapter-number" form="ordinal"/>
      <text variable="part"/>
      <number variable="number-of-pages"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('[Migration Source Dossier: Multi-volume Reviewer Set | Import Handbook: Volume Desk Edition | vol. 2 | vols. 4 | chap. 7 | part 1; Migration Source Dossier: Multi-volume Reviewer Set | vols. 4]', $styled->renderCitationCluster([
            $citation('volume-chapter', '[@volume-chapter]'),
            $citation('dossier-set', '[@dossier-set]'),
        ]));
        $t->same('Review Checklist :: Migration Source Dossier: Multi-volume Reviewer Set :: Internal archive packet :: 4 :: 7th :: 1 :: 320', $styled->renderBibliographyEntry('volume-chapter'));
        $t->same('Migration Source Dossier: Multi-volume Reviewer Set :: 4', $styled->renderBibliographyEntry('dossier-set'));

        $document = (new MarkdownReader())->read('Multi-volume source @volume-chapter and dossier [@dossier-set] remain reviewable.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Multi-volume source Smith (2026) and dossier (Curator 2025) remain reviewable.</p>', $blocks);
        $t->contains('<dt>Smith 2026</dt><dd>Smith, Ada. Review Checklist. Import Handbook: Volume Desk Edition. Main title: Migration Source Dossier: Multi-volume Reviewer Set. Main title addendum: Internal archive packet. Vol. 2 of 4. Part 1. Chap. 7. 320 pp. 2026. 33-39.</dd>', $blocks);
        $t->contains('<dt>Curator 2025</dt><dd>Curator, Eli. Migration Source Dossier: Multi-volume Reviewer Set. 4 vols. Review Press, 2025.</dd>', $blocks);
    },
    'maps bounded biblatex note addendum and howpublished review metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@online{review-note-source,
  author       = {Ng, Nia},
  title        = {Review Packet Snapshot},
  date         = {2026-06-05},
  howpublished = {Archived web packet},
  note         = {Needs source-check before migration},
  addendum     = {Queue imported by handoff},
  url          = {https://example.test/review-packet}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(1, count($items));
        $t->same('review-note-source', $items[0]['id']);
        $t->same('Archived web packet', $items[0]['medium']);
        $t->same('Needs source-check before migration', $items[0]['note']);
        $t->same('Queue imported by handoff', $items[0]['addendum']);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $item = $processor->item('review-note-source');
        $t->same('Archived web packet', $item['medium'] ?? null);
        $t->same('Needs source-check before migration', $item['note'] ?? null);
        $t->same('Queue imported by handoff', $item['addendum'] ?? null);
        $t->same('(Ng 2026)', $processor->renderCitationCluster([$citation('review-note-source', '[@review-note-source]')]));
        $t->same(
            'Ng, Nia. Review Packet Snapshot. 2026. Medium: Archived web packet. Note: Needs source-check before migration. Addendum: Queue imported by handoff. https://example.test/review-packet.',
            $processor->renderBibliographyEntry('review-note-source')
        );

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="medium"/>
        <text variable="note"/>
        <text variable="addendum"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="medium"/>
      <text variable="note"/>
      <text variable="addendum"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('[Review Packet Snapshot | Archived web packet | Needs source-check before migration | Queue imported by handoff]', $styled->renderCitationCluster([$citation('review-note-source', '[@review-note-source]')]));
        $t->same('Review Packet Snapshot :: Archived web packet :: Needs source-check before migration :: Queue imported by handoff', $styled->renderBibliographyEntry('review-note-source'));

        $document = (new MarkdownReader())->read('Review note source @review-note-source keeps import audit notes attached.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Review note source Ng (2026) keeps import audit notes attached.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Ng, Nia. Review Packet Snapshot. 2026. Medium: Archived web packet. Note: Needs source-check before migration. Addendum: Queue imported by handoff. https://example.test/review-packet.</dd>', $blocks);

        $manual = CitationCslProcessor::fromItems([[
            'id' => 'manual-note',
            'title' => 'Manual Note Source',
            'medium' => 'Reviewer PDF',
            'note' => 'Manual note',
            'addendum' => 'Manual addendum',
        ]])->item('manual-note');
        $t->same('Reviewer PDF', $manual['medium'] ?? null);
        $t->same('Manual note', $manual['note'] ?? null);
        $t->same('Manual addendum', $manual['addendum'] ?? null);
    },
    'maps bounded biblatex entry subtype review metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@report{review-subtype,
  author       = {Ng, Nia},
  title        = {Source Audit Report},
  date         = {2026},
  type         = {white paper},
  entrysubtype = {migration source audit},
  institution  = {Migration Desk},
  url          = {https://example.test/subtype-report}
}

@online{snapshot-subtype,
  author       = {{Review Desk}},
  title        = {Import Queue Snapshot},
  date         = {2025},
  entrysubtype = {review snapshot},
  howpublished = {Archived source packet}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(2, count($items));
        $t->same('report', $items[0]['type']);
        $t->same('white paper', $items[0]['genre']);
        $t->same('migration source audit', $items[0]['entry-subtype'] ?? null);
        $t->same('entrysubtype', array_key_exists('entrysubtype', $items[0]['rawBibtex']['fields'] ?? []) ? 'entrysubtype' : null);
        $t->same('webpage', $items[1]['type']);
        $t->same('review snapshot', $items[1]['genre']);
        $t->same('review snapshot', $items[1]['entry-subtype'] ?? null);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $report = $processor->item('review-subtype');
        $snapshot = $processor->item('snapshot-subtype');
        $t->same('white paper', $report['genre'] ?? null);
        $t->same('migration source audit', $report['entrySubtype'] ?? null);
        $t->same('review snapshot', $snapshot['genre'] ?? null);
        $t->same('review snapshot', $snapshot['entrySubtype'] ?? null);
        $t->same('(Ng 2026; Review Desk 2025)', $processor->renderCitationCluster([
            $citation('review-subtype', '[@review-subtype]'),
            $citation('snapshot-subtype', '[@snapshot-subtype]'),
        ]));
        $t->same(
            'Ng, Nia. Source Audit Report. Migration Desk, 2026. Entry subtype: migration source audit. https://example.test/subtype-report.',
            $processor->renderBibliographyEntry('review-subtype')
        );
        $t->same(
            'Review Desk. Import Queue Snapshot. 2025. Medium: Archived source packet. Entry subtype: review snapshot.',
            $processor->renderBibliographyEntry('snapshot-subtype')
        );

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="genre"/>
        <text variable="entry-subtype"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="genre"/>
      <text variable="entry-subtype"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('[Ng | white paper | migration source audit; Review Desk | review snapshot | review snapshot]', $styled->renderCitationCluster([
            $citation('review-subtype', '[@review-subtype]'),
            $citation('snapshot-subtype', '[@snapshot-subtype]'),
        ]));
        $t->same('Source Audit Report :: white paper :: migration source audit', $styled->renderBibliographyEntry('review-subtype'));
        $t->same('Import Queue Snapshot :: review snapshot :: review snapshot', $styled->renderBibliographyEntry('snapshot-subtype'));

        $manual = CitationCslProcessor::fromItems([[
            'id' => 'manual-subtype',
            'title' => 'Manual Subtype Source',
            'entry-subtype' => 'manual review packet',
        ]])->item('manual-subtype');
        $t->same('manual review packet', $manual['entrySubtype'] ?? null);

        $document = (new MarkdownReader())->read('Subtype source @review-subtype and snapshot [@snapshot-subtype] preserve source-kind review metadata.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Subtype source Ng (2026) and snapshot (Review Desk 2025) preserve source-kind review metadata.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Ng, Nia. Source Audit Report. Migration Desk, 2026. Entry subtype: migration source audit. https://example.test/subtype-report.</dd>', $blocks);
        $t->contains('<dt>Review Desk 2025</dt><dd>Review Desk. Import Queue Snapshot. 2025. Medium: Archived source packet. Entry subtype: review snapshot.</dd>', $blocks);
    },
    'maps bounded biblatex editorial role name lists into csl metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@book{role-review,
  author       = {Smith, Ada},
  title        = {Annotated Migration Manual},
  date         = {2026},
  publisher    = {Review Press},
  origauthor   = {Garc{\'i}a, Gia},
  commentator  = {Roe, Pat and {{Migration Desk}}},
  annotator    = {Ng, Nia},
  introduction = {de la Cruz, Ana Maria},
  foreword     = {M{\"u}ller, Mia},
  afterword    = {Curator, Eli}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(1, count($items));
        $t->same('role-review', $items[0]['id']);
        $t->same([['family' => 'García', 'given' => 'Gia']], $items[0]['original-author']);
        $t->same([['family' => 'Roe', 'given' => 'Pat'], ['literal' => 'Migration Desk']], $items[0]['commentator']);
        $t->same([['family' => 'Ng', 'given' => 'Nia']], $items[0]['annotator']);
        $t->same([['family' => 'Cruz', 'given' => 'Ana Maria', 'non-dropping-particle' => 'de la']], $items[0]['introduction']);
        $t->same([['family' => 'Müller', 'given' => 'Mia']], $items[0]['foreword']);
        $t->same([['family' => 'Curator', 'given' => 'Eli']], $items[0]['afterword']);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $item = $processor->item('role-review');
        $t->same('García', $item['originalAuthors'][0]['family'] ?? null);
        $t->same('Roe', $item['commentators'][0]['family'] ?? null);
        $t->same('Migration Desk', $item['commentators'][1]['literal'] ?? null);
        $t->same('Ng', $item['annotators'][0]['family'] ?? null);
        $t->same('de la', $item['introductionAuthors'][0]['nonDroppingParticle'] ?? null);
        $t->same('Müller', $item['forewordAuthors'][0]['family'] ?? null);
        $t->same('Curator', $item['afterwordAuthors'][0]['family'] ?? null);
        $t->same('(Smith 2026)', $processor->renderCitationCluster([$citation('role-review', '[@role-review]')]));
        $t->same(
            'Smith, Ada. Annotated Migration Manual. Review Press, 2026. Commentary by Roe, Pat; Migration Desk. Annotated by Ng, Nia. Introduction by de la Cruz, Ana Maria. Foreword by Müller, Mia. Afterword by Curator, Eli. Original author: García, Gia.',
            $processor->renderBibliographyEntry('role-review')
        );

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text value="review roles"/>
        <names variable="commentator annotator"/>
        <names variable="introduction"/>
        <names variable="foreword"/>
        <names variable="afterword"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <names variable="commentator" prefix="commentary: "/>
      <names variable="annotator" prefix="annotation: "/>
      <names variable="original-author" prefix="original: "/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('[review roles | Roe and Migration Desk | de la Cruz | Müller | Curator]', $styled->renderCitationCluster([$citation('role-review', '[@role-review]')]));
        $t->same('Annotated Migration Manual :: commentary: Roe, Pat; Migration Desk :: annotation: Ng, Nia :: original: García, Gia', $styled->renderBibliographyEntry('role-review'));

        $noRoleStyle = CitationCslProcessor::fromItems([[
            'id' => 'plain-role',
            'title' => 'Plain Role Source',
            'issued' => ['date-parts' => [[2026]]],
        ]])->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" ">
        <text value="roles"/>
        <names variable="commentator annotator"/>
      </group>
    </layout>
  </citation>
  <bibliography><layout><text variable="title"/></layout></bibliography>
</style>
XML);
        $t->same('[Plain Role Source 2026]', $noRoleStyle->renderCitationCluster([$citation('plain-role', '[@plain-role]')]));

        $document = (new MarkdownReader())->read('Role-rich source @role-review keeps editorial review names attached.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Role-rich source Smith (2026) keeps editorial review names attached.</p>', $blocks);
        $t->contains('<dt>Smith 2026</dt><dd>Smith, Ada. Annotated Migration Manual. Review Press, 2026. Commentary by Roe, Pat; Migration Desk. Annotated by Ng, Nia. Introduction by de la Cruz, Ana Maria. Foreword by Müller, Mia. Afterword by Curator, Eli. Original author: García, Gia.</dd>', $blocks);

        $manual = CitationCslProcessor::fromItems([[
            'id' => 'manual-role',
            'title' => 'Manual Role Source',
            'commentator' => [
                ['family' => 'Roe', 'given' => 'Pat'],
            ],
        ]])->item('manual-role');
        $t->same('Roe', $manual['commentators'][0]['family'] ?? null);
    },
    'maps bounded biblatex secondary editor roles into csl metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@collection{secondary-editor-review,
  editor      = {Smith, Ada},
  editora     = {Roe, Pat and {{Migration Desk}}},
  editoratype = {compiler},
  editorb     = {Ng, Nia},
  editorbtype = {editorialdirector},
  editorc     = {de la Cruz, Ana Maria},
  editorctype = {reviewer},
  title       = {Migration Source Dossier},
  date        = {2026},
  publisher   = {Review Press}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(1, count($items));
        $t->same('secondary-editor-review', $items[0]['id']);
        $t->same([['family' => 'Roe', 'given' => 'Pat'], ['literal' => 'Migration Desk']], $items[0]['compiler']);
        $t->same([['family' => 'Ng', 'given' => 'Nia']], $items[0]['editorial-director']);
        $t->same('editora', $items[0]['editorial-roles'][0]['field'] ?? null);
        $t->same('compiler', $items[0]['editorial-roles'][0]['type'] ?? null);
        $t->same('Compiler', $items[0]['editorial-roles'][0]['label'] ?? null);
        $t->same([['family' => 'Cruz', 'given' => 'Ana Maria', 'non-dropping-particle' => 'de la']], $items[0]['editorial-roles'][2]['names'] ?? null);
        $t->same('Reviewer', $items[0]['editorial-roles'][2]['label'] ?? null);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $item = $processor->item('secondary-editor-review');
        $t->same('Roe', $item['compilers'][0]['family'] ?? null);
        $t->same('Migration Desk', $item['compilers'][1]['literal'] ?? null);
        $t->same('Ng', $item['editorialDirectors'][0]['family'] ?? null);
        $t->same('reviewer', $item['editorialRoles'][2]['type'] ?? null);
        $t->same('de la', $item['editorialRoles'][2]['names'][0]['nonDroppingParticle'] ?? null);
        $t->same('(Smith 2026)', $processor->renderCitationCluster([$citation('secondary-editor-review', '[@secondary-editor-review]')]));
        $t->same(
            'Smith, Ada. Migration Source Dossier. Review Press, 2026. Compiled by Roe, Pat; Migration Desk. Editorial direction by Ng, Nia. Reviewer: de la Cruz, Ana Maria.',
            $processor->renderBibliographyEntry('secondary-editor-review')
        );

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text value="secondary"/>
        <names variable="compiler"/>
        <names variable="editorial-director"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="editorial-role-summary"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('[secondary | Roe and Migration Desk | Ng]', $styled->renderCitationCluster([$citation('secondary-editor-review', '[@secondary-editor-review]')]));
        $t->same('Migration Source Dossier :: Compiled by Roe, Pat; Migration Desk. Editorial direction by Ng, Nia. Reviewer: de la Cruz, Ana Maria.', $styled->renderBibliographyEntry('secondary-editor-review'));

        $manual = CitationCslProcessor::fromItems([[
            'id' => 'manual-secondary',
            'title' => 'Manual Secondary Editor Source',
            'compiler' => [
                ['family' => 'Roe', 'given' => 'Pat'],
            ],
        ]])->item('manual-secondary');
        $t->same('Roe', $manual['compilers'][0]['family'] ?? null);
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([[
            'id' => 'bad-secondary-role',
            'title' => 'Bad Secondary Role',
            'editorial-roles' => 'compiler',
        ]]));
    },
    'maps bounded biblatex name annotations and name addendum metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@book{name-annotation-review,
  author     = {Smith, Ada and Ng, Nia},
  author+an  = {1=primary source author; 2:family=family name verified},
  editor     = {Curator, Eli},
  editor+an  = {1=review editor},
  title      = {Annotated Source Names},
  date       = {2026},
  publisher  = {Review Press},
  nameaddon  = {Imported source names verified by review desk}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(1, count($items));
        $t->same('name-annotation-review', $items[0]['id']);
        $t->same('Imported source names verified by review desk', $items[0]['name-addon']);
        $t->same([['part' => 'name', 'value' => 'primary source author']], $items[0]['author'][0]['annotations'] ?? null);
        $t->same([['part' => 'family', 'value' => 'family name verified']], $items[0]['author'][1]['annotations'] ?? null);
        $t->same([['part' => 'name', 'value' => 'review editor']], $items[0]['editor'][0]['annotations'] ?? null);
        $t->same('1=primary source author; 2:family=family name verified', $items[0]['rawBibtex']['fields']['author+an'] ?? null);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $item = $processor->item('name-annotation-review');
        $t->same('Imported source names verified by review desk', $item['nameAddon'] ?? null);
        $t->same('primary source author', $item['authors'][0]['annotations'][0]['value'] ?? null);
        $t->same('family', $item['authors'][1]['annotations'][0]['part'] ?? null);
        $t->same('review editor', $item['editors'][0]['annotations'][0]['value'] ?? null);
        $t->same('(Smith and Ng 2026)', $processor->renderCitationCluster([$citation('name-annotation-review', '[@name-annotation-review]')]));
        $t->same(
            'Smith, Ada; Ng, Nia. Annotated Source Names. Review Press, 2026. Name addendum: Imported source names verified by review desk. Name annotations: Author 1: primary source author; Author 2 family: family name verified; Editor 1: review editor.',
            $processor->renderBibliographyEntry('name-annotation-review')
        );

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="name-addon"/>
        <text variable="name-annotation-summary"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="name-addon"/>
      <text variable="name-annotation-summary"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('[Smith and Ng | Imported source names verified by review desk | Author 1: primary source author; Author 2 family: family name verified; Editor 1: review editor]', $styled->renderCitationCluster([$citation('name-annotation-review', '[@name-annotation-review]')]));
        $t->same('Annotated Source Names :: Imported source names verified by review desk :: Author 1: primary source author; Author 2 family: family name verified; Editor 1: review editor', $styled->renderBibliographyEntry('name-annotation-review'));

        $document = (new MarkdownReader())->read('Annotated name source @name-annotation-review keeps review metadata.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Annotated name source Smith and Ng (2026) keeps review metadata.</p>', $blocks);
        $t->contains('<dt>Smith and Ng 2026</dt><dd>Smith, Ada; Ng, Nia. Annotated Source Names. Review Press, 2026. Name addendum: Imported source names verified by review desk. Name annotations: Author 1: primary source author; Author 2 family: family name verified; Editor 1: review editor.</dd>', $blocks);

        $manual = CitationCslProcessor::fromItems([[
            'id' => 'manual-name-annotation',
            'title' => 'Manual Name Annotation',
            'name-addon' => 'Manual reviewer note',
            'author' => [
                [
                    'family' => 'Smith',
                    'given' => 'Ada',
                    'annotations' => [
                        ['part' => 'name', 'value' => 'manual annotation'],
                    ],
                ],
            ],
        ]])->item('manual-name-annotation');
        $t->same('Manual reviewer note', $manual['nameAddon'] ?? null);
        $t->same('manual annotation', $manual['authors'][0]['annotations'][0]['value'] ?? null);
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([[
            'id' => 'bad-name-annotation',
            'title' => 'Bad Name Annotation',
            'author' => [
                ['family' => 'Smith', 'annotations' => 'primary'],
            ],
        ]]));
    },
    'maps bounded biblatex shorthand labels and short creator lists' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@book{shorthand-review,
  author         = {Smith, Ada and Curator, Eli},
  shortauthor    = {{WIR Desk}},
  title          = {WordPress Import Review Manual},
  date           = {2026},
  publisher      = {Review Press},
  shorthand      = {WIR},
  shorthandintro = {cited as WordPress Import Review},
  label          = {Manual Label}
}

@collection{short-editor-review,
  editor      = {Roe, Pat and Ng, Nia},
  shorteditor = {{Review Editors}},
  title       = {Editor Label Source},
  date        = {2025},
  publisher   = {Review Press}
}

@online{explicit-label-review,
  title = {Legacy Source Packet},
  label = {LSP},
  date  = {2024},
  url   = {https://example.test/legacy-source-packet}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(3, count($items));
        $t->same('WIR', $items[0]['citation-label']);
        $t->same('WIR', $items[0]['shorthand']);
        $t->same('cited as WordPress Import Review', $items[0]['shorthand-intro']);
        $t->same([['literal' => 'WIR Desk']], $items[0]['short-author']);
        $t->same('Manual Label', $items[0]['label']);
        $t->same([['literal' => 'Review Editors']], $items[1]['short-editor']);
        $t->same('LSP', $items[2]['citation-label']);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $shorthand = $processor->item('shorthand-review');
        $shortEditor = $processor->item('short-editor-review');
        $explicitLabel = $processor->item('explicit-label-review');
        $t->same('WIR', $shorthand['citationLabel'] ?? null);
        $t->same('cited as WordPress Import Review', $shorthand['shorthandIntro'] ?? null);
        $t->same('WIR Desk', $shorthand['shortAuthors'][0]['literal'] ?? null);
        $t->same('Review Editors', $shortEditor['shortEditors'][0]['literal'] ?? null);
        $t->same('LSP', $explicitLabel['citationLabel'] ?? null);
        $t->same('(WIR; Review Editors 2025; LSP)', $processor->renderCitationCluster([
            $citation('shorthand-review', '[@shorthand-review]'),
            $citation('short-editor-review', '[@short-editor-review]'),
            $citation('explicit-label-review', '[@explicit-label-review]'),
        ]));
        $t->same('(WIR, p. 9)', $processor->renderCitationCluster([
            $citation('shorthand-review', '[@shorthand-review, p. 9]', 'normal', ['suffix' => 'p. 9']),
        ]));
        $t->same('Smith, Ada; Curator, Eli. WordPress Import Review Manual. Review Press, 2026.', $processor->renderBibliographyEntry('shorthand-review'));

        $bibliography = $processor->bibliographyDefinitionList(['shorthand-review', 'short-editor-review', 'explicit-label-review']);
        $t->same('WIR', $bibliography->children[0]->children[0]->attr('text'));
        $t->same('Review Editors 2025', $bibliography->children[1]->children[0]->attr('text'));
        $t->same('LSP', $bibliography->children[2]->children[0]->attr('text'));

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="citation-label"/>
        <text variable="shorthand-intro"/>
        <names variable="short-author short-editor"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="citation-label"/>
      <text variable="shorthand"/>
      <text variable="shorthand-intro"/>
      <names variable="short-author short-editor"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('[WIR | cited as WordPress Import Review | WIR Desk; Review Editors; LSP]', $styled->renderCitationCluster([
            $citation('shorthand-review', '[@shorthand-review]'),
            $citation('short-editor-review', '[@short-editor-review]'),
            $citation('explicit-label-review', '[@explicit-label-review]'),
        ]));
        $t->same('WordPress Import Review Manual :: WIR :: WIR :: cited as WordPress Import Review :: WIR Desk', $styled->renderBibliographyEntry('shorthand-review'));
        $t->same('Editor Label Source :: Review Editors', $styled->renderBibliographyEntry('short-editor-review'));

        $document = (new MarkdownReader())->read('Shorthand source @shorthand-review and editor source [@short-editor-review] keep compact review labels.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Shorthand source WIR and editor source (Review Editors 2025) keep compact review labels.</p>', $blocks);
        $t->contains('<dt>WIR</dt><dd>Smith, Ada; Curator, Eli. WordPress Import Review Manual. Review Press, 2026.</dd>', $blocks);
        $t->contains('<dt>Review Editors 2025</dt><dd>Roe, Pat; Ng, Nia. Editor Label Source. Review Press, 2025.</dd>', $blocks);

        $manual = CitationCslProcessor::fromItems([[
            'id' => 'manual-label',
            'title' => 'Manual Label Source',
            'citation-label' => 'MLS',
            'shorthand-intro' => 'manual intro',
            'short-author' => [
                ['literal' => 'Manual Desk'],
            ],
        ]]);
        $manualItem = $manual->item('manual-label');
        $t->same('MLS', $manualItem['citationLabel'] ?? null);
        $t->same('manual intro', $manualItem['shorthandIntro'] ?? null);
        $t->same('Manual Desk', $manualItem['shortAuthors'][0]['literal'] ?? null);
        $t->same('(MLS)', $manual->renderCitationCluster([$citation('manual-label', '[@manual-label]')]));
    },
    'maps bounded biblatex software dataset version and pubstate metadata' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@software{import-tool,
  author   = {{Migration Desk}},
  title    = {Block Import Verifier},
  date     = {2026-06-05},
  version  = {2.1.0-beta},
  pubstate = {preprint},
  url      = {https://example.test/import-verifier}
}

@dataset{source-dataset,
  author   = {Ng, Nia},
  title    = {Source Packet Dataset},
  date     = {2025},
  version  = {2025.4},
  pubstate = {revised},
  doi      = {10.5555/dataset}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(2, count($items));
        $t->same('software', $items[0]['type']);
        $t->same('2.1.0-beta', $items[0]['version']);
        $t->same('preprint', $items[0]['status']);
        $t->same('preprint', $items[0]['rawBibtex']['fields']['pubstate'] ?? null);
        $t->same('dataset', $items[1]['type']);
        $t->same('2025.4', $items[1]['version']);
        $t->same('revised', $items[1]['status']);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $software = $processor->item('import-tool');
        $dataset = $processor->item('source-dataset');
        $t->same('2.1.0-beta', $software['version'] ?? null);
        $t->same('preprint', $software['status'] ?? null);
        $t->same('2025.4', $dataset['version'] ?? null);
        $t->same('revised', $dataset['status'] ?? null);
        $t->same('(Migration Desk 2026; Ng 2025)', $processor->renderCitationCluster([
            $citation('import-tool', '[@import-tool]'),
            $citation('source-dataset', '[@source-dataset]'),
        ]));
        $t->same('Migration Desk. Block Import Verifier. 2026. Version: 2.1.0-beta. Status: preprint. https://example.test/import-verifier.', $processor->renderBibliographyEntry('import-tool'));
        $t->same('Ng, Nia. Source Packet Dataset. 2025. Version: 2025.4. Status: revised. DOI 10.5555/dataset.', $processor->renderBibliographyEntry('source-dataset'));

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" ">
        <text variable="title"/>
        <text variable="version" prefix="v"/>
        <text variable="status" prefix="(" suffix=")"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="version" prefix="version "/>
      <text variable="status" prefix="state "/>
      <text variable="DOI"/>
      <text variable="URL"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('[Block Import Verifier v2.1.0-beta (preprint); Source Packet Dataset v2025.4 (revised)]', $styled->renderCitationCluster([
            $citation('import-tool', '[@import-tool]'),
            $citation('source-dataset', '[@source-dataset]'),
        ]));
        $t->same('Block Import Verifier :: version 2.1.0-beta :: state preprint :: https://example.test/import-verifier', $styled->renderBibliographyEntry('import-tool'));
        $t->same('Source Packet Dataset :: version 2025.4 :: state revised :: 10.5555/dataset', $styled->renderBibliographyEntry('source-dataset'));

        $document = (new MarkdownReader())->read('Software @import-tool and dataset [@source-dataset] preserve release state.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Software Migration Desk (2026) and dataset (Ng 2025) preserve release state.</p>', $blocks);
        $t->contains('<dt>Migration Desk 2026</dt><dd>Migration Desk. Block Import Verifier. 2026. Version: 2.1.0-beta. Status: preprint. https://example.test/import-verifier.</dd>', $blocks);
        $t->contains('<dt>Ng 2025</dt><dd>Ng, Nia. Source Packet Dataset. 2025. Version: 2025.4. Status: revised. DOI 10.5555/dataset.</dd>', $blocks);

        $manual = CitationCslProcessor::fromItems([[
            'id' => 'manual-version',
            'title' => 'Manual Version Source',
            'version' => '3.0',
            'status' => 'forthcoming',
        ]])->item('manual-version');
        $t->same('3.0', $manual['version'] ?? null);
        $t->same('forthcoming', $manual['status'] ?? null);
    },
    'maps bounded biblatex event metadata into csl handoff' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@proceedings{event-proceedings,
  editor          = {Curator, Eli},
  title           = {WordPress Import Conference Proceedings},
  eventtitle      = {WordCamp Migration Summit},
  eventtitleaddon = {Reviewer track},
  eventtype       = {conference},
  venue           = {Portland},
  eventdate       = {2026-06-04/2026-06-05},
  date            = {2026},
  publisher       = {Migration Desk}
}

@inproceedings{event-paper,
  author    = {Ng, Nia},
  title     = {Source Packet Event Review},
  pages     = {44--48},
  crossref  = {event-proceedings}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(2, count($items));
        $t->same('WordCamp Migration Summit', $items[0]['event']);
        $t->same('Reviewer track', $items[0]['event-title-addon']);
        $t->same('conference', $items[0]['event-type']);
        $t->same('Portland', $items[0]['event-place']);
        $t->same(['date-parts' => [[2026, 6, 4], [2026, 6, 5]]], $items[0]['event-date']);
        $t->same('WordCamp Migration Summit', $items[1]['event']);
        $t->same('Reviewer track', $items[1]['event-title-addon']);
        $t->same('conference', $items[1]['event-type']);
        $t->same('Portland', $items[1]['event-place']);
        $t->same(['date-parts' => [[2026, 6, 4], [2026, 6, 5]]], $items[1]['event-date']);
        $t->same('WordPress Import Conference Proceedings', $items[1]['container-title']);
        $t->same('Migration Desk', $items[1]['publisher']);
        $t->same('Portland', $items[1]['publisher-place']);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $proceedings = $processor->item('event-proceedings');
        $paper = $processor->item('event-paper');
        $t->same('WordCamp Migration Summit', $proceedings['eventTitle'] ?? null);
        $t->same('Reviewer track', $proceedings['eventTitleAddon'] ?? null);
        $t->same('conference', $proceedings['eventType'] ?? null);
        $t->same('Portland', $proceedings['eventPlace'] ?? null);
        $t->same([[2026, 6, 4], [2026, 6, 5]], $proceedings['eventDate']['rangeParts'] ?? null);
        $t->same('WordCamp Migration Summit', $paper['eventTitle'] ?? null);
        $t->same('2026-06-04/2026-06-05', $paper['eventDate']['display'] ?? null);
        $t->same('(Curator 2026; Ng 2026)', $processor->renderCitationCluster([
            $citation('event-proceedings', '[@event-proceedings]'),
            $citation('event-paper', '[@event-paper]'),
        ]));
        $t->same(
            'Curator, Eli. WordPress Import Conference Proceedings. Event: WordCamp Migration Summit. Event addendum: Reviewer track. Event type: conference. Event place: Portland. Event date 2026-06-04/2026-06-05. Migration Desk, 2026.',
            $processor->renderBibliographyEntry('event-proceedings')
        );
        $t->same(
            'Ng, Nia. Source Packet Event Review. WordPress Import Conference Proceedings. Event: WordCamp Migration Summit. Event addendum: Reviewer track. Event type: conference. Event place: Portland. Event date 2026-06-04/2026-06-05. Migration Desk, 2026. 44-48.',
            $processor->renderBibliographyEntry('event-paper')
        );

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author editor"/>
        <text variable="event"/>
        <text variable="event-title-addon"/>
        <text variable="event-type"/>
        <text variable="event-place"/>
        <date variable="event-date"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="event"/>
      <text variable="event-title-addon"/>
      <text variable="event-type"/>
      <text variable="event-place"/>
      <date variable="event-date"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('[Curator | WordCamp Migration Summit | Reviewer track | conference | Portland | 2026-06-04/2026-06-05; Ng | WordCamp Migration Summit | Reviewer track | conference | Portland | 2026-06-04/2026-06-05]', $styled->renderCitationCluster([
            $citation('event-proceedings', '[@event-proceedings]'),
            $citation('event-paper', '[@event-paper]'),
        ]));
        $t->same('Source Packet Event Review :: WordCamp Migration Summit :: Reviewer track :: conference :: Portland :: 2026-06-04/2026-06-05', $styled->renderBibliographyEntry('event-paper'));

        $document = (new MarkdownReader())->read('Event paper @event-paper and proceedings [@event-proceedings] preserve conference metadata.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Event paper Ng (2026) and proceedings (Curator 2026) preserve conference metadata.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Ng, Nia. Source Packet Event Review. WordPress Import Conference Proceedings. Event: WordCamp Migration Summit. Event addendum: Reviewer track. Event type: conference. Event place: Portland. Event date 2026-06-04/2026-06-05. Migration Desk, 2026. 44-48.</dd>', $blocks);

        $manual = CitationCslProcessor::fromItems([[
            'id' => 'manual-event',
            'title' => 'Manual Event Source',
            'event' => 'Manual Review Meeting',
            'event-place' => 'Remote',
            'event-date' => ['date-parts' => [[2026, 6, 5]]],
        ]])->item('manual-event');
        $t->same('Manual Review Meeting', $manual['eventTitle'] ?? null);
        $t->same('Remote', $manual['eventPlace'] ?? null);
        $t->same('2026-06-05', $manual['eventDate']['display'] ?? null);
    },
    'maps bounded biblatex event organizer metadata into csl handoff' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@proceedings{organized-proceedings,
  editor        = {Curator, Eli},
  title         = {WordPress Import Conference Proceedings},
  eventtitle    = {WordCamp Migration Summit},
  organization  = {{WordCamp Foundation} and {Migration Desk}},
  venue         = {Portland},
  eventdate     = {2026-06-04/2026-06-05},
  date          = {2026},
  publisher     = {Migration Desk Publications}
}

@inproceedings{organized-paper,
  author    = {Ng, Nia},
  title     = {Source Packet Organizer Review},
  pages     = {44--48},
  crossref  = {organized-proceedings}
}

@online{organizer-webinar,
  author         = {Smith, Ada},
  title          = {Remote Review Webinar},
  eventtitle     = {Remote Import Clinic},
  eventorganizer = {{Review Team} and Curator, Eli},
  date           = {2025},
  url            = {https://example.test/organizer-webinar}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(3, count($items));
        $t->same([['literal' => 'WordCamp Foundation'], ['literal' => 'Migration Desk']], $items[0]['event-organizer']);
        $t->same([['literal' => 'WordCamp Foundation'], ['literal' => 'Migration Desk']], $items[1]['event-organizer']);
        $t->same('WordCamp Migration Summit', $items[1]['event']);
        $t->same('Portland', $items[1]['event-place']);
        $t->same('Migration Desk Publications', $items[1]['publisher']);
        $t->same([['literal' => 'Review Team'], ['family' => 'Curator', 'given' => 'Eli']], $items[2]['event-organizer']);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $paper = $processor->item('organized-paper');
        $webinar = $processor->item('organizer-webinar');
        $t->same('WordCamp Foundation', $paper['eventOrganizers'][0]['literal'] ?? null);
        $t->same('Migration Desk', $paper['eventOrganizers'][1]['literal'] ?? null);
        $t->same('Review Team', $webinar['eventOrganizers'][0]['literal'] ?? null);
        $t->same('Curator', $webinar['eventOrganizers'][1]['family'] ?? null);
        $t->same('(Curator 2026; Ng 2026; Smith 2025)', $processor->renderCitationCluster([
            $citation('organized-proceedings', '[@organized-proceedings]'),
            $citation('organized-paper', '[@organized-paper]'),
            $citation('organizer-webinar', '[@organizer-webinar]'),
        ]));
        $t->same(
            'Ng, Nia. Source Packet Organizer Review. WordPress Import Conference Proceedings. Event: WordCamp Migration Summit. Event organizer: WordCamp Foundation; Migration Desk. Event place: Portland. Event date 2026-06-04/2026-06-05. Migration Desk Publications, 2026. 44-48.',
            $processor->renderBibliographyEntry('organized-paper')
        );
        $t->same(
            'Smith, Ada. Remote Review Webinar. Event: Remote Import Clinic. Event organizer: Review Team; Curator, Eli. 2025. https://example.test/organizer-webinar.',
            $processor->renderBibliographyEntry('organizer-webinar')
        );

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author editor"/>
        <names variable="event-organizer"/>
        <text variable="event"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <names variable="event-organizer"/>
      <text variable="event"/>
      <date variable="event-date"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('[Curator | WordCamp Foundation and Migration Desk | WordCamp Migration Summit; Ng | WordCamp Foundation and Migration Desk | WordCamp Migration Summit; Smith | Review Team and Curator | Remote Import Clinic]', $styled->renderCitationCluster([
            $citation('organized-proceedings', '[@organized-proceedings]'),
            $citation('organized-paper', '[@organized-paper]'),
            $citation('organizer-webinar', '[@organizer-webinar]'),
        ]));
        $t->same('Source Packet Organizer Review :: WordCamp Foundation; Migration Desk :: WordCamp Migration Summit :: 2026-06-04/2026-06-05', $styled->renderBibliographyEntry('organized-paper'));

        $document = (new MarkdownReader())->read('Organizer paper @organized-paper and webinar [@organizer-webinar] keep event review owners.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Organizer paper Ng (2026) and webinar (Smith 2025) keep event review owners.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Ng, Nia. Source Packet Organizer Review. WordPress Import Conference Proceedings. Event: WordCamp Migration Summit. Event organizer: WordCamp Foundation; Migration Desk. Event place: Portland. Event date 2026-06-04/2026-06-05. Migration Desk Publications, 2026. 44-48.</dd>', $blocks);
        $t->contains('<dt>Smith 2025</dt><dd>Smith, Ada. Remote Review Webinar. Event: Remote Import Clinic. Event organizer: Review Team; Curator, Eli. 2025. https://example.test/organizer-webinar.</dd>', $blocks);

        $manual = CitationCslProcessor::fromItems([[
            'id' => 'manual-organizer',
            'title' => 'Manual Organizer Source',
            'event-organizer' => [
                ['literal' => 'Manual Review Desk'],
            ],
        ]])->item('manual-organizer');
        $t->same('Manual Review Desk', $manual['eventOrganizers'][0]['literal'] ?? null);
    },
    'maps bounded biblatex ids aliases into canonical csl citations' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@book{canonical-manual,
  author    = {Smith, Ada},
  title     = {Alias Import Manual},
  date      = {2026},
  publisher = {Review Press},
  ids       = {legacy-manual, source-packet-manual}
}

@book{separate-source,
  author = {Curator, Eli},
  title  = {Separate Source},
  date   = {2025}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(2, count($items));
        $t->same('canonical-manual', $items[0]['id']);
        $t->same(['legacy-manual', 'source-packet-manual'], $items[0]['citation-aliases']);
        $t->same('legacy-manual, source-packet-manual', $items[0]['rawBibtex']['fields']['ids'] ?? null);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $canonical = $processor->item('canonical-manual');
        $alias = $processor->item('legacy-manual');
        $t->same(['legacy-manual', 'source-packet-manual'], $canonical['citationAliases'] ?? null);
        $t->same('canonical-manual', $alias['id'] ?? null);
        $t->same('legacy-manual', $alias['citationAlias'] ?? null);
        $t->same('Alias Import Manual', $alias['title'] ?? null);
        $t->same('(Smith 2026)', $processor->renderCitationCluster([$citation('legacy-manual', '[@legacy-manual]')]));
        $t->same('Smith, Ada. Alias Import Manual. Review Press, 2026.', $processor->renderBibliographyEntry('source-packet-manual'));

        $document = (new MarkdownReader())->read('Alias source @legacy-manual and primary [@canonical-manual] stay one bibliography item. Missing [@missing-source] remains visible.');
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $t->same(['legacy-manual', 'canonical-manual', 'missing-source'], $processor->citationIds($document));
        $t->same(['missing-source'], $processor->missingCitationIds($document));
        $bibliography = $processed->children[2] ?? null;
        $t->same('definition_list', $bibliography?->type);
        $t->same(1, count($bibliography?->children ?? []));
        $t->same('Smith 2026', $bibliography?->children[0]->children[0]->attr('text'));

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Alias source Smith (2026) and primary (Smith 2026) stay one bibliography item. Missing [@missing-source] remains visible.</p>', $blocks);
        $t->same(1, substr_count($blocks, '<dt>Smith 2026</dt><dd>Smith, Ada. Alias Import Manual. Review Press, 2026.</dd>'));

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="citation-aliases"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="citation-key"/>
      <text variable="title"/>
      <text variable="citation-aliases"/>
    </layout>
  </bibliography>
</style>
XML);
        $t->same('[Alias Import Manual | legacy-manual, source-packet-manual]', $styled->renderCitationCluster([$citation('legacy-manual', '[@legacy-manual]')]));
        $t->same('canonical-manual :: Alias Import Manual :: legacy-manual, source-packet-manual', $styled->renderBibliographyEntry('canonical-manual'));

        $manual = CitationCslProcessor::fromItems([[
            'id' => 'manual-primary',
            'title' => 'Manual Alias Source',
            'citation-aliases' => ['manual-alias'],
        ]])->item('manual-alias');
        $t->same('manual-primary', $manual['id'] ?? null);
        $t->same('manual-alias', $manual['citationAlias'] ?? null);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromBibtex('@book{a,title={A},ids={b}} @book{b,title={B}}'));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromBibtex('@book{a,title={A},ids={alias}} @book{b,title={B},ids={alias}}'));
    },
    'parses pandoc bracketed citation clusters with prefixes locators suppression and url keys' => static function (TestRunner $t) use ($cslJson): void {
        $processor = CitationCslProcessor::fromJson($cslJson());
        $document = (new MarkdownReader())->read(
            'Archive says [see @smith1899, pp. 33-35; also @doe2020, chap. 1; -@wp-team, sec. 2].'
            . "\n\n" . 'Forced locator [@smith1899, {ii, A, D-Z} with a suffix].'
            . "\n\n" . 'URL key [@{https://example.com/bib?name=foobar&date=2000}, p. 33].'
            . "\n\n" . 'Review keeps [see @missing-source; @smith1899, p. 7] visible.'
        );

        $cluster = $document->children[0]->children[1];
        $t->same('citation_group', $cluster->type);
        $t->same('[@smith1899]', (new MarkdownReader())->read('[@smith1899]')->children[0]->children[0]->attr('text'));
        $t->same(['citation', 'citation', 'citation'], array_map(static fn (AstNode $node): string => $node->type, $cluster->children));
        $t->same('smith1899', $cluster->children[0]->attr('id'));
        $t->same('see', $cluster->children[0]->attr('prefix'));
        $t->same('pp. 33-35', $cluster->children[0]->attr('locator'));
        $t->same('doe2020', $cluster->children[1]->attr('id'));
        $t->same('also', $cluster->children[1]->attr('prefix'));
        $t->same('chap. 1', $cluster->children[1]->attr('locator'));
        $t->same('wp-team', $cluster->children[2]->attr('id'));
        $t->same('suppress_author', $cluster->children[2]->attr('mode'));
        $t->same('sec. 2', $cluster->children[2]->attr('locator'));

        $forced = $document->children[1]->children[1];
        $t->same('citation', $forced->type);
        $t->same('ii, A, D-Z with a suffix', $forced->attr('locator'));
        $urlKey = $document->children[2]->children[1];
        $t->same('https://example.com/bib?name=foobar&date=2000', $urlKey->attr('id'));
        $t->same('p. 33', $urlKey->attr('locator'));

        $processed = $processor->apply($document);
        $processedCluster = $processed->children[0]->children[1];
        $missingCluster = $processed->children[3]->children[1];
        $t->same('(see Smith 1899, pp. 33-35; also Doe and Roe 2020, chap. 1; 2024, sec. 2)', $processedCluster->attr('rendered'));
        $t->same('(see @missing-source; Smith 1899, p. 7)', $missingCluster->attr('rendered'));
        $t->same(['missing-source'], $missingCluster->attr('missingCslItems'));
        $t->same(
            ['smith1899', 'doe2020', 'wp-team', 'smith1899', 'https://example.com/bib?name=foobar&date=2000', 'missing-source', 'smith1899'],
            $processor->citationIds($document)
        );
        $t->same(['missing-source'], $processor->missingCitationIds($document));

        $withBibliography = $processor->appendBibliography($document, 'Works Cited');
        $markdown = (new MarkdownWriter())->write($withBibliography);
        $t->contains('Archive says (see Smith 1899, pp. 33-35; also Doe and Roe 2020, chap. 1; 2024, sec. 2).', $markdown);
        $t->contains('Forced locator (Smith 1899, ii, A, D-Z with a suffix).', $markdown);
        $t->contains('URL key (URL Key Source 2000, p. 33).', $markdown);
        $t->contains('Review keeps (see @missing-source; Smith 1899, p. 7) visible.', $markdown);
        $t->contains('URL Key Source 2000' . "\n" . ':   URL Key Source. 2000. https://example.com/bib?name=foobar&date=2000.', $markdown);

        $blocks = (new WordPressBlockWriter())->write($withBibliography);
        $t->contains('<p>Archive says (see Smith 1899, pp. 33-35; also Doe and Roe 2020, chap. 1; 2024, sec. 2).</p>', $blocks);
        $t->contains('<p>URL key (URL Key Source 2000, p. 33).</p>', $blocks);
        $t->contains('<p>Review keeps (see @missing-source; Smith 1899, p. 7) visible.</p>', $blocks);
        $t->contains('<dt>URL Key Source 2000</dt><dd>URL Key Source. 2000. https://example.com/bib?name=foobar&amp;date=2000.</dd>', $blocks);
    },
    'appends deterministic csl bibliography blocks for markdown and wordpress output' => static function (TestRunner $t) use ($cslJson): void {
        $processor = CitationCslProcessor::fromJson($cslJson());
        $document = (new MarkdownReader())->read(
            'Smith says @smith1899 and later [@doe2020].'
            . "\n\n" . 'The migration team wrote @wp-team.'
            . "\n\n" . 'Missing source [@missing] stays reviewable.'
            . "\n\n" . 'Smith repeats @smith1899.'
        );
        $processed = $processor->appendBibliography($document, 'Works Cited');

        $t->same(['smith1899', 'doe2020', 'wp-team', 'missing', 'smith1899'], $processor->citationIds($document));
        $t->same(['missing'], $processor->missingCitationIds($document));
        $t->same('document', $processed->type);
        $t->same(6, count($processed->children));
        $t->same('heading', $processed->children[4]->type);
        $t->same('works-cited', $processed->children[4]->attr('id'));
        $t->same('definition_list', $processed->children[5]->type);
        $t->same(3, count($processed->children[5]->children));
        $t->same('Smith 1899', $processed->children[5]->children[0]->children[0]->attr('text'));
        $t->same('Doe and Roe 2020', $processed->children[5]->children[1]->children[0]->attr('text'));
        $t->same('WordPress Migration Team 2024', $processed->children[5]->children[2]->children[0]->attr('text'));
        $t->same('Smith, Ada. Migration Patterns. Archive Press, 1899. DOI 10.1234/source.', $processor->renderBibliographyEntry('smith1899'));
        $t->same('Doe, Jane; Roe, Pat. Field Notes. Journal of Imports. 2020. 55-60. https://example.test/field-notes.', $processor->renderBibliographyEntry('doe2020'));
        $t->same('WordPress Migration Team. Reviewer Log. 2024. https://example.test/reviewer-log.', $processor->renderBibliographyEntry('wp-team'));

        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains('Smith says Smith (1899) and later (Doe and Roe 2020).', $markdown);
        $t->contains('The migration team wrote WordPress Migration Team (2024).', $markdown);
        $t->contains('Missing source [@missing] stays reviewable.', $markdown);
        $t->contains('## Works Cited', $markdown);
        $t->contains('Smith 1899' . "\n" . ':   Smith, Ada. Migration Patterns. Archive Press, 1899. DOI 10.1234/source.', $markdown);
        $t->contains('Doe and Roe 2020' . "\n" . ':   Doe, Jane; Roe, Pat. Field Notes. Journal of Imports. 2020. 55-60. https://example.test/field-notes.', $markdown);

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Smith says Smith (1899) and later (Doe and Roe 2020).</p>', $blocks);
        $t->contains('<p>The migration team wrote WordPress Migration Team (2024).</p>', $blocks);
        $t->contains('<p>Missing source [@missing] stays reviewable.</p>', $blocks);
        $t->contains('<h2 id="works-cited">Works Cited</h2>', $blocks);
        $t->contains('<dt>Smith 1899</dt><dd>Smith, Ada. Migration Patterns. Archive Press, 1899. DOI 10.1234/source.</dd>', $blocks);
        $t->contains('<dt>Doe and Roe 2020</dt><dd>Doe, Jane; Roe, Pat. Field Notes. Journal of Imports. 2020. 55-60. https://example.test/field-notes.</dd>', $blocks);
        $t->contains('<dt>WordPress Migration Team 2024</dt><dd>WordPress Migration Team. Reviewer Log. 2024. https://example.test/reviewer-log.</dd>', $blocks);
    },
    'applies bounded csl style xml citation layout and locale terms' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'two-authors',
                'type' => 'webpage',
                'title' => 'Two Author Packet',
                'author' => [
                    ['family' => 'Mueller', 'given' => 'Mia'],
                    ['family' => 'Schmidt', 'given' => 'Sam'],
                ],
                'issued' => ['date-parts' => [[2024]]],
                'URL' => 'https://example.test/two-authors',
            ],
            [
                'id' => 'three-authors',
                'type' => 'report',
                'title' => 'Three Author Packet',
                'author' => [
                    ['family' => 'Garcia', 'given' => 'Gia'],
                    ['family' => 'Ng', 'given' => 'Nia'],
                    ['family' => 'Okafor', 'given' => 'Ola'],
                ],
                'issued' => ['date-parts' => [[2025]]],
            ],
            [
                'id' => 'undated',
                'type' => 'webpage',
                'title' => 'Undated Packet',
                'issued' => [],
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="de-DE">
  <info>
    <title>Bounded Local Review Style</title>
    <id>https://example.test/styles/bounded-local-review</id>
    <updated>2026-06-04T00:00:00+00:00</updated>
  </info>
  <locale xml:lang="de-DE">
    <terms>
      <term name="et-al">u. a.</term>
      <term name="no date">o. J.</term>
    </terms>
  </locale>
  <citation>
    <layout prefix="[" suffix="]" delimiter=" | "/>
  </citation>
  <bibliography>
    <layout/>
  </bibliography>
</style>
XML,
            [
                <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<locale xmlns="http://purl.org/net/xbiblio/csl" xml:lang="de-DE" version="1.0">
  <terms>
    <term name="and">und</term>
    <term name="no date">kein Datum</term>
  </terms>
</locale>
XML,
            ]
        );

        $summary = $processor->cslStyleSummary();
        $t->same('Bounded Local Review Style', $summary['title'] ?? null);
        $t->same('de-DE', $summary['defaultLocale'] ?? null);
        $t->same('[', $summary['citationLayout']['prefix'] ?? null);
        $t->same(' | ', $summary['citationLayout']['delimiter'] ?? null);

        $document = (new MarkdownReader())->read('Review [@two-authors; @three-authors; @undated]. Missing [@missing] stays visible.');
        $processed = $processor->appendBibliography($document, 'Works Cited');

        $cluster = $processed->children[0]->children[1];
        $missing = $processed->children[0]->children[3];
        $t->same('[Mueller und Schmidt 2024 | Garcia u. a. 2025 | Undated Packet o. J.]', $cluster->attr('rendered'));
        $t->same('[@missing]', $missing->attr('rendered'));
        $t->same('Mueller und Schmidt 2024', $processed->children[2]->children[0]->children[0]->attr('text'));
        $t->same('Garcia u. a. 2025', $processed->children[2]->children[1]->children[0]->attr('text'));
        $t->same('Undated Packet o. J.', $processed->children[2]->children[2]->children[0]->attr('text'));
        $t->same('Mueller, Mia; Schmidt, Sam. Two Author Packet. 2024. https://example.test/two-authors.', $processor->renderBibliographyEntry('two-authors'));
        $t->same('Undated Packet.', $processor->renderBibliographyEntry('undated'));

        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains('Review [Mueller und Schmidt 2024 | Garcia u. a. 2025 | Undated Packet o. J.]. Missing [@missing] stays visible.', $markdown);
        $t->contains('Undated Packet o. J.' . "\n" . ':   Undated Packet.', $markdown);

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Review [Mueller und Schmidt 2024 | Garcia u. a. 2025 | Undated Packet o. J.]. Missing [@missing] stays visible.</p>', $blocks);
        $t->contains('<dt>Undated Packet o. J.</dt><dd>Undated Packet.</dd>', $blocks);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle('<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text"><info/></style>'));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle('<locale xmlns="http://purl.org/net/xbiblio/csl" version="1.0"/>'));
    },
    'applies bounded csl bibliography layout affixes and accessed locale term' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'source-packet',
                'type' => 'webpage',
                'title' => 'Source Packet',
                'author' => [
                    ['family' => 'Cruz', 'given' => 'Ana Maria', 'non-dropping-particle' => 'de la'],
                ],
                'issued' => ['date-parts' => [[2026, 6, 4]]],
                'accessed' => ['date-parts' => [[2026, 6, 5]]],
                'URL' => 'https://example.test/source-packet',
            ],
            [
                'id' => 'undated-packet',
                'type' => 'report',
                'title' => 'Undated Packet',
                'author' => [
                    ['family' => 'Adams', 'given' => 'Ari'],
                    ['family' => 'Baker', 'given' => 'Bea'],
                    ['family' => 'Clark', 'given' => 'Cy'],
                ],
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-GB">
  <info>
    <title>WordPress Review Bibliography</title>
    <id>https://example.test/styles/wordpress-review-bibliography</id>
    <updated>2026-06-05T00:00:00+00:00</updated>
  </info>
  <locale xml:lang="en">
    <terms>
      <term name="accessed">Retrieved</term>
      <term name="et-al">and colleagues</term>
      <term name="no date">no source date</term>
    </terms>
  </locale>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; "/>
  </citation>
  <bibliography hanging-indent="true" entry-spacing="0" line-spacing="1">
    <layout prefix="[" suffix="]" delimiter=" "/>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $t->same('[', $summary['bibliographyLayout']['prefix'] ?? null);
        $t->same(']', $summary['bibliographyLayout']['suffix'] ?? null);
        $t->same(' ', $summary['bibliographyLayout']['delimiter'] ?? null);
        $t->same(true, $summary['bibliographyOptions']['hangingIndent'] ?? null);
        $t->same(0, $summary['bibliographyOptions']['entrySpacing'] ?? null);
        $t->same(1, $summary['bibliographyOptions']['lineSpacing'] ?? null);
        $t->same('Retrieved', $summary['terms']['accessed'] ?? null);

        $t->same('(de la Cruz 2026; Adams and colleagues no source date)', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'source-packet', 'text' => '[@source-packet]']),
            new AstNode('citation', ['id' => 'undated-packet', 'text' => '[@undated-packet]']),
        ]));
        $t->same('[de la Cruz, Ana Maria. Source Packet. 2026. https://example.test/source-packet. Retrieved 2026-06-05.]', $processor->renderBibliographyEntry('source-packet'));
        $t->same('[Adams, Ari; Baker, Bea; Clark, Cy. Undated Packet.]', $processor->renderBibliographyEntry('undated-packet'));

        $document = (new MarkdownReader())->read('Review cites @source-packet and [@undated-packet].');
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $bibliography = $processed->children[2];
        $t->same('definition_list', $bibliography->type);
        $t->same(true, $bibliography->attr('hangingIndent'));
        $t->same(0, $bibliography->attr('entrySpacing'));
        $t->same(1, $bibliography->attr('lineSpacing'));

        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains('Review cites de la Cruz (2026) and (Adams and colleagues no source date).', $markdown);
        $t->contains('de la Cruz 2026' . "\n" . ':   \[de la Cruz, Ana Maria. Source Packet. 2026. https://example.test/source-packet. Retrieved 2026-06-05.\]', $markdown);
        $t->contains('Adams and colleagues no source date' . "\n" . ':   \[Adams, Ari; Baker, Bea; Clark, Cy. Undated Packet.\]', $markdown);

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<dt>de la Cruz 2026</dt><dd>[de la Cruz, Ana Maria. Source Packet. 2026. https://example.test/source-packet. Retrieved 2026-06-05.]</dd>', $blocks);
        $t->contains('<dt>Adams and colleagues no source date</dt><dd>[Adams, Ari; Baker, Bea; Clark, Cy. Undated Packet.]</dd>', $blocks);
    },
    'applies bounded csl citation and bibliography sort keys' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'zed2023',
                'type' => 'webpage',
                'title' => 'Zed Packet',
                'author' => [
                    ['family' => 'Zed', 'given' => 'Zoe'],
                ],
                'issued' => ['date-parts' => [[2023]]],
                'URL' => 'https://example.test/zed',
            ],
            [
                'id' => 'adams2024',
                'type' => 'webpage',
                'title' => 'Newer Adams Packet',
                'author' => [
                    ['family' => 'Adams', 'given' => 'Ari'],
                ],
                'issued' => ['date-parts' => [[2024]]],
                'URL' => 'https://example.test/adams-new',
            ],
            [
                'id' => 'adams2020',
                'type' => 'report',
                'title' => 'Older Adams Packet',
                'author' => [
                    ['family' => 'Adams', 'given' => 'Ari'],
                ],
                'issued' => ['date-parts' => [[2020]]],
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Sorted WordPress Review Style</title>
    <id>https://example.test/styles/sorted-wordpress-review</id>
    <updated>2026-06-05T00:30:00+00:00</updated>
  </info>
  <citation>
    <sort>
      <key variable="issued" sort="descending"/>
      <key variable="author"/>
    </sort>
    <layout prefix="(" suffix=")" delimiter="; "/>
  </citation>
  <bibliography second-field-align="flush">
    <sort>
      <key variable="author"/>
      <key variable="issued"/>
      <key variable="title"/>
    </sort>
    <layout/>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $t->same('Sorted WordPress Review Style', $summary['title'] ?? null);
        $t->same('issued', $summary['citationSort'][0]['variable'] ?? null);
        $t->same('descending', $summary['citationSort'][0]['sort'] ?? null);
        $t->same('author', $summary['bibliographySort'][0]['variable'] ?? null);
        $t->same('issued', $summary['bibliographySort'][1]['variable'] ?? null);
        $t->same('flush', $summary['bibliographyOptions']['secondFieldAlign'] ?? null);

        $document = (new MarkdownReader())->read(
            'Review cites [@zed2023; @adams2024; @adams2020].'
            . "\n\n" . 'First-cited order in source remains inspectable before sorting.'
        );
        $processed = $processor->appendBibliography($document, 'Works Cited');

        $cluster = $processed->children[0]->children[1];
        $bibliography = $processed->children[3];
        $t->same('(Adams 2024; Zed 2023; Adams 2020)', $cluster->attr('rendered'));
        $t->same('definition_list', $bibliography->type);
        $t->same('flush', $bibliography->attr('secondFieldAlign'));
        $t->same('Adams 2020', $bibliography->children[0]->children[0]->attr('text'));
        $t->same('Adams 2024', $bibliography->children[1]->children[0]->attr('text'));
        $t->same('Zed 2023', $bibliography->children[2]->children[0]->attr('text'));
        $t->same(['zed2023', 'adams2024', 'adams2020'], $processor->citationIds($document));

        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains('Review cites (Adams 2024; Zed 2023; Adams 2020).', $markdown);
        $adamsOldPosition = strpos($markdown, 'Adams 2020' . "\n" . ':   Adams, Ari. Older Adams Packet. 2020.');
        $adamsNewPosition = strpos($markdown, 'Adams 2024' . "\n" . ':   Adams, Ari. Newer Adams Packet. 2024. https://example.test/adams-new.');
        $zedPosition = strpos($markdown, 'Zed 2023' . "\n" . ':   Zed, Zoe. Zed Packet. 2023. https://example.test/zed.');
        $t->true(is_int($adamsOldPosition) && is_int($adamsNewPosition) && is_int($zedPosition), 'Sorted bibliography entries were not rendered');
        $t->true($adamsOldPosition < $adamsNewPosition && $adamsNewPosition < $zedPosition, 'Bibliography entries should follow CSL sort order');

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Review cites (Adams 2024; Zed 2023; Adams 2020).</p>', $blocks);
        $blocksAdamsOldPosition = strpos($blocks, '<dt>Adams 2020</dt><dd>Adams, Ari. Older Adams Packet. 2020.</dd>');
        $blocksAdamsNewPosition = strpos($blocks, '<dt>Adams 2024</dt><dd>Adams, Ari. Newer Adams Packet. 2024. https://example.test/adams-new.</dd>');
        $blocksZedPosition = strpos($blocks, '<dt>Zed 2023</dt><dd>Zed, Zoe. Zed Packet. 2023. https://example.test/zed.</dd>');
        $t->true(is_int($blocksAdamsOldPosition) && is_int($blocksAdamsNewPosition) && is_int($blocksZedPosition), 'Sorted WordPress bibliography entries were not rendered');
        $t->true($blocksAdamsOldPosition < $blocksAdamsNewPosition && $blocksAdamsNewPosition < $blocksZedPosition, 'WordPress bibliography entries should follow CSL sort order');

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <sort><key variable="issued" sort="sideways"/></sort>
    <layout/>
  </citation>
</style>
XML
        ));
    },
    'applies bounded csl name-part formatting for family and given names' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'name-part-source',
                'type' => 'report',
                'title' => 'Name Part Source',
                'author' => [
                    [
                        'family' => 'Cruz',
                        'given' => 'Ana Maria',
                        'non-dropping-particle' => 'de la',
                        'suffix' => 'Jr.',
                        'comma-suffix' => true,
                    ],
                ],
                'issued' => ['date-parts' => [[2026]]],
                'publisher' => 'Review Press',
            ],
            [
                'id' => 'given-only-source',
                'type' => 'report',
                'title' => 'Given Only Source',
                'author' => [
                    ['given' => 'Single Name'],
                ],
                'issued' => ['date-parts' => [[2025]]],
            ],
            [
                'id' => 'literal-source',
                'type' => 'webpage',
                'title' => 'Literal Source',
                'author' => [
                    ['literal' => 'Review Desk Inc.'],
                ],
                'issued' => ['date-parts' => [[2024]]],
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Name Part Review Style</title>
    <id>https://example.test/styles/bounded-name-part-review</id>
    <updated>2026-06-05T06:00:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author" delimiter=", ">
          <name initialize-with=". ">
            <name-part name="family" text-case="uppercase"/>
            <name-part name="given" prefix="[" suffix="]" strip-periods="true" text-case="uppercase"/>
          </name>
        </names>
        <date variable="issued">
          <date-part name="year"/>
        </date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout>
      <group delimiter=". " suffix=".">
        <names variable="author" delimiter="; ">
          <name initialize-with=". " name-as-sort-order="all">
            <name-part name="family" text-case="uppercase"/>
            <name-part name="given" prefix="given " strip-periods="true"/>
          </name>
        </names>
        <text variable="title"/>
        <text variable="publisher"/>
      </group>
    </layout>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $citationNameParts = $summary['citationRendering'][0]['children'][0]['nameRendering']['nameParts'] ?? [];
        $bibliographyNameParts = $summary['bibliographyRendering'][0]['children'][0]['nameRendering']['nameParts'] ?? [];
        $t->same('Bounded Name Part Review Style', $summary['title'] ?? null);
        $t->same('uppercase', $citationNameParts['family']['textCase'] ?? null);
        $t->same('[', $citationNameParts['given']['prefix'] ?? null);
        $t->same(']', $citationNameParts['given']['suffix'] ?? null);
        $t->same(true, $citationNameParts['given']['stripPeriods'] ?? null);
        $t->same('given ', $bibliographyNameParts['given']['prefix'] ?? null);
        $t->same('uppercase', $summary['nameRendering']['citation']['nameParts']['family']['textCase'] ?? null);

        $t->same('(DE LA CRUZ 2026; [S N] 2025; Review Desk Inc. 2024)', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'name-part-source', 'text' => '[@name-part-source]']),
            new AstNode('citation', ['id' => 'given-only-source', 'text' => '[@given-only-source]']),
            new AstNode('citation', ['id' => 'literal-source', 'text' => '[@literal-source]']),
        ]));
        $t->same('DE LA CRUZ, given A M, Jr. Name Part Source. Review Press.', $processor->renderBibliographyEntry('name-part-source'));
        $t->same('given S N. Given Only Source.', $processor->renderBibliographyEntry('given-only-source'));
        $t->same('Review Desk Inc. Literal Source.', $processor->renderBibliographyEntry('literal-source'));

        $document = (new MarkdownReader())->read('Name part source @name-part-source, given-only [@given-only-source], and literal @literal-source.');
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Name part source DE LA CRUZ (2026), given-only ([S N] 2025), and literal Review Desk Inc. (2024).</p>', $blocks);
        $t->contains('<dt>DE LA CRUZ 2026</dt><dd>DE LA CRUZ, given A M, Jr. Name Part Source. Review Press.</dd>', $blocks);
        $t->contains('<dt>[S N] 2025</dt><dd>given S N. Given Only Source.</dd>', $blocks);
        $t->contains('<dt>Review Desk Inc. 2024</dt><dd>Review Desk Inc. Literal Source.</dd>', $blocks);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <names variable="author"><name><name-part name="suffix"/></name></names>
    </layout>
  </citation>
</style>
XML
        ));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <names variable="author"><name><name-part name="family"/><name-part name="family"/></name></names>
    </layout>
  </citation>
</style>
XML
        ));
    },
    'applies bounded csl names substitutes for missing primary creators' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'author-source',
                'type' => 'report',
                'title' => 'Author Packet',
                'author' => [
                    ['family' => 'Smith', 'given' => 'Ada'],
                ],
                'issued' => ['date-parts' => [[2026]]],
            ],
            [
                'id' => 'editor-source',
                'type' => 'book',
                'title' => 'Edited Packet',
                'editor' => [
                    ['family' => 'Curator', 'given' => 'Eli'],
                ],
                'issued' => ['date-parts' => [[2025]]],
            ],
            [
                'id' => 'translator-source',
                'type' => 'book',
                'title' => 'Translated Packet',
                'translator' => [
                    ['family' => 'Translator', 'given' => 'Tia'],
                ],
                'issued' => ['date-parts' => [[2024]]],
            ],
            [
                'id' => 'title-source',
                'type' => 'report',
                'title' => 'Orphan Packet',
                'issued' => ['date-parts' => [[2023]]],
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Name Substitute Review Style</title>
    <id>https://example.test/styles/bounded-name-substitute-review</id>
    <updated>2026-06-05T06:55:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author" delimiter=", ">
          <name initialize-with=". "/>
          <substitute>
            <names variable="editor"/>
            <names variable="translator"/>
            <text variable="title"/>
          </substitute>
        </names>
        <date variable="issued">
          <date-part name="year"/>
        </date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author" delimiter="; ">
        <name initialize-with=". " name-as-sort-order="all"/>
        <substitute>
          <names variable="editor"/>
          <names variable="translator"/>
          <text variable="title"/>
        </substitute>
      </names>
      <choose>
        <if variable="author editor translator" match="none">
          <text value="title-only source packet"/>
        </if>
        <else>
          <text variable="title"/>
        </else>
      </choose>
    </layout>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $citationSubstitute = $summary['citationRendering'][0]['children'][0]['substitute'] ?? [];
        $bibliographySubstitute = $summary['bibliographyRendering'][0]['substitute'] ?? [];
        $t->same('Bounded Name Substitute Review Style', $summary['title'] ?? null);
        $t->same('author', $summary['citationRendering'][0]['children'][0]['variable'] ?? null);
        $t->same('names', $citationSubstitute[0]['type'] ?? null);
        $t->same('editor', $citationSubstitute[0]['variable'] ?? null);
        $t->same('translator', $citationSubstitute[1]['variable'] ?? null);
        $t->same('title', $citationSubstitute[2]['variable'] ?? null);
        $t->same('translator', $bibliographySubstitute[1]['variable'] ?? null);

        $t->same('(Smith 2026; Curator 2025; Translator 2024; Orphan Packet 2023)', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'author-source', 'text' => '[@author-source]']),
            new AstNode('citation', ['id' => 'editor-source', 'text' => '[@editor-source]']),
            new AstNode('citation', ['id' => 'translator-source', 'text' => '[@translator-source]']),
            new AstNode('citation', ['id' => 'title-source', 'text' => '[@title-source]']),
        ]));
        $t->same('Smith, A. Author Packet.', $processor->renderBibliographyEntry('author-source'));
        $t->same('Curator, E. Edited Packet.', $processor->renderBibliographyEntry('editor-source'));
        $t->same('Translator, T. Translated Packet.', $processor->renderBibliographyEntry('translator-source'));
        $t->same('Orphan Packet. title-only source packet.', $processor->renderBibliographyEntry('title-source'));

        $document = (new MarkdownReader())->read('Review cites [@editor-source; @translator-source; @title-source] for incomplete source packets.');
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains('Review cites (Curator 2025; Translator 2024; Orphan Packet 2023) for incomplete source packets.', $markdown);
        $t->contains('Curator 2025' . "\n" . ':   Curator, E. Edited Packet.', $markdown);
        $t->contains('Translator 2024' . "\n" . ':   Translator, T. Translated Packet.', $markdown);
        $t->contains('Orphan Packet 2023' . "\n" . ':   Orphan Packet. title-only source packet.', $markdown);

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Review cites (Curator 2025; Translator 2024; Orphan Packet 2023) for incomplete source packets.</p>', $blocks);
        $t->contains('<dt>Curator 2025</dt><dd>Curator, E. Edited Packet.</dd>', $blocks);
        $t->contains('<dt>Translator 2024</dt><dd>Translator, T. Translated Packet.</dd>', $blocks);
        $t->contains('<dt>Orphan Packet 2023</dt><dd>Orphan Packet. title-only source packet.</dd>', $blocks);
    },
    'applies bounded csl name rendering options for initials and et al thresholds' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'source-packet',
                'type' => 'webpage',
                'title' => 'Source Packet',
                'author' => [
                    ['family' => 'Cruz', 'given' => 'Ana Maria', 'non-dropping-particle' => 'de la'],
                    ['family' => 'Ng', 'given' => 'Nia'],
                    ['family' => 'Okafor', 'given' => 'Ola'],
                    ['family' => 'Smith', 'given' => 'Sam'],
                ],
                'issued' => ['date-parts' => [[2026, 6, 5]]],
                'URL' => 'https://example.test/source-packet',
            ],
            [
                'id' => 'editor-packet',
                'type' => 'book',
                'title' => 'Edited Packet',
                'editor' => [
                    ['family' => 'Curator', 'given' => 'Eli', 'suffix' => 'III', 'comma-suffix' => true],
                ],
                'issued' => ['date-parts' => [[2025]]],
                'publisher' => 'Review Press',
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Name Options Review Style</title>
    <id>https://example.test/styles/bounded-name-options</id>
    <updated>2026-06-05T01:00:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <names variable="author editor" delimiter=", " et-al-min="3" et-al-use-first="2">
        <name initialize-with=". "/>
      </names>
    </layout>
  </citation>
  <bibliography>
    <layout>
      <names variable="author editor" delimiter="; " et-al-min="4" et-al-use-first="2">
        <name initialize-with=". " name-as-sort-order="all"/>
      </names>
    </layout>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $t->same('Bounded Name Options Review Style', $summary['title'] ?? null);
        $t->same(3, $summary['nameRendering']['citation']['etAlMin'] ?? null);
        $t->same(2, $summary['nameRendering']['citation']['etAlUseFirst'] ?? null);
        $t->same(', ', $summary['nameRendering']['citation']['delimiter'] ?? null);
        $t->same('. ', $summary['nameRendering']['bibliography']['initializeWith'] ?? null);
        $t->same('all', $summary['nameRendering']['bibliography']['nameAsSortOrder'] ?? null);

        $t->same('(de la Cruz, Ng, et al. 2026; Curator 2025)', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'source-packet', 'text' => '[@source-packet]']),
            new AstNode('citation', ['id' => 'editor-packet', 'text' => '[@editor-packet]']),
        ]));
        $t->same('de la Cruz, A. M.; Ng, N.; et al. Source Packet. 2026. https://example.test/source-packet.', $processor->renderBibliographyEntry('source-packet'));
        $t->same('Curator, E., III. Edited Packet. Review Press, 2025.', $processor->renderBibliographyEntry('editor-packet'));

        $document = (new MarkdownReader())->read('Review cites @source-packet and [@editor-packet].');
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains('Review cites de la Cruz, Ng, et al. (2026) and (Curator 2025).', $markdown);
        $t->contains('de la Cruz, Ng, et al. 2026' . "\n" . ':   de la Cruz, A. M.; Ng, N.; et al. Source Packet. 2026. https://example.test/source-packet.', $markdown);

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Review cites de la Cruz, Ng, et al. (2026) and (Curator 2025).</p>', $blocks);
        $t->contains('<dt>de la Cruz, Ng, et al. 2026</dt><dd>de la Cruz, A. M.; Ng, N.; et al. Source Packet. 2026. https://example.test/source-packet.</dd>', $blocks);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <names variable="author" et-al-min="soon"><name/></names>
    </layout>
  </citation>
</style>
XML
        ));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <names variable="author"><name name-as-sort-order="sideways"/></names>
    </layout>
  </citation>
            </style>
XML
        ));
    },
    'applies bounded csl et al element term formatting and delimiter policy' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'source-packet',
                'type' => 'report',
                'title' => 'Et Al Source Packet',
                'author' => [
                    ['family' => 'Cruz', 'given' => 'Ana Maria', 'non-dropping-particle' => 'de la'],
                    ['family' => 'Ng', 'given' => 'Nia'],
                    ['family' => 'Okafor', 'given' => 'Ola'],
                    ['family' => 'Smith', 'given' => 'Sam'],
                ],
                'issued' => ['date-parts' => [[2026]]],
            ],
            [
                'id' => 'editor-packet',
                'type' => 'book',
                'title' => 'Editor Et Al Packet',
                'editor' => [
                    ['family' => 'Curator', 'given' => 'Eli', 'suffix' => 'III', 'comma-suffix' => true],
                    ['family' => 'Reviewer', 'given' => 'Rae'],
                    ['literal' => 'Migration Desk'],
                ],
                'issued' => ['date-parts' => [[2025]]],
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Et Al Review Style</title>
    <id>https://example.test/styles/bounded-et-al-review</id>
    <updated>2026-06-05T09:00:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author editor" delimiter=", " et-al-min="3" et-al-use-first="1" delimiter-precedes-et-al="always">
          <name/>
          <et-al term="and others" prefix="[" suffix="]" text-case="uppercase"/>
        </names>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author editor" delimiter="; " et-al-min="3" et-al-use-first="2">
        <name initialize-with=". " name-as-sort-order="first" delimiter-precedes-et-al="after-inverted-name"/>
        <et-al term="and others" prefix="more: " strip-periods="true" text-case="capitalize-first"/>
      </names>
      <text variable="title"/>
    </layout>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $citationNames = $summary['citationRendering'][0]['children'][0]['nameRendering'] ?? [];
        $bibliographyNames = $summary['bibliographyRendering'][0]['nameRendering'] ?? [];
        $t->same('Bounded Et Al Review Style', $summary['title'] ?? null);
        $t->same('always', $citationNames['delimiterPrecedesEtAl'] ?? null);
        $t->same('and others', $citationNames['etAl']['term'] ?? null);
        $t->same('[', $citationNames['etAl']['prefix'] ?? null);
        $t->same(']', $citationNames['etAl']['suffix'] ?? null);
        $t->same('uppercase', $citationNames['etAl']['textCase'] ?? null);
        $t->same('after-inverted-name', $bibliographyNames['delimiterPrecedesEtAl'] ?? null);
        $t->same('more: ', $bibliographyNames['etAl']['prefix'] ?? null);
        $t->same(true, $bibliographyNames['etAl']['stripPeriods'] ?? null);

        $t->same('(de la Cruz, [AND OTHERS] 2026; Curator, [AND OTHERS] 2025)', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'source-packet', 'text' => '[@source-packet]']),
            new AstNode('citation', ['id' => 'editor-packet', 'text' => '[@editor-packet]']),
        ]));
        $t->same('de la Cruz, A. M.; N. Ng more: And others. Et Al Source Packet.', $processor->renderBibliographyEntry('source-packet'));
        $t->same('Curator, E., III; R. Reviewer more: And others. Editor Et Al Packet.', $processor->renderBibliographyEntry('editor-packet'));

        $afterInverted = $processor->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <names variable="author editor" et-al-min="3" et-al-use-first="1">
        <name/>
      </names>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author editor" delimiter="; " et-al-min="3" et-al-use-first="1">
        <name initialize-with=". " name-as-sort-order="first" delimiter-precedes-et-al="after-inverted-name"/>
        <et-al/>
      </names>
      <text variable="title"/>
    </layout>
  </bibliography>
</style>
XML
        );
        $t->same('de la Cruz, A. M.; et al. Et Al Source Packet.', $afterInverted->renderBibliographyEntry('source-packet'));

        $document = (new MarkdownReader())->read('Review cites @source-packet and [@editor-packet] for et-al handoff.');
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Review cites de la Cruz, [AND OTHERS] (2026) and (Curator, [AND OTHERS] 2025) for et-al handoff.</p>', $blocks);
        $t->contains('<dt>de la Cruz, [AND OTHERS] 2026</dt><dd>de la Cruz, A. M.; N. Ng more: And others. Et Al Source Packet.</dd>', $blocks);
        $t->contains('<dt>Curator, [AND OTHERS] 2025</dt><dd>Curator, E., III; R. Reviewer more: And others. Editor Et Al Packet.</dd>', $blocks);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation><layout><names variable="author"><name delimiter-precedes-et-al="sometimes"/></names></layout></citation>
</style>
XML
        ));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation><layout><names variable="author"><et-al term="everyone"/></names></layout></citation>
</style>
XML
        ));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation><layout><names variable="author"><et-al/><et-al/></names></layout></citation>
</style>
XML
        ));
    },
    'applies bounded csl layout text date group and names rendering elements' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'style-source',
                'type' => 'article-journal',
                'title' => 'Styled Source Packet',
                'container-title' => 'Import Review',
                'author' => [
                    ['family' => 'Cruz', 'given' => 'Ana Maria', 'non-dropping-particle' => 'de la'],
                    ['family' => 'Ng', 'given' => 'Nia'],
                ],
                'issued' => ['date-parts' => [[2026, 6, 5]]],
                'page' => '12-18',
                'DOI' => '10.5555/review',
                'URL' => 'https://example.test/styled-source',
                'accessed' => ['date-parts' => [[2026, 6, 6]]],
            ],
            [
                'id' => 'undated-style',
                'type' => 'report',
                'title' => 'Undated Styled Packet',
                'author' => [
                    ['family' => 'Archive Team'],
                ],
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Direct Rendering Elements Review Style</title>
    <id>https://example.test/styles/direct-rendering-elements</id>
    <updated>2026-06-05T01:40:00+00:00</updated>
  </info>
  <locale xml:lang="en-US">
    <terms>
      <term name="accessed">Visited</term>
      <term name="no date">undated</term>
    </terms>
  </locale>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" ">
        <names variable="author editor" delimiter=", ">
          <name/>
        </names>
        <date variable="issued">
          <date-part name="year"/>
        </date>
      </group>
    </layout>
  </citation>
  <bibliography hanging-indent="true">
    <layout prefix="{" suffix="}" delimiter=" ">
      <group delimiter=". " suffix=".">
        <names variable="author editor" delimiter="; ">
          <name initialize-with=". " name-as-sort-order="all"/>
        </names>
        <text variable="title"/>
        <text variable="container-title"/>
        <group delimiter=", ">
          <text variable="publisher"/>
          <date variable="issued">
            <date-part name="year"/>
          </date>
        </group>
        <text variable="page" prefix="pp. "/>
        <text variable="DOI" prefix="doi:"/>
        <text variable="URL" prefix="Available at "/>
        <group delimiter=" ">
          <text term="accessed"/>
          <date variable="accessed"/>
        </group>
      </group>
    </layout>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $t->same('Direct Rendering Elements Review Style', $summary['title'] ?? null);
        $t->same('group', $summary['citationRendering'][0]['type'] ?? null);
        $t->same('names', $summary['citationRendering'][0]['children'][0]['type'] ?? null);
        $t->same('date', $summary['citationRendering'][0]['children'][1]['type'] ?? null);
        $t->same('text', $summary['bibliographyRendering'][0]['children'][1]['type'] ?? null);
        $t->same('accessed', $summary['bibliographyRendering'][0]['children'][7]['children'][0]['term'] ?? null);

        $t->same('[de la Cruz and Ng 2026; Archive Team undated]', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'style-source', 'text' => '[@style-source]']),
            new AstNode('citation', ['id' => 'undated-style', 'text' => '[@undated-style]']),
        ]));
        $t->same('{de la Cruz, A. M.; Ng, N. Styled Source Packet. Import Review. 2026. pp. 12-18. doi:10.5555/review. Available at https://example.test/styled-source. Visited 2026-06-06.}', $processor->renderBibliographyEntry('style-source'));
        $t->same('{Archive Team. Undated Styled Packet.}', $processor->renderBibliographyEntry('undated-style'));

        $document = (new MarkdownReader())->read('Review cites [see @style-source, pp. 12-18; @undated-style].');
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $cluster = $processed->children[0]->children[1];
        $bibliography = $processed->children[2];
        $t->same('[see de la Cruz and Ng 2026, pp. 12-18; Archive Team undated]', $cluster->attr('rendered'));
        $t->same(true, $bibliography->attr('hangingIndent'));

        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains('Review cites [see de la Cruz and Ng 2026, pp. 12-18; Archive Team undated].', $markdown);
        $t->contains('de la Cruz and Ng 2026' . "\n" . ':   {de la Cruz, A. M.; Ng, N. Styled Source Packet. Import Review. 2026. pp. 12-18. doi:10.5555/review. Available at https://example.test/styled-source. Visited 2026-06-06.}', $markdown);

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Review cites [see de la Cruz and Ng 2026, pp. 12-18; Archive Team undated].</p>', $blocks);
        $t->contains('<dt>de la Cruz and Ng 2026</dt><dd>{de la Cruz, A. M.; Ng, N. Styled Source Packet. Import Review. 2026. pp. 12-18. doi:10.5555/review. Available at https://example.test/styled-source. Visited 2026-06-06.}</dd>', $blocks);
        $t->contains('<dt>Archive Team undated</dt><dd>{Archive Team. Undated Styled Packet.}</dd>', $blocks);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <text variable="title" term="title"/>
    </layout>
  </citation>
</style>
XML
        ));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <date variable="issued"><date-part name="season"/></date>
    </layout>
  </citation>
</style>
XML
        ));
    },
    'applies bounded csl text-case transforms for rendered text elements' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'case-source',
                'type' => 'report',
                'title' => 'migration review: source import and API',
                'short-title' => 'source guide',
                'title-addon' => 'import queue follow-up',
                'container-title' => 'journal of imported sources',
                'author' => [
                    ['family' => 'Cruz', 'given' => 'Ana Maria', 'non-dropping-particle' => 'de la'],
                ],
                'issued' => ['date-parts' => [[2026]]],
                'abstract' => 'Mixed CASE Abstract',
                'genre' => 'working paper',
                'language' => 'en-US',
            ],
            [
                'id' => 'non-english-title',
                'type' => 'book',
                'title' => 'manual de migración y datos',
                'short-title' => 'guía de datos',
                'author' => [
                    ['family' => 'García', 'given' => 'Gia'],
                ],
                'issued' => ['date-parts' => [[2025]]],
                'language' => 'es',
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Text Case Review Style</title>
    <id>https://example.test/styles/bounded-text-case-review</id>
    <updated>2026-06-05T04:45:00+00:00</updated>
  </info>
  <locale xml:lang="en-US">
    <terms>
      <term name="accessed">Accessed</term>
    </terms>
  </locale>
  <macro name="review-status">
    <group delimiter=": ">
      <text value="review status"/>
      <text variable="title-addon"/>
    </group>
  </macro>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title" text-case="title"/>
        <text variable="short-title" text-case="uppercase"/>
        <text value="review note" text-case="capitalize-first"/>
        <text term="accessed" text-case="lowercase"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout>
      <group delimiter=". " suffix=".">
        <text variable="title" text-case="sentence"/>
        <text variable="container-title" text-case="capitalize-all"/>
        <text variable="abstract" text-case="lowercase"/>
        <text variable="genre" text-case="uppercase"/>
        <text macro="review-status" text-case="title"/>
      </group>
    </layout>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $t->same('Bounded Text Case Review Style', $summary['title'] ?? null);
        $t->same('title', $summary['citationRendering'][0]['children'][0]['textCase'] ?? null);
        $t->same('uppercase', $summary['citationRendering'][0]['children'][1]['textCase'] ?? null);
        $t->same('lowercase', $summary['citationRendering'][0]['children'][3]['textCase'] ?? null);
        $t->same('title', $summary['bibliographyRendering'][0]['children'][4]['textCase'] ?? null);

        $t->same('(Migration Review: Source Import and API | SOURCE GUIDE | Review note | accessed; manual de migración y datos | GUÍA DE DATOS | Review note | accessed)', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'case-source', 'text' => '[@case-source]']),
            new AstNode('citation', ['id' => 'non-english-title', 'text' => '[@non-english-title]']),
        ]));
        $t->same('Migration review: source import and API. Journal Of Imported Sources. mixed case abstract. WORKING PAPER. Review Status: Import Queue Follow-Up.', $processor->renderBibliographyEntry('case-source'));
        $t->same('Manual de migración y datos.', $processor->renderBibliographyEntry('non-english-title'));

        $document = (new MarkdownReader())->read('Review cites [@case-source] and [@non-english-title] for title casing.');
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains('Review cites (Migration Review: Source Import and API | SOURCE GUIDE | Review note | accessed) and (manual de migración y datos | GUÍA DE DATOS | Review note | accessed) for title casing.', $markdown);
        $t->contains('de la Cruz 2026' . "\n" . ':   Migration review: source import and API. Journal Of Imported Sources. mixed case abstract. WORKING PAPER. Review Status: Import Queue Follow-Up.', $markdown);

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Review cites (Migration Review: Source Import and API | SOURCE GUIDE | Review note | accessed) and (manual de migración y datos | GUÍA DE DATOS | Review note | accessed) for title casing.</p>', $blocks);
        $t->contains('<dt>de la Cruz 2026</dt><dd>Migration review: source import and API. Journal Of Imported Sources. mixed case abstract. WORKING PAPER. Review Status: Import Queue Follow-Up.</dd>', $blocks);
        $t->contains('<dt>García 2025</dt><dd>Manual de migración y datos.</dd>', $blocks);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation><layout><text variable="title" text-case="sideways"/></layout></citation>
</style>
XML
        ));
    },
    'applies bounded csl quotes and strip periods rendering attributes' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'source-packet',
                'type' => 'report',
                'title' => 'source packet.',
                'container-title' => 'Journal of Review.',
                'author' => [
                    ['literal' => 'Review Desk'],
                ],
                'issued' => ['date-parts' => [[2026]]],
                'page' => '12-18',
            ],
            [
                'id' => 'issue-packet',
                'type' => 'report',
                'title' => 'issue packet.',
                'author' => [
                    ['literal' => 'Issue Desk'],
                ],
                'issued' => ['date-parts' => [[2025]]],
                'number' => '3',
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Quote Review Style</title>
    <id>https://example.test/styles/bounded-quote-review</id>
    <updated>2026-06-05T05:15:00+00:00</updated>
  </info>
  <locale xml:lang="en-US">
    <terms>
      <term name="open-quote">“</term>
      <term name="close-quote">”</term>
    </terms>
  </locale>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <text variable="title" quotes="true" strip-periods="true" text-case="title"/>
        <group delimiter=" ">
          <label variable="page" form="short" strip-periods="true"/>
          <number variable="page"/>
        </group>
        <group delimiter=" ">
          <label variable="number" form="short" strip-periods="true"/>
          <number variable="number"/>
        </group>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" | ">
      <text variable="title" prefix="title " suffix="." quotes="true" strip-periods="true" text-case="title"/>
      <group delimiter=" ">
        <label variable="page" form="short" strip-periods="true" plural="always"/>
        <number variable="page"/>
      </group>
      <group delimiter=" ">
        <label variable="number" form="short" strip-periods="true"/>
        <number variable="number"/>
      </group>
      <text variable="container-title" quotes="true"/>
    </layout>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $t->same('Bounded Quote Review Style', $summary['title'] ?? null);
        $t->same(true, $summary['citationRendering'][0]['children'][0]['quotes'] ?? null);
        $t->same(true, $summary['citationRendering'][0]['children'][0]['stripPeriods'] ?? null);
        $t->same(true, $summary['citationRendering'][0]['children'][1]['children'][0]['stripPeriods'] ?? null);
        $t->same(true, $summary['bibliographyRendering'][0]['quotes'] ?? null);
        $t->same(true, $summary['bibliographyRendering'][1]['children'][0]['stripPeriods'] ?? null);

        $t->same('(see “Source Packet” pp 12-18; “Issue Packet” no 3)', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'source-packet', 'text' => '[@source-packet]', 'prefix' => 'see']),
            new AstNode('citation', ['id' => 'issue-packet', 'text' => '[@issue-packet]']),
        ]));
        $t->same('title “Source Packet”. | pp 12-18 | “Journal of Review.”', $processor->renderBibliographyEntry('source-packet'));
        $t->same('title “Issue Packet”. | no 3', $processor->renderBibliographyEntry('issue-packet'));

        $document = (new MarkdownReader())->read('Review cites [see @source-packet; @issue-packet] for quoted source titles.');
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains('Review cites (see “Source Packet” pp 12-18; “Issue Packet” no 3) for quoted source titles.', $markdown);
        $t->contains('Review Desk 2026' . "\n" . ':   title “Source Packet”. \| pp 12-18 \| “Journal of Review.”', $markdown);

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Review cites (see “Source Packet” pp 12-18; “Issue Packet” no 3) for quoted source titles.</p>', $blocks);
        $t->contains('<dt>Review Desk 2026</dt><dd>title “Source Packet”. | pp 12-18 | “Journal of Review.”</dd>', $blocks);
        $t->contains('<dt>Issue Desk 2025</dt><dd>title “Issue Packet”. | no 3</dd>', $blocks);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation><layout><text variable="title" quotes="sometimes"/></layout></citation>
</style>
XML
        ));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation><layout><label variable="page" strip-periods="sometimes"/></layout></citation>
</style>
XML
        ));
    },
    'applies bounded csl punctuation in quote locale option' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'quote-source',
                'type' => 'article-journal',
                'title' => 'source packet',
                'container-title' => 'Review Journal.',
                'author' => [
                    ['literal' => 'Quote Desk'],
                ],
                'issued' => ['date-parts' => [[2026]]],
                'publisher' => 'Review Press',
            ],
            [
                'id' => 'suffix-source',
                'type' => 'report',
                'title' => 'suffix packet',
                'author' => [
                    ['family' => 'Ng', 'given' => 'Nia'],
                ],
                'issued' => ['date-parts' => [[2025]]],
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Punctuation In Quote Review Style</title>
    <id>https://example.test/styles/bounded-punctuation-in-quote-review</id>
    <updated>2026-06-05T11:08:28+00:00</updated>
  </info>
  <locale xml:lang="en-US">
    <style-options punctuation-in-quote="true"/>
    <terms>
      <term name="open-quote">“</term>
      <term name="close-quote">”</term>
    </terms>
  </locale>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=", ">
        <text variable="title" quotes="true" text-case="title"/>
        <text variable="publisher"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". ">
      <text variable="title" quotes="true" text-case="title"/>
      <text variable="container-title" quotes="true"/>
      <text value="reviewed" suffix="." quotes="true"/>
    </layout>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $t->same('Bounded Punctuation In Quote Review Style', $summary['title'] ?? null);
        $t->same(true, $summary['localeOptions']['punctuationInQuote'] ?? null);
        $t->same(true, $summary['citationRendering'][0]['children'][0]['quotes'] ?? null);

        $t->same('(“Source Packet,” Review Press; “Suffix Packet”)', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'quote-source', 'text' => '[@quote-source]']),
            new AstNode('citation', ['id' => 'suffix-source', 'text' => '[@suffix-source]']),
        ]));
        $t->same('“Source Packet.” “Review Journal.” “reviewed.”', $processor->renderBibliographyEntry('quote-source'));
        $t->same('“Suffix Packet.” “reviewed.”', $processor->renderBibliographyEntry('suffix-source'));

        $document = (new MarkdownReader())->read('Quoted source @quote-source and suffix [@suffix-source] keep localized punctuation.');
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains('Quoted source Quote Desk (2026) and suffix (“Suffix Packet”) keep localized punctuation.', $markdown);
        $t->contains('Quote Desk 2026' . "\n" . ':   “Source Packet.” “Review Journal.” “reviewed.”', $markdown);

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Quoted source Quote Desk (2026) and suffix (“Suffix Packet”) keep localized punctuation.</p>', $blocks);
        $t->contains('<dt>Quote Desk 2026</dt><dd>“Source Packet.” “Review Journal.” “reviewed.”</dd>', $blocks);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <locale>
    <style-options punctuation-in-quote="sometimes"/>
  </locale>
  <citation><layout><text variable="title"/></layout></citation>
</style>
XML
        ));
    },
    'applies bounded csl macro rendering references for citations and bibliography' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'macro-source',
                'type' => 'article-journal',
                'title' => 'Macro Source Packet',
                'container-title' => 'Import Review',
                'author' => [
                    ['family' => 'Cruz', 'given' => 'Ana Maria', 'non-dropping-particle' => 'de la'],
                    ['family' => 'Ng', 'given' => 'Nia'],
                ],
                'issued' => ['date-parts' => [[2026, 6, 5]]],
                'accessed' => ['date-parts' => [[2026, 6, 6]]],
                'URL' => 'https://example.test/macro-source',
            ],
            [
                'id' => 'title-only',
                'type' => 'webpage',
                'title' => 'Title Only Packet',
                'issued' => [],
                'URL' => 'https://example.test/title-only',
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Macro Rendering Review Style</title>
    <id>https://example.test/styles/macro-rendering-review</id>
    <updated>2026-06-05T02:10:00+00:00</updated>
  </info>
  <locale xml:lang="en-US">
    <terms>
      <term name="accessed">Retrieved</term>
      <term name="no date">undated</term>
    </terms>
  </locale>
  <macro name="citation-key">
    <group delimiter=" ">
      <names variable="author editor"/>
      <date variable="issued">
        <date-part name="year"/>
      </date>
    </group>
  </macro>
  <macro name="bibliography-entry">
    <group delimiter=". " suffix=".">
      <names variable="author editor"/>
      <text variable="title"/>
      <text variable="container-title"/>
      <text variable="URL"/>
      <group delimiter=" ">
        <text term="accessed"/>
        <date variable="accessed"/>
      </group>
    </group>
  </macro>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <text macro="citation-key"/>
    </layout>
  </citation>
  <bibliography hanging-indent="true">
    <layout>
      <text macro="bibliography-entry"/>
    </layout>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $t->same('Macro Rendering Review Style', $summary['title'] ?? null);
        $t->same('group', $summary['macros']['citation-key'][0]['type'] ?? null);
        $t->same('text', $summary['citationRendering'][0]['type'] ?? null);
        $t->same('citation-key', $summary['citationRendering'][0]['macro'] ?? null);
        $t->same('bibliography-entry', $summary['bibliographyRendering'][0]['macro'] ?? null);

        $t->same('(de la Cruz and Ng 2026; Title Only Packet undated)', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'macro-source', 'text' => '[@macro-source]']),
            new AstNode('citation', ['id' => 'title-only', 'text' => '[@title-only]']),
        ]));
        $t->same('de la Cruz, Ana Maria; Ng, Nia. Macro Source Packet. Import Review. https://example.test/macro-source. Retrieved 2026-06-06.', $processor->renderBibliographyEntry('macro-source'));
        $t->same('Title Only Packet. https://example.test/title-only.', $processor->renderBibliographyEntry('title-only'));

        $document = (new MarkdownReader())->read('Review cites [see @macro-source, pp. 12-18] and @title-only.');
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $cluster = $processed->children[0]->children[1];
        $titleOnly = $processed->children[0]->children[3];
        $bibliography = $processed->children[2];
        $t->same('(see de la Cruz and Ng 2026, pp. 12-18)', $cluster->attr('rendered'));
        $t->same('Title Only Packet (undated)', $titleOnly->attr('rendered'));
        $t->same(true, $bibliography->attr('hangingIndent'));

        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains('Review cites (see de la Cruz and Ng 2026, pp. 12-18) and Title Only Packet (undated).', $markdown);
        $t->contains('de la Cruz and Ng 2026' . "\n" . ':   de la Cruz, Ana Maria; Ng, Nia. Macro Source Packet. Import Review. https://example.test/macro-source. Retrieved 2026-06-06.', $markdown);

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Review cites (see de la Cruz and Ng 2026, pp. 12-18) and Title Only Packet (undated).</p>', $blocks);
        $t->contains('<dt>de la Cruz and Ng 2026</dt><dd>de la Cruz, Ana Maria; Ng, Nia. Macro Source Packet. Import Review. https://example.test/macro-source. Retrieved 2026-06-06.</dd>', $blocks);
        $t->contains('<dt>Title Only Packet undated</dt><dd>Title Only Packet. https://example.test/title-only.</dd>', $blocks);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation><layout><text macro="missing-macro"/></layout></citation>
</style>
XML
        ));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <macro name="loop"><text macro="loop"/></macro>
  <citation><layout><text macro="loop"/></layout></citation>
</style>
XML
        ));
    },
    'applies bounded csl choose conditionals for variable and type branches' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'article-doi',
                'type' => 'article-journal',
                'title' => 'Conditional Article Packet',
                'container-title' => 'Import Review',
                'author' => [
                    ['family' => 'Cruz', 'given' => 'Ana Maria', 'non-dropping-particle' => 'de la'],
                ],
                'issued' => ['date-parts' => [[2026, 6, 5]]],
                'DOI' => '10.5555/conditional',
            ],
            [
                'id' => 'web-url',
                'type' => 'webpage',
                'title' => 'Conditional Web Packet',
                'author' => [
                    ['literal' => 'Archive Team'],
                ],
                'issued' => ['date-parts' => [[2024]]],
                'URL' => 'https://example.test/conditional-web',
            ],
            [
                'id' => 'local-report',
                'type' => 'report',
                'title' => 'Local Report Packet',
                'author' => [
                    ['literal' => 'Migration Committee'],
                ],
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Conditional Review Style</title>
    <id>https://example.test/styles/conditional-review</id>
    <updated>2026-06-05T02:40:00+00:00</updated>
  </info>
  <locale xml:lang="en-US">
    <terms>
      <term name="no date">undated</term>
    </terms>
  </locale>
  <macro name="source-locator">
    <choose>
      <if variable="DOI" match="any">
        <text variable="DOI" prefix="doi:"/>
      </if>
      <else-if variable="DOI URL" match="none">
        <text value="no stable source locator"/>
      </else-if>
      <else-if variable="URL" match="any">
        <text variable="URL" prefix="available:"/>
      </else-if>
      <else>
        <text value="unclassified source locator"/>
      </else>
    </choose>
  </macro>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=": ">
        <names variable="author editor"/>
        <choose>
          <if type="article-journal" match="any">
            <group delimiter=" ">
              <text value="article"/>
              <date variable="issued">
                <date-part name="year"/>
              </date>
            </group>
          </if>
          <else-if variable="issued" match="any">
            <date variable="issued">
              <date-part name="year"/>
            </date>
          </else-if>
          <else>
            <text term="no date"/>
          </else>
        </choose>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout>
      <group delimiter=". " suffix=".">
        <names variable="author editor"/>
        <text variable="title"/>
        <text macro="source-locator"/>
      </group>
    </layout>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $t->same('choose', $summary['macros']['source-locator'][0]['type'] ?? null);
        $t->same(['DOI'], $summary['macros']['source-locator'][0]['branches'][0]['variables'] ?? null);
        $t->same('none', $summary['macros']['source-locator'][0]['branches'][1]['match'] ?? null);
        $t->same(['article-journal'], $summary['citationRendering'][0]['children'][1]['branches'][0]['types'] ?? null);
        $t->same(['issued'], $summary['citationRendering'][0]['children'][1]['branches'][1]['variables'] ?? null);
        $t->same('any', $summary['citationRendering'][0]['children'][1]['branches'][1]['match'] ?? null);

        $t->same('(de la Cruz: article 2026; Archive Team: 2024; Migration Committee: undated)', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'article-doi', 'text' => '[@article-doi]']),
            new AstNode('citation', ['id' => 'web-url', 'text' => '[@web-url]']),
            new AstNode('citation', ['id' => 'local-report', 'text' => '[@local-report]']),
        ]));
        $t->same('de la Cruz, Ana Maria. Conditional Article Packet. doi:10.5555/conditional.', $processor->renderBibliographyEntry('article-doi'));
        $t->same('Archive Team. Conditional Web Packet. available:https://example.test/conditional-web.', $processor->renderBibliographyEntry('web-url'));
        $t->same('Migration Committee. Local Report Packet. no stable source locator.', $processor->renderBibliographyEntry('local-report'));

        $document = (new MarkdownReader())->read('Review cites [@article-doi; @web-url; @local-report].');
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains('Review cites (de la Cruz: article 2026; Archive Team: 2024; Migration Committee: undated).', $markdown);
        $t->contains('Migration Committee undated' . "\n" . ':   Migration Committee. Local Report Packet. no stable source locator.', $markdown);

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Review cites (de la Cruz: article 2026; Archive Team: 2024; Migration Committee: undated).</p>', $blocks);
        $t->contains('<dt>Migration Committee undated</dt><dd>Migration Committee. Local Report Packet. no stable source locator.</dd>', $blocks);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <choose><if match="sometimes" variable="title"><text variable="title"/></if></choose>
    </layout>
  </citation>
</style>
XML
        ));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <choose><if><text variable="title"/></if></choose>
    </layout>
  </citation>
</style>
XML
        ));
    },
    'applies bounded csl locator and page label rendering' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'locator-source',
                'type' => 'article-journal',
                'title' => 'Locator Source Packet',
                'container-title' => 'Import Review',
                'author' => [
                    ['family' => 'Cruz', 'given' => 'Ana Maria', 'non-dropping-particle' => 'de la'],
                ],
                'issued' => ['date-parts' => [[2026, 6, 5]]],
                'page' => '12-18',
            ],
            [
                'id' => 'chapter-source',
                'type' => 'chapter',
                'title' => 'Manual Chapter',
                'issued' => ['date-parts' => [[2024]]],
                'page' => '99',
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Locator Label Review Style</title>
    <id>https://example.test/styles/locator-label-review</id>
    <updated>2026-06-05T03:20:00+00:00</updated>
  </info>
  <macro name="citation-key">
    <group delimiter=" ">
      <names variable="author editor"/>
      <date variable="issued">
        <date-part name="year"/>
      </date>
    </group>
  </macro>
  <macro name="locator">
    <choose>
      <if variable="locator" match="any">
        <group delimiter=" ">
          <label variable="locator" form="short"/>
          <text variable="locator"/>
        </group>
      </if>
    </choose>
  </macro>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=", ">
        <text macro="citation-key"/>
        <text macro="locator"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout>
      <group delimiter=". " suffix=".">
        <names variable="author editor"/>
        <text variable="title"/>
        <group delimiter=" ">
          <label variable="page" form="short"/>
          <text variable="page"/>
        </group>
      </group>
    </layout>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $t->same('label', $summary['macros']['locator'][0]['branches'][0]['children'][0]['children'][0]['type'] ?? null);
        $t->same('locator', $summary['macros']['locator'][0]['branches'][0]['children'][0]['children'][0]['variable'] ?? null);
        $t->same('short', $summary['macros']['locator'][0]['branches'][0]['children'][0]['children'][0]['form'] ?? null);
        $t->same('page', $summary['bibliographyRendering'][0]['children'][2]['children'][0]['variable'] ?? null);

        $document = (new MarkdownReader())->read('Review cites [see @locator-source, p. 7; @chapter-source, chap. 2; @locator-source, sec. 4-5; @locator-source, {ii, A-D}].');
        $cluster = $document->children[0]->children[1];
        $t->same('citation_group', $cluster->type);
        $t->same('page', $cluster->children[0]->attr('locatorLabel'));
        $t->same('7', $cluster->children[0]->attr('locatorValue'));
        $t->same('chapter', $cluster->children[1]->attr('locatorLabel'));
        $t->same('2', $cluster->children[1]->attr('locatorValue'));
        $t->same('section', $cluster->children[2]->attr('locatorLabel'));
        $t->same('4-5', $cluster->children[2]->attr('locatorValue'));
        $t->same('page', $cluster->children[3]->attr('locatorLabel'));
        $t->same('ii, A-D', $cluster->children[3]->attr('locatorValue'));

        $processed = $processor->appendBibliography($document, 'Works Cited');
        $processedCluster = $processed->children[0]->children[1];
        $t->same('(see de la Cruz 2026, p. 7; Manual Chapter 2024, chap. 2; de la Cruz 2026, secs. 4-5; de la Cruz 2026, p. ii, A-D)', $processedCluster->attr('rendered'));
        $t->same('de la Cruz 2026', $processed->children[2]->children[0]->children[0]->attr('text'));
        $t->same('Manual Chapter 2024', $processed->children[2]->children[1]->children[0]->attr('text'));
        $t->same('de la Cruz, Ana Maria. Locator Source Packet. pp. 12-18.', $processor->renderBibliographyEntry('locator-source'));
        $t->same('Manual Chapter. p. 99.', $processor->renderBibliographyEntry('chapter-source'));

        $symbolStyle = $processor->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <label variable="locator" form="symbol"/>
        <text variable="locator"/>
      </group>
    </layout>
  </citation>
  <bibliography><layout/></bibliography>
</style>
XML
        );
        $t->same("(\u{00A7}\u{00A7} 4-5)", $symbolStyle->renderCitationCluster([
            new AstNode('citation', ['id' => 'locator-source', 'text' => '[@locator-source]', 'locator' => 'sec. 4-5']),
        ]));

        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains('Review cites (see de la Cruz 2026, p. 7; Manual Chapter 2024, chap. 2; de la Cruz 2026, secs. 4-5; de la Cruz 2026, p. ii, A-D).', $markdown);
        $t->contains('de la Cruz 2026' . "\n" . ':   de la Cruz, Ana Maria. Locator Source Packet. pp. 12-18.', $markdown);

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Review cites (see de la Cruz 2026, p. 7; Manual Chapter 2024, chap. 2; de la Cruz 2026, secs. 4-5; de la Cruz 2026, p. ii, A-D).</p>', $blocks);
        $t->contains('<dt>de la Cruz 2026</dt><dd>de la Cruz, Ana Maria. Locator Source Packet. pp. 12-18.</dd>', $blocks);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation><layout><label variable="title"/></layout></citation>
</style>
XML
        ));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation><layout><label variable="locator" form="verb"/></layout></citation>
</style>
XML
        ));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation><layout><label variable="locator" plural="sometimes"/></layout></citation>
            </style>
XML
        ));
    },
    'applies bounded csl number rendering forms for page issue and edition variables' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'numbered-source',
                'type' => 'report',
                'title' => 'Numbered Review Packet',
                'author' => [
                    ['family' => 'Cruz', 'given' => 'Ana Maria', 'non-dropping-particle' => 'de la'],
                ],
                'issued' => ['date-parts' => [[2026]]],
                'number' => '2 - 4',
                'page' => '12 , 18&20',
                'edition' => '3',
            ],
            [
                'id' => 'special-number',
                'type' => 'report',
                'title' => 'Special Number Packet',
                'author' => [
                    ['literal' => 'Archive Team'],
                ],
                'issued' => ['date-parts' => [[2025]]],
                'number' => 'Appendix 2E',
                'page' => 'A2',
                'edition' => 'Eleventh',
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Number Review Style</title>
    <id>https://example.test/styles/bounded-number-review</id>
    <updated>2026-06-05T04:20:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author editor"/>
        <group delimiter=" " prefix="[" suffix="]">
          <label variable="number" form="short"/>
          <number variable="number" form="roman"/>
        </group>
        <group delimiter=" " prefix="edition ">
          <number variable="edition" form="long-ordinal"/>
        </group>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout>
      <group delimiter=". " suffix=".">
        <text variable="title"/>
        <group delimiter=" ">
          <label variable="page" form="short"/>
          <number variable="page"/>
        </group>
        <group delimiter=" ">
          <label variable="edition"/>
          <number variable="edition" form="long-ordinal"/>
        </group>
        <group delimiter=" ">
          <text value="ordinal"/>
          <number variable="number" form="ordinal"/>
        </group>
      </group>
    </layout>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $t->same('Bounded Number Review Style', $summary['title'] ?? null);
        $t->same('number', $summary['citationRendering'][0]['children'][1]['children'][1]['type'] ?? null);
        $t->same('roman', $summary['citationRendering'][0]['children'][1]['children'][1]['form'] ?? null);
        $t->same('long-ordinal', $summary['citationRendering'][0]['children'][2]['children'][0]['form'] ?? null);
        $t->same('number', $summary['bibliographyRendering'][0]['children'][1]['children'][1]['type'] ?? null);
        $t->same('numeric', $summary['bibliographyRendering'][0]['children'][1]['children'][1]['form'] ?? null);

        $t->same('(de la Cruz [nos. ii-iv] edition third; Archive Team [no. Appendix 2E] edition Eleventh)', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'numbered-source', 'text' => '[@numbered-source]']),
            new AstNode('citation', ['id' => 'special-number', 'text' => '[@special-number]']),
        ]));
        $t->same('Numbered Review Packet. pp. 12, 18 & 20. edition third. ordinal 2nd-4th.', $processor->renderBibliographyEntry('numbered-source'));
        $t->same('Special Number Packet. p. A2. edition Eleventh. ordinal Appendix 2E.', $processor->renderBibliographyEntry('special-number'));

        $ordinalLocale = $processor->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <locale>
    <terms>
      <term name="ordinal">º</term>
      <term name="ordinal-01">er</term>
      <term name="long-ordinal-03">third local</term>
    </terms>
  </locale>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" ">
        <number variable="edition" form="long-ordinal"/>
        <number variable="number" form="ordinal"/>
      </group>
    </layout>
  </citation>
</style>
XML
        );
        $t->same('[third local 2º-4º]', $ordinalLocale->renderCitationCluster([
            new AstNode('citation', ['id' => 'numbered-source', 'text' => '[@numbered-source]']),
        ]));

        $document = (new MarkdownReader())->read('Review cites [@numbered-source; @special-number] for numbered source packets.');
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Review cites (de la Cruz [nos. ii-iv] edition third; Archive Team [no. Appendix 2E] edition Eleventh) for numbered source packets.</p>', $blocks);
        $t->contains('<dt>de la Cruz 2026</dt><dd>Numbered Review Packet. pp. 12, 18 &amp; 20. edition third. ordinal 2nd-4th.</dd>', $blocks);
        $t->contains('<dt>Archive Team 2025</dt><dd>Special Number Packet. p. A2. edition Eleventh. ordinal Appendix 2E.</dd>', $blocks);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation><layout><number variable="title"/></layout></citation>
</style>
XML
        ));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation><layout><number variable="number" form="alphabetic"/></layout></citation>
</style>
XML
        ));
    },
    'assigns bounded csl citation-number variables from sorted bibliography order' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'zeta',
                'type' => 'report',
                'title' => 'Zeta Packet',
                'author' => [
                    ['family' => 'Zeta', 'given' => 'Zoe'],
                ],
                'issued' => ['date-parts' => [[2026]]],
            ],
            [
                'id' => 'alpha',
                'type' => 'report',
                'title' => 'Alpha Packet',
                'author' => [
                    ['family' => 'Alpha', 'given' => 'Ava'],
                ],
                'issued' => ['date-parts' => [[2024]]],
            ],
            [
                'id' => 'middle',
                'type' => 'report',
                'title' => 'Middle Packet',
                'author' => [
                    ['family' => 'Middle', 'given' => 'Mia'],
                ],
                'issued' => ['date-parts' => [[2025]]],
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Citation Number Review Style</title>
    <id>https://example.test/styles/bounded-citation-number-review</id>
    <updated>2026-06-05T09:29:29+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter=", ">
      <number variable="citation-number"/>
    </layout>
  </citation>
  <bibliography second-field-align="flush">
    <sort>
      <key variable="author"/>
    </sort>
    <layout delimiter=" ">
      <number variable="citation-number" display="left-margin" prefix="[" suffix="]"/>
      <group display="right-inline" delimiter=". " suffix=".">
        <names variable="author">
          <name initialize-with=". " name-as-sort-order="all"/>
        </names>
        <text variable="title"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $t->same('citation-number', $summary['citationRendering'][0]['variable'] ?? null);
        $t->same('citation-number', $summary['bibliographyRendering'][0]['variable'] ?? null);
        $t->same('author', $summary['bibliographySort'][0]['variable'] ?? null);
        $t->same('[3] Zeta, Z. Zeta Packet. 2026.', $processor->renderBibliographyEntry('zeta'));

        $document = (new MarkdownReader())->read('Review cites [@zeta; @alpha, p. 9; @middle] for source numbering.');
        $processed = $processor->appendBibliography($document, 'Numbered Sources');
        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Review cites [3, 1, p. 9, 2] for source numbering.</p>', $blocks);
        $t->contains('<dt>Alpha 2024</dt><dd><div class="csl-entry"><div class="csl-left-margin">[1]</div><div class="csl-right-inline">Alpha, A. Alpha Packet. 2024.</div></div></dd>', $blocks);
        $t->contains('<dt>Middle 2025</dt><dd><div class="csl-entry"><div class="csl-left-margin">[2]</div><div class="csl-right-inline">Middle, M. Middle Packet. 2025.</div></div></dd>', $blocks);
        $t->contains('<dt>Zeta 2026</dt><dd><div class="csl-entry"><div class="csl-left-margin">[3]</div><div class="csl-right-inline">Zeta, Z. Zeta Packet. 2026.</div></div></dd>', $blocks);

        $numbered = $processor->apply($document);
        $group = $numbered->children[0]->children[1] ?? null;
        $t->same('citation_group', $group instanceof AstNode ? $group->type : null);
        $t->same('3', $group instanceof AstNode ? ($group->children[0]->attr('cslItem')['citationNumber'] ?? null) : null);
        $t->same('1', $group instanceof AstNode ? ($group->children[1]->attr('cslItem')['citationNumber'] ?? null) : null);
        $t->same('2', $group instanceof AstNode ? ($group->children[2]->attr('cslItem')['citationNumber'] ?? null) : null);
    },
    'collapses bounded csl citation-number ranges for numeric styles' => static function (TestRunner $t): void {
        $items = [
            [
                'id' => 'alpha',
                'type' => 'report',
                'title' => 'Alpha Packet',
                'author' => [
                    ['family' => 'Alpha', 'given' => 'Ava'],
                ],
                'issued' => ['date-parts' => [[2024]]],
            ],
            [
                'id' => 'beta',
                'type' => 'report',
                'title' => 'Beta Packet',
                'author' => [
                    ['family' => 'Beta', 'given' => 'Bea'],
                ],
                'issued' => ['date-parts' => [[2025]]],
            ],
            [
                'id' => 'gamma',
                'type' => 'report',
                'title' => 'Gamma Packet',
                'author' => [
                    ['family' => 'Gamma', 'given' => 'Gia'],
                ],
                'issued' => ['date-parts' => [[2026]]],
            ],
            [
                'id' => 'delta',
                'type' => 'report',
                'title' => 'Delta Packet',
                'author' => [
                    ['family' => 'Delta', 'given' => 'Dee'],
                ],
                'issued' => ['date-parts' => [[2027]]],
            ],
            [
                'id' => 'epsilon',
                'type' => 'report',
                'title' => 'Epsilon Packet',
                'author' => [
                    ['family' => 'Epsilon', 'given' => 'Eli'],
                ],
                'issued' => ['date-parts' => [[2028]]],
            ],
        ];
        $processor = CitationCslProcessor::fromItems($items)->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Citation Number Collapse Style</title>
    <id>https://example.test/styles/bounded-citation-number-collapse</id>
    <updated>2026-06-05T11:41:47+00:00</updated>
  </info>
  <citation collapse="citation-number">
    <layout prefix="[" suffix="]" delimiter=", ">
      <number variable="citation-number"/>
    </layout>
  </citation>
  <bibliography second-field-align="flush">
    <layout delimiter=" ">
      <number variable="citation-number" display="left-margin" prefix="[" suffix="]"/>
      <group display="right-inline" delimiter=". " suffix=".">
        <names variable="author">
          <name initialize-with=". " name-as-sort-order="all"/>
        </names>
        <text variable="title"/>
      </group>
    </layout>
  </bibliography>
</style>
XML
        );
        $dash = "\u{2013}";

        $summary = $processor->cslStyleSummary();
        $t->same('citation-number', $summary['citationOptions']['collapse'] ?? null);
        $t->same('citation-number', $summary['citationRendering'][0]['variable'] ?? null);
        $t->same("[1{$dash}5]", $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'alpha', 'text' => '[@alpha]']),
            new AstNode('citation', ['id' => 'beta', 'text' => '[@beta]']),
            new AstNode('citation', ['id' => 'gamma', 'text' => '[@gamma]']),
            new AstNode('citation', ['id' => 'delta', 'text' => '[@delta]']),
            new AstNode('citation', ['id' => 'epsilon', 'text' => '[@epsilon]']),
        ]));
        $t->same('[5, 4, 3]', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'epsilon', 'text' => '[@epsilon]', 'cslCitationNumber' => '5']),
            new AstNode('citation', ['id' => 'delta', 'text' => '[@delta]', 'cslCitationNumber' => '4']),
            new AstNode('citation', ['id' => 'gamma', 'text' => '[@gamma]', 'cslCitationNumber' => '3']),
        ]));
        $t->same("[1{$dash}3, 4, p. 9, 5]", $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'alpha', 'text' => '[@alpha]']),
            new AstNode('citation', ['id' => 'beta', 'text' => '[@beta]']),
            new AstNode('citation', ['id' => 'gamma', 'text' => '[@gamma]']),
            new AstNode('citation', ['id' => 'delta', 'text' => '[@delta, p. 9]', 'locator' => 'p. 9']),
            new AstNode('citation', ['id' => 'epsilon', 'text' => '[@epsilon]']),
        ]));

        $decoratedStyle = CitationCslProcessor::fromItems($items)->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation collapse="citation-number">
    <layout prefix="[" suffix="]" delimiter=", ">
      <group delimiter=" ">
        <text value="source"/>
        <number variable="citation-number"/>
      </group>
    </layout>
  </citation>
</style>
XML
        );
        $t->same('[source 1, source 2, source 3]', $decoratedStyle->renderCitationCluster([
            new AstNode('citation', ['id' => 'alpha', 'text' => '[@alpha]']),
            new AstNode('citation', ['id' => 'beta', 'text' => '[@beta]']),
            new AstNode('citation', ['id' => 'gamma', 'text' => '[@gamma]']),
        ]));

        $document = (new MarkdownReader())->read(
            'Collapsed range cites [@alpha; @beta; @gamma; @delta; @epsilon].'
            . "\n\n" . 'Descending review order keeps source order [@epsilon; @delta; @gamma].'
            . "\n\n" . 'Locator boundary cites [@alpha; @beta; @gamma; @delta, p. 9; @epsilon].'
        );
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Numbered Sources'));
        $t->contains("<p>Collapsed range cites [1{$dash}5].</p>", $blocks);
        $t->contains('<p>Descending review order keeps source order [5, 4, 3].</p>', $blocks);
        $t->contains("<p>Locator boundary cites [1{$dash}3, 4, p. 9, 5].</p>", $blocks);
        $t->contains('<dt>Alpha 2024</dt><dd><div class="csl-entry"><div class="csl-left-margin">[1]</div><div class="csl-right-inline">Alpha, A. Alpha Packet.</div></div></dd>', $blocks);
        $t->contains('<dt>Epsilon 2028</dt><dd><div class="csl-entry"><div class="csl-left-margin">[5]</div><div class="csl-right-inline">Epsilon, E. Epsilon Packet.</div></div></dd>', $blocks);
    },
    'applies bounded csl citation position conditionals for repeated cites' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'source-a',
                'type' => 'article-journal',
                'title' => 'Position Source A',
                'author' => [
                    ['family' => 'Cruz', 'given' => 'Ana Maria', 'non-dropping-particle' => 'de la'],
                ],
                'issued' => ['date-parts' => [[2026]]],
            ],
            [
                'id' => 'source-b',
                'type' => 'report',
                'title' => 'Position Source B',
                'author' => [
                    ['family' => 'Ng', 'given' => 'Nia'],
                ],
                'issued' => ['date-parts' => [[2025]]],
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Position Review Style</title>
    <id>https://example.test/styles/bounded-position-review</id>
    <updated>2026-06-05T03:45:00+00:00</updated>
  </info>
  <macro name="citation-key">
    <group delimiter=" ">
      <names variable="author editor"/>
      <date variable="issued">
        <date-part name="year"/>
      </date>
    </group>
  </macro>
  <macro name="pinpoint">
    <choose>
      <if variable="locator" match="any">
        <group delimiter=" ">
          <label variable="locator" form="short"/>
          <text variable="locator"/>
        </group>
      </if>
    </choose>
  </macro>
  <macro name="normal-cite">
    <group delimiter=", ">
      <text macro="citation-key"/>
      <text macro="pinpoint"/>
    </group>
  </macro>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <choose>
        <if position="ibid-with-locator" match="any">
          <group delimiter=", ">
            <text value="ibid"/>
            <text macro="pinpoint"/>
          </group>
        </if>
        <else-if position="ibid" match="any">
          <text value="ibid"/>
        </else-if>
        <else-if position="subsequent" match="any">
          <group delimiter=", ">
            <text value="subsequent"/>
            <text macro="normal-cite"/>
          </group>
        </else-if>
        <else>
          <text macro="normal-cite"/>
        </else>
      </choose>
    </layout>
  </citation>
  <bibliography>
    <layout>
      <choose>
        <if position="first" match="any">
          <text value="position leaked into bibliography"/>
        </if>
        <else>
          <group delimiter=". " suffix=".">
            <names variable="author editor"/>
            <text variable="title"/>
          </group>
        </else>
      </choose>
    </layout>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $t->same('Bounded Position Review Style', $summary['title'] ?? null);
        $t->same(['ibid-with-locator'], $summary['citationRendering'][0]['branches'][0]['positions'] ?? null);
        $t->same(['ibid'], $summary['citationRendering'][0]['branches'][1]['positions'] ?? null);
        $t->same(['subsequent'], $summary['citationRendering'][0]['branches'][2]['positions'] ?? null);
        $t->same(['first'], $summary['bibliographyRendering'][0]['branches'][0]['positions'] ?? null);

        $document = (new MarkdownReader())->read(
            'First [@source-a, p. 1].'
            . "\n\n" . 'Same locator [@source-a, p. 1].'
            . "\n\n" . 'Different locator [@source-a, p. 2].'
            . "\n\n" . 'No locator [@source-a].'
            . "\n\n" . 'Within cluster [@source-b; @source-b, p. 5].'
            . "\n\n" . 'After multi [@source-b].'
            . "\n\n" . 'After single [@source-b].'
        );
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $first = $processed->children[0]->children[1];
        $sameLocator = $processed->children[1]->children[1];
        $differentLocator = $processed->children[2]->children[1];
        $noLocator = $processed->children[3]->children[1];
        $withinCluster = $processed->children[4]->children[1];
        $afterMulti = $processed->children[5]->children[1];
        $afterSingle = $processed->children[6]->children[1];

        $t->same('first', $first->attr('cslPosition'));
        $t->same(['first'], $first->attr('cslPositionTests'));
        $t->same('ibid', $sameLocator->attr('cslPosition'));
        $t->same(['subsequent', 'ibid'], $sameLocator->attr('cslPositionTests'));
        $t->same('ibid-with-locator', $differentLocator->attr('cslPosition'));
        $t->same(['subsequent', 'ibid', 'ibid-with-locator'], $differentLocator->attr('cslPositionTests'));
        $t->same('subsequent', $noLocator->attr('cslPosition'));
        $t->same('first', $withinCluster->children[0]->attr('cslPosition'));
        $t->same('ibid-with-locator', $withinCluster->children[1]->attr('cslPosition'));
        $t->same('subsequent', $afterMulti->attr('cslPosition'));
        $t->same('ibid', $afterSingle->attr('cslPosition'));

        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains('First (de la Cruz 2026, p. 1).', $markdown);
        $t->contains('Same locator (ibid).', $markdown);
        $t->contains('Different locator (ibid, p. 2).', $markdown);
        $t->contains('No locator (subsequent, de la Cruz 2026).', $markdown);
        $t->contains('Within cluster (Ng 2025; ibid, p. 5).', $markdown);
        $t->contains('After multi (subsequent, Ng 2025).', $markdown);
        $t->contains('After single (ibid).', $markdown);
        $t->contains('de la Cruz 2026' . "\n" . ':   de la Cruz, Ana Maria. Position Source A.', $markdown);
        $t->contains('Ng 2025' . "\n" . ':   Ng, Nia. Position Source B.', $markdown);

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Different locator (ibid, p. 2).</p>', $blocks);
        $t->contains('<p>Within cluster (Ng 2025; ibid, p. 5).</p>', $blocks);
        $t->contains('<p>After multi (subsequent, Ng 2025).</p>', $blocks);
        $t->contains('<dt>de la Cruz 2026</dt><dd>de la Cruz, Ana Maria. Position Source A.</dd>', $blocks);

        $t->same('(de la Cruz 2026, p. 1; ibid, p. 2)', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'source-a', 'text' => '[@source-a]', 'locator' => 'p. 1']),
            new AstNode('citation', ['id' => 'source-a', 'text' => '[@source-a]', 'locator' => 'p. 2']),
        ]));
        $t->same('de la Cruz, Ana Maria. Position Source A.', $processor->renderBibliographyEntry('source-a'));

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <choose><if position="later"><text value="bad"/></if></choose>
    </layout>
  </citation>
</style>
XML
        ));
    },
    'applies bounded csl near-note position conditionals for note citations' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'source-a',
                'type' => 'article-journal',
                'title' => 'Near Note Source A',
                'author' => [
                    ['family' => 'Cruz', 'given' => 'Ana Maria', 'non-dropping-particle' => 'de la'],
                ],
                'issued' => ['date-parts' => [[2026]]],
            ],
            [
                'id' => 'source-b',
                'type' => 'report',
                'title' => 'Near Note Source B',
                'author' => [
                    ['family' => 'Ng', 'given' => 'Nia'],
                ],
                'issued' => ['date-parts' => [[2025]]],
            ],
            [
                'id' => 'source-c',
                'type' => 'webpage',
                'title' => 'Spacer Source C',
                'author' => [
                    ['literal' => 'Archive Desk'],
                ],
                'issued' => ['date-parts' => [[2024]]],
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="note" default-locale="en-US">
  <info>
    <title>Bounded Near Note Review Style</title>
    <id>https://example.test/styles/bounded-near-note-review</id>
    <updated>2026-06-05T12:48:25+00:00</updated>
  </info>
  <macro name="citation-key">
    <group delimiter=" ">
      <names variable="author editor"/>
      <date variable="issued">
        <date-part name="year"/>
      </date>
    </group>
  </macro>
  <citation near-note-distance="2">
    <layout prefix="(" suffix=")" delimiter="; ">
      <choose>
        <if position="ibid" match="any">
          <text value="ibid"/>
        </if>
        <else-if position="near-note" match="any">
          <group delimiter=" ">
            <text value="near-note"/>
            <text macro="citation-key"/>
          </group>
        </else-if>
        <else-if position="subsequent" match="any">
          <group delimiter=" ">
            <text value="subsequent"/>
            <text macro="citation-key"/>
          </group>
        </else-if>
        <else>
          <text macro="citation-key"/>
        </else>
      </choose>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author"/>
      <text variable="title"/>
    </layout>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $t->same('note', $summary['class'] ?? null);
        $t->same(2, $summary['citationOptions']['nearNoteDistance'] ?? null);
        $t->same(['near-note'], $summary['citationRendering'][0]['branches'][1]['positions'] ?? null);

        $document = (new MarkdownReader())->read(
            'Initial source note.[^a]'
            . "\n\n" . 'Bridge note.[^b]'
            . "\n\n" . 'Nearby source note.[^c]'
            . "\n\n" . 'Nearby bridge note.[^d]'
            . "\n\n" . 'Spacer note.[^e]'
            . "\n\n" . 'Far source note.[^f]'
            . "\n\n" . '[^a]: Initial footnote cites [@source-a].'
            . "\n\n" . '[^b]: Bridge footnote cites [@source-b].'
            . "\n\n" . '[^c]: Nearby footnote cites [@source-a].'
            . "\n\n" . '[^d]: Nearby bridge footnote cites [@source-b].'
            . "\n\n" . '[^e]: Spacer footnote cites [@source-c].'
            . "\n\n" . '[^f]: Far footnote cites [@source-a].'
        );
        $processed = $processor->appendBibliography($document, 'Works Cited');

        $citations = [];
        $collectCitations = static function (AstNode $node) use (&$collectCitations, &$citations): void {
            if ($node->type === 'citation') {
                $citations[] = $node;
            }

            foreach ($node->children as $child) {
                $collectCitations($child);
            }
        };
        $collectCitations($processed);

        $t->same(6, count($citations));
        $t->same(['first'], $citations[0]->attr('cslPositionTests'));
        $t->same(['first'], $citations[1]->attr('cslPositionTests'));
        $t->same(['subsequent', 'near-note'], $citations[2]->attr('cslPositionTests'));
        $t->same(['subsequent', 'near-note'], $citations[3]->attr('cslPositionTests'));
        $t->same(['first'], $citations[4]->attr('cslPositionTests'));
        $t->same(['subsequent'], $citations[5]->attr('cslPositionTests'));

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<li id="fn-1"><p>Initial footnote cites (de la Cruz 2026).</p>', $blocks);
        $t->contains('<li id="fn-3"><p>Nearby footnote cites (near-note de la Cruz 2026).</p>', $blocks);
        $t->contains('<li id="fn-4"><p>Nearby bridge footnote cites (near-note Ng 2025).</p>', $blocks);
        $t->contains('<li id="fn-6"><p>Far footnote cites (subsequent de la Cruz 2026).</p>', $blocks);
        $t->contains('<dt>de la Cruz 2026</dt><dd>de la Cruz, Ana Maria. Near Note Source A.</dd>', $blocks);

        $explicit = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('citation', ['id' => 'source-a', 'text' => '[@source-a]', 'cslNoteIndex' => 10]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('citation', ['id' => 'source-b', 'text' => '[@source-b]', 'cslNoteIndex' => 11]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('citation', ['id' => 'source-a', 'text' => '[@source-a]', 'cslNoteIndex' => 12]),
            ]),
        ]);
        $explicitProcessed = $processor->apply($explicit);
        $explicitNearNote = $explicitProcessed->children[2]->children[0];
        $t->same(['subsequent', 'near-note'], $explicitNearNote->attr('cslPositionTests'));
        $t->same('(near-note de la Cruz 2026)', $explicitNearNote->attr('rendered'));

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="note">
  <citation near-note-distance="close"><layout><text variable="title"/></layout></citation>
</style>
XML
        ));
    },
    'applies bounded csl bibliography display parts for second field layouts' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'source-packet',
                'type' => 'report',
                'title' => 'Source Packet',
                'author' => [
                    ['family' => 'Cruz', 'given' => 'Ana Maria', 'non-dropping-particle' => 'de la'],
                ],
                'issued' => ['date-parts' => [[2026]]],
                'note' => 'Attachment needs review.',
                'URL' => 'https://example.test/source-packet',
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Display Review Style</title>
    <id>https://example.test/styles/bounded-display-review</id>
    <updated>2026-06-05T07:25:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography second-field-align="flush">
    <layout delimiter=" ">
      <text variable="citation-key" display="left-margin" prefix="[" suffix="]"/>
      <group display="right-inline" delimiter=". " suffix=".">
        <names variable="author">
          <name initialize-with=". " name-as-sort-order="all"/>
        </names>
        <text variable="title"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
      <text variable="note" display="indent" prefix="Review note: "/>
      <text variable="URL" display="block" prefix="Source: "/>
    </layout>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $t->same('Bounded Display Review Style', $summary['title'] ?? null);
        $t->same('flush', $summary['bibliographyOptions']['secondFieldAlign'] ?? null);
        $t->same('left-margin', $summary['bibliographyRendering'][0]['display'] ?? null);
        $t->same('right-inline', $summary['bibliographyRendering'][1]['display'] ?? null);
        $t->same('indent', $summary['bibliographyRendering'][2]['display'] ?? null);
        $t->same('block', $summary['bibliographyRendering'][3]['display'] ?? null);

        $t->same('(de la Cruz 2026)', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'source-packet', 'text' => '[@source-packet]']),
        ]));
        $t->same('[source-packet] de la Cruz, A. M. Source Packet. 2026. Review note: Attachment needs review. Source: https://example.test/source-packet', $processor->renderBibliographyEntry('source-packet'));

        $document = (new MarkdownReader())->read('Review cites @source-packet for second-field bibliography output.');
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $bibliography = $processed->children[2];
        $item = $bibliography->children[0];
        $displayParts = $item->attr('cslDisplayParts');
        $t->same('definition_list', $bibliography->type);
        $t->same('flush', $bibliography->attr('secondFieldAlign'));
        $t->same('definition_item', $item->type);
        $t->same([
            ['display' => 'left-margin', 'text' => '[source-packet]'],
            ['display' => 'right-inline', 'text' => 'de la Cruz, A. M. Source Packet. 2026.'],
            ['display' => 'indent', 'text' => 'Review note: Attachment needs review.'],
            ['display' => 'block', 'text' => 'Source: https://example.test/source-packet'],
        ], $displayParts);

        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains('Review cites de la Cruz (2026) for second-field bibliography output.', $markdown);
        $t->contains('de la Cruz 2026' . "\n" . ':   \[source-packet\] de la Cruz, A. M. Source Packet. 2026. Review note: Attachment needs review. Source: https://example.test/source-packet', $markdown);

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Review cites de la Cruz (2026) for second-field bibliography output.</p>', $blocks);
        $t->contains('<dt>de la Cruz 2026</dt><dd><div class="csl-entry"><div class="csl-left-margin">[source-packet]</div><div class="csl-right-inline">de la Cruz, A. M. Source Packet. 2026.</div><div class="csl-indent">Review note: Attachment needs review.</div><div class="csl-block">Source: https://example.test/source-packet</div></div></dd>', $blocks);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <text variable="title" display="sideways"/>
    </layout>
  </citation>
</style>
XML
        ));
    },
    'applies bounded csl year suffix disambiguation for ambiguous author dates' => static function (TestRunner $t): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'smith-post',
                'type' => 'report',
                'title' => 'Post Import Packet',
                'author' => [
                    ['family' => 'Smith', 'given' => 'Ada'],
                ],
                'issued' => ['date-parts' => [[2026]]],
                'URL' => 'https://example.test/post-import',
            ],
            [
                'id' => 'ng-2026',
                'type' => 'report',
                'title' => 'Ng Import Packet',
                'author' => [
                    ['family' => 'Ng', 'given' => 'Nia'],
                ],
                'issued' => ['date-parts' => [[2026]]],
                'URL' => 'https://example.test/ng-import',
            ],
            [
                'id' => 'smith-media',
                'type' => 'report',
                'title' => 'Media Import Packet',
                'author' => [
                    ['family' => 'Smith', 'given' => 'Ada'],
                ],
                'issued' => ['date-parts' => [[2026]]],
                'URL' => 'https://example.test/media-import',
            ],
            [
                'id' => 'smith-2025',
                'type' => 'report',
                'title' => 'Earlier Import Packet',
                'author' => [
                    ['family' => 'Smith', 'given' => 'Ada'],
                ],
                'issued' => ['date-parts' => [[2025]]],
                'URL' => 'https://example.test/earlier-import',
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Year Suffix Review Style</title>
    <id>https://example.test/styles/bounded-year-suffix-review</id>
    <updated>2026-06-05T07:53:00+00:00</updated>
  </info>
  <citation disambiguate-add-year-suffix="true" collapse="year-suffix">
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <group delimiter="">
          <date variable="issued"><date-part name="year"/></date>
          <text variable="year-suffix"/>
        </group>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author">
        <name initialize-with=". " name-as-sort-order="all"/>
      </names>
      <group delimiter="">
        <date variable="issued"><date-part name="year"/></date>
        <text variable="year-suffix"/>
      </group>
      <text variable="title"/>
      <text variable="URL"/>
    </layout>
  </bibliography>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $t->same('Bounded Year Suffix Review Style', $summary['title'] ?? null);
        $t->same(true, $summary['citationOptions']['disambiguateAddYearSuffix'] ?? null);
        $t->same('year-suffix', $summary['citationOptions']['collapse'] ?? null);
        $t->same('year-suffix', $summary['citationRendering'][0]['children'][1]['children'][1]['variable'] ?? null);
        $t->same('year-suffix', $summary['bibliographyRendering'][1]['children'][1]['variable'] ?? null);

        $t->same('(Smith 2026a,b; Ng 2026; Smith 2025)', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'smith-post', 'text' => '[@smith-post]']),
            new AstNode('citation', ['id' => 'smith-media', 'text' => '[@smith-media]']),
            new AstNode('citation', ['id' => 'ng-2026', 'text' => '[@ng-2026]']),
            new AstNode('citation', ['id' => 'smith-2025', 'text' => '[@smith-2025]']),
        ]));
        $t->same('Smith, A. 2026a. Post Import Packet. https://example.test/post-import.', $processor->renderBibliographyEntry('smith-post'));
        $t->same('Smith, A. 2026b. Media Import Packet. https://example.test/media-import.', $processor->renderBibliographyEntry('smith-media'));
        $t->same('Ng, N. 2026. Ng Import Packet. https://example.test/ng-import.', $processor->renderBibliographyEntry('ng-2026'));

        $document = (new MarkdownReader())->read('Review cites @smith-post, @ng-2026, and [@smith-media; @smith-2025] before the bibliography.');
        $processed = $processor->appendBibliography($document, 'Works Cited');
        $citationNodes = [];
        $collectCitations = static function (AstNode $node) use (&$collectCitations, &$citationNodes): void {
            if ($node->type === 'citation') {
                $citationNodes[(string) $node->attr('id', '')] = $node;
            }

            foreach ($node->children as $child) {
                $collectCitations($child);
            }
        };
        $collectCitations($processed);
        $bibliography = $processed->children[2];
        $t->same('a', $citationNodes['smith-post']->attr('cslYearSuffix'));
        $t->same('', $citationNodes['ng-2026']->attr('cslYearSuffix'));
        $t->same('b', $citationNodes['smith-media']->attr('cslYearSuffix'));
        $t->same('', $citationNodes['smith-2025']->attr('cslYearSuffix'));
        $t->same('Smith 2026a', $bibliography->children[0]->children[0]->attr('text'));
        $t->same('Ng 2026', $bibliography->children[1]->children[0]->attr('text'));
        $t->same('Smith 2026b', $bibliography->children[2]->children[0]->attr('text'));
        $t->same('Smith 2025', $bibliography->children[3]->children[0]->attr('text'));

        $markdown = (new MarkdownWriter())->write($processed);
        $t->contains('Review cites Smith (2026a), Ng (2026), and (Smith 2026b; Smith 2025) before the bibliography.', $markdown);
        $t->contains('Smith 2026a' . "\n" . ':   Smith, A. 2026a. Post Import Packet. https://example.test/post-import.', $markdown);
        $t->contains('Smith 2026b' . "\n" . ':   Smith, A. 2026b. Media Import Packet. https://example.test/media-import.', $markdown);
        $t->contains('Ng 2026' . "\n" . ':   Ng, N. 2026. Ng Import Packet. https://example.test/ng-import.', $markdown);

        $blocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('<p>Review cites Smith (2026a), Ng (2026), and (Smith 2026b; Smith 2025) before the bibliography.</p>', $blocks);
        $t->contains('<dt>Smith 2026a</dt><dd>Smith, A. 2026a. Post Import Packet. https://example.test/post-import.</dd>', $blocks);
        $t->contains('<dt>Smith 2026b</dt><dd>Smith, A. 2026b. Media Import Packet. https://example.test/media-import.</dd>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Ng, N. 2026. Ng Import Packet. https://example.test/ng-import.</dd>', $blocks);

        $withoutOption = CitationCslProcessor::fromItems([[
            'id' => 'plain-a',
            'title' => 'Plain A',
            'author' => [['family' => 'Smith', 'given' => 'Ada']],
            'issued' => ['date-parts' => [[2026]]],
        ], [
            'id' => 'plain-b',
            'title' => 'Plain B',
            'author' => [['family' => 'Smith', 'given' => 'Ada']],
            'issued' => ['date-parts' => [[2026]]],
        ]])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter="">
        <date variable="issued"><date-part name="year"/></date>
        <text variable="year-suffix"/>
      </group>
    </layout>
  </citation>
</style>
XML
        );
        $t->same('(2026; 2026)', $withoutOption->renderCitationCluster([
            new AstNode('citation', ['id' => 'plain-a', 'text' => '[@plain-a]']),
            new AstNode('citation', ['id' => 'plain-b', 'text' => '[@plain-b]']),
        ]));

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation disambiguate-add-year-suffix="sometimes">
    <layout><text variable="title"/></layout>
  </citation>
</style>
XML
        ));
    },
    'applies bounded csl citation collapse for author date clusters' => static function (TestRunner $t): void {
        $items = [
            [
                'id' => 'smith-2024',
                'type' => 'report',
                'title' => 'Source Packet 2024',
                'author' => [
                    ['family' => 'Smith', 'given' => 'Ada'],
                ],
                'issued' => ['date-parts' => [[2024]]],
            ],
            [
                'id' => 'smith-2025',
                'type' => 'report',
                'title' => 'Source Packet 2025',
                'author' => [
                    ['family' => 'Smith', 'given' => 'Ada'],
                ],
                'issued' => ['date-parts' => [[2025]]],
            ],
            [
                'id' => 'ng-2025',
                'type' => 'report',
                'title' => 'Ng Source Packet',
                'author' => [
                    ['family' => 'Ng', 'given' => 'Nia'],
                ],
                'issued' => ['date-parts' => [[2025]]],
            ],
        ];
        $processor = CitationCslProcessor::fromItems($items)->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation collapse="year">
    <layout prefix="(" suffix=")" delimiter="; "/>
  </citation>
</style>
XML
        );

        $summary = $processor->cslStyleSummary();
        $t->same('year', $summary['citationOptions']['collapse'] ?? null);
        $t->same('(Smith 2024, 2025; Ng 2025)', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'smith-2024', 'text' => '[@smith-2024]']),
            new AstNode('citation', ['id' => 'smith-2025', 'text' => '[@smith-2025]']),
            new AstNode('citation', ['id' => 'ng-2025', 'text' => '[@ng-2025]']),
        ]));
        $t->same('(see Smith 2024, 2025)', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'smith-2024', 'text' => '[@smith-2024]', 'prefix' => 'see']),
            new AstNode('citation', ['id' => 'smith-2025', 'text' => '[@smith-2025]']),
        ]));
        $t->same('(Smith 2024, p. 7; Smith 2025)', $processor->renderCitationCluster([
            new AstNode('citation', ['id' => 'smith-2024', 'text' => '[@smith-2024]', 'locator' => 'p. 7']),
            new AstNode('citation', ['id' => 'smith-2025', 'text' => '[@smith-2025]']),
        ]));

        $document = (new MarkdownReader())->read('Review cites [@smith-2024; @smith-2025; @ng-2025].');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Review cites (Smith 2024, 2025; Ng 2025).</p>', $blocks);

        $ranged = CitationCslProcessor::fromItems([
            [
                'id' => 'smith-a',
                'title' => 'A',
                'author' => [['family' => 'Smith', 'given' => 'Ada']],
                'issued' => ['date-parts' => [[2026]]],
            ],
            [
                'id' => 'smith-b',
                'title' => 'B',
                'author' => [['family' => 'Smith', 'given' => 'Ada']],
                'issued' => ['date-parts' => [[2026]]],
            ],
            [
                'id' => 'smith-c',
                'title' => 'C',
                'author' => [['family' => 'Smith', 'given' => 'Ada']],
                'issued' => ['date-parts' => [[2026]]],
            ],
        ])->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation disambiguate-add-year-suffix="true" collapse="year-suffix-ranged">
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <group delimiter="">
          <date variable="issued"><date-part name="year"/></date>
          <text variable="year-suffix"/>
        </group>
      </group>
    </layout>
  </citation>
</style>
XML
        );
        $t->same('(Smith 2026a-c)', $ranged->renderCitationCluster([
            new AstNode('citation', ['id' => 'smith-a', 'text' => '[@smith-a]']),
            new AstNode('citation', ['id' => 'smith-b', 'text' => '[@smith-b]']),
            new AstNode('citation', ['id' => 'smith-c', 'text' => '[@smith-c]']),
        ]));

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems($items)->withCslStyle(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation collapse="sideways">
    <layout><text variable="title"/></layout>
  </citation>
</style>
XML
        ));
    },
    'applies bounded csl subsequent author substitute in bibliography handoff' => static function (TestRunner $t) use ($citation): void {
        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'smith-post',
                'type' => 'report',
                'title' => 'Post Import Packet',
                'author' => [
                    ['family' => 'Smith', 'given' => 'Ada'],
                ],
                'issued' => ['date-parts' => [[2024]]],
                'URL' => 'https://example.test/post-import',
            ],
            [
                'id' => 'smith-media',
                'type' => 'report',
                'title' => 'Media Import Packet',
                'author' => [
                    ['family' => 'Smith', 'given' => 'Ada'],
                ],
                'issued' => ['date-parts' => [[2025]]],
                'URL' => 'https://example.test/media-import',
            ],
            [
                'id' => 'ng-2026',
                'type' => 'report',
                'title' => 'Ng Import Packet',
                'author' => [
                    ['family' => 'Ng', 'given' => 'Nia'],
                ],
                'issued' => ['date-parts' => [[2026]]],
                'URL' => 'https://example.test/ng-import',
            ],
        ])->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Subsequent Author Review</title>
    <id>https://example.test/styles/bounded-subsequent-author-review</id>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography subsequent-author-substitute="---" subsequent-author-substitute-rule="complete-all">
    <layout delimiter=". " suffix=".">
      <names variable="author">
        <name initialize-with=". " name-as-sort-order="all"/>
      </names>
      <date variable="issued"><date-part name="year"/></date>
      <text variable="title"/>
      <text variable="URL"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $processor->cslStyleSummary();
        $t->same('---', $summary['bibliographyOptions']['subsequentAuthorSubstitute'] ?? null);
        $t->same('complete-all', $summary['bibliographyOptions']['subsequentAuthorSubstituteRule'] ?? null);
        $t->same('(Smith 2024; Smith 2025; Ng 2026)', $processor->renderCitationCluster([
            $citation('smith-post', '[@smith-post]'),
            $citation('smith-media', '[@smith-media]'),
            $citation('ng-2026', '[@ng-2026]'),
        ]));
        $t->same('Smith, A. 2025. Media Import Packet. https://example.test/media-import.', $processor->renderBibliographyEntry('smith-media'));

        $bibliography = $processor->bibliographyDefinitionList(['smith-post', 'smith-media', 'ng-2026']);
        $t->same('Smith 2024', $bibliography->children[0]->children[0]->attr('text'));
        $t->same('Smith 2025', $bibliography->children[1]->children[0]->attr('text'));
        $t->same('Ng 2026', $bibliography->children[2]->children[0]->attr('text'));
        $t->same('Smith, A. 2024. Post Import Packet. https://example.test/post-import.', $bibliography->children[0]->children[1]->children[0]->children[0]->attr('text'));
        $t->same('---. 2025. Media Import Packet. https://example.test/media-import.', $bibliography->children[1]->children[1]->children[0]->children[0]->attr('text'));
        $t->same('Ng, N. 2026. Ng Import Packet. https://example.test/ng-import.', $bibliography->children[2]->children[1]->children[0]->children[0]->attr('text'));

        $document = (new MarkdownReader())->read('Review cites @smith-post and @smith-media before @ng-2026.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Review cites Smith (2024) and Smith (2025) before Ng (2026).</p>', $blocks);
        $t->contains('<dt>Smith 2024</dt><dd>Smith, A. 2024. Post Import Packet. https://example.test/post-import.</dd>', $blocks);
        $t->contains('<dt>Smith 2025</dt><dd>---. 2025. Media Import Packet. https://example.test/media-import.</dd>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Ng, N. 2026. Ng Import Packet. https://example.test/ng-import.</dd>', $blocks);

        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([])->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation><layout><text variable="title"/></layout></citation>
  <bibliography subsequent-author-substitute="---" subsequent-author-substitute-rule="sideways">
    <layout><text variable="title"/></layout>
  </bibliography>
</style>
XML));
    },
    'rejects malformed csl json and invalid citation records without external citeproc' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromJson('{not json'));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromJson('{"id":"single-object"}'));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([['title' => 'Missing ID']]));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([['id' => ''], ['id' => 'ok']]));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([['id' => 'dup'], ['id' => 'dup']]));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([['id' => 'bad-author', 'author' => 'Ada']]));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([['id' => 'bad-name', 'author' => [[]]]]));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([['id' => 'bad-year', 'issued' => ['date-parts' => [['soon']]]]]));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([['id' => 'bad-accessed-month', 'accessed' => ['date-parts' => [[2026, 13]]]]]));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([['id' => 'bad-comma-suffix', 'author' => [['family' => 'Smith', 'comma-suffix' => 'yes']]]]));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([['id' => 'bad-source-files', 'sourceFiles' => 'source.pdf']]));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromItems([['id' => 'bad-source-file-path', 'sourceFiles' => [['label' => 'Missing path']]]]));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromBibtex('@book{missing, title={Bad}'));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromBibtex('@book{dup,title={A}} @article{dup,title={B}}'));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromBibtex('@online{bad,date={2026-13-01}}'));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromBibtex('@misc{bad,year={2026},month={13}}'));
        $t->throws(InvalidArgumentException::class, static fn (): CitationCslProcessor => CitationCslProcessor::fromBibtex('@book{bad,title {Missing Equals}}'));

        $processor = CitationCslProcessor::fromItems([[
            'id' => 'untitled',
            'issued' => [],
        ]]);
        $untitled = new AstNode('citation', ['id' => 'untitled', 'text' => '[@untitled]']);
        $t->same('(untitled n.d.)', $processor->renderCitationCluster([$untitled]));
        $t->same('untitled n.d.', $processor->bibliographyDefinitionList(['untitled'])->children[0]->children[0]->attr('text'));
        $t->same('', $processor->renderBibliographyEntry('untitled'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $processor->renderBibliographyEntry('missing'));
        $t->throws(InvalidArgumentException::class, static fn (): AstNode => $processor->normalizeCitation(new AstNode('paragraph')));
    },
];
