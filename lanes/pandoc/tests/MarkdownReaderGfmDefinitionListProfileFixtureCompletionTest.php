<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-gfm-definition-list-profile.md'
);

return [
    'maps pandoc 3.10 gfm definition-list profile fixture' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'gfm+definition_lists']))->read($fixture());
            $list = $document->children[0] ?? new AstNode('missing');
            $item = $list->children[0] ?? new AstNode('missing');
            $definition = $item->children[1] ?? new AstNode('missing');
            $plain = $definition->children[0] ?? new AstNode('missing');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(1, count($document->children));
            $t->same('definition_list', $list->type);
            $t->same('Term', $item->attr('term'));
            $t->same('definition', $definition->type);
            $t->same('plain', $plain->type);
            $t->same('Definition in GFM profile', $plain->attr('text'));
            $t->contains('DefinitionList [ ( [ Str "Term" ]', $native);
            $t->contains('Plain [ Str "Definition" , Space , Str "in"', $native);
            $t->contains('<dl><dt>Term</dt><dd>Definition in GFM profile</dd></dl>', $blocks);
        },

    'records pandoc 3.10 gfm definition-list profile fixture literal' =>
        static function (TestRunner $t) use ($fixture): void {
            $t->same("Term\n: Definition in GFM profile", trim($fixture()));
        },
];
