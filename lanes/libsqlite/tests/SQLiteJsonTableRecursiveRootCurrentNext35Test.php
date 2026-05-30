<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonTableRecursiveRootCursor;

$tests = [];

$document = '{"menus":[{"name":"main","children":[{"name":"home","children":[]},{"name":"docs","children":[{"name":"api","children":[]}]}]},{"name":"footer","children":[{"name":"privacy","children":[]}]}],"meta":{"version":3}}';
$settings = '{"plugins":[{"slug":"cache","rules":[{"name":"page","next":[{"name":"mobile","next":[]}]},{"name":"object","next":[]}]},{"slug":"seo","rules":[{"name":"schema","next":[]}]}]}';

$names = static fn (array $rows): array => array_values(array_filter(array_map(
    static fn (array $row): mixed => ($row['key'] ?? null) === 'name' ? ($row['atom'] ?? null) : null,
    $rows,
), static fn (mixed $value): bool => $value !== null));

$frames = static function (string $json, string $root = '$.menus'): array {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($json, $root);

    return $cursor->drainByChildKey('children');
};

$tests['json table recursive root current next35 starts at requested root'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus[0]');
    $t->true($cursor->next());
    $t->same('$.menus[0]', $cursor->currentRoot());
};

$tests['json table recursive root current next35 exposes current root rows before enqueue'] = static function (TestRunner $t) use ($document, $names): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus[0]');
    $cursor->next();
    $t->same(['main', 'home', 'docs', 'api'], $names($cursor->currentRows()));
};

$tests['json table recursive root current next35 enqueues child roots from current rows'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus[0]');
    $cursor->next();
    $t->same(['$.menus[0].children', '$.menus[0].children[0].children', '$.menus[0].children[1].children', '$.menus[0].children[1].children[0].children'], $cursor->enqueueChildRootsByKey('children'));
};

$tests['json table recursive root current next35 next advances to first queued child root'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus[0]');
    $cursor->next();
    $cursor->enqueueChildRootsByKey('children');
    $t->true($cursor->next());
    $t->same('$.menus[0].children', $cursor->currentRoot());
};

$tests['json table recursive root current next35 current rows reset per root'] = static function (TestRunner $t) use ($document, $names): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus[0]');
    $cursor->next();
    $cursor->enqueueChildRootsByKey('children');
    $cursor->next();
    $t->same(['home', 'docs', 'api'], $names($cursor->currentRows()));
};

$tests['json table recursive root current next35 preserves breadth first root order'] = static function (TestRunner $t) use ($document, $frames): void {
    $t->same(['$.menus', '$.menus[0].children', '$.menus[0].children[0].children', '$.menus[0].children[1].children', '$.menus[0].children[1].children[0].children', '$.menus[1].children', '$.menus[1].children[0].children'], array_column($frames($document), 'root'));
};

$tests['json table recursive root current next35 frame positions advance with roots'] = static function (TestRunner $t) use ($document, $frames): void {
    $t->same([0, 1, 2, 3, 4, 5, 6], array_column($frames($document), 'position'));
};

$tests['json table recursive root current next35 atoms are scoped to current root'] = static function (TestRunner $t) use ($document, $frames): void {
    $t->same(['main', 'home', 'docs', 'api', 'footer', 'privacy'], $frames($document)[0]['atoms']);
};

$tests['json table recursive root current next35 later atom frame excludes prior siblings'] = static function (TestRunner $t) use ($document, $frames): void {
    $t->same([], $frames($document)[2]['atoms']);
};

$tests['json table recursive root current next35 queued roots are frame local'] = static function (TestRunner $t) use ($document, $frames): void {
    $t->same(['$.menus[0].children', '$.menus[0].children[0].children', '$.menus[0].children[1].children', '$.menus[0].children[1].children[0].children', '$.menus[1].children', '$.menus[1].children[0].children'], $frames($document)[0]['queued']);
};

$tests['json table recursive root current next35 empty child arrays do not enqueue descendants'] = static function (TestRunner $t) use ($document, $frames): void {
    $t->same([], $frames($document)[3]['queued']);
};

$tests['json table recursive root current next35 duplicate roots are skipped'] = static function (TestRunner $t) use ($document): void {
    $cursor = new SQLiteJsonTableRecursiveRootCursor('json_tree', $document, ['$.menus[0]', '$.menus[0]']);
    $cursor->next();
    $t->same([], $cursor->queuedRoots());
};

$tests['json table recursive root current next35 missing root yields empty current rows'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.missing');
    $cursor->next();
    $t->same([], $cursor->currentRows());
};

