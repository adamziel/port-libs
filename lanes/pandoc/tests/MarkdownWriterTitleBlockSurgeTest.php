<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$document = static fn (array $meta, string $body): AstNode => new AstNode('document', ['meta' => $meta], [
    $paragraph($body),
]);
$inline = static fn (string $type, array $children = [], array $attrs = []): AstNode => new AstNode($type, $attrs, $children);

$expectedTitleBlock = static function (array $titleLines, array $authorLines, array $dateLines, string $body): string {
    $fields = [$titleLines, $authorLines, $dateLines];
    $lastPopulated = 0;
    foreach ($fields as $index => $lines) {
        if ($lines !== []) {
            $lastPopulated = $index;
        }
    }

    $lines = [];
    for ($index = 0; $index <= $lastPopulated; $index++) {
        $fieldLines = $fields[$index];
        $first = array_shift($fieldLines);
        $lines[] = $first === null || $first === '' ? '%' : '% ' . $first;

        foreach ($fieldLines as $line) {
            $lines[] = '  ' . $line;
        }
    }

    return implode("\n", $lines) . "\n\n" . $body;
};

$case = static function (
    array $meta,
    array $titleLines,
    array $authorLines,
    array $dateLines,
    string $body,
    array $options = ['format' => 'markdown'],
    ?array $readerOptions = null
) use ($document, $expectedTitleBlock): array {
    $expectedMeta = [];
    if ($titleLines !== []) {
        $expectedMeta['title'] = implode(' ', $titleLines);
    }
    if ($authorLines !== []) {
        $expectedMeta['author'] = $authorLines;
        $expectedMeta['authors'] = $authorLines;
    }
    if ($dateLines !== []) {
        $expectedMeta['date'] = implode(' ', $dateLines);
    }

    return [
        'document' => $document($meta, $body),
        'expected' => $expectedTitleBlock($titleLines, $authorLines, $dateLines, $body),
        'options' => $options,
        'readerOptions' => $readerOptions ?? $options,
        'expectedMeta' => $expectedMeta,
    ];
};

$cases = [];

$titleSamples = [
    'Writer title alpha',
    'Packet import beta',
    'Markdown parity gamma',
    'AT&T import delta',
    '# literal heading epsilon',
    '1. ordered literal zeta',
    'Question? eta',
    'Price $5 theta',
    'Use [brackets] iota',
    'Path C:\\source kappa',
    'Pipe | literal lambda',
    'Ampersand &copy; mu',
    'Colon: metadata nu',
    'Dash -- range xi',
    'Ellipsis... omicron',
    'Quote "source" pi',
    "Apostrophe source rho",
    'Unicode cafe sigma',
    'CommonMark opt-in tau',
    'Pandoc alias upsilon',
];
foreach ($titleSamples as $index => $title) {
    $options = match ($index % 4) {
        1 => ['format' => 'pandoc'],
        2 => ['titleBlock' => true],
        3 => ['format' => 'commonmark+pandoc_title_block'],
        default => ['format' => 'markdown'],
    };
    $cases['title only ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = $case(
        ['title' => $title],
        [$title],
        [],
        [],
        'Body title ' . ($index + 1) . '.',
        $options
    );
}

$authorSamples = [
    'Ada Lovelace',
    'Grace Hopper',
    'Katherine Johnson',
    'Dorothy Vaughan',
    'Mary Jackson',
    'Margaret Hamilton',
    'Radia Perlman',
    'Sophie Wilson',
    'Evelyn Boyd Granville',
    'Jean Bartik',
    'Frances Allen',
    'Annie Easley',
    'Mary Allen Wilkes',
    'Adele Goldberg',
    'Barbara Liskov',
    'Karen Sparck Jones',
];
foreach ($authorSamples as $index => $author) {
    $title = 'Author title ' . ($index + 1);
    $meta = $index % 3 === 0
        ? ['title' => $title, 'author' => $author]
        : ['title' => $title, 'author' => [$author], 'authors' => [$author]];
    $cases['single author ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = $case(
        $meta,
        [$title],
        [$author],
        [],
        'Body author ' . ($index + 1) . '.'
    );
}

