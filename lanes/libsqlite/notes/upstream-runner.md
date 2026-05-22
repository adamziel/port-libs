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
Expression indexes beyond the later `lower(column)` slice, broader
predicate-aware partial-index use, custom collations, and full composite-key
scans remain unported.

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
custom collations, expression indexes beyond `lower(column)`, and broader
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

Optimized composite duplicate scans with secondary constraints, custom
collations, expression indexes beyond `lower(column)`, broader
predicate-aware partial-index use, and full WITHOUT ROWID table reads remain
unported.

## Focused Native Mapping: sqlite_sequence AUTOINCREMENT Metadata And Allocation

This slice maps SQLite's internal `sqlite_sequence` table and the bounded
AUTOINCREMENT allocation state needed by WordPress import/recovery tooling.
SQLite creates `sqlite_sequence(name,seq)` for AUTOINCREMENT tables, keeps one
row per table that has allocated a sequence value, and allows the table
contents to be manually updated even though the system table itself cannot be
indexed or dropped. The native PHP path now models the rowid counter update
without adding a SQL execution engine or raw b-tree page writer.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick autoinc.test
```

Result: 1 Tcl script, 0 errors out of 88 tests in 00:00. This focused runner
was re-run for the allocation slice.

Focused upstream fixture boundary:

- `test/autoinc.test` verifies creation of `sqlite_sequence`, empty initial
  rows, monotonically tracked maximum sequence values, explicit rowid inserts
  that advance `seq`, generated rowids after deletes, deleted/missing sequence
  rows, independent AUTOINCREMENT table rows, manual invalid `seq` mutation,
  `NULL` name mutation, maximum-rowid failure, and the no-index/no-drop
  protection around `sqlite_sequence`.

The native PHP reader now resolves the `sqlite_sequence` table from
`sqlite_schema`, decodes its rows through the existing table b-tree reader, and
preserves mutable SQLite scalar `name`/`seq` values instead of forcing `seq` to
an integer. WordPress-oriented recovery tools can inspect post/comment/user
sequence counters from a database image without invoking the SQLite extension.
The new `SQLiteAutoincrementState` builds on those records plus the current
table b-tree reader to pick the next generated rowid, create a missing
sequence row in state, coerce invalid `seq` values the way SQLite's
AUTOINCREMENT VM path does, and advance the counter for explicit WordPress
import IDs without lowering an existing sequence. Raw SQL execution, b-tree
page writes, malformed schema recovery, attached/temp database sequence
tables, journaling/WAL, and trigger/upsert statement orchestration remain
outside this bounded slice.
The native PHP tests now cover allocation from an existing `sqlite_sequence`
row, missing sequence rows, invalid `seq` values, numeric text coercion, and
explicit WordPress import IDs advancing the sequence before the next generated
ID is chosen.

## Focused Native Mapping: Automatic Index Collation And DESC Metadata

SQLite automatic indexes created for `UNIQUE` or non-rowid `PRIMARY KEY`
constraints inherit per-column collations from explicit constraint terms or
from the table column declaration. They also preserve `DESC` terms for the
index key order. The native PHP reader now carries that metadata for the first
automatic-index column instead of assuming `BINARY ASC` for every
`sqlite_autoindex_*` row whose `sql` is `NULL`.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index3.test collate1.test descidx1.test
```

Result: 5 Tcl script/permutation runs, 0 errors out of 166 tests in 00:00.

Focused upstream fixture boundary:

- `test/index3.test` creates `UNIQUE('b' COLLATE nocase DESC)`, verifies the
  resulting `sqlite_autoindex_*` row, and searches through that autoindex using
  `COLLATE nocase`.
- `test/collate1.test` verifies column default collation precedence, including
  SQLite's compatibility behavior where repeated `COLLATE` clauses leave the
  last collation in effect.
- `src/build.c` stores explicit index-term collations first, otherwise the
  owning column default collation, and records requested sort order for each
  index term.

The native PHP tests now cover parsing automatic-index first-column metadata
from `CREATE TABLE` SQL, repeated column `COLLATE` declarations with last-token
precedence, table-level `UNIQUE(... COLLATE RTRIM DESC)` metadata, and a
WordPress-shaped `wp_options` recovery lookup through
`sqlite_autoindex_wp_options_1` where `UNIQUE(option_name COLLATE NOCASE DESC)`
requires both case-insensitive comparison and descending b-tree search.
Remaining automatic-index gaps include automatic composite-key range metadata
and custom collation callbacks.

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
custom collations, broader predicate-aware partial-index use, composite keys,
expression indexes beyond `lower(column)`, and range variants beyond the
bounded first-column slice below remain unported.