$tests['json table recursive root current next35 null json yields empty current rows'] = static function (TestRunner $t): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree(null, '$');
    $cursor->next();
    $t->same([], $cursor->currentRows());
};

$tests['json table recursive root current next35 eof clears current root'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus[1].children');
    $cursor->next();
    $t->same(false, $cursor->next());
    $t->same(null, $cursor->currentRoot());
};

$tests['json table recursive root current next35 rejects malformed seed root'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteJsonTableRecursiveRootCursor::tree('{"a":1}', '$.'),
);

$tests['json table recursive root current next35 rejects unsupported function'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => new SQLiteJsonTableRecursiveRootCursor('json_group_array', '[]'),
);

$tests['json table recursive root current next35 rejects negative drain limit'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteJsonTableRecursiveRootCursor::tree('[]')->drainByChildKey('children', -1),
);

$tests['json table recursive root current next35 json_each starts at array root'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::each($document, '$.menus');
    $cursor->next();
    $t->same([0, 1], array_column($cursor->currentRows(), 'key'));
};

$tests['json table recursive root current next35 json_each current rows expose direct children only'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::each($document, '$.menus[0].children');
    $cursor->next();
    $t->same(['object', 'object'], array_column($cursor->currentRows(), 'type'));
};

$tests['json table recursive root current next35 predicate enqueue can target object roots'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus');
    $cursor->next();
    $queued = $cursor->enqueueRootsWhere(static fn (array $row): bool => ($row['key'] ?? null) === 0);
    $t->same(['$.menus[0]', '$.menus[0].children[0]', '$.menus[0].children[1].children[0]', '$.menus[1].children[0]'], $queued);
};

$tests['json table recursive root current next35 predicate enqueue skips scalar roots'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus[0]');
    $cursor->next();
    $queued = $cursor->enqueueRootsWhere(static fn (array $row): bool => ($row['key'] ?? null) === 'name');
    $t->same(['$.menus[0].name', '$.menus[0].children[0].name', '$.menus[0].children[1].name', '$.menus[0].children[1].children[0].name'], $queued);
};

$tests['json table recursive root current next35 scalar queued root produces scalar frame'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus[0]');
    $cursor->next();
    $cursor->enqueueRootsWhere(static fn (array $row): bool => ($row['key'] ?? null) === 'name');
    $cursor->next();
    $t->same(['main'], array_column($cursor->currentRows(), 'atom'));
};

$tests['json table recursive root current next35 row path records parent path'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus[0].children[1]');
    $cursor->next();
    $t->same('$.menus[0].children', $cursor->currentRows()[0]['path']);
};

$tests['json table recursive root current next35 root row key reflects selected array index'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus[1]');
    $cursor->next();
    $t->same(1, $cursor->currentRows()[0]['key']);
};

$tests['json table recursive root current next35 negative array root resolves current key'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus[#-1]');
    $cursor->next();
    $t->same(1, $cursor->currentRows()[0]['key']);
};

$tests['json table recursive root current next35 subtype input keeps traversal'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree(new SQLiteJsonSubtypeValue($document), '$.menus[0]');
    $cursor->next();
    $t->same(['main', 'home', 'docs', 'api'], array_values(array_filter(array_column($cursor->currentAtomRows(), 'atom'), 'is_string')));
};

$tests['json table recursive root current next35 jsonb input keeps traversal'] = static function (TestRunner $t) use ($settings): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree(new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($settings))), '$.plugins[0].rules');
    $cursor->next();
    $t->same(['page', 'mobile', 'object'], array_values(array_filter(array_column($cursor->currentAtomRows(), 'atom'), 'is_string')));
};

$tests['json table recursive root current next35 malformed jsonb root is empty'] = static function (TestRunner $t): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree(new SQLiteBlobValue("\x1c\x00"), '$');
    $cursor->next();
    $t->same([], $cursor->currentRows());
};

$tests['json table recursive root current next35 queue is visible before next'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus');
    $cursor->next();
    $cursor->enqueueChildRootsByKey('children');
    $t->same(['$.menus[0].children', '$.menus[0].children[0].children', '$.menus[0].children[1].children', '$.menus[0].children[1].children[0].children', '$.menus[1].children', '$.menus[1].children[0].children'], $cursor->queuedRoots());
};

$tests['json table recursive root current next35 queue shrinks after next'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus');
    $cursor->next();
    $cursor->enqueueChildRootsByKey('children');
    $cursor->next();
    $t->same(['$.menus[0].children[0].children', '$.menus[0].children[1].children', '$.menus[0].children[1].children[0].children', '$.menus[1].children', '$.menus[1].children[0].children'], $cursor->queuedRoots());
};

