<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PdfSourceDispositionLedger;

return [
    'counts attr-only code block text as emitted source in exact order' => static function (TestRunner $t): void {
        $ledger = PdfSourceDispositionLedger::fromSourceLineItems(
            [
                ['page' => 1, 'stream' => 1, 'text' => 'const answer = 42;'],
                ['page' => 1, 'stream' => 1, 'text' => 'return answer;'],
            ],
            [new AstNode('code_block', ['text' => "const answer = 42;\nreturn answer;"])]
        );

        $t->same(['emitted' => 2], $ledger['dispositionCounts']);
        $t->same(0, $ledger['unresolvedOccurrenceCount']);
        $t->same(0, $ledger['unclaimedEmittedTokenCount']);
        $t->same(0, $ledger['unclaimedEmittedSignificantCharacterCount']);
        $t->same(true, $ledger['orderedSignificantCharactersPreserved']);
        $t->same('source-order-exact', $ledger['orderedSignificantCharacterBasis']);
    },
];
