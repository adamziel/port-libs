<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$firstInline = static function (string $markdown): AstNode {
    $document = (new MarkdownReader())->read($markdown);

    return $document->children[0]->children[0] ?? new AstNode('missing');
};

$bareBracedCitationCases = [
    ['space key', '@{source key}', 'source key', null],
    ['year key', '@{source key 2026}', 'source key 2026', null],
    ['uppercase key', '@{Doe 2026}', 'Doe 2026', null],
    ['legacy import key', '@{legacy import note}', 'legacy import note', null],
    ['colon key', '@{chapter: source key}', 'chapter: source key', null],
    ['slash key', '@{archive/import batch}', 'archive/import batch', null],
    ['period key', '@{source.v1 import}', 'source.v1 import', null],
    ['hyphen key', '@{source-key import}', 'source-key import', null],
    ['plus key', '@{source+key import}', 'source+key import', null],
    ['percent key', '@{source%key import}', 'source%key import', null],
    ['ampersand key', '@{source&key import}', 'source&key import', null],
    ['question key', '@{source?key import}', 'source?key import', null],
    ['angle key', '@{source<key> import}', 'source<key> import', null],
    ['tilde key', '@{source~key import}', 'source~key import', null],
    ['money key', '@{source$key import}', 'source$key import', null],
    ['hash key', '@{source#key import}', 'source#key import', null],
    ['pipe key', '@{source|key import}', 'source|key import', null],
    ['mixed punctuation key', '@{source:/#? batch}', 'source:/#? batch', null],
    ['underscore space key', '@{mixed_underscore source}', 'mixed_underscore source', null],
    ['numeric key', '@{source 001}', 'source 001', null],
    ['three word key', '@{source key alpha}', 'source key alpha', null],
    ['long audit key', '@{migration source archive packet}', 'migration source archive packet', null],
    ['html-ish key', '@{<legacy-source> packet}', '<legacy-source> packet', null],
    ['operator key', '@{source + review = pass}', 'source + review = pass', null],
    ['page suffix', '@{source key} [p. 5]', 'source key', 'p. 5'],
    ['chapter suffix', '@{source key 2026} [chapter 2]', 'source key 2026', 'chapter 2'],
    ['section suffix', '@{legacy import note} [sec. 4]', 'legacy import note', 'sec. 4'],
    ['figure suffix', '@{archive/import batch} [fig. 7]', 'archive/import batch', 'fig. 7'],
    ['table suffix', '@{source|key import} [table 3]', 'source|key import', 'table 3'],
    ['appendix suffix', '@{source$key import} [appendix B]', 'source$key import', 'appendix B'],
    ['line suffix', '@{source#key import} [line 44]', 'source#key import', 'line 44'],
    ['note suffix', '@{source?key import} [note 12]', 'source?key import', 'note 12'],
    ['volume suffix', '@{source~key import} [vol. 8]', 'source~key import', 'vol. 8'],
    ['issue suffix', '@{source%key import} [issue 9]', 'source%key import', 'issue 9'],
    ['custom suffix', '@{source&key import} [review packet]', 'source&key import', 'review packet'],
    ['forced suffix text', '@{source + review = pass} [source appendix]', 'source + review = pass', 'source appendix'],
];

$escapedBracedCitationCases = [
    ['bare escaped close brace', '@{source\\}key}', 'source}key', 'author_in_text', null, null],
    ['bare escaped backslash', '@{source\\\\archive}', 'source\\archive', 'author_in_text', null, null],
    ['bare escaped space', '@{source\ key}', 'source key', 'author_in_text', null, null],
    ['bare escaped open brace', '@{source\{key}', 'source{key', 'author_in_text', null, null],
    ['bare escaped closing bracket', '@{source\]key}', 'source]key', 'author_in_text', null, null],
    ['bare escaped at sign', '@{source\@key}', 'source@key', 'author_in_text', null, null],
    ['bracketed escaped close brace', '[@{source\\}key}]', 'source}key', 'normal', null, null],
    ['bracketed escaped suppress author', '[-@{source\\}key}]', 'source}key', 'suppress_author', null, null],
    ['bracketed escaped locator', '[see @{source\\} key}, p. 5]', 'source} key', 'normal', 'see', 'p. 5'],
    ['bracketed escaped backslash locator', '[compare @{source\\\\archive}, fig. 2]', 'source\\archive', 'normal', 'compare', 'fig. 2'],
    ['bracketed escaped closing bracket locator', '[audit @{source\]key}, sec. 9]', 'source]key', 'normal', 'audit', 'sec. 9'],
    ['bracketed escaped open brace', '[@{source\{key}]', 'source{key', 'normal', null, null],
];

$tests = [];

foreach ($bareBracedCitationCases as [$name, $markdown, $id, $suffix]) {
    $tests["maps upstream pandoc markdown bare braced citation {$name}"] =
        static function (TestRunner $t) use ($firstInline, $markdown, $id, $suffix): void {
            $citation = $firstInline($markdown);
            $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($markdown));

            $t->same('citation', $citation->type);
            $t->same('author_in_text', $citation->attr('mode'));
            $t->same($id, $citation->attr('id'));
            $t->same($markdown, $citation->attr('text'));
            $t->same($suffix, $citation->attr('suffix'));
            $t->contains(htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $blocks);
        };
}

foreach ($escapedBracedCitationCases as [$name, $markdown, $id, $mode, $prefix, $locator]) {
    $tests["maps upstream pandoc markdown escaped braced citation {$name}"] =
        static function (TestRunner $t) use ($firstInline, $markdown, $id, $mode, $prefix, $locator): void {
            $citation = $firstInline($markdown);

            $t->same('citation', $citation->type);
            $t->same($mode, $citation->attr('mode'));
            $t->same($id, $citation->attr('id'));
            $t->same($markdown, $citation->attr('text'));
            $t->same($prefix, $citation->attr('prefix'));
            $t->same($locator, $citation->attr('locator'));
        };
}

$tests['records markdown braced citation surge mapped-case count'] =
    static function (TestRunner $t) use ($bareBracedCitationCases, $escapedBracedCitationCases): void {
        $t->same(48, count($bareBracedCitationCases) + count($escapedBracedCitationCases));
    };

return $tests;
