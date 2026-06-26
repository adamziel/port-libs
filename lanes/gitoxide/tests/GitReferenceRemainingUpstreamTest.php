<?php

declare(strict_types=1);

use PortLibs\Gitoxide\LooseReferenceStore;
use PortLibs\Gitoxide\PackedReferences;
use PortLibs\Gitoxide\ReferenceName;
use PortLibs\Gitoxide\ReferenceStore;

$c1 = '134385f6d781b7e97062102c6a483440bfda2a03';
$c2 = '9902e3c3e8f0c569b4ab295ddf473e6de763e1e7';
$tag = 'b3109a7e51fc593f85b145a76c70ddd1d133fafd';
$peeled = '4c3f4cce493d7beb45012e478021b5f65295e5a3';

$tempGitDirCounter = 0;
$tempGitDir = static function (string $label) use (&$tempGitDirCounter): string {
    $dir = sys_get_temp_dir() . '/port-libs-git-ref-remaining-' . $label . '-' . getmypid() . '-' . (++$tempGitDirCounter);
    mkdir($dir, 0777, true);

    return $dir;
};

$writeGitFile = static function (string $dir, string $name, string $contents): void {
    $path = $dir . '/' . $name;
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    file_put_contents($path, $contents);
};

return [
    'upstream packed open and iter edge cases map to PHP packed references' => static function (TestRunner $t) use ($c1, $c2, $tag, $peeled): void {
        $empty = PackedReferences::fromBytes('');
        $t->same([], $empty->all(), 'packed::Iter::empty and open::empty_buffers_should_not_exist_but_are_fine_to_open');

        $sorted = PackedReferences::fromBytes("# pack-refs with: sorted \n");
        $t->same(true, $sorted->hasHeader(), 'packed::open::sorted_buffer_works has a recognized header');
        $t->same(true, $sorted->headerSorted(), 'packed::open::sorted_buffer_works records sorted trait');

        $withoutHeader = PackedReferences::fromBytes(
            "{$c1} refs/tags/TEST-0.0.1\n"
            . "{$tag} refs/tags/gix-actor-v0.1.0\n"
            . "^{$peeled}\n"
            . "{$c2} refs/tags/gix-actor-v0.1.1\n",
        );
        $t->same(false, $withoutHeader->hasHeader(), 'packed::iter::packed_refs_without_header');
        $t->same(
            ['refs/tags/TEST-0.0.1', 'refs/tags/gix-actor-v0.1.0', 'refs/tags/gix-actor-v0.1.1'],
            $withoutHeader->names(),
        );
        $t->same($tag, $withoutHeader->find('refs/tags/gix-actor-v0.1.0')->targetObjectId());
        $t->same($peeled, $withoutHeader->find('gix-actor-v0.1.0')->objectId());

        $unsorted = PackedReferences::fromBytes(
            "{$tag} refs/tags/tag-object\n"
            . "^{$peeled}\n"
            . "{$c2} refs/remotes/origin/main\n"
            . "{$c1} refs/heads/A\n"
            . "{$c1} refs/heads/main\n",
        );
        $t->same(false, $unsorted->headerSorted(), 'packed::open::unsorted_buffers_or_those_without_a_header_can_be_opened_and_searched');
        $t->same(['refs/heads/A', 'refs/heads/main', 'refs/remotes/origin/main', 'refs/tags/tag-object'], $unsorted->names());
        $t->same('refs/heads/A', $unsorted->find('A')->name, 'packed::find::capitalized_branch');
        $t->same('refs/remotes/origin/main', $unsorted->find('remotes/origin/main')->name);
        $t->same('refs/remotes/origin/main', $unsorted->find('origin/main')->name);
        $t->same($peeled, $unsorted->find('tag-object')->objectId());

        foreach ($unsorted->all() as $reference) {
            $found = $unsorted->find($reference->name);
            $t->same($reference->name, $found->name, 'packed::find::all_iterable_refs_can_be_found');
            $t->same($reference->targetObjectId(), $found->targetObjectId());
        }
    },
    'upstream packed iter_prefixed accepts directory and partial prefixes' => static function (TestRunner $t) use ($c1, $c2, $tag): void {
        $packed = PackedReferences::fromBytes(
            "# pack-refs with: peeled fully-peeled sorted\n"
            . "{$c1} refs/d1\n"
            . "{$c1} refs/heads/A\n"
            . "{$c1} refs/heads/d1\n"
            . "{$c2} refs/heads/dt1\n"
            . "{$c1} refs/heads/main\n"
            . "{$c2} refs/remotes/origin/main\n"
            . "{$c1} refs/remotes/origin/multi-link-target3\n"
            . "{$tag} refs/tags/t1\n",
        );

        $names = static fn (array $references): array => array_map(static fn ($reference): string => $reference->name, $references);

        $t->same(
            ['refs/heads/A', 'refs/heads/d1', 'refs/heads/dt1', 'refs/heads/main'],
            $names($packed->prefixed('refs/heads/')),
            'packed::iter::iter_prefix directory prefix',
        );
        $t->same(['refs/heads/d1', 'refs/heads/dt1'], $names($packed->prefixed('refs/heads/d')), 'packed::iter::iter_prefix partial prefix');
        $t->same(['refs/remotes/origin/main', 'refs/remotes/origin/multi-link-target3'], $names($packed->prefixed('refs/remotes/')));
        $t->same(['refs/tags/t1'], $names($packed->prefixed('refs/tags/t1')), 'packed::iter::iter_prefix last ref exact prefix');
        $t->same(['refs/d1'], $names($packed->prefixed('refs/d1')), 'packed::iter::iter_prefix first ref exact prefix');
    },
    'upstream loose find conversion rules resolve full partial and pseudo refs' => static function (TestRunner $t) use ($tempGitDir, $writeGitFile, $c1, $c2): void {
        $dir = $tempGitDir('loose-find');
        $writeGitFile($dir, 'HEAD', "ref: refs/heads/main\n");
        $writeGitFile($dir, 'FETCH_HEAD', "{$c1}\t\tbranch 'main' of https://example.test/repo\n");
        $writeGitFile($dir, 'refs/d1', "{$c1}\n");
        $writeGitFile($dir, 'refs/heads/A', "{$c1}\n");
        $writeGitFile($dir, 'refs/heads/d1', "{$c1}\n");
        $writeGitFile($dir, 'refs/heads/main', "{$c1}\n");
        $writeGitFile($dir, 'refs/heads/dt1', "{$c2}\n");
        $writeGitFile($dir, 'refs/tags/dt1', "{$c1}\n");
        $writeGitFile($dir, 'refs/tags/t1', "{$c2}\n");
        $writeGitFile($dir, 'refs/remotes/origin/HEAD', "ref: refs/remotes/origin/main\n");
        $writeGitFile($dir, 'refs/remotes/origin/main', "{$c2}\n");
        $writeGitFile($dir, 'refs/broken', "not-a-reference\n");

        $store = new ReferenceStore($dir);
        foreach ([
            ['dt1', 'refs/tags/dt1', 'object'],
            ['FETCH_HEAD', 'FETCH_HEAD', 'object'],
            ['heads/dt1', 'refs/heads/dt1', 'object'],
            ['d1', 'refs/d1', 'object'],
            ['heads/d1', 'refs/heads/d1', 'object'],
            ['HEAD', 'HEAD', 'symbolic'],
            ['origin', 'refs/remotes/origin/HEAD', 'symbolic'],
            ['origin/HEAD', 'refs/remotes/origin/HEAD', 'symbolic'],
            ['origin/main', 'refs/remotes/origin/main', 'object'],
            ['t1', 'refs/tags/t1', 'object'],
            ['main', 'refs/heads/main', 'object'],
            ['heads/main', 'refs/heads/main', 'object'],
            ['refs/heads/main', 'refs/heads/main', 'object'],
            ['A', 'refs/heads/A', 'object'],
        ] as [$partialName, $expectedName, $expectedKind]) {
            $reference = $store->find($partialName);
            $t->same($expectedName, $reference->name, "file::store::find::loose::success {$partialName}");
            $t->same($expectedKind, $reference->kind());
        }

        $t->same(null, $store->tryFind('does-not-exist'), 'file::store::find::loose::failure missing');
        $t->throws(InvalidArgumentException::class, static fn () => $store->tryFind('broken'));
        $t->throws(InvalidArgumentException::class, static fn () => $store->tryFind('../escaping'));
        $t->same($c1, $store->find('FETCH_HEAD')->targetObjectId(), 'file::store::find::fetch_head_can_be_parsed');
    },
    'upstream loose iter prefixes and pseudo refs are deterministic' => static function (TestRunner $t) use ($tempGitDir, $writeGitFile, $c1, $c2): void {
        $emptyStore = new ReferenceStore($tempGitDir('missing-refs-dir'));
        $t->same([], $emptyStore->all(), 'file::store::iter::missing_refs_dir_yields_empty_iteration');
        $t->same([], $emptyStore->looseAll());

        $dir = $tempGitDir('loose-iter');
        $writeGitFile($dir, 'FETCH_HEAD', "{$c1}\t\tbranch 'main' of https://example.test/repo\n");
        $writeGitFile($dir, 'HEAD', "ref: refs/heads/main\n");
        $writeGitFile($dir, 'JIRI_HEAD', "{$c2}\n");
        $writeGitFile($dir, 'lower_head', "{$c1}\n");
        $writeGitFile($dir, 'refs/heads/A', "{$c1}\n");
        $writeGitFile($dir, 'refs/heads/d1', "{$c1}\n");
        $writeGitFile($dir, 'refs/heads/dt1', "{$c2}\n");
        $writeGitFile($dir, 'refs/heads/main', "{$c1}\n");
        $writeGitFile($dir, 'refs/heads/multi-link-target1', "{$c2}\n");
        $writeGitFile($dir, 'refs/tags/t1', "{$c1}\n");
        $writeGitFile($dir, 'refs/tags/dt1', "{$c2}\n");
        $writeGitFile($dir, 'refs/remotes/origin/main', "{$c2}\n");
        $writeGitFile($dir, 'refs/heads/main.lock', 'held lock');

        $store = new ReferenceStore($dir);
        $names = static fn (array $references): array => array_map(static fn ($reference): string => $reference->name, $references);

        $t->same(['FETCH_HEAD', 'HEAD', 'JIRI_HEAD'], $names($store->pseudoReferences()), 'file::store::iter::pseudo_refs_iter');
        $t->same(
            ['refs/heads/A', 'refs/heads/d1', 'refs/heads/dt1', 'refs/heads/main', 'refs/heads/multi-link-target1'],
            $names($store->loosePrefixed('refs/heads/')),
            'file::store::iter::loose_iter_with_prefix',
        );
        $t->same(
            ['refs/heads/A', 'refs/heads/d1', 'refs/heads/dt1', 'refs/heads/main', 'refs/heads/multi-link-target1'],
            $names($store->loosePrefixed('refs/heads')),
            'file::store::iter::loose_iter_with_partial_prefix_dir',
        );
        $t->same(['refs/heads/d1', 'refs/heads/dt1'], $names($store->loosePrefixed('refs/heads/d')), 'file::store::iter::loose_iter_with_partial_prefix');
    },
    'upstream overlay iter prefers loose refs and supports partial prefixes' => static function (TestRunner $t) use ($tempGitDir, $writeGitFile, $c1, $c2, $tag): void {
        $dir = $tempGitDir('overlay');
        $writeGitFile(
            $dir,
            'packed-refs',
            "# pack-refs with: peeled fully-peeled sorted\n"
            . "{$c1} refs/d1\n"
            . "{$c1} refs/heads/A\n"
            . "{$c1} refs/heads/main\n"
            . "{$c1} refs/prefix/feature-suffix\n"
            . "{$c1} refs/prefix/feature/sub/dir/algo\n"
            . "{$c1} refs/remotes/origin/main\n"
            . "{$tag} refs/tags/tag-object\n",
        );
        $writeGitFile($dir, 'refs/heads/newer-as-loose', "{$c2}\n");
        $writeGitFile($dir, 'refs/heads/d1', "{$c2}\n");
        $writeGitFile($dir, 'refs/remotes/origin/HEAD', "ref: refs/remotes/origin/main\n");
        $writeGitFile($dir, 'refs/heads/main', "{$c2}\n");

        $store = ReferenceStore::at($dir);
        $pairs = static fn (array $references): array => array_map(
            static fn ($reference): array => [$reference->name, $reference->source, $reference->targetObjectId() ?? $reference->target->value],
            $references,
        );

        $t->same(
            [
                ['refs/d1', 'packed', $c1],
                ['refs/heads/A', 'packed', $c1],
                ['refs/heads/d1', 'loose', $c2],
                ['refs/heads/main', 'loose', $c2],
                ['refs/heads/newer-as-loose', 'loose', $c2],
                ['refs/prefix/feature-suffix', 'packed', $c1],
                ['refs/prefix/feature/sub/dir/algo', 'packed', $c1],
                ['refs/remotes/origin/HEAD', 'loose', 'refs/remotes/origin/main'],
                ['refs/remotes/origin/main', 'packed', $c1],
                ['refs/tags/tag-object', 'packed', $tag],
            ],
            $pairs($store->all()),
            'file::store::iter::overlay_iter',
        );
        $t->same(
            [
                ['refs/heads/A', 'packed', $c1],
                ['refs/heads/d1', 'loose', $c2],
                ['refs/heads/main', 'loose', $c2],
                ['refs/heads/newer-as-loose', 'loose', $c2],
            ],
            $pairs($store->prefixed('refs/heads/')),
            'file::store::iter::overlay_prefixed_iter',
        );
        $t->same([['refs/d1', 'packed', $c1]], $pairs($store->prefixed('refs/d')), 'file::store::iter::overlay_partial_prefix_iter_reproduce_1934');
        $t->same([['refs/heads/main', 'loose', $c2]], $pairs($store->prefixed('refs/heads/m')), 'file::store::iter::overlay_partial_prefix_iter');
        $t->same([['refs/prefix/feature-suffix', 'packed', $c1], ['refs/prefix/feature/sub/dir/algo', 'packed', $c1]], $pairs($store->prefixed('refs/prefix/feature')));
        $t->same([['refs/prefix/feature/sub/dir/algo', 'packed', $c1]], $pairs($store->prefixed('refs/prefix/feature/')));
    },
    'upstream reference namespace stripping applies to names and symbolic targets through store views' => static function (TestRunner $t) use ($tempGitDir, $writeGitFile, $c1): void {
        $dir = $tempGitDir('namespace-strip');
        $prefix = ReferenceName::expandNamespace('ns');
        $writeGitFile($dir, $prefix . 'refs/heads/main', "{$c1}\n");
        $writeGitFile($dir, $prefix . 'refs/tags/foo', "{$c1}\n");
        $writeGitFile($dir, $prefix . 'refs/heads/symbolic', "ref: {$prefix}refs/tags/foo\n");

        $store = new ReferenceStore($dir, null, 'ns');
        $main = $store->find('refs/heads/main');
        $symbolic = $store->find('refs/heads/symbolic');

        $t->same('refs/heads/main', $main->name, 'reference::strip_namespace name is stripped');
        $t->same('refs/heads/symbolic', $symbolic->name);
        $t->same('refs/tags/foo', $symbolic->target->value, 'reference::strip_namespace symbolic target is stripped');
    },
];
