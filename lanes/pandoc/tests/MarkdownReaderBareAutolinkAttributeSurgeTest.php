<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$firstLink = static function (AstNode $document): AstNode {
    foreach ($document->children as $block) {
        foreach ($block->children as $child) {
            if ($child->type === 'link') {
                return $child;
            }
        }
    }

    return new AstNode('missing');
};

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        return (string) $node->attr('text', '');
    }

    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return $text;
};

$html = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$uriCases = [
    'http id' => [
        'markdown' => 'https://example.test/review{#review-link}',
        'url' => 'https://example.test/review',
        'text' => 'https://example.test/review',
        'id' => 'review-link',
        'classes' => [],
        'attributes' => [],
    ],
    'http class' => [
        'markdown' => 'https://example.test/review{.source-link}',
        'url' => 'https://example.test/review',
        'text' => 'https://example.test/review',
        'classes' => ['source-link'],
        'attributes' => [],
    ],
    'http data attribute' => [
        'markdown' => 'https://example.test/review{data-source=batch-42}',
        'url' => 'https://example.test/review',
        'text' => 'https://example.test/review',
        'classes' => [],
        'attributes' => ['data-source' => 'batch-42'],
    ],
    'http spaced tuple' => [
        'markdown' => 'https://example.test/review{#source .review data-kind=uri}',
        'url' => 'https://example.test/review',
        'text' => 'https://example.test/review',
        'id' => 'source',
        'classes' => ['review'],
        'attributes' => ['data-kind' => 'uri'],
    ],
    'http quoted tuple' => [
        'markdown' => 'https://example.test/review{#quoted .source title="Review packet"}',
        'url' => 'https://example.test/review',
        'text' => 'https://example.test/review',
        'id' => 'quoted',
        'classes' => ['source'],
        'attributes' => ['title' => 'Review packet'],
    ],
    'http query tuple' => [
        'markdown' => 'https://example.test/search?q=alpha&stage=review{#query-link .query data-stage=review}',
        'url' => 'https://example.test/search?q=alpha&stage=review',
        'text' => 'https://example.test/search?q=alpha&stage=review',
        'id' => 'query-link',
        'classes' => ['query'],
        'attributes' => ['data-stage' => 'review'],
    ],
    'http fragment tuple' => [
        'markdown' => 'https://example.test/docs#section-2{#fragment-link .fragment data-kind=anchor}',
        'url' => 'https://example.test/docs#section-2',
        'text' => 'https://example.test/docs#section-2',
        'id' => 'fragment-link',
        'classes' => ['fragment'],
        'attributes' => ['data-kind' => 'anchor'],
    ],
    'http port tuple' => [
        'markdown' => 'http://example.test:8080/admin{#admin-link .port data-port=8080}',
        'url' => 'http://example.test:8080/admin',
        'text' => 'http://example.test:8080/admin',
        'id' => 'admin-link',
        'classes' => ['port'],
        'attributes' => ['data-port' => '8080'],
    ],
    'uppercase scheme tuple' => [
        'markdown' => 'HTTPS://EXAMPLE.TEST/UP{#upper-link .caps data-kind=upper}',
        'url' => 'HTTPS://EXAMPLE.TEST/UP',
        'text' => 'HTTPS://EXAMPLE.TEST/UP',
        'id' => 'upper-link',
        'classes' => ['caps'],
        'attributes' => ['data-kind' => 'upper'],
    ],
    'git tuple' => [
        'markdown' => 'git://example.test/repo.git{#git-link .repo data-vcs=git}',
        'url' => 'git://example.test/repo.git',
        'text' => 'git://example.test/repo.git',
        'id' => 'git-link',
        'classes' => ['repo'],
        'attributes' => ['data-vcs' => 'git'],
    ],
    'file tuple' => [
        'markdown' => 'file:///Users/source/review.md{#file-link .file data-host=local}',
        'url' => 'file:///Users/source/review.md',
        'text' => 'file:///Users/source/review.md',
        'id' => 'file-link',
        'classes' => ['file'],
        'attributes' => ['data-host' => 'local'],
    ],
    'mailto scheme tuple' => [
        'markdown' => 'mailto:reviewer@example.test{#mailto-link .mail data-kind=scheme}',
        'url' => 'mailto:reviewer@example.test',
        'text' => 'mailto:reviewer@example.test',
        'id' => 'mailto-link',
        'classes' => ['mail'],
        'attributes' => ['data-kind' => 'scheme'],
    ],
    'doi tuple' => [
        'markdown' => 'doi:10.1000/182{#doi-link .identifier data-kind=doi}',
        'url' => 'doi:10.1000/182',
        'text' => 'doi:10.1000/182',
        'id' => 'doi-link',
        'classes' => ['identifier'],
        'attributes' => ['data-kind' => 'doi'],
    ],
    'percent path tuple' => [
        'markdown' => 'https://example.test/url%20with%20spaces{#percent-link .encoded data-kind=percent}',
        'url' => 'https://example.test/url%20with%20spaces',
        'text' => 'https://example.test/url%20with%20spaces',
        'id' => 'percent-link',
        'classes' => ['encoded'],
        'attributes' => ['data-kind' => 'percent'],
    ],
    'plus path tuple' => [
        'markdown' => 'https://example.test/action+pack{#plus-link .operator data-kind=plus}',
        'url' => 'https://example.test/action+pack',
        'text' => 'https://example.test/action+pack',
        'id' => 'plus-link',
        'classes' => ['operator'],
        'attributes' => ['data-kind' => 'plus'],
    ],
    'colon path tuple' => [
        'markdown' => 'https://example.test/a:b{#colon-link .operator data-kind=colon}',
        'url' => 'https://example.test/a:b',
        'text' => 'https://example.test/a:b',
        'id' => 'colon-link',
        'classes' => ['operator'],
        'attributes' => ['data-kind' => 'colon'],
    ],
    'underscore path tuple' => [
        'markdown' => 'https://example.test/review_packet{#underscore-link .snake data-kind=underscore}',
        'url' => 'https://example.test/review_packet',
        'text' => 'https://example.test/review_packet',
        'id' => 'underscore-link',
        'classes' => ['snake'],
        'attributes' => ['data-kind' => 'underscore'],
    ],
    'at path tuple' => [
        'markdown' => 'https://example.test/@review{#at-link .mention data-kind=at}',
        'url' => 'https://example.test/@review',
        'text' => 'https://example.test/@review',
        'id' => 'at-link',
        'classes' => ['mention'],
        'attributes' => ['data-kind' => 'at'],
    ],
    'entity query tuple' => [
        'markdown' => 'https://example.test?a=AT&amp;T{#entity-link .entity data-kind=amp}',
        'url' => 'https://example.test?a=AT&T',
        'text' => 'https://example.test?a=AT&T',
        'id' => 'entity-link',
        'classes' => ['entity'],
        'attributes' => ['data-kind' => 'amp'],
    ],
    'trailing punctuation after tuple' => [
        'markdown' => 'https://example.test/review{#punct-link .review data-kind=end}.',
        'url' => 'https://example.test/review',
        'text' => 'https://example.test/review',
        'id' => 'punct-link',
        'classes' => ['review'],
        'attributes' => ['data-kind' => 'end'],
        'after' => '.',
    ],
];

