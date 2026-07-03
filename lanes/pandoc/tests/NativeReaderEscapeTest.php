<?php

declare(strict_types=1);

use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;

return [
    'reads haskell named character escapes in pandoc native strings' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Para [ Str "alpha\ESC\&omega" , Space , Str "unit\US\&separator" ] ]
NATIVE;

        $document = (new NativeReader())->read($native);
        $paragraph = $document->children[0];

        $t->same('alpha' . chr(27) . 'omega', $paragraph->children[0]->attr('text'));
        $t->same('unit' . chr(31) . 'separator', $paragraph->children[2]->attr('text'));
        $t->same(
            '[ Para [ Str "alpha\27omega" , Space , Str "unit\31separator" ]' . "\n" . ']',
            (new NativeWriter(['blocksOnly' => true]))->write($document)
        );
    },
    'reads haskell single letter control escapes in pandoc native strings' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Plain [ Str "\a\b\f\v" ] ]
NATIVE;

        $document = (new NativeReader())->read($native);

        $t->same(chr(7) . chr(8) . chr(12) . chr(11), $document->children[0]->children[0]->attr('text'));
    },
    'preserves leading nonbreaking spaces in pandoc native inline text summaries' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ LineBlock [ [ Str "\160\160keep", Space, Str "indentation" ] ] ]
NATIVE;

        $document = (new NativeReader())->read($native);
        $line = $document->children[0]->children[0];

        $t->same("\u{00a0}\u{00a0}keep", $line->children[0]->attr('text'));
        $t->same("\u{00a0}\u{00a0}keep indentation", $line->attr('text'));
    },
];
