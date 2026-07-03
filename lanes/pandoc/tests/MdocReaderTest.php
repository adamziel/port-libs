<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MdocReader;
use PortLibs\Pandoc\PandocConverter;

$read = static fn (string $source): AstNode => (new MdocReader())->read($source);

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if (in_array($node->type, ['space', 'softbreak', 'linebreak'], true)) {
        return ' ';
    }

    return implode('', array_map($plainText, $node->children));
};

$types = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$classes = static fn (AstNode $node): array => array_values(array_map('strval', is_array($node->attr('classes', [])) ? $node->attr('classes', []) : []));

return [
    'reads checked-in mdoc smoke fixture into shared ast' => static function (TestRunner $t) use ($read, $plainText, $types, $classes): void {
        $source = file_get_contents(dirname(__DIR__) . '/fixtures/man-corpus-smoke/mdoc.1');
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read mdoc smoke fixture');
        }

        $document = $read($source);
        $nameParagraph = $document->children[1];

        $t->same('mdoc', $document->attr('sourceFormat'));
        $t->same(['heading', 'paragraph', 'heading', 'paragraph'], $types($document));
        $t->same('NAME', $plainText($document->children[0]));
        $t->same("mdoctool \u{2014} synthetic mdoc fixture", $plainText($nameParagraph));
        $t->same('code', $nameParagraph->children[0]->type);
        $t->same('mdoctool', $nameParagraph->children[0]->attr('text'));
        $t->same([], $classes($nameParagraph->children[0]));
        $t->same('DESCRIPTION', $plainText($document->children[2]));
        $t->same('This file is intentionally outside the man-dialect audit target.', $plainText($document->children[3]));
    },

    'maps common pandoc mdoc inline and list macro shapes' => static function (TestRunner $t) use ($read, $plainText, $types, $classes): void {
        $document = $read(<<<'MDOC'
.Dd July 2, 2026
.Dt SAMPLE 1
.Os
.Sh DESCRIPTION
The
.Nm sample
command reads
.Ar file
and writes
.Pa /tmp/out .
.Bl -bullet
.It
First item.
.It
Second item with
.Fl v
flag.
.El
.Bl -enum
.It
First.
.It
Second.
.El
.Bl -tag -width Ds
.It Fl o Ar file
Write output.
.It Cm check
Validate input.
.El
MDOC);

        $paragraph = $document->children[1];
        $bullet = $document->children[2];
        $ordered = $document->children[3];
        $definition = $document->children[4];

        $t->same(['heading', 'paragraph', 'bullet_list', 'ordered_list', 'definition_list'], $types($document));
        $t->same('The sample command reads file and writes /tmp/out.', $plainText($paragraph));
        $t->same(['Nm'], $classes($paragraph->children[1]));
        $t->same(['variable'], $classes($paragraph->children[3]));
        $t->same('span', $paragraph->children[5]->type);
        $t->same(['Pa'], $classes($paragraph->children[5]));

        $t->same('bullet_list', $bullet->type);
        $t->same('First item.', $plainText($bullet->children[0]));
        $t->same('Second item with -v flag.', $plainText($bullet->children[1]));
        $t->same(['Fl'], $classes($bullet->children[1]->children[0]->children[1]));

        $t->same('ordered_list', $ordered->type);
        $t->same(1, $ordered->attr('start'));
        $t->same('default', $ordered->attr('style'));
        $t->same('First.', $plainText($ordered->children[0]));

        $t->same('definition_list', $definition->type);
        $t->same('-o file', $plainText($definition->children[0]->children[0]));
        $t->same(['Fl'], $classes($definition->children[0]->children[0]->children[0]));
        $t->same(['variable'], $classes($definition->children[0]->children[0]->children[2]));
        $t->same('Write output.', $plainText($definition->children[0]->children[1]));
        $t->same('check', $plainText($definition->children[1]->children[0]));
        $t->same(['Cm'], $classes($definition->children[1]->children[0]->children[0]));
    },

    'reads mdoc through converter and preserves synopsis native shape' => static function (TestRunner $t): void {
        $source = <<<'MDOC'
.Dd July 2, 2026
.Dt SAMPLE 1
.Os
.Sh SYNOPSIS
.Nm
.Op Fl a Ar file
MDOC;

        $document = PandocConverter::read($source, 'mdoc');
        $native = PandocConverter::write($document, 'native');
        $html = PandocConverter::write($document, 'html');

        $t->same('mdoc', $document->attr('sourceFormat'));
        $t->same('line_block', $document->children[1]->type);
        $t->contains('LineBlock', $native);
        $t->contains('Code ( "" , [ "Nm" ] , [  ] ) "sample"', $native);
        $t->contains('Code ( "" , [ "Fl" ] , [  ] ) "-a"', $native);
        $t->contains('Code ( "" , [ "variable" ] , [  ] ) "file"', $native);
        $t->contains('<h1>SYNOPSIS</h1>', $html);
    },
];
