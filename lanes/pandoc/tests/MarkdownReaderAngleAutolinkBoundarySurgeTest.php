<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$collectLinks = static function (AstNode $node) use (&$collectLinks): array {
    $links = [];
    if ($node->type === 'link') {
        $links[] = $node;
    }

    foreach ($node->children as $child) {
        array_push($links, ...$collectLinks($child));
    }

    return $links;
};

$linkUrls = static fn (AstNode $node): array => array_map(
    static fn (AstNode $link): string => (string) $link->attr('url', ''),
    $collectLinks($node)
);

$textBeforeFirstLink = static function (AstNode $node): string {
    $text = '';
    foreach ($node->children as $child) {
        if ($child->type === 'link') {
            return $text;
        }

        $text .= match ($child->type) {
            'text', 'code' => (string) $child->attr('text', ''),
            'raw_html_inline' => (string) $child->attr('html', ''),
            'softbreak' => ' ',
            'linebreak' => "\n",
            default => '',
        };
    }

    return $text;
};

$html = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$validUriCases = [
    'http uri' => ['markdown' => '<http://example.test/source>', 'url' => 'http://example.test/source'],
    'https query uri' => ['markdown' => '<https://example.test/search?q=alpha&lang=en>', 'url' => 'https://example.test/search?q=alpha&lang=en'],
    'ftp uri' => ['markdown' => '<ftp://example.test/pub/file.txt>', 'url' => 'ftp://example.test/pub/file.txt'],
    'mailto uri' => ['markdown' => '<mailto:editor@example.test>', 'url' => 'mailto:editor@example.test'],
    'git uri' => ['markdown' => '<git://example.test/repo.git>', 'url' => 'git://example.test/repo.git'],
    'file uri' => ['markdown' => '<file:///tmp/import-source.csv>', 'url' => 'file:///tmp/import-source.csv'],
    'doi uri' => ['markdown' => '<doi:10.1000/182>', 'url' => 'doi:10.1000/182'],
    'uppercase scheme uri' => ['markdown' => '<HTTPS://EXAMPLE.TEST/PATH>', 'url' => 'HTTPS://EXAMPLE.TEST/PATH'],
    'plus scheme uri' => ['markdown' => '<web+demo:review-packet>', 'url' => 'web+demo:review-packet'],
    'dot scheme uri' => ['markdown' => '<urn:source:review:packet>', 'url' => 'urn:source:review:packet'],
    'xmpp uri' => ['markdown' => '<xmpp:reviewer@example.test>', 'url' => 'xmpp:reviewer@example.test'],
    'irc uri' => ['markdown' => '<irc://example.test:6667/review>', 'url' => 'irc://example.test:6667/review'],
    'ssh uri' => ['markdown' => '<ssh://example.test/repo>', 'url' => 'ssh://example.test/repo'],
    'data uri' => ['markdown' => '<data:text/plain,review>', 'url' => 'data:text/plain,review'],
    'ipfs uri' => ['markdown' => '<ipfs://bafybeireviewpacket>', 'url' => 'ipfs://bafybeireviewpacket'],
    'news uri' => ['markdown' => '<news:comp.infosystems.www.servers.unix>', 'url' => 'news:comp.infosystems.www.servers.unix'],
    'tel uri' => ['markdown' => '<tel:+15551234567>', 'url' => 'tel:+15551234567'],
    'ldap uri' => ['markdown' => '<ldap://example.test/cn=review>', 'url' => 'ldap://example.test/cn=review'],
    'mid uri' => ['markdown' => '<mid:source-review@example.test>', 'url' => 'mid:source-review@example.test'],
    'cid uri' => ['markdown' => '<cid:source-review@example.test>', 'url' => 'cid:source-review@example.test'],
];

$validEmailCases = [
    'hyphenated domain label' => [
        'markdown' => '<review@example-domain.test>',
        'url' => 'mailto:review@example-domain.test',
        'text' => 'review@example-domain.test',
    ],
    'numeric subdomain label' => [
        'markdown' => '<u123@x42.example.test>',
        'url' => 'mailto:u123@x42.example.test',
        'text' => 'u123@x42.example.test',
    ],
    'unicode domain label' => [
        'markdown' => '<测@foo.测.baz>',
        'url' => 'mailto:测@foo.测.baz',
        'text' => '测@foo.测.baz',
    ],
    'guarded backslash local part' => [
        'markdown' => '<review\\+tag@example.test>',
        'url' => 'mailto:review\\+tag@example.test',
        'text' => 'review\\+tag@example.test',
    ],
];

