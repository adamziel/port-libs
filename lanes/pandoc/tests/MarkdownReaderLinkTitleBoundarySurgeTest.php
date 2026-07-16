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

$hasNodeType = null;
$hasNodeType = static function (AstNode $node, string $type) use (&$hasNodeType): bool {
    if ($node->type === $type) {
        return true;
    }

    foreach ($node->children as $child) {
        if ($hasNodeType($child, $type)) {
            return true;
        }
    }

    return false;
};

$plainText = null;
$plainText = static function (AstNode $node) use (&$plainText): string {
    $text = '';
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        $text .= (string) $node->attr('text', '');
    } elseif ($node->type === 'raw_tex') {
        $text .= (string) $node->attr('tex', '');
    }

    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return $text;
};

$html = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$omittedDestinationTitleSources = [
    'double simple' => ['"Double title"', 'Double title'],
    'double entity' => ['"AT&amp;T title"', 'AT&T title'],
    'double escaped quote' => ['"Escaped \"quote\" title"', 'Escaped "quote" title'],
    'double numeric entity' => ['"Score &#41; title"', 'Score ) title'],
    'double escaped star' => ['"Escaped \* title"', 'Escaped * title'],
    'double multiline' => ["\"First line\nsecond line\"", 'First line second line'],
    'double tabbed' => ["\"First\tsecond\"", 'First second'],
    'single simple' => ["'Single title'", 'Single title'],
    'single double quote' => ["'Double \"inside\" title'", 'Double "inside" title'],
    'single escaped apostrophe' => ["'Escaped \\' apostrophe'", "Escaped ' apostrophe"],
    'single entity' => ["'Entity &copy; title'", "Entity \u{00A9} title"],
    'paren simple' => ['(Paren title)', 'Paren title'],
    'paren escaped close' => ['(Paren \) title)', 'Paren ) title'],
    'paren escaped open' => ['(Paren \( title)', 'Paren ( title'],
    'paren entity' => ['(Paren &amp; title)', 'Paren & title'],
];

$whitespaceReferenceLabels = [
    'one space' => ' ',
    'two spaces' => '  ',
    'three spaces' => '   ',
    'tab' => "\t",
];

$invalidTitleSources = [
    'double nested word' => '"Title "and" tail"',
    'double nested prefix' => '"A "quoted" title"',
    'double nested suffix' => '"Title with "quote" end"',
    'double repeated quotes' => '"One "two" three"',
    'single nested word' => "'Title 'and' tail'",
    'single nested prefix' => "'A 'quoted' title'",
    'single nested suffix' => "'Title with 'quote' end'",
    'single repeated quotes' => "'One 'two' three'",
    'paren unescaped close' => '(Title ) and tail)',
    'paren unescaped open' => '(Title (and tail)',
    'paren nested pair' => '(Title (and) tail)',
    'paren repeated pair' => '(One (two) three)',
];

$tests = [];

foreach (['link', 'image'] as $kind) {
    foreach ($omittedDestinationTitleSources as $name => [$titleSource, $expectedTitle]) {
        $label = 'Omitted ' . $name . ' ' . $kind;
        $markdown = $kind === 'link'
            ? '[' . $label . ']( ' . $titleSource . ')'
            : '![' . $label . ']( ' . $titleSource . ')';

        $tests["maps upstream omitted destination {$kind} title {$name}"] =
            static function (TestRunner $t) use ($findFirstNode, $html, $kind, $label, $markdown, $expectedTitle): void {
                $document = (new MarkdownReader())->read($markdown);
                $node = $findFirstNode($document, $kind);
                $blocks = (new WordPressBlockWriter())->write($document);

                $t->same($kind, $node->type, $label . ' node');
                $t->same('', $node->attr('url'), $label . ' url');
                $t->same($expectedTitle, $node->attr('title'), $label . ' title');
                if ($kind === 'image') {
                    $t->same($label, $node->attr('alt'), $label . ' alt');
                    $t->contains('<img src="" alt="' . $html($label) . '" title="' . $html($expectedTitle) . '"/>', $blocks, $label . ' html');
                } else {
                    $t->same($label, $node->children[0]->attr('text'), $label . ' text');
                    $t->contains('<a href="" title="' . $html($expectedTitle) . '">' . $html($label) . '</a>', $blocks, $label . ' html');
                }
            };
    }
}

foreach ($whitespaceReferenceLabels as $labelName => $sourceLabel) {
    $referenceDefinition = '[' . $sourceLabel . ']: /blank-' . str_replace(' ', '-', $labelName) . ' "Blank title"';
    $referenceCases = [
        'shortcut link' => '[' . $sourceLabel . "]\n\n" . $referenceDefinition,
        'collapsed link' => '[' . $sourceLabel . "][]\n\n" . $referenceDefinition,
        'full link' => '[visible][' . $sourceLabel . "]\n\n" . $referenceDefinition,
        'shortcut image' => '![' . $sourceLabel . "]\n\n" . $referenceDefinition,
        'collapsed image' => '![' . $sourceLabel . "][]\n\n" . $referenceDefinition,
        'full image' => '![visible][' . $sourceLabel . "]\n\n" . $referenceDefinition,
    ];

    foreach ($referenceCases as $shape => $markdown) {
        $tests["rejects upstream whitespace-only reference label {$labelName} {$shape}"] =
            static function (TestRunner $t) use ($hasNodeType, $plainText, $shape, $markdown): void {
                $document = (new MarkdownReader())->read($markdown);
                $blocks = (new WordPressBlockWriter())->write($document);

                $t->same(false, $hasNodeType($document, 'link'), $shape . ' should not resolve link');
                $t->same(false, $hasNodeType($document, 'image'), $shape . ' should not resolve image');
                $t->true(str_contains($plainText($document), '['), $shape . ' literal bracket text remains visible');
                $t->same(false, str_contains($blocks, 'href="/blank-'), $shape . ' href must not render');
                $t->same(false, str_contains($blocks, 'src="/blank-'), $shape . ' image src must not render');
            };
    }
}

foreach (['link', 'image'] as $kind) {
    foreach ($invalidTitleSources as $name => $titleSource) {
        $label = 'Invalid ' . $name . ' ' . $kind;
        $url = '/invalid-title-' . str_replace(' ', '-', $name) . '-' . $kind;
        $markdown = $kind === 'link'
            ? '[' . $label . '](' . $url . ' ' . $titleSource . ')'
            : '![' . $label . '](' . $url . ' ' . $titleSource . ')';

        $tests["rejects upstream malformed {$kind} title delimiter {$name}"] =
            static function (TestRunner $t) use ($hasNodeType, $plainText, $kind, $label, $markdown, $url): void {
                $document = (new MarkdownReader())->read($markdown);
                $blocks = (new WordPressBlockWriter())->write($document);

                $t->same(false, $hasNodeType($document, $kind), $label . ' should not create ' . $kind);
                $t->contains($label, $plainText($document), $label . ' literal label remains visible');
                $t->same(false, str_contains($blocks, 'href="' . $url . '"'), $label . ' href must not render');
                $t->same(false, str_contains($blocks, 'src="' . $url . '"'), $label . ' src must not render');
            };
    }
}

$tests['records markdown reader link title boundary mapped-case count'] =
    static function (TestRunner $t) use ($omittedDestinationTitleSources, $whitespaceReferenceLabels, $invalidTitleSources): void {
        $t->same(
            78,
            (count($omittedDestinationTitleSources) * 2)
                + (count($whitespaceReferenceLabels) * 6)
                + (count($invalidTitleSources) * 2)
        );
    };

return $tests;