$wwwCases = [
    'www id' => [
        'markdown' => 'www.example.test{#www-link}',
        'url' => 'http://www.example.test',
        'text' => 'www.example.test',
        'id' => 'www-link',
        'classes' => [],
        'attributes' => [],
    ],
    'www class' => [
        'markdown' => 'www.example.test{.review-site}',
        'url' => 'http://www.example.test',
        'text' => 'www.example.test',
        'classes' => ['review-site'],
        'attributes' => [],
    ],
    'www data attribute' => [
        'markdown' => 'www.example.test{data-source=www}',
        'url' => 'http://www.example.test',
        'text' => 'www.example.test',
        'classes' => [],
        'attributes' => ['data-source' => 'www'],
    ],
    'www spaced tuple' => [
        'markdown' => 'www.example.test/docs{#www-docs .review data-kind=www}',
        'url' => 'http://www.example.test/docs',
        'text' => 'www.example.test/docs',
        'id' => 'www-docs',
        'classes' => ['review'],
        'attributes' => ['data-kind' => 'www'],
    ],
    'www quoted tuple' => [
        'markdown' => 'www.example.test/docs{#www-quoted .review title="WWW packet"}',
        'url' => 'http://www.example.test/docs',
        'text' => 'www.example.test/docs',
        'id' => 'www-quoted',
        'classes' => ['review'],
        'attributes' => ['title' => 'WWW packet'],
    ],
    'www query tuple' => [
        'markdown' => 'www.example.test/search?q=alpha{#www-query .query data-kind=search}',
        'url' => 'http://www.example.test/search?q=alpha',
        'text' => 'www.example.test/search?q=alpha',
        'id' => 'www-query',
        'classes' => ['query'],
        'attributes' => ['data-kind' => 'search'],
    ],
    'www fragment tuple' => [
        'markdown' => 'www.example.test/docs#section{#www-fragment .fragment data-kind=anchor}',
        'url' => 'http://www.example.test/docs#section',
        'text' => 'www.example.test/docs#section',
        'id' => 'www-fragment',
        'classes' => ['fragment'],
        'attributes' => ['data-kind' => 'anchor'],
    ],
    'www port tuple' => [
        'markdown' => 'www.example.test:8080/admin{#www-port .port data-port=8080}',
        'url' => 'http://www.example.test:8080/admin',
        'text' => 'www.example.test:8080/admin',
        'id' => 'www-port',
        'classes' => ['port'],
        'attributes' => ['data-port' => '8080'],
    ],
    'www uppercase tuple' => [
        'markdown' => 'WWW.EXAMPLE.TEST/UP{#www-upper .caps data-kind=upper}',
        'url' => 'http://WWW.EXAMPLE.TEST/UP',
        'text' => 'WWW.EXAMPLE.TEST/UP',
        'id' => 'www-upper',
        'classes' => ['caps'],
        'attributes' => ['data-kind' => 'upper'],
    ],
    'www encoded tuple' => [
        'markdown' => 'www.example.test/url%20with%20spaces{#www-encoded .encoded data-kind=percent}',
        'url' => 'http://www.example.test/url%20with%20spaces',
        'text' => 'www.example.test/url%20with%20spaces',
        'id' => 'www-encoded',
        'classes' => ['encoded'],
        'attributes' => ['data-kind' => 'percent'],
    ],
    'www plus tuple' => [
        'markdown' => 'www.example.test/action+pack{#www-plus .operator data-kind=plus}',
        'url' => 'http://www.example.test/action+pack',
        'text' => 'www.example.test/action+pack',
        'id' => 'www-plus',
        'classes' => ['operator'],
        'attributes' => ['data-kind' => 'plus'],
    ],
    'www underscore tuple' => [
        'markdown' => 'www.example.test/review_packet{#www-underscore .snake data-kind=underscore}',
        'url' => 'http://www.example.test/review_packet',
        'text' => 'www.example.test/review_packet',
        'id' => 'www-underscore',
        'classes' => ['snake'],
        'attributes' => ['data-kind' => 'underscore'],
    ],
    'www entity tuple' => [
        'markdown' => 'www.example.test?a=AT&amp;T{#www-entity .entity data-kind=amp}',
        'url' => 'http://www.example.test?a=AT&T',
        'text' => 'www.example.test?a=AT&T',
        'id' => 'www-entity',
        'classes' => ['entity'],
        'attributes' => ['data-kind' => 'amp'],
    ],
    'www trailing punctuation after tuple' => [
        'markdown' => 'www.example.test/docs{#www-punct .review data-kind=end},',
        'url' => 'http://www.example.test/docs',
        'text' => 'www.example.test/docs',
        'id' => 'www-punct',
        'classes' => ['review'],
        'attributes' => ['data-kind' => 'end'],
        'after' => ',',
    ],
    'www leading text tuple' => [
        'markdown' => 'Source www.example.test/docs{#www-inline .review data-kind=inline} ready.',
        'url' => 'http://www.example.test/docs',
        'text' => 'www.example.test/docs',
        'id' => 'www-inline',
        'classes' => ['review'],
        'attributes' => ['data-kind' => 'inline'],
        'after' => ' ready.',
    ],
];