## Focused Native Mapping: Lower Expression Indexes

This slice adds a bounded expression-index parser for first-term
`lower(<column>)` indexes. Ordinary column-index discovery still rejects
expression terms, so an index on `lower(option_name)` is not mistaken for a
plain `option_name` index. The native lookup path matches SQLite's stored index
payload shape by searching for the ASCII-lowered expression key, then resolving
the rowid tail through the `wp_options` table b-tree.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr1.test indexexpr2.test indexexpr3.test
```

Result: 3 Tcl scripts, 0 errors out of 248 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr1.test` verifies expression indexes such as
  `substr(...)`, `length(...)`, `lower(...)`, expression collations, and the
  rule that expression indexes are used only for matching expression
  predicates.
- The same file rejects expressions inside `PRIMARY KEY` and `UNIQUE`
  constraints, so this PHP slice keeps automatic table-constraint inference
  column-only.
- The native slice intentionally supports only deterministic first-term
  `lower(column)` expression indexes. Arbitrary expression evaluation,
  multi-term expression prefixes, non-deterministic function rejection, and
  covering-index expression semantics remain unported.

The native PHP tests cover parsing `lower(option_name)` metadata with
`COLLATE`, `DESC`, and safe `WHERE option_name IS NOT NULL` predicates;
rejecting constant and unrelated expression indexes; preserving plain
`option_name` lookup rejection for expression indexes; and WordPress-shaped
case-folded option recovery lookups through
`CREATE INDEX ... ON wp_options(lower(option_name))`.

## Focused Native Mapping: Lower Expression Range Seek Bounds

This slice extends the bounded `lower(option_name)` expression-index reader
from point lookup to range scans. Caller-supplied bounds are ASCII-folded before
the index b-tree is searched, while returned rows are rechecked against the
folded range before being exposed. Only `WHERE option_name IS NOT NULL`
partial predicates are accepted for expression range scans; raw `option_name`
comparison predicates are not treated as implied by `lower(option_name)` bounds.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr1.test indexexpr2.test indexexpr3.test
```

Result: 3 Tcl scripts, 0 errors out of 248 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr1.test` verifies `lower(a)` expression-index use and
  expression-index planner boundaries.
- `test/indexexpr2.test` covers expression-index collation behavior and
  expression terms in indexed searches.
- `test/indexexpr3.test` covers expression terms in multi-column indexes.

The native PHP tests now cover a case-folded transient-style
`lower(option_name)` range scan, limit handling, rejection of ordinary
`option_name` range lookup against expression-only indexes, and a bounded
seek fixture where an out-of-range index branch is intentionally unreadable.
`examples/wordpress-lowercase-option-name-range.php` maps case-folded transient
recovery on hosts without the PHP SQLite extension. Remaining expression-index
work includes arbitrary expressions beyond `lower(column)`, expression
prefixes after ordinary indexed columns, custom collations, and expression
`IN (...)` lookups.

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

## Focused Native Mapping: OR Equality Partial Predicates

SQLite's partial-index planner allows an index whose predicate is an OR
expression when one query WHERE term implies one OR arm. The native PHP reader
now maps that bounded rule for OR predicates made of simple equality terms,
which is enough for WordPress recovery callers that know a concrete autoload
state and need to use a narrowed `wp_options(option_name)` index.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index7.test
```

Result: 2 Tcl script/permutation runs, 0 errors out of 60 tests in 00:00.

Focused upstream fixture boundary:

- `test/index7.test` verifies OR partial-index usability boundaries with
  `CREATE INDEX t2a2 ON t2(a) WHERE a<100 OR a>200`, including the requirement
  that a query term imply one OR arm before the partial index is usable.
- `src/where.c` `whereUsablePartialIndex()` and `src/expr.c`
  `sqlite3ExprImpliesExpr()` are the focused source boundaries: AND predicates
  must have every term usable, while OR predicates are usable if one branch is
  implied.

The native PHP tests now parse `WHERE autoload='yes' OR autoload='on'`, expose
the OR predicate tree, use the partial option-name index when the caller
supplies either matching autoload equality, and reject `autoload='no'`.
Comparison OR terms and custom collation-aware predicate comparison remain
outside this slice.

## Focused Native Mapping: AND-Connected Partial Predicates

SQLite uses a partial index with AND-connected WHERE terms only when all terms
are implied by the query. The native PHP reader now maps that bounded rule for
AND predicates composed of simple equality and `IS NOT NULL` terms, which
covers narrowed WordPress recovery indexes such as
`WHERE autoload='yes' AND option_name IS NOT NULL`.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index6.test index7.test indexA.test
```

