<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = static function (string $name): string {
    $bytes = file_get_contents(dirname(__DIR__) . '/fixtures/' . $name);
    if ($bytes === false) {
        throw new RuntimeException("Unable to read fixture {$name}");
    }

    return $bytes;
};

$collectNodes = null;
$collectNodes = static function (AstNode $node, string $type) use (&$collectNodes): array {
    $matches = $node->type === $type ? [$node] : [];
    foreach ($node->children as $child) {
        array_push($matches, ...$collectNodes($child, $type));
    }

    return $matches;
};

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

return [
    'maps upstream command 11589 attributed superscript fixture' =>
        static function (TestRunner $t) use ($fixture, $collectNodes, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read(
                $fixture('upstream-command-11589-attributed-superscript.md')
            );
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $firstSuperscript = $paragraph->children[1] ?? new AstNode('missing');
            $secondSuperscript = $paragraph->children[4] ?? new AstNode('missing');
            $firstSpan = $firstSuperscript->children[0] ?? new AstNode('missing');
            $secondSpan = $secondSuperscript->children[0] ?? new AstNode('missing');
            $secondAttributes = $secondSpan->attr('attributes', []);

            $t->same('paragraph', $paragraph->type);
            $t->same(['text', 'superscript', 'softbreak', 'text', 'superscript'], $inlineTypes($paragraph));
            $t->same([], $collectNodes($paragraph, 'note'));

            $t->same(['span', 'text'], $inlineTypes($firstSuperscript));
            $t->same(['cb'], $firstSpan->attr('classes'));
            $t->same('a', ($firstSpan->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same("\xC2\xA0b", ($firstSuperscript->children[1] ?? new AstNode('missing'))->attr('text'));

            $t->same(['span', 'text'], $inlineTypes($secondSuperscript));
            $t->same('a', ($secondSpan->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('yellow', $secondAttributes['fg'] ?? null);
            $t->same('blue', $secondAttributes['bg'] ?? null);
            $t->same("\xC2\xA0b", ($secondSuperscript->children[1] ?? new AstNode('missing'))->attr('text'));
        },

    'keeps ordinary inline notes before attributed superscript disambiguation' =>
        static function (TestRunner $t) use ($collectNodes): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read('Before ^[note] after.');
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $note = $collectNodes($paragraph, 'note')[0] ?? new AstNode('missing');
            $noteParagraph = $note->children[0] ?? new AstNode('missing');

            $t->same('note', $note->type);
            $t->same('note', $noteParagraph->attr('text'));
            $t->same(' after.', ($paragraph->children[2] ?? new AstNode('missing'))->attr('text'));
        },
];