$invalidAngleCases = [
    'https uri with space' => ['markdown' => '<https://example.test/a b>'],
    'http uri with space' => ['markdown' => '<http://example.test/a b>'],
    'ftp uri with space' => ['markdown' => '<ftp://example.test/a b>'],
    'git uri with space' => ['markdown' => '<git://example.test/a b>'],
    'file uri with space' => ['markdown' => '<file:///tmp/import source.csv>'],
    'doi uri with space' => ['markdown' => '<doi:10.1000/182 extra>'],
    'mailto uri with space' => ['markdown' => '<mailto:user@example.test extra>'],
    'plus scheme with space' => ['markdown' => '<web+demo:review packet>'],
    'urn with space' => ['markdown' => '<urn:review packet>'],
    'https uri with tab' => ['markdown' => "<https://example.test/a\tb>"],
    'https uri with vertical tab' => ['markdown' => "<https://example.test/a\x0Bb>"],
    'https uri with form feed' => ['markdown' => "<https://example.test/a\x0Cb>"],
    'https uri with unit separator' => ['markdown' => "<https://example.test/a\x1Fb>"],
    'https uri with delete control' => ['markdown' => "<https://example.test/a\x7Fb>"],
    'http uri with bell control' => ['markdown' => "<http://example.test/a\x07b>"],
    'prefixed https uri' => ['markdown' => '<bad https://example.test/a>'],
    'prefixed http uri' => ['markdown' => '<bad http://example.test/a>'],
    'prefixed ftp uri' => ['markdown' => '<bad ftp://example.test/a>'],
    'prefixed mailto uri' => ['markdown' => '<bad mailto:user@example.test>'],
    'prefixed bare www' => ['markdown' => '<bad www.example.test/a>'],
    'prefixed bare email' => ['markdown' => '<bad user@example.test>'],
    'angle bare www host' => ['markdown' => '<www.example.test>'],
    'angle bare www path' => ['markdown' => '<www.example.test/path>'],
    'angle email with trailing word' => ['markdown' => '<user@example.test extra>'],
    'angle email with tab' => ['markdown' => "<user@example.test\tname>"],
    'angle email with bell control' => ['markdown' => "<user@example.test\x07>"],
    'angle email with local space' => ['markdown' => '<first last@example.test>'],
    'angle email with leading hyphen domain label' => ['markdown' => '<user@-example.test>'],
    'angle email with trailing hyphen domain label' => ['markdown' => '<user@example-.test>'],
    'angle email with empty domain label' => ['markdown' => '<user@example..test>'],
    'angle email with underscore domain label' => ['markdown' => '<user@example_bad.test>'],
    'angle email with backslash domain label' => ['markdown' => '<user@example\\.test>'],
    'angle email with decoded underscore domain label' => ['markdown' => '<user@example&#95;bad.test>'],
    'angle email with decoded second at sign' => ['markdown' => '<foo&#64;bar@example.test>'],
    'angle mailto followed by email' => ['markdown' => '<mailto:user@example.test user@example.test>'],
    'inline prefixed invalid https uri' => ['markdown' => 'prefix<https://example.test/a b>'],
    'parenthesized invalid https uri' => ['markdown' => '(<https://example.test/a b>)'],
    'quoted invalid https uri' => ['markdown' => '"<https://example.test/a b>"'],
    'sentence invalid https uri' => ['markdown' => 'See <https://example.test/a b>.'],
    'invalid angle then outside bare uri' => [
        'markdown' => '<https://example.test/a b> and https://outside.test',
        'links' => ['https://outside.test'],
        'before' => '<https://example.test/a b> and ',
    ],
    'invalid angle then outside bare www' => [
        'markdown' => '<bad https://example.test/a> and www.outside.test',
        'links' => ['http://www.outside.test'],
        'before' => '<bad https://example.test/a> and ',
    ],
    'invalid angle then outside bare email' => [
        'markdown' => '<user@example.test extra> and editor@example.test',
        'links' => ['mailto:editor@example.test'],
        'before' => '<user@example.test extra> and ',
    ],
    'invalid bare www angle then valid angle uri' => [
        'markdown' => '<www.example.test> and <https://ok.example/path>',
        'links' => ['https://ok.example/path'],
        'before' => '<www.example.test> and ',
    ],
    'invalid spaced http then valid mailto uri' => [
        'markdown' => '<http://example.test/has space> and <mailto:ok@example.test>',
        'links' => ['mailto:ok@example.test'],
        'before' => '<http://example.test/has space> and ',
    ],
    'invalid spaced https adjacent to valid uri' => [
        'markdown' => '<https://example.test/a b><https://ok.example/>',
        'links' => ['https://ok.example/'],
        'before' => '<https://example.test/a b>',
    ],
    'invalid email angle adjacent to valid uri' => [
        'markdown' => '<bad user@example.test><https://ok.example/>',
        'links' => ['https://ok.example/'],
        'before' => '<bad user@example.test>',
    ],
    'invalid spaced http then valid email angle' => [
        'markdown' => '<http://example.test/a b> text <editor@example.test>',
        'links' => ['mailto:editor@example.test'],
        'before' => '<http://example.test/a b> text ',
    ],
    'invalid domain email angle then valid email angle' => [
        'markdown' => '<user@example-.test><editor@example.test>',
        'links' => ['mailto:editor@example.test'],
        'before' => '<user@example-.test>',
    ],
];