Result: 6 Tcl script/permutation runs, 0 errors out of 300 tests in 00:00.

Focused upstream fixture boundary:

- `test/index6.test` section `index6-10.*` creates
  `CREATE INDEX t10x ON t10(d) WHERE a=1 AND b=2 AND c=3`, verifies use when
  all equality terms are present, and verifies non-use when a term is missing.
- `test/indexA.test` includes a partial index with `WHERE b='abc' AND i=5`
  used through `INDEXED BY`, anchoring the same conjunction shape in a
  rowid-table scenario.
- `src/where.c` `whereUsablePartialIndex()` and `src/expr.c`
  `sqlite3ExprImpliesExpr()` are the focused source boundaries: every
  AND-connected partial-index term must be implied before the index is safe.

The native PHP tests now parse AND predicate trees, use a
`wp_options(option_name)` partial index only when both `autoload='yes'` and
`option_name IS NOT NULL` are implied by supplied point constraints, and reject
the same index for `autoload='no'` or unconstrained option-name lookups.
Expression predicates and custom collation-aware predicate comparison remain
outside this slice.

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
full index traversal, expression indexes beyond `lower(column)`, custom
collations, and composite ranges beyond one equality prefix plus one range
column.

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
for composite prefixes, composite ranges beyond one equality prefix plus one
range column, expression indexes beyond `lower(column)`, and custom collations.

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
full native index traversal, composite ranges beyond one equality prefix plus
one range column, expression indexes beyond `lower(column)`, and custom
collations.

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
full native index traversal, composite ranges beyond one equality prefix plus
one range column, expression indexes beyond `lower(column)`, and custom
collations.

## Focused Native Mapping: Comparison And BETWEEN Partial Predicates

SQLite can use a partial index when query terms imply comparison predicates in
the partial-index WHERE clause, including bounded comparison terms and
`BETWEEN` ranges. This slice maps a conservative native read-side subset:
parse `<`, `<=`, `>`, `>=`, `!=`, `<>`, and `BETWEEN` predicates in explicit
`CREATE INDEX` statements, preserve AND/OR predicate trees without splitting
the `AND` inside `BETWEEN`, and use the partial index only when supplied point
or range constraints are contained by the parsed predicate.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index6.test index7.test
```

Result: 4 Tcl script/permutation runs, 0 errors out of 140 tests in 00:00.

Focused upstream fixture boundary:

- `test/index6.test` creates partial indexes such as
  `CREATE INDEX t2a2 ON t2(a) WHERE a<100 OR a>200` and verifies use only when
  the query includes an implying comparison term.
- `test/index6.test` also creates
  `CREATE INDEX t3b ON t3(b) WHERE xyzzy.t3.b BETWEEN 5 AND 10`, anchoring
  database-qualified `BETWEEN` predicates.
- `test/index7.test` repeats the same partial-index planner boundaries for
  WITHOUT ROWID tables.
- `src/where.c` `whereUsablePartialIndex()` and `src/expr.c`
  `sqlite3ExprImpliesExpr()` remain the source boundary for safe partial-index
  use: a partial index is an optimization only when the query term implies the
  partial WHERE term.

The native PHP tests now cover parsing comparison and `BETWEEN` partial
predicates, using a WordPress-shaped
``wp_options(option_name) WHERE option_name >= '_transient_' AND option_name < '_transient`'``
partial index for transient point and range recovery, rejecting the same index
for out-of-range option names, and using an inclusive `BETWEEN` partial index
for bounded transient scans. Remaining work includes optimized b-tree seek
bounds, expression indexes beyond `lower(column)`, custom collations, and
composite ranges beyond one equality prefix plus one range column.

## Focused Native Mapping: Composite Equality-Prefix Range Constraints

