<?php

declare(strict_types=1);

use PortLibs\Gitoxide\PackedReferences;

$sha1 = 'd53c4b0f91f1b29769c9430f2d1c0bcab1170c75';
$peeled = 'e9cdc958e7ce2290e2d7958cdb5aa9323ef35d37';

return [
    'parses packed refs header traits and sorted flag' => static function (TestRunner $t): void {
        $packed = PackedReferences::fromBytes("# pack-refs with: peeled fully-peeled sorted  \n");
        $t->same(true, $packed->hasHeader());
        $t->same(true, $packed->headerSorted());
        $t->same(['peeled', 'fully-peeled', 'sorted'], $packed->headerTraits());
        $t->same([], $packed->all());
    },
    'parses uppercase packed ref and peeled object ids' => static function (TestRunner $t) use ($sha1, $peeled): void {
        $packed = PackedReferences::fromBytes(strtoupper($sha1) . " refs/heads/uppercase\n^" . strtoupper($peeled) . "\n");
        $reference = $packed->find('refs/heads/uppercase');
        $t->same('refs/heads/uppercase', $reference->name);
        $t->same($sha1, $reference->targetObjectId());
        $t->same($peeled, $reference->objectId());
    },
    'parses sha256 packed refs when requested' => static function (TestRunner $t): void {
        $sha256 = str_repeat('a', 64);
        $packed = PackedReferences::fromBytes($sha256 . " refs/heads/main\n", 'sha256');
        $reference = $packed->find('main');
        $t->same('refs/heads/main', $reference->name);
        $t->same($sha256, $reference->targetObjectId());
    },
    'rejects invalid packed ref inputs' => static function (TestRunner $t) use ($sha1): void {
        $t->throws(InvalidArgumentException::class, static fn () => PackedReferences::fromBytes("# some user comment\n"));
        $t->throws(InvalidArgumentException::class, static fn () => PackedReferences::fromBytes("^{$sha1}\n"));
        $t->throws(InvalidArgumentException::class, static fn () => PackedReferences::fromBytes(str_repeat('a', 64) . " refs/heads/main\n"));
        $t->throws(InvalidArgumentException::class, static fn () => PackedReferences::fromBytes("{$sha1} refs/heads/bad lock\n"));
    },
    'maps upstream packed refs fixture without header' => static function (TestRunner $t): void {
        $packed = PackedReferences::open(dirname(__DIR__) . '/fixtures/packed-refs-without-header');
        $t->same(false, $packed->hasHeader());
        $t->same(
            ['refs/heads/main', 'refs/heads/newer-as-loose', 'refs/remotes/origin/main', 'refs/tags/tag-object'],
            $packed->names()
        );
        $t->same('refs/heads/main', $packed->find('main')->name);
        $t->same('refs/remotes/origin/main', $packed->find('origin/main')->name);
        $t->same('134385f6d781b7e97062102c6a483440bfda2a03', $packed->find('tag-object')->objectId());
    },
    'sorts upstream unsorted packed refs fixture before lookup' => static function (TestRunner $t): void {
        $packed = PackedReferences::open(dirname(__DIR__) . '/fixtures/packed-refs-unsorted');
        $t->same(false, $packed->headerSorted());
        $t->same(
            ['refs/heads/main', 'refs/heads/newer-as-loose', 'refs/remotes/origin/main', 'refs/tags/tag-object'],
            $packed->names()
        );
        $t->same('refs/heads/main', $packed->find('main')->name);
        $t->same('refs/tags/tag-object', $packed->find('tag-object')->name);
    },
    'applies packed partial lookup disambiguation rules' => static function (TestRunner $t): void {
        $content = "# pack-refs with: peeled fully-peeled sorted\n"
            . str_repeat('a', 40) . " refs/heads/head-or-tag\n"
            . str_repeat('b', 40) . " refs/heads/main\n"
            . str_repeat('c', 40) . " refs/remotes/origin/main\n"
            . str_repeat('d', 40) . " refs/tags/head-or-tag\n"
            . str_repeat('e', 40) . " refs/tags/tag-object\n"
            . "^" . str_repeat('f', 40) . "\n";
        $packed = PackedReferences::fromBytes($content);

        $t->same(null, $packed->tryFind('HEAD'));
        $t->same('refs/tags/head-or-tag', $packed->find('head-or-tag')->name);
        $t->same('refs/heads/head-or-tag', $packed->find('heads/head-or-tag')->name);
        $t->same('refs/heads/main', $packed->find('main')->name);
        $t->same('refs/remotes/origin/main', $packed->find('origin/main')->name);
        $t->same(str_repeat('f', 40), $packed->find('tag-object')->objectId());
    },
    'wordpress fixture maps packed branch and release tag refs without git binary' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-packed-refs.php';
        $packed = PackedReferences::fromBytes($fixture['content']);

        $t->same($fixture['branchCommit'], $packed->find($fixture['branch'])->targetObjectId());
        $t->same($fixture['remoteCommit'], $packed->find($fixture['remoteBranch'])->targetObjectId());
        $t->same($fixture['tagObject'], $packed->find($fixture['releaseTag'])->targetObjectId());
        $t->same($fixture['tagCommit'], $packed->find($fixture['releaseTag'])->objectId());
    },
    'wordpress packed refs example peels prefixed release candidates through packed data' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-packed-refs.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-packed-refs.php';

        $t->same($fixture['branchCommit'], $summary['branch']['commit']);
        $t->same($fixture['tagObject'], $summary['releaseTag']['tagObject']);
        $t->same($fixture['tagCommit'], $summary['releaseTag']['peeledCommit']);
        $t->same($fixture['expectedPeeledHeads'], $summary['peeledHeads']);
    },
];
