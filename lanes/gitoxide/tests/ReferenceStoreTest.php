<?php

declare(strict_types=1);

use PortLibs\Gitoxide\PackedReferences;
use PortLibs\Gitoxide\CommitSignature;
use PortLibs\Gitoxide\ReferenceName;
use PortLibs\Gitoxide\ReferenceStore;
use PortLibs\Gitoxide\ReferenceTarget;

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
        $t->contains(
            "{$old} {$new} Deploy Bot <deploy@example.com> 1234 +0000\tpack deployment branch\n",
            (string) $store->reflogContents('refs/heads/main'),
        );
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
    'reference store deletes packed refs file when all packed entries are removed' => static function (TestRunner $t) use ($old): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-ref-packed-remove-all-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/packed-refs', "{$old} refs/heads/main\n");
        $store = ReferenceStore::at($dir);

        $store->deleteReference('refs/heads/main');

        $t->same(false, is_file($dir . '/packed-refs'));
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
    },
    'wordpress packed reference transaction example rewrites packed refs and records reflog' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-packed-reference-transaction.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-packed-reference-transaction.php';

        $t->same($fixture['productionRef'], $summary['productionRef']);
        $t->same($fixture['newProductionCommit'], $summary['productionCommit']);
        $t->same($fixture['reviewCommit'], $summary['deletedReviewCommit']);
        $t->same($fixture['expectedPackedNames'], $summary['packedNames']);
        $t->same($fixture['newProductionCommit'], $summary['packedProductionCommit']);
        $t->same(false, $summary['looseProductionExists']);
        $t->same(false, $summary['reviewRefStillExists']);
        $t->contains(
            $fixture['oldProductionCommit'] . ' ' . $fixture['newProductionCommit'],
            (string) $summary['productionReflog'],
        );
        $t->contains($fixture['message'], (string) $summary['productionReflog']);
    },
];
