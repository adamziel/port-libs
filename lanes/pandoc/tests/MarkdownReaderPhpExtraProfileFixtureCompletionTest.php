<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-z-phpextra-profile.md'
);

return [
    'maps pandoc 3.10 markdown phpextra profile fixture' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown_phpextra']))->read($fixture());
            $heading = $document->children[0] ?? new AstNode('missing');
            $linkParagraph = $document->children[1] ?? new AstNode('missing');
            $link = $linkParagraph->children[0] ?? new AstNode('missing');
            $definitionList = $document->children[2] ?? new AstNode('missing');
            $definitionItem = $definitionList->children[0] ?? new AstNode('missing');
            $definition = $definitionItem->children[1] ?? new AstNode('missing');
            $noteParagraph = $document->children[3] ?? new AstNode('missing');
            $note = $noteParagraph->children[1] ?? new AstNode('missing');
            $noteBody = $note->children[0] ?? new AstNode('missing');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);

            $t->same(4, count($document->children));
            $t->same('heading', $heading->type);
            $t->same('php-extra', $heading->attr('id'));
            $t->same('Title', $heading->attr('text'));
            $t->same('link', $link->type);
            $t->same('https://example.test', $link->attr('url'));
            $t->same(['tracked'], $link->attr('classes'));
            $t->same(['key' => 'v'], $link->attr('attributes'));
            $t->same('definition_list', $definitionList->type);
            $t->same('Term', $definitionItem->attr('term'));
            $t->same('Definition', $definition->children[0]->attr('text'));
            $t->same('note', $note->type);
            $t->same('Note body', $noteBody->attr('text'));
            $t->contains('Header 1 ( "php-extra" , [  ] , [  ] ) [ Str "Title" ]', $native);
            $t->contains('Link ( "" , [ "tracked" ] , [ ( "key" , "v" ) ] ) [ Str "link" ]', $native);
            $t->contains('DefinitionList [ ( [ Str "Term" ]', $native);
            $t->contains('Note [ Para [ Str "Note" , Space , Str "body" ]', $native);
        },

    'keeps pandoc 3.10 markdown phpextra fixture profile-gated' =>
        static function (TestRunner $t) use ($fixture): void {
            $strict = (new MarkdownReader(['format' => 'markdown_strict']))->read($fixture());
            $strictHeading = $strict->children[0] ?? new AstNode('missing');
            $strictLinkParagraph = $strict->children[1] ?? new AstNode('missing');
            $strictLink = $strictLinkParagraph->children[0] ?? new AstNode('missing');
            $strictDefinitionLiteral = $strict->children[2] ?? new AstNode('missing');
            $strictFootnoteDefinition = $strict->children[3] ?? new AstNode('missing');
            $strictNoteParagraph = $strict->children[4] ?? new AstNode('missing');

            $t->same('heading', $strictHeading->type);
            $t->same('', $strictHeading->attr('id', ''));
            $t->same('Title {#php-extra}', $strictHeading->attr('text'));
            $t->same('link', $strictLink->type);
            $t->same([], $strictLink->attr('classes', []));
            $t->same([], $strictLink->attr('attributes', []));
            $t->same('paragraph', $strictDefinitionLiteral->type);
            $t->same('Term : Definition', $strictDefinitionLiteral->attr('text'));
            $t->same('paragraph', $strictFootnoteDefinition->type);
            $t->same('[^n]: Note body', $strictFootnoteDefinition->attr('text'));
            $t->same('paragraph', $strictNoteParagraph->type);
            $t->same('Use note[^n].', $strictNoteParagraph->attr('text'));
        },

    'records pandoc 3.10 markdown phpextra profile fixture mapped-case count' =>
        static function (TestRunner $t) use ($fixture): void {
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($fixture())) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(5, count($cases));
            $t->same('# Title {#php-extra}', $cases[0]);
            $t->same('[link](https://example.test){.tracked key="v"}', $cases[1]);
            $t->same("Term\n: Definition", $cases[2]);
            $t->same('[^n]: Note body', $cases[3]);
            $t->same('Use note[^n].', $cases[4]);
        },
];
