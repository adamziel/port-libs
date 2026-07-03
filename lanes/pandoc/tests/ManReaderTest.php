<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\ManReader;
use PortLibs\Pandoc\PandocConverter;

$read = static fn (string $source): AstNode => (new ManReader())->read($source);

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if (in_array($node->type, ['space', 'softbreak', 'linebreak'], true)) {
        return ' ';
    }

    return implode('', array_map($plainText, $node->children));
};

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

return [
    'maps upstream man macro unit semantics' => static function (TestRunner $t) use ($read, $plainText, $inlineTypes): void {
        $bold = $read('.B foo')->children[0];
        $italic = $read(".I bar\n")->children[0];
        $boldItalic = $read('.BI foo bar')->children[0];
        $h1 = $read(".SH The header\n")->children[0];
        $h2 = $read('.SS "The header 2"')->children[0];
        $macroArgs = $read('.B "single arg with ""Q"""')->children[0];
        $nextLineArg = $read(".B\nsingle arg with \"Q\"")->children[0];
        $comment = $read(".\\\"bla\naaa")->children[0];
        $link = $read('.BR aa (1)')->children[0];

        $t->same('paragraph', $bold->type);
        $t->same(['strong'], $inlineTypes($bold));
        $t->same('foo', $plainText($bold));
        $t->same(['emph'], $inlineTypes($italic));
        $t->same('bar', $plainText($italic));
        $t->same(['strong', 'emph'], $inlineTypes($boldItalic));
        $t->same('foobar', $plainText($boldItalic));
        $t->same('heading', $h1->type);
        $t->same(1, $h1->attr('level'));
        $t->same('The header', $plainText($h1));
        $t->same(2, $h2->attr('level'));
        $t->same('The header 2', $plainText($h2));
        $t->same(['strong'], $inlineTypes($macroArgs));
        $t->same('single arg with "Q"', $plainText($macroArgs));
        $t->same('single arg with "Q"', $plainText($nextLineArg));
        $t->same('aaa', $plainText($comment));
        $t->same(['strong', 'text'], $inlineTypes($link));
        $t->same('aa(1)', $plainText($link));
    },

    'maps upstream man escape and font unit semantics' => static function (TestRunner $t) use ($read, $plainText, $inlineTypes): void {
        $fonts = $read('aa\fIbb\fRcc')->children[0];
        $nestedFonts = $read('\f[BI]hi\f[I] there\f[R]')->children[0];
        $nestedFonts2 = $read('\f[R]hi \f[I]there \f[BI]bold\f[R] ok')->children[0];

        $t->same(['text', 'emph', 'text'], $inlineTypes($fonts));
        $t->same('aabbcc', $plainText($fonts));
        $t->same(['emph'], $inlineTypes($nestedFonts));
        $t->same(['strong', 'text'], $inlineTypes($nestedFonts->children[0]));
        $t->same('hi there', $plainText($nestedFonts));
        $t->same(['text', 'emph', 'text'], $inlineTypes($nestedFonts2));
        $t->same(['text', 'strong'], $inlineTypes($nestedFonts2->children[1]));
        $t->same('hi there bold ok', $plainText($nestedFonts2));

        $t->same("ab\u{2007}", $plainText($read("a\\%\\\n\\:b\\0")->children[0]));
        $t->same("- \\\u{201C}\u{201D}\u{2014}\u{2013}\u{201C}\u{201D}", $plainText($read('\\-\\ \\\\\\[lq]\\[rq]\\[em]\\[en]\\*(lq\\*(rq')->children[0]));
        $t->same("\\`\u{200A}\u{2006}'", $plainText($read("\\t\\e\\`\\^\\|\\'")->children[0]));
        $t->same('Foo', $plainText($read("Foo \\\" bar\n")->children[0]));
        $t->same('Foobar', $plainText($read("Foo\\#\nbar\n")->children[0]));
        $t->same("\u{00C5}\u{00D5}", $plainText($read('\\(oA\\(~O')->children[0]));
        $t->same("\u{00C5}\u{00D5}$\u{00A5}\u{220F}_", $plainText($read('\\[oA]\\[~O]\\[Do]\\[Ye]\\[product]\\[ul]')->children[0]));
        $t->same("\u{2020}", $plainText($read('\\[u2020]')->children[0]));
        $t->same("\u{00FA}", $plainText($read('\\[u0075_u0301]')->children[0]));
        $t->same('9', $plainText($read('\\9')->children[0]));
    },

    'maps upstream man list unit semantics' => static function (TestRunner $t) use ($read, $plainText): void {
        $bullet = $read(".IP \"\\[bu]\"\nfirst\n.IP \"\\[bu]\"\nsecond")->children[0];
        $ordered = $read(".IP 2 a\nfirst\n.IP 3 a\nsecond")->children[0];
        $upper = $read(".IP A) a\nfirst\n.IP B) a\nsecond")->children[0];
        $nested = $read(".IP \"\\[bu]\"\nfirst\n.RS\n.IP \"\\[bu]\"\n1a\n.IP \"\\[bu]\"\n1b\n.RE")->children[0];
        $changed = $read(".IP \\[bu]\nfirst\n.IP 1\nsecond")->children;
        $wrapped = $read(".IP \"\\[bu]\"\nfirst\ncontinued")->children[0];

        $t->same('bullet_list', $bullet->type);
        $t->same(2, count($bullet->children));
        $t->same('first', $plainText($bullet->children[0]));
        $t->same('second', $plainText($bullet->children[1]));
        $t->same('ordered_list', $ordered->type);
        $t->same(2, $ordered->attr('start'));
        $t->same('decimal', $ordered->attr('style'));
        $t->same('default', $ordered->attr('delimiter'));
        $t->same('upper_alpha', $upper->attr('style'));
        $t->same('one_paren', $upper->attr('delimiter'));
        $t->same('first', $plainText($nested->children[0]->children[0]));
        $t->same('bullet_list', $nested->children[0]->children[1]->type);
        $t->same('1a', $plainText($nested->children[0]->children[1]->children[0]));
        $t->same('1b', $plainText($nested->children[0]->children[1]->children[1]));
        $t->same(['bullet_list', 'ordered_list'], array_map(static fn (AstNode $node): string => $node->type, $changed));
        $t->same('first continued', $plainText($wrapped->children[0]));
    },

    'maps upstream man code block and table unit semantics' => static function (TestRunner $t) use ($read, $plainText): void {
        $code = $read(".nf\naa\n\tbb\n.fi")->children[0];
        $table = $read(".TS\nallbox;\nl l l.\na\tb\tc\nd\te\tf\n.TE")->children[0];
        $longCell = $read(".TS\n;\nr.\nT{\na\nb\nc d\nT}\nf\n.TE")->children[0];

        $t->same('code_block', $code->type);
        $t->same("aa\n\tbb", $code->attr('text'));
        $t->same('table', $table->type);
        $t->same(['left', 'left', 'left'], $table->attr('alignments'));
        $t->same(3, $table->attr('nativeColumnCount'));
        $rows = $table->children[1]->children;
        $t->same('a', $plainText($rows[0]->children[0]));
        $t->same('b', $plainText($rows[0]->children[1]));
        $t->same('c', $plainText($rows[0]->children[2]));
        $t->same('d', $plainText($rows[1]->children[0]));
        $t->same('e', $plainText($rows[1]->children[1]));
        $t->same('f', $plainText($rows[1]->children[2]));

        $t->same(['right'], $longCell->attr('alignments'));
        $t->same('a b c d', $plainText($longCell->children[1]->children[0]->children[0]));
        $t->same('f', $plainText($longCell->children[1]->children[1]->children[0]));
    },

    'keeps common generated manpage requests out of visible text' => static function (TestRunner $t) use ($read, $plainText): void {
        $document = $read(<<<'ROFF'
.TH "TOOL" "1" "July 2026" "tool 1.0" "User Commands"
.nh
.if n .ad l
.SH NAME
.B tool
\- do work
.PP
The \fBtool\fR command prints \(dqquoted\(dq text\&.
.TP
.BI -o " file"
Write output file.
.TP
.IR input
Read input.
.nf
.sp
.RS 4n
tool --flag
.RE
.fi
ROFF);

        $types = array_map(static fn (AstNode $node): string => $node->type, $document->children);
        $visible = $plainText($document);
        $definitionList = $document->children[4];
        $secondDefinition = $definitionList->children[1]->children[1];

        $t->same(['heading', 'paragraph', 'paragraph', 'paragraph', 'definition_list'], $types);
        $t->same('NAME', $plainText($document->children[0]));
        $t->same('tool', $plainText($document->children[1]));
        $t->same('- do work', $plainText($document->children[2]));
        $t->contains('The tool command prints "quoted" text.', $visible);
        $t->true(!str_contains($visible, '.TH'), 'TH request leaked into visible text');
        $t->true(!str_contains($visible, '.nh'), 'nh request leaked into visible text');
        $t->true(!str_contains($visible, '.if'), 'if request leaked into visible text');
        $t->same('definition_list', $definitionList->type);
        $t->same('-o file', $plainText($definitionList->children[0]->children[0]));
        $t->same('Write output file.', $plainText($definitionList->children[0]->children[1]));
        $t->same('input', $plainText($definitionList->children[1]->children[0]));
        $t->same('definition', $secondDefinition->type);
        $t->same('paragraph', $secondDefinition->children[0]->type);
        $t->same('code_block', $secondDefinition->children[1]->type);
        $t->same("\ntool --flag", $secondDefinition->children[1]->attr('text'));
    },

    'does not use man block boundaries as tagged paragraph terms' => static function (TestRunner $t) use ($read, $plainText): void {
        $document = $read(<<<'ROFF'
.SH OPTIONS
.TP
.TP
.B --real
Real option.
.TP
.SH NEXT
After options.
ROFF);

        $list = $document->children[1];

        $t->same(['heading', 'definition_list', 'heading', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('', $plainText($list->children[0]->children[0]));
        $t->same('--real', $plainText($list->children[1]->children[0]));
        $t->same('Real option.', $plainText($list->children[1]->children[1]));
        $t->same('NEXT', $plainText($document->children[2]));
        $t->same('After options.', $plainText($document->children[3]));
    },

    'ignores unmatched man code and table terminators outside active blocks' => static function (TestRunner $t) use ($read, $plainText): void {
        $document = $read(".fi\n.EE\n.TE\n.SH NAME\ntool\n");
        $visible = $plainText($document);

        $t->same(['heading', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('NAME', $plainText($document->children[0]));
        $t->same('tool', $plainText($document->children[1]));
        $t->true(!str_contains($visible, '.fi'), 'unmatched fi terminator should stay hidden');
        $t->true(!str_contains($visible, '.EE'), 'unmatched EE terminator should stay hidden');
        $t->true(!str_contains($visible, '.TE'), 'unmatched TE terminator should stay hidden');
    },

    'skips paragraph requests inside man table bodies' => static function (TestRunner $t) use ($read, $plainText): void {
        $table = $read(<<<'ROFF'
.TS
l l.
.PP
left	right
.PP
next	value
.TE
ROFF)->children[0];

        $rows = $table->children[1]->children;

        $t->same('table', $table->type);
        $t->same(2, count($rows));
        $t->same('left', $plainText($rows[0]->children[0]));
        $t->same('right', $plainText($rows[0]->children[1]));
        $t->same('next', $plainText($rows[1]->children[0]));
        $t->same('value', $plainText($rows[1]->children[1]));
    },

    'keeps man IP lists from swallowing following sections and inline macro lines' => static function (TestRunner $t) use ($read, $plainText): void {
        $document = $read(<<<'ROFF'
.SH OPTIONS
.IP --flag
.B bold option
continues here.
.SH NEXT
After list.
ROFF);

        $list = $document->children[1];

        $t->same(['heading', 'bullet_list', 'heading', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('OPTIONS', $plainText($document->children[0]));
        $t->same('bold option continues here.', $plainText($list->children[0]));
        $t->same('NEXT', $plainText($document->children[2]));
        $t->same('After list.', $plainText($document->children[3]));
    },

    'skips generated macro definition bodies and bare roff comments' => static function (TestRunner $t) use ($read, $plainText): void {
        $document = $read(<<<'ROFF'
\" generated wrapper comment
.de EX
.SH HIDDEN
.TP
hidden definition body
..
.TH TOOL 1
.SH NAME
.B tool \" comment with .SH must stay hidden
\- visible command
ROFF);

        $visible = $plainText($document);

        $t->same(['heading', 'paragraph', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('NAME', $plainText($document->children[0]));
        $t->same('tool', $plainText($document->children[1]));
        $t->same('- visible command', $plainText($document->children[2]));
        $t->true(!str_contains($visible, 'HIDDEN'), 'macro definition body should not become visible text');
        $t->true(!str_contains($visible, '.TH'), 'TH request should not leak after leading comments');
        $t->true(!str_contains($visible, '.SH'), 'comments in macro args should stay hidden');
    },

    'reads man through converter and renders shared ast outputs' => static function (TestRunner $t): void {
        $document = PandocConverter::read(".SH Name\n.B tool\n", 'man');
        $native = PandocConverter::write($document, 'native');
        $html = PandocConverter::write($document, 'html');
        $blocks = PandocConverter::write($document, 'wordpress');

        $t->same('man', $document->attr('sourceFormat'));
        $t->contains('Header 1', $native);
        $t->contains('Strong [ Str "tool" ]', $native);
        $t->contains('<h1>Name</h1>', $html);
        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
    },
];
