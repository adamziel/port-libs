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
Expression indexes, broader predicate-aware partial-index use, custom collations,
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
broader predicate-aware partial-index use remain unported.

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

Optimized composite duplicate scans with secondary constraints, custom
collations, automatic-index collation metadata, expression indexes, broader
predicate-aware partial-index use, and full WITHOUT ROWID table reads remain
unported.

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
collation metadata, composite keys, expression indexes, and range variants
beyond the bounded first-column slice below remain unported.

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

## Focused Native Mapping: Duplicate First-Column Index Scans

SQLite non-unique indexes allow multiple rows with the same first indexed key.
This slice maps the bounded read-side behavior needed by WordPress recovery:
scan an explicit first-column index for all records whose first key equals the
requested value, decode the rowid stored as the last index-record field, and
load the matching `wp_options` rows through the table b-tree. Composite index
tails are preserved in the index payload but are not yet used for full
multi-column seek bounds.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index.test where.test
```

Result: 4 Tcl script/permutation runs, 0 errors out of 589 tests in 00:00.

Focused upstream fixture boundary:

- `test/index.test` section `index-10.*` verifies that an ordinary index may
  contain more than one entry with the same key and that equality lookup
  returns all matching rows.
- `test/where.test` section `where-6.*` exercises equality constraints against
  indexed first columns and composite-index ordering boundaries.

The native PHP tests now cover a WordPress-shaped
`CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)`
scan that returns duplicate `autoload='yes'` options in index order, honors a
result limit, returns an empty list for missing first-column values, and also
uses a safe `WHERE autoload IS NOT NULL` partial index for non-null autoload
point scans. Remaining index work includes optimized range seeks instead of
full index traversal, expression indexes, custom collations, automatic-index
collation metadata, and full composite-key range scans.

## Focused Native Mapping: Composite Index Prefix Constraints

SQLite can use a multi-column index for equality constraints across consecutive
leading columns. This slice maps a bounded read-side variant for WordPress
recovery: parse explicit `CREATE INDEX` column lists, retain per-column
collation metadata for leading columns, and resolve a
`wp_options(autoload, option_name)` index when both prefix values are known.
The current implementation still traverses the bounded native index reader
rather than performing lower/upper b-tree seek bounds.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  where4.test whereH.test index8.test
```

Result: 4 Tcl script/permutation runs, 0 errors out of 141 tests in 00:00.

Focused upstream fixture boundary:

- `test/where4.test` checks multi-column index constraints such as `w=1 AND x
  IS NULL AND y=3` and verifies that all constrained leading columns affect
  index lookup behavior.
- `test/whereH.test` verifies planner preference for the longer composite index
  when `a=? AND b=?` or deeper leading constraints make it more specific than a
  shorter candidate index.
- `test/index8.test` covers scoring for index scans where later indexed columns
  are relevant to filtering or ordering.

The native PHP tests now cover parsing full explicit index column lists,
rejecting expression-bearing composite lists for this slice, preserving
second-column `COLLATE NOCASE` metadata, accepting an implied
`WHERE autoload IS NOT NULL` partial predicate, and fetching one
WordPress option through `wp_options(autoload, option_name)` without scanning
the whole `wp_options` table. Remaining index work includes b-tree seek bounds
for composite prefixes, composite range constraints, expression indexes, custom
collations, automatic-index collation metadata, and broader partial-predicate
implication.

## Focused Native Mapping: First-Column Range Constraints

SQLite can use an index for bounded first-column range constraints such as
`a>=100 AND a<300`. This slice maps a native read-side subset for WordPress:
given an explicit or safe `WHERE option_name IS NOT NULL` partial
`wp_options(option_name)` index, scan decoded index records and return rows
whose first key is greater than or equal to a lower bound and less than an upper
bound under the index collation.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index.test wherelimit3.test index6.test index7.test
```

Result: 8 Tcl script/permutation runs, 0 errors out of 415 tests in 00:00.

Focused upstream fixture boundary:

- `test/index.test` section `index-14.*` checks indexed first-column text
  comparison boundaries for `>`, `>=`, `<`, and `<=`.
- `test/wherelimit3.test` verifies planner use of `SEARCH ... USING INDEX`
  for lower/upper range constraints such as `a>=100 AND a<300`.
- `test/index6.test` and `test/index7.test` verify that `a IS NOT NULL`
  partial indexes may be used when the query predicate implies non-nullness.

The native PHP tests now cover a WordPress-shaped transient recovery range
``option_name >= '_transient_' AND option_name < '_transient`'``, result limiting,
empty ranges, rowid resolution back through the table b-tree, and safe use of a
partial `WHERE option_name IS NOT NULL` index for non-null range bounds.
Remaining range work includes true b-tree lower/upper seek bounds instead of
full native index traversal, composite-key ranges, and broader partial-predicate
implication.

## Focused Native Mapping: Open-Ended And Inclusive Range Variants

SQLite range constraints can be lower-only, upper-only, or inclusive on either
side. This slice keeps the native reader bounded to first-column index records
but extends the WordPress-facing range helper to support nullable open bounds,
inclusive upper bounds, and explicit range-root discovery when at least one
bound is present. Bounded comparisons now skip `NULL` first-column index keys
so `option_name < 'm'` behaves like SQL comparison semantics instead of
treating `NULL` as a matching low sentinel.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index.test wherelimit3.test
```

Result: 4 Tcl script/permutation runs, 0 errors out of 275 tests in 00:00.

Focused upstream fixture boundary:

- `test/index.test` section `index-14.*` covers indexed comparison boundary
  variants including inclusive lower/upper operators.
- `test/wherelimit3.test` covers planner use of indexed lower and upper range
  constraints across bounded queries.

The native PHP tests now cover upper-only ranges, inclusive upper ranges,
lower-only ranges, result limits, explicit range-root lookup, safe use of
`WHERE option_name IS NOT NULL` partial indexes when any non-null bound implies
the predicate, rejection of unconstrained partial ranges, and descending
`wp_options(option_name DESC)` index traversal for inclusive bounded scans.
Remaining range work includes true b-tree lower/upper seek bounds instead of
full native index traversal, composite-key ranges, expression indexes, custom
collations, automatic-index collation metadata, and broader partial-predicate
implication.
