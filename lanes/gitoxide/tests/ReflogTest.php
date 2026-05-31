<?php

declare(strict_types=1);

use PortLibs\Gitoxide\CommitSignature;
use PortLibs\Gitoxide\ReferenceStore;
use PortLibs\Gitoxide\ReferenceTarget;
use PortLibs\Gitoxide\ReflogEntry;

$old = '134385f6d781b7e97062102c6a483440bfda2a03';
$new = 'a98ad44f7f0d6eae901abe9c6f10b4d9be2a190f';
$other = '28ce6a8b26aa170e1de65536fe8abe1832bd3242';
$zeros = str_repeat('0', 40);

return [
    'reflog entry parses upstream gix-ref line forms' => static function (TestRunner $t) use ($old, $new, $zeros): void {
        $line = "{$old} {$new} Sebastian Thiel <foo@example.com> 1618030561 +0800\tpull --ff-only: Fast-forward";
        $entry = ReflogEntry::parse($line);

        $t->same($old, $entry->previousOid);
        $t->same($new, $entry->newOid);
        $t->same('Sebastian Thiel', $entry->signature->name);
        $t->same('foo@example.com', $entry->signature->email);
        $t->same('1618030561 +0800', $entry->signature->time);
        $t->same('pull --ff-only: Fast-forward', $entry->message);

        $withNewline = ReflogEntry::parse($line . "\nignored trailing line");
        $t->same($entry->previousOid, $withNewline->previousOid);
        $t->same($entry->message, $withNewline->message);

        $emptyMessage = ReflogEntry::parse("{$zeros} {$zeros} name <foo@example.com> 1234567890 -0000\n");
        $t->same('', $emptyMessage->message);
        $t->same('name', $emptyMessage->signature->name);

        $tabEmptyMessage = ReflogEntry::parse("{$zeros} {$zeros} one <foo@example.com> 1234567890 -0000\t\n{$zeros} {$zeros} two <foo@example.com> 1234567890 -0000\thello");
        $t->same('', $tabEmptyMessage->message);
        $t->same('one', $tabEmptyMessage->signature->name);

        $t->throws(InvalidArgumentException::class, static fn () => ReflogEntry::parse('definitely not a log entry'));
        $t->throws(
            InvalidArgumentException::class,
            static fn () => ReflogEntry::parse("{$zeros} {$zeros} one <foo@example.com> 1234567890 -0000message"),
        );

        $angleMessage = 'rebase (pick): Replace Into<Range<u32>> by From<LineRange>';
        $angleLine = "7b114132d03c468a9cd97836901553658c9792de 306cdbab5457c323d1201aa8a59b3639f600a758 First Last <first.last@example.com> 1727013187 +0200\t{$angleMessage}";
        $angleEntry = ReflogEntry::parse($angleLine);
        $t->same('First Last', $angleEntry->signature->name);
        $t->same('first.last@example.com', $angleEntry->signature->email);
        $t->same('1727013187 +0200', $angleEntry->signature->time);
        $t->same($angleMessage, $angleEntry->message);
    },
    'reference store appends and parses reflog entries forward and reverse' => static function (TestRunner $t) use ($old, $new, $other, $zeros): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-reflog-forward-reverse-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $committer = new CommitSignature(' Deploy Bot ', ' deploy@example.com ', '1234 +0000');

        $store->appendReflog(
            'refs/heads/main',
            null,
            ReferenceTarget::object($old),
            $committer,
            'branch created',
        );
        $store->appendReflog(
            'refs/heads/main',
            ReferenceTarget::object($old),
            ReferenceTarget::object($new),
            $committer,
            'fast-forward deployment',
        );
        $store->appendReflog(
            'refs/heads/main',
            ReferenceTarget::object($new),
            ReferenceTarget::object($other),
            $committer,
            '',
        );

        $forward = $store->reflogEntries('refs/heads/main');
        $reverse = $store->reflogEntriesReverse('refs/heads/main');

        $t->same(3, count($forward ?? []));
        $t->same(3, count($reverse ?? []));
        $t->same($zeros, $forward[0]->previousOid);
        $t->same($old, $forward[0]->newOid);
        $t->same('branch created', $forward[0]->message);
        $t->same($old, $forward[1]->previousOid);
        $t->same($new, $forward[1]->newOid);
        $t->same($other, $forward[2]->newOid);
        $t->same('', $forward[2]->message);
        $t->same('Deploy Bot', $forward[0]->signature->name);
        $t->same('deploy@example.com', $forward[0]->signature->email);
        $t->same($other, $reverse[0]->newOid);
        $t->same($new, $reverse[1]->newOid);
        $t->same($old, $reverse[2]->newOid);
        $t->same(null, $store->reflogEntries('refs/heads/missing'));

        mkdir($dir . '/logs/refs/heads/directory', 0777, true);
        $t->same(null, $store->reflogEntries('refs/heads/directory'));
    },
    'reference store direct append recovers empty reflog directory blockers like upstream' => static function (TestRunner $t) use ($old, $new, $zeros): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-reflog-dir-recovery-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $committer = new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000');
        mkdir($dir . '/logs/refs/heads/recovered/empty-a/empty-b', 0777, true);

        $store->appendReflog(
            'refs/heads/recovered',
            null,
            ReferenceTarget::object($new),
            $committer,
            'replace empty directory blocker',
        );

        $entries = $store->reflogEntries('refs/heads/recovered');
        $t->same(1, count($entries ?? []));
        $t->same($zeros, $entries[0]->previousOid);
        $t->same($new, $entries[0]->newOid);
        $t->same('replace empty directory blocker', $entries[0]->message);
        $t->same(true, is_file($dir . '/logs/refs/heads/recovered'));
        $t->same(false, is_dir($dir . '/logs/refs/heads/recovered/empty-a'));

        mkdir($dir . '/logs/refs/heads/non-empty', 0777, true);
        file_put_contents($dir . '/logs/refs/heads/non-empty/held', 'not empty');
        $t->throws(
            RuntimeException::class,
            static fn () => $store->appendReflog(
                'refs/heads/non-empty',
                ReferenceTarget::object($old),
                ReferenceTarget::object($new),
                $committer,
                'should not replace non-empty blocker',
            ),
        );
        $t->same(true, is_dir($dir . '/logs/refs/heads/non-empty'));
        $t->same('not empty', (string) file_get_contents($dir . '/logs/refs/heads/non-empty/held'));
    },
    'reference store respects auto create boundaries and forced tag reflogs' => static function (TestRunner $t) use ($old, $new, $zeros): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-reflog-autocreate-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $committer = new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000');

        $store->appendReflog(
            'refs/tags/v1.0.0',
            null,
            ReferenceTarget::object($old),
            $committer,
            'tag should not auto-create',
            false,
        );
        $t->same(null, $store->reflogEntries('refs/tags/v1.0.0'));

        $store->appendReflog(
            'refs/tags/v1.0.0',
            null,
            ReferenceTarget::object($old),
            $committer,
            'force tag audit',
            true,
        );
        $tagEntries = $store->reflogEntries('refs/tags/v1.0.0');
        $t->same(1, count($tagEntries ?? []));
        $t->same($zeros, $tagEntries[0]->previousOid);
        $t->same('force tag audit', $tagEntries[0]->message);

        $store->appendReflog(
            'refs/heads/main',
            ReferenceTarget::object($old),
            ReferenceTarget::object($old),
            $committer,
            'unchanged object is skipped',
        );
        $t->same(null, $store->reflogEntries('refs/heads/main'));

        $line = ReflogEntry::appendLine(null, ReferenceTarget::object($new), $committer, '', 'sha1');
        $t->same("{$zeros} {$new} Deploy Bot <deploy@example.com> 1234 +0000\n", $line);
        $parsed = ReflogEntry::parse($line);
        $t->same('', $parsed->message);
    },
    'reference store writes symbolic reflog from existing-must-match object like upstream clone accommodation' => static function (TestRunner $t) use ($old, $new, $zeros): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-reflog-symbolic-peeled-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $committer = new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000');

        $result = $store->updateWithReport(
            'refs/heads/symbolic',
            ReferenceTarget::symbolic('refs/heads/alt-main'),
            ReferenceStore::PREVIOUS_EXISTING_MUST_MATCH,
            ReferenceTarget::object($new),
            false,
            'sha1',
            $committer,
            'clone peeled symbolic branch',
        );

        $t->same('refs/heads/symbolic', $result->reference->name);
        $t->same('symbolic', $result->reference->target->kind);
        $t->same('refs/heads/alt-main', $result->reference->target->value);
        $t->same(null, $store->looseStore()->tryRead('refs/heads/alt-main'));

        $symbolicEntries = $store->reflogEntries('refs/heads/symbolic');
        $t->same(1, count($symbolicEntries ?? []));
        $t->same($zeros, $symbolicEntries[0]->previousOid);
        $t->same($new, $symbolicEntries[0]->newOid);
        $t->same('clone peeled symbolic branch', $symbolicEntries[0]->message);
        $t->contains("\tclone peeled symbolic branch\n", (string) $store->reflogContents('refs/heads/symbolic'));

        $plainSymbolicStore = new ReferenceStore($dir . '-plain-symbolic');
        $plainSymbolicStore->updateWithReport(
            'HEAD',
            ReferenceTarget::symbolic('refs/heads/alt-main'),
            ReferenceStore::PREVIOUS_MUST_NOT_EXIST,
            null,
            false,
            'sha1',
            $committer,
            'ignored for symbolic ref without peeled object',
        );
        $t->same(null, $plainSymbolicStore->reflogEntries('HEAD'));

        $plainSymbolicStore->updateWithReport(
            'refs/heads/symbolic-object-guard',
            ReferenceTarget::symbolic('refs/heads/alt-main'),
            ReferenceStore::PREVIOUS_EXISTING_MUST_MATCH,
            ReferenceTarget::symbolic('refs/heads/other'),
            false,
            'sha1',
            $committer,
            'ignored because expected target is symbolic',
        );
        $t->same(null, $plainSymbolicStore->reflogEntries('refs/heads/symbolic-object-guard'));

        $objectEntriesBefore = $store->reflogEntries('refs/heads/object-update');
        $t->same(null, $objectEntriesBefore);
        $store->updateWithReport(
            'refs/heads/object-update',
            ReferenceTarget::object($new),
            ReferenceStore::PREVIOUS_EXISTING_MUST_MATCH,
            ReferenceTarget::object($old),
            false,
            'sha1',
            $committer,
            'normal object update still uses object target',
        );
        $objectEntries = $store->reflogEntries('refs/heads/object-update');
        $t->same(1, count($objectEntries ?? []));
        $t->same($zeros, $objectEntries[0]->previousOid);
        $t->same($new, $objectEntries[0]->newOid);
    },
    'reflog append preserves carriage returns while rejecting line feeds like upstream' => static function (TestRunner $t) use ($old, $new, $other, $zeros): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-reflog-cr-message-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $committer = new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000');
        $message = "deploy: publish audited blocks\rprogress=done";

        $line = ReflogEntry::appendLine(
            ReferenceTarget::object($old),
            ReferenceTarget::object($new),
            $committer,
            $message,
        );
        $t->same("{$old} {$new} Deploy Bot <deploy@example.com> 1234 +0000\t{$message}\n", $line);

        $entry = ReflogEntry::parse($line);
        $t->same($message, $entry->message);
        $t->same($line, $entry->storageBytes());

        $emptyStored = (new ReflogEntry($zeros, $old, $committer, ''))->storageBytes();
        $t->same("{$zeros} {$old} Deploy Bot <deploy@example.com> 1234 +0000\t\n", $emptyStored);

        $store->appendReflog(
            'refs/heads/main',
            ReferenceTarget::object($old),
            ReferenceTarget::object($new),
            $committer,
            $message,
        );
        $entries = $store->reflogEntries('refs/heads/main');
        $t->same(1, count($entries ?? []));
        $t->same($message, $entries[0]->message);
        $t->contains("\rprogress=done\n", (string) $store->reflogContents('refs/heads/main'));

        $preparedStore = new ReferenceStore($dir . '-prepared');
        $preparedStore
            ->prepareLooseUpdateTransaction(
                ['refs/heads/prepared' => ReferenceTarget::object($other)],
                'sha1',
                $committer,
                $message,
                true,
            )
            ->commit();
        $preparedEntries = $preparedStore->reflogEntries('refs/heads/prepared');
        $t->same(1, count($preparedEntries ?? []));
        $t->same($message, $preparedEntries[0]->message);
        $t->same($zeros, $preparedEntries[0]->previousOid);

        $t->throws(
            InvalidArgumentException::class,
            static fn () => ReflogEntry::appendLine(
                ReferenceTarget::object($old),
                ReferenceTarget::object($new),
                $committer,
                "bad\nline",
            ),
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $store->appendReflog(
                'refs/heads/line-feed',
                null,
                ReferenceTarget::object($new),
                $committer,
                "bad\nline",
            ),
        );
        $t->throws(
            InvalidArgumentException::class,
            static function () use ($dir, $committer, $new): void {
                $lineFeedStore = new ReferenceStore($dir . '-line-feed-prepared');
                $lineFeedStore
                    ->prepareLooseUpdateTransaction(
                        ['refs/heads/prepared' => ReferenceTarget::object($new)],
                        'sha1',
                        $committer,
                        "bad\nline",
                        true,
                    )
                    ->commit();
            },
        );
    },
    'reflog parser reports malformed iterator entries with line numbers' => static function (TestRunner $t) use ($old, $new, $zeros): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-reflog-malformed-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        mkdir($dir . '/logs/refs/heads', 0777, true);
        file_put_contents(
            $dir . '/logs/refs/heads/main',
            "{$zeros} {$old} Deploy Bot <deploy@example.com> 1234 +0000\tcreated\n"
            . "not-a-reflog-entry\n"
            . "{$old} {$new} Deploy Bot <deploy@example.com> 1235 +0000\tupdated\n",
        );

        try {
            $store->reflogEntries('refs/heads/main');
            $t->same(true, false, 'malformed reflog line should fail');
        } catch (InvalidArgumentException $exception) {
            $t->contains('line 2', $exception->getMessage());
            $t->contains('not-a-reflog-entry', $exception->getMessage());
        }

        try {
            $store->reflogEntriesReverse('refs/heads/main');
            $t->same(true, false, 'malformed reverse reflog line should fail');
        } catch (InvalidArgumentException $exception) {
            $t->contains('line 2 from end', $exception->getMessage());
        }
    },
    'reflog iterator results keep parsing after malformed entries' => static function (TestRunner $t) use ($old, $new, $other, $zeros): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-reflog-result-iterator-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        mkdir($dir . '/logs/refs/heads', 0777, true);
        file_put_contents(
            $dir . '/logs/refs/heads/main',
            "{$zeros} {$old} Deploy Bot <deploy@example.com> 1234 +0000\tcreated\n"
            . "not-a-reflog-entry\n"
            . "{$old} {$new} Deploy Bot <deploy@example.com> 1235 +0000\tupdated\n"
            . "{$new} {$other} Deploy Bot <deploy@example.com> 1236 +0000\tfinal",
        );

        $forward = $store->reflogEntryResults('refs/heads/main');
        $reverse = $store->reflogEntryResultsReverse('refs/heads/main');

        $t->same([true, false, true, true], array_map(static fn (array $result): bool => $result['ok'], $forward ?? []));
        $t->same([1, 2, 3, 4], array_map(static fn (array $result): int => $result['line'], $forward ?? []));
        $t->same('created', $forward[0]['entry']->message ?? null);
        $t->same('updated', $forward[2]['entry']->message ?? null);
        $t->same('final', $forward[3]['entry']->message ?? null);
        $t->contains('In line 2:', $forward[1]['error'] ?? '');
        $t->contains('not-a-reflog-entry', $forward[1]['raw'] ?? '');

        $t->same([true, true, false, true], array_map(static fn (array $result): bool => $result['ok'], $reverse ?? []));
        $t->same([1, 2, 3, 4], array_map(static fn (array $result): int => $result['line'], $reverse ?? []));
        $t->same('final', $reverse[0]['entry']->message ?? null);
        $t->same('updated', $reverse[1]['entry']->message ?? null);
        $t->same('created', $reverse[3]['entry']->message ?? null);
        $t->contains('In line 3 from the end:', $reverse[2]['error'] ?? '');
    },
    'reflog bounded reverse iterator reports fixed-buffer boundaries like upstream' => static function (TestRunner $t) use ($old, $new, $zeros): void {
        $first = "1000000000000000000000000000000000000000 234385f6d781b7e97062102c6a483440bfda2a03 committer <committer@example.com> 946771200 +0000\tcommit (initial): c2";
        $second = "{$zeros} {$old} committer <committer@example.com> 946771200 +0000\tcommit (initial): c1";

        foreach ([$first . "\n" . $second, $first . "\n" . $second . "\n"] as $bytes) {
            $results = ReflogEntry::iterateReverseBounded($bytes, 256);
            $t->same([true, true], array_map(static fn (array $result): bool => $result['ok'], $results));
            $t->same([1, 2], array_map(static fn (array $result): int => $result['line'], $results));
            $t->same(['commit (initial): c1', 'commit (initial): c2'], array_map(static fn (array $result): ?string => $result['entry']->message ?? null, $results));
            $t->same([$old, '234385f6d781b7e97062102c6a483440bfda2a03'], array_map(static fn (array $result): ?string => $result['entry']->newOid ?? null, $results));
        }

        $t->throws(
            InvalidArgumentException::class,
            static fn () => ReflogEntry::iterateReverseBounded($second, 0),
        );

        $tooSmall = ReflogEntry::iterateReverseBounded($second, 128);
        $t->same(1, count($tooSmall));
        $t->same(false, $tooSmall[0]['ok']);
        $t->same(true, $tooSmall[0]['fromEnd']);
        $t->same(true, $tooSmall[0]['bufferTooSmall'] ?? false);
        $t->same(1, $tooSmall[0]['line']);
        $t->same(true, strlen($tooSmall[0]['raw']) <= 128);
        $t->contains('buffer too small for line size', $tooSmall[0]['error'] ?? '');
        $t->contains('\tcommit (initial): c1', $tooSmall[0]['error'] ?? '');

        $dir = sys_get_temp_dir() . '/port-libs-git-reflog-bounded-reverse-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $committer = new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000');
        $store->appendReflog(
            'refs/heads/main',
            ReferenceTarget::object($old),
            ReferenceTarget::object($new),
            $committer,
            str_repeat('x', 96),
        );

        $storeResults = $store->reflogEntryResultsReverseBounded('refs/heads/main', 4096);
        $t->same(true, $storeResults[0]['ok'] ?? false);
        $t->same(str_repeat('x', 96), $storeResults[0]['entry']->message ?? null);

        $storeTooSmall = $store->reflogEntryResultsReverseBounded('refs/heads/main', 80);
        $t->same(false, $storeTooSmall[0]['ok'] ?? true);
        $t->same(true, $storeTooSmall[0]['bufferTooSmall'] ?? false);
    },
    'reflog entries preserve sha256 object ids like report-status-v2 refs' => static function (TestRunner $t): void {
        $old = str_repeat('1', 64);
        $new = str_repeat('2', 64);
        $dir = sys_get_temp_dir() . '/port-libs-git-reflog-sha256-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $committer = new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000');

        $store->appendReflog(
            'refs/heads/sha256-main',
            ReferenceTarget::object($old, 'sha256'),
            ReferenceTarget::object($new, 'sha256'),
            $committer,
            'sha256 deployment ref',
            true,
            'sha256',
        );

        $entries = $store->reflogEntries('refs/heads/sha256-main', 'sha256');
        $t->same(1, count($entries ?? []));
        $t->same($old, $entries[0]->previousOid);
        $t->same($new, $entries[0]->newOid);
        $t->same('sha256 deployment ref', $entries[0]->message);

        $roundTrip = ReflogEntry::parse($entries[0]->storageBytes(), 'sha256');
        $t->same($old, $roundTrip->previousOid);
        $t->same($new, $roundTrip->newOid);
    },
    'wordpress reflog audit example parses newest deployment entries first' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-reflog-audit.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-reflog-audit.php';

        $t->same($fixture['siteRef'], $summary['siteRef']);
        $t->same(2, $summary['lineCount']);
        $t->same($fixture['expectedForwardMessages'], $summary['forwardMessages']);
        $t->same($fixture['expectedReverseNewOids'], $summary['reverseNewOids']);
        $t->same(array_reverse($fixture['expectedForwardMessages']), $summary['boundedReverseMessages']);
        $t->same($fixture['previousCommit'], $summary['oldestPreviousOid']);
        $t->same($fixture['rolledBackCommit'], $summary['latestNewOid']);
        $t->same('WordPress Deploy Bot <deploy@example.com>', $summary['trimmedCommitter']);
        $t->contains($fixture['messages'][0], (string) $summary['rawReflog']);
        $t->contains($fixture['messages'][1], (string) $summary['rawReflog']);
        $t->same($fixture['symbolicSiteRef'], $summary['symbolicRef']);
        $t->same($fixture['symbolicReferentRef'], $summary['symbolicTarget']);
        $t->same(false, $summary['symbolicReferentExists']);
        $t->same([$fixture['symbolicMessage']], $summary['symbolicReflogMessages']);
        $t->same([str_repeat('0', 40)], $summary['symbolicReflogPreviousOids']);
        $t->same([$fixture['publishedCommit']], $summary['symbolicReflogNewOids']);
        $t->same(false, $summary['smallBufferReverseDiagnostics'][0]['ok'] ?? true);
        $t->same(true, $summary['smallBufferReverseDiagnostics'][0]['fromEnd'] ?? false);
        $t->same(true, $summary['smallBufferReverseDiagnostics'][0]['bufferTooSmall'] ?? false);
        $t->contains('buffer too small for line size', $summary['smallBufferReverseDiagnostics'][0]['error'] ?? '');
        $t->same([false, true, true], array_map(static fn (array $result): bool => $result['ok'], $summary['corruptLineDiagnostics']));
        $t->contains('In line 1:', $summary['corruptLineDiagnostics'][0]['error'] ?? '');
        $t->contains($fixture['corruptLine'], $summary['corruptLineDiagnostics'][0]['error'] ?? '');
        $t->contains('git reflog', $summary['wordpressUse']);
    },
];
