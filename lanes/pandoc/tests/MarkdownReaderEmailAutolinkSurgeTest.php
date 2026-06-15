<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$collectNodes = null;
$collectNodes = static function (AstNode $node, string $type) use (&$collectNodes): array {
    $matches = $node->type === $type ? [$node] : [];
    foreach ($node->children as $child) {
        array_push($matches, ...$collectNodes($child, $type));
    }

    return $matches;
};

$plainText = null;
$plainText = static function (array $nodes) use (&$plainText): string {
    $text = '';
    foreach ($nodes as $node) {
        if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
            $text .= (string) $node->attr('text', '');
            continue;
        }

        if ($node->type === 'raw_tex') {
            $text .= (string) $node->attr('tex', '');
            continue;
        }

        if ($node->type === 'softbreak' || $node->type === 'linebreak') {
            $text .= "\n";
            continue;
        }

        $text .= $plainText($node->children);
    }

    return $text;
};

$html = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$invalidEmailAutolinks = [
    'local comma middle' => 'alpha,beta@example.test',
    'local semicolon middle' => 'alpha;beta@example.test',
    'local open paren' => 'alpha(beta@example.test',
    'local close paren' => 'alpha)beta@example.test',
    'local open bracket' => 'alpha[beta@example.test',
    'local close bracket' => 'alpha]beta@example.test',
    'local double quote' => 'alpha"beta@example.test',
    'local backslash' => 'alpha\\beta@example.test',
    'local leading comma' => ',alpha@example.test',
    'local trailing comma' => 'alpha,@example.test',
    'local leading semicolon' => ';alpha@example.test',
    'local trailing semicolon' => 'alpha;@example.test',
    'local leading paren' => '(alpha@example.test',
    'local trailing paren' => 'alpha)@example.test',
    'local leading bracket' => '[alpha@example.test',
    'local trailing bracket' => 'alpha]@example.test',
    'local quoted word' => 'review"source@example.test',
    'local bracketed word' => 'review[source]@example.test',
    'local parenthesized word' => 'review(source)@example.test',
    'local comma path' => 'review,source@example.test',
    'domain underscore' => 'user@example_domain.test',
    'domain leading hyphen' => 'user@-example.test',
    'domain trailing hyphen' => 'user@example-.test',
    'domain double dot' => 'user@example..test',
    'domain leading dot' => 'user@.example.test',
    'domain trailing dot' => 'user@example.test.',
    'domain comma' => 'user@example,source.test',
    'domain colon' => 'user@example:source.test',
    'domain semicolon' => 'user@example;source.test',
    'domain open paren' => 'user@example(source.test',
    'domain close paren' => 'user@example)source.test',
    'domain open bracket' => 'user@example[source.test',
    'domain close bracket' => 'user@example]source.test',
    'domain double quote' => 'user@example"source.test',
    'domain backslash' => 'user@example\\source.test',
    'domain slash' => 'user@example/source.test',
    'domain percent' => 'user@example%source.test',
    'domain bang' => 'user@example!source.test',
    'domain plus' => 'user@example+source.test',
    'domain equals' => 'user@example=source.test',
    'domain second label too long' => 'user@example.' . str_repeat('b', 64),
    'domain tilde' => 'user@example~source.test',
    'domain pipe' => 'user@example|source.test',
    'domain open brace' => 'user@example{source.test',
    'domain close brace' => 'user@example}source.test',
    'domain caret' => 'user@example^source.test',
    'domain backtick' => 'user@example`source.test',
    'domain label too long' => 'user@' . str_repeat('a', 64) . '.test',
    'domain hyphen second label' => 'user@example.-source',
    'domain trailing hyphen second label' => 'user@example.source-',
];

$tests = [];

$tests['maps commonmark invalid angle email autolinks as literal text'] =
    static function (TestRunner $t) use ($invalidEmailAutolinks, $collectNodes, $plainText, $html): void {
        $reader = new MarkdownReader();
        $mapped = 0;
        foreach ($invalidEmailAutolinks as $name => $address) {
            $source = 'Before <' . $address . '> after.';
            $document = $reader->read($source);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('paragraph', $paragraph->type, $name);
            $t->same([], $collectNodes($document, 'link'), $name . ' should not create links');
            $t->same($source, $plainText($paragraph->children), $name . ' literal paragraph text');
            $t->contains('Before &lt;' . $html($address) . '&gt; after.', $blocks, $name . ' WordPress escaped handoff');
            $mapped++;
        }

        $t->same(50, $mapped);
    };

$tests['preserves valid commonmark angle email autolink handoff'] =
    static function (TestRunner $t) use ($collectNodes): void {
        $document = (new MarkdownReader())->read('<review.source+tag@example.test>');
        $links = $collectNodes($document, 'link');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, count($links));
        $t->same('mailto:review.source+tag@example.test', $links[0]->attr('url'));
        $t->same(['email'], $links[0]->attr('classes'));
        $t->same('review.source+tag@example.test', $links[0]->children[0]->attr('text'));
        $t->contains('<a href="mailto:review.source+tag@example.test">review.source+tag@example.test</a>', $blocks);
    };

return $tests;