$tests['json table recursive root current next35 root arrays preserve id restart'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus');
    $cursor->next();
    $cursor->enqueueChildRootsByKey('children');
    $cursor->next();
    $t->same(0, $cursor->currentRows()[0]['id']);
};

$tests['json table recursive root current next35 parent ids stay local to current frame'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus[0].children');
    $cursor->next();
    $parents = array_values(array_unique(array_filter(array_column($cursor->currentRows(), 'parent'), static fn (mixed $value): bool => $value !== null)));
    $t->same([0, 1, 4, 6, 7], $parents);
};

$tests['json table recursive root current next35 child key can be numeric'] = static function (TestRunner $t): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree('[[["deep"]],["next"]]', '$');
    $cursor->next();
    $t->same(['$[0]', '$[0][0]'], $cursor->enqueueChildRootsByKey(0));
};

$tests['json table recursive root current next35 numeric child root advances'] = static function (TestRunner $t): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree('[[["deep"]],["next"]]', '$');
    $cursor->next();
    $cursor->enqueueChildRootsByKey(0);
    $cursor->next();
    $t->same('$[0]', $cursor->currentRoot());
};

$tests['json table recursive root current next35 drain limit stops before queued roots'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus');
    $frames = $cursor->drainByChildKey('children', 1);
    $t->same(['$.menus[0].children', '$.menus[0].children[0].children', '$.menus[0].children[1].children', '$.menus[0].children[1].children[0].children', '$.menus[1].children', '$.menus[1].children[0].children'], $frames[0]['queued']);
    $t->same(['$.menus[0].children', '$.menus[0].children[0].children', '$.menus[0].children[1].children', '$.menus[0].children[1].children[0].children', '$.menus[1].children', '$.menus[1].children[0].children'], $cursor->queuedRoots());
};

$tests['json table recursive root current next35 drain zero leaves seed queued'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus');
    $t->same([], $cursor->drainByChildKey('children', 0));
    $t->same(['$.menus'], $cursor->queuedRoots());
};

$tests['json table recursive root current next35 selected root fullkey remains selected path'] = static function (TestRunner $t) use ($settings): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($settings, '$.plugins[0].rules[0]');
    $cursor->next();
    $t->same('$.plugins[0].rules[0]', $cursor->currentRows()[0]['fullkey']);
};

$tests['json table recursive root current next35 application rule next roots'] = static function (TestRunner $t) use ($settings): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($settings, '$.plugins[0].rules');
    $cursor->next();
    $t->same(['$.plugins[0].rules[0].next', '$.plugins[0].rules[0].next[0].next', '$.plugins[0].rules[1].next'], $cursor->enqueueChildRootsByKey('next'));
};

$tests['json table recursive root current next35 application next rule atom'] = static function (TestRunner $t) use ($settings): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($settings, '$.plugins[0].rules');
    $cursor->next();
    $cursor->enqueueChildRootsByKey('next');
    $cursor->next();
    $t->same(['mobile'], array_values(array_filter(array_column($cursor->currentAtomRows(), 'atom'), 'is_string')));
};

$tests['json table recursive root current next35 application second next root is empty'] = static function (TestRunner $t) use ($settings): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($settings, '$.plugins[0].rules');
    $cursor->next();
    $cursor->enqueueChildRootsByKey('next');
    $cursor->next();
    $cursor->next();
    $t->same([], $cursor->currentAtomRows());
};

$tests['json table recursive root current next35 json_each queued object root enumerates object members'] = static function (TestRunner $t) use ($settings): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::each($settings, '$.plugins');
    $cursor->next();
    $cursor->enqueueRootsWhere(static fn (array $row): bool => ($row['key'] ?? null) === 0);
    $cursor->next();
    $t->same(['slug', 'rules'], array_column($cursor->currentRows(), 'key'));
};

$tests['json table recursive root current next35 json_each scalar root has one null-key row'] = static function (TestRunner $t) use ($settings): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::each($settings, '$.plugins[0].slug');
    $cursor->next();
    $t->same([null], array_column($cursor->currentRows(), 'key'));
};

$tests['json table recursive root current next35 object label quoting survives next root'] = static function (TestRunner $t): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree('{"a b":{"children":[{"name":"quoted"}]}}', '$');
    $cursor->next();
    $cursor->enqueueRootsWhere(static fn (array $row): bool => ($row['key'] ?? null) === 'a b');
    $cursor->next();
    $t->same('$."a b"', $cursor->currentRoot());
};