$dateSamples = [
    '2026-06-16',
    'June 16, 2026',
    'Q2 2026',
    'review window 01',
    'build 2026.06',
    'Tuesday handoff',
    'Sprint 14',
    'release candidate',
    'ISO week 25',
    'final harvest',
    'writer parity',
    'native PHP',
];
foreach ($dateSamples as $index => $date) {
    $title = 'Date title ' . ($index + 1);
    $cases['date field ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = $case(
        ['title' => $title, 'date' => $date],
        [$title],
        [],
        [$date],
        'Body date ' . ($index + 1) . '.'
    );
}

for ($index = 1; $index <= 24; $index++) {
    $title = 'Combined title ' . $index;
    $authors = [
        'Reviewer ' . $index,
        'Editor ' . $index,
        'Verifier ' . $index,
    ];
    $date = '2026-06-' . str_pad((string) (($index % 28) + 1), 2, '0', STR_PAD_LEFT);
    $cases['combined title author date ' . str_pad((string) $index, 2, '0', STR_PAD_LEFT)] = $case(
        [
            'title' => $title,
            'author' => $authors,
            'authors' => $authors,
            'date' => $date,
        ],
        [$title],
        $authors,
        [$date],
        'Body combined ' . $index . '.'
    );
}

$inlineCases = [
    'strong title' => [
        'meta' => ['titleInlines' => [$text('Inline '), $inline('strong', [$text('Packet')])]],
        'title' => ['Inline **Packet**'],
        'authors' => [],
        'date' => [],
    ],
    'emphasis title' => [
        'meta' => ['titleInlines' => [$text('Inline '), $inline('emph', [$text('review')])]],
        'title' => ['Inline *review*'],
        'authors' => [],
        'date' => [],
    ],
    'code title' => [
        'meta' => ['titleInlines' => [$text('Use '), $inline('code', [], ['text' => 'wp import'])]],
        'title' => ['Use `wp import`'],
        'authors' => [],
        'date' => [],
    ],
    'link title' => [
        'meta' => ['titleInlines' => [$text('Review '), $inline('link', [$text('source')], ['url' => '/source'])]],
        'title' => ['Review [source](/source)'],
        'authors' => [],
        'date' => [],
    ],
    'title softbreak' => [
        'meta' => ['titleInlines' => [$text('Line one'), new AstNode('softbreak'), $text('Line two')]],
        'title' => ['Line one', 'Line two'],
        'authors' => [],
        'date' => [],
    ],
    'inline author' => [
        'meta' => ['title' => 'Inline author title', 'authorInlines' => [[$text('Ada '), $inline('emph', [$text('Lovelace')])]]],
        'title' => ['Inline author title'],
        'authors' => ['Ada *Lovelace*'],
        'date' => [],
    ],
    'two inline authors' => [
        'meta' => ['title' => 'Two inline authors', 'authorInlines' => [[$inline('strong', [$text('Reviewer')])], [$inline('code', [], ['text' => 'editor'])]]],
        'title' => ['Two inline authors'],
        'authors' => ['**Reviewer**', '`editor`'],
        'date' => [],
    ],
    'inline date' => [
        'meta' => ['title' => 'Inline date title', 'dateInlines' => [$text('Reviewed '), $inline('strong', [$text('today')])]],
        'title' => ['Inline date title'],
        'authors' => [],
        'date' => ['Reviewed **today**'],
    ],
    'inline all fields' => [
        'meta' => [
            'titleInlines' => [$text('All '), $inline('strong', [$text('fields')])],
            'authorInlines' => [[$text('Writer '), $inline('emph', [$text('One')])], [$text('Writer Two')]],
            'dateInlines' => [$inline('code', [], ['text' => '2026-06-16'])],
        ],
        'title' => ['All **fields**'],
        'authors' => ['Writer *One*', 'Writer Two'],
        'date' => ['`2026-06-16`'],
    ],
    'commonmark inline opt in' => [
        'meta' => ['titleInlines' => [$text('CommonMark '), $inline('strong', [$text('opt-in')])]],
        'title' => ['CommonMark **opt-in**'],
        'authors' => [],
        'date' => [],
        'options' => ['format' => 'commonmark+pandoc_title_block'],
    ],
    'explicit option inline' => [
        'meta' => ['titleInlines' => [$text('Explicit '), $inline('emph', [$text('option')])]],
        'title' => ['Explicit *option*'],
        'authors' => [],
        'date' => [],
        'options' => ['titleBlock' => true],
    ],
    'pandoc alias inline' => [
        'meta' => ['titleInlines' => [$text('Pandoc '), $inline('strong', [$text('alias')])], 'date' => 'today'],
        'title' => ['Pandoc **alias**'],
        'authors' => [],
        'date' => ['today'],
        'options' => ['format' => 'pandoc'],
    ],
];
foreach ($inlineCases as $label => $inlineCase) {
    $cases['inline metadata ' . $label] = $case(
        $inlineCase['meta'],
        $inlineCase['title'],
        $inlineCase['authors'],
        $inlineCase['date'],
        'Body inline ' . $label . '.',
        $inlineCase['options'] ?? ['format' => 'markdown']
    );
}

