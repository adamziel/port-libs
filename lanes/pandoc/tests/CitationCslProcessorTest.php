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
