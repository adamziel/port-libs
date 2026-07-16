<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$findFirstNode = null;
$findFirstNode = static function (AstNode $node, string $type) use (&$findFirstNode): AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findFirstNode($child, $type);
        if ($match->type === $type) {
            return $match;
        }
    }

    return new AstNode('missing');
};

$readFirstNode = static function (string $markdown, string $type) use ($findFirstNode): AstNode {
    return $findFirstNode((new MarkdownReader())->read($markdown), $type);
};

$html = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$linkDestinationCases = [
    'absolute open paren path' => ['https://example.test/review(2026', 'https://example.test/review(2026'],
    'absolute close paren path' => ['https://example.test/review)2026', 'https://example.test/review)2026'],
    'relative open paren segment' => ['docs/(draft', 'docs/(draft'],
    'relative close paren segment' => ['docs/)draft', 'docs/)draft'],
    'double open paren segment' => ['docs/((draft', 'docs/((draft'],
    'double close paren segment' => ['docs/))draft', 'docs/))draft'],
    'query open paren value' => ['search?q=(draft', 'search?q=(draft'],
    'query close paren value' => ['search?q=)draft', 'search?q=)draft'],
    'fragment open paren value' => ['docs#section(1', 'docs#section(1'],
    'fragment close paren value' => ['docs#section)1', 'docs#section)1'],
    'entity ampersand open paren' => ['AT&amp;T(report', 'AT&T(report'],
    'numeric entity close paren with open' => ['score&#41;(report', 'score)(report'],
    'escaped greater open paren' => ['angle\>review(', 'angle>review('],
    'escaped less close paren' => ['angle\<review)', 'angle<review)'],
    'space before open paren' => ['white space(report', 'white%20space(report'],
    'tab before close paren' => ["white\tspace)report", 'white%20space)report'],
    'apostrophe open paren' => ["authors'(note", "authors'(note"],
    'colon close paren path' => ['time)10:30', 'time)10:30'],
    'semicolon close paren path' => ['case);v=1', 'case);v=1'],
    'comma open paren path' => ['case,(draft', 'case,(draft'],
    'unicode open paren path' => ["caf\u{00E9}(draft", "caf\u{00E9}(draft"],
    'mailto open paren path' => ['mailto:user(name@example.test', 'mailto:user(name@example.test'],
    'doi open paren path' => ['doi:10.1000/(abc', 'doi:10.1000/(abc'],
    'parent directory close paren' => ['../path)draft', '../path)draft'],
    'root open paren' => ['/(draft', '/(draft'],
    'hash then close paren' => ['#appendix)draft', '#appendix)draft'],
    'percent encoded open paren' => ['url%20encoded(draft', 'url%20encoded(draft'],
    'plus open paren' => ['action+pack(draft', 'action+pack(draft'],
    'equals close paren' => ['review=)draft', 'review=)draft'],
    'at sign open paren' => ['@review(draft', '@review(draft'],
    'tilde close paren' => ['~review)draft', '~review)draft'],
    'pipe open paren' => ['review|draft(', 'review|draft('],
];

$imageDestinationCases = [
    'figure open paren name' => ['media/figure(1.png', 'media/figure(1.png'],
    'figure close paren name' => ['media/figure)1.png', 'media/figure)1.png'],
    'diagram double open name' => ['media/diagram((draft.svg', 'media/diagram((draft.svg'],
    'diagram double close name' => ['media/diagram))draft.svg', 'media/diagram))draft.svg'],
    'space open figure' => ['media/review figure(draft.png', 'media/review%20figure(draft.png'],
    'entity open figure' => ['media/AT&amp;T(logo.png', 'media/AT&T(logo.png'],
    'escaped greater figure' => ['media/angle\>figure(.png', 'media/angle>figure(.png'],
    'fragment close image' => ['media/chart.svg#view)main', 'media/chart.svg#view)main'],
    'query open image' => ['media/chart.svg?state=(draft', 'media/chart.svg?state=(draft'],
    'percent image' => ['media/chart%20draft(.svg', 'media/chart%20draft(.svg'],
    'unicode image' => ["media/caf\u{00E9}(draft.png", "media/caf\u{00E9}(draft.png"],
    'root close image' => ['/media)draft.png', '/media)draft.png'],
];