SQLite can use a composite index when the left-most indexed column is
constrained by equality and the next indexed column has range bounds. This
slice maps the read-side WordPress shape
`wp_options(autoload, option_name)`: constrain `autoload` first, then scan
bounded `option_name` keys for transient-style recovery queries without
decoding the whole options table.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  where.test whereH.test
```

Result: 2 Tcl script/permutation runs, 0 errors out of 335 tests in 00:00.

Focused upstream fixture boundary:

- `test/where.test` covers composite-index constraints such as `x=3 AND y<100`,
  `x=3 AND y>=121 AND y<=196`, and ascending/descending ordered range scans on
  the second indexed term.
- `test/whereH.test` covers longer equality prefixes before a range term, such
  as `a=? AND b=? AND c>=?` against a three-column index.

The native PHP tests now cover a WordPress-shaped
`wp_options(autoload, option_name)` index for non-autoloaded transient range
recovery, range limits, inclusive and empty same-bound ranges, safe rejection
when no range bound is provided, and a partial
`autoload='no' AND option_name IS NOT NULL` composite index with `NOCASE DESC`
second-column metadata. This run adds bounded composite b-tree traversal:
subtrees whose separator-key intervals cannot contain the requested
`autoload` equality plus `option_name` range are skipped before their pages are
decoded. A WordPress-shaped fixture keeps the matching transient rows in one
index branch and makes the unrelated branch invalid, proving the native reader
does not need healthy out-of-range index pages for constrained recovery.
Remaining work includes expression indexes beyond `lower(column)`, custom
collations, expression `IN (...)` lookups, and composite ranges beyond one
equality prefix plus one range column.

## Focused Native Mapping: Equality Partial Predicates

SQLite can use a partial index with an exact equality predicate when the query
predicate implies the partial-index WHERE clause. This slice maps the bounded
read-side form needed for WordPress recovery: parse simple partial-index
predicates such as `autoload='yes'`, require callers to supply the matching
equality constraint, and then use the `wp_options(option_name)` partial index
only for constrained autoloaded option lookups.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index7.test
```

Result: 2 Tcl script/permutation runs, 0 errors out of 60 tests in 00:00.

Focused upstream fixture boundary:

- `test/index7.test` section `index7-6.*` creates `CREATE INDEX i4 ON t4(c)
  WHERE d='xyz'` and verifies that `WHERE d='xyz' AND c='def'` searches the
  partial index.
- The same script continues to cover `IS NOT NULL` implication for point
  lookups and range-compatible non-null predicates.

The native PHP tests now cover parsing equality partial predicates, rejecting
the partial index for unconstrained `option_name` lookups, accepting it only
when `autoload='yes'` is supplied as an equality constraint, resolving matching
rowids back through the table b-tree, and refusing to use the index for a
non-implying autoload value. Remaining partial-index work includes
inequality/range implication, richer expression handling, and planner-style
combinations across more query terms.

## Focused Native Mapping: IN-List Option Lookups

SQLite's `IN (...)` operator treats duplicate RHS values as a set for result
rows and does not match `NULL` RHS terms in a `WHERE` predicate. The native PHP
reader now maps that bounded first-column behavior for indexed
`wp_options(option_name)` reads, including built-in collation handling and
limits.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  where.test where2.test index6.test
```

Result: 4 Tcl script/permutation runs, 0 errors out of 490 tests in 00:00.

Focused upstream fixture boundary:

- `test/where.test` section `where-5.*` covers indexed `IN` lookups with
  reordered RHS values and ascending/descending output.
- `test/where2.test` section `where2-4.6*` verifies duplicate RHS values do
  not duplicate output rows.
- `test/index6.test` covers partial-index planner boundaries for `IS NOT NULL`
  and exact `IN (...)` predicates, including the upstream behavior where a
  partial `IN` predicate is only usable for an exact matching `IN` query shape.

The native PHP tests now cover bulk WordPress option-name reads through an
explicit `option_name` index, duplicate RHS suppression, `NULL` RHS
non-matching semantics, safe use of `WHERE option_name IS NOT NULL` partial
indexes, and exact-order `WHERE option_name IN ('siteurl','home')` partial
indexes. IN-list scans now also derive conservative first-key intervals for
index interior children, so out-of-range subtrees are skipped before their
pages are parsed. The focused regression fixture uses a WordPress-shaped
`wp_options(option_name)` lookup for `siteurl` while the unrelated left-hand
index branch is intentionally invalid. The new
`examples/wordpress-options-by-name-list.php` script maps bulk option
preload/recovery workflows on hosts without the PHP SQLite extension.
Remaining work includes expression-index `IN` lookup families beyond
`lower(column)`, custom collations, and broader composite `IN` constraints.

## Focused Native Mapping: Lower Expression IN-List Option Lookups

SQLite can use an expression index for an `IN (...)` predicate when the query
expression matches the indexed expression. The native PHP reader now maps the
WordPress-oriented `lower(option_name) IN (...)` slice: caller-supplied names
are case-folded with SQLite-style ASCII lowercasing, duplicate RHS names do not
duplicate result rows, `NULL` RHS terms do not match, and safe
`WHERE option_name IS NOT NULL` partial expression indexes can be used.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr1.test where2.test
```