$tests = [];

$tests['records markdown writer title block surge mapped-case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(84, count($cases));
    };

foreach ($cases as $label => $caseData) {
    $tests['maps upstream markdown writer title block surge ' . $label] =
        static function (TestRunner $t) use ($caseData): void {
            $markdown = (new MarkdownWriter($caseData['options']))->write($caseData['document']);
            $t->same($caseData['expected'], $markdown);
            $t->true(!str_starts_with($markdown, "---\n"), 'Title block cases should not fall back to YAML');

            $roundTrip = (new MarkdownReader($caseData['readerOptions']))->read($markdown);
            $meta = $roundTrip->attr('meta', []);
            foreach ($caseData['expectedMeta'] as $key => $value) {
                $t->same($value, $meta[$key] ?? null, 'Round-trip metadata field ' . $key);
            }
            $t->same('paragraph', $roundTrip->children[0]->type ?? null);
        };
}

$tests['keeps richer markdown metadata on yaml block'] =
    static function (TestRunner $t) use ($document): void {
        $markdown = (new MarkdownWriter(['format' => 'markdown']))->write($document([
            'title' => 'YAML fallback title',
            'review' => ['status' => 'needs-yaml'],
        ], 'Body yaml fallback.'));

        $t->true(str_starts_with($markdown, "---\n"));
        $t->contains('title: "YAML fallback title"', $markdown);
        $t->contains('status: needs-yaml', $markdown);
    };

$tests['honors disabled title block by using yaml metadata'] =
    static function (TestRunner $t) use ($document): void {
        $markdown = (new MarkdownWriter(['format' => 'markdown-pandoc_title_block']))->write($document([
            'title' => 'Disabled title block',
        ], 'Body disabled.'));

        $t->true(str_starts_with($markdown, "---\n"));
        $t->contains('title: "Disabled title block"', $markdown);
    };

$tests['omits title metadata when target format has no title or yaml support'] =
    static function (TestRunner $t) use ($document): void {
        $markdown = (new MarkdownWriter(['format' => 'gfm']))->write($document([
            'title' => 'GFM omitted title',
        ], 'Body gfm.'));

        $t->same('Body gfm.', $markdown);
    };

$tests['falls back to yaml for semicolon author ambiguity'] =
    static function (TestRunner $t) use ($document): void {
        $markdown = (new MarkdownWriter(['format' => 'markdown']))->write($document([
            'title' => 'Ambiguous author title',
            'author' => 'One; Two',
        ], 'Body author yaml.'));

        $t->true(str_starts_with($markdown, "---\n"));
        $t->contains('author: "One; Two"', $markdown);
    };

return $tests;