$titledDestinationCases = [
    'double title open paren' => ['docs/(draft', 'docs/(draft', '"Draft title"', 'Draft title'],
    'double title close paren' => ['docs/)draft', 'docs/)draft', '"Close title"', 'Close title'],
    'single title open paren' => ['search?q=(draft', 'search?q=(draft', "'Search title'", 'Search title'],
    'single title close paren' => ['search?q=)draft', 'search?q=)draft', "'Close search'", 'Close search'],
    'paren title open paren' => ['media/(draft', 'media/(draft', '(Media title)', 'Media title'],
    'paren title close paren' => ['media/)draft', 'media/)draft', '(Close media)', 'Close media'],
    'entity title open paren' => ['entity&amp;(draft', 'entity&(draft', '"AT&amp;T title"', 'AT&T title'],
    'escaped title close paren' => ['escaped)draft', 'escaped)draft', '"Escaped \\"title\\""', 'Escaped "title"'],
    'numeric title open paren' => ['numeric(draft', 'numeric(draft', '"Score &#41; title"', 'Score ) title'],
    'space title close paren' => ['white space)draft', 'white%20space)draft', '"White space"', 'White space'],
];

$tests = [];

foreach ($linkDestinationCases as $name => [$destination, $expectedUrl]) {
    $tests["maps upstream angle link destination {$name}"] =
        static function (TestRunner $t) use ($readFirstNode, $html, $name, $destination, $expectedUrl): void {
            $label = 'Angle link ' . $name;
            $markdown = '[' . $label . '](<' . $destination . '>)';
            $link = $readFirstNode($markdown, 'link');
            $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($markdown));

            $t->same('link', $link->type, $name);
            $t->same($expectedUrl, $link->attr('url'), $name);
            $t->same($label, $link->children[0]->attr('text'), $name);
            $t->contains('<a href="' . $html($expectedUrl) . '">' . $html($label) . '</a>', $blocks, $name);
        };
}

foreach ($imageDestinationCases as $name => [$destination, $expectedUrl]) {
    $tests["maps upstream angle image destination {$name}"] =
        static function (TestRunner $t) use ($readFirstNode, $html, $name, $destination, $expectedUrl): void {
            $label = 'Angle image ' . $name;
            $markdown = '![' . $label . '](<' . $destination . '>)';
            $image = $readFirstNode($markdown, 'image');
            $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($markdown));

            $t->same('image', $image->type, $name);
            $t->same($expectedUrl, $image->attr('url'), $name);
            $t->same($label, $image->attr('alt'), $name);
            $t->contains('<img src="' . $html($expectedUrl) . '" alt="' . $html($label) . '"/>', $blocks, $name);
        };
}

foreach ($titledDestinationCases as $name => [$destination, $expectedUrl, $titleSource, $expectedTitle]) {
    $tests["maps upstream titled angle destination {$name}"] =
        static function (TestRunner $t) use ($readFirstNode, $html, $name, $destination, $expectedUrl, $titleSource, $expectedTitle): void {
            $label = 'Titled angle ' . $name;
            $markdown = '[' . $label . '](<' . $destination . '> ' . $titleSource . ')';
            $link = $readFirstNode($markdown, 'link');
            $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($markdown));

            $t->same('link', $link->type, $name);
            $t->same($expectedUrl, $link->attr('url'), $name);
            $t->same($expectedTitle, $link->attr('title'), $name);
            $t->contains('<a href="' . $html($expectedUrl) . '" title="' . $html($expectedTitle) . '">' . $html($label) . '</a>', $blocks, $name);
        };
}

$tests['records markdown angle destination mapped-case count'] = static function (TestRunner $t) use ($linkDestinationCases, $imageDestinationCases, $titledDestinationCases): void {
    $t->same(54, count($linkDestinationCases) + count($imageDestinationCases) + count($titledDestinationCases));
};

return $tests;
