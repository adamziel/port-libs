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
        $t->same($fixture['previousCommit'], $summary['oldestPreviousOid']);
        $t->same($fixture['rolledBackCommit'], $summary['latestNewOid']);
        $t->same('WordPress Deploy Bot <deploy@example.com>', $summary['trimmedCommitter']);
        $t->contains($fixture['messages'][0], (string) $summary['rawReflog']);
        $t->contains($fixture['messages'][1], (string) $summary['rawReflog']);
        $t->contains('git reflog', $summary['wordpressUse']);
    },
];