Result: 2 Tcl scripts, 0 errors out of 199 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr1.test` sections `indexexpr1-150` and `indexexpr1-250`
  verify expression-index `IN (...)` probes and planner use for
  `substr(a,b,3) IN (...)` on rowid and WITHOUT ROWID tables.
- `test/where2.test` section `where2-4.6*` verifies duplicate RHS values do
  not duplicate output rows for indexed `IN` probes.

The native PHP tests now cover `wp_options(lower(option_name) COLLATE NOCASE)`
IN-list reads for mixed-case `SiteURL`/`HOME` option names, duplicate RHS
suppression, `NULL` RHS non-matching behavior, rejection as a plain
`option_name` index, limit handling, invalid RHS types, and bounded lower-key
seek pruning where an out-of-range index branch is intentionally unreadable.
The new `examples/wordpress-lowercase-options-by-name-list.php` script maps
case-folded bulk option preload/recovery workflows on hosts without the PHP
SQLite extension.

## Focused Native Mapping: Upper Expression Option Lookups

This slice adds first-term `upper(option_name)` expression-index discovery and
lookup. SQLite's built-in `upper()` function is bytewise ASCII-only without the
ICU extension, so the native PHP reader now applies the same ASCII uppercase
mapping to caller-supplied option names and to row verification after the index
points back to the `wp_options` table row. The implementation intentionally
accepts only safe `option_name IS NOT NULL` partial expression indexes for this
new path.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  func.test indexexpr1.test
```

Result: 2 Tcl scripts, 0 errors out of 15138 tests in 00:01.

Focused upstream fixture boundary:

- `src/func.c` implements `upper()` and `lower()` by applying
  `sqlite3Toupper()`/`sqlite3Tolower()` byte by byte.
- `test/func.test` section `func-5.*` verifies `upper()`/`lower()` behavior
  and argument-count boundaries.
- `test/indexexpr1.test` verifies deterministic expression-index lookup and
  planner use for scalar expression keys, including the existing `lower(a)`
  expression-index family.

The native PHP tests now cover parsing `upper(option_name)` metadata without
mistaking it for an ordinary column index, point lookup through
`wp_options(upper(option_name))`, IN-list lookup with duplicate RHS
suppression and `NULL` non-matching behavior, SQLite-style ASCII-only folding
for a non-ASCII option name such as `café`, and rejection as a plain
`option_name` index. The new
`examples/wordpress-uppercase-options-by-name-list.php` script maps bulk
ASCII-folded option recovery on hosts without the PHP SQLite extension.

## Focused Native Mapping: First-Column B-Tree Seek Bounds

SQLite range and equality probes over an index move a b-tree cursor to the
bounded key interval rather than decoding unrelated branches. The native PHP
reader now maps that bounded read-side behavior for first-column point and
range scans by deriving conservative first-key intervals for index interior
children. Out-of-range subtrees are skipped before their pages are parsed, while
matching leaf and interior records still use the existing SQLite scalar
comparison rules and rowid resolution.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  index.test wherelimit3.test where.test
```

Result: 5 Tcl script/permutation runs, 0 errors out of 593 tests in 00:00.

Focused upstream fixture boundary:

- `test/index.test` section `index-14.*` covers indexed first-column comparison
  boundaries for `>`, `>=`, `<`, and `<=`.
- `test/wherelimit3.test` records planner output such as
  `SEARCH ... USING INDEX ... (a>? AND a<?)`, anchoring the same lower/upper
  index-search shape used by this native slice.
- `test/where.test` covers equality and range constraints against indexed first
  columns and composite index boundaries.

The native PHP test adds a WordPress-shaped `wp_options(option_name)` range
lookup where the requested lower bound is in the index root's right-hand
subtree and the left-hand child page is intentionally invalid. The lookup now
returns `siteurl` without reading that out-of-range branch. Remaining seek work
includes expression indexes beyond the first `lower(column)`/`upper(column)` slices and
expression seek bounds.

## Focused Native Mapping: Substr Expression Index Prefixes

SQLite expression indexes can use deterministic scalar expressions such as
`substr(column,start,length)` as the indexed key. The native PHP reader now
parses first-term `substr()`/`substring()` expression-index metadata when the
start and optional length are positive integer literals, preserves built-in
collation and `DESC` metadata, and uses `substr(option_name,1,N)` expression
indexes for WordPress option-name prefix scans. Partial expression indexes are
accepted only for the safe `option_name IS NOT NULL` predicate family in this
slice.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr1.test indexexpr2.test
```