$emailCases = [
    'email id' => [
        'markdown' => 'reviewer@example.test{#email-link}',
        'url' => 'mailto:reviewer@example.test',
        'text' => 'reviewer@example.test',
        'id' => 'email-link',
        'classes' => [],
        'attributes' => [],
    ],
    'email class' => [
        'markdown' => 'reviewer@example.test{.reviewer-mail}',
        'url' => 'mailto:reviewer@example.test',
        'text' => 'reviewer@example.test',
        'classes' => ['reviewer-mail'],
        'attributes' => [],
    ],
    'email data attribute' => [
        'markdown' => 'reviewer@example.test{data-source=email}',
        'url' => 'mailto:reviewer@example.test',
        'text' => 'reviewer@example.test',
        'classes' => [],
        'attributes' => ['data-source' => 'email'],
    ],
    'email spaced tuple' => [
        'markdown' => 'reviewer@example.test{#mail-review .mail data-kind=email}',
        'url' => 'mailto:reviewer@example.test',
        'text' => 'reviewer@example.test',
        'id' => 'mail-review',
        'classes' => ['mail'],
        'attributes' => ['data-kind' => 'email'],
    ],
    'email quoted tuple' => [
        'markdown' => 'reviewer@example.test{#mail-quoted .mail title="Reviewer inbox"}',
        'url' => 'mailto:reviewer@example.test',
        'text' => 'reviewer@example.test',
        'id' => 'mail-quoted',
        'classes' => ['mail'],
        'attributes' => ['title' => 'Reviewer inbox'],
    ],
    'email plus local tuple' => [
        'markdown' => 'first.last+tag@example.test{#mail-plus .mail data-kind=plus}',
        'url' => 'mailto:first.last+tag@example.test',
        'text' => 'first.last+tag@example.test',
        'id' => 'mail-plus',
        'classes' => ['mail'],
        'attributes' => ['data-kind' => 'plus'],
    ],
    'email dotted local tuple' => [
        'markdown' => 'first.last@example.test{#mail-dot .mail data-kind=dot}',
        'url' => 'mailto:first.last@example.test',
        'text' => 'first.last@example.test',
        'id' => 'mail-dot',
        'classes' => ['mail'],
        'attributes' => ['data-kind' => 'dot'],
    ],
    'email hyphen domain tuple' => [
        'markdown' => 'source-review@example-domain.test{#mail-hyphen .mail data-kind=hyphen}',
        'url' => 'mailto:source-review@example-domain.test',
        'text' => 'source-review@example-domain.test',
        'id' => 'mail-hyphen',
        'classes' => ['mail'],
        'attributes' => ['data-kind' => 'hyphen'],
    ],
    'email underscore local tuple' => [
        'markdown' => 'migration_batch@example.test{#mail-underscore .mail data-kind=underscore}',
        'url' => 'mailto:migration_batch@example.test',
        'text' => 'migration_batch@example.test',
        'id' => 'mail-underscore',
        'classes' => ['mail'],
        'attributes' => ['data-kind' => 'underscore'],
    ],
    'email apostrophe local tuple' => [
        'markdown' => "o'connor@example.test{#mail-apostrophe .mail data-kind=apostrophe}",
        'url' => "mailto:o'connor@example.test",
        'text' => "o'connor@example.test",
        'id' => 'mail-apostrophe',
        'classes' => ['mail'],
        'attributes' => ['data-kind' => 'apostrophe'],
    ],
    'email slash local tuple' => [
        'markdown' => 'foo/bar@example.test{#mail-slash .mail data-kind=slash}',
        'url' => 'mailto:foo/bar@example.test',
        'text' => 'foo/bar@example.test',
        'id' => 'mail-slash',
        'classes' => ['mail'],
        'attributes' => ['data-kind' => 'slash'],
    ],
    'email uppercase tuple' => [
        'markdown' => 'USER@EXAMPLE.TEST{#mail-upper .mail data-kind=upper}',
        'url' => 'mailto:USER@EXAMPLE.TEST',
        'text' => 'USER@EXAMPLE.TEST',
        'id' => 'mail-upper',
        'classes' => ['mail'],
        'attributes' => ['data-kind' => 'upper'],
    ],
    'email country domain tuple' => [
        'markdown' => 'user.name@example.co.uk{#mail-country .mail data-kind=country}',
        'url' => 'mailto:user.name@example.co.uk',
        'text' => 'user.name@example.co.uk',
        'id' => 'mail-country',
        'classes' => ['mail'],
        'attributes' => ['data-kind' => 'country'],
    ],
    'email trailing punctuation after tuple' => [
        'markdown' => 'reviewer@example.test{#mail-punct .mail data-kind=end}!',
        'url' => 'mailto:reviewer@example.test',
        'text' => 'reviewer@example.test',
        'id' => 'mail-punct',
        'classes' => ['mail'],
        'attributes' => ['data-kind' => 'end'],
        'after' => '!',
    ],
    'email leading text tuple' => [
        'markdown' => 'Contact reviewer@example.test{#mail-inline .mail data-kind=inline} now.',
        'url' => 'mailto:reviewer@example.test',
        'text' => 'reviewer@example.test',
        'id' => 'mail-inline',
        'classes' => ['mail'],
        'attributes' => ['data-kind' => 'inline'],
        'after' => ' now.',
    ],
];

