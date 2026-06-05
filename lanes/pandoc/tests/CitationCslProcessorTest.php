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