Result: 2 Tcl scripts, 0 errors out of 234 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr1.test` covers `CREATE INDEX ... ON t1(substr(a,1,12))`,
  equality predicates with the expression on either side, composite
  expression indexes such as `(b, substr(a,2,3), c)`, and expression
  collations such as `substr(b,2,4) COLLATE nocase`.
- `test/indexexpr2.test` covers `substr(a, 2) COLLATE NOCASE` expression
  index ordering and lookup behavior.

The native PHP tests now cover parser rejection for variable `substr()` starts,
expression metadata for qualified and quoted column names, and a
WordPress-shaped `wp_options(substr(option_name,1,11) COLLATE NOCASE)` index
that returns `_transient_` option buckets without using the SQLite extension.
The `examples/wordpress-option-name-prefix.php` script maps transient/cache
bucket inspection on hosts where only a database image is available. Remaining
expression-index work includes variable-start `substr(a,b,3)`, expression
`IN` lookups beyond the literal-start prefix-list slice, `abs()`,
broader `json_extract()` paths beyond the later strict `$.key` point slice,
arbitrary deterministic expressions, and custom collations.

## Focused Native Mapping: Substr Expression Index Prefix IN Lists

SQLite can probe expression indexes with `IN (...)` constraints. This slice
adds a bounded first-term `substr(option_name,1,N) IN (...)` path to the native
reader. The implementation accepts same-length non-empty prefix values, ignores
`NULL` RHS values for matching, suppresses duplicate RHS row output by scanning
index records once, honors built-in collation and `DESC` metadata, and uses the
existing bounded index traversal so out-of-range expression-index subtrees do
not need to be readable.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr1.test where2.test
```

Result: 2 Tcl scripts, 0 errors out of 199 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr1.test` verifies expression-index `IN` probes such as
  `substr(a,b,3) IN ('and','l_t','xyz')` use the expression index and return
  only matching rows.
- `test/where2.test` covers duplicate RHS `IN` values without duplicate output
  rows, which maps the native prefix-list scan behavior.

The native PHP tests now cover a WordPress-shaped
`wp_options(substr(option_name,1,11) COLLATE NOCASE)` index that reads both
`_transient_` and `_site_trans` buckets from one prefix list, ignores `NULL`
RHS values, rejects mixed prefix lengths, and prunes an intentionally invalid
out-of-range index branch. The new
`examples/wordpress-option-name-prefix-list.php` script maps cache and
site-transient recovery on hosts where the PHP SQLite extension is unavailable.

## Focused Native Mapping: Negative-Start Substr Expression Index Suffix Buckets

This slice extends the bounded `substr(column,...)` parser from positive
literal starts to SQLite's negative-start expression-index shape. A first-term
`substr(option_name,-N)` index stores the last `N` characters of each option
name. The native PHP reader now preserves the negative start, rejects the
unsupported zero start, and can use the stored suffix key with built-in
collations before resolving the rowid tail through `wp_options`.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr2.test
```

Result: 1 Tcl script, 0 errors out of 127 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr2.test` creates `CREATE INDEX i4 ON t4( Substr(a,-2) COLLATE
  nocase )` and verifies the planner can scan that expression index for
  negative-start suffix ordering.
- The same script covers nearby expression-index collation boundaries for
  `substr(a, 2) COLLATE NOCASE`.

The native PHP tests now cover parsing `Substr(option_name,-9) COLLATE NOCASE
DESC`, rejecting start `0`, using a WordPress-shaped
`wp_options(substr(option_name,-9) COLLATE NOCASE DESC)` index to find
`*_settings` options case-insensitively, limit handling, and continuing to
reject expression indexes as ordinary column indexes. The new
`examples/wordpress-option-name-suffix.php` script maps plugin/theme settings
bucket inspection when only a SQLite database image is available.

## Focused Native Mapping: Length Expression Index Buckets

This slice adds a second bounded scalar expression family beyond
`lower(column)` and positive-start `substr(column,...)`: first-term
`length(column)` expression indexes. The native PHP reader parses
`CREATE INDEX ... ON wp_options(length(option_name))`, preserves `DESC`
metadata, rejects the expression as an ordinary column index, and searches the
stored integer expression key before resolving the rowid tail through the
`wp_options` table b-tree. Partial expression indexes remain limited to the
safe `option_name IS NOT NULL` predicate family for this slice.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr1.test
```