$cases = array_merge($uriCases, $wwwCases, $emailCases);
$tests = [];

foreach ($cases as $name => $case) {
    $tests["maps upstream GFM bare autolink trailing attributes {$name}"] =
        static function (TestRunner $t) use ($case, $firstLink, $plainText, $html): void {
            $document = (new MarkdownReader(['format' => 'markdown+autolink_bare_uris']))->read($case['markdown']);
            $link = $firstLink($document);
            $blocks = (new WordPressBlockWriter())->write($document);
            $roundTrip = (new MarkdownWriter())->write($document);

            $t->same('link', $link->type);
            $t->same($case['url'], $link->attr('url'));
            $t->same($case['text'], $plainText($link));
            $t->same($case['id'] ?? null, $link->attr('id', null));
            $t->same($case['classes'], $link->attr('classes', []));
            $t->same($case['attributes'], $link->attr('attributes', []));
            $t->contains('<a href="' . $html($case['url']) . '"', $blocks);

            if (($case['id'] ?? null) !== null) {
                $t->contains(' id="' . $html($case['id']) . '"', $blocks);
                $t->contains('{#' . $case['id'], $roundTrip);
            }
            foreach ($case['classes'] as $class) {
                $t->contains($class, $blocks);
            }
            foreach ($case['attributes'] as $key => $value) {
                $t->contains($key . '="' . $html($value) . '"', $blocks);
            }
            if (array_key_exists('after', $case)) {
                $paragraphText = $plainText($document->children[0] ?? new AstNode('missing'));
                $t->contains($case['text'] . $case['after'], $paragraphText);
            }
        };
}

$tests['keeps non-attribute curly brace bare autolink paths mapped'] =
    static function (TestRunner $t) use ($firstLink, $plainText): void {
        $document = (new MarkdownReader(['format' => 'markdown+autolink_bare_uris']))->read(implode("\n\n", [
            'www.example.test/path{one}',
            'https://example.test/path{review}',
            'name{tag}@example.test',
        ]));
        $links = [];
        foreach ($document->children as $block) {
            $links[] = $firstLink(new AstNode('document', [], [$block]));
        }

        $t->same('http://www.example.test/path%7Bone%7D', $links[0]->attr('url'));
        $t->same('www.example.test/path{one}', $plainText($links[0]));
        $t->same('https://example.test/path%7Breview%7D', $links[1]->attr('url'));
        $t->same('https://example.test/path{review}', $plainText($links[1]));
        $t->same('mailto:name{tag}@example.test', $links[2]->attr('url'));
        $t->same('name{tag}@example.test', $plainText($links[2]));
    };

$tests['records markdown bare autolink attribute surge mapped-case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(50, count($cases));
    };

return $tests;
