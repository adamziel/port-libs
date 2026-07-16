<?php

declare(strict_types=1);

use PortLibs\Quadrable\HashTree;
use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\SparseTree;

return [
    'maps upstream basic put get sparse tree stats' => static function (TestRunner $t): void {
        $tree = new SparseTree();
        $tree->change()
            ->put('hello', 'world')
            ->apply();

        $t->same('world', $tree->get('hello'));
        $t->same(null, $tree->get('missing'));
        $t->same((new HashTree())->leafHash('hello', 'world'), $tree->rootHash());

        $stats = $tree->stats();
        $t->same(1, $stats['numNodes']);
        $t->same(1, $stats['numLeafNodes']);
        $t->same(0, $stats['numBranchNodes']);
    },
    'rejects zero length string keys like upstream' => static function (TestRunner $t): void {
        $tree = new SparseTree();

        $t->throws(InvalidArgumentException::class, static fn () => $tree->put('', '1'));
        $t->throws(InvalidArgumentException::class, static fn () => $tree->delete(''));
        $t->throws(InvalidArgumentException::class, static fn () => $tree->change()->put('', '1')->apply());
    },
    'preserves empty heads after deleting the last leaf' => static function (TestRunner $t): void {
        $tree = new SparseTree();
        $t->same(HashTree::EMPTY_HASH, $tree->rootHash());
        $t->same(null, $tree->get('hello'));
        $t->same(0, $tree->stats()['numLeafNodes']);

        $tree->put('a', '1');
        $t->true($tree->rootHash() !== HashTree::EMPTY_HASH);

        $tree->delete('a');
        $t->same(HashTree::EMPTY_HASH, $tree->rootHash());
        $t->same(0, $tree->stats()['numLeafNodes']);
    },
    'batch insert is path independent and supports getMulti' => static function (TestRunner $t): void {
        $batched = new SparseTree();
        $batched->change()
            ->put('a', '1')
            ->put('b', '2')
            ->put('c', '3')
            ->apply();

        $sequential = new SparseTree();
        $sequential->put('c', '3')
            ->put('b', '2')
            ->put('a', '1');

        $t->same($batched->rootHash(), $sequential->rootHash());
        $t->same(3, $batched->stats()['numLeafNodes']);
        $t->same('2', $batched->get('b'));

        $query = $batched->getMulti(['b', 'c', 'missing']);
        $t->true($query['b']['exists']);
        $t->same('2', $query['b']['value']);
        $t->true($query['c']['exists']);
        $t->true(!$query['missing']['exists']);
    },
    'raw key multi get maps upstream getMultiRaw for integer keys' => static function (TestRunner $t): void {
        $tree = new SparseTree();
        $tree->change()
            ->putKey(Key::fromInteger(1), 'one')
            ->putKey(Key::fromInteger(3), 'three')
            ->putKey(Key::fromInteger(5), 'five')
            ->apply();

        $query = $tree->getMultiRaw([
            Key::fromInteger(5),
            Key::fromInteger(2),
            Key::fromInteger(1),
        ]);

        $t->same(3, count($query));
        $t->true($query[Key::fromInteger(1)->hex()]['exists']);
        $t->same('one', $query[Key::fromInteger(1)->hex()]['value']);
        $t->true(!$query[Key::fromInteger(2)->hex()]['exists']);
        $t->same(null, $query[Key::fromInteger(2)->hex()]['value']);
        $t->true($query[Key::fromInteger(5)->hex()]['exists']);
        $t->same('five', $query[Key::fromInteger(5)->hex()]['value']);

        $t->throws(InvalidArgumentException::class, static fn () => $tree->getMultiRaw(['not-a-key']));
    },
    'cached proof tree invalidates after later updates' => static function (TestRunner $t): void {
        $tree = new SparseTree();
        $tree->change()
            ->putKey(Key::fromInteger(1), 'one')
            ->putKey(Key::fromInteger(2), 'two')
            ->apply();

        $t->same(2, $tree->stats()['numLeafNodes']);
        $t->same('one', SparseTree::importProof(
            $tree->exportRawProof([Key::fromInteger(1)]),
            $tree->rootHash()
        )->getKey(Key::fromInteger(1)));

        $oldRoot = $tree->rootHash();
        $tree->change()
            ->putKey(Key::fromInteger(2), 'two updated')
            ->putKey(Key::fromInteger(3), 'three')
            ->apply();

        $newRoot = $tree->rootHash();
        $t->true($oldRoot !== $newRoot);
        $t->same(3, $tree->stats()['numLeafNodes']);

        $partial = SparseTree::importProof(
            $tree->exportRawProof([Key::fromInteger(2), Key::fromInteger(3)]),
            $newRoot
        );

        $t->same('two updated', $partial->getKey(Key::fromInteger(2)));
        $t->same('three', $partial->getKey(Key::fromInteger(3)));
    },
    'update sets let later operations overwrite earlier operations before apply' => static function (TestRunner $t): void {
        $doublePut = new SparseTree();
        $doublePut->change()
            ->put('a', '1')
            ->put('a', '1')
            ->apply();

        $singlePut = new SparseTree();
        $singlePut->put('a', '1');
        $t->same($singlePut->rootHash(), $doublePut->rootHash());

        $deleted = new SparseTree();
        $deleted->change()
            ->put('a', '1')
            ->delete('a')
            ->apply();
        $t->same(HashTree::EMPTY_HASH, $deleted->rootHash());

        $putAfterDelete = new SparseTree();
        $putAfterDelete->change()
            ->put('a', '1')
            ->delete('a')
            ->put('a', '2')
            ->apply();

        $direct = new SparseTree();
        $direct->put('a', '2');
        $t->same($direct->rootHash(), $putAfterDelete->rootHash());
        $t->same('2', $putAfterDelete->get('a'));
    },
    'delete bubbling and missing deletes preserve equivalent roots' => static function (TestRunner $t): void {
        $deleteRight = new SparseTree();
        $deleteRight->change()->put('a', '1')->put('b', '2')->apply();
        $deleteRight->delete('b');

        $onlyLeft = new SparseTree();
        $onlyLeft->put('a', '1');
        $t->same($onlyLeft->rootHash(), $deleteRight->rootHash());

        $deleteBoth = new SparseTree();
        $deleteBoth->change()->put('a', '1')->put('b', '2')->put('c', '3')->apply();
        $deleteBoth->change()->delete('a')->delete('c')->apply();

        $onlyMiddle = new SparseTree();
        $onlyMiddle->put('b', '2');
        $t->same($onlyMiddle->rootHash(), $deleteBoth->rootHash());

        $unchanged = new SparseTree();
        $unchanged->change()->put('a', '1')->put('b', '2')->put('c', '3')->apply();
        $before = $unchanged->rootHash();
        $unchanged->delete('d');
        $t->same($before, $unchanged->rootHash());
    },
    'raw integer keys support wordpress ordered snapshot fixtures' => static function (TestRunner $t): void {
        $tree = new SparseTree();
        $tree->change()
            ->putKey(Key::fromInteger(1), 'wp_options:siteurl=https://example.test')
            ->putKey(Key::fromInteger(2), 'wp_posts:1=Hello')
            ->apply();

        $t->same('wp_options:siteurl=https://example.test', $tree->getKey(Key::fromInteger(1)));
        $t->same('wp_posts:1=Hello', $tree->getKey(Key::fromInteger(2)));
        $t->same(2, $tree->stats()['numLeafNodes']);

        $afterDelete = $tree->rootHash();
        $tree->change()
            ->deleteKey(Key::fromInteger(404))
            ->putKey(Key::fromInteger(2), 'wp_posts:1=Hello world')
            ->apply();

        $t->true($afterDelete !== $tree->rootHash());
        $t->same('wp_posts:1=Hello world', $tree->getKey(Key::fromInteger(2)));
    },
    'wordpress ordered snapshot root is upstream blake2s compatible' => static function (TestRunner $t): void {
        $records = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/wordpress-ordered-snapshot.json'), true, flags: JSON_THROW_ON_ERROR);

        $tree = new SparseTree();
        $changes = $tree->change();
        foreach ($records as $record) {
            $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
        }
        $changes->apply();

        $t->same('2104f0067df53e082bf83d7a1d90af2c03dc1045ab7d3d6666394cc2c6e5e206', $tree->rootHash());
    },
];