Result: 1 Tcl script, 0 errors out of 107 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr1.test` creates `CREATE INDEX t1alen ON t1(length(a))` and
  verifies the expression index can provide covering order for `length(a)`.
- The same file covers expression-index matching boundaries and deterministic
  function restrictions for nearby expression-index cases.

The native PHP tests now cover parsing `length(option_name)` metadata with
qualified/quoted column names, `DESC`, and safe `WHERE option_name IS NOT NULL`
predicates; rejecting constant and unrelated expression terms; and a
WordPress-shaped exact-length option-name bucket lookup that returns options
such as `home`, `cron`, and UTF-8 text names using SQLite-style character
length without scanning the whole table. The new
`examples/wordpress-option-name-length.php` script maps recovery or audit tools
that bucket suspicious, short, or policy-sensitive WordPress option names on
hosts where the PHP SQLite extension is unavailable.

## Focused Native Mapping: Length Expression Index IN Lists

SQLite's `IN (...)` lookup behavior also applies to expression-index keys.
This slice extends the existing first-term `length(option_name)` expression
path from one exact bucket to a bounded integer length list. The native PHP
reader validates non-negative integer RHS values, ignores `NULL` RHS values
for matching, suppresses duplicate RHS output by scanning index records once,
honors `DESC` metadata, and prunes out-of-range index subtrees before page
decoding.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr1.test where2.test
```

Result: 2 Tcl scripts, 0 errors out of 199 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr1.test` covers `length(a)` expression-index keys and nearby
  expression-index planner matching.
- `test/where2.test` covers indexed `IN (...)` lookup behavior, including
  duplicate RHS values not producing duplicate output rows.

The native PHP tests now cover a WordPress-shaped
`wp_options(length(option_name) DESC)` index that reads multiple length buckets
such as `4` and `10` in one pass, rejects non-integer and negative RHS values,
ignores `NULL` RHS values, preserves UTF-8 character length behavior for
stored option names, and skips an intentionally invalid out-of-range index
branch. The new `examples/wordpress-option-name-length-list.php` script maps
multi-bucket option-name audits on hosts where the PHP SQLite extension is
unavailable.

## Focused Native Mapping: Length Expression Index Ranges

SQLite indexed range behavior also applies to expression-index keys. This
slice extends the first-term `length(option_name)` expression path from exact
and `IN (...)` buckets to bounded integer ranges. The native PHP reader
accepts nullable lower/upper bounds, optional inclusive upper bounds, rejects
negative length bounds, honors `DESC` metadata, accepts only safe
`option_name IS NOT NULL` partial predicates, and prunes out-of-range index
subtrees before page decoding.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr1.test where.test
```

Result: 2 Tcl scripts, 0 errors out of 425 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr1.test` covers `length(a)` expression-index keys and nearby
  expression-index planner matching.
- `test/where.test` covers indexed lower/upper range constraints and inclusive
  bound behavior used by the native bounded expression-index range traversal.

The native PHP tests now cover a WordPress-shaped
`wp_options(length(option_name) DESC)` index that reads medium-length option
names such as `db_version` and `siteurl`, supports exact inclusive single
length ranges, open bounds, limit handling, UTF-8 character length checks, and
skips an intentionally invalid out-of-range index branch. The new
`examples/wordpress-option-name-length-range.php` script maps option-name
length audits on hosts where the PHP SQLite extension is unavailable.

## Focused Native Mapping: CAST AS INTEGER Expression Indexes

This slice adds a bounded expression-index family for
`CAST(column AS INTEGER)`. The native PHP reader parses first-term
`CAST(option_value AS INTEGER)` expression indexes, keeps `DESC` metadata,
rejects the expression as an ordinary column index, accepts only the safe
`option_value IS NOT NULL` partial-predicate family, and searches stored
integer expression keys before resolving rowids through `wp_options`. It now
supports both exact point lookup and bounded `IN (...)` lookup over integer
cast buckets, plus nullable lower/upper range bounds for integer cast audits.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr2.test where2.test
```

