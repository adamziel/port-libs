# libsqlite Upstream Runner Evidence

Date: 2026-05-22

Upstream checkout:

- Git mirror commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- Official manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Cache: `.upstream-cache/libsqlite`

## Cache Hydration

The cache was inspected before changes. It was a clean shallow blobless checkout
with only root files materialized through a root-only sparse checkout.

Hydration command:

```sh
git -C .upstream-cache/libsqlite sparse-checkout set src test tool ext autosetup autoconf mptest
```

This materialized the directories required by SQLite's `configure`,
`testfixture`, and Tcl test runner paths without deleting or resetting the
cache.

## Prerequisites

Installed direct build/test prerequisites with passwordless sudo:

```sh
sudo -n dnf install -y tcl tcl-devel gcc make
```

Verified tools:

- `tclsh`: `/usr/bin/tclsh`, Tcl 9.0.2
- `cc`/`gcc`: GCC 16.1.1-2.fc44
- `make`: GNU Make 4.4.1
- Tcl headers: `/usr/include/tcl.h`

## Build And Tests

Configure:

```sh
mkdir -p .upstream-cache/libsqlite-build-port-libsqlite
cd .upstream-cache/libsqlite-build-port-libsqlite
../libsqlite/configure CFLAGS='-O0 -g'
```

Result: passed. Configure detected Tcl via `/usr/bin/tclsh9.0` and
`/usr/lib64/tclConfig.sh`.

Build:

```sh
make -C .upstream-cache/libsqlite-build-port-libsqlite -j2 testfixture
```

Result: passed.

Focused runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  btree*.test pager*.test quick*.test schema*.test rowid*.test table*.test
```

Result: 37 scripts, 0 errors out of 6731 tests in 00:07.

Strongest bounded runner completed in this run:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick
```

Result: 1235 scripts, 0 errors out of 329670 tests in 01:51.

Boundary: SQLite `all` and `release` permutations were not run in this bounded
lane pass because they cover many build configurations and higher-cost suites.
The stale missing `tclsh`/compiler/`make`/Tcl-header blocker is resolved.

## Focused Native Mapping: Table Leaf Overflow

The current PHP slice maps SQLite's table leaf overflow payload placement from
`src/btree.c`: `maxLeaf = usableSize - 35`, `minLeaf =
((usableSize - 12) * 32 / 255) - 23`, and overflow pages store a 4-byte
big-endian next-page pointer followed by up to `usableSize - 4` payload bytes.

Focused upstream fixture boundary:

- `test/corrupt3.test` creates a page-size 1024 table row with one overflow
  page, verifies the first overflow pointer location, and checks malformed
  overflow chains.

The native PHP tests now cover local-payload length calculation, single-page
overflow reads, multi-page chained overflow reads, and premature overflow-chain
termination for WordPress-shaped `wp_options` rows.

## Focused Native Mapping: Index B-Tree Option Lookup

The current PHP slice also maps SQLite index b-tree cell layout from
`src/btree.c`: index leaf cells store a payload-length varint followed by the
index record payload; index interior cells prefix the same payload with a
4-byte left-child page pointer; both use `maxLocal = ((usableSize - 12) * 64 /
255) - 23` and `minLocal = ((usableSize - 12) * 32 / 255) - 23`.

Focused upstream fixture boundary:

- `test/index.test` covers `CREATE INDEX` schema records and automatic index
  naming.
- `test/rowid.test` covers rowid lookups joined through an index, including
  `CREATE INDEX idxt1 ON t1(x)` and equality on `rowid`/`_rowid_`/`oid`.

The native PHP tests now cover index leaf and interior cell parsing, in-order
index b-tree traversal that preserves interior index records, index local
payload calculations, explicit `CREATE INDEX ... ON wp_options(option_name)`
schema discovery, option-name index lookup, rowid-backed table retrieval, and
automatic `PRIMARY KEY` index inference for simple first-column lookups.
Expression indexes, predicate-aware partial-index use, custom collations,
automatic-index collation metadata, and full composite-key scans remain
unported.

## Focused Native Mapping: Automatic UNIQUE Autoindexes

SQLite stores automatic indexes created by `UNIQUE` and `PRIMARY KEY`
constraints as `sqlite_schema` index rows with `sql` set to `NULL`. The native
PHP reader now infers the first column for automatic `UNIQUE` indexes from the
owning table's `CREATE TABLE` SQL and maps those inferred columns to
`sqlite_autoindex_<table>_<n>` rows in schema order.

Focused upstream fixture boundary:

- `test/index.test` checks the automatic index name convention
  `sqlite_autoindex_<table name>_<integer>` and verifies that automatic indexes
  cannot be dropped.
- `test/schema6.test` checks that inline `b UNIQUE` table declarations produce
  the same database content as an explicit `CREATE UNIQUE INDEX ... ON t1(b)`.
- `test/schema5.test` and `test/index3.test` cover table-level `UNIQUE(...)`
  constraints, quoted constraint columns, and collation/sort-order syntax at
  the table/index boundary.