$tests = [];

foreach ($validUriCases as $name => $case) {
    $tests["maps upstream markdown angle autolink boundary valid {$name}"] =
        static function (TestRunner $t) use ($case, $collectLinks, $html): void {
            $document = (new MarkdownReader())->read($case['markdown']);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $links = $collectLinks($paragraph);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(1, count($links), $case['markdown']);
            $link = $links[0] ?? new AstNode('missing');
            $t->same('link', $link->type, $case['markdown']);
            $t->same($case['url'], $link->attr('url'), $case['markdown']);
            $t->same(['uri'], $link->attr('classes'), $case['markdown']);
            $t->same($case['url'], $link->children[0]->attr('text'), $case['markdown']);
            $t->contains('<a href="' . $html($case['url']) . '">' . $html($case['url']) . '</a>', $blocks);
        };
}

foreach ($validEmailCases as $name => $case) {
    $tests["maps upstream markdown angle email autolink domain boundary valid {$name}"] =
        static function (TestRunner $t) use ($case, $collectLinks, $html): void {
            $document = (new MarkdownReader())->read($case['markdown']);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $links = $collectLinks($paragraph);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(1, count($links), $case['markdown']);
            $link = $links[0] ?? new AstNode('missing');
            $t->same('link', $link->type, $case['markdown']);
            $t->same($case['url'], $link->attr('url'), $case['markdown']);
            $t->same(['email'], $link->attr('classes'), $case['markdown']);
            $t->same($case['text'], $link->children[0]->attr('text'), $case['markdown']);
            $t->contains('<a href="' . $html($case['url']) . '">' . $html($case['text']) . '</a>', $blocks);
        };
}

foreach ($invalidAngleCases as $name => $case) {
    $tests["maps upstream markdown angle autolink boundary invalid {$name}"] =
        static function (TestRunner $t) use ($case, $linkUrls, $textBeforeFirstLink): void {
            $document = (new MarkdownReader(['format' => 'markdown+autolink_bare_uris']))->read($case['markdown']);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $expectedLinks = $case['links'] ?? [];

            $t->same($expectedLinks, $linkUrls($paragraph), $case['markdown']);
            if ($expectedLinks === []) {
                $t->true($document->children !== [], $case['markdown']);
            } else {
                $t->same($case['before'], $textBeforeFirstLink($paragraph), $case['markdown']);
            }
        };
}

$tests['records upstream markdown angle autolink boundary surge mapped-case count'] =
    static function (TestRunner $t) use ($validUriCases, $validEmailCases, $invalidAngleCases): void {
        $t->same(72, count($validUriCases) + count($validEmailCases) + count($invalidAngleCases));
    };

return $tests;