$tests['json table recursive root current next35 quoted object child can enqueue children'] = static function (TestRunner $t): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree('{"a b":{"children":[{"name":"quoted"}]}}', '$');
    $cursor->next();
    $cursor->enqueueRootsWhere(static fn (array $row): bool => ($row['key'] ?? null) === 'a b');
    $cursor->next();
    $t->same(['$."a b".children'], $cursor->enqueueChildRootsByKey('children'));
};

$tests['json table recursive root current next35 duplicate descendants are not requeued'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus');
    $cursor->next();
    $first = $cursor->enqueueChildRootsByKey('children');
    $second = $cursor->enqueueChildRootsByKey('children');
    $t->same([6, 0], [count($first), count($second)]);
};

$tests['json table recursive root current next35 current atom rows excludes containers'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus[0]');
    $cursor->next();
    $t->same(['main', 'home', 'docs', 'api'], array_column($cursor->currentAtomRows(), 'atom'));
};

$tests['json table recursive root current next35 all container frame has empty atoms'] = static function (TestRunner $t): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree('{"children":[{"children":[]}]}', '$.children');
    $cursor->next();
    $t->same([], $cursor->currentAtomRows());
};

$tests['json table recursive root current next35 root scalar keeps path parent'] = static function (TestRunner $t) use ($settings): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($settings, '$.plugins[0].slug');
    $cursor->next();
    $t->same('$.plugins[0]', $cursor->currentRows()[0]['path']);
};

$tests['json table recursive root current next35 current row json column preserves source'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus[0]');
    $cursor->next();
    $t->same($document, $cursor->currentRows()[0]['json']);
};

$tests['json table recursive root current next35 current row root column preserves selected root'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus[0]');
    $cursor->next();
    $t->same('$.menus[0]', $cursor->currentRows()[0]['root']);
};

$tests['json table recursive root current next35 position starts before first next'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus');
    $t->same(-1, $cursor->position());
};

$tests['json table recursive root current next35 current root starts null'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus');
    $t->same(null, $cursor->currentRoot());
};

$tests['json table recursive root current next35 current rows starts empty'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus');
    $t->same([], $cursor->currentRows());
};

$tests['json table recursive root current next35 queued roots starts with seed'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus');
    $t->same(['$.menus'], $cursor->queuedRoots());
};

$tests['json table recursive root current next35 multiple seed roots preserve order'] = static function (TestRunner $t) use ($document): void {
    $cursor = new SQLiteJsonTableRecursiveRootCursor('json_tree', $document, ['$.menus[1]', '$.menus[0]']);
    $cursor->next();
    $first = $cursor->currentRoot();
    $cursor->next();
    $t->same(['$.menus[1]', '$.menus[0]'], [$first, $cursor->currentRoot()]);
};

$tests['json table recursive root current next35 json_each factory reports direct scalar atom'] = static function (TestRunner $t) use ($settings): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::each($settings, '$.meta.missing');
    $cursor->next();
    $t->same([], $cursor->currentRows());
};

$tests['json table recursive root current next35 drain reports row counts'] = static function (TestRunner $t) use ($document, $frames): void {
    $t->same([19, 10, 1, 4, 1, 4, 1], array_column($frames($document), 'rows'));
};

$tests['json table recursive root current next35 drain reaches eof after final frame'] = static function (TestRunner $t) use ($document): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($document, '$.menus[1].children');
    $cursor->drainByChildKey('children');
    $t->same(false, $cursor->next());
};

$tests['json table recursive root current next35 malformed dynamic child root is rejected'] = static function (TestRunner $t): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree('{"bad.path":{"children":[]}}', '$');
    $cursor->next();
    $t->same(['$."bad.path"'], $cursor->enqueueRootsWhere(static fn (array $row): bool => ($row['key'] ?? null) === 'bad.path'));
};

$tests['json table recursive root current next35 selected nested root keeps row-local first id'] = static function (TestRunner $t) use ($settings): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($settings, '$.plugins[0].rules[0].next');
    $cursor->next();
    $t->same(0, $cursor->currentRows()[0]['id']);
};

$tests['json table recursive root current next35 selected nested root keeps row-local parent null'] = static function (TestRunner $t) use ($settings): void {
    $cursor = SQLiteJsonTableRecursiveRootCursor::tree($settings, '$.plugins[0].rules[0].next');
    $cursor->next();
    $t->same(null, $cursor->currentRows()[0]['parent']);
};

return $tests;