The native PHP tests cover column-level `option_name text UNIQUE`, table-level
`UNIQUE("slug" COLLATE nocase)` parsing, bracket-quoted column names, ignored
`UNIQUE` text inside `CHECK(...)`, and a WordPress-shaped
`sqlite_autoindex_wp_options_1` row whose `sql` is `NULL`. The lookup then uses
the automatic index root page, decodes the index record's rowid tail, and reads
the target `wp_options` row through the table b-tree. Full composite-key scans,
custom collations, automatic-index collation metadata, expression indexes, and
predicate-aware partial-index use remain unported.

## Focused Native Mapping: Automatic PRIMARY KEY Autoindexes

This slice extends automatic index inference from `UNIQUE` constraints to
non-rowid `PRIMARY KEY` constraints. A focused upstream runner was executed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index.test index3.test schema6.test indexedby.test
```

Result: 8 Tcl script/permutation runs, 0 errors out of 404 tests in 00:00.

Focused upstream fixture boundary:

- `test/index.test` verifies automatic primary-key index creation, the
  `sqlite_autoindex_<table>_<n>` naming convention, and duplicate
  `UNIQUE`/`PRIMARY KEY` constraint coalescing.
- `test/index3.test` verifies quoted/string identifier compatibility and that
  `INTEGER PRIMARY KEY` table declarations do not create autoindex rows.
- `test/schema6.test` cross-checks `INTEGER PRIMARY KEY`, `PRIMARY KEY(...)`,
  `UNIQUE`, and `WITHOUT ROWID` database-content equivalence.
- `test/indexedby.test` verifies that a primary-key-created automatic index can
  be named by `INDEXED BY sqlite_autoindex_*`.

The native PHP reader now derives automatic index first-column order from
`CREATE TABLE` SQL for both `UNIQUE` and `PRIMARY KEY` constraints. It skips
rowid-alias `INTEGER PRIMARY KEY` constraints, handles the SQLite
`INTEGER PRIMARY KEY DESC` exception as an autoindexed primary key, suppresses
`WITHOUT ROWID` table-primary-key autoindex slots, and preserves earlier
`UNIQUE` autoindex ordinals before a later table-level primary key. A
WordPress-shaped fixture verifies lookup through `sqlite_autoindex_wp_options_2`
when `sqlite_autoindex_wp_options_1` belongs to an earlier `autoload UNIQUE`
constraint and `PRIMARY KEY(option_name)` backs the option-name lookup.

Composite duplicate scans, custom collations, automatic-index collation
metadata, expression indexes, predicate-aware partial-index use, and full
WITHOUT ROWID table reads remain unported.

## Focused Native Mapping: Explicit Index Collation And DESC Order

This slice replaces the previous explicit-index regex boundary with a small
`CREATE INDEX` first-column parser. It records the first indexed column,
first-column `COLLATE` clause, first-column `ASC`/`DESC` direction, and whether
the index is partial. Native indexed `wp_options` point lookups now carry that
metadata into the index b-tree binary search.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  collate1.test collate2.test descidx1.test index3.test
```

Result: 6 Tcl script/permutation runs, 0 errors out of 286 tests in 00:00.

Focused upstream fixture boundary:

- `test/collate1.test` and `test/collate2.test` verify built-in collation
  ordering, especially `BINARY` versus `NOCASE` behavior.
- `test/descidx1.test` verifies that descending indexes reverse range and
  order traversal semantics while remaining usable for lookup.
- `test/index3.test` verifies legacy quoted-string index column identifiers and
  `COLLATE nocase DESC` syntax in indexed columns.

The native PHP tests cover parsing quoted first-column index identifiers,
`COLLATE NOCASE`, `DESC`, partial-index detection, expression-index rejection,
case-insensitive lookup through a descending `wp_options(option_name)` index,
and refusal to use unsupported partial `option_name` indexes for unconstrained
lookup. Built-in `RTRIM` comparison is implemented for text point lookups, but
custom collations, broader predicate-aware partial-index use, automatic-index
collation metadata, composite keys, expression indexes, and range scans remain
unported.

## Focused Native Mapping: IS NOT NULL Partial Index Point Lookup

SQLite uses a partial index whose predicate is `a IS NOT NULL` for point
lookups such as `a=5`, because the equality constraint implies the partial
predicate. The native PHP reader now recognizes a `CREATE INDEX` partial
predicate of the form `WHERE <first-column> IS NOT NULL` and allows that index
for non-null point lookups only. Other partial predicates continue to be
rejected for `wp_options` option-name lookup until broader predicate implication
is ported.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index7.test
```

Result: 2 Tcl script/permutation runs, 0 errors out of 60 tests in 00:00.

Focused upstream fixture boundary:

- `test/index7.test` verifies partial-index creation and the planner's use of
  `CREATE INDEX t2a1 ON t2(a) WHERE a IS NOT NULL` for `SELECT * FROM t2
  WHERE a=5`, while refusing to use unrelated partial indexes for queries that
  do not imply the predicate.

The native PHP tests now cover parsing qualified/quoted `IS NOT NULL` partial
predicates, keeping `indexRootPageForColumn()` unconstrained, exposing a
point-lookup root-page helper, resolving a WordPress-shaped
`wp_options(option_name) WHERE option_name IS NOT NULL` index, and continuing
to reject an unsupported `WHERE autoload='yes'` partial index for generic
option-name point lookup.
