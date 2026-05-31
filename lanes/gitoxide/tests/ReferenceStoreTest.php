<?php

declare(strict_types=1);

use PortLibs\Gitoxide\PackedReferences;
use PortLibs\Gitoxide\CommitSignature;
use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\GitTag;
use PortLibs\Gitoxide\LooseObjectStore;
use PortLibs\Gitoxide\ObjectDatabase;
use PortLibs\Gitoxide\ReferenceName;
use PortLibs\Gitoxide\ReferenceStore;
use PortLibs\Gitoxide\ReferenceTarget;
use PortLibs\Gitoxide\ReferenceTransactionEdit;

$old = '134385f6d781b7e97062102c6a483440bfda2a03';
$new = 'a98ad44f7f0d6eae901abe9c6f10b4d9be2a190f';
$tag = 'b3109a7e51fc593f85b145a76c70ddd1d133fafd';
$other = '28ce6a8b26aa170e1de65536fe8abe1832bd3242';

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
    'namespaced reference store iteration is transparent like upstream gix ref' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-namespaced-refs.php';
        $dir = sys_get_temp_dir() . '/port-libs-wp-namespaced-ref-store-' . bin2hex(random_bytes(4));

        foreach ($fixture['looseRefs'] as $name => $contents) {
            $path = $dir . '/' . $name;
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            file_put_contents($path, $contents);
        }

        $store = new ReferenceStore($dir, PackedReferences::fromBytes($fixture['packedRefs']), $fixture['namespace']);

        $t->same($fixture['expectedStoreNames'], array_map(static fn ($reference): string => $reference->name, $store->all()));
        $t->same($fixture['expectedLooseNames'], array_map(static fn ($reference): string => $reference->name, $store->looseAll()));
        $t->same(
            ['refs/heads/review/plugin-a'],
            array_map(static fn ($reference): string => $reference->name, $store->prefixed('refs/heads/review/')),
        );
        $t->same(
            ['refs/remotes/origin/review/plugin-a'],
            array_map(static fn ($reference): string => $reference->name, $store->prefixed('refs/remotes/origin/review')),
        );

        $review = $store->find('heads/review/plugin-a');
        $t->same('refs/heads/review/plugin-a', $review->name);
        $t->same('loose', $review->source);
        $t->same($fixture['reviewCommit'], $review->targetObjectId());

        $remote = $store->find('refs/remotes/origin/review/plugin-a');
        $t->same('packed', $remote->source);
        $t->same($fixture['remoteCommit'], $remote->targetObjectId());

        $alias = $store->find('review-alias');
        $t->same('refs/review-alias', $alias->name);
        $t->same('symbolic', $alias->kind());
        $t->same('refs/heads/review/plugin-a', $alias->target->value);

        $t->same(
            null,
            $store->tryFind(ReferenceName::prefixNamespace('refs/heads/review/plugin-a', $fixture['namespace'])),
            'a namespace-aware store does not find refs by redundantly namespaced store-relative names',
        );

        $plainStore = new ReferenceStore($dir, PackedReferences::fromBytes($fixture['packedRefs']));
        $t->same(
            $fixture['expectedFullNamespacedNames'],
            array_map(
                static fn ($reference): string => $reference->name,
                $plainStore->prefixed(ReferenceName::expandNamespace($fixture['namespace'])),
            ),
        );
    },
    'wordpress namespaced reference example scopes multisite review refs without git binary' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-namespaced-refs.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-namespaced-reference-store.php';

        $t->same($fixture['namespace'], $summary['namespace']);
        $t->same(ReferenceName::expandNamespace($fixture['namespace']), $summary['namespacedPrefix']);
        $t->same($fixture['expectedStoreNames'], $summary['storeRelativeNames']);
        $t->same($fixture['expectedLooseNames'], $summary['storeRelativeLooseNames']);
        $t->same($fixture['expectedFullNamespacedNames'], $summary['fullNamespacedNames']);
        $t->same($fixture['reviewCommit'], $summary['reviewBranchCommit']);
        $t->same($fixture['remoteCommit'], $summary['remoteReviewCommit']);
        $t->same('refs/heads/review/plugin-a', $summary['aliasTarget']);
        $t->same(true, $summary['redundantNamespaceLookup']);
    },
    'reference store update enforces upstream previous value constraints' => static function (TestRunner $t) use ($old, $new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-transaction-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);

        $t->throws(
            RuntimeException::class,
            static fn () => $store->update(
                'refs/heads/main',
                ReferenceTarget::object($old),
                ReferenceStore::PREVIOUS_MUST_EXIST,
            ),
        );

        $created = $store->update(
            'refs/heads/main',
            ReferenceTarget::object($old),
            ReferenceStore::PREVIOUS_MUST_NOT_EXIST,
        );
        $t->same('refs/heads/main', $created->name);
        $t->same('loose', $created->source);
        $t->same($old, $created->targetObjectId());

        $sameValue = $store->update(
            'refs/heads/main',
            ReferenceTarget::object($old),
            ReferenceStore::PREVIOUS_MUST_NOT_EXIST,
        );
        $t->same($old, $sameValue->targetObjectId(), 'MustNotExist allows a no-op when the target already matches');

        $t->throws(
            RuntimeException::class,
            static fn () => $store->update(
                'refs/heads/main',
                ReferenceTarget::object($new),
                ReferenceStore::PREVIOUS_MUST_NOT_EXIST,
            ),
        );
        $t->throws(
            RuntimeException::class,
            static fn () => $store->update(
                'refs/heads/main',
                ReferenceTarget::object($new),
                ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
                ReferenceTarget::object($new),
            ),
        );

        $updated = $store->update(
            'refs/heads/main',
            ReferenceTarget::object($new),
            ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
            ReferenceTarget::object($old),
        );
        $t->same($new, $updated->targetObjectId());

        $createdWithMayExist = $store->update(
            'refs/heads/review',
            ReferenceTarget::object($old),
            ReferenceStore::PREVIOUS_EXISTING_MUST_MATCH,
            ReferenceTarget::object($new),
        );
        $t->same($old, $createdWithMayExist->targetObjectId());
    },
    'reference store delete follows upstream missing and match constraints' => static function (TestRunner $t) use ($old, $new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-delete-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);

        $t->same(null, $store->deleteReference('refs/heads/missing'));
        $t->same(
            null,
            $store->deleteReference(
                'refs/heads/missing',
                ReferenceStore::PREVIOUS_EXISTING_MUST_MATCH,
                ReferenceTarget::object($old),
            ),
        );
        $t->throws(
            RuntimeException::class,
            static fn () => $store->deleteReference('refs/heads/missing', ReferenceStore::PREVIOUS_MUST_EXIST),
        );

        $store->update('refs/heads/main', ReferenceTarget::object($old));
        $t->throws(
            RuntimeException::class,
            static fn () => $store->deleteReference(
                'refs/heads/main',
                ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
                ReferenceTarget::object($new),
            ),
        );

        $deleted = $store->deleteReference(
            'refs/heads/main',
            ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
            ReferenceTarget::object($old),
        );
        $t->same('refs/heads/main', $deleted?->name);
        $t->same($old, $deleted?->targetObjectId());
        $t->same(null, $store->tryFind('refs/heads/main'));

        $packedStore = new ReferenceStore($dir, PackedReferences::fromBytes("{$old} refs/heads/packed-only\n"));
        $packedDeleted = $packedStore->deleteReference('refs/heads/packed-only');
        $t->same($old, $packedDeleted?->targetObjectId());
        $t->same(null, $packedStore->tryFind('refs/heads/packed-only'));
    },
    'reference store delete rewrites packed refs and removes stale loose overlays' => static function (TestRunner $t) use ($old, $new, $other): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-packed-delete-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        file_put_contents(
            $dir . '/packed-refs',
            "# pack-refs with: peeled fully-peeled sorted \n"
            . "{$old} refs/heads/main\n"
            . "{$other} refs/heads/side\n",
        );
        $store = ReferenceStore::at($dir);
        $store->looseStore()->writeDirect('refs/heads/main', $new);
        $store->appendReflog(
            'refs/heads/main',
            ReferenceTarget::object($old),
            ReferenceTarget::object($new),
            new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000'),
            'deploy: publish content',
            true,
        );

        $deleted = $store->deleteReference(
            'refs/heads/main',
            ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
            ReferenceTarget::object($new),
        );

        $t->same($new, $deleted?->targetObjectId());
        $t->same(null, $store->tryFind('refs/heads/main'));
        $t->same(false, is_file($dir . '/refs/heads/main'));
        $t->same(false, $store->reflogExists('refs/heads/main'));
        $packed = PackedReferences::open($dir . '/packed-refs');
        $t->same(['refs/heads/side'], $packed->names());
        $t->same($other, $packed->find('refs/heads/side')->targetObjectId());
    },
    'reference store deletes broken loose refs with permissive constraints like upstream gix ref' => static function (TestRunner $t) use ($old): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-delete-broken-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        mkdir($dir . '/refs/heads', 0777, true);
        mkdir($dir . '/logs/refs/heads', 0777, true);
        file_put_contents($dir . '/refs/heads/broken', 'not-an-object-id');
        file_put_contents($dir . '/logs/refs/heads/broken', 'stale audit log');

        $result = $store->deleteWithReport('refs/heads/broken', ReferenceStore::PREVIOUS_MUST_EXIST);

        $t->same(null, $result->reference);
        $t->same(['refs/heads/broken'], array_map(static fn ($edit): string => $edit->name, $result->edits));
        $t->same([null], array_map(static fn ($edit): mixed => $edit->previousTarget?->value, $result->edits));
        $t->same([true], array_map(static fn ($edit): bool => $edit->updatesReference, $result->edits));
        $t->same(false, is_file($dir . '/refs/heads/broken'));
        $t->same(false, is_file($dir . '/logs/refs/heads/broken'));

        mkdir($dir . '/refs/heads', 0777, true);
        file_put_contents($dir . '/refs/heads/broken', 'still-not-an-object-id');
        $t->throws(
            RuntimeException::class,
            static fn () => $store->deleteReference(
                'refs/heads/broken',
                ReferenceStore::PREVIOUS_EXISTING_MUST_MATCH,
                ReferenceTarget::object($old),
            ),
        );
        $t->same('still-not-an-object-id', file_get_contents($dir . '/refs/heads/broken'));
    },
    'reference store update leaves default packed refs shadowed by loose refs' => static function (TestRunner $t) use ($old, $new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-packed-shadow-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/packed-refs', "{$old} refs/heads/main\n");
        $store = ReferenceStore::at($dir);

        $updated = $store->update(
            'refs/heads/main',
            ReferenceTarget::object($new),
            ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
            ReferenceTarget::object($old),
        );

        $t->same('loose', $updated->source);
        $t->same($new, $updated->targetObjectId());
        $t->same($old, PackedReferences::open($dir . '/packed-refs')->find('refs/heads/main')->targetObjectId());
        $t->same("{$new}\n", file_get_contents($dir . '/refs/heads/main'));
        $t->same(false, is_file($dir . '/packed-refs.lock'));
    },
    'reference store refreshes packed ref buffers after external file changes' => static function (TestRunner $t) use ($old, $new, $other): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-packed-refresh-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/packed-refs', "{$old} refs/heads/main\n");
        $store = ReferenceStore::at($dir);

        $t->same($old, $store->find('refs/heads/main')->targetObjectId());

        file_put_contents($dir . '/packed-refs', "{$new} refs/heads/main\n{$other} refs/heads/review\n");
        $t->same($new, $store->find('refs/heads/main')->targetObjectId());
        $t->same($other, $store->find('refs/heads/review')->targetObjectId());
        $t->same(['refs/heads/main', 'refs/heads/review'], array_map(static fn ($reference): string => $reference->name, $store->prefixed('refs/heads/')));

        unlink($dir . '/packed-refs');
        $t->same(null, $store->tryFind('refs/heads/main'));

        file_put_contents($dir . '/packed-refs', "{$other} refs/heads/main\n");
        $t->same($other, $store->find('refs/heads/main')->targetObjectId());
    },
    'reference store packed update mode rewrites packed refs and can prune loose source' => static function (TestRunner $t) use ($old, $new, $other): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-packed-update-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/packed-refs', "{$old} refs/heads/main\n{$other} refs/heads/side\n");
        $store = ReferenceStore::at($dir);
        $store->looseStore()->writeDirect('refs/heads/main', $old);

        $updated = $store->update(
            'refs/heads/main',
            ReferenceTarget::object($new),
            ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
            ReferenceTarget::object($old),
            false,
            'sha1',
            new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000'),
            'pack deployment branch',
            true,
            ReferenceStore::PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES_REMOVE_LOOSE_SOURCE_REFERENCE,
        );

        $packed = PackedReferences::open($dir . '/packed-refs');
        $t->same('packed', $updated->source);
        $t->same($new, $packed->find('refs/heads/main')->targetObjectId());
        $t->same($other, $packed->find('refs/heads/side')->targetObjectId());
        $t->same(false, is_file($dir . '/refs/heads/main'));
        $t->same(false, is_file($dir . '/packed-refs.lock'));
        $t->contains(
            "{$old} {$new} Deploy Bot <deploy@example.com> 1234 +0000\tpack deployment branch\n",
            (string) $store->reflogContents('refs/heads/main'),
        );
    },
    'reference store packed update refuses stale packed refs lock without side effects' => static function (TestRunner $t) use ($old, $new, $other): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-packed-lock-collision-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        $packedContents = "{$old} refs/heads/main\n{$other} refs/heads/side\n";
        file_put_contents($dir . '/packed-refs', $packedContents);
        file_put_contents($dir . '/packed-refs.lock', 'held by another transaction');
        $store = ReferenceStore::at($dir);

        $t->throws(
            RuntimeException::class,
            static fn () => $store->update(
                'refs/heads/main',
                ReferenceTarget::object($new),
                ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
                ReferenceTarget::object($old),
                false,
                'sha1',
                null,
                '',
                false,
                ReferenceStore::PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES_REMOVE_LOOSE_SOURCE_REFERENCE,
            ),
        );

        $t->same($packedContents, file_get_contents($dir . '/packed-refs'));
        $t->same('held by another transaction', file_get_contents($dir . '/packed-refs.lock'));
        $t->same(false, is_file($dir . '/refs/heads/main'));
        $t->same(null, $store->reflogContents('refs/heads/main'));
        $t->same($old, PackedReferences::open($dir . '/packed-refs')->find('refs/heads/main')->targetObjectId());
    },
    'reference store direct update refuses packed refs lock before loose or reflog side effects' => static function (TestRunner $t) use ($old, $new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-packed-direct-lock-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        $packedContents = "{$old} refs/heads/main\n";
        file_put_contents($dir . '/packed-refs', $packedContents);
        file_put_contents($dir . '/packed-refs.lock', 'held by another transaction');
        $store = ReferenceStore::at($dir);

        $t->throws(
            RuntimeException::class,
            static fn () => $store->update(
                'refs/heads/main',
                ReferenceTarget::object($new),
                ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
                ReferenceTarget::object($old),
                false,
                'sha1',
                new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000'),
                'direct packed ref update',
                true,
            ),
        );

        $t->same($packedContents, file_get_contents($dir . '/packed-refs'));
        $t->same('held by another transaction', file_get_contents($dir . '/packed-refs.lock'));
        $t->same(false, is_file($dir . '/refs/heads/main'));
        $t->same(false, is_file($dir . '/logs/refs/heads/main'));
        $t->same($old, PackedReferences::open($dir . '/packed-refs')->find('refs/heads/main')->targetObjectId());
    },
    'reference store direct creation refuses packed refs lock before creating loose refs' => static function (TestRunner $t) use ($new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-packed-direct-create-lock-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/packed-refs.lock', 'packed transaction in progress');
        $store = ReferenceStore::at($dir);

        $t->throws(
            RuntimeException::class,
            static fn () => $store->update(
                'refs/heads/new-review',
                ReferenceTarget::object($new),
                ReferenceStore::PREVIOUS_MUST_NOT_EXIST,
            ),
        );

        $t->same('packed transaction in progress', file_get_contents($dir . '/packed-refs.lock'));
        $t->same(false, is_dir($dir . '/refs'));
        $t->same(null, $store->tryFind('refs/heads/new-review'));
    },
    'reference store direct delete refuses packed refs lock before reflog or loose overlay removal' => static function (TestRunner $t) use ($old, $new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-packed-delete-lock-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        $packedContents = "{$old} refs/heads/main\n";
        file_put_contents($dir . '/packed-refs', $packedContents);
        $store = ReferenceStore::at($dir);
        $committer = new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000');
        $store->looseStore()->writeDirect('refs/heads/main', $new);
        $store->appendReflog(
            'refs/heads/main',
            ReferenceTarget::object($old),
            ReferenceTarget::object($new),
            $committer,
            'loose overlay audit',
            true,
        );
        $reflogBefore = (string) $store->reflogContents('refs/heads/main');
        file_put_contents($dir . '/packed-refs.lock', 'held by another transaction');

        $t->throws(
            RuntimeException::class,
            static fn () => $store->deleteReference(
                'refs/heads/main',
                ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
                ReferenceTarget::object($new),
            ),
        );

        $t->same($packedContents, file_get_contents($dir . '/packed-refs'));
        $t->same('held by another transaction', file_get_contents($dir . '/packed-refs.lock'));
        $t->same("{$new}\n", file_get_contents($dir . '/refs/heads/main'));
        $t->same($reflogBefore, $store->reflogContents('refs/heads/main'));
        $t->same($new, $store->find('refs/heads/main')->targetObjectId());
        $t->same($old, PackedReferences::open($dir . '/packed-refs')->find('refs/heads/main')->targetObjectId());
    },
    'reference store packed update mode refreshes stale packed refs even when loose value already matches' => static function (TestRunner $t) use ($old, $new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-packed-stale-refresh-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/packed-refs', "{$old} refs/heads/main\n");
        $store = ReferenceStore::at($dir);
        $store->looseStore()->writeDirect('refs/heads/main', $new);

        $updated = $store->update(
            'refs/heads/main',
            ReferenceTarget::object($new),
            ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
            ReferenceTarget::object($new),
            false,
            'sha1',
            null,
            '',
            false,
            ReferenceStore::PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES,
        );

        $t->same('loose', $updated->source);
        $t->same($new, PackedReferences::open($dir . '/packed-refs')->find('refs/heads/main')->targetObjectId());
        $t->same("{$new}\n", file_get_contents($dir . '/refs/heads/main'));
        $t->same(null, $store->reflogContents('refs/heads/main'));
    },
    'reference store packed update mode peels tag objects through object database' => static function (TestRunner $t) use ($old): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-packed-tag-peel-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/packed-refs', "{$old} refs/heads/main\n");

        $objects = new LooseObjectStore($dir);
        $commitObject = new GitObject(
            'commit',
            'tree ' . str_repeat('0', 40) . "\n"
            . "author Release Bot <release@example.com> 1770000000 +0000\n"
            . "committer Release Bot <release@example.com> 1770000000 +0000\n\n"
            . "Publish WordPress release package\n",
        );
        $commitId = $objects->write($commitObject);
        $tagObject = (new GitTag(
            $commitId,
            'commit',
            'wp-release-v2026.05',
            'Release Bot <release@example.com> 1770000000 +0000',
            "WordPress release package\n",
        ))->object();
        $tagId = $objects->write($tagObject);

        $store = ReferenceStore::at($dir);
        $updated = $store->update(
            'refs/tags/wp-release-v2026.05',
            ReferenceTarget::object($tagId),
            ReferenceStore::PREVIOUS_MUST_NOT_EXIST,
            null,
            false,
            'sha1',
            null,
            '',
            false,
            ReferenceStore::PACKED_DELETIONS_AND_NON_SYMBOLIC_UPDATES_REMOVE_LOOSE_SOURCE_REFERENCE,
            new ObjectDatabase($dir),
        );

        $packed = PackedReferences::open($dir . '/packed-refs');
        $release = $packed->find('refs/tags/wp-release-v2026.05');

        $t->same('packed', $updated->source);
        $t->same($tagId, $updated->targetObjectId());
        $t->same($commitId, $updated->objectId());
        $t->same($tagId, $release->targetObjectId());
        $t->same($commitId, $release->objectId());
        $t->contains("^{$commitId}\n", (string) file_get_contents($dir . '/packed-refs'));
        $t->same(false, is_file($dir . '/refs/tags/wp-release-v2026.05'));
    },
    'reference store peel uses packed peeled ids through symbolic refs without object lookup' => static function (TestRunner $t) use ($old, $tag): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-packed-peel-symbolic-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        file_put_contents(
            $dir . '/packed-refs',
            "# pack-refs with: peeled fully-peeled sorted \n"
            . "{$old} refs/heads/main\n"
            . "{$tag} refs/tags/wp-release-v2026.05\n"
            . "^{$old}\n",
        );
        $store = ReferenceStore::at($dir);
        $store->looseStore()->writeSymbolic('HEAD', 'refs/tags/wp-release-v2026.05');

        $release = $store->find('refs/tags/wp-release-v2026.05');
        $head = $store->find('HEAD');

        $t->same('packed', $release->source);
        $t->same('loose', $head->source);
        $t->same('symbolic', $head->kind());
        $t->same($tag, $release->targetObjectId());
        $t->same($old, $release->objectId());
        $t->same($tag, $store->followToObjectId('HEAD'));
        $t->same($tag, $store->followToObjectId('refs/tags/wp-release-v2026.05'));
        $t->same($old, $store->peelToObjectId('HEAD'));
        $t->same($old, $store->peelToObjectId('wp-release-v2026.05'));
    },
    'reference store prefixed peeled iteration uses packed peeled ids like upstream gix' => static function (TestRunner $t) use ($old, $new, $tag): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-packed-prefixed-peel-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        file_put_contents(
            $dir . '/packed-refs',
            "# pack-refs with: peeled fully-peeled sorted \n"
            . "{$old} refs/heads/main\n"
            . "{$tag} refs/heads/release-from-packed\n"
            . "^{$old}\n"
            . "{$new} refs/remotes/origin/main\n",
        );
        $store = ReferenceStore::at($dir);
        $store->looseStore()->writeSymbolic('refs/heads/release-candidate', 'refs/heads/release-from-packed');
        $store->looseStore()->writeSymbolic('refs/heads/remote-candidate', 'refs/remotes/origin/main');

        $peeled = $store->prefixedPeeled('refs/heads/');
        $tuples = array_map(
            static fn ($reference): array => [
                $reference->name,
                $reference->targetObjectId(),
                $reference->objectId(),
                $reference->source,
            ],
            $peeled,
        );

        $t->same(
            [
                ['refs/heads/main', $old, $old, 'packed'],
                ['refs/heads/release-from-packed', $old, $old, 'packed'],
                ['refs/heads/release-from-packed', $old, $old, 'packed'],
                ['refs/remotes/origin/main', $new, $new, 'packed'],
            ],
            $tuples,
        );
        $t->same(['refs/heads/main', 'refs/heads/release-candidate', 'refs/heads/release-from-packed', 'refs/heads/remote-candidate'], array_map(static fn ($reference): string => $reference->name, $store->prefixed('refs/heads/')));
        $t->same($old, $store->peelToObjectId('refs/heads/release-candidate'));
        $t->same($new, $store->peelToObjectId('refs/heads/remote-candidate'));
    },
    'reference store peel follows loose tag chains through object database' => static function (TestRunner $t): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-loose-tag-chain-peel-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);

        $objects = new LooseObjectStore($dir);
        $commitId = $objects->write(new GitObject(
            'commit',
            'tree ' . str_repeat('0', 40) . "\n"
            . "author Release Bot <release@example.com> 1770000000 +0000\n"
            . "committer Release Bot <release@example.com> 1770000000 +0000\n\n"
            . "Publish WordPress release package\n",
        ));
        $innerTagId = $objects->write((new GitTag(
            $commitId,
            'commit',
            'wp-release-v2026.05-inner',
            'Release Bot <release@example.com> 1770000000 +0000',
            "Inner release tag\n",
        ))->object());
        $outerTagId = $objects->write((new GitTag(
            $innerTagId,
            'tag',
            'wp-release-v2026.05',
            'Release Bot <release@example.com> 1770000000 +0000',
            "Outer release tag\n",
        ))->object());

        $store = ReferenceStore::at($dir);
        $store->looseStore()->writeDirect('refs/tags/wp-release-v2026.05', $outerTagId);

        $t->same($outerTagId, $store->followToObjectId('wp-release-v2026.05'));
        $t->same($outerTagId, $store->peelToObjectId('wp-release-v2026.05'));
        $t->same($commitId, $store->peelToObjectId('wp-release-v2026.05', new ObjectDatabase($dir)));
        $t->same($commitId, $store->peelToObjectId('refs/tags/wp-release-v2026.05', new ObjectDatabase($dir)));
    },
    'reference store peel detects symbolic cycles like upstream gix ref' => static function (TestRunner $t): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-peel-cycle-' . bin2hex(random_bytes(4));
        $store = ReferenceStore::at($dir);
        $store->looseStore()->writeSymbolic('refs/loop-a', 'refs/loop-b');
        $store->looseStore()->writeSymbolic('refs/loop-b', 'refs/loop-a');

        $t->throws(RuntimeException::class, static fn () => $store->followToObjectId('refs/loop-a'));
        $t->throws(RuntimeException::class, static fn () => $store->peelToObjectId('refs/loop-a'));
        $t->same('refs/loop-b', $store->find('refs/loop-a')->target->value);
    },
    'reference store deref update reports log-only symbolic split like upstream gix ref' => static function (TestRunner $t) use ($old, $new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-deref-update-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $store->looseStore()->writeSymbolic('HEAD', 'refs/heads/main');
        $store->looseStore()->writeDirect('refs/heads/main', $old);

        $result = $store->updateWithReport(
            'HEAD',
            ReferenceTarget::object($new),
            ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
            ReferenceTarget::object($old),
            true,
            'sha1',
            new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000'),
            'deploy via symbolic head',
            true,
        );

        $t->same('refs/heads/main', $result->reference->name);
        $t->same($new, $result->reference->targetObjectId());
        $t->same("ref: refs/heads/main\n", file_get_contents($dir . '/HEAD'));
        $t->same("{$new}\n", file_get_contents($dir . '/refs/heads/main'));
        $t->same(2, count($result->edits));
        $t->same('HEAD', $result->edits[0]->name);
        $t->same(ReferenceTransactionEdit::REFLOG_ONLY, $result->edits[0]->reflogMode);
        $t->same(false, $result->edits[0]->updatesReference);
        $t->same('symbolic', $result->edits[0]->previousTarget?->kind);
        $t->same('refs/heads/main', $result->edits[0]->previousTarget?->value);
        $t->same('refs/heads/main', $result->edits[1]->name);
        $t->same(ReferenceTransactionEdit::REFLOG_AND_REFERENCE, $result->edits[1]->reflogMode);
        $t->same(true, $result->edits[1]->updatesReference);
        $t->same($old, $result->edits[1]->previousTarget?->value);
        $t->contains(
            "{$old} {$new} Deploy Bot <deploy@example.com> 1234 +0000\tdeploy via symbolic head\n",
            (string) $store->reflogContents('HEAD'),
        );
        $t->contains(
            "{$old} {$new} Deploy Bot <deploy@example.com> 1234 +0000\tdeploy via symbolic head\n",
            (string) $store->reflogContents('refs/heads/main'),
        );
    },
    'reference store deref update creates missing referent while preserving symbolic head' => static function (TestRunner $t) use ($new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-deref-missing-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $store->looseStore()->writeSymbolic('HEAD', 'refs/heads/main');

        $result = $store->updateWithReport(
            'HEAD',
            ReferenceTarget::object($new),
            ReferenceStore::PREVIOUS_ANY,
            null,
            true,
            'sha1',
            new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000'),
            'hydrate production branch',
            true,
        );
        $zeros = str_repeat('0', 40);

        $t->same('refs/heads/main', $result->reference->name);
        $t->same(null, $result->edits[1]->previousTarget);
        $t->same("ref: refs/heads/main\n", file_get_contents($dir . '/HEAD'));
        $t->same("{$new}\n", file_get_contents($dir . '/refs/heads/main'));
        $t->contains(
            "{$zeros} {$new} Deploy Bot <deploy@example.com> 1234 +0000\thydrate production branch\n",
            (string) $store->reflogContents('HEAD'),
        );
        $t->contains(
            "{$zeros} {$new} Deploy Bot <deploy@example.com> 1234 +0000\thydrate production branch\n",
            (string) $store->reflogContents('refs/heads/main'),
        );
    },
    'reference store deref delete removes only reflogs through symbolic split like upstream gix ref' => static function (TestRunner $t) use ($old, $new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-deref-delete-log-only-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $committer = new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000');
        $store->looseStore()->writeSymbolic('HEAD', 'refs/heads/main');
        $store->looseStore()->writeDirect('refs/heads/main', $old);
        $store->appendReflog('HEAD', ReferenceTarget::object($old), ReferenceTarget::object($new), $committer, 'head audit', true);
        $store->appendReflog('refs/heads/main', ReferenceTarget::object($old), ReferenceTarget::object($new), $committer, 'branch audit', true);

        $result = $store->deleteWithReport(
            'HEAD',
            ReferenceStore::PREVIOUS_MUST_EXIST,
            null,
            true,
            'sha1',
            ReferenceTransactionEdit::REFLOG_ONLY,
        );

        $t->same('refs/heads/main', $result->reference?->name);
        $t->same($old, $result->reference?->targetObjectId());
        $t->same(2, count($result->edits));
        $t->same(ReferenceTransactionEdit::CHANGE_DELETE, $result->edits[0]->change);
        $t->same('HEAD', $result->edits[0]->name);
        $t->same(ReferenceTransactionEdit::REFLOG_ONLY, $result->edits[0]->reflogMode);
        $t->same(false, $result->edits[0]->updatesReference);
        $t->same('symbolic', $result->edits[0]->previousTarget?->kind);
        $t->same('refs/heads/main', $result->edits[0]->previousTarget?->value);
        $t->same('refs/heads/main', $result->edits[1]->name);
        $t->same(ReferenceTransactionEdit::REFLOG_ONLY, $result->edits[1]->reflogMode);
        $t->same(false, $result->edits[1]->updatesReference);
        $t->same($old, $result->edits[1]->previousTarget?->value);
        $t->same("ref: refs/heads/main\n", file_get_contents($dir . '/HEAD'));
        $t->same("{$old}\n", file_get_contents($dir . '/refs/heads/main'));
        $t->same(false, $store->reflogExists('HEAD'));
        $t->same(false, $store->reflogExists('refs/heads/main'));
    },
    'reference store deref delete keeps symbolic parent and deletes leaf reference' => static function (TestRunner $t) use ($old, $new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-deref-delete-ref-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $committer = new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000');
        $store->looseStore()->writeSymbolic('HEAD', 'refs/heads/main');
        $store->looseStore()->writeDirect('refs/heads/main', $old);
        $store->appendReflog('HEAD', ReferenceTarget::object($old), ReferenceTarget::object($new), $committer, 'head audit', true);
        $store->appendReflog('refs/heads/main', ReferenceTarget::object($old), ReferenceTarget::object($new), $committer, 'branch audit', true);

        $result = $store->deleteWithReport(
            'HEAD',
            ReferenceStore::PREVIOUS_MUST_EXIST,
            null,
            true,
            'sha1',
            ReferenceTransactionEdit::REFLOG_AND_REFERENCE,
        );

        $t->same('refs/heads/main', $result->reference?->name);
        $t->same($old, $result->reference?->targetObjectId());
        $t->same(2, count($result->edits));
        $t->same('HEAD', $result->edits[0]->name);
        $t->same(ReferenceTransactionEdit::REFLOG_ONLY, $result->edits[0]->reflogMode);
        $t->same(false, $result->edits[0]->updatesReference);
        $t->same('refs/heads/main', $result->edits[1]->name);
        $t->same(ReferenceTransactionEdit::REFLOG_AND_REFERENCE, $result->edits[1]->reflogMode);
        $t->same(true, $result->edits[1]->updatesReference);
        $t->same("ref: refs/heads/main\n", file_get_contents($dir . '/HEAD'));
        $t->same(false, is_file($dir . '/refs/heads/main'));
        $t->same('symbolic', $store->find('HEAD')->kind());
        $t->same(null, $store->tryFind('refs/heads/main'));
        $t->same(false, $store->reflogExists('HEAD'));
        $t->same(false, $store->reflogExists('refs/heads/main'));
    },
    'reference store deletes packed refs file when all packed entries are removed' => static function (TestRunner $t) use ($old): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-packed-remove-all-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/packed-refs', "{$old} refs/heads/main\n");
        $store = ReferenceStore::at($dir);

        $store->deleteReference('refs/heads/main');

        $t->same(false, is_file($dir . '/packed-refs'));
        $t->same(false, is_file($dir . '/packed-refs.lock'));
        $t->same(null, $store->tryFind('refs/heads/main'));
    },
    'namespaced reference transactions are transparent like upstream gix ref' => static function (TestRunner $t) use ($old): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-namespaced-transaction-' . bin2hex(random_bytes(4));
        $namespace = 'site-a';
        $prefix = ReferenceName::expandNamespace($namespace);
        $store = new ReferenceStore($dir, null, $namespace);

        $store->update('refs/for/deletion', ReferenceTarget::object($old));
        $deleted = $store->deleteReference(
            'refs/for/deletion',
            ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
            ReferenceTarget::object($old),
        );
        $head = $store->update(
            'HEAD',
            ReferenceTarget::symbolic('refs/heads/hello'),
            ReferenceStore::PREVIOUS_MUST_NOT_EXIST,
        );

        $t->same('refs/for/deletion', $deleted?->name);
        $t->same('HEAD', $head->name);
        $t->same('refs/heads/hello', $head->target->value);
        $t->same("ref: {$prefix}refs/heads/hello\n", file_get_contents($dir . '/' . $prefix . 'HEAD'));
        $t->same(null, $store->tryFind('refs/for/deletion'));
        $t->same('refs/heads/hello', $store->find('HEAD')->target->value);

        $plainStore = new ReferenceStore($dir);
        $t->same(null, $plainStore->tryFind('HEAD'));
        $t->same($prefix . 'HEAD', $plainStore->find($prefix . 'HEAD')->name);
    },
    'prepared reference transaction rollback removes intermediate lock directories' => static function (TestRunner $t) use ($old, $new): void {
        foreach ([true, false] as $explicitRollback) {
            $dir = sys_get_temp_dir() . '/port-libs-git-ref-prepare-rollback-' . bin2hex(random_bytes(4));
            $store = new ReferenceStore($dir);
            $transaction = $store->prepareLooseUpdateTransaction([
                'refs/heads/a/b/ref' => ReferenceTarget::object($old),
                'refs/heads/a/c/ref' => ReferenceTarget::object($new),
            ]);

            $t->same(true, is_dir($dir . '/refs/heads/a/b'));
            $t->same(true, is_dir($dir . '/refs/heads/a/c'));
            $t->same("{$old}\n", file_get_contents($dir . '/refs/heads/a/b/ref.lock'));

            if ($explicitRollback) {
                $edits = $transaction->rollback();
                $t->same(['refs/heads/a/b/ref', 'refs/heads/a/c/ref'], array_map(static fn ($edit): string => $edit->name, $edits));
            } else {
                unset($transaction);
            }

            $t->same(false, is_dir($dir . '/refs/heads/a/b'));
            $t->same(false, is_dir($dir . '/refs/heads/a/c'));
            $t->same(false, is_dir($dir . '/refs'), 'rollback prunes the empty refs directory like upstream gix-ref');
        }
    },
    'prepared reference transaction commit publishes lock files like upstream gix ref' => static function (TestRunner $t) use ($old, $new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-prepare-commit-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $transaction = $store->prepareLooseUpdateTransaction([
            'refs/heads/review/plugin-a' => ReferenceTarget::object($old),
            'refs/heads/review/plugin-b' => ReferenceTarget::object($new),
        ]);

        $t->same(true, is_file($dir . '/refs/heads/review/plugin-a.lock'));
        $t->same(true, is_file($dir . '/refs/heads/review/plugin-b.lock'));

        $edits = $transaction->commit();
        $t->same(false, $transaction->isOpen());
        $t->same(
            ['refs/heads/review/plugin-a', 'refs/heads/review/plugin-b'],
            array_map(static fn ($edit): string => $edit->name, $edits),
        );
        $t->same(false, is_file($dir . '/refs/heads/review/plugin-a.lock'));
        $t->same(false, is_file($dir . '/refs/heads/review/plugin-b.lock'));
        $t->same("{$old}\n", file_get_contents($dir . '/refs/heads/review/plugin-a'));
        $t->same("{$new}\n", file_get_contents($dir . '/refs/heads/review/plugin-b'));

        unset($transaction);

        $t->same($old, $store->find('refs/heads/review/plugin-a')->targetObjectId());
        $t->same($new, $store->find('refs/heads/review/plugin-b')->targetObjectId());
    },
    'prepared reference transaction commit recovers empty directory blockers' => static function (TestRunner $t): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-prepare-commit-empty-dir-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $transaction = $store->prepareLooseUpdateTransaction([
            'HEAD' => ReferenceTarget::symbolic('refs/heads/main'),
        ]);
        mkdir($dir . '/HEAD/interrupted-deploy/empty', 0777, true);

        $edits = $transaction->commit();

        $t->same(['HEAD'], array_map(static fn ($edit): string => $edit->name, $edits));
        $t->same("ref: refs/heads/main\n", file_get_contents($dir . '/HEAD'));
        $t->same(false, is_dir($dir . '/HEAD/interrupted-deploy'));
        $t->same('refs/heads/main', $store->find('HEAD')->target->value);
    },
    'prepared reference transaction commit is non atomic after a later lock failure' => static function (TestRunner $t) use ($old, $new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-prepare-commit-partial-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $transaction = $store->prepareLooseUpdateTransaction([
            'refs/heads/review/plugin-a' => ReferenceTarget::object($old),
            'refs/heads/review/plugin-b' => ReferenceTarget::object($new),
        ]);
        mkdir($dir . '/refs/heads/review/plugin-b', 0777, true);
        file_put_contents($dir . '/refs/heads/review/plugin-b/blocker.txt', 'not empty');

        $t->throws(
            RuntimeException::class,
            static fn () => $transaction->commit(),
        );

        $t->same(false, $transaction->isOpen());
        $t->same("{$old}\n", file_get_contents($dir . '/refs/heads/review/plugin-a'));
        $t->same(false, is_file($dir . '/refs/heads/review/plugin-a.lock'));
        $t->same(true, is_file($dir . '/refs/heads/review/plugin-b.lock'));
        $t->same(true, is_file($dir . '/refs/heads/review/plugin-b/blocker.txt'));
        $t->same(null, $store->tryFind('refs/heads/review/plugin-b'));

        unset($transaction);

        $t->same($old, $store->find('refs/heads/review/plugin-a')->targetObjectId());
        $t->same(true, is_file($dir . '/refs/heads/review/plugin-b.lock'));
    },
    'prepared reference transaction commit writes object reflogs before publishing locks' => static function (TestRunner $t) use ($old, $new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-prepare-reflog-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $committer = new CommitSignature(' Deploy Bot ', ' deploy@example.com ', '1234 +0000');
        $store->looseStore()->writeDirect('refs/heads/review/plugin-a', $old);
        $transaction = $store->prepareLooseUpdateTransaction(
            [
                'refs/heads/review/plugin-a' => ReferenceTarget::object($new),
                'refs/heads/review/plugin-b' => ReferenceTarget::object($old),
            ],
            'sha1',
            $committer,
            'prepared review publish',
        );

        $transaction->commit();

        $t->contains(
            "{$old} {$new} Deploy Bot <deploy@example.com> 1234 +0000\tprepared review publish\n",
            (string) $store->reflogContents('refs/heads/review/plugin-a'),
        );
        $t->contains(
            str_repeat('0', 40) . " {$old} Deploy Bot <deploy@example.com> 1234 +0000\tprepared review publish\n",
            (string) $store->reflogContents('refs/heads/review/plugin-b'),
        );
        $t->same("{$new}\n", file_get_contents($dir . '/refs/heads/review/plugin-a'));
        $t->same("{$old}\n", file_get_contents($dir . '/refs/heads/review/plugin-b'));
    },
    'prepared reference transaction commit needs a committer only when a reflog would be written' => static function (TestRunner $t) use ($old, $new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-prepare-reflog-missing-committer-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $transaction = $store->prepareLooseUpdateTransaction(
            [
                'refs/heads/review/plugin-a' => ReferenceTarget::object($old),
                'refs/internal/no-auto-log' => ReferenceTarget::object($new),
            ],
            'sha1',
            null,
            'message requires a committer',
        );

        $t->throws(
            InvalidArgumentException::class,
            static fn () => $transaction->commit(),
        );

        $t->same(false, $transaction->isOpen());
        $t->same(true, is_file($dir . '/refs/heads/review/plugin-a.lock'));
        $t->same(false, is_file($dir . '/refs/heads/review/plugin-a'));
        $t->same(null, $store->reflogContents('refs/heads/review/plugin-a'));

        $nonAutoDir = sys_get_temp_dir() . '/port-libs-git-ref-prepare-reflog-non-auto-' . bin2hex(random_bytes(4));
        $nonAuto = new ReferenceStore($nonAutoDir);
        $noLogTransaction = $nonAuto->prepareLooseUpdateTransaction(
            ['refs/internal/no-auto-log' => ReferenceTarget::object($new)],
            'sha1',
            null,
            'not auto-created',
            false,
        );
        $noLogTransaction->commit();
        $t->same("{$new}\n", file_get_contents($nonAutoDir . '/refs/internal/no-auto-log'));
        $t->same(null, $nonAuto->reflogContents('refs/internal/no-auto-log'));
    },
    'prepared reference transaction commit recovers empty reflog directory blockers' => static function (TestRunner $t) use ($old): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-prepare-reflog-dir-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $committer = new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000');
        $transaction = $store->prepareLooseUpdateTransaction(
            ['refs/heads/review/plugin-a' => ReferenceTarget::object($old)],
            'sha1',
            $committer,
            'prepared from empty directory',
            true,
        );
        mkdir($dir . '/logs/refs/heads/review/plugin-a/empty/recovered', 0777, true);

        $transaction->commit();

        $t->same(true, is_file($dir . '/logs/refs/heads/review/plugin-a'));
        $t->contains('prepared from empty directory', (string) $store->reflogContents('refs/heads/review/plugin-a'));
        $t->same($old, $store->find('refs/heads/review/plugin-a')->targetObjectId());
    },
    'prepared reference transaction deletes symbolic reflog without deref like upstream gix ref' => static function (TestRunner $t) use ($old, $new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-prepare-delete-log-only-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $committer = new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000');
        $store->looseStore()->writeSymbolic('HEAD', 'refs/heads/main');
        $store->looseStore()->writeDirect('refs/heads/main', $old);
        $store->appendReflog('HEAD', ReferenceTarget::object($old), ReferenceTarget::object($new), $committer, 'head audit', true);
        $store->appendReflog('refs/heads/main', ReferenceTarget::object($old), ReferenceTarget::object($new), $committer, 'branch audit', true);

        $transaction = $store->prepareLooseDeleteTransaction(
            ['HEAD'],
            ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
            ReferenceTarget::symbolic('refs/heads/main'),
            false,
            'sha1',
            ReferenceTransactionEdit::REFLOG_ONLY,
        );
        $t->same(true, is_file($dir . '/HEAD.lock'));

        $edits = $transaction->commit();

        $t->same(['HEAD'], array_map(static fn ($edit): string => $edit->name, $edits));
        $t->same(false, is_file($dir . '/HEAD.lock'));
        $t->same("ref: refs/heads/main\n", file_get_contents($dir . '/HEAD'));
        $t->same("{$old}\n", file_get_contents($dir . '/refs/heads/main'));
        $t->same(false, $store->reflogExists('HEAD'));
        $t->same(true, $store->reflogExists('refs/heads/main'));
    },
    'prepared reference transaction deletes dereferenced reflogs while preserving refs' => static function (TestRunner $t) use ($old, $new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-prepare-delete-deref-log-only-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $committer = new CommitSignature('Deploy Bot', 'deploy@example.com', '1234 +0000');
        $store->looseStore()->writeSymbolic('HEAD', 'refs/heads/main');
        $store->looseStore()->writeDirect('refs/heads/main', $old);
        $store->appendReflog('HEAD', ReferenceTarget::object($old), ReferenceTarget::object($new), $committer, 'head audit', true);
        $store->appendReflog('refs/heads/main', ReferenceTarget::object($old), ReferenceTarget::object($new), $committer, 'branch audit', true);

        $transaction = $store->prepareLooseDeleteTransaction(
            ['HEAD'],
            ReferenceStore::PREVIOUS_MUST_EXIST,
            null,
            true,
            'sha1',
            ReferenceTransactionEdit::REFLOG_ONLY,
        );
        $edits = $transaction->commit();

        $t->same(['HEAD', 'refs/heads/main'], array_map(static fn ($edit): string => $edit->name, $edits));
        $t->same([ReferenceTransactionEdit::REFLOG_ONLY, ReferenceTransactionEdit::REFLOG_ONLY], array_map(static fn ($edit): string => $edit->reflogMode, $edits));
        $t->same([false, false], array_map(static fn ($edit): bool => $edit->updatesReference, $edits));
        $t->same(false, is_file($dir . '/HEAD.lock'));
        $t->same(false, is_file($dir . '/refs/heads/main.lock'));
        $t->same("ref: refs/heads/main\n", file_get_contents($dir . '/HEAD'));
        $t->same("{$old}\n", file_get_contents($dir . '/refs/heads/main'));
        $t->same(false, $store->reflogExists('HEAD'));
        $t->same(false, $store->reflogExists('refs/heads/main'));
    },
    'prepared reference transaction stops on reflog delete failure before deleting ref' => static function (TestRunner $t) use ($old): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-prepare-delete-reflog-failure-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $store->looseStore()->writeDirect('refs/heads/review/plugin-a', $old);
        $transaction = $store->prepareLooseDeleteTransaction(
            ['refs/heads/review/plugin-a'],
            ReferenceStore::PREVIOUS_MUST_EXIST_AND_MATCH,
            ReferenceTarget::object($old),
        );
        mkdir($dir . '/logs/refs/heads/review/plugin-a', 0777, true);

        $t->throws(RuntimeException::class, static fn () => $transaction->commit());

        $t->same(false, $transaction->isOpen());
        $t->same("{$old}\n", file_get_contents($dir . '/refs/heads/review/plugin-a'));
        $t->same(true, is_file($dir . '/refs/heads/review/plugin-a.lock'));
        $t->same(true, is_dir($dir . '/logs/refs/heads/review/plugin-a'));
        $t->same($old, $store->find('refs/heads/review/plugin-a')->targetObjectId());
    },
    'prepared reference transaction deletes broken loose refs after staging locks' => static function (TestRunner $t): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-prepare-delete-broken-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        mkdir($dir . '/refs/heads/review/plugin-b', 0777, true);
        file_put_contents($dir . '/refs/heads/review/plugin-b/broken', 'broken-ref');

        $transaction = $store->prepareLooseDeleteTransaction(
            ['refs/heads/review/plugin-b/broken'],
            ReferenceStore::PREVIOUS_MUST_EXIST,
        );

        $t->same(true, is_file($dir . '/refs/heads/review/plugin-b/broken.lock'));
        $edits = $transaction->commit();

        $t->same(['refs/heads/review/plugin-b/broken'], array_map(static fn ($edit): string => $edit->name, $edits));
        $t->same([true], array_map(static fn ($edit): bool => $edit->updatesReference, $edits));
        $t->same([null], array_map(static fn ($edit): mixed => $edit->previousTarget?->value, $edits));
        $t->same(false, is_file($dir . '/refs/heads/review/plugin-b/broken'));
        $t->same(false, is_file($dir . '/refs/heads/review/plugin-b/broken.lock'));
        $t->same(false, is_dir($dir . '/refs'));
    },
    'prepared reference transaction lock collision rolls back already prepared locks' => static function (TestRunner $t) use ($old, $new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-prepare-lock-collision-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        $heldLock = $dir . '/refs/heads/a/b/ref.lock';
        mkdir(dirname($heldLock), 0777, true);
        file_put_contents($heldLock, 'held by another transaction');

        $t->throws(
            RuntimeException::class,
            static fn () => $store->prepareLooseUpdateTransaction([
                'refs/heads/a/c/ref' => ReferenceTarget::object($old),
                'refs/heads/a/b/ref' => ReferenceTarget::object($new),
            ]),
        );

        $t->same(true, is_file($heldLock));
        $t->same(false, is_file($dir . '/refs/heads/a/c/ref.lock'));
        $t->same(false, is_dir($dir . '/refs/heads/a/c'));
    },
    'prepared reference transaction refuses packed refs lock before loose locks' => static function (TestRunner $t) use ($old, $new): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-prepare-packed-lock-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/packed-refs.lock', 'packed transaction in progress');
        $store = new ReferenceStore($dir);

        $t->throws(
            RuntimeException::class,
            static fn () => $store->prepareLooseUpdateTransaction([
                'refs/heads/a/b/ref' => ReferenceTarget::object($old),
                'refs/heads/a/c/ref' => ReferenceTarget::object($new),
            ]),
        );

        $t->same('packed transaction in progress', file_get_contents($dir . '/packed-refs.lock'));
        $t->same(false, is_dir($dir . '/refs'));
        $t->same(null, $store->tryFind('refs/heads/a/b/ref'));
        $t->same(null, $store->tryFind('refs/heads/a/c/ref'));
    },
    'reference store recovers empty directory blockers when creating loose refs' => static function (TestRunner $t): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-empty-dir-blocker-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        mkdir($dir . '/HEAD/a/b/also-empty', 0777, true);

        $head = $store->update(
            'HEAD',
            ReferenceTarget::symbolic('refs/heads/main'),
            ReferenceStore::PREVIOUS_MUST_NOT_EXIST,
        );

        $t->same('HEAD', $head->name);
        $t->same('refs/heads/main', $head->target->value);
        $t->same("ref: refs/heads/main\n", file_get_contents($dir . '/HEAD'));
        $t->same(false, is_dir($dir . '/HEAD/a'));
    },
    'reference store refuses non-empty directory blockers when creating loose refs' => static function (TestRunner $t): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-non-empty-dir-blocker-' . bin2hex(random_bytes(4));
        $store = new ReferenceStore($dir);
        mkdir($dir . '/HEAD/a/b/also-empty', 0777, true);
        file_put_contents($dir . '/HEAD/file.ext', '');

        $t->throws(
            RuntimeException::class,
            static fn () => $store->update(
                'HEAD',
                ReferenceTarget::symbolic('refs/heads/main'),
                ReferenceStore::PREVIOUS_MUST_NOT_EXIST,
            ),
        );
        $t->same(true, is_file($dir . '/HEAD/file.ext'));
        $t->same(true, is_dir($dir . '/HEAD'));
    },
    'wordpress reference transaction example promotes and prunes review refs without git binary' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-reference-transaction.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-reference-transaction.php';

        $t->same($fixture['namespace'], $summary['namespace']);
        $t->same($fixture['productionCommit'], $summary['productionCommit']);
        $t->same($fixture['reviewCommit'], $summary['deletedReviewCommit']);
        $t->same($fixture['headTarget'], $summary['headTarget']);
        $t->same($fixture['expectedVisibleRefs'], $summary['visibleRefs']);
        $t->same($fixture['expectedPhysicalHead'], $summary['physicalHead']);
        $t->same(false, $summary['reviewRefStillExists']);
        $t->same($fixture['expectedHeadDirectoryRecovered'], $summary['headDirectoryRecovered']);
        $t->same($fixture['expectedPreparedRollbackEditNames'], $summary['preparedRollbackEditNames']);
        $t->same($fixture['expectedPreparedRollbackHadLocks'], $summary['preparedRollbackHadLocks']);
        $t->same($fixture['expectedPreparedRollbackCleaned'], $summary['preparedRollbackCleaned']);
        $t->same($fixture['expectedPreparedCommitEditNames'], $summary['preparedCommitEditNames']);
        $t->same($fixture['expectedPreparedCommitHadLocks'], $summary['preparedCommitHadLocks']);
        $t->same($fixture['expectedPreparedCommitCleanedLocks'], $summary['preparedCommitCleanedLocks']);
        $t->same($fixture['expectedPreparedCommitOpenAfterCommit'], $summary['preparedCommitOpenAfterCommit']);
        $t->same($fixture['reviewCommit'], $summary['preparedContentCommit']);
        $t->same($fixture['productionCommit'], $summary['preparedAssetsCommit']);
        $t->contains(
            str_repeat('0', 40) . ' ' . $fixture['reviewCommit'] . ' ' . $fixture['preparedReflogCommitter'] . "\t" . $fixture['preparedReflogMessage'],
            (string) $summary['preparedContentReflog'],
        );
        $t->contains(
            str_repeat('0', 40) . ' ' . $fixture['productionCommit'] . ' ' . $fixture['preparedReflogCommitter'] . "\t" . $fixture['preparedReflogMessage'],
            (string) $summary['preparedAssetsReflog'],
        );
        $t->same($fixture['expectedPreparedDeleteEditNames'], $summary['preparedDeleteEditNames']);
        $t->same($fixture['expectedPreparedDeleteHadLock'], $summary['preparedDeleteHadLock']);
        $t->same($fixture['expectedPreparedDeleteCleanedLock'], $summary['preparedDeleteCleanedLock']);
        $t->same($fixture['expectedPreparedDeleteRefStillExists'], $summary['preparedDeleteRefStillExists']);
        $t->same($fixture['expectedPreparedDeleteReflogExists'], $summary['preparedDeleteReflogExists']);
        $t->same($fixture['expectedPreparedBrokenDeleteEditNames'], $summary['preparedBrokenDeleteEditNames']);
        $t->same($fixture['expectedPreparedBrokenDeleteHadLock'], $summary['preparedBrokenDeleteHadLock']);
        $t->same($fixture['expectedPreparedBrokenDeleteCleanedLock'], $summary['preparedBrokenDeleteCleanedLock']);
        $t->same($fixture['expectedPreparedBrokenDeleteRefStillExists'], $summary['preparedBrokenDeleteRefStillExists']);
    },
    'wordpress deref reference transaction example updates production through symbolic head' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-deref-reference-transaction.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-deref-reference-transaction.php';

        $t->same($fixture['headRef'], $summary['editNames'][0]);
        $t->same($fixture['productionRef'], $summary['editNames'][1]);
        $t->same($fixture['expectedEditModes'], $summary['editModes']);
        $t->same($fixture['oldProductionCommit'], $summary['oldProductionCommit']);
        $t->same($fixture['newProductionCommit'], $summary['productionCommit']);
        $t->same($fixture['expectedHeadContents'], $summary['headContents']);
        $t->same($fixture['newProductionCommit'], $summary['productionFileCommit']);
        $t->contains($fixture['message'], (string) $summary['headReflog']);
        $t->contains($fixture['message'], (string) $summary['productionReflog']);
        $t->same($fixture['headRef'], $summary['deleteEditNames'][0]);
        $t->same($fixture['productionRef'], $summary['deleteEditNames'][1]);
        $t->same($fixture['expectedDeleteEditModes'], $summary['deleteEditModes']);
        $t->same($fixture['expectedDeleteUpdatesReference'], $summary['deleteUpdatesReference']);
        $t->same($fixture['oldProductionCommit'], $summary['deletedProductionCommit']);
        $t->same($fixture['expectedHeadContents'], $summary['deleteHeadContents']);
        $t->same($fixture['oldProductionCommit'], $summary['deleteProductionFileCommit']);
        $t->same(false, $summary['deleteHeadReflogExists']);
        $t->same(false, $summary['deleteProductionReflogExists']);
        $t->contains('symbolic HEAD', $summary['wordpressUse']);
    },
    'wordpress packed reference transaction example rewrites packed refs and records reflog' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-packed-reference-transaction.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-packed-reference-transaction.php';

        $t->same($fixture['productionRef'], $summary['productionRef']);
        $t->same($fixture['newProductionCommit'], $summary['productionCommit']);
        $t->same($fixture['reviewCommit'], $summary['deletedReviewCommit']);
        $t->same($fixture['releaseRef'], $summary['releaseRef']);
        $t->same($summary['releaseTagObject'], $summary['packedReleaseTagObject']);
        $t->same($summary['releasePeeledCommit'], $summary['packedReleasePeeledCommit']);
        $t->same($summary['releaseTagObject'], $summary['releaseCandidateTagObject']);
        $t->same($summary['releasePeeledCommit'], $summary['releaseCandidatePeeledCommit']);
        $t->same($fixture['expectedPackedNames'], $summary['packedNames']);
        $t->same($fixture['newProductionCommit'], $summary['packedProductionCommit']);
        $t->same($fixture['oldProductionCommit'], $summary['externalPackedBeforeRefresh']);
        $t->same($fixture['newProductionCommit'], $summary['externalPackedAfterRefresh']);
        $t->same(true, $summary['externalPackedAfterRemovalMissing']);
        $t->same(false, $summary['looseProductionExists']);
        $t->same(false, $summary['looseReleaseTagExists']);
        $t->same(false, $summary['reviewRefStillExists']);
        $t->contains(
            $fixture['oldProductionCommit'] . ' ' . $fixture['newProductionCommit'],
            (string) $summary['productionReflog'],
        );
        $t->contains($fixture['message'], (string) $summary['productionReflog']);
        $t->same($fixture['packedRefs'], $summary['lockedPackedRefsAfterFailure']);
        $t->same($fixture['expectedPackedLockFailurePrefix'], substr($summary['packedLockFailure'], 0, strlen($fixture['expectedPackedLockFailurePrefix'])));
        $t->same(true, $summary['packedLockStillPresent']);
        $t->same(false, $summary['lockedLooseProductionExists']);
        $t->same($fixture['packedRefs'], $summary['directLockedPackedRefsAfterFailure']);
        $t->same($fixture['expectedPackedLockFailurePrefix'], substr($summary['directPackedLockFailure'], 0, strlen($fixture['expectedPackedLockFailurePrefix'])));
        $t->same(false, $summary['directLockedLooseProductionExists']);
        $t->same(false, $summary['directLockedProductionReflogExists']);
        $t->contains('peel a packed release tag', $summary['wordpressUse']);
        $t->contains('reflogs', $summary['wordpressUse']);
    },
];
