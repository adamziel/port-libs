<?php

declare(strict_types=1);

use PortLibs\Gitoxide\PackedReferences;
use PortLibs\Gitoxide\ReferenceStore;

$old = '134385f6d781b7e97062102c6a483440bfda2a03';
$new = 'a98ad44f7f0d6eae901abe9c6f10b4d9be2a190f';
$tag = 'b3109a7e51fc593f85b145a76c70ddd1d133fafd';

return [
    'reference store finds loose refs before packed refs' => static function (TestRunner $t) use ($old, $new, $tag): void {
        $packed = PackedReferences::fromBytes("# pack-refs with: peeled fully-peeled sorted\n"
            . "{$old} refs/heads/A\n"
            . "{$old} refs/heads/main\n"
            . "{$tag} refs/tags/tag-object\n"
            . "^{$old}\n");
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-store-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir, $packed);
        $store->looseStore()->writeDirect('refs/heads/main', $new);

        $main = $store->find('main');
        $t->same('loose', $main->source);
        $t->same($new, $main->targetObjectId());
        $t->same('packed', $store->find('A')->source);
        $t->same($old, $store->find('tag-object')->objectId());
    },
    'reference store opens packed refs from git directory' => static function (TestRunner $t) use ($old): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-store-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/packed-refs', "{$old} refs/heads/main\n");

        $reference = ReferenceStore::at($dir)->find('main');
        $t->same('packed', $reference->source);
        $t->same('refs/heads/main', $reference->name);
        $t->same($old, $reference->targetObjectId());
    },
    'reference store honors loose remote head special case' => static function (TestRunner $t) use ($old): void {
        $packed = PackedReferences::fromBytes("{$old} refs/remotes/origin/main\n");
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-store-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir, $packed);
        $store->looseStore()->writeSymbolic('refs/remotes/origin/HEAD', 'refs/remotes/origin/main');

        $origin = $store->find('origin');
        $t->same('loose', $origin->source);
        $t->same('symbolic', $origin->kind());
        $t->same('refs/remotes/origin/main', $origin->target->value);
        $t->same('packed', $store->find('origin/main')->source);
    },
    'wordpress fixture resolves packed branch and release tag through reference store' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-packed-refs.php';
        $dir = sys_get_temp_dir() . '/port-libs-wp-ref-store-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir, PackedReferences::fromBytes($fixture['content']));
        $store->looseStore()->writeSymbolic('HEAD', 'refs/heads/main');

        $t->same('loose', $store->find('HEAD')->source);
        $t->same($fixture['branchCommit'], $store->find($fixture['branch'])->targetObjectId());
        $t->same($fixture['tagCommit'], $store->find($fixture['releaseTag'])->objectId());
    },
];
