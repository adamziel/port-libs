<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordPageImageWrite(1, $page('before-import-root'));
    $stack->recordWalFrameWrite(1, 1);
    $stack->savepoint('plugin-options');
    $stack->recordPageImageWrite(2, $page('before-plugin-option'));
    $stack->recordWalFrameWrite(2, 2);
    $stack->beginStatementJournal('insert-duplicate-option');
    $stack->recordStatementPageImageWrite('insert-duplicate-option', 3, $page('before-statement-table'));
    $stack->recordStatementWalFrameWrite('insert-duplicate-option', 3, 3);
    $stack->recordStatementPageImageWrite('insert-duplicate-option', 4, $page('before-statement-index'));
    $stack->recordStatementWalFrameWrite('insert-duplicate-option', 4, 4, true);

    return $stack;
};

$database = $page('dirty-import-root') . $page('dirty-plugin-option') . $page('dirty-statement-table') . $page('dirty-statement-index');

$cases = [
    'statement journal state records active statement name' => static fn (): mixed => $makeStack()->statementJournalState()[0]['name'],
    'statement journal state records enclosing savepoint' => static fn (): mixed => $makeStack()->statementJournalState()[0]['savepoint'],
    'statement journal state records wal start frame' => static fn (): mixed => $makeStack()->statementJournalState()[0]['wal_start_frame'],
    'statement journal state records page images in order' => static fn (): mixed => $makeStack()->statementJournalState()[0]['page_numbers'],
    'statement journal state records wal frames in order' => static fn (): mixed => $makeStack()->statementJournalState()[0]['wal_frame_indexes'],
    'statement rollback plan names failed statement' => static fn (): mixed => $makeStack()->statementRollbackPlan('insert-duplicate-option', 512)['statement'],
    'statement rollback plan names savepoint' => static fn (): mixed => $makeStack()->statementRollbackPlan('insert-duplicate-option', 512)['savepoint'],
    'statement rollback plan restores statement pages only' => static fn (): mixed => $makeStack()->statementRollbackPlan('insert-duplicate-option', 512)['restored_page_numbers'],
    'statement rollback plan restore page count' => static fn (): mixed => count($makeStack()->statementRollbackPlan('insert-duplicate-option', 512)['restore_pages']),
    'statement rollback plan first restore offset' => static fn (): mixed => $makeStack()->statementRollbackPlan('insert-duplicate-option', 512)['restore_pages'][0]['database_offset'],
    'statement rollback plan second restore offset' => static fn (): mixed => $makeStack()->statementRollbackPlan('insert-duplicate-option', 512)['restore_pages'][1]['database_offset'],
    'statement rollback plan restore byte size' => static fn (): mixed => $makeStack()->statementRollbackPlan('insert-duplicate-option', 512)['restore_pages'][0]['bytes'],
    'statement rollback plan keeps transaction active' => static fn (): mixed => $makeStack()->statementRollbackPlan('insert-duplicate-option', 512)['transaction_active_after'],
    'statement rollback plan keeps savepoint active' => static fn (): mixed => $makeStack()->statementRollbackPlan('insert-duplicate-option', 512)['savepoint_active_after'],
    'statement rollback plan clears statement journal' => static fn (): mixed => $makeStack()->statementRollbackPlan('insert-duplicate-option', 512)['statement_journal_cleared'],
    'statement rollback plan uses statement wal start frame' => static fn (): mixed => $makeStack()->statementRollbackPlan('insert-duplicate-option', 512)['rollback_to_wal_frame'],
    'statement rollback plan discards two wal frames' => static fn (): mixed => count($makeStack()->statementRollbackPlan('insert-duplicate-option', 512)['discarded_wal_frames']),
    'statement rollback plan first discarded wal frame' => static fn (): mixed => $makeStack()->statementRollbackPlan('insert-duplicate-option', 512)['discarded_wal_frames'][0]['frame_index'],
    'statement rollback plan second discarded wal frame' => static fn (): mixed => $makeStack()->statementRollbackPlan('insert-duplicate-option', 512)['discarded_wal_frames'][1]['frame_index'],
    'statement rollback plan preserves commit marker evidence' => static fn (): mixed => $makeStack()->statementRollbackPlan('insert-duplicate-option', 512)['discarded_wal_frames'][1]['commit_frame'],
    'statement rollback image restores table page' => static fn (): mixed => substr($makeStack()->rollbackStatementDatabaseImage('insert-duplicate-option', $database, 512), 1024, 22),
    'statement rollback image restores index page' => static fn (): mixed => substr($makeStack()->rollbackStatementDatabaseImage('insert-duplicate-option', $database, 512), 1536, 22),
    'statement rollback image keeps outer transaction page dirty' => static fn (): mixed => substr($makeStack()->rollbackStatementDatabaseImage('insert-duplicate-option', $database, 512), 0, 17),
    'statement rollback image keeps enclosing savepoint page dirty' => static fn (): mixed => substr($makeStack()->rollbackStatementDatabaseImage('insert-duplicate-option', $database, 512), 512, 19),
    'statement rollback with plan leaves savepoint names' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementOnErrorWithPlan('insert-duplicate-option', 512);
        return $stack->names();
    },
    'statement rollback with plan clears statement journal state' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementOnErrorWithPlan('insert-duplicate-option', 512);
        return $stack->statementJournalState();
    },
    'statement rollback with plan trims pending wal frames' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementOnErrorWithPlan('insert-duplicate-option', 512);
        return $stack->pendingWalFrameIndexes();
    },
    'statement rollback with plan restores prior pending page numbers for savepoint rollback' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementOnErrorWithPlan('insert-duplicate-option', 512);
        return $stack->pendingPageNumbers();
    },
    'statement rollback with plan keeps outer savepoint rollback images' => static function () use ($makeStack, $database): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementOnErrorWithPlan('insert-duplicate-option', 512);
        return substr($stack->rollbackToDatabaseImage('plugin-options', $database, 512), 512, 20);
    },
    'statement rollback with plan allows later wal frame recording' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackStatementOnErrorWithPlan('insert-duplicate-option', 512);
        $stack->recordWalFrameWrite(3, 2);
        return $stack->pendingWalFrameIndexes();
    },
    'statement rollback with plan returns discarded frame page numbers' => static function () use ($makeStack): mixed {
        return array_column($makeStack()->rollbackStatementOnErrorWithPlan('insert-duplicate-option', 512)['discarded_wal_frames'], 'page_number');
    },
    'statement duplicate page keeps first statement image' => static function () use ($page, $database): mixed {
        $stack = new SQLiteSavepointStack();
        $stack->beginTransaction('wp-import');
        $stack->savepoint('plugin-options');
        $stack->beginStatementJournal('failed-update');
        $stack->recordStatementPageImageWrite('failed-update', 3, $page('first-statement-copy'));
        $stack->recordStatementPageImageWrite('failed-update', 3, $page('second-statement-copy'));
        return substr($stack->rollbackStatementDatabaseImage('failed-update', $database, 512), 1024, 20);
    },
    'statement journal can start after savepoint writes' => static function () use ($page): mixed {
        $stack = new SQLiteSavepointStack();
        $stack->beginTransaction('wp-import');
        $stack->savepoint('plugin-options');
        $stack->recordWalFrameWrite(1, 1);
        $stack->beginStatementJournal('failed-insert');
        return $stack->statementJournalState()[0]['wal_start_frame'];
    },
    'statement journal rollback supports empty page image set' => static function (): mixed {
        $stack = new SQLiteSavepointStack();
        $stack->beginTransaction('wp-import');
        $stack->beginStatementJournal('failed-noop');
        return $stack->statementRollbackPlan('failed-noop', 512)['restored_page_numbers'];
    },
    'statement rollback empty page set still clears journal' => static function (): mixed {
        $stack = new SQLiteSavepointStack();
        $stack->beginTransaction('wp-import');
        $stack->beginStatementJournal('failed-noop');
        $stack->rollbackStatementOnErrorWithPlan('failed-noop', 512);
        return $stack->statementJournalState();
    },
    'nested savepoint statement records leaf savepoint' => static function (): mixed {
        $stack = new SQLiteSavepointStack();
        $stack->beginTransaction('wp-import');
        $stack->savepoint('plugin-options');
        $stack->savepoint('single-row');
        $stack->beginStatementJournal('failed-row');
        return $stack->statementJournalState()[0]['savepoint'];
    },
    'statement rollback in nested savepoint keeps all frames' => static function () use ($page): mixed {
        $stack = new SQLiteSavepointStack();
        $stack->beginTransaction('wp-import');
        $stack->savepoint('plugin-options');
        $stack->savepoint('single-row');
        $stack->beginStatementJournal('failed-row');
        $stack->recordStatementPageImageWrite('failed-row', 2, $page('before-row'));
        $stack->rollbackStatementOnErrorWithPlan('failed-row', 512);
        return $stack->names();
    },
    'release enclosing savepoint clears stale statement journals' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('plugin-options');
        return $stack->statementJournalState();
    },
    'full rollback clears statement journals' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollback();
        return $stack->statementJournalState();
    },
    'commit clears statement journals' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->commit();
        return $stack->statementJournalState();
    },
    'statement journal rejects duplicate names' => static function (): mixed {
        $stack = new SQLiteSavepointStack();
        $stack->beginTransaction('wp-import');
        $stack->beginStatementJournal('failed');
        try {
            $stack->beginStatementJournal('failed');
        } catch (LogicException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'statement journal requires active transaction' => static function (): mixed {
        try {
            (new SQLiteSavepointStack())->beginStatementJournal('failed');
        } catch (LogicException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'statement journal rejects empty name' => static function (): mixed {
        try {
            $stack = new SQLiteSavepointStack();
            $stack->beginTransaction('wp-import');
            $stack->beginStatementJournal('');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'statement page image requires existing journal' => static function () use ($page): mixed {
        try {
            $stack = new SQLiteSavepointStack();
            $stack->beginTransaction('wp-import');
            $stack->recordStatementPageImageWrite('missing', 1, $page('before'));
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'statement page image rejects zero page' => static function () use ($page): mixed {
        try {
            $stack = new SQLiteSavepointStack();
            $stack->beginTransaction('wp-import');
            $stack->beginStatementJournal('failed');
            $stack->recordStatementPageImageWrite('failed', 0, $page('before'));
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'statement page image rejects empty image' => static function (): mixed {
        try {
            $stack = new SQLiteSavepointStack();
            $stack->beginTransaction('wp-import');
            $stack->beginStatementJournal('failed');
            $stack->recordStatementPageImageWrite('failed', 1, '');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'statement wal frame requires existing journal' => static function (): mixed {
        try {
            $stack = new SQLiteSavepointStack();
            $stack->beginTransaction('wp-import');
            $stack->recordStatementWalFrameWrite('missing', 1, 1);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'statement rollback plan rejects missing journal' => static function (): mixed {
        try {
            $stack = new SQLiteSavepointStack();
            $stack->beginTransaction('wp-import');
            $stack->statementRollbackPlan('missing', 512);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'statement rollback plan rejects invalid page size' => static function () use ($makeStack): mixed {
        try {
            $makeStack()->statementRollbackPlan('insert-duplicate-option', 0);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'statement rollback image rejects unaligned database' => static function () use ($makeStack): mixed {
        try {
            $makeStack()->rollbackStatementDatabaseImage('insert-duplicate-option', 'short', 512);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'statement rollback image rejects mismatched page size' => static function () use ($makeStack, $database): mixed {
        try {
            $makeStack()->rollbackStatementDatabaseImage('insert-duplicate-option', $database, 256);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'statement rollback with plan rejects missing journal' => static function () use ($makeStack): mixed {
        try {
            $makeStack()->rollbackStatementOnErrorWithPlan('missing', 512);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'statement journal state records active statement name' => 'insert-duplicate-option',
    'statement journal state records enclosing savepoint' => 'plugin-options',
    'statement journal state records wal start frame' => 2,
    'statement journal state records page images in order' => [3, 4],
    'statement journal state records wal frames in order' => [3, 4],
    'statement rollback plan names failed statement' => 'insert-duplicate-option',
    'statement rollback plan names savepoint' => 'plugin-options',
    'statement rollback plan restores statement pages only' => [3, 4],
    'statement rollback plan restore page count' => 2,
    'statement rollback plan first restore offset' => 1024,
    'statement rollback plan second restore offset' => 1536,
    'statement rollback plan restore byte size' => 512,
    'statement rollback plan keeps transaction active' => true,
    'statement rollback plan keeps savepoint active' => true,
    'statement rollback plan clears statement journal' => true,
    'statement rollback plan uses statement wal start frame' => 2,
    'statement rollback plan discards two wal frames' => 2,
    'statement rollback plan first discarded wal frame' => 3,
    'statement rollback plan second discarded wal frame' => 4,
    'statement rollback plan preserves commit marker evidence' => true,
    'statement rollback image restores table page' => 'before-statement-table',
    'statement rollback image restores index page' => 'before-statement-index',
    'statement rollback image keeps outer transaction page dirty' => 'dirty-import-root',
    'statement rollback image keeps enclosing savepoint page dirty' => 'dirty-plugin-option',
    'statement rollback with plan leaves savepoint names' => ['wp-import', 'plugin-options'],
    'statement rollback with plan clears statement journal state' => [],
    'statement rollback with plan trims pending wal frames' => [1, 2],
    'statement rollback with plan restores prior pending page numbers for savepoint rollback' => [1, 2],
    'statement rollback with plan keeps outer savepoint rollback images' => 'before-plugin-option',
    'statement rollback with plan allows later wal frame recording' => [1, 2, 3],
    'statement rollback with plan returns discarded frame page numbers' => [3, 4],
    'statement duplicate page keeps first statement image' => 'first-statement-copy',
    'statement journal can start after savepoint writes' => 1,
    'statement journal rollback supports empty page image set' => [],
    'statement rollback empty page set still clears journal' => [],
    'nested savepoint statement records leaf savepoint' => 'single-row',
    'statement rollback in nested savepoint keeps all frames' => ['wp-import', 'plugin-options', 'single-row'],
    'release enclosing savepoint clears stale statement journals' => [],
    'full rollback clears statement journals' => [],
    'commit clears statement journals' => [],
    'statement journal rejects duplicate names' => 'rejected',
    'statement journal requires active transaction' => 'rejected',
    'statement journal rejects empty name' => 'rejected',
    'statement page image requires existing journal' => 'rejected',
    'statement page image rejects zero page' => 'rejected',
    'statement page image rejects empty image' => 'rejected',
    'statement wal frame requires existing journal' => 'rejected',
    'statement rollback plan rejects missing journal' => 'rejected',
    'statement rollback plan rejects invalid page size' => 'rejected',
    'statement rollback image rejects unaligned database' => 'rejected',
    'statement rollback image rejects mismatched page size' => 'rejected',
    'statement rollback with plan rejects missing journal' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['sqlite savepoint statement journal error edge ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