Result: 2 Tcl scripts, 0 errors out of 219 tests in 00:00.

Focused range runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr2.test where.test
```

Result: 2 Tcl scripts, 0 errors out of 445 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr2.test` creates `CREATE INDEX x1i ON x1( CAST(b AS INTEGER) )`
  and verifies `CAST(b AS INTEGER)=123` matches integer, text, mixed text
  such as `123abc`, and real storage-class values.
- The same file covers nearby expression-index planner boundaries and
  expression collation behavior.
- `test/where2.test` covers indexed `IN (...)` lookup behavior, including
  duplicate RHS values not producing duplicate output rows.
- `test/where.test` covers lower/upper range constraints, inclusive bounds,
  and index-ordered range traversal boundaries used by this native cast range
  slice.

The native PHP tests now cover parsing `CAST(option_value AS INTEGER)` with a
qualified/quoted column, `DESC`, and a safe `WHERE option_value IS NOT NULL`
predicate; rejecting `CAST(... AS TEXT)` and constant casts for this bounded
slice; and a WordPress-shaped numeric option-value lookup that finds
`db_version`-style rows through SQLite's text-prefix integer cast semantics.
The IN-list tests read multiple integer cast buckets in one index pass, ignore
`NULL` RHS values, suppress duplicate RHS output, reject non-integer terms for
this bounded API, and skip an intentionally invalid out-of-range index branch.
The range tests scan `CAST(option_value AS INTEGER)` keys through descending
and ascending expression indexes, handle open and inclusive upper bounds, keep
SQLite-style casts such as `123.9` and non-numeric text, reject unbounded range
calls, and skip an intentionally invalid out-of-range index branch.
The new `examples/wordpress-option-value-integer.php` and
`examples/wordpress-option-value-integer-list.php` scripts map recovery or
audit tools that need one or more numeric option values without a full table
scan or the PHP SQLite extension. The new
`examples/wordpress-option-value-integer-range.php` script maps numeric option
audits such as version/counter ranges through the same native index path.

## Focused Native Mapping: JSON Extract Expression Indexes

This slice adds a bounded expression-index family for
`json_extract(column,'$.key')`. The native PHP reader parses first-term
`json_extract(option_value,'$.key')` expression indexes, preserves collation
and `DESC` metadata, rejects the expression as an ordinary column index,
accepts only safe `option_value IS NOT NULL` partial predicates, and searches
stored JSON scalar expression keys before resolving rowids through
`wp_options`. The verification step evaluates strict JSON option values with
the same simple object-member path before returning a row.

Focused upstream runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr3.test
```

Result: 1 Tcl script, 0 errors out of 14 tests in 00:00.

Focused IN-list runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  indexexpr3.test where2.test
```

Result: 2 Tcl scripts, 0 errors out of 106 tests in 00:00.

Focused upstream fixture boundary:

- `test/indexexpr3.test` creates
  `CREATE INDEX i1 ON t1( json_extract(j, '$.x') )` and verifies SQLite can
  satisfy `json_extract()` reads from expression-index payloads without
  re-running the function for covered cases.
- The same file creates `CREATE INDEX i1 ON t1( a, json_extract(j, '$.x') )`
  and checks the composite expression-index planner boundary for `a=?`.
- `test/where2.test` covers indexed `IN (...)` lookup behavior, including
  duplicate RHS values not producing duplicate output rows.

The native PHP tests now cover parsing `json_extract(option_value,'$.enabled')`
metadata with qualified/quoted column names, literal JSON paths, collation,
`DESC`, and safe partial predicates; rejecting constant JSON arguments and
multi-path calls for this bounded slice; and a WordPress-shaped plugin settings
lookup that reads boolean/number JSON scalar keys from
`wp_options(option_value)` without scanning the full table. The IN-list tests
read multiple JSON scalar buckets in one index pass, honor `COLLATE NOCASE`,
ignore `NULL` RHS values for matching, suppress duplicate RHS output, reject
unsupported lookup values, and skip an intentionally invalid out-of-range index
branch. The new `examples/wordpress-json-option-value.php` and
`examples/wordpress-json-option-value-list.php` scripts map recovery or audit
tools that need one or more indexed plugin/theme JSON settings such as enabled
flags or mode lists on hosts where the PHP SQLite extension is unavailable.
