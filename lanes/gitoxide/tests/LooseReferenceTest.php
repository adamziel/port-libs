<?php

declare(strict_types=1);

use PortLibs\Gitoxide\LooseReference;
use PortLibs\Gitoxide\LooseReferenceStore;
use PortLibs\Gitoxide\ReferenceName;

$sha1 = 'c5241b835b93af497cda80ce0dceb8f49800df1c';
$sha256 = str_repeat('a', 64);

return [
    'parses direct loose ref and normalizes uppercase ids' => static function (TestRunner $t) use ($sha1): void {
        $reference = LooseReference::parse('refs/heads/main', strtoupper($sha1) . "\n");
        $t->same('object', $reference->kind());
        $t->same($sha1, $reference->target->value);
        $t->same($sha1 . "\n", $reference->storageBytes());
    },
    'direct loose refs accept fetch-head style data after id' => static function (TestRunner $t) use ($sha1): void {
        $reference = LooseReference::parse('FETCH_HEAD', $sha1 . "\t\tbranch 'main' of https://example.test/repo\n");
        $t->same('object', $reference->kind());
        $t->same($sha1, $reference->target->value);
    },
    'parses sha256 direct loose ref when requested' => static function (TestRunner $t) use ($sha256): void {
        $reference = LooseReference::parse('refs/heads/main', $sha256, 'sha256');
        $t->same('object', $reference->kind());
        $t->same($sha256, $reference->target->value);
    },
    'rejects sha256 sized id and trailing hex in sha1 mode' => static function (TestRunner $t) use ($sha1, $sha256): void {
        $t->throws(InvalidArgumentException::class, static fn () => LooseReference::parse('refs/heads/main', $sha256));
        $t->throws(InvalidArgumentException::class, static fn () => LooseReference::parse('refs/heads/main', $sha1 . 'extra'));
    },
    'parses symbolic loose ref with extra spaces before target' => static function (TestRunner $t): void {
        $reference = LooseReference::parse('HEAD', "ref:        refs/heads/main\r\n");
        $t->same('symbolic', $reference->kind());
        $t->same('refs/heads/main', $reference->target->value);
        $t->same("ref: refs/heads/main\n", $reference->storageBytes());
    },
    'rejects invalid symbolic targets and unsafe ref names' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => LooseReference::parse('HEAD', "ref: refs/heads/bad lock\n"));
        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::assertValid('../escaping'));
        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::assertValid('refs/heads/main.lock'));
        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::assertValid('refs/heads/main.'));
    },
    'loose reference store writes direct and symbolic files' => static function (TestRunner $t) use ($sha1): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-refs-' . bin2hex(random_bytes(4));
        $store = new LooseReferenceStore($dir);
        $store->writeSymbolic('HEAD', 'refs/heads/main');
        $store->writeDirect('refs/heads/main', $sha1);

        $t->same("ref: refs/heads/main\n", (string) file_get_contents($dir . '/HEAD'));
        $t->same($sha1 . "\n", (string) file_get_contents($dir . '/refs/heads/main'));
        $t->same('refs/heads/main', $store->read('HEAD')->target->value);
        $t->same($sha1, $store->read('refs/heads/main')->target->value);
    },
    'wordpress fixture stores deploy branch references without git binary' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-references.php';
        $dir = sys_get_temp_dir() . '/port-libs-wp-refs-' . bin2hex(random_bytes(4));
        $store = new LooseReferenceStore($dir);
        $store->writeSymbolic('HEAD', $fixture['defaultBranch']);
        $store->writeDirect($fixture['defaultBranch'], $fixture['commitOid']);
        $store->writeSymbolic('refs/remotes/origin/HEAD', $fixture['defaultBranch']);

        $t->same('symbolic', $store->read('HEAD')->kind());
        $t->same($fixture['defaultBranch'], $store->read('HEAD')->target->value);
        $t->same($fixture['commitOid'], $store->read($fixture['defaultBranch'])->target->value);
        $t->same($fixture['defaultBranch'], $store->read('refs/remotes/origin/HEAD')->target->value);
    },
];
