# libsqlite Application Scenario

SQLite fallback/read-write tooling for Application hosts where the SQLite extension is unavailable.

## B-tree Interior Redistribute Pointer-Map Current Next32 Scenario

`examples/application-btree-interior-redistribute-pointermap-current-next32.php`
reports a copied `wp_options` option-name index interior sibling
redistribution applied to current page images, including parent divider
replacement and auto-vacuum pointer-map parent rewrites for moved child pages
without requiring ext/sqlite.

Status delta 2026-05-27 isolated B-tree slice: added
`SQLiteBTreeInteriorRedistributionApplyPlan` and focused
`SQLiteBTreeInteriorRedistributePointerMapCurrentNext32Test.php` with 62
passing assertions over table and index interior redistribution application,
post-apply child lists, parent divider page images, pointer-map page images,
and malformed/non-auto-vacuum guards. This does not repeat the accepted
standalone redistribution plan, page relocation, index-interior merge, root
collapse, or overflow freelist release clusters.

## Expression Collation/Affinity CASE Scenario

Copied `wp_options` diagnostics can now classify option names through simple
`CASE` expressions that carry explicit SQLite collations. A CASE base or WHEN
arm using `COLLATE NOCASE` matches mixed-case option names such as `siteurl`
and `SiteURL`, `COLLATE RTRIM` preserves SQLite trailing-space comparison
behavior, and NUMERIC/TEXT/BLOB casts keep storage-class comparison semantics
for branch selection. The smoke in
`examples/application-expression-collation-affinity-current-next21.php` reports
case-folded option buckets and NUMERIC CAST branch buckets without requiring
ext/sqlite.

Status delta 2026-05-27 isolated expression/collation slice: added
`SQLiteExpressionCollationAffinityCurrentNext21Test.php` with 30 focused PASS
lines and updated CASE projection dispatch to reuse the expression evaluator.
Dependency closure: no new support component is needed; this reuses lane-local
SELECT parsing, built-in collation comparison, and CAST affinity handling.

## UPSERT RETURNING Multi-Conflict Scenario

Copied Application import staging can now preview multiple SQLite UPSERT conflict
arms with RETURNING. The smoke
`examples/application-upsert-returning-multi-conflict-current.php` reports ordered
`ON CONFLICT` handling where `option_name` conflicts update first, `autoload`
conflicts update a current row after earlier statement changes, `slot`
conflicts can `DO NOTHING` without RETURNING output, and a catch-all conflict
arm can handle another current UNIQUE conflict without requiring ext/sqlite.

Status delta 2026-05-27 isolated
`yield-sqlite-upsert-returning-multi-conflict-current-next17` slice: added
`SQLiteUpsertDoUpdateWherePlan::executeConflictArms()` plus 44 focused PASS
lines in `SQLiteUpsertReturningMultiConflictCurrentNext17Test.php`. Focused
adjacent UPSERT verification passed at 3 files / 147 assertions / 0 failures.
Mapped upstream coverage is unchanged because this is focused PHP behavior
coverage, not a newly admitted upstream inventory row.

## INSERT SELECT Conflict Scenario

Copied Application archive/import staging can now model SQLite conflict handling
for `INSERT OR IGNORE ... SELECT` and `INSERT OR REPLACE ... SELECT` without
ext/sqlite. The smoke
`examples/application-insert-select-conflict-current.php` reports skipped
current/prior unique `option_name` rows for IGNORE, deleted current/prior
conflicts for REPLACE, and final archive row names.

Status delta 2026-05-27 isolated DML conflict slice: updated
`SQLiteInsertSelectSql` to parse conflict actions and apply caller-supplied
UNIQUE metadata across current and newly inserted row-array state. Focused
assertions cover IGNORE skips, REPLACE delete-before-insert behavior, conflicts
against prior inserted rows, composite UNIQUE keys, SQL NULL non-conflicts,
parameterized SELECT sources, ABORT/FAIL/ROLLBACK conflict errors, and
malformed unique metadata guards. Focused verification passed at
`SQLiteHeaderTest.php` plus `SQLiteInsertSelectConflictTest.php`: 2 test files,
9775 assertions, 0 failures. The patch contributes 27 PASS cases: one
`SQLiteHeaderTest.php` corpus case plus 26 split
`SQLiteInsertSelectConflictTest.php` cases. Lane `phpPass` moves from 803 to
830.

Dependency closure: no new support component is needed. This reuses lane-local
SELECT SQL execution and pure PHP Application option fixtures. Non-overlap: this
avoids accepted low-level `INSERT OR REPLACE` option planning and accepted
`UPDATE FROM` conflict behavior by covering parser-level
`INSERT INTO ... SELECT` conflict execution only.

## PRAGMA Locking Mode Preflight Scenario

Copied Application database preflight can now report SQLite `PRAGMA locking_mode`
connection state without ext/sqlite. The existing
`examples/application-pragma-preflight.php` smoke includes current `normal`,
assigned `exclusive`, restored `normal`, and TEMP schema `exclusive` rows next
to header-derived pragma metadata.

Status delta 2026-05-27 isolated libsqlite slice: added
`SQLitePragmaLockingMode` for bounded `PRAGMA locking_mode` current-state
execution. Focused assertions cover default NORMAL mode, schema-qualified
queries, EXCLUSIVE and NORMAL assignments, invalid-mode no-op behavior, TEMP
database EXCLUSIVE behavior, row result shape, and malformed PRAGMA guards.
Focused `SQLiteHeaderTest.php` passed at 9570 assertions, up from the prior
lane-status focused count of 9516 assertions (`+54`).

Dependency closure: no new support component is needed. This reuses lane-local
pragma/header state and pure PHP Application copy preflight fixtures. Non-overlap:
this avoids accepted VFS lock byte-range/process-lock/lock-state work by
covering only SQLite's connection-level `locking_mode` PRAGMA state.

## UPDATE/DELETE ORDER BY LIMIT Scenario

Copied Application option cleanup previews can now model SQLite builds that
enable `ORDER BY` and `LIMIT` on `UPDATE` and `DELETE`. The smoke
`examples/application-update-delete-limit-order.php` reports transient option
rows selected by sorted metadata, DELETE remaining rows, UPDATE selected rows,
and UPDATE mutation IDs in source row order without requiring ext/sqlite.

Status delta 2026-05-27 isolated SQL-exec slice: added
`SQLiteUpdateDeleteLimitPlan` for bounded decoded row arrays. Focused
assertions cover WHERE qualification, ORDER BY row selection, LIMIT/OFFSET,
negative LIMIT, source-order DELETE/UPDATE result materialization, callable
UPDATE assignments, custom rowid columns, summary output, and malformed plan
guards. Selected focused checks passed at 75 assertions and full focused
`SQLiteHeaderTest.php` passed at 9452 assertions, up from the prior
lane-status focused count of 9377 assertions (`+75`).

Dependency closure: no new support component is needed. This reuses
lane-local row-array sorting, LIMIT/OFFSET semantics, and pure PHP Application
option fixtures. Non-overlap: this avoids accepted SELECT `ORDER BY`/`LIMIT`,
expression `ORDER BY`, comma `LIMIT`, scalar subquery, VFS, WAL, JSON, and
B-tree clusters by covering UPDATE/DELETE limited row selection instead.

## SELECT SQL Scalar Subquery Scenario

Copied Application option previews can now use correlated scalar SELECT
subqueries as SQL expressions, not only WHERE `EXISTS`/`IN` filters. The smoke
`examples/application-select-sql-scalar-subquery.php` reports `wp_options` rows
enriched by metadata from `option_meta` in projection lists, expression
`ORDER BY`, and composed labels without requiring ext/sqlite.

Status delta 2026-05-27 isolated SQL-exec slice: added scalar `subquery`
expression dispatch through `SQLiteSelectSql`, `SQLiteSelectExpression`,
`SQLiteSelectProjection`, and `SQLiteSelectPredicate`. Focused assertions cover
correlated projection values, empty scalar subquery NULL results, WHERE operand
comparison, hidden expression ORDER BY columns, function and binary expression
composition, plan shape, multi-column subquery rejection, missing source
guards, unsupported joined subqueries, and unsupported grouped scalar
subqueries. Focused `SQLiteHeaderTest.php` passed at 9377 assertions and 0
failures in this worktree, up from the pre-slice focused count of 9351
assertions (`+26`).

Dependency closure: no new support component is needed. This reuses lane-local
SELECT SQL parsing, correlated subquery row execution, row-array expression
evaluation, and pure PHP Application option fixtures. Non-overlap: this avoids
the accepted parser-level correlated `EXISTS`/`IN` subquery filter cluster and
adds scalar subquery expression positions instead.

## JSON Table Disjunctive Pushdown Scenario

Copied Application plugin settings can now plan OR-shaped `json_tree()`
diagnostics as separate visible-column constraint branches. The smoke
`examples/application-json-table-disjunctive-pushdown.php` reports branch-local
`key`, `type`, `atom`, and `fullkey` pushdown estimates, residual filtering,
and duplicate row suppression without requiring ext/sqlite.

Status delta 2026-05-27 isolated JSON table slice: added
`SQLiteJsonTablePlan::alternativePlan()` and `filteredAlternativeRows()` for
bounded disjunctive visible-column planner branches. Focused assertions cover
branch metadata, visible pushdown/residual preservation, estimated rows/cost,
row materialization, duplicate branch suppression, unusable hidden sources,
and malformed branch guards.

Dependency closure: no new support component is needed. This reuses
lane-local JSON table planning, JSON path/JSON5/JSONB parsing, residual
predicate comparison, and pure PHP row arrays.

## Auto-vacuum Pointer-map Apply Scenario

Copied Application repair/import flows can now apply auto-vacuum pointer-map
updates directly into complete database page images after a B-tree page is
materialized or moved. The smoke
`examples/application-autovacuum-pointer-map-apply.php` reports changed
pointer-map pages, freed-page entries, retargeted overflow ownership, and a
new B-tree owner page across the first and second pointer-map pages without
requiring ext/sqlite.

Status delta 2026-05-27 isolated B-tree slice: added
`SQLiteAutoVacuumPointerMapApplyPlan` with focused assertions for complete
database-byte materialization, base page-image merging, multiple pointer-map
page updates, applied-entry summaries, post-apply database inspection, pointer
map page skip math, Application option readability after apply, and malformed
apply guards. Focused `SQLiteHeaderTest.php` passes at 8909 assertions and
0 failures in this worktree, up from the current focused baseline of 8861
assertions (`+48`).

Dependency closure: no new support component is needed. This reuses lane-local
auto-vacuum pointer-map planning, B-tree page images, SQLite database page
readers, and pure PHP Application option fixtures.

## B-tree Freeblock/Freelist Rebalance Scenario

Copied Application transient cleanup can now keep a non-empty table or
`option_name` index leaf page after deletion while releasing that deleted
cell's obsolete overflow pages into the SQLite freelist. The smoke
`examples/application-btree-freeblock-freelist-rebalance.php` reports reusable
leaf freeblocks, freelist trunk/leaf page images, secure-delete clearing of
released overflow pages, and auto-vacuum pointer-map free-page rewrites without
requiring ext/sqlite.

Status delta 2026-05-27 isolated B-tree slice: added
`SQLiteBTreeFreeblockFreelistRebalancePlan` with focused assertions for table
and index leaves, overflow release into empty and existing freelists,
auto-vacuum pointer-map rewrites, post-plan database inspection, secure-delete
clearing, and malformed rebalance guards. Focused `SQLiteHeaderTest.php`
passes at 8500 assertions and 0 failures in this worktree.

Dependency closure: no new support component is needed. This reuses lane-local
table/index delete results, B-tree freeblock accounting, freelist free
planning, secure-delete clearing, and auto-vacuum pointer-map mutation.

## SELECT SQL Compound Scenario

Copied single-site and network `wp_options` previews can now execute bounded
compound SQLite SELECT text through the native PHP planner. The smoke
`examples/application-select-sql-compound.php` reports `UNION` duplicate
removal, `UNION ALL` duplicate retention, `INTERSECT`, local-only `EXCEPT`
rows, final `ORDER BY`, `LIMIT`/`OFFSET`, and CTE-fed compound arms without
requiring ext/sqlite.

Status delta 2026-05-27 isolated SQL-exec slice: updated `SQLiteSelectSql` to
recognize top-level compound SELECT operators, execute each arm through the
existing SELECT SQL planner, combine rows through the accepted compound helper,
and apply final compound ordering and limits. Focused `SQLiteHeaderTest.php`
passed at 8324 assertions, up from the current lane-status focused baseline of
8273 (`+51`). Lane `phpPass` moves from 795 to 796 and mapped coverage moves
from 446 to 447. Dependency closure: no new support component is needed; this
reuses lane-local SELECT SQL parsing, query-plan execution, compound row
combination, CTE materialization, scalar dispatch, and pure PHP row arrays.

## SELECT SQL Comma LIMIT Scenario

Copied Application option previews can now execute bounded SQLite SELECT text that
uses `LIMIT offset,count`, preserving SQLite's comma-form operand order where
the first value is the offset and the second value is the row count. The smoke
`examples/application-select-sql-limit-comma.php` reports plain `wp_options`
rows and grouped autoload buckets selected through the comma form without
requiring ext/sqlite.

Status delta 2026-05-27 isolated SQL-exec slice: updated
`SQLiteSelectSql::limitOffset()` to parse the comma LIMIT form and reuse the
accepted row-array limit/offset executor. Focused assertions cover direct rows,
`LIMIT ... OFFSET` equivalence, negative count no-limit behavior, query-plan
shape, grouped aggregates, joins, JSON table rows, and malformed comma LIMIT
guards. Focused `SQLiteHeaderTest.php` passed at 7393 assertions and 0
failures from the current accepted source. Lane `phpPass` moves from 783 to
784 and mapped coverage moves from 438 to 439. Dependency closure: no new
support component is needed; this reuses lane-local SELECT SQL parsing,
query-plan execution, join/group/JSON table rows, and pure PHP result limiting.

## Unicode GLOB Range Scenario

Copied Application option diagnostics can now match plugin option names through
SQLite-style `GLOB` character classes whose ranges compare Unicode codepoints,
not only single-byte ASCII endpoints. The smoke
`examples/application-option-name-like-glob.php` reports Latin, Greek, Cyrillic,
CJK, emoji, negated Unicode classes, literal bracket and hyphen handling, and
reversed-range compatibility without requiring ext/sqlite.

Status delta 2026-05-27 clean integration replay: updated
`SQLiteDatabase::globMatches()` so character classes retain Unicode ranges
and test membership by decoded codepoint. Focused assertions cover Latin,
Greek, Cyrillic, CJK, emoji, negated Unicode classes, literal bracket/hyphen
classes, reversed ranges, and copied `wp_options` Unicode GLOB smoke output.
Focused `SQLiteHeaderTest.php` passed at 7336 assertions and 0 failures from
the current accepted source after replay. Lane `phpPass` moves from 782 to 783
and mapped coverage moves from 437 to 438. Dependency closure: no new support
component is needed; this reuses lane-local UTF-8 splitting, LIKE/GLOB
matching, wp_options table/index readers, and pure PHP row arrays.

## B-tree Overflow Freelist Release Scenario

Copied Application transient cleanup can now connect deleted table and
`option_name` index overflow chains into the SQLite freelist. The smoke
`examples/application-overflow-freelist-release.php` reports obsolete overflow
pages released as freelist trunk/leaf pages, secure-delete clearing for released
leaf pages, auto-vacuum pointer-map entries rewritten to `free-page`, and the
next allocation order without requiring ext/sqlite.

Status delta 2026-05-27 clean integration: added
`SQLiteOverflowFreelistReleasePlan` over accepted table/index delete results
with focused assertions for source labels, released page ordering, freelist
page images, pointer-map rewrites, post-release database inspection, and
malformed release guards. Focused `SQLiteHeaderTest.php` passed at 7276
assertions, +53 over the current accepted 7223 assertion baseline.

Dependency closure: no new shared support component is needed. This reuses
lane-local table/index overflow delete results, freelist free planning,
secure-delete clearing, and auto-vacuum pointer-map mutation. Follow-up should
apply this release path to broader SQL DELETE/rebalance flows without repeating
accepted bulk overflow freeblocks, page moves, index-interior merge, or this
overflow release path.

Status delta 2026-05-27 isolated B-tree overflow secure-delete current-next20
slice: added `SQLiteOverflowFreelistReleasePlan::fromOverflowChains()` and
`examples/application-overflow-securedelete-current-next20.php`. The smoke starts
from copied `wp_options` table and `option_name` index first overflow pages,
follows the current SQLite next-page pointers, releases the obsolete pages to a
full freelist, secure-delete clears released freelist leaves, and auto-vacuum
pointer-map entries become `free-page` without requiring ext/sqlite. Focused
`SQLiteBTreeOverflowSecureDeleteCurrentNext20Test.php` passed with 52 PASS
lines / 52 assertions / 0 failures. Dependency closure: no new support
component is needed; this reuses lane-local overflow next-pointer parsing,
freelist planning, secure-delete clearing, and pointer-map mutation.

## VFS Locked Writer Scenario

Copied Application database imports can now apply file-handle writes only after
an exclusive SQLite VFS process lock is acquired. The smoke
`examples/application-vfs-locked-writer-apply.php` reports shared-reader
blocking, exclusive acquisition after reader drain, file write/truncate/sync
operations, directory sync, lock release, and dependency tags without requiring
the SQLite extension.

Status delta 2026-05-27 clean integration replay: added
`SQLiteVfsLockedFileWriter`, a bounded adapter that composes accepted
`SQLiteVfsFileLock` and `SQLiteVfsFileWriter` behavior for copied
`wp_options` import writes. The selected focused test passed with 57 assertions
and 0 failures. Lane `phpPass` moves from 777 to 778 and mapped coverage moves
from 433 to 434. Dependency closure: no new shared support component is needed;
this remains lane-local VFS/pager behavior.

## VFS Process File-Lock Scenario

Copied Application database open handles can now keep process-backed VFS lock
handles alive in native PHP without requiring the SQLite extension. The smoke
`examples/application-vfs-file-lock-apply.php` reports shared reader locks,
reserved writer locks, competing writer blockers, pending-reader blockers,
exclusive upgrades after readers drain, open-plan conflict propagation, and
the lane-local dependency tag for process file locks.

Status delta 2026-05-27 isolated dependency/open slice: added
`SQLiteVfsFileLock`, a bounded file-lock adapter that consumes accepted
`SQLiteLockByteRangePlan` output and applies shared/reserved/pending/exclusive
lock transitions to sidecar lock files while preserving SQLite conflict
semantics in PHP. Focused assertions cover shared reader concurrency, writer
exclusion, pending-reader blocking, exclusive upgrades, per-connection and
whole-path release, lock sidecar paths, nolock/open-plan blockers, malformed
input guards, and copied Application smoke output. The focused lane test count
moves from the current lane-status baseline of 6793 assertions to 6875
assertions, +82. Lane `phpPass` moves from 775 to 776 and mapped coverage
moves from 431 to 432. Dependency closure: no new root support component is
needed; this is a lane-local VFS/open primitive reusing accepted file URI,
open-plan, and lock byte-range planning.

## JSON Table SELECT SQL Source Scenario

Copied Application option diagnostics can now run bounded SQLite SELECT text
whose `FROM` or `JOIN` source is `json_tree()` / `json_each()` without
requiring the SQLite extension. The smoke
`examples/application-select-sql-json-table.php` reports copied plugin settings
queried through parser-level JSON table sources, including predicate filters,
ordering, joins back to `wp_options`, and left-join NULL extension.

Status delta 2026-05-27 isolated JSON table/window slice: updated
`SQLiteSelectSql` so table references recognize literal `json_each()` and
`json_tree()` sources with optional aliases, translate the JSON/root arguments
to the existing JSON table planner, and expose virtual rows to existing SELECT
WHERE, JOIN, GROUP BY, ORDER BY, LIMIT, and projection dispatch. Focused
assertions cover simple `json_tree` scans, `json_each` array scans, joins to
copied `wp_options`, empty JSON left joins, grouped JSON table aggregates,
plan-shape checks, SQL NULL inputs, and malformed source guards. The focused
lane test count in this bounded replay worktree is 6525 assertions, +68 over the current 6457-assertion baseline. Lane `phpPass` moves from 770 to 771 and mapped coverage moves from 426 to 427. Dependency closure: no new support
component is needed; this reuses lane-local `SQLiteSelectSql`,
`SQLiteJsonTablePlan`, JSON path/JSON5/JSONB parsing, SELECT predicate,
projection, join, grouped aggregate, and result-ordering helpers.

## SELECT SQL Text Grouped Aggregate Scenario

Copied Application option import previews can now run bounded `GROUP BY` and
`HAVING` aggregate queries from SQLite SELECT text through `SQLiteSelectSql`
without requiring the SQLite extension. The smoke
`examples/application-select-sql-grouped-preview.php` reports copied
`wp_options` rows grouped by `autoload`, filtered by aggregate HAVING terms,
projected through count/sum/avg/group_concat summary columns, and ordered with
LIMIT.

Status delta 2026-05-27 isolated SQL execution/planner slice: updated
`SQLiteSelectSql` so parser-level SELECT text recognizes `GROUP BY` and
`HAVING`, rewrites bounded aggregate functions to the existing grouped summary
columns, and composes the result with joins, projection aliases, final
ORDER BY, LIMIT, and OFFSET. Focused assertions cover single and composite
group keys, joined-source grouping, HAVING aggregate rewrites, count/sum/avg/
min/max/group_concat projection, plan-shape checks, NULL buckets, LIMIT/OFFSET,
and strict malformed SQL guards. The focused lane test count moves from the
current lane-status baseline of 6055 assertions to 6106 assertions, +51.
Dependency closure: no new support component is needed; this reuses
lane-local `SQLiteSelectSql`, grouped aggregate summaries, SELECT predicate,
projection, result ordering, join composition, scalar dispatch, and pure PHP
row arrays.

## SELECT Grouped Aggregate Query Scenario

Copied Application option import previews can now run grouped aggregate summaries
through the bounded `SQLiteSelectQuery` pipeline without requiring the SQLite
extension. The smoke `examples/application-select-grouped-aggregate-preview.php`
reports copied `wp_options` rows grouped by `autoload`, filtered by HAVING,
ordered by aggregate totals, projected through summary/scalar columns, and
finally ordered as SELECT result rows.

Status delta 2026-05-27 isolated SQL execution/planner slice: updated
`SQLiteSelectQuery` so GROUP BY/HAVING aggregate dispatch composes after
FROM/JOIN/WHERE and before SELECT projection, DISTINCT, ORDER BY, LIMIT, and
OFFSET. Focused assertions cover aggregate ORDER BY/LIMIT/OFFSET, projected
summary columns, scalar labels, CASE buckets, DISTINCT, final ORDER BY, NULL
aggregate groups, empty groups, validation guards, and copied Application smoke
output. The focused lane test count moves from the current accepted B-tree interior redistribution baseline
of 5340 assertions to 5420 assertions, +80. Lane `phpPass` moves from 754 to
755 and mapped coverage moves from 417 to 418. Dependency closure: no new
support component is needed; this reuses lane-local grouped aggregate,
predicate, projection, result ordering, scalar dispatch, and pure PHP row-array
helpers.

## SELECT WHERE Scalar Expression Scenario

Copied Application option import previews can now model scalar expression
operands inside residual `WHERE` predicates without requiring the SQLite
extension. The smoke `examples/application-options-where-predicate-preview.php`
now reports copied `wp_options` rows filtered through scalar expressions such
as `upper(autoload)`, `lower(option_name)`, `length(option_name)`, and
`replace(option_name, '_', '-')` before final result ordering.

Status delta 2026-05-27 isolated scalar SQL execution/planner slice: updated
`SQLiteSelectPredicate` so predicate operands accept typed column/literal and
bounded scalar function expression arrays, including nested scalar arguments.
Focused assertions cover `lower()`, `upper()`, `length()`, `coalesce()`,
`trim()`, `substr()`, `instr()`, `replace()`, `hex()`, `quote()`, `printf()`,
comparison, `BETWEEN`, `IN`/`NOT IN`, `LIKE ... ESCAPE`, `GLOB`, `IS`/`IS
NOT`, boolean composition, SQL NULL propagation, malformed expression guards,
and copied Application smoke output. The focused lane test count moves from the
current accepted baseline of 5149 assertions to 5199 assertions, +50. Lane
`phpPass` moves from 750 to 751 and mapped coverage moves from 414 to 415.
Dependency closure: no new support component is needed; this reuses
lane-local scalar dispatch, BLOB wrappers, LIKE/GLOB matchers, result ordering,
and pure PHP row arrays.

## JSON Table Reverse-Root Scenario

Copied Application option settings can now expand the last JSON array item through
`json_tree()` with a reverse array root such as `$.plugin.rules[#-1]` without
losing the selected root row metadata. The smoke
`examples/application-json-table-reverse-root.php` reports the resolved selected
root key, parent, path, rowid, and leaf atom for a copied `wp_options` plugin
settings payload without requiring the SQLite extension.

Status delta 2026-05-27 isolated JSON table/window slice: updated
`SQLiteJsonTree` so selected-root metadata resolves reverse array indexes
against the parent array for text JSON and JSONB inputs. Focused assertions
cover root `key`, `parent`, `path`, `root`, rowid projection, residual
filtering, `json_each` comparison behavior, JSONB parity, and Application smoke
output. The focused lane test count moves from the current lane-status
baseline of 4629 assertions to 4721 assertions, +92. Lane `phpPass` moves from
742 to 743 and mapped coverage moves from 405 to 406. Dependency closure: no
new support component is needed; this reuses lane-local JSON path parsing,
JSONB decoding, JSON table planning, projection, and residual predicate
helpers.

## Hot Rollback-Journal Recovery Scenario

Copied Application database repair/import previews can now apply a hot SQLite
rollback journal in pure PHP without requiring the SQLite extension. The smoke
`examples/application-rollback-journal-option-diagnostics.php` reports recovered
`wp_options` page bytes, clean-vs-dirty option values, lock-preserved dirty
images, and the post-recovery `delete_journal_after_recovery` action.

Status delta 2026-05-27 isolated WAL rollback/savepoint slice: added
`SQLiteRollbackJournal::hotJournalRecoveryResult()` with focused assertions for
hot recovery, initial-size truncation, skipped beyond-size journal pages,
reserved-lock blockers, missing and present super-journal gates, short
non-hot journals, invalid database alignment, and Application copied-row smoke
output. The focused lane test count moves from the current lane-status
baseline of 4343 assertions to 4453 assertions, +110. Lane `phpPass` moves
from 738 to 739 and mapped coverage moves from 402 to 403. Dependency closure:
no new support component is needed; this reuses lane-local rollback-journal
header/page parsing, checksum validation, and recovery-plan helpers.

## SELECT Query Plan Preview Scenario

Copied Application option import previews can now compose the accepted SELECT
execution helpers through one bounded native query plan without requiring the
SQLite extension. The smoke
`examples/application-select-query-plan-preview.php` reports copied
`wp_options` rows joined with metadata, filtered by residual `WHERE`
predicates, projected through table-star, scalar, and CASE expressions, then
deduplicated and ordered with `DISTINCT`, `ORDER BY`, and `LIMIT`.

Status delta 2026-05-26 isolated priority SQL execution/planner slice: added
`SQLiteSelectQuery` with focused assertions for FROM rows, INNER/LEFT/CROSS
and USING joins, WHERE predicate dispatch, projection dispatch, DISTINCT,
ORDER BY, LIMIT/OFFSET, derived LEFT join null-extension columns, validation
guards, and Application copied-row smoke output. The focused lane test count
moves from the accepted current-source baseline of 4201 assertions to 4343
assertions, +142. Lane `phpPass` moves from 737 to 738 and mapped coverage
moves from 401 to 402. Dependency closure: no new support component is needed;
this reuses lane-local SELECT predicate, projection, result, join, scalar, and
pure PHP row-array helpers.

## SELECT WHERE Predicate Preview Scenario

Copied Application option import previews can now model residual `WHERE`
predicate dispatch before final result ordering without requiring the SQLite
extension. The smoke `examples/application-options-where-predicate-preview.php`
reports copied `wp_options` rows filtered through nested `AND`/`OR`/`NOT`,
comparison, `BETWEEN`, `IN`/`NOT IN`, `IS`/`IS NOT`, `IS NULL`,
`LIKE ... ESCAPE`, and `GLOB` predicates, preserving SQLite three-valued
truth handling for `NULL` comparisons and `NOT IN` lists.

Status delta 2026-05-26 isolated priority SQL execution/planner slice: added
`SQLiteSelectPredicate` with focused assertions for residual WHERE filtering,
SQLite truth tables, BLOB identity, storage-class distinctness, missing-column
guards, invalid operator/list/type guards, and final `ORDER BY` integration.
The focused lane test count moves from the accepted current-source baseline of
4175 assertions to 4201 assertions, +26. Lane `phpPass` moves from 734 to 735.
Dependency closure: no new support component is needed; this reuses lane-local
BLOB wrappers, LIKE/GLOB matchers, SQL result ordering, and pure PHP row
arrays.

## Compound SELECT Option Preview Scenario

Copied Application option import previews can now model result rows composed from
multiple SELECT arms without requiring the SQLite extension. The new smoke
`examples/application-options-compound-select-preview.php` reports
`UNION`, `UNION ALL`, `INTERSECT`, and `EXCEPT` over copied `wp_options`-style
rows, including duplicate removal, duplicate preservation, SQL `NULL`
intersection, BLOB payload identity, and final ordering through the accepted
result helper.

Status delta 2026-05-26 isolated priority SQL execution/planner slice: added
`SQLiteSelectCompound` with focused assertions for compound operators, storage
class keys, column-shape validation, invalid operators, and final
`ORDER BY`/`LIMIT`/`OFFSET` integration. The focused lane test count moves from
3871 to 3945 assertions, +74. Manifest mapped coverage moves from 393 to 394.
Dependency closure: no new support component is needed; this reuses lane-local
BLOB wrappers, SQL result ordering, and pure PHP row arrays.

## B-tree Rebalance Summary Scenario

Copied Application repair/import tooling can now read a compact native summary of
delete-triggered replacement rebalancing before writing a changed `wp_options`
database image. The index leaf-merge and parent-collapse smokes report
rebalance summaries for composite `autoload, option_name` index repairs:
action type sets, updated pages, freed pages, merged pages, divider removals,
rightmost pointer rewrites, and aggregate free-space deltas.

Status delta 2026-05-26 isolated B-tree delete/rebalance slice: added
`SQLiteOptionRowReplacementPlan::btreeRebalanceSummary()`, surfaced
rebalance summaries/actions in the Application index merge and parent-collapse
examples, and added focused assertions for underfilled leaf merge and non-root
parent-collapse diagnostics. The focused lane test count moves from 3828 to
3871 assertions, +43. Manifest mapped coverage moves from 391 to 392.
Dependency closure: no new support component is needed; this reuses lane-local
B-tree page headers, index-cell parsing, freelist planning, and existing
replacement-plan rebalance actions.

## SELECT Projection Scalar Preview Scenario

Copied Application option import previews can now model SELECT projection
columns that are scalar expressions, not just raw decoded columns. The smoke
`examples/application-select-projection-scalar-preview.php` reports copied
`wp_options`-style rows projected through `lower()`, `coalesce()`, `printf()`,
and nested `LIKE`/`iif()` expressions, then ordered through the existing
result helper without requiring the SQLite extension.

Status delta 2026-05-26 isolated SQL execution/planner slice: added
`SQLiteSelectProjection` with focused source-column, literal, alias, nested
function argument, scalar dispatch, missing-column, invalid-alias,
invalid-argument-list, unsupported-function, and invalid-expression assertions.
The focused lane test count moves from 3787 to 3828 assertions, +41.
Dependency closure: no new support component is needed; this reuses lane-local
core scalar dispatch and pure PHP result-row arrays.

## WAL Checkpoint Dry-run Scenario

Copied Application SQLite databases can now preview checkpoint mode effects
without writing files or loading the SQLite extension. The WAL frame smoke
`examples/application-wal-option-frame-diagnostics.php` reports bounded
checkpoint results for PASSIVE, FULL, RESTART, and TRUNCATE: reader-limited
PASSIVE/FULL images preserve older base pages, committed RESTART/TRUNCATE
images include WAL option writes, and the result names the preserve, restart,
or truncate WAL action.

Status delta 2026-05-26 isolated WAL/rollback/savepoint slice: added
`SQLiteWal::checkpointModeResult()` with 49 focused assertions for
reader-limited checkpoint images, busy FULL/RESTART cases, committed
RESTART/TRUNCATE reset actions, empty WAL behavior, invalid mode/read-frame
guards, and aligned database-image validation. The focused lane test count
moved from 3491 to 3540 assertions. Dependency closure: no new support
component is needed; this reuses lane-local WAL frame parsing, checkpoint
plans, and pure PHP database image assembly.

## SELECT Result Preview Scenario

Copied Application option import previews can now model the final SELECT result
row phase without requiring the SQLite extension. The smoke
`examples/application-options-order-limit.php` still exercises decoded
`wp_options` `ORDER BY option_name LIMIT/OFFSET`, and now also reports a
bounded result-set preview for `DISTINCT`, multi-term `ORDER BY`, `LIMIT`, and
`OFFSET` over copied option rows.

Status delta 2026-05-26 isolated SQL execution/planner slice: added
`SQLiteSelectResult` with focused DISTINCT, SQL sort-class ordering, stable
tie ordering, BLOB/NULL/numeric/text comparison, negative LIMIT, empty-page
OFFSET, and strict missing-column/type assertions. The focused lane test count
moves from 3410 to 3440 assertions. Dependency closure: no new support
component is needed; this reuses lane-local SQL value ordering and pure PHP
result arrays.

## Builtin Window Option Ranking Scenario

Copied Application option result previews can now model SQLite builtin window
functions without requiring the SQLite extension. The new smoke
`examples/application-window-option-rankings.php` reports `row_number()`,
`rank()`, `dense_rank()`, `percent_rank()`, `cume_dist()`, `ntile()`,
`lag()`, `lead()`, `first_value()`, `last_value()`, and `nth_value()` over a
bounded `wp_options`-style result set ordered by value size.

Status delta 2026-05-26 isolated SQL execution/planner slice: added
`SQLiteWindowFunction` with focused ranking, distribution, offset, value, peer
group, SQL sort-class, empty/single-row, and strict argument assertions. The
focused lane test count moves from 3249 to 3276 assertions. Dependency closure:
no new support component is needed; this reuses lane-local SQL value ordering
semantics and pure PHP result arrays.

## B-tree Full Freelist Trunk Free Scenario

Copied Application SQLite databases can delete or replace large `wp_options`
values whose obsolete overflow pages must be returned to a freelist whose first
trunk is already full. The new smoke
`examples/application-free-pages-full-freelist-trunk.php` previews that repair
case without the SQLite extension: the first freed page becomes a new trunk,
later freed pages become secure-delete-cleared leaves, the old trunk remains
linked, and the next allocation order is visible for reuse planning.

Status delta 2026-05-26 isolated B-tree delete/rebalance slice: added focused
tests for full-trunk freePage2 planning and auto-vacuum pointer-map update
summaries, and exposed `updated_pointer_map_page_numbers` from
`SQLiteFreelistFreePlan::toArray()`. Dependency closure: no new support
component is needed; this reuses lane-local freelist trunk/page-free,
secure-delete, and pointer-map planners.

## Date/Time Scalar Timestamp Scenario

Copied Application option and migration SQL often formats import timestamps with
SQLite temporal helpers. The scalar smoke
`examples/application-core-scalar-option-default.php` now reports
`timestampPreview`, showing bounded UTC `datetime()`, `unixepoch()`, and
`strftime()` dispatch for copied `wp_options` diagnostics without requiring the
SQLite extension.

Status delta 2026-05-26 isolated sql-exec/planner slice: extended bounded
`SQLiteCoreScalarFunction` date/time modifier dispatch with `start of month`,
`start of year`, signed month/year modifiers, and `weekday N` forward
scheduling. The Application scalar smoke now reports month-bucket and weekly-cron
timestamp previews for copied `wp_options` diagnostics. Dependency closure: no
new support component is needed; this reuses lane-local scalar coercion plus
PHP `DateTimeImmutable` UTC handling.

Status delta 2026-05-26 isolated dependency-suite slice: added bounded
`SQLiteCoreScalarFunction` dispatch for `date()`, `time()`, `datetime()`,
`julianday()`, `unixepoch()`, and `strftime()`, focused tests for explicit ISO
inputs, unixepoch conversion, Julian day output, simple signed modifiers,
start-of-day handling, NULL propagation, and unsupported modifier/value errors,
and updated the Application scalar diagnostic smoke. Dependency closure: no new
support component is needed; this reuses lane-local scalar coercion plus PHP
`DateTimeImmutable` for UTC timestamp handling.

## Conditional Scalar Default Scenario

Copied Application option migration SQL can use SQLite's compact conditional
functions to select site, network, or fallback defaults inline. The scalar
smoke `examples/application-core-scalar-option-default.php` now reports
`conditionalDefaultPreview`, showing bounded `iif()`/`if()` dispatch with
SQLite numeric truthiness, pair scanning, optional fallback results, and SQL
NULL false conditions.

Status delta 2026-05-26 isolated sql-exec/planner slice: added
`SQLiteCoreScalarFunction` dispatch for `iif()` and `if()`, focused tests for
true, false, NULL, numeric-text, variadic-pair, fallback, BLOB-result, and
strict arity/type behavior, and updated the Application scalar diagnostic smoke.
This helper evaluates already-supplied arguments and does not claim full VDBE
short-circuit expression evaluation. Dependency closure: no new support
component is needed; this reuses lane-local scalar expression dispatch and
numeric coercion helpers.

## Planner Hint Scalar Predicate Scenario

Application migrations and plugin-maintained SQL can include SQLite planner hint
functions around option predicates. The scalar smoke
`examples/application-core-scalar-option-default.php` now reports
`plannerHintPreview`, showing that `likely()`, `unlikely()`, and
`likelihood()` preserve the wrapped runtime value while validating the
likelihood probability argument.

Status delta 2026-05-26 isolated sql-exec/planner slice: added
`SQLiteCoreScalarFunction` dispatch for `likely()`, `unlikely()`, and
`likelihood()`, focused pass-through and probability validation tests, and
updated the Application scalar diagnostic smoke. Dependency closure: no new
support component is needed; this reuses lane-local scalar expression dispatch
and numeric coercion helpers.

## B-tree Leaf Defragmentation Diagnostics Scenario

Native B-tree deletion diagnostics can now compact table-leaf and index-leaf
pages after obsolete option rows or index entries are removed. The
`examples/application-page-freeblocks.php` smoke reports a `defragmentation`
preview for leaf pages, showing the post-compaction cell-content start,
freeblock head, fragmented-byte count, and preserved free-space accounting
without requiring the SQLite extension.

Status delta 2026-05-26 isolated planner/WAL/B-tree closure slice: added a
lane-local `SQLiteBTreeLeafPageCompactor`, public `defragment()` helpers on
table and index leaf pages, focused tests for delete-then-compact table rows
and option-name index records, and updated the Application page-freeblock
diagnostic smoke. Dependency closure: no new support component is needed; this
reuses lane-local B-tree headers, cell parsers, freeblock accounting, and
record decoding.

Status delta 2026-05-26 isolated B-tree delete/rebalance slice: parsed index
cells that spill to overflow pages now preserve local payload length and
first-overflow-page metadata, and writable index replacement entries carry that
metadata forward for later obsolete-chain release planning. The index-leaf
delete smoke reports the overflow-chain prerequisite for large
`wp_options(option_name, ...)` index records. Dependency closure: no new support
component is needed; this reuses lane-local index cells, overflow-chain
readers, freelist planning, and Application index replacement diagnostics.

Status delta 2026-05-26 isolated B-tree delete/rebalance slice: table and
index leaf pages can now insert replacement cells into reusable in-page
freeblocks left by deletes. The table/index helpers preserve sorted pointer
order, split reusable freeblocks when enough space remains, convert tiny
remainders into fragmented free bytes, and reject duplicate rowid/record
inserts. The Application table-leaf freeblock smoke now deletes obsolete
transient rows and reinserts a refreshed transient into the coalesced freeblock.
Dependency closure: no new support component is needed; this reuses lane-local
B-tree headers, leaf cell encoders, record encoding, and freeblock diagnostics.

## REGEXP-Style Option Name Pattern Scenario

Native Application option diagnostics can now evaluate SQLite-style
application-defined `REGEXP` matching over decoded `option_name` values. The
existing LIKE/GLOB smoke reports `regexpOptions` beside `likeOptions` and
`globOptions`, so import and repair tooling can model site-specific option-name
filters without loading the SQLite extension or truncating at the first 100
rows.

Status delta 2026-05-26 isolated encoding/collation slice: added
`SQLiteDatabase::regexpMatches()`, `optionRowsByNameRegexp()`, focused
callback validation and late-row tests, and updated
`examples/application-option-name-like-glob.php` to report REGEXP-style rows.
Dependency closure: no new support component is needed; REGEXP remains an
application-defined callback and reuses lane-local decoded row traversal.

## Indexed LIKE Prefix Option-Name Range Scenario

Case-sensitive Application option-name scans can now use a leading literal LIKE
prefix as an `option_name` index range before applying the existing LIKE matcher
as a residual predicate. The pattern smoke reports `likePrefixRange` and
`indexedLikeOptions`, showing escaped `_` and `%` literals, binary upper-bound
derivation, and late indexed rows without requiring the SQLite extension.

Status delta 2026-05-26 isolated encoding/collation slice: added
`SQLiteDatabase::likePrefixRangeBounds()` and
`optionRowsByIndexedNameLikePrefixRange()`, focused assertions for
escaped literal prefixes, no-prefix rejection, limits, and residual filtering,
and updated `examples/application-option-name-like-glob.php` with an indexed
self-test fixture. Dependency closure: no new support component is needed; this
reuses lane-local index range traversal and the accepted UTF-8 LIKE matcher.

## NOCASE Indexed LIKE Uppercase Prefix Scenario

Copied Application option queries can arrive as uppercase LIKE prefixes even when
the stored `option_name` rows are lowercase. The pattern smoke now reports a
NOCASE indexed uppercase-prefix probe, showing that `SITE%`-style scans derive
their range from the ASCII-folded prefix before residual LIKE matching.

Status delta 2026-05-26 isolated encoding/collation slice: fixed
`optionRowsByIndexedNameLikePrefixRangeNoCase()` so its upper bound is
derived after SQLite NOCASE ASCII folding, added a focused `SITE%` assertion,
and updated `examples/application-option-name-like-glob.php` to report
`indexedNoCaseUpperCaseLikeOptions`. Dependency closure: no new support
component is needed; this reuses lane-local NOCASE comparison, LIKE matching,
and index range traversal.

## Indexed GLOB Prefix Option-Name Range Scenario

Case-sensitive Application option-name diagnostics can now use a leading literal
`GLOB` prefix as an `option_name` index range before applying SQLite `GLOB` as
a residual predicate. The pattern smoke reports `globPrefixRange` and
`indexedGlobOptions`, covering bracket-class suffixes, `?` UTF-8 character
matches, wildcard residual filtering, zero limits, and no-prefix rejection.

Status delta 2026-05-26 isolated encoding/collation slice: added
`SQLiteDatabase::globPrefixRangeBounds()` and
`optionRowsByIndexedNameGlobPrefixRange()`, plus 13 focused assertions in
`SQLiteHeaderTest.php` and an updated Application pattern smoke.

Dependency closure: no new support component is needed; this reuses
lane-local binary index range traversal and the accepted SQLite GLOB matcher.

## UTF-16 Embedded NUL Option Text Scenario

Copied SQLite text fields can legally contain embedded U+0000 codepoints
because record payloads are length-delimited. The UTF-16 Application smoke now
reports `embeddedNulTextRoundTrip` while still reporting malformed UTF-16
rejection, so import and repair tooling can distinguish valid NUL-containing
option text from corrupt text instead of silently truncating at the first NUL.

Status delta 2026-05-26 isolated encoding/collation slice: added focused
UTF-16LE/UTF-16BE embedded-NUL record round-trip assertions, LIKE/GLOB
single-character pattern assertions over embedded NUL, and updated
`examples/application-utf16-option-insert-plan.php` to report the round-trip
smoke field. Dependency closure: no new support component is needed; the slice
reuses lane-local record encoding/decoding, UTF-16 fallback validation, decoded
text pattern splitting, and ASCII pattern helpers.

## UTF-16 Option Insert Native Conversion Scenario

Copied UTF-16 Application SQLite databases can now be inspected and written
without requiring the host to load mbstring. `SQLiteRecord` still uses
mbstring when available, but falls back to native PHP UTF-16LE/UTF-16BE
conversion and surrogate validation for record text. The existing
`examples/application-utf16-option-insert-plan.php` smoke now reports the native
fallback dependency status while planning a bounded `wp_options` insert.

Status delta 2026-05-26 isolated dependency-suite slice: added lane-local
UTF-16 conversion fallback helpers, focused surrogate-pair fallback assertions,
and updated the UTF-16 Application smoke. Dependency closure: no new shared
support component is needed; UTF-16 conversion no longer has a hard mbstring
activation gate.

## JSON Table Residual Filter Planner Scenario

Native JSON table planning now has a bounded residual-filter execution helper
for visible `json_each()`/`json_tree()` columns after hidden `json` and `root`
constraints are planned. `SQLiteJsonTablePlan::filteredRows()` applies
SQLite-style residual equality, inequality, `IS`, `IS NOT`, `LIKE`, and `GLOB`
checks to the native row stream, so copied `wp_options.option_value` plugin
settings can request rows such as `type = 'object'`,
`key = 'enabled' AND atom IS 1`, or pattern-matched `fullkey`/`value` rows
without requiring the SQLite extension.

Status delta 2026-05-26 isolated json-table/window: added residual `LIKE` and
`GLOB` execution for visible text columns, focused tests for pattern matches,
SQL NULL non-matches, non-text operand rejection, and unsupported operator
rejection, and updated `examples/application-json-each-option-settings.php` to
report cache-rule pattern rows plus the residual planner record. Dependency
closure: no new support component is needed; the slice reuses lane-local JSON
table rows, hidden-column planning, and existing SQLite LIKE/GLOB matchers.

Status delta 2026-05-26 isolated sql-exec/planner: added filtered JSON table
rows, focused residual predicate tests, and updated
`examples/application-json-each-option-settings.php` to report filtered object
rule rows plus the residual planner record. Dependency closure: no new support
component is needed; the slice reuses lane-local JSON table rows, hidden-column
planning, and scalar comparison semantics.

## LIKE/GLOB Late-Row Option Scan Scenario

Native Application option diagnostics now preserve decoded LIKE/GLOB pattern
matches that appear after the first 100 `wp_options` rows. The example
`examples/application-option-name-like-glob.php --self-test` includes a late
`_transient_late` option at rowid 105 and reports it for escaped
`LIKE '\_transient\_%'`, so import and repair tooling can scan copied option
tables without being truncated by the convenience `optionRows()` default
limit.

Status delta 2026-05-26 isolated sql-exec/planner: updated
`optionRowsByNameLike()` and `optionRowsByNameGlob()` to traverse
`wp_options` rows directly while preserving explicit caller limits, extended
focused tests for late LIKE/GLOB matches, and updated the Application smoke.
Dependency closure: no new support component is needed; the slice reuses
lane-local table traversal, decoded Application option rows, UTF-8 pattern
splitting, and ASCII case folding.

## UTF-16 Option Insert Malformed Text Guard Scenario

Native UTF-16 record decoding now rejects malformed copied database text before
Application option rows are inspected or mutated. The example
`examples/application-utf16-option-insert-plan.php` reports
`malformedUtf16Rejected` while planning a UTF-16LE `wp_options` insert, so
import and repair tooling can fail fast on odd-length or invalid-surrogate
record text instead of silently normalizing corrupted names or values.

Status delta 2026-05-26 isolated encoding/collation: updated
`SQLiteRecord::parse()` to validate UTF-16LE and UTF-16BE text fields before
conversion, added focused malformed-record tests, and extended the UTF-16
Application smoke. Dependency closure: no new support component is needed; the
slice reuses PHP mbstring already required by lane-local UTF-16 record
encoding/decoding.

## UTF-8 to UTF-16 Option Insert Guard Scenario

Native UTF-16 record encoding now rejects malformed UTF-8 option text before
mbstring conversion can normalize replacement characters into copied Application
rows. The example `examples/application-utf16-option-insert-plan.php` reports
`malformedUtf8RejectedBeforeUtf16Encoding` while planning a UTF-16LE
`wp_options` insert, so import and repair tooling can distinguish valid
length-delimited UTF-8 bytes in UTF-8 databases from corrupt text that cannot
be encoded into UTF-16 database pages.

Status delta 2026-05-27 isolated encoding/collation slice: added
`SQLiteRecord::assertValidUtf8Text()` before UTF-16LE/UTF-16BE record
encoding, a focused malformed UTF-8 matrix covering bad continuations,
truncated sequences, overlong forms, surrogate codepoints, out-of-range
codepoints, and copied Application option-name/value positions, plus the
updated UTF-16 Application smoke. Focused `SQLiteHeaderTest.php` moved from the
current lane baseline of 5993 assertions to 6079 assertions, +86. Lane
`phpPass` moves from 763 to 764 and mapped coverage moves from 425 to 426.
Dependency closure: no new support component is needed; this reuses
lane-local record encoding/decoding and PHP's UTF-8 validation path.

## Rollback Journal Sector-Padded Option Recovery Scenario

Native rollback-journal diagnostics now accept copied rollback journals that
carry zero-filled sector padding after their declared page records. The example
`examples/application-rollback-journal-option-diagnostics.php` reports
`sectorPaddingBytes` while restoring the clean `siteurl` option page over a
dirty copied database image, so Application import and repair tooling can inspect
pre-transaction option values without requiring the SQLite extension.

Status delta 2026-05-26 isolated dependency closure: updated
`SQLiteRollbackJournal::parse()` to honor declared page counts, accept NUL
trailing padding, reject non-zero trailing bytes, and preserve unknown-count
EOF parsing. Dependency closure: no new support component is needed; the slice
reuses existing lane-local rollback journal header/page parsing, checksum
validation, rollback image overlay, and Application option decoding.

## WAL Committed Transaction Option Diagnostics Scenario

Native WAL diagnostics now expose committed transaction batches for copied
Application database WAL files. The example
`examples/application-wal-option-frame-diagnostics.php` reports
`committedTransactions` and `uncommittedFrameCount` beside the accepted
checkpoint overlay result so import and repair tooling can distinguish
committed wp_options writes from uncommitted tail frames without requiring the
SQLite extension.

Status delta 2026-05-26 isolated refill: added
`SQLiteWal::committedTransactions()` and `uncommittedFrameCount()` with
focused tests for multi-transaction WAL files, page-number summaries,
replacement page images, zero-frame WAL files, and uncommitted tails.
Dependency closure: no new support component is needed; the slice reuses
existing lane-local WAL frame parsing, checksum validation, checkpoint overlay,
and Application option decoding.

Status delta 2026-05-27 isolated WAL rollback/savepoint slice: added
`SQLiteWal::durableCheckpointResult()` and WAL byte reserialization so copied
Application database repair tooling can preview the concrete database image plus
sidecar bytes that a checkpoint writer would persist. The smoke now reports
preserved WAL bytes while readers or uncommitted tails block reset, restarted
WAL headers with advanced checkpoint sequence/salt and regenerated checksums,
and empty WAL bytes for complete TRUNCATE checkpoints. Dependency closure: no
new support component is needed; this reuses lane-local WAL parsing, checkpoint
mode planning, checksum calculation, and the existing Application WAL diagnostic
smoke.

## JSON Tree Selected-Root Option Review Scenario

Native recursive JSON option expansion now mirrors SQLite's
`json_tree(X, root)` selected-root row shape for copied `wp_options`
subtrees. The example `examples/application-json-tree-option-settings.php`
reports `selectedRootShape` for strict JSON, JSON5 text, and JSONB blobs so
import tooling can distinguish the selected node key, full path, parent path,
and hidden root argument before plugin settings are migrated without requiring
the SQLite extension.

Status delta 2026-05-26 isolated refill: updated `SQLiteJsonTree` to derive
the selected root row key and parent path from the supplied root path while
preserving hidden `json` and `root` columns, added focused tests for object,
array, JSONB, quoted-label, and scalar selected roots, and updated the
Application smoke output. Dependency closure: no new support component is
needed; the slice reuses existing lane-local JSON path lookup, JSON5/JSONB
decoding, table row shaping, and SQL NULL support.

## JSON Aggregate ORDER BY Option Summary Scenario

Native JSON aggregate summaries now include a bounded
`json_group_array(X ORDER BY option_name)` ordering helper for copied option
values. The example `examples/application-json-aggregate-option-summary.php`
now streams copied `wp_options.option_value` rows with `option_name` order
keys, reports ordered text aggregate output, and decodes the ordered JSONB
result for review. This gives Application import tooling a local-only way to
produce deterministic option summaries before migration without requiring the
SQLite extension.

Status delta 2026-05-26 isolated refill: added
`SQLiteJsonAggregate::jsonGroupArrayOrderBy()`,
`SQLiteJsonAggregateState::stepArrayOrderBy()`, and
`SQLiteJsonAggregateState::finalizeOrderedArray()` with focused tests for
NULL-low ascending ordering, stable equal-key ties, text and numeric order
keys, SQL NULL values, JSON subtype fragments, JSONB BLOB values, empty
aggregate finalization, invalid function names, malformed raw BLOB rejection,
and JSONB output decoding. Dependency closure: no new support component is
needed; the slice reuses existing lane-local JSON aggregate, constructor
value coercion, JSON subtype, JSONB, BLOB, and SQL NULL support.

## JSON Aggregate Distinct Option Summary Scenario

Native JSON aggregate summaries now include a bounded
`json_group_array(DISTINCT X)` row de-duplication helper for copied option
values. The example `examples/application-json-aggregate-option-summary.php`
now includes duplicated string and JSONB option values, reports direct and
step/final distinct JSON arrays, and decodes the JSONB distinct result for
review. This gives Application import tooling a local-only way to spot unique
settings payloads before migration without requiring the SQLite extension.

Status delta 2026-05-26 isolated refill: added
`SQLiteJsonAggregate::jsonGroupArrayDistinct()`,
`SQLiteJsonAggregateState::stepArrayDistinct()`, and
`SQLiteJsonAggregateState::finalizeDistinctArray()` with focused tests for
first-seen ordering, SQL NULL collapse, boolean/integer equality, JSON subtype
fragments, JSONB BLOB values, empty aggregate finalization, invalid function
names, and malformed raw BLOB rejection. Dependency closure: no new support
component is needed; the slice reuses existing lane-local JSON aggregate,
constructor value coercion, JSON subtype, JSONB, BLOB, and SQL NULL support.

## JSON Aggregate Step/Final Option Summary Scenario

Native JSON aggregate summaries now include a bounded step/final state helper
for ordered `json_group_array()` and `json_group_object()` rows. The example
`examples/application-json-aggregate-option-summary.php` now streams copied
`wp_options.option_value` rows into `SQLiteJsonAggregateState`, finalizes text
and JSONB aggregate results through uppercase SQL function names, and reports
the step counts beside the accepted direct aggregate output. This gives
Application import and repair tooling a local-only path that mirrors SQLite's
aggregate lifecycle without requiring the SQLite extension.

Status delta 2026-05-25 isolated refill: added
`SQLiteJsonAggregateState`, focused step/final tests for text and JSONB array
and object results, invalid-name propagation, empty aggregate finalization,
and updated the existing Application smoke to stream copied options through the
new state object. Dependency closure: no new support component is needed; the
slice reuses existing lane-local JSON aggregate dispatch, JSON subtype, JSONB,
BLOB, constructor value coercion, and SQL NULL support.

## `json_remove()`/`jsonb_remove()` Argument-Vector Cleanup Scenario

Native JSON removal now includes bounded SQL-style argument-vector dispatch for
`json_remove()` and `jsonb_remove()` with case-insensitive function lookup.
The example `examples/application-json-remove-sql-dispatch-preflight.php`
exercises copied `wp_options.option_value` inputs through uppercase
argument-vector dispatch for text and JSONB result typing, multiple path
removals, SQL NULL input, and root removal. This gives Application import and
repair tooling a local-only cleanup preflight that mirrors SQLite's SQL entry
point without requiring the SQLite extension.

Status delta 2026-05-25 isolated refill: added
`removeSqlFunctionArguments()`, switched direct remove function-name
validation to case-insensitive lookup, added focused arity, JSON argument type,
path type, and invalid-name rejection tests, and updated the existing
Application smoke to report uppercase argument-vector dispatch. Dependency
closure: no new support component is needed; the slice reuses existing
lane-local JSON path, JSON5, JSONB, canonical JSON, BLOB, and SQL NULL support.

## `json_insert()`/`json_set()`/`json_replace()` Option Mutation Dispatch Scenario

Native JSON option mutation now includes bounded SQL-style argument-vector
dispatch for `json_insert()`, `jsonb_insert()`, `json_set()`, `jsonb_set()`,
`json_replace()`, and `jsonb_replace()` with case-insensitive function lookup.
The example `examples/application-jsonb-mutate-option-field.php` now exercises
copied `wp_options.option_value` JSON through uppercase argument-vector
dispatch while preserving text versus JSONB result typing, JSON subtype
fragments, JSONB replacement values, and SQL NULL propagation. This gives
Application import and repair tooling a local-only mutation preflight that
mirrors SQLite's SQL entry points without requiring the SQLite extension.

Status delta 2026-05-25 isolated refill: added
`mutateSqlFunctionArguments()`, switched direct mutation function-name
validation to case-insensitive lookup, added focused arity/input/path invalid
argument tests, and updated the existing Application smoke to report uppercase
argument-vector dispatch. Dependency closure: no new support component is
needed; the slice reuses existing lane-local JSON path mutation, JSON subtype,
JSONB, BLOB, and SQL NULL support.

## JSON Aggregate Option Summary Dispatch Scenario

Native JSON aggregate summaries now include bounded SQL-style argument-vector
dispatch for `json_group_array()`, `jsonb_group_array()`,
`json_group_object()`, and `jsonb_group_object()` with case-insensitive
function lookup. The example
`examples/application-json-aggregate-option-summary.php` exercises copied
`wp_options.option_value` rows through uppercase argument-vector dispatch for
text and JSONB aggregate result typing, JSON subtype fragments, JSONB blobs,
booleans, and SQL NULL option values. This gives Application import and repair
tooling a local-only option summary path that mirrors SQLite's SQL entry
points without requiring the SQLite extension.

Status delta 2026-05-25 isolated refill: added
`jsonGroupArraySqlFunctionArguments()` and
`jsonGroupObjectSqlFunctionArguments()`, switched direct aggregate
function-name validation to case-insensitive lookup, added focused invalid
name and malformed object row rejection tests, and updated the existing
Application smoke to report uppercase argument-vector dispatch. Dependency
closure: no new support component is needed; the slice reuses existing
lane-local JSON constructor value coercion, JSON subtype, JSONB, BLOB, and
SQL NULL support.

## `json_patch()`/`jsonb_patch()` Option-Value Merge Dispatch Scenario

Native JSON merge-patch now includes bounded SQL-style argument-vector
dispatch for `json_patch()` and `jsonb_patch()` with case-insensitive function
lookup. The example
`examples/application-json-patch-sql-dispatch-preflight.php` exercises copied
`wp_options.option_value` inputs through uppercase argument-vector SQL
dispatch for JSON text, SQLite JSON5 patch text, copied JSONB blobs, cast text
BLOB handling, JSONB result typing, and SQL NULL propagation. This gives
Application import and repair tooling a local-only merge-patch preflight that
mirrors SQLite's SQL entry point without requiring the SQLite extension.

Status delta 2026-05-25 isolated refill: added
`patchSqlFunctionArguments()`, switched direct patch function-name validation
to case-insensitive lookup, added focused arity and invalid-name rejection
tests, and updated the existing Application smoke to report uppercase
argument-vector dispatch. Dependency closure: no new support component is
needed; the slice reuses existing lane-local JSON canonicalization, JSON5,
JSONB patch, BLOB, and SQL NULL support.

## `json_quote()` Option-Value SQL Dispatch Scenario

Native JSON SQL-value quoting now includes bounded SQL-style argument-vector
dispatch for `json_quote()` with case-insensitive function lookup. The example
`examples/application-json-quote-option-preflight.php` exercises copied
`wp_options.option_value` inputs through uppercase argument-vector SQL
dispatch for SQL NULL, integers, REAL values, copied text settings,
control-character text, JSONB blobs, and raw BLOB rejection. This gives
Application import and repair tooling a local-only preflight that mirrors
SQLite's SQL entry point without requiring the SQLite extension.

Status delta 2026-05-25 isolated refill: added
`jsonQuoteSqlFunctionArguments()`, switched direct quote function-name
validation to case-insensitive lookup, added focused arity and invalid-name
rejection tests, and updated the existing Application smoke to report uppercase
argument-vector dispatch. Dependency closure: no new support component is
needed; the slice reuses existing lane-local JSONB, JSON subtype, BLOB, SQL
scalar, and SQL NULL support.

## `json_type()`/`json_array_length()` Option-Value Inspection Dispatch Scenario

Native JSON inspection now includes bounded SQL-style argument-vector dispatch
for `json_type()` and `json_array_length()` with case-insensitive function
lookup. The example `examples/application-json-inspection-preflight.php`
exercises copied `wp_options.option_value` inputs through direct inspection,
direct SQL dispatch, and uppercase argument-vector SQL dispatch for strict
JSON text, SQLite JSON5 text, cast text BLOBs, JSONB blobs, and SQL NULL
option values. This gives Application import and repair tooling a local-only
preflight that mirrors SQLite's SQL entry points without requiring the SQLite
extension.

Status delta 2026-05-25 isolated refill: added
`inspectionSqlFunctionArguments()`, switched direct inspection function-name
validation to case-insensitive lookup, added focused arity/path-type
rejection tests, and updated the existing Application smoke to report uppercase
argument-vector dispatch. Dependency closure: no new support component is
needed; the slice reuses existing lane-local JSON path, JSON5, JSONB, BLOB,
and SQL NULL support.

## `json_pretty()` SQL-Dispatch Option-Value Review Scenario

Native JSON pretty-printing now includes a bounded SQL function-name dispatch
helper for `json_pretty()` with SQLite-style case-insensitive function lookup.
The example
`examples/application-json-pretty-option-review.php` exercises the dispatch
path for copied `wp_options.option_value` inputs, including strict JSON text,
SQLite JSON5 text, cast text BLOBs, JSONB blobs, SQL NULL option values,
JSON subtype fragments, scalar SQL option values including booleans, fractional floats, and whole REAL
values, malformed settings, and custom text/numeric/boolean indentation. This gives Application migration and
repair tooling a local-only review path that mirrors SQLite's SQL entry point
without requiring the SQLite extension.

Status delta 2026-05-25 isolated rework: added `jsonPrettySqlFunction()`,
kept invalid-name rejection, accepted uppercase SQL spelling through direct
and argument-vector dispatch, added one-or-two argument-vector dispatch
coverage, added subtype input and malformed-input dispatch smoke coverage,
added scalar SQL argument-vector coercion coverage for integer, float, true, and false inputs,
aligned direct SQL-dispatch scalar coercion with the argument-vector path,
and updated the existing Application smoke to call the SQL-dispatch helper
through its argument-vector entry point. This preserves
accepted json_extract/jsonb_extract subtype dispatch and json_each
table-valued row evidence while making the deferred json_pretty patch
additive. Dependency closure: no new support component is needed; the slice
reuses existing lane-local JSON canonicalization, JSON5, JSONB, BLOB, subtype, and
pretty formatter support and counts no shared support-library progress.
Priority-keeper refresh 2026-05-25T09:23Z keeps the same behavior cluster and
adds focused malformed JSON propagation coverage through the argument-vector
SQL-dispatch path, preserving the already accepted manifest/status evidence.
Priority-keeper refresh 2026-05-25T09:58Z adds the missing direct-dispatch
`true` scalar assertion without changing the Application smoke surface.
Priority libsqlite rework 2026-05-26T02:10Z preserves the accepted
json_pretty SQL-dispatch surface and updates the Application smoke to exercise
mixed-case `Json_Pretty` through both direct and argument-vector dispatch,
matching SQLite's case-insensitive SQL function lookup without requiring the
SQLite extension.
Priority-finisher refresh 2026-05-25T10:13Z preserves whole REAL scalar output
such as `3.0` through direct and argument-vector SQL dispatch and adds that
case to the Application smoke surface.
Clean-integrator rebase 2026-05-25T10:17Z also keeps signed integer and
fractional float option-value smoke coverage in the same SQL-dispatch cluster.
Priority-finisher refresh 2026-05-25T10:28Z adds direct SQL-dispatch coverage
for cast text BLOB and JSON subtype custom indentation, and the Application
smoke now reports direct `JSON_PRETTY` output beside argument-vector output.
Priority-keeper refresh 2026-05-25T10:40Z adds boolean true and fractional
REAL custom-indent option rows so local review output covers SQLite SQL scalar
coercion for the second `json_pretty(JSON, INDENT)` argument too.
Priority-keeper rework 2026-05-25T10:50Z adds the missing boolean false
custom-indent option row and direct-dispatch assertion so both SQL-dispatch
entry points cover SQLite's false-to-`0` second-argument coercion.
Priority-rework refill 2026-05-25T11:02Z adds explicit cast text-BLOB JSON
input review through both direct and argument-vector SQL dispatch, including a
custom text indentation row in the Application smoke. This keeps the slice
inside the accepted json_pretty SQL-dispatch cluster and preserves existing
json_extract/jsonb_extract and json_each evidence.
Priority-keeper rework 2026-05-25T11:10Z additively covers JSONB option blobs
with custom indentation through both direct and argument-vector SQL dispatch,
so local review output now exercises the same indentation path for SQLite JSONB
storage as for text JSON and cast text BLOB inputs.
Priority-keeper rework 2026-05-25T11:27Z adds the matching focused assertions
for JSONB option blobs through both SQL-dispatch paths with default
indentation, aligning the native tests with the existing `jsonb_settings`
Application smoke row.
Priority-refill rework 2026-05-25T12:13Z adds the matching SQL NULL
first-argument plus custom-indent second-argument row for copied option values,
so both direct and argument-vector SQL-dispatch paths return NULL for
`json_pretty(NULL, '--')` instead of treating the indent as meaningful output.
Supervisor-rework refill 2026-05-25T12:53Z adds a JSON subtype option-value
smoke row and matching focused assertions for default indentation through both
direct and argument-vector SQL-dispatch paths.
Dependency closure remains unchanged: no new support component is needed.

## `json_each()` Option-Value Expansion Scenario

Native JSON table-valued inspection now includes a bounded `json_each(X[,P])`
row producer for strict JSON text, SQLite JSON5 text, JSONB blobs, missing
paths, scalar paths, and SQL NULL option values. The example
`examples/application-json-each-option-settings.php` expands copied
`wp_options.option_value` plugin settings at the root, `$.plugin`, and
`$.plugin.rules`, reporting SQLite-shaped `key`, `value`, `type`, `atom`,
`id`, `parent`, `fullkey`, and `path` columns without requiring the SQLite
extension. This gives Application import and repair tooling a local-only way to
review setting members and rule arrays before import.

Status delta 2026-05-25 isolated micro-slice: added `SQLiteJsonEach`, focused
tests, and a Application smoke. The slice covers immediate child rows only; full
recursive `json_tree()`, hidden `json`/`root` columns, planner behavior, and
virtual table cursor internals remain out of scope. Focused verification is
recorded in `lane-status.json`. Blocker: no hydrated upstream cache exists in
this isolated worktree, so no fresh SQLite testfixture run was performed; this
slice reuses prior focused JSON1/JSONB runner evidence and maps the
table-valued-function row-shape boundary natively. Dependency closure: no new
support component is needed; the slice reuses existing lane-local JSON path,
JSON5, JSONB, canonical encoding, and BLOB support and counts no shared
support-library progress.

## `json_tree()` Recursive Option-Value Expansion Scenario

Native JSON table-valued inspection now includes a bounded `json_tree(X[,P])`
row producer for strict JSON text, SQLite JSON5 text, JSONB blobs, missing
paths, scalar paths, and SQL NULL option values. The example
`examples/application-json-tree-option-settings.php` recursively expands copied
`wp_options.option_value` plugin settings at the root, `$.plugin`, and
`$.plugin.rules`, reporting SQLite-shaped `key`, `value`, `type`, `atom`,
`id`, `parent`, `fullkey`, and `path` columns without requiring the SQLite
extension. This gives Application import and repair tooling a local-only way to
review nested setting trees and rule arrays before import.

Status delta 2026-05-25 isolated micro-slice: added `SQLiteJsonTree`, focused
tests, and a Application smoke. The slice covers recursive row production and
parent ids; hidden `json`/`root` columns, planner behavior, and virtual table
cursor internals remain out of scope. Focused verification is recorded in
`lane-status.json`. Blocker: no hydrated upstream cache exists in this
isolated worktree, so no fresh SQLite testfixture run was performed; this
slice reuses prior focused JSON1/JSONB runner evidence and maps the recursive
table-valued-function row-shape boundary natively. Dependency closure: no new
support component is needed; the slice reuses existing lane-local JSON path,
JSON5, JSONB, canonical encoding, and BLOB support and counts no shared
support-library progress.

## JSON Operator Parenthesized RHS Index Preflight Scenario

Native JSON operator expression-index preflight now folds parenthesized scalar
RHS constants for copied `wp_options` JSON operator indexes. The example
`examples/application-json-operator-parenthesized-rhs.php` checks indexes such
as `option_value ->> ('cache')`, `option_value ->> (1)`, and
`option_value -> ('settings.v1')`, then proves the normalized paths can resolve
index root pages and option rows without the SQLite extension. Arithmetic and
broader SQL expressions remain unsupported so this does not over-credit full
SQLite expression evaluation.

## `json_extract()`/`jsonb_extract()` Option-Value Preflight Scenario

Native JSON extraction now follows a bounded SQLite `json_extract(X,P...)`
SQL-result typing slice for strict JSON text, SQLite JSON5 text, cast text
BLOBs, JSONB blobs, SQL NULL option values, missing paths, scalar paths,
object/array paths, multi-path JSON array output, and the result-type boundary
where `jsonb_extract()` returns JSONB blobs for object/array or multi-path
results while preserving SQL scalar result typing for scalar paths. The example
`examples/application-json-extract-option-preflight.php` checks local
`wp_options.option_value`-shaped copied plugin settings and reports extracted
enabled flags as SQLite-style `1`/`0`, text titles as SQL text, object paths as
canonical JSON text, missing paths as NULL, multi-path summaries as JSON
arrays, and decoded JSONB summaries with their hex bytes. This gives Application
import and repair tooling a local-only way to read copied plugin settings
without requiring the SQLite extension.

Status delta 2026-05-25 isolated micro-slice: added `json_extract()` and
`jsonb_extract()` SQL function-name dispatch, focused tests, and updated the
Application smoke to call the dispatch helper and report JSONB result blobs.
Focused verification is recorded in `lane-status.json` after local checks.
Blocker: no hydrated upstream cache exists in this isolated worktree, so no
fresh SQLite testfixture run was performed; this slice reuses prior
`json101.test`, `json102.test`, and `jsonb.test` extract evidence. Next task:
integrator acceptance, then one additional bounded libsqlite behavior slice
with its own evidence. Dependency closure: no new support component is needed;
the slice reuses existing lane-local JSON extraction, JSON path, inspection,
JSONB, and BLOB support and counts no shared support-library progress.

## `json_extract()` Subtype Diagnostics Scenario

Native JSON extraction now also exposes a bounded JSON-argument path for
SQLite subtype propagation when object/array or multi-path
`json_extract(X,P...)` results are passed into JSON constructors. The example
`examples/application-json-extract-subtype-option-diagnostics.php` checks local
strict JSON, JSON5 text, and JSONB `wp_options.option_value`-shaped copied
plugin settings, wraps extracted rules and summaries with `json_array()` and
`json_object()`, and verifies that nested JSON values are embedded as JSON
rather than double-quoted text. This gives Application migration and repair
tooling local-only constructor diagnostics before copied plugin settings are
imported, without requiring the SQLite extension.

Status delta 2026-05-25 isolated micro-slice: added
`extractJsonArgumentSqlFunction()` for `json_extract()`/`jsonb_extract()`
function-name dispatch at the JSON-constructor argument boundary, focused
tests, and a Application smoke. `json_extract()` object/array and multi-path
arguments preserve SQLite JSON subtype text; `jsonb_extract()` object/array
and multi-path arguments preserve SQLite JSONB blobs; scalar, missing, and
SQL NULL arguments keep SQL typing. Focused verification is recorded in
`lane-status.json`. Blocker: no hydrated upstream cache exists in this
isolated worktree, so no fresh SQLite testfixture run was performed; this
slice reuses prior `json101.test`, `json102.test`, `subtype1.test`, and
`jsonb01.test` evidence. Next task: integrator acceptance, then one
additional bounded libsqlite behavior slice with its own evidence. Dependency
closure: no new support component is needed; the slice reuses existing
lane-local JSON extraction, JSON path, inspection, JSONB, BLOB, subtype, and
constructor support and counts no shared support-library progress.

## `json_remove()` Option-Value Cleanup Scenario

Native JSON removal now follows a bounded SQLite `json_remove(X,P...)`
text-result slice for strict JSON text, SQLite JSON5 text, cast text BLOBs,
SQLite JSONB blobs, SQL NULL option values, no-path canonicalization, multiple
paths in SQLite argument order, missing-path no-ops, array reverse indexes, and
root `$` removal to SQL NULL. The example
`examples/application-json-remove-option-preflight.php` checks local
`wp_options.option_value`-shaped copied plugin settings and removes obsolete
settings such as `$.plugin.legacyToken` and stale rule entries before import.
This gives Application import and repair tooling a local-only cleanup path
without requiring the SQLite extension.

## `json_remove()`/`jsonb_remove()` Result-Type Dispatch Scenario

Native JSON removal now includes a bounded SQL-dispatch helper for the SQLite
result-type boundary: `json_remove()` returns canonical JSON text, while
`jsonb_remove()` returns SQLite JSONB blob bytes. The example
`examples/application-json-remove-sql-dispatch-preflight.php` checks copied
`wp_options.option_value` plugin settings and can report either decoded JSONB
plus hex bytes or text JSON after obsolete paths are removed. This gives
Application import and repair tooling a local-only way to preserve JSONB fixture
typing during cleanup without requiring the SQLite extension.

## `json_patch()`/`jsonb_patch()` Result-Type Dispatch Scenario

Native JSON merge patching now includes a bounded SQL-dispatch helper for the
SQLite result-type boundary: `json_patch()` returns canonical JSON text, while
`jsonb_patch()` returns SQLite JSONB blob bytes. The example
`examples/application-json-patch-sql-dispatch-preflight.php` checks copied
`wp_options.option_value` plugin settings and applies RFC-7396 merge patches
where object-member `null` values delete keys, nested objects merge, and arrays
replace whole arrays. This gives Application import and repair tooling a
local-only way to preserve JSONB fixture typing while applying plugin setting
patches before import, without requiring the SQLite extension.

## JSON Operator `min()`/`max()` RHS Index Preflight Scenario

Native JSON operator expression-index preflight now folds reduced SQLite
`min()`/`max()` RHS constants over homogeneous literal strings or homogeneous
numeric literals. The example `examples/application-json-operator-minmax-rhs.php`
checks copied `wp_options` JSON operator indexes such as
`option_value ->> min('seo','cache')`,
`option_value ->> max('plugin.enabled','plugin.disabled')`, and
`option_value ->> min(2,1)`, then proves the normalized paths can resolve
index root pages and option rows without the SQLite extension. Mixed-type and
single-argument calls remain unsupported so broader SQLite scalar semantics do
not get over-credited.

## `json_pretty()` Option-Value Review Scenario

Native JSON pretty-printing now follows SQLite's `json_pretty(JSON[,INDENT])`
boundary for strict JSON text, SQLite JSON5 text, cast text BLOBs, JSONB
blobs, SQL NULL option values, malformed JSON, and custom indentation. The
example `examples/application-json-pretty-option-review.php` checks local
`wp_options.option_value`-shaped inputs for copied strict plugin settings,
JSON5 plugin settings with comments and trailing commas, tab-indented review
output, cast text BLOBs, JSONB option blobs, NULL values, and malformed
duplicate-comma settings. For Application migration and repair tooling this
gives a local-only way to generate SQLite-style review output for copied
plugin settings without requiring the SQLite extension or shelling out to
SQLite.

## `json(X)` Option-Value Canonicalization Scenario

Native JSON canonicalization now follows SQLite's `json(X)` boundary for
strict JSON text, SQLite JSON5 text, cast text BLOBs, JSONB blobs, malformed
JSON, and SQL NULL option values. The example
`examples/application-json-canonical-option-preflight.php` checks local
`wp_options.option_value`-shaped inputs for copied strict plugin settings,
JSON5 plugin settings with comments and trailing commas, cast text BLOBs,
JSONB option blobs, NULL values, and malformed duplicate-comma settings. For
Application migration and repair tooling this gives a local-only way to produce
SQLite-style canonical JSON before plugin settings are imported or compared,
without requiring the SQLite extension or shelling out to SQLite.

## JSON Constructor Option Diagnostics Scenario

Native JSON constructor diagnostics now follow SQLite's `json_array()` and
`json_object()` SQL-value boundary for SQL NULL, numeric values, text values,
`TRUE`/`FALSE` integer expressions, JSON subtype passthrough, JSONB BLOB
passthrough, raw BLOB rejection, and `json_object()` label/arity errors. The
example `examples/application-json-constructor-option-diagnostics.php` builds
local `wp_options` import reports and migration queue diagnostics before
copied plugin settings are trusted. For Application migration and repair tooling
this gives a local-only way to construct SQLite-style JSON diagnostics without
requiring the SQLite extension or shelling out to SQLite.

## `json_quote()` Option-Value Preflight Scenario

Native JSON quoting now follows SQLite's `json_quote(X)` SQL-value boundary
for SQL NULL, numeric values, copied TEXT settings, control-character TEXT,
JSONB option blobs, raw BLOB rejection, and superficial-only malformed JSONB
errors. The example `examples/application-json-quote-option-preflight.php`
checks `wp_options.option_value`-shaped values before import and reports the
quoted JSON text or SQLite-style rejection status. For Application migration and
repair tooling this gives a local-only way to render copied scalar option
values into JSON diagnostics, preserve JSONB option blobs as JSON text, and
reject raw BLOBs before plugin settings are trusted without requiring the
SQLite extension.

Status delta 2026-05-25 isolated micro-slice: added `json_quote()` SQL
function-name dispatch, focused tests, and updated the Application smoke to call
the dispatch helper. Focused verification is recorded in `lane-status.json`
after local checks. Blocker: no hydrated upstream cache exists in this
isolated worktree, so no fresh SQLite testfixture run was performed; this
slice reuses prior `json101.test`, `json102.test`, and `subtype1.test`
`json_quote()` evidence. Next task: integrator acceptance, then one additional
bounded libsqlite behavior slice with its own evidence. Dependency closure: no
new support component is needed; the slice reuses existing lane-local JSON
quote, JSON subtype, JSONB, and BLOB support and counts no shared
support-library progress.

## JSON Type And Array-Length Option-Value Inspection Scenario

Native JSON inspection now follows SQLite's `json_type(X[,P])` and
`json_array_length(X[,P])` boundary for strict JSON text, JSON5 text, cast
text BLOBs, JSONB blobs, missing paths, scalar paths, and SQL NULL option
values. The example `examples/application-json-inspection-preflight.php` checks
`wp_options.option_value`-shaped inputs for plugin settings roots, nested
plugin objects, plugin `modes` arrays, missing plugin paths, and NULL values.
For Application migration and repair tooling this gives local-only shape checks
that can distinguish object, array, scalar, missing, JSONB, and JSON5 inputs
before copied plugin settings are imported or trusted.

## `json_error_position()` Option-Value Diagnostics Scenario

Native JSON diagnostics now follow SQLite's `json_error_position(X)` boundary
for text, JSON5, BLOB, JSONB, and SQL NULL option values. The example
`examples/application-json-error-position-preflight.php` checks
`wp_options.option_value`-shaped inputs for JSON5 plugin settings, duplicate
commas, nested malformed copied settings, leading-zero numeric mistakes, cast
text BLOBs, valid JSONB blobs, superficial-only corrupt JSONB blobs, and NULL
values. For Application migration and repair tooling this gives local-only
offsets that can be shown in diagnostics or used to route copied plugin
settings to strict import, JSON5 normalization, JSONB repair, or rejection
before the SQLite extension is available.

## `json_valid()` Option-Value Preflight Scenario

Native JSON validity preflight now follows SQLite's `json_valid(X, FLAGS)`
dispatcher across strict JSON text, SQLite JSON5 text, BLOB fallback, JSONB,
and SQL NULL option values. The example
`examples/application-json-validity-preflight.php` checks local
`wp_options.option_value`-shaped inputs for strict plugin settings JSON, JSON5
plugin settings, malformed copied text, cast text BLOBs, valid JSONB blobs,
superficial-only corrupt JSONB blobs, and NULL values. For Application migration
and repair tooling this gives a local-only way to decide whether copied plugin
settings should be accepted as strict JSON, accepted only under SQLite JSON5
rules, treated as a text BLOB fallback, routed through JSONB strict validation,
or rejected before import.

## JSONB Validity Preflight Scenario

Native JSONB preflight now distinguishes SQLite's fast `json_valid(X,4)`
superficial BLOB check from strict recursive JSONB validation. The example
`examples/application-jsonb-validity-preflight.php` checks four local
`wp_options.option_value`-shaped inputs: a valid plugin settings JSONB blob, a
large corrupt BLOB that passes SQLite's superficial flag-4 header check but
fails strict validation, a cast text JSON BLOB that is rejected at SQLite's
ambiguous small-BLOB boundary, and a scalar null header with a non-zero
payload. For Application migration and repair tooling this lets a local-only
import preflight cheaply triage copied JSONB option blobs and route
superficial-only settings to strict decode or repair before plugin settings
are trusted.

## JSON Path Validation Preflight Scenario

Native expression-index preflight now validates full SQLite JSON paths before
trusting copied `wp_options` schema metadata. The example
`examples/application-json-path-validation-preflight.php` builds a local fixture
with one valid expression index:

```sql
option_value ->> '$.""'
```

and two malformed copied-schema expression indexes:

```sql
option_value ->> '$.plugin[#-]'
json_extract(option_value, '$.')
```

For Application migration and database-repair tooling this prevents a damaged or
hand-copied schema row from making native recovery code trust an unusable JSON
expression-index root page. The scenario reports `$.""` as valid, `$.`,
`$.plugin[#-]`, and `$.plugin[#9]` as invalid, resolves root page 3 for the
valid empty-label path, skips the malformed plugin-path root page, returns
`plugin_empty_label_settings`, and stays local-only without requiring the
SQLite extension.

## JSON Operator json_quote() RHS Scenario

Native JSON operator expression-index preflights now fold direct SQLite
`json_quote(VALUE)` constants for copied `wp_options` indexes when SQLite's
JSON rendering yields a reusable abbreviated path. The example
`examples/application-json-operator-json-quote-rhs-forms.php` builds a local
fixture with these indexes:

```sql
option_value ->> json_quote(NULL)
option_value ->> json_quote(123)
option_value ->> json_quote(1.25)
```

For Application migration and repair tooling this prevents copied plugin
settings from ignoring SQLite's JSON rendering for SQL `NULL`, integer, and
REAL RHS values inside schema SQL. The scenario reports `$.null`, `$."123"`,
and `$."1.25"`, uses root pages 3-5, returns expected
`plugin_json_quote_*` rows, leaves direct quoted text, raw BLOB, and
invalid-arity RHS outputs unsupported as reusable paths, and stays local-only
without requiring the SQLite extension.

## Current Native Slice

Native SQLite database header parser, SQLite varint decoder and encoder,
b-tree page header parser for schema/root pages, table leaf and table interior cell
parsing, a page-backed database reader, SQLite record serial decoding, and
`sqlite_schema` table-b-tree traversal for Application table discovery. The
write-side preflight slice now also serializes SQLite records as UTF-8,
UTF-16LE, or UTF-16BE according to the database header text encoding, plus
table-leaf cells and clean table-leaf pages for minimal fixture or
repair-planning images that can be parsed back by the native reader. The
current slice also
decodes bounded table rows and maps the standard
`wp_options` row shape into `option_id`, `option_name`, `option_value`, and
`autoload` fields without using the PHP SQLite extension. Large
`option_value` records that spill from a table leaf cell into SQLite overflow
pages are now reassembled through the native page reader. Rowid-bounded
`wp_options` reads can now scan `option_id` bands across table-interior pages,
honor inclusive/exclusive upper bounds and limits, and prune unrelated damaged
branches before reading leaf cells, which maps resumable Application option
imports and partial database recovery when no `option_name` index is usable.
Overflow-page
fixtures can also be assembled with caller-supplied non-contiguous page
numbers and reserved-byte usable sizes, mapping repair/preflight workflows
where reusable freelist pages become a new large `wp_options.option_value`
chain. Actual freelist trunk metadata is now readable from database images, so
repair tooling can choose reusable pages from header/trunk state before
building that overflow chain. Allocation planning now also returns the
mutated first-page header and freelist trunk page images after reusable pages
are consumed, including leaf-array replacement, emptied-trunk removal, and
append-after-depletion page numbers for bounded generated-write preflight.
Free planning now mirrors SQLite's bounded `freePage2` behavior for repair
preflight: obsolete pages can be inserted as leaves on the first freelist trunk
or promoted into a new first trunk when the freelist is empty or the first
trunk is compatibility-full. Bounded insert planning now combines these
write-side primitives for explicit-rowid `wp_options` fixtures whose root is a
single table leaf page: the planner returns first-page, table-page,
overflow-page, freelist-trunk, and, for explicit `option_name` indexes,
single-leaf, root-growth, no-split multi-page, same-depth leaf-split,
parent-root-split, or `WHERE option_name IS NOT NULL` partial index page
images for a new option row. It also handles explicit single-leaf
`autoload, option_name` composite indexes plus matching `sqlite_autoindex_*`
automatic UNIQUE/PRIMARY KEY index shapes. It rejects duplicate rowids or
option names and still refuses unsupported composite shapes, unsafe partial
predicates, expression indexes, unsupported automatic indexes, non-root
parent-page splits, or index-overflow cases instead of leaving stale
secondary indexes behind.
Bounded replacement planning handles index-free, single-leaf `wp_options`
fixtures for both shrink and large-value rewrites. Large replacement payloads
allocate their new overflow chain before obsolete overflow pages are returned
to freelist metadata, matching SQLite's b-tree update ordering and avoiding
accidental same-operation reuse of the old chain. Replacement planning also
allows explicit single-leaf full or safe partial `option_name` indexes when
the key and rowid are unchanged, verifies that the index already points to the
replaced row, and can move a single-leaf or multi-page
`autoload, option_name` composite index entry when an `autoload` rewrite
changes the leading key. The same bounded maintenance now also splits a full
destination composite-index leaf when the parent page can absorb the promoted
divider. Inferred `sqlite_autoindex_*` UNIQUE/PRIMARY KEY indexes whose
columns match `option_name` or `autoload, option_name` remain supported for
the bounded single-leaf write shapes. The planner still rejects unsupported
index shapes, unsafe partial predicates, expression indexes, unsupported
automatic indexes, overflowing non-root parent-page splits, source-leaf
rebalancing, or index-overflow cases beyond bounded root growth instead of
leaving stale secondary indexes behind.
Replacement planning can now also locate a target `wp_options` row below a
table-interior root, rewrite only the table leaf that contains the option, and
leave the interior table page unchanged when the replacement cell fits within
the existing leaf. This maps larger Application SQLite images where repair tools
need to update a single option in a multi-page table before the lane supports
general table-leaf splits, rebalancing, journaling, or WAL.
When the larger replacement makes a table leaf split, the planner now handles
both root-level table-interior parents and one-level-deeper non-root
table-interior parents that have room for the new divider. The root page is
left unchanged for the non-root case while the lower parent receives the old
leaf's new max rowid and a new right-most child pointer. Overflowing non-root
parent pages still remain outside this bounded slice.
Explicit
`CREATE INDEX ... ON wp_options(option_name)` b-trees can now be parsed and
used to fetch a single option by indexed name, then resolve the stored rowid
through the table b-tree without scanning the whole options table. The same
lookup path now handles automatic `UNIQUE` indexes where SQLite records
`sqlite_autoindex_*` schema rows with `sql` set to `NULL`, by inferring the
first indexed column from the owning table's `CREATE TABLE` statement. It also
handles automatic non-rowid `PRIMARY KEY` indexes, preserving earlier UNIQUE
autoindex slots so a Application-shaped `PRIMARY KEY(option_name)` lookup still
finds the correct `sqlite_autoindex_wp_options_*` root page. Automatic indexes
now inherit first-column `COLLATE` and `DESC` metadata from `CREATE TABLE`
constraints, so a Application-shaped `UNIQUE(option_name COLLATE NOCASE DESC)`
autoindex can serve case-insensitive option recovery. Explicit `CREATE INDEX`
definitions also carry first-column `COLLATE` and `ASC`/`DESC` metadata into
lookup, so a descending `option_name COLLATE NOCASE` index can serve the same
recovery path. Partial `option_name` indexes are detected and skipped for
unconstrained lookup instead of returning incomplete results; the safe
`WHERE option_name IS NOT NULL` partial-index form is usable for non-null
option-name point lookup. Non-unique first-column indexes can now be scanned
for duplicate matches, allowing a `wp_options(autoload,
option_name)` index to return all autoloaded options for a requested value.
Explicit composite index metadata is now parsed far enough to constrain both
`autoload` and `option_name`, including second-column `NOCASE` comparison and
safe `autoload IS NOT NULL` partial-index use for a known non-null value.
Explicit or safe partial `wp_options(option_name)` indexes can also serve
bounded range scans, including open lower/upper bounds and inclusive upper
bounds. Bounded range scans skip `NULL` option-name keys the same way SQL
comparison predicates do, which lets recovery tooling inspect transient-style
or migration-prefix option-name ranges without decoding every row in the
options table. Equality partial indexes such as
`CREATE INDEX ... ON wp_options(option_name) WHERE autoload='yes'` are now
usable when the recovery caller supplies the matching autoload constraint, so
autoloaded single-option lookups can avoid both a whole-table scan and a wider
composite index requirement. OR equality partial predicates such as
`WHERE autoload='yes' OR autoload='on'` are also usable when the caller
supplies one matching autoload value, which helps migration/recovery tools read
Application databases with mixed legacy autoload state encodings. AND-connected
partial predicates such as
`WHERE autoload='yes' AND option_name IS NOT NULL` are now accepted only when
every term is implied by caller-supplied constraints, so narrowed autoloaded
option indexes can be used without risking incomplete generic lookups.
Comparison and `BETWEEN` partial predicates are now parsed for bounded
`option_name` point and range lookups, so a transient-specific partial index
such as
``WHERE option_name >= '_transient_' AND option_name < '_transient`'``
can serve recovery scans only when the requested bounds or option name are
contained by that predicate.
First-term `lower(option_name)` expression indexes are now parsed as expression
indexes rather than plain column indexes. A case-folded recovery lookup can use
the stored lowered key payload to find `wp_options` rows such as `SiteURL`
without requiring the PHP SQLite extension, while generic `option_name` lookup
continues to reject expression-only indexes unless the caller asks for the
lowercase expression path. The same expression-index path can now serve
case-folded option-name range scans, so transient or migration-prefix recovery
can match mixed-case option rows through `lower(option_name)` while avoiding
ordinary `option_name` index assumptions. Only safe `option_name IS NOT NULL`
partial predicates are accepted for expression ranges; raw comparison
predicates are left unsupported because they are not implied by folded bounds.
The lower-expression path now also supports bounded `IN (...)` reads. Recovery
or preload tools can request a small mixed-case list such as `SITEURL,HOME`
through `wp_options(lower(option_name))`, avoid duplicate rows for duplicate
RHS names, ignore `NULL` RHS terms, and skip out-of-range index branches before
page decoding when a large or partially damaged options database contains
unrelated lower-key subtrees.
If a `lower(option_name)` index declares an application-defined collation, a
caller can now supply the matching PHP comparator explicitly. This maps
slug-like Application option names where separators such as underscores and
hyphens compare equal under a site-defined collation while the ordinary
built-in lower-expression path still rejects unsupported collations.
The custom-collation lower-expression path now also supports `IN (...)` lists
and bounded ranges. Recovery tools can request several slug-equivalent
mixed-case option names without duplicate RHS rows, ignore `NULL` RHS terms,
or scan plugin option-name bands such as `plugin-` through `plugin.` using the
site's comparator while rechecking the table row against the callback before
returning it.
First-term `upper(option_name)` expression indexes are now parsed for
ASCII-folded point, `IN (...)`, and bounded range reads. This maps databases or
recovery tools that stored an uppercase expression index instead of a lowercase
one: callers can request `siteurl,home` or a transient-prefix range, the native
reader probes the stored uppercase keys using
SQLite's built-in bytewise ASCII `upper()` semantics, rejects the expression
index as an ordinary `option_name` index, accepts only safe
`option_name IS NOT NULL` partial predicates for this path, and skips
out-of-range b-tree branches before page decoding.
First-term `trim(option_name)`, `ltrim(option_name)`, and
`rtrim(option_name)` expression indexes are now parsed for point lookups with
SQLite's default space trimming or a literal character-set argument. This maps
recovery databases where option names were accidentally padded during a manual
import or migration: callers can request `siteurl`, the native reader probes a
stored `trim(option_name)` key such as `SiteURL`, preserves `COLLATE NOCASE`
metadata, accepts only safe `option_name IS NOT NULL` partial predicates, and
returns the original row name such as ` SiteURL  ` for review or repair.
First-term `substr(option_name,start,length)` expression indexes are now
parsed for non-zero integer start and optional non-negative length literals. A
Application recovery tool can use a `substr(option_name,1,N)` expression index to read prefix
buckets such as `_transient_` through native index traversal, including
`COLLATE NOCASE` comparison and safe `option_name IS NOT NULL` partial
predicate checks. This remains intentionally narrower than SQLite's full
expression engine: variable-start substrings, expression `IN` lookup families
beyond `lower(column)`, `upper(column)`, and this literal-start prefix-list
path, and arbitrary functions are still future slices.
The literal-start prefix path now also supports bounded `IN (...)` reads for
same-length prefixes. Recovery tools can read `_transient_` and `_site_trans`
cache buckets from one `substr(option_name,1,N)` expression index, avoid
duplicate rows for duplicate RHS prefixes, ignore `NULL` RHS values, and skip
out-of-range expression-index branches before page decoding.
Negative literal starts are now
accepted for suffix buckets such as `substr(option_name,-9)`: native recovery
tools can inspect `*_settings` option groups through stored suffix keys,
including `COLLATE NOCASE`/`DESC` metadata, without treating that expression
index as a normal `option_name` column index.
First-term `length(option_name)` expression indexes are now parsed for exact
integer length bucket lookups. A Application audit or recovery tool can use a
`length(option_name)` index to find suspiciously short, policy-sensitive, or
known-length option-name groups without scanning the whole `wp_options` table.
This slice accepts only safe `option_name IS NOT NULL` partial predicates and
uses UTF-8 character length when text is decodable, matching SQLite's text
length semantics for the current Application-oriented fixture boundary.
The same length-expression path now supports bounded `IN (...)` reads for
multiple integer buckets. Recovery and audit tools can request lengths such as
`4,10` in one index pass, ignore `NULL` RHS values, reject invalid length
terms before lookup, and skip unrelated length subtrees before page decoding.
The length-expression path also supports bounded integer range scans with
open or inclusive upper bounds. Recovery and audit tools can inspect suspicious
option-name length bands such as medium-length migration markers without
scanning every `wp_options` row, while still using SQLite-style UTF-8 text
length behavior for the current fixture boundary.
First-term `CAST(option_value AS INTEGER)` expression indexes are now parsed
for exact integer lookups. Recovery and audit tools can find numeric-looking
option values such as `db_version` through SQLite's integer cast behavior,
including text prefixes like `58796abc` and non-numeric text casting to `0`,
without treating the expression index as a normal `option_value` column index.
This slice accepts only safe `option_value IS NOT NULL` partial predicates.
The same CAST-expression path now supports bounded `IN (...)` reads for
multiple integer buckets. Recovery and audit tools can request values such as
`58796,0` in one index pass, ignore `NULL` RHS values, reject invalid
non-integer terms before lookup, suppress duplicate RHS output, and skip
unrelated integer-key subtrees before page decoding.
The CAST-expression path also supports bounded integer range scans with open
or inclusive upper bounds. Recovery and audit tools can inspect numeric option
families such as version counters or plugin migration markers through
`CAST(option_value AS INTEGER) >= 100 AND < 60000`, while still using SQLite's
text-prefix integer cast rules and avoiding unrelated index branches.
First-term `json_extract(option_value,'$.key')` expression indexes are now
parsed for exact scalar lookups over strict JSON or supported JSON5 option values. Recovery and
audit tools can find plugin/theme settings such as `{"enabled":true}` through
the stored JSON expression key, with SQLite-style boolean scalars mapped to
`1`/`0`, without treating the expression index as a normal `option_value`
column index. This slice accepts only simple object-member paths and safe
`option_value IS NOT NULL` partial predicates.

Large `wp_options` replacement preflights now include the bounded case where a
target table leaf split overflows a full non-root table-interior parent while
the root can still absorb the promoted divider. This maps larger Application
SQLite database images with a deeper options table: repair tooling can rewrite
one expanded option row, split the target leaf, split the full lower parent,
update the root separators, and inspect the resulting page images without the
SQLite extension. The focused example is
`examples/application-nonroot-table-parent-split-option-replacement-plan.php`.
The same JSON-expression path now supports bounded `IN (...)` reads for
multiple scalar buckets. Recovery and preload tools can request values such as
`enabled,disabled`, honor `COLLATE NOCASE`, ignore `NULL` RHS values for
matching, suppress duplicate RHS output, and skip unrelated JSON-key subtrees
before page decoding.
The JSON-expression path also supports bounded scalar range scans with open or
inclusive upper bounds. Recovery and audit tools can inspect numeric priority
bands or text status bands inside strict-JSON plugin settings through
`json_extract(option_value,'$.key')` without scanning every option row, while
still excluding JSON null or missing-path keys from bounded comparisons.
JSON expression row verification now falls back to a bounded SQLite JSON5
parser when strict JSON decoding fails. Recovery tools can read manually
edited plugin settings such as `{enabled: true, mode: 'dark', /* note */
rules: [{enabled:false}, {enabled:true,},],}` through stored JSON expression
indexes, while malformed JSON5 such as duplicate commas is still rejected
instead of trusting an index payload blindly.
JSON5 non-finite numbers now follow SQLite's JSON normalization boundary:
`+Infinity` and `-Infinity` can be matched through scalar JSON expression
indexes and through `->` fragment indexes as `9e999` and `-9e999`, while
`NaN` is treated as JSON null. This maps plugin/theme option values that use
JSON5 sentinels for unlimited cache TTLs, disabled quotas, or unset import
limits, and the JSONB fixture example can generate matching BLOB values for
preflight/recovery tests.
SQLite `option_value ->> 'key'` expression indexes are now accepted for the
same simple JSON object-member lookup family. This maps plugin/theme settings
databases that use the JSON text-operator shorthand instead of
`json_extract(...)`: recovery tools can still request `$.enabled`, resolve the
arrow expression index, and verify the strict JSON or supported JSON5 scalar before returning
matching `wp_options` rows.
SQLite `option_value -> 'key'` expression indexes are now accepted as a
separate JSON-fragment lookup family. This maps plugin/theme settings
databases that index a JSON object, array, quoted string, boolean, or JSON null
fragment instead of the SQL scalar returned by `->>`: recovery tools can
request a path such as `$."settings.v1"`, compare SQLite's JSON text result,
and distinguish a stored JSON null from a missing path.
The same JSON-fragment path now supports bounded `IN (...)` and range reads.
Recovery and audit tools can request several stored JSON fragments such as a
settings object, a string state, and JSON null in one indexed pass, suppress
duplicate RHS values, and scan JSON-text channel ranges while still excluding
missing paths.
JSON expression paths now also support non-negative array indexes such as
`$.rules[0].enabled` and `$[0]`, plus reverse array indexes such as
`$.rules[#-1].enabled` and `$[#-1]`. This maps plugin/theme settings that store
ordered rule lists, feature channels, or migration stages in JSON arrays:
native recovery tools can resolve `json_extract(option_value,
'$.rules[0].enabled')`, `json_extract(option_value,'$.rules[#-1].enabled')`,
`option_value ->> '[0]'`, `option_value ->> 0`, or `option_value ->> -1`
expression indexes, distinguish arrays from object labels, treat `[#]` as
not-found for extraction, and reject malformed reverse path forms until broader
JSON mutation behavior is ported.
SQLite JSON path object-label escaping now matches the focused `json502.test`
boundary. Recovery tools can use expression indexes whose path labels contain
embedded quotes, JSON5-style hex escapes, or backslashes, including
`json_extract(option_value,'$.A"Key')`,
`json_extract(option_value,'$."plugin\x5cenabled"')`, and `option_value ->>
'a\x62c'`. This maps plugin/theme settings exports whose option JSON keys were
generated from external identifiers rather than plain PHP array keys.
Composite `wp_options(autoload, option_name)` indexes can now serve the common
SQLite equality-prefix plus range shape: `autoload='no'` constrains the first
indexed column while bounded `option_name` comparisons scan only matching
index records. This maps transient cleanup and cache-inspection workflows that
need non-autoloaded `_transient_` rows from a database image. The same path
honors second-column `NOCASE` comparison, physical `DESC` index order, and
partial predicates such as `autoload='no' AND option_name IS NOT NULL` only
when the caller's constraints imply the predicate.
The composite range path now also prunes unrelated b-tree branches before
reading their pages, so a recovery/import tool can still inspect a narrow
autoload/name range when an out-of-range index branch is damaged or expensive
to hydrate.
Multi-column equality prefixes are now available through
`optionRowsByIndexedNameRangeWithPrefix()`. A recovery tool can target
indexes shaped like `wp_options(autoload, option_value, option_name)`, for
example `autoload='no' AND option_value='cached-feed'` plus a transient
`option_name` range, and still avoid unrelated or damaged branches.

B-tree page freeblock chains can now be inspected directly from a page header.
The native varint encoder now gives Application recovery/import tools a bounded
write-side primitive for preflighting generated `wp_options` table-leaf cell
payload-length and rowid prefixes before broader raw b-tree page writing is
ported.
Application recovery or import diagnostics can report reclaimed/deleted-space
regions on the schema root or `wp_options` root page, compute SQLite-style
free-space totals, and flag overlapping, out-of-usable-space, or impossible
free-space accounting before relying on an index or table page. This is a
read-only page-integrity slice, not SQLite defragmentation or page rewriting.

First-column `IN (...)` option-name lookups now read multiple requested
options through an `option_name` index, suppress duplicate RHS names the way
SQLite avoids duplicate result rows, and ignore `NULL` RHS values for `WHERE`
matching. The same path can safely use `WHERE option_name IS NOT NULL` partial
indexes and exact-order `WHERE option_name IN ('siteurl','home')` partial
indexes, matching the bounded SQLite planner behavior instead of treating every
logical subset as usable. IN-list reads now also prune out-of-range index
subtrees before page decoding, so a small preload list can still be recovered
when an unrelated branch of a large `wp_options(option_name)` index is damaged
or expensive to hydrate.

First-column range, lower-expression IN-list/range, length-expression IN-list/range,
CAST-expression IN-list/range, first-column IN-list, JSON expression point/IN-list/range,
and composite equality-prefix range scans now use bounded index b-tree traversal instead of
decoding every index page. This matters for Application recovery and import tools
that inspect a narrow option-name range or a small known option-name set from a
large or partially damaged database image: an unrelated out-of-range index
branch no longer has to be readable before constrained `wp_options(option_name)`,
`wp_options(lower(option_name))`, `wp_options(CAST(option_value AS INTEGER))`,
`wp_options(json_extract(option_value,'$.key'))`, or
`wp_options(autoload, option_name)` lookups can return matching rows.

The reader now also exposes `sqlite_sequence` records for AUTOINCREMENT tables.
Application import, recovery, or Data Liberation tooling can inspect sequence
counters for tables such as `wp_posts`, `wp_comments`, and `wp_users` from a
raw database image, preserving mutable SQLite `name` and `seq` scalar values
instead of assuming every `seq` cell is an integer.
The native AUTOINCREMENT state can now also compute the next generated ID from
the target table plus `sqlite_sequence`, create a missing sequence row in
state, recover from invalid mutable `seq` values, and advance the counter for
explicitly imported Application IDs so the next generated post/comment/user ID
does not collide with imported content. This is deliberately a bounded
read/write model for sequence state, not a general SQL insert engine or raw
SQLite page writer.

## Example

`examples/application-options-root-page.php` reads a Application-oriented SQLite
database file, walks the `sqlite_schema` table b-tree, resolves the
`wp_options` root page, reports schema/options root-page metadata, and emits a
bounded sample of decoded `wp_options` records without using the PHP SQLite
extension. The same path now handles large serialized/autoloaded option values
stored on overflow pages. This is an inspection primitive needed by
import/export and recovery tooling on hosts where `sqlite3` is unavailable.

`examples/application-page-freeblocks.php` reads a Application-oriented SQLite
database image, inspects one b-tree page's freeblock chain, reports
SQLite-style free-space accounting, and surfaces page-local freeblock
corruption without invoking the SQLite extension.

`examples/application-indexed-option-lookup.php` reads a Application-oriented
SQLite database file, resolves an explicit `wp_options(option_name)` index,
an automatic `UNIQUE` option-name autoindex, or an automatic non-rowid
`PRIMARY KEY` option-name autoindex, and returns one option by name using
native index and rowid b-tree traversal. Explicit and automatic first-column
`COLLATE NOCASE`, `COLLATE RTRIM`, and `DESC` index metadata are honored for
point lookups. Unsupported partial indexes are not used for unconstrained
option lookup, while `WHERE option_name IS NOT NULL` indexes can serve normal
non-null option-name recovery.

`examples/application-options-by-name-list.php` reads a Application-oriented SQLite
database file, resolves an indexed `wp_options(option_name)` IN-list lookup,
and returns a bounded set of named options such as `siteurl,home,blogname`
without scanning the full options table or using the PHP SQLite extension. This
path now uses bounded index traversal, mapping plugin/theme preload and
recovery workflows that need a small known set of options from a database image
without requiring every unrelated index branch to be readable first.

`examples/application-autoloaded-options.php` reads a Application-oriented SQLite
database file, resolves an explicit or safe partial first-column
`wp_options(autoload, ...)` index, and returns all matching options for an
autoload value without scanning the entire `wp_options` table. This maps the
recovery/import use case where a site needs to inspect autoloaded options on a
host without the PHP SQLite extension.

`examples/application-autoloaded-option-by-name.php` reads a Application-oriented
SQLite database file, resolves either an explicit composite
`wp_options(autoload, option_name)` index or an equality partial
`wp_options(option_name) WHERE autoload='yes'` index. The same path now accepts
OR equality partial predicates such as `autoload='yes' OR autoload='on'` when
the requested autoload value matches one branch, and AND-connected partial
predicates such as `autoload='yes' AND option_name IS NOT NULL` when all terms
are implied. It returns a single option when both the autoload value and option
name are known. This is useful for recovery tools that need to inspect one
autoloaded option while avoiding a whole-table scan on constrained hosts.

`examples/application-option-name-range.php` reads a Application-oriented SQLite
database file, resolves an explicit or safe partial `wp_options(option_name)`
range index, and returns options whose names fall between caller-supplied lower
and upper bounds. The range helper now also accepts comparison and `BETWEEN`
partial indexes when the requested bounds imply the partial predicate. Either
bound can be omitted with `-`, and the upper bound can be made inclusive; at
least one bound is required. By default it targets the `_transient_` prefix
range, which maps cleanup and cache-inspection workflows on hosts without the
PHP SQLite extension.

`examples/application-autoloaded-option-name-range.php` reads a
Application-oriented SQLite database file, resolves a composite
`wp_options(autoload, option_name)` index, and returns options for one autoload
value whose names fall between caller-supplied bounds. By default it targets
non-autoloaded `_transient_` rows, which maps transient cleanup and recovery
tools that need SQLite index semantics without the PHP SQLite extension.

`examples/application-prefixed-option-name-range.php` reads a Application-oriented
SQLite database file, accepts a JSON equality-prefix object such as
`{"autoload":"no","option_value":"cached-feed"}`, resolves a composite index
whose next column is `option_name`, and returns options in the requested name
range. This maps recovery of a narrow subset of transient/cache rows from
large or partially damaged option databases.

`examples/application-lowercase-option-lookup.php` reads a Application-oriented
SQLite database file, resolves a first-term
`wp_options(lower(option_name))` expression index, and returns a single option
by case-folded name. This maps recovery workflows that need case-insensitive
option inspection from a database image but must not treat expression indexes
as ordinary column indexes.

`examples/application-lowercase-custom-collation-option-lookup.php` reads a
Application-oriented SQLite database file, resolves a first-term
`wp_options(lower(option_name) COLLATE WPSLUG)` expression index, and returns
matching options only when the caller supplies the matching PHP collation
callback. This maps plugin/theme settings whose option-name slugs differ by
case, underscores, or hyphens while keeping unsupported custom collations out
of the ordinary lower-expression lookup path.

`examples/application-lowercase-option-name-range.php` reads a
Application-oriented SQLite database file, resolves a first-term
`wp_options(lower(option_name))` expression index, and returns options whose
folded names fall between caller-supplied bounds. By default it targets the
`_transient_` prefix range, mapping case-folded transient cleanup and recovery
without requiring the PHP SQLite extension or every out-of-range index branch
to be readable.

`examples/application-lowercase-options-by-name-list.php` reads a
Application-oriented SQLite database file, resolves a first-term
`wp_options(lower(option_name))` expression index, and returns a bounded set of
case-folded names such as `SITEURL,HOME` without scanning the whole table. This
maps plugin/theme preload and recovery workflows where option names may have
unexpected case and a plain `option_name` index is not available.

`examples/application-uppercase-options-by-name-list.php` reads a
Application-oriented SQLite database file, resolves a first-term
`wp_options(upper(option_name))` expression index, and returns a bounded set of
ASCII-folded names such as `siteurl,home` without scanning the whole table.
This maps recovery workflows where an uppercase expression index exists and the
PHP SQLite extension is unavailable.

`examples/application-uppercase-option-name-range.php` reads a
Application-oriented SQLite database file, resolves a first-term
`wp_options(upper(option_name))` expression index, and returns options whose
ASCII-folded names fall inside caller supplied bounds. This maps transient or
migration-prefix recovery when the available expression index stores uppercase
keys rather than lowercase keys.

`examples/application-option-name-prefix.php` reads a Application-oriented SQLite
database file, resolves a first-term
`wp_options(substr(option_name,1,N))` expression index, and returns options
whose name prefix equals the caller-supplied prefix. By default it targets the
`_transient_` bucket, mapping cache/transient inspection from SQLite database
images without requiring the PHP SQLite extension or a full table scan.

`examples/application-option-name-prefix-list.php` reads a Application-oriented
SQLite database file, resolves a first-term
`wp_options(substr(option_name,1,N))` expression index, and returns options
whose prefix is in a same-length caller-supplied list such as
`_transient_,_site_trans`. This maps cache/site-transient recovery and preload
workflows that need multiple option-name buckets without scanning every row.

`examples/application-option-name-suffix.php` reads a Application-oriented SQLite
database file, resolves a first-term
`wp_options(substr(option_name,-N))` expression index, and returns options
whose name suffix equals the caller-supplied suffix. By default it targets
`_settings`, mapping plugin/theme settings bucket inspection from database
images without requiring the PHP SQLite extension or a full table scan.

`examples/application-option-name-length.php` reads a Application-oriented SQLite
database file, resolves a first-term `wp_options(length(option_name))`
expression index, and returns options whose names have the requested length.
By default it targets length `4`, mapping quick recovery checks for compact
core options such as `home` or other policy-sensitive option-name buckets
without requiring a full table scan.

`examples/application-option-name-length-list.php` reads a Application-oriented
SQLite database file, resolves a first-term `wp_options(length(option_name))`
expression index, and returns options whose name lengths are in a caller
supplied list such as `4,10`. This maps multi-bucket option-name audits and
preload checks without scanning every `wp_options` row.

`examples/application-option-name-length-range.php` reads a Application-oriented
SQLite database file, resolves a first-term `wp_options(length(option_name))`
expression index, and returns options whose name lengths fall inside caller
supplied bounds. This maps option-name length anomaly audits and recovery
checks without requiring the PHP SQLite extension or a full table scan.

`examples/application-option-value-integer.php` reads a Application-oriented SQLite
database file, resolves a first-term
`wp_options(CAST(option_value AS INTEGER))` expression index, and returns
options whose values cast to a requested integer. This maps recovery and audit
checks for numeric-looking options such as `db_version`, plugin counters, or
legacy values like `58796abc` that SQLite casts by their leading integer text
without requiring the PHP SQLite extension.

`examples/application-option-value-integer-list.php` reads a Application-oriented
SQLite database file, resolves a first-term
`wp_options(CAST(option_value AS INTEGER))` expression index, and returns
options whose cast values are in a caller supplied integer list such as
`58796,0`. This maps multi-value numeric option audits and recovery checks
without scanning every `wp_options` row.

`examples/application-option-value-integer-range.php` reads a Application-oriented
SQLite database file, resolves a first-term
`wp_options(CAST(option_value AS INTEGER))` expression index, and returns
options whose cast values are inside caller supplied integer bounds. This maps
version/counter audits and recovery checks that need numeric ranges without
scanning every `wp_options` row.

`examples/application-json-option-value.php` reads a Application-oriented SQLite
database file, resolves a first-term
`wp_options(json_extract(option_value,'$.key'))` expression index, and returns
options whose strict JSON or supported JSON5 scalar value matches a requested path/value pair.
This maps plugin/theme settings recovery such as indexed enabled flags without
requiring the PHP SQLite extension or a full table scan.

`examples/application-json5-option-value.php` documents the same indexed scalar
lookup for option rows whose JSON text uses SQLite JSON5 input features such
as unquoted keys, single-quoted strings, comments, extra whitespace, or
trailing commas. This maps recovery of manually edited plugin/theme settings
without requiring the SQLite extension.

`examples/application-json-escaped-label-option-value.php` documents indexed
scalar lookups for JSON object labels that require SQLite path escaping, such
as embedded quotes, `\xNN` label escapes, or backslash-containing plugin
settings keys.

`examples/application-json-array-option-value.php` reads a Application-oriented
SQLite database file, resolves a first-term
`wp_options(json_extract(option_value,'$.rules[0].enabled'))`-style expression
index, and returns options whose strict JSON scalar at a non-negative array
path matches the requested value. This maps ordered plugin rule/channel
settings without scanning every serialized option row.

`examples/application-json-last-array-option-value.php` reads a
Application-oriented SQLite database file, resolves a first-term
`wp_options(json_extract(option_value,'$[#-1]'))` or `option_value ->> -1`
expression index, and returns options whose strict JSON scalar at the last
array position matches the requested value. This maps plugin channel lists,
latest migration stages, and last-rule checks without scanning every serialized
option row.

`examples/application-json-option-value-list.php` reads a Application-oriented
SQLite database file, resolves a first-term
`wp_options(json_extract(option_value,'$.key'))` expression index, and returns
options whose strict JSON scalar value is in a caller supplied list. This maps
multi-state plugin/theme settings recovery such as enabled/disabled mode lists
without scanning every `wp_options` row.

`examples/application-json-option-value-range.php` reads a Application-oriented
SQLite database file, resolves a first-term
`wp_options(json_extract(option_value,'$.key'))` expression index, and returns
options whose strict JSON scalar value falls inside caller supplied bounds. This
maps plugin/theme settings audits such as numeric priority or migration stage
bands without scanning every `wp_options` row.

`examples/application-json-option-arrow.php` reads a Application-oriented SQLite
database file, resolves a first-term `wp_options(option_value ->> 'key')`
expression index, and returns options whose strict JSON scalar value matches
the requested label/path and scalar. This maps plugin/theme settings recovery
when the database uses SQLite's JSON text-operator shorthand.

`examples/application-json-option-fragment.php` reads a Application-oriented
SQLite database file, resolves a first-term `wp_options(option_value -> 'key')`
expression index, and returns options whose JSON fragment matches a requested
path and JSON value. This maps plugin/theme settings recovery when a database
indexes a nested settings object, JSON string, boolean, or JSON null as JSON
text rather than as a SQL scalar.

`examples/application-json-option-fragment-list.php` reads a Application-oriented
SQLite database file, resolves a first-term `wp_options(option_value -> 'key')`
expression index, and returns options whose JSON fragment is in a caller
supplied JSON array. This maps multi-state plugin/theme settings recovery where
object fragments, strings, booleans, and JSON null must be matched without a
full table scan.

`examples/application-json-option-fragment-range.php` reads a Application-oriented
SQLite database file, resolves a first-term `wp_options(option_value -> 'key')`
expression index, and returns options whose JSON-text fragment falls inside
caller supplied bounds. This maps channel/stage audits where the database
stores JSON fragments through SQLite's value-operator shorthand.

`examples/application-jsonb-option-value.php` reads a Application-oriented SQLite
database file, resolves a first-term
`wp_options(json_extract(option_value,'$.key'))` expression index, and returns
options whose scalar value matches even when the underlying `option_value`
record is a SQLite JSONB BLOB. This maps plugin/theme settings databases that
were populated by SQLite JSONB functions while still running on hosts without
the PHP SQLite extension.

`examples/application-jsonb-option-fixture.php` encodes strict JSON or supported
SQLite JSON5 settings text into native SQLite JSONB bytes for Application
`wp_options.option_value` BLOB fixtures. This maps recovery tests and import
preflight tools that need JSONB-shaped plugin settings without shelling out to
SQLite.

`examples/application-jsonb-remove-option-field.php` removes one or more JSON
paths from strict JSON, supported SQLite JSON5, or SQLite JSONB option-value
fixtures and prints the resulting JSONB bytes. This maps Application recovery
and migration preflight workflows that need to strip obsolete or sensitive
plugin settings from `wp_options.option_value` JSONB blobs while preserving
SQLite-style object-member, array-index, reverse-index, missing-path, and root
removal behavior.

`examples/application-jsonb-mutate-option-field.php` applies SQLite-style
`insert`, `set`, or `replace` edits to strict JSON, supported SQLite JSON5, or
SQLite JSONB option-value fixtures and prints the resulting JSONB bytes. This
maps Application recovery and migration preflight workflows that need to add
migration markers, append rule objects, replace stale plugin settings, or
leave existing fields untouched according to SQLite's
`jsonb_insert`/`jsonb_set`/`jsonb_replace` path semantics.

`examples/application-jsonb-array-insert-option-field.php` applies SQLite-style
`jsonb_array_insert` edits to strict JSON, supported SQLite JSON5, or SQLite
JSONB option/meta fixtures and prints the resulting JSONB bytes. This maps
Application import preflight workflows that need to insert a migration queue
entry before an existing option-array item or append postmeta migration keys
while preserving SQLite's array-index, reverse-index, missing-path, and
non-array no-op boundaries.

`examples/application-jsonb-inspect-option-arrays.php` checks SQLite JSONB
option/meta fixture paths with `json_type` and `json_array_length` semantics.
This maps Application import and migration preflight workflows that need to
confirm option migration queues or postmeta key lists are present arrays, while
distinguishing missing paths from existing scalar or object targets before
array insertion, append, or reorder steps.

`examples/application-jsonb-patch-option-field.php` applies SQLite-style
RFC-7396 merge patches to strict JSON, supported SQLite JSON5, or SQLite JSONB
option-value fixtures and prints the resulting JSONB bytes. This maps
Application import and recovery preflight workflows that need to apply a patch
object where `null` removes obsolete plugin settings, nested objects merge,
and arrays such as rule lists or channel lists are replaced as complete
values.

`examples/application-trimmed-option-name.php` reads a Application-oriented SQLite
database file, resolves a first-term
`wp_options(trim(option_name))`/`ltrim`/`rtrim` expression index, and returns
the option whose normalized name matches the requested input. This maps
whitespace-damaged option-name recovery without requiring the PHP SQLite
extension or a full table scan.

`examples/application-sequence-counters.php` reads a Application-oriented SQLite
database file, resolves the internal `sqlite_sequence` table, and reports all
AUTOINCREMENT rows plus selected counters such as `wp_posts`, `wp_comments`,
and `wp_users`. This maps ID-continuity checks during imports and recovery on
hosts where the PHP SQLite extension is unavailable.

`examples/application-autoincrement-continuity.php` reads a Application-oriented
SQLite database file, builds AUTOINCREMENT state for selected tables, reports
the next generated ID and sequence row after a generated insert, and can model
planned explicit imports such as `wp_posts=500` to verify that subsequent
generated IDs continue after imported content.

`examples/application-schema-record.php` builds a deterministic schema-root page
containing a `wp_options` table record, parses the table leaf cell payload, and
reports the decoded table name/root page without using the PHP SQLite extension.

`examples/application-custom-collation-option-lookup.php` reads a
Application-oriented SQLite database file whose `wp_options(option_name)` index
was created with an application-defined collation such as `WPCASE` or
`BACKWARDS`. The caller supplies the matching PHP comparator, allowing recovery
tooling to use that index intentionally while ordinary built-in lookup paths
continue to reject unsupported collations instead of returning misleading
results.

`examples/application-custom-collation-option-name-range.php` reads a bounded
`wp_options(option_name COLLATE X)` name range with a supplied comparator. This
maps plugin recovery indexes whose option-name ordering treats case,
underscores, or other slug separators differently from SQLite's built-in
`BINARY`/`NOCASE`/`RTRIM` collations, while still requiring an explicit
collation match and a comparator that returns an integer.

`examples/application-custom-collation-autoload-option-name-range.php` reads a
bounded `wp_options(autoload, option_name COLLATE X)` range with a supplied
comparator for the second indexed column. This maps non-autoloaded transient or
cache recovery on sites that created custom slug/case collations while still
requiring the autoload equality prefix and a collation-safe partial predicate.

`examples/application-custom-collation-prefix-option-name-range.php` reads a
bounded `option_name` range through a composite index whose equality-prefix
column uses an application-defined collation, for example
`wp_options(option_value COLLATE WPSLUG, option_name)`. The caller supplies a
collation callback map, so recovery tooling can group plugin/cache rows where
`Plugin-Core` and `plugin_core` compare equal under site-specific slug rules
while the ordinary composite path continues to reject unsupported collations.

`examples/application-table-leaf-page-assembly.php` assembles a minimal two-page
SQLite database image containing a `wp_options` table and a `siteurl` row using
only native PHP record, table-leaf cell, and table-leaf page encoders. The
script immediately parses the generated bytes through the native reader,
making it useful for fixture generation and repair preflight workflows that
need to reason about generated SQLite bytes without the PHP SQLite extension.

`examples/application-index-leaf-page-assembly.php` extends that fixture path to
a three-page image with a generated `wp_options(option_name)` index leaf page.
The script encodes index cells natively, assembles the index b-tree page,
parses it back through the native reader, and verifies an indexed `siteurl`
lookup through the generated rowid payload without the PHP SQLite extension.

`examples/application-index-interior-page-assembly.php` extends generated index
fixtures to a five-page image whose `wp_options(option_name)` index root is an
interior b-tree page. The script encodes the left child pointer, separator
payload, right-most pointer, and leaf pages natively, then verifies that the
reader walks the generated multi-page index and resolves `siteurl` by rowid
without the PHP SQLite extension.

`examples/application-overflow-page-assembly.php` assembles a `wp_options` row
whose large `option_value` spills from the table leaf cell to SQLite overflow
pages. The script uses native PHP to split the local payload, write the
overflow next-page pointer chain, and parse the generated database image back
through `optionRows()`.

`examples/application-overflow-page-freelist-reuse.php` assembles a
Application-shaped `wp_options` row whose large `option_value` spills onto
non-contiguous reusable overflow pages such as `5 -> 3 -> 7 -> 0` while each
page reserves 12 bytes at the tail. The script verifies the generated
next-page pointers and parses the option back without the PHP SQLite
extension, mapping repair tools that need to plan writes into freed pages
without a full pager yet.

`examples/application-freelist-overflow-repair-plan.php` starts from an actual
SQLite-style freelist trunk page, reads its leaf page pointers and header
counts, chooses reusable pages using SQLite's ordinary freelist allocation
order, receives the mutated header/trunk page images from
`planPageAllocation()`, writes a large `wp_options.option_value` overflow
chain into those pages, and verifies the repaired image through the native
reader.

`examples/application-free-obsolete-overflow-pages.php` models the opposite
repair direction: rewrite a large `wp_options` row down to a small inline
value, return the old overflow pages to freelist metadata through
`planPageFreeList()`, and verify both the smaller option row and resulting
freelist allocation order without the PHP SQLite extension.

`examples/application-generated-option-insert-plan.php` starts from a minimal
index-free `wp_options` table image, asks `planOptionRowInsert()` for a
bounded generated row insert, applies the returned page images, and parses the
new large option value back through the native reader. This maps Application
fixture generation and low-level repair preflight where a tool needs concrete
SQLite page bytes before a full pager, index maintainer, or WAL writer exists.

`examples/application-indexed-generated-option-insert-plan.php` starts from a
minimal `wp_options` table with a single-leaf `option_name` index, asks
`planOptionRowInsert()` for a bounded generated row insert, applies the
returned table and index page images, and verifies that the new `home` option
is reachable through `optionRowByIndexedName()`. This maps repair and
fixture generation for common Application SQLite images that already have a
simple option-name secondary index.

`examples/application-utf16-option-insert-plan.php` starts from a minimal
UTF-16LE SQLite database image, asks `planOptionRowInsert()` for a
bounded generated `blogdescription` row, applies the returned table page
image, and verifies that the option value decodes back to UTF-8. This maps
Application SQLite repair/preflight where the file header text encoding is not
UTF-8 but tooling still cannot rely on the SQLite extension.

`examples/application-automatic-indexed-generated-option-insert-plan.php` starts
from a minimal `wp_options` table whose `option_name UNIQUE` constraint is
represented by a `sqlite_autoindex_wp_options_1` schema row with `sql=NULL`.
It asks `planOptionRowInsert()` for a bounded generated row insert,
applies the returned table and autoindex page images, and verifies that the
new `home` option is reachable through the inferred automatic index. This maps
Application SQLite repair preflight where uniqueness is enforced by a table
constraint rather than an explicit `CREATE INDEX` statement.

`examples/application-partial-indexed-generated-option-insert-plan.php` starts
from a minimal `wp_options` table with a single-leaf
`WHERE option_name IS NOT NULL` partial `option_name` index, asks
`planOptionRowInsert()` for a bounded generated row insert, applies the
returned table and index page images, and verifies that the new `home` option
is reachable through the partial index. This maps Application SQLite images that
use a safe partial option-name index to exclude malformed `NULL` names while
still covering every normal Application option row.

`examples/application-composite-indexed-generated-option-insert-plan.php` starts
from a minimal `wp_options` table with a single-leaf
`autoload, option_name COLLATE NOCASE DESC` composite index, asks
`planOptionRowInsert()` for a bounded generated row insert, applies the
returned table and index page images, and verifies that the new `home` option
is reachable through `optionRowByIndexedAutoloadAndName('yes', 'HOME')`.
This maps common Application recovery/preload flows that first constrain
autoloaded options and then probe or sort by option name without decoding the
whole table.

`examples/application-composite-indexed-option-replacement-plan.php` starts from
a minimal `wp_options` table with the same composite index, asks
`planOptionRowReplace()` to rewrite `siteurl` from `autoload='yes'` to
`autoload='no'`, applies the returned table and index page images, and
verifies that the option is reachable through
`optionRowByIndexedAutoloadAndName('no', 'SITEURL')`. This maps
Application repair tools that disable autoload for heavy options while keeping a
preload-oriented composite index consistent.

`examples/application-multipage-composite-indexed-option-replacement-plan.php`
starts from a `wp_options` table with a two-level
`autoload, option_name` secondary index, asks `planOptionRowReplace()`
to rewrite `siteurl` from `autoload='yes'` to `autoload='no'`, applies the
returned table and two leaf-index page images, and verifies that the
`index-interior` root still resolves the row through
`optionRowByIndexedAutoloadAndName('no', 'siteurl')`. This maps larger
Application option tables where repair tooling must update preload indexes
without collapsing the index tree or invoking the SQLite extension.

`examples/application-index-split-option-insert-plan.php` starts from a
`wp_options` table with a two-level `option_name` secondary index whose
right-most leaf is full for the native page assembler. It asks
`planOptionRowInsert()` for a generated option row, applies the returned
header/table/root/leaf page images, and verifies that the existing
`index-interior` root now has a promoted divider plus a newly allocated leaf
while the inserted option is reachable through
`optionRowByIndexedName()`. This maps larger Application SQLite images
where repair tooling must insert generated options without the SQLite
extension and without leaving a secondary index stale when the target leaf
splits but the parent can stay at the same depth.

`examples/application-index-root-split-option-insert-plan.php` starts from a
`wp_options` table with a full single-leaf `option_name` secondary index. It
asks `planOptionRowInsert()` for a generated option row, applies the
returned header/table/root/new-leaf page images, and verifies that the
original index root page has grown into an `index-interior` page whose two new
leaf children keep the inserted option reachable through
`optionRowByIndexedName()`. This maps small-to-medium Application SQLite
images where a repair or fixture-generation insert crosses the first b-tree
depth boundary without the SQLite extension.

`examples/application-index-parent-root-split-option-insert-plan.php` starts
from a larger `wp_options` table with a full two-level `option_name`
secondary index whose right-most leaf and index-interior root are both full.
It asks `planOptionRowInsert()` for a generated option row, applies the
returned header/table/root/leaf/new-interior page images, and verifies that
the original root has grown into a higher-level `index-interior` page over
two newly allocated interior pages while the inserted option remains reachable
through `optionRowByIndexedName()`. This maps repair tooling that must
insert a generated option into a larger SQLite-backed Application database when
the secondary index crosses a deeper b-tree boundary.

`examples/application-composite-index-parent-root-split-option-insert-plan.php`
starts from a larger `wp_options` table with a full two-level
`autoload, option_name` secondary index whose right-most leaf and
index-interior root are both full. It asks `planOptionRowInsert()` for a
generated autoloaded option row, applies the returned
header/table/root/leaf/new-interior page images, and verifies that the grown
composite index still resolves the row through
`optionRowByIndexedAutoloadAndName('yes', $optionName)`. This maps
preload-oriented Application SQLite images where repair tooling must add a
generated option without stale composite indexes or the SQLite extension.

`examples/application-nonroot-index-parent-split-option-insert-plan.php` starts
from a larger `wp_options` table with a three-level `autoload, option_name`
secondary index. It asks `planOptionRowInsert()` for a generated
autoloaded option row whose target leaf is full and whose non-root
index-interior parent is also full. The example applies the returned
header/table/root/parent/leaf/new-parent page images and verifies that the
root absorbs the promoted parent divider while the inserted option remains
reachable through `optionRowByIndexedAutoloadAndName('yes',
$optionName)`. This maps large Application SQLite fallback databases where a
repair preflight must add a generated option without stale composite indexes
and without invoking the SQLite extension.

`examples/application-index-split-option-replacement-plan.php` starts from a
`wp_options` table with a two-level `autoload, option_name` secondary index
whose target `autoload='no'` leaf is full. It asks
`planOptionRowReplace()` to rewrite an existing option from
`autoload='yes'` to `autoload='no'`, applies the returned
header/table/root/source-leaf/split-leaf page images, and verifies that the
replaced option is reachable through
`optionRowByIndexedAutoloadAndName('no', $optionName)`. This maps larger
Application repair flows that disable autoload for a heavy option while keeping
a preload-oriented composite index consistent through a same-depth leaf split.

`examples/application-composite-index-parent-root-split-option-replacement-plan.php`
starts from a larger `wp_options` table with a full two-level
`autoload, option_name` secondary index. It rewrites an existing option from
`autoload='yes'` to `autoload='no'`, where the destination composite-index
leaf and the index-interior root both have to split. The example applies the
returned header/table/source-leaf/destination-leaf/root/new-interior page
images and verifies that the rewritten option is reachable through
`optionRowByIndexedAutoloadAndName('no', $optionName)`. This maps
preload repair tools that must turn off autoload for a heavy option in a
larger SQLite-backed Application database without leaving the composite index
stale and without invoking the SQLite extension.

`examples/application-index-root-collapse-option-replacement-plan.php` starts
from a `wp_options` table whose `autoload, option_name` secondary index root
has two leaf children. It rewrites `siteurl` from `autoload='yes'` to
`autoload='no'`, moving the entry into the sibling leaf and emptying the
source leaf. The planner rebuilds the root as an `index-leaf`, returns the
obsolete child pages to SQLite freelist metadata, and verifies that the
rewritten option remains reachable through the composite index. This maps
Application repair tooling that disables autoload for a heavy option in a small
two-level secondary index without leaving orphaned b-tree child pages.

`examples/application-index-redistribute-option-replacement-plan.php` starts
from a `wp_options` table whose `autoload, option_name` secondary index root
has three child leaves. It rewrites a long cached option from
`autoload='yes'` to `autoload='no'`, leaving the old source leaf underfilled
but non-empty. The planner redistributes that source leaf with its adjacent
sibling, updates the parent divider, inserts the moved key into the updated
destination leaf, and verifies that the rewritten option remains reachable
through the composite index. This maps larger Application repair tooling that
disables autoload for heavy options without leaving a sparsely filled
secondary-index page behind.

`examples/application-multipage-table-option-replacement-plan.php` starts from
a `wp_options` table whose root is a table-interior page over two table leaf
pages. It asks `planOptionRowReplace()` to rewrite the `blogname`
option in the right leaf, applies the returned page image, and verifies that
only page 4 changed while the page-2 table root remains `table-interior`. This
maps larger Application SQLite fallback/repair tools that need to change a
single option below an interior table root without the SQLite extension and
before full pager/journal support exists.

`examples/application-table-root-split-option-replacement-plan.php` starts from
a small `wp_options` table whose root is still a single table leaf. It asks
`planOptionRowReplace()` for a larger `blogname` rewrite, applies the
returned header/root/new-leaf page images, and verifies that page 2 has grown
into a `table-interior` root over split leaf pages 3 and 4 while every option
row remains readable in rowid order. This maps small Application SQLite
databases that cross the first table b-tree depth boundary during a repair or
migration preflight without the SQLite extension.

`examples/application-table-leaf-split-option-replacement-plan.php` starts from
a `wp_options` table whose root is a table-interior page and whose left child
leaf becomes too full after a larger `blogname` replacement. It asks
`planOptionRowReplace()` for the rewrite, applies the returned
header/root/old-leaf/new-leaf page images, and verifies that the root now has
two separator cells while all option rows remain readable in rowid order. This
maps Application repair tooling that must expand a stored option below a
multi-page table root without the SQLite extension and without silently
corrupting table b-tree separators.

`examples/application-nonroot-table-split-option-replacement-plan.php` starts
from a three-level `wp_options` table b-tree. It replaces `blogname` with a
larger value that splits a leaf under a non-root table-interior parent,
applies the returned header/lower-parent/old-leaf/new-leaf page images, and
verifies that the root separator remains unchanged while the lower parent now
points at the split leaves. This maps larger Application SQLite fallback
databases where repair preflight must update one option without forcing a
whole table rewrite.

`examples/application-table-parent-root-split-option-replacement-plan.php`
starts from a `wp_options` table whose table-interior root is full. It
replaces `blogname` with a larger value that splits the right-most leaf and
then grows the full root into two lower table-interior parent pages under a
new one-cell root. The example applies the returned header/root/old-leaf/
new-leaf/new-parent page images and verifies that the rewritten option remains
readable by rowid. This maps large Application SQLite fallback databases where
a repair preflight crosses a deeper table b-tree balance boundary without
requiring the SQLite extension.

`examples/application-replace-obsolete-overflow-option.php` starts from a
large `wp_options` value stored across overflow pages, asks
`planOptionRowReplace()` for a bounded same-row replacement, applies the
returned table/header/freelist page images, and verifies that the smaller row
is readable while the obsolete overflow chain is now available for future
allocation. This maps cache/transient cleanup and migration repair tools that
need to shrink option rows safely before broader pager, index, or WAL support
exists.

`examples/application-replace-large-overflow-option.php` starts from a large
`wp_options` value, replaces it with a larger overflow-backed value, applies
the returned page images, and verifies both the new overflow chain and the
freelist containing the obsolete pages. This maps Application migration and
preload repair tools that need to rewrite large serialized/JSON option
payloads without the SQLite extension while preserving SQLite's allocate-new,
free-old update order.

`examples/application-pointer-map-diagnostics.php` starts from a Application-shaped
auto-vacuum SQLite database with a pointer-map page, a `wp_options` root page,
a child b-tree page, an overflow chain, and a free page. It prints the
root/free/btree/overflow pointer-map entries while still reading the
`siteurl` option through the native table reader. This maps repair preflights
that must recognize auto-vacuum metadata before moving, freeing, or reusing
pages in a Application SQLite fallback database.

`examples/application-pointer-map-mutation-plan.php` starts from a
Application-shaped auto-vacuum SQLite database with a pointer-map page,
`wp_options` b-tree pages, and an overflow chain. It asks the native free-page
planner to release an obsolete overflow page, applies the returned
header/pointer-map/freelist-trunk page images, and verifies that the freed
page's pointer-map entry is now `free-page` while `siteurl` remains readable.
This maps repair preflight for auto-vacuum databases where page moves or
future overflow reuse must not leave stale pointer-map parent references.

`examples/application-autovacuum-overflow-option-insert-plan.php` starts from a
Application-shaped auto-vacuum SQLite database and inserts a large
`theme_mods_twentyfive` option that spills to a three-page overflow chain. It
applies the returned header/pointer-map/table/overflow page images, verifies
that the first overflow page points back to the `wp_options` b-tree page and
that continuation overflow pages point to their previous overflow page, then
reads the inserted option through the native table reader. This maps repair
and migration preflight for large theme-mod or cache options on hosts where
the SQLite extension is unavailable and stale auto-vacuum pointer-map entries
would make later page moves unsafe.

`examples/application-autovacuum-overflow-option-replacement-plan.php` starts
from a Application-shaped auto-vacuum SQLite database with an existing large
`theme_mods_twentyfive` option stored on overflow pages. It asks
`planOptionRowReplace()` to rewrite the option to a larger value,
applies the returned header/pointer-map/table/freelist/overflow page images,
verifies that obsolete overflow pages are now `free-page` entries, verifies
that the new overflow chain carries `first-overflow-page` and `overflow-page`
parent links back to the owning `wp_options` table leaf, and reads the
rewritten option through the native table reader. This maps Application repair
preflight where changing a serialized theme-mod/cache option in an
auto-vacuum SQLite database must not leave stale pointer-map owners behind.

`examples/application-autovacuum-table-root-split-option-replacement-plan.php`
starts from a Application-shaped auto-vacuum SQLite database whose `wp_options`
table is still a single root leaf. It rewrites `blogname` to a larger value,
applies the returned header/pointer-map/table page images, verifies that the
root grew into a table-interior page, and checks that the new child leaf pages
are `btree-page` pointer-map entries owned by the `wp_options` root. This maps
Application repair preflight where changing one larger option must keep
auto-vacuum b-tree parent ownership valid even before broader journaling or
SQL execution support exists.

`examples/application-secure-delete-obsolete-overflow-pages.php` starts from a
Application-shaped SQLite database where a large `wp_options` row stores private
cache data on overflow pages. It rewrites the option to a small inline value
with secure-delete planning enabled, applies the returned header/table/freelist
page images, verifies that obsolete overflow pages are on the freelist, and
checks that the obsolete overflow page inserted as a freelist leaf has been
zeroed. This maps repair preflight for sites that require deleted option
payload fragments to be cleared before those pages are reused.

`examples/application-index-merge-option-replacement-plan.php` starts from a
multi-page `wp_options(autoload, option_name)` secondary index where changing a
large cached option from `autoload='yes'` to `autoload='no'` underfills the old
source leaf and leaves too few cells for a legal two-leaf redistribution. It
asks `planOptionRowReplace()` for the rewrite, applies the returned
header/table/root/leaf/freelist page images, and verifies that the obsolete
index leaf is now page 6 on the freelist while the rewritten option is
reachable through the composite index. This maps Application cache or migration
repair tools that need to change autoload state without leaving an invalid
sparse secondary-index page behind.

`examples/application-nonroot-index-merge-option-replacement-plan.php` starts
from a deeper `wp_options(autoload, option_name)` secondary index with a root
interior page, a lower interior parent, and five leaf children. It changes an
option from `autoload='yes'` to `autoload='no'`, merges the underfilled source
leaf with its adjacent sibling below that non-root parent, removes the parent
divider, moves the lower parent's right-most pointer, and verifies that the
obsolete leaf is now on the freelist while the rewritten option is reachable
through the composite index. This maps larger Application SQLite fallback
databases where autoload repair must maintain a deeper secondary index without
waiting for general SQL UPDATE, journaling, or WAL support.

`examples/application-index-parent-collapse-option-replacement-plan.php` starts
from a deeper `wp_options(autoload, option_name)` secondary index where the
source-leaf merge also underfills the lower index-interior parent below a
two-child root. It changes an option from `autoload='yes'` to `autoload='no'`,
merges the old source leaf with its sibling, collapses the underfilled parent
and sibling parent into the root, frees the obsolete leaf and interior pages,
and verifies that the rewritten option is still reachable through the
composite index. This maps larger Application SQLite fallback databases where
autoload repair crosses one more b-tree balancing boundary but still does not
require a general SQL engine.

`examples/application-index-parent-merge-option-replacement-plan.php` starts
from a deeper `wp_options(autoload, option_name)` secondary index where the
root has more than two child parents. It changes an option from
`autoload='yes'` to `autoload='no'`, merges the old source leaf with its
sibling, merges the now-underfilled lower parent with an adjacent interior
sibling, removes the root divider while keeping the root at the same height,
frees the obsolete leaf and interior parent, and verifies that the rewritten
option is still reachable through the composite index. This maps larger
Application SQLite fallback databases where autoload repair crosses a non-root
parent underflow boundary but the index still has sibling parent pages that
can absorb the merge without requiring a full SQL engine.

The same parent-merge scenario now also exposes
`SQLiteOptionRowReplacementPlan::btreeRebalanceActions()`. The diagnostic
compares pre/post B-tree page images and reports root divider removal, merged
interior-parent growth, leaf entry merges, and freed obsolete leaf/interior
pages. This gives Application repair tools an auditable explanation of the
delete-triggered rebalance plan instead of only opaque replacement page images.

## Next Task

Broaden non-root composite-index parent redistribution when adjacent
interior-parent merge does not fit, then broaden cell-level FAST secure-delete,
journaling, and WAL behavior beyond page-image preflight.

## Current-Base Rebase-Prep: `json_group_array()`/`json_group_object()` Option Summary Scenario

Native JSON aggregation now includes a bounded SQLite `json_group_array(X)`/`json_group_object(NAME,VALUE)` row boundary for ordered input rows, SQL NULLs, booleans, JSON subtype fragments, JSONB BLOB values, empty groups, text labels, and malformed raw BLOB rejection. The example `examples/application-json-aggregate-option-summary.php` checks copied `wp_options` rows and produces local-only aggregate JSON summaries that can be reviewed before import without requiring the SQLite extension.

## `jsonb_group_array()`/`jsonb_group_object()` Option Summary Scenario

Native JSON aggregation now also includes a bounded SQLite
`jsonb_group_array(X)`/`jsonb_group_object(NAME,VALUE)` SQL result-type
dispatch boundary. The example
`examples/application-json-aggregate-option-summary.php` checks copied
`wp_options` rows and reports text JSON aggregate summaries plus decoded/hex
JSONB aggregate outputs for copied option values, JSON subtype fragments,
JSONB option blobs, booleans, and NULLs. This gives Application import and
repair tooling a local-only way to preserve JSONB fixture typing for aggregate
diagnostics without requiring the SQLite extension.

## `json_insert()`/`json_set()`/`json_replace()` Mutation Dispatch Scenario

Native JSON mutation now includes a bounded SQLite SQL result-type boundary for
`json_insert()`, `json_set()`, `json_replace()`, and their `jsonb_*` variants.
The updated `examples/application-jsonb-mutate-option-field.php` script can
preflight copied `wp_options` JSON option values with text JSON results or
JSONB blob results, preserving SQLite's distinction between ordinary SQL
scalar values and JSON subtype/JSONB embedded fragments without requiring the
SQLite extension.

## `json_array_insert()`/`jsonb_array_insert()` Array Insert Dispatch Scenario

Native JSON array insertion now includes a bounded SQLite SQL result-type
boundary for `json_array_insert()` and `jsonb_array_insert()`. The updated
`examples/application-jsonb-array-insert-option-field.php` script can preflight
copied `wp_options` JSON option arrays or postmeta migration queues with text
JSON results or JSONB blob results, preserving SQLite's array-index,
reverse-index append, missing-array creation, non-array no-op, and JSON
subtype/JSONB embedded-fragment boundaries without requiring the SQLite
extension. The latest isolated finisher exercises uppercase SQL-style
argument-vector dispatch through the same example, preserving the local
Application path while matching SQLite's case-insensitive function-name
boundary.

## `json_type()`/`json_array_length()` Inspection Dispatch Scenario

Native JSON inspection now includes a bounded SQLite SQL function dispatch
boundary for `json_type(X[,P])` and `json_array_length(X[,P])`. The updated
`examples/application-json-inspection-preflight.php` script can preflight copied
`wp_options` JSON option values using the same function-name dispatch that SQL
callers expect, including strict JSON, JSON5, cast text BLOBs, JSONB blobs,
SQL NULL, missing paths, non-array scalar length `0`, and JSON type-name
results without requiring the SQLite extension.

## `json_valid()` Validity Dispatch Scenario

Native JSON validity now includes a bounded SQLite SQL function dispatch
boundary for `json_valid(X[,FLAGS])`. The updated
`examples/application-json-validity-preflight.php` script can preflight copied
`wp_options` option values using the same function-name dispatch SQL callers
expect, including strict JSON, JSON5, cast text BLOBs, JSONB blobs, SQL NULL
input, nullable `FLAGS`, and combined flag checks without requiring the SQLite
extension.

## `json_error_position()` Diagnostic Dispatch Scenario

Native JSON diagnostics now include a bounded SQLite SQL function dispatch
boundary for `json_error_position(X)`. The updated
`examples/application-json-error-position-preflight.php` script can preflight
copied `wp_options` option values using the same function-name dispatch SQL
callers expect, including JSON5 text, malformed copied text, cast text BLOBs,
JSONB blobs, superficial-only JSONB blobs, and SQL NULL input without
requiring the SQLite extension.

## JSON Constructor Dispatch Scenario

Native JSON constructors now include a bounded SQLite SQL function dispatch
boundary for `json_array()`, `json_object()`, `jsonb_array()`, and
`jsonb_object()`. The updated
`examples/application-json-constructor-option-diagnostics.php` script can
preflight copied `wp_options` migration diagnostics with text JSON or decoded
JSONB review output, preserving SQLite's distinction between ordinary SQL
values, JSON subtype fragments, JSONB BLOB fragments, raw BLOB rejection, odd
`json_object()` arity, and invalid constructor function names.

Status delta 2026-05-25 isolated micro-slice: added constructor SQL-dispatch
helpers, focused tests, and the Application smoke update. Focused verification is
recorded in `lane-status.json` after local checks. Blocker: no hydrated
upstream cache exists in this isolated worktree, so no fresh SQLite testfixture
run was performed; this slice reuses prior `json101.test` and `subtype1.test`
constructor evidence. Next task: integrator acceptance, then one additional
bounded libsqlite behavior slice with its own evidence. Dependency closure: no
new support component is needed; the slice reuses existing lane-local JSON
constructor, JSON subtype, JSONB, and BLOB support and counts no shared
support-library progress.

## `json()`/`jsonb()` Canonical Dispatch Scenario

Native JSON canonicalization now includes a bounded SQLite SQL result-type
boundary for `json()` and `jsonb()`. The updated
`examples/application-json-canonical-option-preflight.php` script can preflight
copied `wp_options` JSON option values with canonical text JSON or decoded
JSONB review output, preserving SQLite's distinction between text JSON
results and JSONB blob results for strict JSON, JSON5, cast text BLOBs, JSONB
BLOBs, SQL NULL values, malformed JSON, and raw BLOB rejection without
requiring the SQLite extension.

Status delta 2026-05-25 isolated micro-slice: added canonical json/jsonb
SQL-dispatch helper, focused tests, and the Application smoke update. Latest
priority-refill 2026-05-25T16:13Z keeps that accepted behavior and adds
case-insensitive `JSON`/`JSONB` lookup plus one-argument SQL vector dispatch;
the Application smoke now exercises uppercase argument-vector dispatch for
copied option values. Focused verification is recorded in `lane-status.json`
after local checks. Blocker: no hydrated upstream cache exists in this
isolated worktree, so no fresh SQLite testfixture run was performed; this
slice reuses prior `json101.test`, `json501.test`, `json107.test`, and
`jsonb01.test` canonicalization evidence. Next task: integrator acceptance,
then one additional bounded libsqlite behavior slice with its own evidence.
Dependency closure: no new support component is needed; the slice reuses
existing lane-local JSON canonicalizer, JSON5 parser, JSONB, and BLOB support
and counts no shared support-library progress.

## JSON Table Rowid Alias Residual Scenario

Native JSON table planning now accepts SQLite's `rowid`, `_rowid_`, and `oid`
aliases for bounded `json_each()` and `json_tree()` residual predicates and
`ORDER BY` paging after hidden `json` and `root` constraints are planned. The
aliases map to the existing JSON table `id` column, preserving accepted visible
column filtering while allowing copied `wp_options` plugin-setting reviews to
page or resume deterministic JSON virtual-table scans by row identity.

Status delta 2026-05-26 isolated json-table/window slice: added alias mapping
in `SQLiteJsonTablePlan`, 36 focused assertions for `BETWEEN`, `IN`, `NOT IN`,
and descending `ORDER BY` over rowid aliases, plus updated
`application-json-each-option-settings.php` smoke output. Dependency closure: no
new support component is needed; the slice reuses lane-local JSON table rows,
hidden-column planning, and existing SQLite scalar comparison helpers.

## Focused Native Mapping: `json_each()`/`json_tree()` Hidden Columns

Date: 2026-05-25

This isolated micro-slice maps the bounded SQLite JSON table-valued hidden-column boundary for `json_each(X[,P])` and `json_tree(X[,P])`. Native row arrays now include the hidden `json` column as the original text/JSONB argument and the hidden `root` column as the effective root path used for the scan, while preserving the accepted visible `key`, `value`, `type`, `atom`, `id`, `parent`, `fullkey`, and `path` columns.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated `.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was started. This slice reuses prior focused JSON1/JSONB table-valued evidence for the same upstream behavior cluster:

```sh
json101.test json102.test json501.test json107.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run: 1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonEach.php
php -l lanes/libsqlite/src/SQLiteJsonTree.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-tree-option-settings.php
php lanes/libsqlite/examples/application-json-tree-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed, the Application example reported recursive root/plugin/rules rows with hidden `json`/`root` summaries, focused PHP passed 1 selected test file, 2116 assertions, and 0 failures, and final diff/json checks are recorded in `lane-status.json`. This worker did not start the root aggregate harness because root verification was not assigned.

Dependency closure: no new support component is needed. The slice reuses existing lane-local JSON path, JSON5, JSONB, BLOB, canonical encoding, and SQL value typing support; it counts no shared support-library progress.

## Focused Native Mapping: `json_each()` Case-Insensitive SQL Dispatch

Date: 2026-05-25

This isolated micro-slice updates the local wp_options `json_each()` expansion smoke to exercise uppercase `JSON_EACH` SQL dispatch. That keeps plugin settings review paths aligned with SQLite's case-insensitive function-name behavior while preserving the accepted strict JSON, JSON5 text, JSONB blob, SQL NULL, hidden `json`/`root`, and invalid-function coverage.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated `.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was started. This slice reuses prior focused JSON1 table-valued evidence for the same upstream behavior cluster:

```sh
json101.test json102.test json501.test json107.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run: 1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonEach.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-each-option-settings.php
php lanes/libsqlite/examples/application-json-each-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: recorded in `lane-status.json` after focused verification. Root aggregate harness was not assigned for this isolated micro-slice.

## Focused Native Mapping: `json_tree()` Quoted Selected-Root Labels

This isolated micro-slice updates the copied `wp_options` JSON tree smoke for
plugin settings whose option values contain object labels with punctuation.
`json_tree(X, '$.plugin."dotted.key"')` and
`json_tree(X, '$.plugin."bracket[0]"."nested.label"')` now report selected-root
rows with the decoded object label in `key`, the parent path in `path`, and the
caller root preserved in the hidden `root` column. That keeps local import
preflight output aligned with SQLite JSON table-valued row shape for plugin
configuration keys that are not valid bare path labels.

Focused local verification:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTree.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-tree-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-tree-option-settings.php
```

Result: syntax checks passed, focused PHP passed 1 selected file with 2391
assertions and 0 failures, and the Application smoke reported quoted-root rows
for JSONB settings. Root aggregate harness was not assigned for this isolated
micro-slice.

Dependency closure: no new support component is needed; this reuses lane-local
JSON path decoding, JSON5 quoted-label decoding, JSONB decode, and existing
`json_tree()` row assembly.

Dependency closure: no new support component is needed. The slice reuses existing lane-local JSON path, JSON5, JSONB, BLOB, canonical encoding, and table-valued row support; it counts no shared support-library progress.

## Focused Native Mapping: Table-Valued JSON Case-Insensitive Dispatch

Date: 2026-05-25

This isolated micro-slice updates the local wp_options recursive JSON expansion smoke to exercise uppercase `JSON_TREE` SQL dispatch and tightens both table-valued dispatch helpers to explicit case-insensitive comparison. That keeps plugin settings review paths aligned with SQLite's case-insensitive function-name behavior while preserving accepted `json_each()`/`json_tree()` rows, hidden `json`/`root` values, strict JSON, JSON5 text, JSONB blob, SQL NULL, and invalid-function coverage.

Focused verification is recorded in `lane-status.json`. Dependency closure: no new support component is needed; this reuses existing lane-local JSON path, JSON5, JSONB, BLOB, canonical encoding, and table-valued row support and counts no shared support-library progress.

## Focused Native Mapping: Table-Valued JSON Argument-Vector Dispatch

Date: 2026-05-25

This isolated micro-slice updates the local wp_options `json_each()` and
`json_tree()` smokes to exercise uppercase SQL function names through
one-or-two argument vectors. The smokes now dispatch `$.plugin` and
`$.plugin.rules` via the SQL-style vector helpers while preserving accepted
strict JSON, SQLite JSON5, JSONB blob, SQL NULL, hidden `json`/`root`, and
case-insensitive function-name coverage.

Focused verification is recorded in `lane-status.json`. Dependency closure:
no new support component is needed; this reuses existing lane-local JSON path,
JSON5, JSONB, BLOB, canonical encoding, and table-valued row support and
counts no shared support-library progress.

## `json_error_position()` Argument-Vector Dispatch Scenario

Native JSON diagnostics now include the one-argument SQL-style vector dispatch
boundary for `json_error_position(X)`. The updated
`examples/application-json-error-position-preflight.php` script exercises
uppercase `JSON_ERROR_POSITION` dispatch over copied `wp_options` option
values, including JSON5 text, malformed copied settings, cast text BLOBs,
JSONB blobs, superficial-only JSONB blobs, and SQL NULL input without
requiring the SQLite extension.

## `json_valid()` Argument-Vector Dispatch Scenario

Native JSON validity now includes the one-or-two argument SQL-style vector
dispatch boundary for `json_valid(X[,FLAGS])`. The updated
`examples/application-json-validity-preflight.php` script exercises uppercase
`JSON_VALID` dispatch over copied `wp_options` option values, including strict
JSON text, JSON5 text, cast text BLOBs, copied JSONB blobs, SQL NULL input,
nullable `FLAGS` rejection, and combined JSON5-or-superficial-JSONB flag
checks without requiring the SQLite extension.

## JSON Constructor Argument-Vector Dispatch Scenario

Native JSON constructor diagnostics now include SQL-style vector dispatch for
`json_array()`, `jsonb_array()`, `json_object()`, and `jsonb_object()`. The
updated `examples/application-json-constructor-option-diagnostics.php` script
exercises uppercase `JSON_ARRAY`, `JSON_OBJECT`, `JSONB_ARRAY`, and
`JSONB_OBJECT` dispatch over copied `wp_options` migration diagnostics,
including JSON subtype passthrough, JSONB queue blobs, SQL NULL array members,
JSONB result decoding, and raw BLOB rejection without requiring the SQLite
extension.
## WAL Frame Option Diagnostics

Native WAL inspection now includes a bounded read-only frame parser for
Application recovery/import tooling. The new
`examples/application-wal-option-frame-diagnostics.php` script builds a WAL
fixture with a stale base `wp_options` page plus committed schema and option
page images, overlays committed WAL frames onto the base database image, and
reads pending `siteurl`/`blogname` options without requiring the SQLite
extension. Uncommitted tail frames are intentionally ignored.

This is intentionally not a full checkpoint writer or recovery engine yet. WAL
WAL-index/shared-memory state, filesystem checkpointing, rollback journals, and
savepoint behavior remain separate slices.

## LIKE/GLOB Option-Name Matching Scenario

Native Application option diagnostics now include SQLite-style pattern matching
for decoded `wp_options.option_name` text. The new
`examples/application-option-name-like-glob.php` smoke exercises escaped
`LIKE '\_transient\_%'` matching with SQLite's default ASCII-only case folding
and a case-sensitive `GLOB '_Transient_[A-Z][A-Z][A-Z]'` pattern without
requiring the SQLite extension.

This is intentionally a bounded decoded-row helper, not a full SQL WHERE
executor. General SQL LIKE planning, configurable `PRAGMA case_sensitive_like`,
regexp extension callbacks, and broader SQL expression dispatch remain separate
slices.

## WAL Checksum Option Diagnostics

Native WAL inspection now validates WAL header and frame checksums when a
caller requests strict parsing. The
`examples/application-wal-option-frame-diagnostics.php` smoke builds a checksummed
WAL fixture, parses it with checksum validation enabled, extracts page images
through the last commit frame, and reads the pending `siteurl` option without
requiring the SQLite extension.

## WAL Checkpoint Image Overlay Scenario

Native WAL inspection now includes a read-only checkpoint-style image overlay
for import and recovery previews. The
`examples/application-wal-option-frame-diagnostics.php` smoke starts with a stale
base database image, overlays committed WAL frames through the last commit
frame, ignores an uncommitted tail frame, and reads the committed `siteurl` and
`blogname` rows without requiring the SQLite extension.

## Rollback Journal Option Diagnostics

Native rollback-journal inspection now includes a read-only recovery preview
for copied Application SQLite databases. The new
`examples/application-rollback-journal-option-diagnostics.php` smoke starts with
a dirty `wp_options` database image, parses a checksummed rollback journal,
restores the pre-transaction option page image, and reads the recovered
`siteurl` row without requiring the SQLite extension.

This is intentionally not a full pager recovery engine yet. Master-journal
handling, multi-sector journal edge cases, journal file writing, savepoints,
and transaction orchestration remain separate slices.

## Savepoint Option Import Diagnostics

Native transaction diagnostics now include bounded SQLite savepoint state
tracking for copied Application database imports. The new
`examples/application-savepoint-option-import-diagnostics.php` smoke simulates a
`wp_options` import transaction, records dirty database pages under nested
savepoints, rolls back a failed option-row savepoint, releases the surviving
plugin-settings savepoint, and reports the remaining pending page numbers
without requiring the SQLite extension.

This is intentionally not a full pager transaction engine yet. WAL-index
shared-memory state, master-journal coordination, durable journal writing, and
general SQL execution remain separate slices.

## JSON Aggregate FILTER Option Summary Scenario

Native JSON aggregate summaries now include bounded SQLite aggregate
`FILTER` behavior for copied `wp_options` rows. The
`examples/application-json-aggregate-option-summary.php` smoke now reports
autoload-filtered `json_group_array()` results and autoload-only
`json_group_object()` maps, including text and JSONB aggregate dispatch, so
plugin/theme option imports can preflight filtered summaries without requiring
the SQLite extension.

This is intentionally a row-level aggregate helper, not a full SQL executor.
Window frames, HAVING/GROUP BY planner integration, and a general expression
VM remain separate slices.

## Commented Schema Autoindex Review Scenario

Native schema inspection now ignores SQL comments while inferring automatic
`UNIQUE` and non-rowid `PRIMARY KEY` index metadata from copied
`sqlite_schema.sql` rows. The new
`examples/application-commented-schema-autoindex.php` smoke checks a commented
`wp_options` schema row with inline `--` comments, block comments, quoted
string literals that contain comment-looking text, and a commented
`WITHOUT ROWID` table option.

This is intentionally a bounded schema metadata parser improvement, not a full
SQL parser. Trigger bodies, generated-column expression planning, view
expansion, and general DDL execution remain separate slices.
## JSON Table Hidden Constraint Planning Scenario

Local-only Application option import tooling can now preflight `json_each`/`json_tree` scans using the same hidden-column shape SQLite exposes for table-valued JSON functions. `SQLiteJsonTablePlan` maps hidden `json = option_value` and `root = '$.plugin.rules'` constraints into a two-argument table-valued call, reports which constraints can be omitted by the virtual table, leaves visible-column filters as residual predicates, and returns planned rows through the accepted native `json_each`/`json_tree` helpers.

The `application-json-each-option-settings.php` smoke now reports `plannedRulesRows` and a normalized planner record for strict JSON text, JSON5 text, JSONB blobs, and SQL NULL option values. This keeps copied `wp_options` plugin-settings expansion deterministic on hosts where the SQLite extension is unavailable while staying bounded to hidden `json`/`root` equality planning.

Status delta 2026-05-26 isolated json-table/window slice: added `SQLiteJsonTablePlan`, focused native assertions for usable/unusable hidden constraints, SQL NULL empty-row execution, residual predicates, invalid function/json/root constraints, and a Application smoke update. Full virtual-table cursor lifecycle, join-order costing, visible-column pushdown, and broader planner integration remain separate follow-up work.

Dependency closure: no new support component is needed; this reuses lane-local JSON table helpers and path validation.

## Composite Index Parent Pointer Repair Scenario

Native `wp_options` replacement diagnostics now expose right-most pointer
repair when a delete-triggered composite-index leaf merge also merges adjacent
non-root index parents. The
`examples/application-index-parent-merge-option-replacement-plan.php` smoke
reports `index-interior-rightmost-pointer-update` on the surviving parent
beside root divider removal, parent divider insertion, leaf-entry merges, and
freelist pages, so local repair tooling can audit that the merged interior
parent still points at the correct right-most child without requiring the
SQLite extension.

Status delta 2026-05-26 isolated btree-delete/rebalance slice: updated
`SQLiteDatabase::btreeRebalanceActionsForPageImages()` to report interior
right-most pointer changes for index/table interior pages, extended the
focused multi-child composite-index parent merge assertion, and reused the
existing Application smoke output. Full row deletion, table-leaf delete/merge,
and auto-vacuum pointer-map cleanup after arbitrary delete remain separate
follow-up work.

Dependency closure: no new support component is needed; this reuses lane-local
B-tree page headers, replacement planning, index page assembly, freelist
mutation, and Application fixture helpers.

The parent-merge smoke now also exposes `before_left_children` and
`after_left_children` on interior divider insert/removal rebalance actions.
For the copied `wp_options` composite-index scenario, this reports root child
slots changing from `[4,8]` to `[4]` and the surviving lower parent changing
from `[5,6]` to `[5,6,9]`, making the delete-triggered parent merge auditable
without manually parsing before/after page images.

Status delta 2026-05-26 isolated btree-delete/rebalance slice: updated
`SQLiteDatabase::btreeRebalanceActionsForPageImages()` to include interior
left-child pointer lists on cell-delta actions, extended the focused
multi-child composite-index parent merge assertion, and reused the existing
Application smoke output. Full row deletion, table-leaf delete/merge, and
auto-vacuum pointer-map cleanup after arbitrary delete remain separate
follow-up work.

Dependency closure: no new support component is needed; this reuses lane-local
B-tree page headers, index cell parsing, replacement planning, page-image
overlays, freelist mutation, and Application fixture helpers.

The same rebalance action stream now includes page free-space accounting:
cell-delta actions report `before_free_space_bytes`,
`after_free_space_bytes`, and `delta_free_space_bytes`, while pages moved to
the freelist report their last b-tree `before_free_space_bytes`. For copied
`wp_options` composite-index repairs this makes underfill/merge capacity
auditable from the native plan output, rather than requiring a separate manual
freeblock parse of every before/after page image.

Status delta 2026-05-26 isolated btree-delete/rebalance slice: updated
`SQLiteDatabase::btreeRebalanceActionsForPageImages()` to reuse lane-local
B-tree free-space accounting for rebalance diagnostics, extended the focused
multi-child composite-index parent merge assertion, and reused the existing
Application smoke output. Full row deletion, table-leaf delete/merge,
auto-vacuum pointer-map cleanup after arbitrary delete, and actual SQL DELETE
statement execution remain separate follow-up work.

Dependency closure: no new support component is needed; this reuses lane-local
B-tree page headers, freeblock/free-space accounting, replacement planning,
page-image overlays, freelist mutation, and Application fixture helpers.

## Partial IN-List Option Lookup Scenario

Native `wp_options` name-list lookup planning now recognizes that a partial
index such as `WHERE option_name IN ('siteurl','home','blogname')` can satisfy
a narrower copied-option query such as `option_name IN ('home','siteurl')`.
The `examples/application-options-by-name-list.php` smoke documents this planner
behavior in its JSON output, so Application import or migration tools can explain
why a partial option-name index is usable for a bounded subset of core option
names without requiring the SQLite extension.

Status delta 2026-05-26 isolated planner slice: updated
`SQLiteIndexPredicate::isImpliedByInListLookup()` to compare covered non-null
lookup values against partial IN-list predicate values, refreshed the existing
exact-list assertion to the subset behavior, added focused `wp_options`
coverage for subset, single-value, null-containing, and uncovered lists, and
updated the options-by-name smoke output. General SQL WHERE normalization,
costing, join order, expression rewrite, and virtual-table planner behavior
remain separate follow-up work.

Dependency closure: no new support component is needed; this reuses lane-local
schema parsing, partial-index predicate metadata, index b-tree traversal, and
scalar comparison semantics.

## Index Leaf Delete Freeblock Scenario

Native `wp_options` index maintenance diagnostics now include a page-local
secondary-index delete primitive. The
`examples/application-delete-option-index-leaf-freeblock.php` smoke builds a
minimal `option_name` index leaf, deletes an obsolete `_transient_cache` record,
reports the remaining index records, exposes the reusable freeblock created by
the deleted cell, and shows the secure-deleted payload bytes.

Status delta 2026-05-26 isolated planner/WAL/B-tree closure slice: added
`SQLiteIndexLeafPage::deleteCellByRecordValues()`, focused index-leaf
freeblock/coalescing/secure-delete assertions, and a Application-visible smoke
for local option-index repair tooling. Full SQL `DELETE`, table-row deletion,
arbitrary secondary-index maintenance, sibling merge/redistribution after
delete, and auto-vacuum pointer-map cleanup remain separate follow-up work.

Dependency closure: no new support component is needed; this reuses lane-local
index cell parsing, record decoding, B-tree page headers, freeblock parsing,
and Application fixture helpers.

## JSON Option Settings IN-List Filtering

Native `wp_options` JSON setting diagnostics now include residual `IN` and
`NOT IN` predicates for JSON table rows after hidden `json` and `root`
planning. The `examples/application-json-each-option-settings.php` smoke reports
`filteredRuleInRows` for strict JSON, JSON5 text, and JSONB inputs, so local
plugin-setting import tools can explain bounded `WHERE key IN (...)` or
`WHERE value IN (...)` filters without requiring the SQLite extension.

Status delta 2026-05-26 isolated sql-exec/planner slice: updated
`SQLiteJsonTablePlan::filteredRows()` to execute residual `IN` and `NOT IN`
comparisons with SQLite NULL behavior, added focused JSON table assertions, and
extended the Application option-settings smoke output. Full virtual-table cursor
lifecycle, join-order costing, and full SQL WHERE execution remain separate
follow-up work.

Dependency closure: no new support component is needed; this reuses lane-local
JSON table row assembly, hidden-column planning, JSONB wrappers, and scalar
residual comparison semantics.

## Savepoint Rollback Page Preview Scenario

Native `wp_options` import diagnostics now report the exact dirty database
pages that a `ROLLBACK TO` savepoint would revert before mutating savepoint
state. The `examples/application-savepoint-option-import-diagnostics.php` smoke
now includes rollback previews for the plugin-settings savepoint and the nested
single-option savepoint, then performs the accepted rollback/release sequence.

Status delta 2026-05-26 isolated WAL/rollback/savepoint slice: added
`SQLiteSavepointStack::rollbackToPageNumbers()`, focused assertions for named,
nested, outer, post-rollback, and missing savepoint cases, and refreshed the
Application-visible savepoint import smoke. Full pager journal writing,
master-journal coordination, WAL-index shared-memory state, and general SQL
transaction execution remain separate follow-up work.

Dependency closure: no new support component is needed; this reuses lane-local
transaction frame and page-number bookkeeping.

## Table Leaf Delete Freeblock Scenario

Native `wp_options` table maintenance diagnostics now include a page-local
table-row delete primitive. The
`examples/application-delete-option-table-leaf-freeblock.php` smoke builds a
minimal table leaf, deletes an obsolete `_transient_cache` row by rowid,
reports the remaining rowids, exposes the reusable freeblock created by the
deleted cell, and shows the secure-deleted payload bytes.

Status delta 2026-05-26 isolated B-tree delete/rebalance slice: added
`SQLiteTableLeafPage::deleteCellByRowId()`, focused table-leaf
freeblock/coalescing/secure-delete assertions, and a Application-visible smoke
for local option-table repair tooling. Full SQL `DELETE`, paired arbitrary
secondary-index maintenance, sibling merge/redistribution after delete, and
auto-vacuum pointer-map cleanup remain separate follow-up work.

Dependency closure: no new support component is needed; this reuses lane-local
table leaf cell parsing, B-tree page headers, freeblock parsing, and Application
fixture helpers.

## JSON Aggregate DISTINCT ORDER BY Option Summary Scenario

Native `wp_options` import summaries now include the combined SQLite
`json_group_array(DISTINCT X ORDER BY option_name)` aggregate boundary. The
`examples/application-json-aggregate-option-summary.php` smoke records both text
and JSONB decoded results for copied option values, preserving accepted JSON
subtype fragments, JSONB blobs, SQL NULLs, and stable ordered de-duplication.

Status delta 2026-05-26 isolated SQL execution/planner slice: added
`SQLiteJsonAggregate::jsonGroupArrayDistinctOrderBy()`, JSONB dispatch,
`SQLiteJsonAggregateState::stepArrayDistinctOrderBy()` and
`finalizeDistinctOrderedArray()`, focused aggregate assertions, and a
Application-visible aggregate summary smoke. Full SELECT aggregate scheduling,
window frames, multi-term collations, and full SQL parser integration remain
separate follow-up work.

Dependency closure: no new support component is needed; this reuses lane-local
JSON aggregate coercion, JSON subtype handling, JSONB encode/decode, and
ordered row scheduling helpers.

## JSON Aggregate ROWS Window Option Summary Scenario

Native JSON aggregate summaries now include bounded ROWS-style window frames
for `json_group_array()` and `jsonb_group_array()`. The
`examples/application-json-aggregate-option-summary.php` smoke reports rolling
current-and-previous option-value arrays in input order and after
`ORDER BY option_name`, plus decoded JSONB frame output. This gives copied
`wp_options` review tooling a local-only way to preview adjacent option
settings during import without requiring the SQLite extension.

Status delta 2026-05-26 isolated json-table/window slice: added
`SQLiteJsonAggregate::jsonGroupArrayWindow()`,
`jsonGroupArrayOrderByWindow()`, text/JSONB SQL dispatch helpers,
`SQLiteJsonAggregateState` window step/final helpers, focused aggregate
assertions, and the Application-visible smoke output. This is intentionally a
bounded ROWS frame helper, not full SELECT/window parser integration; RANGE,
GROUPS, EXCLUDE, partition scheduling, inverse aggregate optimization, and
multi-term collation-aware ordering remain follow-up work.

Dependency closure: no new shared support component is needed; the slice
reuses lane-local JSON aggregate coercion, JSON subtype handling, JSONB
encode/decode, SQL-style scalar ordering, and aggregate state helpers.

## WAL Reader Page Map Option Diagnostics

Native `wp_options` WAL diagnostics now report page-level provenance for the
reader-visible database image before a checkpoint writer exists. The
`examples/application-wal-option-frame-diagnostics.php` smoke includes a
`readerPageMap` and `readerOptionPage` summary showing that the option page is
served from the committed WAL frame and that a later uncommitted tail frame is
ignored.

Status delta 2026-05-26 isolated WAL/rollback/savepoint slice: added
`SQLiteWal::readerPageImage()` and `readerPageMap()`, focused assertions for
base-database pages, WAL pages, repeated page-number overwrite selection,
uncommitted tail exclusion, committed-size bounds, and aligned database image
validation, plus a refreshed Application-visible WAL smoke. WAL-index
shared-memory read marks, durable checkpoint writing, rollback journal writing,
and master-journal coordination remain separate follow-up work.

Dependency closure: no new support component is needed; this reuses lane-local
WAL frame parsing, committed transaction summaries, SQLite header parsing, and
Application page fixture helpers.

## Ordered Option Result Window Scenario

Native `wp_options` imports now include a bounded result-ordering helper for
local SQL-style scans. The `examples/application-options-order-limit.php` smoke
builds a small copied options database, orders decoded rows by `option_name`,
then applies `LIMIT 2 OFFSET 1` after sorting so Application migration previews
can page deterministic option lists without the SQLite extension.

Status delta 2026-05-26 isolated SQL execution/planner slice: added
`SQLiteDatabase::optionRowsOrdered()`, focused assertions for
`ORDER BY option_name`, descending `autoload`, descending `rowid`, limit zero,
offset, invalid column, and invalid bounds, plus a Application-visible smoke.
Full SELECT parsing, multi-column ORDER BY, collation-aware ordering, and
index-backed sort elision remain separate follow-up work.

Dependency closure: no new support component is needed; this reuses lane-local
table traversal, record decoding, Application option mapping, and scalar
comparison helpers.

## Core Scalar Option Default Scenario

Native SQL execution helpers now include a bounded core scalar dispatch boundary
for `abs()`, `round()`, `typeof()`, `quote()`, `coalesce()`, `ifnull()`, and
`nullif()`. The `examples/application-core-scalar-option-default.php` smoke
reports local-only function arguments and results for copied `wp_options` values,
including a `--self-test` path that resolves `coalesce(NULL, 'published')`.

Status delta 2026-05-26 isolated SQL execution/planner scalar slice: added
`SQLiteCoreScalarFunction::sqlFunctionArguments()` with case-insensitive
function lookup, SQL NULL propagation/defaulting, SQLite-style text numeric
coercion for numeric functions, half-away rounding, storage-class labels, BLOB
and text literal quoting, and strict arity/type errors. This is intentionally a
scalar dispatch helper, not a full SELECT expression evaluator or VDBE opcode
implementation.

Dependency closure: no new shared support component is needed. This reuses
lane-local scalar coercion and `SQLiteBlobValue` without activating shared
support-library work.

## Bulk Leaf Delete Freeblock Scenario

Native `wp_options` repair tooling can now delete adjacent obsolete rows or
index records from a single B-tree leaf in one bounded operation. The
`examples/application-delete-option-table-leaf-freeblock.php` smoke bulk deletes
`_transient_cache` and `_transient_timeout_cache`, reports remaining rowids
`[1,4]`, exposes one coalesced reusable freeblock, and verifies secure-delete
clearing across the coalesced payload.

Status delta 2026-05-26 isolated B-tree delete/rebalance slice: added
`SQLiteTableLeafPage::deleteCellsByRowIds()` and
`SQLiteIndexLeafPage::deleteCellsByRecordValues()`, focused table/index bulk
delete assertions, and tightened secure-delete clearing so stale interior
freeblock headers are wiped when adjacent deleted cells coalesce. Full SQL
DELETE dispatch, paired secondary-index maintenance, sibling
merge/redistribution after delete, and auto-vacuum pointer-map cleanup remain
separate follow-up work.

Dependency closure: no new shared support component is needed; this reuses
lane-local B-tree page headers, table/index cell parsing, record decoding,
freeblock accounting, and Application fixture helpers.

## Rollback Journal Recovery Plan Scenario

Native `wp_options` rollback diagnostics can now preview recovery before a
filesystem writer exists. The
`examples/application-rollback-journal-option-diagnostics.php` smoke reports a
rollback recovery plan that restores the first page from the journal, skips a
journal page beyond the initial database size, and truncates the dirty image
back to the original page count.

Status delta 2026-05-26 isolated WAL/rollback/savepoint slice: added
`SQLiteRollbackJournal::recoveryPlan()` and `rollbackDatabaseImage()` with
focused assertions for restored pages, skipped pages, original-size
truncation, checksum-validated journal input, and misaligned dirty image
rejection. Hot-journal master-journal coordination, durable journal/WAL file
writes, WAL-index shared-memory state, and multi-sector edge handling beyond
the accepted rollback preview remain separate follow-up work.

Dependency closure: no new shared support component is needed; this reuses
lane-local rollback journal parsing, checksum validation, SQLite page headers,
and Application fixture helpers.

## WAL Checkpoint Plan Scenario

Native `wp_options` WAL diagnostics can now preview checkpoint provenance before a filesystem checkpoint writer exists. The `examples/application-wal-option-frame-diagnostics.php` smoke reports a checkpoint plan that applies committed frames, marks superseded frames, ignores uncommitted tail frames, and excludes frames beyond the committed database size.

Status delta 2026-05-26 isolated WAL slice: added `SQLiteWal::checkpointPlan()` with focused assertions for applied, superseded, beyond-size, uncommitted-tail, empty-WAL, and malformed base-image cases. Filesystem checkpoint writes, WAL-index/shared-memory state, and durability orchestration remain separate follow-up work.

Dependency closure: no new shared support component is needed; this reuses lane-local WAL parsing, checksum validation, checkpoint overlay, SQLite header parsing, and Application fixture helpers.

## Core Scalar Min/Max And Text Helper Scenario

Native SQL execution helpers now include the next bounded core scalar dispatch
cluster needed by local Application option repair and expression planning:
`min()`, `max()`, `lower()`, `upper()`, and `length()`. The
`examples/application-core-scalar-option-default.php` smoke reports these
function results through the same local-only wp_options scalar preflight
surface used for `abs()`, `round()`, `typeof()`, `quote()`, `coalesce()`,
`ifnull()`, and `nullif()`.

Status delta 2026-05-26 isolated dependency-suite scalar slice: added
SQLite-style storage-class ordering for scalar `min()`/`max()`, SQL NULL
propagation across multi-argument min/max calls, BLOB byte comparison,
ASCII-only `lower()`/`upper()` behavior, and `length()` over UTF-8 text
characters or BLOB bytes. This is intentionally a scalar dispatch helper, not a
full SELECT expression evaluator or collation-aware comparison engine.

Dependency closure: no new shared support component is needed; this reuses
lane-local scalar coercion, SQLiteBlobValue, and existing expression-semantics
helpers.

## JSON Table MATCH Residual Scenario

Native JSON table planning now supports visible-column `MATCH` and `NOT MATCH`
residual filters after hidden `json`/`root` constraint planning. The Application
JSON settings smoke expands copied `wp_options` plugin rules through
`json_each()` and applies a caller-supplied MATCH callback to mirror SQLite's
application-defined operator surface without requiring an FTS extension.

Status delta 2026-05-26 isolated json-table/window slice: added
`SQLiteJsonTablePlan` residual `MATCH`/`NOT MATCH` support with focused
assertions for callback payload validation, SQL NULL non-matches, and
Application option-value smoke output.

Dependency closure: no new shared support component is needed; this reuses
lane-local JSON table planning, JSON path/JSONB decoding, and caller-supplied
application callbacks.

## JSON Table Numeric Equality Residual Scenario

Native JSON table planning now applies SQLite numeric equality semantics to
visible `json_each()` / `json_tree()` residual filters after hidden `json` and
`root` constraints are planned. This lets local Application import tooling match
JSON integer option atoms against real-valued SQL constants for `=`, `!=`,
`IN`, and `IS NOT DISTINCT FROM` without weakening text or SQL NULL handling.

Status delta 2026-05-26 isolated json-table/window slice: updated
`SQLiteJsonTablePlan` residual equality helpers, focused assertions, and the
`application-json-each-option-settings.php` smoke to report numeric equality
filter rows for copied plugin settings.

Dependency closure: no new shared support component is needed; this reuses
lane-local JSON table planning, JSON path/JSONB decoding, and existing SQL
numeric comparison behavior.

## JSON Table Null-Safe Residual Predicates

Native `json_each()` / `json_tree()` residual filtering now accepts SQLite's
`IS DISTINCT FROM` and `IS NOT DISTINCT FROM` spellings in addition to `IS` and
`IS NOT`. The Application JSON option settings smoke reports container rows where
`atom IS NOT DISTINCT FROM NULL` and scalar rows where
`atom IS DISTINCT FROM NULL`, which is useful when copied plugin settings mix
arrays/objects with scalar rule names during local import preflight.

Status delta 2026-05-26 isolated JSON table/window slice: added null-safe
residual predicate aliases to `SQLiteJsonTablePlan`, focused assertions for
container/scalar row separation, and smoke output/planner records in
`examples/application-json-each-option-settings.php`.

Dependency closure: no new shared support component is needed; this reuses
lane-local JSON table planning, JSON5/JSONB decoding, SQL NULL comparison, and
existing Application option JSON smoke coverage.

## JSON Table REGEXP Residual Scenario

Native JSON table planning now supports visible-column `REGEXP` and
`NOT REGEXP` residual filters after hidden `json`/`root` constraint planning.
The `examples/application-json-each-option-settings.php` smoke reports copied
`wp_options` plugin settings expanded through `json_each()` with strict JSON,
JSON5, JSONB, and SQL NULL inputs, then applies callback-backed REGEXP filters
to rule values and full JSON paths without requiring the SQLite extension.

Status delta 2026-05-26 isolated json-table/window slice: added bounded
`SQLiteJsonTablePlan` REGEXP residual comparison using an explicit
`pattern`/`regexp` payload and the lane-local SQLite REGEXP callback contract.

Dependency closure: no new shared support component is needed; this reuses
lane-local JSON table planning, JSON path/JSONB decoding, and existing REGEXP
callback validation.

## Core Random Scalar Scenario

Native SQL execution helpers now include `random()` and `randomblob()` in the
bounded core scalar dispatch surface used by local Application option repair and
expression planning. The `examples/application-core-scalar-option-default.php`
smoke reports local-only token and nonce byte diagnostics for copied
`wp_options` workflows, including SQLite zero-argument `random()` output,
`randomblob(N)` BLOB allocation, and the one-byte minimum for non-positive
randomblob lengths without requiring the SQLite extension.

Status delta 2026-05-26 isolated SQL execution/planner scalar slice: added
SQLite-style random/randomblob dispatch with focused assertions for signed
integer range, minimum-sentinel exclusion, BLOB byte counts, non-positive
length handling, and strict arity/type errors. This is intentionally a scalar
dispatch helper, not a full SELECT expression evaluator or VDBE projection
engine.

Dependency closure: no new shared support component is needed; this reuses
lane-local scalar coercion, SQLiteBlobValue, PHP CSPRNG primitives, and
existing expression-semantics helpers.

## NOCASE Indexed LIKE Prefix Option-Name Scenario

Default SQLite `LIKE` matching folds ASCII case unless `case_sensitive_like` is
enabled. Application recovery databases that keep a `COLLATE NOCASE` option-name
index can now use the leading literal `LIKE` prefix as a NOCASE index range,
then apply the existing residual LIKE matcher so mixed-case rows such as
`_Transient_API` and `_transient_feed` are both returned.

Status delta 2026-05-26 isolated encoding/collation slice: added
`SQLiteDatabase::optionRowsByIndexedNameLikePrefixRangeNoCase()`,
focused tests for escaped leading prefixes, limit handling, missing-prefix
errors, negative-limit errors, and mixed-case residual matches, and updated
`examples/application-option-name-like-glob.php` to report the indexed NOCASE
path when a matching index is present.

Dependency closure: no new support component is needed; this reuses lane-local
SQLite index metadata parsing, NOCASE comparison, LIKE prefix bound derivation,
and the accepted decoded-text LIKE matcher.

## JSON Table Residual NOT LIKE/NOT GLOB Scenario

Local-only Application option import tooling can now preflight negative pattern
filters on `json_each()` and `json_tree()` visible columns after hidden `json`
and `root` constraints are planned. `SQLiteJsonTablePlan::filteredRows()`
applies `NOT LIKE` and `NOT GLOB` residual predicates with the accepted SQLite
pattern matchers, while preserving SQL NULL non-match behavior and strict text
operand validation for copied plugin settings.

Status delta 2026-05-26 isolated json-table/window slice: added
`SQLiteJsonTablePlan` residual `NOT LIKE` and `NOT GLOB` support with focused
native assertions and updated the `application-json-each-option-settings.php`
smoke to report the negative-pattern filtered rows. This remains a bounded
residual-filter helper, not full virtual-table cursor lifecycle or SQL planner
pushdown.

Dependency closure: no new shared support component is needed; this reuses
lane-local JSON table planning, JSON path/table-valued helpers, and accepted
LIKE/GLOB scalar matching.

## Core Encoding Scalar Diagnostics Scenario

Native SQL execution helpers now include `hex()`, `unhex()`, `char()`,
`unicode()`, and `octet_length()` in the bounded core scalar dispatch surface
used by local Application option diagnostics. The
`examples/application-core-scalar-option-default.php` smoke reports local-only
byte/codepoint diagnostics for copied `wp_options` values, including BLOB hex
round trips, ignored separators in hex text, UTF-8 codepoints, and byte length
without requiring the SQLite extension.

Status delta 2026-05-26 isolated SQL execution/planner scalar slice: added
SQLite-style SQL NULL propagation, malformed `unhex()` NULL results, invalid
`char()` codepoint replacement, UTF-8 codepoint construction and inspection,
byte-length diagnostics, and strict arity/type errors. This is intentionally a
scalar dispatch helper, not a full SELECT expression evaluator or VDBE
projection engine.

Dependency closure: no new shared support component is needed; this reuses
lane-local scalar coercion, SQLiteBlobValue, UTF-8 helpers when available, and
existing expression-semantics helpers.

## Core Scalar Native UTF-8 Text Unit Scenario

Native SQL execution helpers no longer need mbstring to preserve SQLite UTF-8
TEXT character semantics for bounded scalar diagnostics. The
`examples/application-core-scalar-option-default.php` smoke now reports
`nativeUtf8TextUnits` for a mixed emoji/accent/CJK option value, proving
character `length()`, `substr()`, and `instr()` positions remain stable on
hosts without mbstring while BLOB diagnostics still use byte positions.

Status delta 2026-05-26 isolated dependency-suite scalar slice: updated
`SQLiteCoreScalarFunction` to split valid UTF-8 text with lane-local PCRE
helpers for `length()`, `substr()`/`substring()`, and `instr()`, added focused
multibyte assertions, and extended the Application scalar smoke. This stays a
bounded scalar helper, not a full SELECT expression evaluator.

Dependency closure: no new shared support component is needed; this removes a
hard mbstring dependency for UTF-8 text units by reusing lane-local scalar
coercion, PHP PCRE UTF-8 validation/splitting, and existing byte fallback
behavior.

## JSON Table BETWEEN Residual Scenario

Local-only Application option import tooling can now preflight SQL-style
`BETWEEN` filters while expanding copied JSON option values through
`json_each`/`json_tree`. The
`examples/application-json-each-option-settings.php` smoke reports priority
rows selected by a visible-column `atom BETWEEN 6 AND 7` residual after hidden
`json`/`root` planning, alongside the accepted type, LIKE/GLOB, IN-list, and
range residual diagnostics.

Status delta 2026-05-26 isolated json-table/window slice: added
`SQLiteJsonTablePlan` residual `BETWEEN` and `NOT BETWEEN` support with
focused assertions for numeric bounds, text bounds, SQL NULL non-matches,
invalid bound arity, and Application-visible planner output. This is a bounded
residual predicate helper, not full virtual-table cursor lifecycle or broader
SELECT planner integration.

Dependency closure: no new shared support component is needed; this reuses
lane-local JSON table planning, JSON row production, scalar residual ordering,
and Application fixture diagnostics.

## WAL Checkpoint Mode Plan Scenario

Native `wp_options` WAL diagnostics now include checkpoint-mode eligibility for
future repair/import tooling. The
`examples/application-wal-option-frame-diagnostics.php` smoke reports
reader-limited `PASSIVE` progress, `FULL` busy status when an active reader
blocks completion, and `RESTART`/`TRUNCATE` reset decisions while preserving
uncommitted WAL tail frames.

Status delta 2026-05-26 isolated WAL/rollback/savepoint slice: added
`SQLiteWal::checkpointModePlan()` with focused assertions for PASSIVE, FULL,
RESTART, and TRUNCATE modes, active reader frame limits, blocking-mode busy
reporting, empty-WAL handling, invalid modes, invalid reader frames, and
misaligned base-image rejection. This remains read-only diagnostic planning,
not a WAL-index shared-memory implementation, lock manager, or durable
filesystem checkpoint writer.

Dependency closure: no new shared support component is needed; this reuses
lane-local WAL parsing, checkpoint/reset planning, SQLite header parsing, and
Application fixture helpers.

## Core Scalar Substring Scenario

Native SQL execution helpers now include `substr()` and `substring()` in the
bounded core scalar dispatch surface used by local Application option repair and
expression planning. The `examples/application-core-scalar-option-default.php`
smoke reports local-only slicing of copied `wp_options` values, including
prefix/tail extraction, UTF-8 option text, and BLOB byte slices without
requiring the SQLite extension.

Status delta 2026-05-26 isolated SQL execution/planner scalar slice: added
SQLite-style substring start and length handling for SQL NULL propagation,
1-based starts, start zero, negative starts, negative lengths, UTF-8 text, BLOB
bytes, and strict arity/type errors. This is intentionally a scalar dispatch
helper, not a full SELECT expression evaluator or collation-aware expression
engine.

Dependency closure: no new shared support component is needed; this reuses
lane-local scalar coercion, SQLiteBlobValue, and existing expression-semantics
helpers.

## WAL Reset Plan Scenario

Native `wp_options` WAL diagnostics now include reset eligibility after a
checkpoint preview. The `examples/application-wal-option-frame-diagnostics.php`
smoke reports that the copied WAL must be preserved when an uncommitted tail
frame follows the last committed Application option update, while fully
checkpointed WAL input can be truncated or restarted by a future filesystem
writer.

Status delta 2026-05-26 isolated WAL slice: added
`SQLiteWal::resetPlan()` with focused assertions for committed WAL reset,
uncommitted-tail preservation, no-commit preservation, empty-WAL handling,
salt rollover, and malformed base-image rejection. This is intentionally a
diagnostic plan, not a durable checkpoint writer or WAL-index implementation.

Dependency closure: no new shared support component is needed; this reuses
lane-local WAL parsing, checkpoint planning, SQLite header parsing, and
Application fixture helpers.

## Savepoint Rollback/Release Plan Scenario

Native `wp_options` import diagnostics now preview nested savepoint effects
before mutating the transaction stack. The
`examples/application-savepoint-option-import-diagnostics.php` smoke reports a
`ROLLBACK TO plugin_settings` plan with discarded frame names and page numbers,
then reports `RELEASE plugin_settings` and outer transaction release plans with
the dirty pages that would merge upward or close the transaction.

Status delta 2026-05-26 isolated WAL/rollback/savepoint slice: added
`SQLiteSavepointStack::rollbackToPlan()` and `releasePlan()` with focused
assertions for nested rollback, nested release, outer transaction release, and
missing-savepoint errors. This is intentionally diagnostic state tracking, not
a durable pager transaction writer, rollback journal writer, WAL-index
implementation, or master-journal coordinator.

Dependency closure: no new shared support component is needed; this reuses
lane-local savepoint state tracking and Application fixture diagnostics.

## Savepoint Rollback Apply and Commit Plan Scenario

Native `wp_options` import diagnostics now include apply-and-report helpers for
`ROLLBACK TO` and `COMMIT`. The
`examples/application-savepoint-option-import-diagnostics.php` smoke reports a
`rollbackToWithPlan()` transition and a separate commit preview that aggregates
dirty page numbers, counts released savepoints, and confirms the transaction is
closed after commit.

Status delta 2026-05-26 isolated WAL/rollback/savepoint slice: added
`SQLiteSavepointStack::rollbackToWithPlan()`, `commitPlan()`, and
`commitWithPlan()` with 23 focused assertions for duplicate savepoint names,
rollback page retention, commit page aggregation, commit apply state, and empty
transaction errors. This remains diagnostic pager state tracking, not durable
journal/WAL file writing or master-journal coordination.

Dependency closure: no new shared support component is needed; this reuses
lane-local savepoint state tracking and Application fixture diagnostics.

## Core Text Scalar Cleanup Scenario

Native SQL execution helpers now include `trim()`, `ltrim()`, `rtrim()`,
`replace()`, and `instr()` in the bounded core scalar dispatch surface used by
local Application option repair and expression planning. The
`examples/application-core-scalar-option-default.php` smoke reports local-only
cleanup and matching of copied `wp_options` values, including explicit trim
character sets, option-name separator replacement, UTF-8 text positions, and
BLOB byte search positions without requiring the SQLite extension.

Status delta 2026-05-26 isolated SQL execution/planner scalar slice: added
SQLite-style SQL NULL propagation, default and explicit trim character sets,
empty replace-pattern no-op behavior, UTF-8 text-unit handling, BLOB byte
`instr()` positions, not-found zero results, and strict arity/type errors. This
is intentionally a scalar dispatch helper, not a full SELECT expression
evaluator or VDBE projection engine.

Dependency closure: no new shared support component is needed; this reuses
lane-local scalar coercion, SQLiteBlobValue, and existing expression-semantics
helpers.

## Focused Native Mapping: `json_each()`/`json_tree()` Hidden Columns

Date: 2026-05-25

This isolated micro-slice maps the bounded SQLite JSON table-valued hidden-column boundary for `json_each(X[,P])` and `json_tree(X[,P])`. Native row arrays now include the hidden `json` column as the original text/JSONB argument and the hidden `root` column as the effective root path used for the scan, while preserving the accepted visible `key`, `value`, `type`, `atom`, `id`, `parent`, `fullkey`, and `path` columns.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated `.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was started. This slice reuses prior focused JSON1/JSONB table-valued evidence for the same upstream behavior cluster:

```sh
json101.test json102.test json501.test json107.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run: 1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonEach.php
php -l lanes/libsqlite/src/SQLiteJsonTree.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-tree-option-settings.php
php lanes/libsqlite/examples/application-json-tree-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed, the Application example reported recursive root/plugin/rules rows with hidden `json`/`root` summaries, focused PHP passed 1 selected test file, 2116 assertions, and 0 failures, and final diff/json checks are recorded in `lane-status.json`. This worker did not start the root aggregate harness because root verification was not assigned.

Dependency closure: no new support component is needed. The slice reuses existing lane-local JSON path, JSON5, JSONB, BLOB, canonical encoding, and SQL value typing support; it counts no shared support-library progress.

## Core Concat Scalar Scenario

Native SQL execution helpers now include `concat()` and `concat_ws()` in the
bounded core scalar dispatch surface used by local Application option repair and
expression planning. The `examples/application-core-scalar-option-default.php`
smoke reports local-only option-key assembly for copied `wp_options` values,
including SQLite NULL-as-empty concat behavior, concat_ws NULL separator
propagation, skipped NULL fields, and BLOB byte text coercion without requiring
the SQLite extension.

Status delta 2026-05-26 isolated dependency-suite scalar slice: added
SQLite-style concat/concat_ws dispatch with focused assertions for NULL
handling, skipped NULL fields, separator behavior, BLOB coercion, and strict
arity/type errors. This is intentionally a scalar dispatch helper, not a full
SELECT expression evaluator or VDBE projection engine.

Dependency closure: no new shared support component is needed; this reuses
lane-local scalar coercion, SQLiteBlobValue, and existing expression-semantics
helpers.

## Core Format Scalar Scenario

Native SQL execution helpers now include `printf()` and `format()` in the
bounded core scalar dispatch surface used by local Application option repair and
expression planning. The `examples/application-core-scalar-option-default.php`
smoke reports local-only formatted option diagnostics for copied `wp_options`
values, including `%q`/`%Q` SQL literal escaping, `%w` identifier escaping,
text/integer/hex/octal/float/character formatting, missing argument defaults,
and literal percent escapes without requiring the SQLite extension.

Status delta 2026-05-26 isolated SQL execution/planner scalar slice: added
SQLite-style printf/format dispatch with focused assertions for NULL format
propagation, aliasing, quote escaping, bounded width/precision formatting,
missing argument defaults, and strict arity/type errors. This is intentionally
a scalar dispatch helper, not a full SELECT expression evaluator or VDBE
projection engine.

Dependency closure: no new shared support component is needed; this reuses
lane-local scalar coercion, SQLiteBlobValue, and existing expression-semantics
helpers.

## Core Pattern Scalar Scenario

Native SQL execution helpers now include scalar `like()` and `glob()` dispatch
for bounded SQL expression evaluation. The implementation reuses the accepted
SQLite LIKE/GLOB pattern engines used by decoded `wp_options` scans, preserving
SQLite's SQL function argument order: `like(pattern, value[, escape])` and
`glob(pattern, value)`.

Status delta 2026-05-26 isolated SQL execution/planner scalar slice: added
focused assertions for SQL NULL propagation, case-folded LIKE matching, escaped
literal LIKE patterns, UTF-8 pattern units, embedded-NUL matching,
case-sensitive GLOB ranges, and strict arity/type errors. The Application scalar
smoke now reports local option-name predicate previews for copied option data
without requiring the SQLite extension. This is intentionally scalar dispatch
coverage, not a full SELECT/VDBE executor.

Dependency closure: no new shared support component is needed; this reuses
lane-local scalar coercion and the existing lane-local LIKE/GLOB pattern
helpers already accepted for Application option-name scans.

## Text Aggregate Option Summary Scenario

Native SQL execution helpers now include bounded `group_concat()`/`string_agg()`
aggregate behavior for local Application option repair and import diagnostics.
The `examples/application-group-concat-option-summary.php` smoke reports copied
`wp_options` option-name summaries using SQLite-style NULL row skipping,
custom separators, DISTINCT de-duplication, ORDER BY scheduling,
FILTER-style autoload selection, and ROWS-style rolling windows without
requiring the SQLite extension.

Status delta 2026-05-26 isolated SQL execution/planner aggregate slice: added
focused assertions for text aggregate step/final semantics, including BLOB
byte text coercion, NULL separator propagation, distinct ordered rows, filter
truthiness, window frame bounds, state summaries, and strict type errors. This
is intentionally an aggregate helper, not a full SELECT/VDBE executor.

Dependency closure: no new shared support component is needed; this reuses
lane-local scalar coercion, SQLiteBlobValue, and accepted aggregate scheduling
patterns.

## Connection Counter Option Insert Scenario

Native dependency helpers now expose bounded SQLite connection counters for
copied Application insert/update batches. The
`examples/application-connection-counter-option-insert.php` smoke reports
`last_insert_rowid()`, `changes()`, and `total_changes()` after a generated
`wp_options` insert, an autoload update, and savepoint rollback restoration
without requiring the SQLite extension.

Status delta 2026-05-26 isolated dependency-suite connection-counter slice:
added 29 focused assertions for initial zero counters, generated rowid capture,
update/delete/no-op change counts, total change accumulation, rollback snapshot
restore, SQL-function arity/state errors, and Application option-insert
diagnostics.

Dependency closure: no new shared support component is needed; this reuses
lane-local write-plan, savepoint, and scalar-dispatch evidence while keeping
stateful connection counters out of the stateless core scalar dispatcher.

## Rollback Hot Journal Admission Scenario

Native rollback-journal diagnostics now classify copied rollback journal bytes
as hot-journal recovery candidates before repair tooling applies page images.
The `examples/application-rollback-journal-option-diagnostics.php` smoke reports
too-small journals, invalid headers, reserved-lock blockers, super-journal
requirements, unknown page counts, and recoverable hot journals without
requiring the SQLite extension.

Status delta 2026-05-26 bounded-rebased priority rollback slice: added 27
focused assertions for hot-journal admission diagnostics while preserving the
accepted recovery-plan and rollback-image previews. This is intentionally
read-only admission and preview coverage, not a durable pager recovery writer.

Dependency closure: no new shared support component is needed; this reuses
lane-local rollback journal parsing, checksum behavior, and Application rollback
diagnostics.

Status delta 2026-05-26 isolated SQL execution/planner aggregate slice: added
11 focused assertions for `sum(DISTINCT X)`, `total(DISTINCT X)`, and
`avg(DISTINCT X)` numeric aggregate helpers, including NULL skipping, duplicate
storage-class keys, text and BLOB numeric coercion, empty-DISTINCT results,
state wrapper finalization, strict type errors, and updated Application
option-size smoke output. This is intentionally an aggregate helper, not a
full SELECT/VDBE executor.

Dependency closure: no new shared support component is needed; this reuses
lane-local scalar coercion, SQLiteBlobValue, and accepted aggregate DISTINCT
state patterns.

## Overflow-backed Option Delete Release Scenario

Native B-tree helpers now expose delete diagnostics for large `wp_options`
rows and option_name index entries whose payload spills to overflow pages. The
`examples/application-delete-overflow-option-release-plan.php` smoke deletes a
large transient option row and matching index record, reports obsolete overflow
page chains, verifies coalesced secure-delete freeblocks, and leaves the
remaining table/index entries readable without requiring the SQLite extension.

Status delta 2026-05-26 isolated B-tree delete/rebalance slice: added 20
focused assertions for overflow-backed table leaf rowid deletion and index
leaf record deletion release diagnostics. This is intentionally a bounded
delete primitive; the follow-up whole-option delete plan should feed the
reported overflow pages into the existing freelist/pointer-map mutation path.

Dependency closure: no new shared support component is needed; this reuses
lane-local overflow-chain readers, B-tree freeblock deletion, secure-delete,
and freelist planning primitives.

## Core Math Scalar Scenario

Native SQL execution helpers now include bounded math scalar behavior for local
Application option repair and import diagnostics. The
`examples/application-core-scalar-option-default.php` smoke reports copied
`wp_options` math previews using SQLite-style numeric coercion, SQL NULL
propagation, invalid-domain NULL results, and strict arity/type errors for
`ceil()`/`ceiling()`, `floor()`, `trunc()`, `sqrt()`, `pow()`/`power()`,
`mod()`, `ln()`, `log()`, `log10()`, `log2()`, `exp()`, `pi()`, `acos()`,
`asin()`, `atan()`, `atan2()`, `cos()`, `sin()`, and `tan()` without requiring
the SQLite extension.

Status delta 2026-05-26 isolated SQL execution/planner scalar slice: added 29
focused assertions for bounded core math scalar dispatch. This is intentionally
scalar dispatch coverage, not a full SELECT/VDBE executor.

Dependency closure: no new shared support component is needed; this reuses
lane-local scalar coercion and PHP runtime math primitives.

## Core Unistr Scalar Scenario

Native SQL execution helpers now include bounded SQLite 3.50 `unistr()` and
`unistr_quote()` behavior for local Application option repair and import
diagnostics. The `examples/application-core-scalar-option-default.php` smoke now
reports decoded Unicode escape previews and display-safe SQL literal previews
for copied `wp_options` text that contains control characters or backslashes,
without requiring the SQLite extension.

Status delta 2026-05-26 isolated SQL execution/planner scalar slice: added 19
focused assertions for `unistr()` backslash Unicode forms, literal backslash
escaping, unknown escape preservation, SQL NULL propagation, `unistr_quote()`
control-character and backslash-safe SQL literals, BLOB fallback quoting, and
strict arity/type errors. This is intentionally scalar dispatch coverage, not a
full SELECT/VDBE executor.

Dependency closure: no new shared support component is needed; this reuses
lane-local scalar coercion, SQL literal quoting, and UTF-8 codepoint helpers.

## SQLite Capability Preflight Scenario

Native SQL execution helpers now include bounded SQLite introspection scalar
behavior for local Application database compatibility checks. The
`examples/application-sqlite-capability-preflight.php` smoke reports
`sqlite_version()`, `sqlite_source_id()`, `sqlite_compileoption_get()`, and
`sqlite_compileoption_used()` diagnostics for copied databases before import or
repair without requiring the SQLite extension.

Status delta 2026-05-26 isolated SQL execution/planner scalar slice: added 19
focused assertions for version/source-id introspection, compile-option lookup,
`SQLITE_` prefix normalization, option-name matching independent of configured
values, NULL handling, and strict arity/type errors. This is intentionally
scalar dispatch coverage, not a full PRAGMA or SELECT/VDBE executor.

Dependency closure: no new shared support component is needed; this reuses
lane-local scalar coercion and a deterministic lane-local compile-option
inventory tied to the accepted upstream manifest commit.

## Numeric Aggregate Option Size Scenario

Native SQL execution helpers now include bounded `count()`, `sum()`, `total()`,
`avg()`, `min()`, and `max()` aggregate behavior for local Application option
repair and import diagnostics. The
`examples/application-numeric-aggregate-option-summary.php` smoke reports copied
`wp_options` value-size summaries using SQLite-style NULL skipping, text/blob
numeric coercion, DISTINCT counting, FILTER-style autoload selection, and
ROWS-style rolling totals without requiring the SQLite extension.

Status delta 2026-05-26 isolated SQL execution/planner aggregate slice: added
43 focused assertions for numeric aggregate step/final semantics, including
count(*) versus count(X), count(DISTINCT X), sum NULL versus total zero
behavior, avg divisor semantics, min/max SQL sort classes, filter truthiness,
window frame bounds, state summaries, and strict type errors. This is
intentionally an aggregate helper, not a full SELECT/VDBE executor.

Dependency closure: no new shared support component is needed; this reuses
lane-local scalar coercion, SQLiteBlobValue, and accepted aggregate scheduling
patterns.
## PRAGMA Metadata Preflight

`examples/application-pragma-preflight.php` reads a copied Application SQLite
database file and reports native header-backed PRAGMA metadata without requiring
the SQLite extension. The smoke covers `page_size`, `page_count`,
`freelist_count`, `encoding`, `journal_mode`, `auto_vacuum`,
`incremental_vacuum`, `application_id`, `user_version`, `schema_version`, and
`data_version`, giving import/repair tools a bounded compatibility preflight
before they mutate local database files.

Dependency closure: no new shared support component is needed; this reuses
lane-local header parsing, page counting, freelist counters, and auto-vacuum
diagnostics.

## ATTACH Open URI Current Next24

`examples/application-attach-open-uri-current-next24.php` demonstrates attaching
a copied Application SQLite database from a `file:` URI. The SQL-form `ATTACH`
path percent-decodes the filename for `PRAGMA database_list` and attached
schema loading while preserving mode/cache/immutable open metadata without
requiring the SQLite extension.

## File URI Open Preflight

`examples/application-file-uri-open-preflight.php` decodes and validates SQLite
`file:` URI filenames before copied Application database repair, import, or
read-only inspection tools open them. The smoke reports decoded paths,
localhost authority, mode/cache/vfs options, boolean immutable/nolock/psow
flags, repeated query values, and preserved unknown parameters without
requiring the SQLite extension.

Status delta 2026-05-26 bounded dependency-suite slice: added 27 focused
assertions for plain filenames, percent decoding, `file::memory:`, local
authority handling, valid mode/cache/boolean flags, repeated query tracking,
unknown parameter preservation, malformed percent escapes, unsupported
authorities, empty query names, and invalid mode/cache/boolean values.

Dependency closure: no new shared support component is needed; this is a
lane-local SQLite filename parser and does not activate the shared URL or
percent-encoding backlog.

## Grouped Option Summary Scenario

Native SQL execution helpers now include bounded GROUP BY result summaries for
copied Application option diagnostics. The
`examples/application-grouped-option-summary.php` smoke reports `wp_options`
groups by `autoload` with `count()`, `sum()`, `total()`, `avg()`, `min()`,
`max()`, `group_concat()`, HAVING-style filters, and ORDER BY result ordering
without requiring the SQLite extension.

Status delta 2026-05-26 isolated SQL execution/planner grouped aggregate
slice: added 31 focused assertions for scalar and NULL grouping keys,
count(*) versus count(X), count(DISTINCT X) NULL skipping, numeric aggregate
reuse per group, stable SQL sort-class ordering, HAVING count/sum filters,
BLOB group keys, and strict missing-column/type/order errors. This is a
bounded result-semantics helper, not a full SELECT/VDBE executor.

Dependency closure: no new shared support component is needed; this reuses
lane-local numeric/text aggregate helpers, SQLiteBlobValue wrappers, and
SQL-style comparison/group-key semantics.

## Busy Open Preflight Scenario

Native dependency helpers now include bounded SQLite busy-handler planning for
copied Application database open and WAL checkpoint preflights. The
`examples/application-busy-open-preflight.php` smoke reports decoded file URI
metadata plus busy-timeout retry sleeps, timeout status, and callback-cancelled
checkpoint status without requiring the SQLite extension.

Status delta 2026-05-26 isolated dependency-suite busy-handler slice: added 57
focused assertions for default busy_timeout scheduling, custom retry delays,
zero-timeout behavior, callback cancellation, ready-lock bypass, busy timeout
classification, strict invalid timeout/delay/operation errors, and copied
wp_options open/checkpoint diagnostics.

Dependency closure: no new shared support component is needed; this is a
lane-local busy/open dependency helper that reuses the existing file URI
preflight surface and does not activate a shared VFS, URL, or sleep/timer
support row.

## Incremental-vacuum Tail Truncation Scenario

Native B-tree/freelist helpers now include bounded
`planFreelistTailTruncation()` diagnostics for copied Application SQLite
maintenance flows after large transient or cache option deletes. The
`examples/application-incremental-vacuum-tail-truncation.php` smoke reports
contiguous free tail pages removed from the database image, freelist trunk
metadata rewritten, and lower reusable freelist pages preserved without
requiring the SQLite extension.

Status delta 2026-05-26 isolated B-tree delete/rebalance slice: added 25
focused assertions for tail-leaf and tail-trunk truncation, header
page-count/freelist-count rewrites, non-tail blockers, empty-freelist no-ops,
and invalid-count errors.

Dependency closure: no new shared support component is needed; this reuses
lane-local SQLite header and freelist trunk parsing/assembly.

## SELECT Subquery Option Filter Scenario

Copied Application option import previews can now model bounded SQLite subquery
filters without requiring the SQLite extension. The new smoke
`examples/application-options-subquery-preview.php` reports correlated `EXISTS`
metadata filters, selected-name `IN` filters, and NULL-sensitive `NOT IN`
anti-filter behavior before final result ordering.

Status delta 2026-05-26 isolated SQL execution/planner slice: added
`SQLiteSelectResult::whereExists()` and `SQLiteSelectResult::whereIn()` with
focused tests for correlated rows, NOT EXISTS, scalar/BLOB equality, empty
subqueries, NULL left-hand values, NULL values inside `NOT IN` subqueries,
composition with existing ORDER/LIMIT result semantics, and strict malformed
column/value guards. Dependency closure: no new support component is needed;
this reuses lane-local SQL value keys and pure PHP result arrays.
### 2026-05-26 JSON table malformed JSONB planner diagnostics

Native JSON table planning now has a bounded `validatedPlan()` diagnostic for
`json_each`/`json_tree` hidden-column constraints. It preserves the accepted
`plan()` behavior, while allowing Application import tooling to classify copied
`wp_options.option_value` inputs as strict/JSON5 text, text BLOB, JSONB, SQL
NULL, or malformed JSONB before row expansion.

Focused assertion delta: `SQLiteHeaderTest.php` increased from the accepted
3688 assertion baseline to 3721 assertions, adding 33 focused assertions for
valid JSONB, malformed JSONB, malformed text, JSON5 text BLOB, and SQL NULL
validated planner results. The Application `json_each` option-settings smoke now
includes a malformed JSONB payload that reports `jsonValid=false` and
`jsonError="malformed JSONB"` without requiring ext/sqlite.

Dependency closure: no new support component is needed; this reuses existing
lane-local JSONB validation, JSON5/text validity, BLOB wrappers, JSON table
planning, and Application smoke infrastructure.

## SELECT Join Option Metadata Preview

Copied Application option import previews can now model bounded SQLite join row
production without requiring the SQLite extension. The new smoke
`examples/application-options-join-preview.php` reports INNER JOIN public option
metadata, LEFT JOIN NULL-extension for options without matching metadata,
JOIN USING option_id equality, and result ordering over the joined rows.

Status delta 2026-05-26 isolated priority SQL join slice: added
`SQLiteSelectResult::innerJoin()`, `leftJoin()`, `crossJoin()`, and
`joinUsing()` with focused tests for join order, duplicate right-column naming,
LEFT JOIN NULL-extension, USING equality with SQL NULL non-matches, BLOB
equality keys, CROSS JOIN cartesian output, ORDER BY composition, and strict
predicate/column/type guards. Focused `SQLiteHeaderTest.php` passed at 3835
assertions, up from the accepted 3787-assertion lane-status baseline.

Dependency closure: no new support component is needed; this reuses lane-local
SQL value keys, BLOB wrappers, and pure PHP result-array dispatch.
## SELECT CASE Option Projection Scenario

Copied Application option import previews can now model bounded SQLite CASE
projection expressions without requiring the SQLite extension. The smoke
`examples/application-select-case-preview.php` reports copied `wp_options` rows
bucketed through simple CASE on `autoload`, searched CASE truthiness over score
and option-name expressions, scalar branch results, ELSE fallback values, and
final ordering over projected columns.

Status delta 2026-05-26 isolated SQL execution/planner slice: added
`SQLiteSelectProjection` CASE expression dispatch with 40 focused assertions
covering simple CASE, searched CASE, SQL truthiness for numeric/text/BLOB
values, NULL simple-CASE non-matches, lazy first-match evaluation, nested
scalar branch results, BLOB branch values, strict malformed branch guards, and
composition with existing SELECT result ordering. Dependency closure: no new
support component is needed; this reuses lane-local SELECT projection and
scalar dispatch helpers.

## SELECT Wildcard Option Projection Scenario

Copied Application option import previews can now model bounded SQLite `*` and
table-star projection expansion without requiring the SQLite extension. The
smoke `examples/application-select-wildcard-preview.php` reports copied
`wp_options` rows projected through table-star expansion after metadata joins,
then composes scalar and CASE follow-up columns before final ordering.

Status delta 2026-05-26 isolated priority SQL execution/planner slice: added
`SQLiteSelectProjection` wildcard dispatch with 40 focused assertions covering
unqualified star expansion, prefixed table-star expansion, source column order,
BLOB and NULL propagation, joined-row prefix filtering, scalar and CASE
composition, ORDER BY composition, and strict alias/prefix/duplicate-output
guards. Focused `SQLiteHeaderTest.php` passed at 4067 assertions.

Dependency closure: no new support component is needed; this reuses lane-local
SELECT projection, scalar dispatch, BLOB wrappers, and pure PHP result arrays.

## SELECT Expression-Index Plan Scenario

Copied Application option import previews can now explain bounded SQLite
expression-index dispatch for SELECT WHERE predicates without requiring the
SQLite extension. The smoke
`examples/application-select-expression-index-plan.php` reports which copied
`wp_options` expression index would serve `lower(option_name) = 'siteurl'`,
`length(option_name) BETWEEN ...`, or
`CAST(option_value AS INTEGER) = 58796`, including root page, expression type,
safe partial-index satisfaction, and residual predicate requirements.

Status delta 2026-05-26 isolated SQL execution/planner slice: added
`SQLiteSelectExpressionIndexPlan` with 41 focused assertions covering point,
IN-list, BETWEEN, reversed range operands, lower/upper/length/integer-cast
expression matching, collation/direction propagation, safe `IS NOT NULL`
partial-index gating, unsupported-column and NULL-search rejection, and strict
malformed payload guards. Focused `SQLiteHeaderTest.php` passed at 4242
assertions, up from the accepted 4201-assertion lane-status baseline.

Dependency closure: no new support component is needed; this reuses lane-local
CREATE INDEX expression parsing, partial-index predicate metadata, scalar
values, and BLOB wrappers.

## SQLite Lock Coordination Preflight Scenario

Copied Application database tools can now explain bounded SQLite lock admission
without requiring the SQLite extension or a shared VFS component. The smoke
`examples/application-lock-coordination-preflight.php` reports read-only copied
database opens acquiring shared locks, write opens blocked by existing reserved
writers, new readers blocked by pending writers, busy-handler wait summaries,
and exclusive lock readiness after reader drain.

Status delta 2026-05-26 isolated dependency/open slice: added
`SQLiteLockCoordinator` with 53 focused assertions covering shared reader
coexistence, reserved writer exclusion, pending-writer reader blocking,
exclusive lock drain requirements, open-admission composition, holder release,
busy-handler integration, dependency tags, and strict malformed connection or
lock-level guards. Focused selected `SQLiteHeaderTest.php` passed with 53
assertions and 0 failures.

Dependency closure: no new shared support component is needed. This is a
lane-local VFS/open admission helper; a future real VFS/process-lock component
remains gated on durable cross-process byte-range locking evidence.

## JSON Table Window-Ranking Scenario

Copied Application plugin settings can now be expanded through `json_tree()` and
annotated with SQLite-style window metadata before import or repair tools need
ext/sqlite. The smoke
`examples/application-json-table-window-ranking.php` reports priority rows with
row numbers, peer ranks, dense ranks, ntile buckets, lag/lead values, and
first/last values over copied `wp_options` JSON.

Status delta 2026-05-27 isolated JSON table/window slice: added
`SQLiteJsonTablePlan::windowedRows()` with 60 focused assertions covering
ordered rowsets, peer groups, partitions, JSONB/subtype inputs, LIMIT/OFFSET
composition, SQL NULL empty rowsets, and strict malformed option guards.
Focused selected `SQLiteHeaderTest.php` passed with 60 assertions and 0
failures; the lane status moves `phpPass` from 751 to 752.

Dependency closure: no new support component is needed. This reuses accepted
lane-local JSON table planning, residual filtering, ordering, JSONB/subtype
wrappers, and bounded window semantics.

## SQLite VFS Sidecar Preflight Scenario

Copied Application database tooling can now resolve bounded SQLite VFS sidecar
paths without requiring ext/sqlite or activating a shared VFS component. The
smoke `examples/application-vfs-sidecar-preflight.php` reports main database,
`-wal`, `-shm`, rollback-journal, super-journal glob, and temp-directory
planning for writable, immutable read-only, nolock repair, and create-copy
opens.

Status delta 2026-05-27 isolated dependency/open slice: added
`SQLiteVfsSidecarPlan` with 69 focused assertions covering sidecar path
derivation, WAL/SHM/journal read-write policy, immutable and nolock suppression
of shared-memory sidecars, `rwc` create-copy sidecar write policy, open-failure
preservation, in-memory exclusion, dependency tags, and empty-path guards.
Focused `SQLiteHeaderTest.php` passed at 4629 assertions, up from the
lane-status recorded 4560 baseline.

Dependency closure: no new shared support component is needed. This reuses
lane-local file URI parsing and open admission. A future full native VFS
component remains gated on actual filesystem byte-range locking, SHM-index
mapping, and durable sidecar creation/deletion evidence.

## SQLite VFS Capability Preflight Scenario

Copied Application database tooling can now resolve bounded SQLite VFS
file-control and device-capability decisions without requiring ext/sqlite or
activating a shared VFS component. The smoke
`examples/application-vfs-capability-preflight.php` reports sector size, device
characteristic flags, powersafe-overwrite policy, persist-WAL, chunk-size,
mmap-size, full-sync and directory-sync requirements for writable, immutable
read-only, and in-memory copied database opens.

Status delta 2026-05-27 isolated dependency/open slice: added
`SQLiteVfsCapabilityPlan` with 63 focused assertions covering sector-size
validation, device-characteristic bitmask normalization, URI `psow` override,
persist-WAL, chunk-size, mmap-size admission, sync policy, immutable/memory
suppression, dependency tags, missing-open preservation, and malformed input
guards. Focused `SQLiteHeaderTest.php` passed at 5073 assertions, up from the
accepted 5010-assertion lane-status baseline.

Dependency closure: no new shared support component is needed for this bounded
slice. It reuses lane-local file URI parsing and open admission. A future full
native VFS/file-control implementation remains gated on actual filesystem
write, sync, mmap, and file-control execution evidence.

## WAL Checkpoint File-Write Coordination Scenario

Copied Application database tooling can now turn bounded WAL checkpoint output
into ordered VFS-style file operations without writing live files or requiring
ext/sqlite. The updated `examples/application-wal-option-frame-diagnostics.php`
smoke reports database page writes, database sync, WAL sidecar preservation,
restarted WAL header writes, WAL truncation, WAL sync, and directory sync for
copied `wp_options` WAL fixtures.

Status delta 2026-05-27 isolated WAL rollback/savepoint slice: added
`SQLiteWalFileWritePlan` with 68 focused assertions covering ordered database
and WAL operations, reader-blocked reset preservation, restart-header writes,
truncate cleanup, empty-WAL truncation, directory-sync suppression, writable
handle guards, and malformed checkpoint input propagation. Selected focused
`SQLiteHeaderTest.php` passed with 68 assertions and 0 failures.

Dependency closure: no new shared support component is needed for this bounded
slice. It reuses lane-local WAL parsing, durable checkpoint byte planning, and
the Application WAL diagnostic smoke. A future native VFS writer remains gated on
actually applying these ordered writes, syncs, and truncations to filesystem
handles.

## VFS Lock Byte-Range Scenario

Copied Application database tooling can now preview the exact SQLite byte ranges
needed for shared, reserved, pending, and exclusive file locks before a native
process-lock executor is activated. The updated
`examples/application-lock-coordination-preflight.php` smoke reports the
pending byte, reserved byte, shared lock range, selected shared-reader slot,
exclusive coverage of the full shared range, and `nolock` suppression for a
repair-copy open.

Status delta 2026-05-27 isolated dependency/open slice: added
`SQLiteLockByteRangePlan` with 67 focused assertions covering lock constants,
shared/reserved/pending/exclusive ranges, shared-slot boundaries, open-plan
dependency composition, failed-open preservation, `nolock` suppression, and
malformed input guards. Selected focused `SQLiteHeaderTest.php` passed with 67
assertions and 0 failures.

Dependency closure: no new shared support component is needed. This reuses
lane-local file URI/open planning and lock coordination evidence. Follow-up
should apply these byte-range plans through bounded native file handles and
record lock acquire/release evidence.

## Table-Interior Delete Rebalance Scenario

Copied Application database repair tooling can now preview table b-tree interior
redistribution after delete underflow without requiring ext/sqlite. The smoke
`examples/application-table-interior-redistribute-delete-rebalance.php` reports a
copied `wp_options` table interior parent borrowing a child pointer from its
right sibling, replacing the parent divider key, materializing updated sibling
page images, and rewriting auto-vacuum pointer-map parent ownership for the
moved child page.

Status delta 2026-05-27 isolated B-tree delete/rebalance slice: added
`SQLiteBTreeInteriorRedistributionPlan` with focused assertions covering
table-interior key ordering, child-pointer repartitioning, updated sibling page
images, divider-key replacement, pointer-map update planning, free-space deltas,
and malformed sibling/page-shape guards. Focused `SQLiteHeaderTest.php` passed
at 5280 assertions.

Dependency closure: no new shared support component is needed. This reuses
lane-local table interior cell/page assembly, b-tree header parsing, database
page access, and pointer-map mutation helpers. Follow-up should wire the plan
into native delete/rebalance page-image application or add table-interior merge
materialization with freelist release.

## WAL Savepoint Page-Image Rollback Scenario

Copied Application database import tooling can now preview nested savepoint
rollback as bounded page-image application, not only dirty page numbers or WAL
frame truncation boundaries. The updated
`examples/application-savepoint-option-import-diagnostics.php` smoke reports
first pre-write page images captured per savepoint frame, database-image bytes
restored by `ROLLBACK TO`, and image propagation through `RELEASE` into an
outer transaction rollback preview for copied `wp_options` import pages.

Status delta 2026-05-27 isolated WAL rollback/savepoint slice: extended
`SQLiteSavepointStack` with 57 focused assertions covering first-image capture,
duplicate page writes across nested frames, missing image diagnostics, aligned
database image restoration, pages beyond the current database image, RELEASE
image merging, outer rollback previews, and malformed page-size/image guards.
Focused `SQLiteHeaderTest.php` passed at 5397 assertions, up from the
lane-status recorded 5340 baseline.

Dependency closure: no new shared support component is needed. This reuses
lane-local savepoint state, rollback journal page-image conventions, WAL frame
boundary diagnostics, and copied Application import smokes. Follow-up should
apply these page-image rollback previews through bounded native VFS/file
handles or connect them to durable pager write/truncate operations.

## VFS File-Writer Apply Scenario

Copied Application SQLite repair tooling can now apply accepted WAL checkpoint
write plans through bounded native PHP file handles instead of stopping at a
preview plan. The smoke `examples/application-vfs-file-writer-apply.php` reports
database image writes, WAL restart/truncate sidecar writes, sync markers,
directory persistence diagnostics, and writable-handle guards for copied
`wp_options` imports without requiring ext/sqlite.

Status delta 2026-05-27 isolated dependency/open slice: added
`SQLiteVfsFileWriter` with 62 focused assertions over the accepted 5842
assertion baseline. Focused coverage applies WAL checkpoint database bytes,
restarted WAL headers, WAL truncation, sparse writes, sync and directory-sync
operations, path-root guards, missing payload guards, byte-count mismatch
guards, unsupported operation guards, read-only/immutable guards, and missing
sync-target diagnostics. `lane-status.json` moves `phpPass` from 761 to 762
and mapped coverage from 423 to 424.

Dependency closure: no new shared support component is needed. This is the
smallest lane-local native PHP support component for the current dependency
gap and reuses accepted WAL checkpoint write plans, VFS sidecar/capability
planning, and lock-byte-range evidence. Follow-up should connect this writer
to pager transaction application with byte-range lock acquisition and platform
fsync/file-control policy.

## SELECT SQL Text Preview Scenario

Copied Application database diagnostics can now execute a bounded single-table
SELECT text subset through the native query-plan executor instead of requiring
callers to prebuild row-array plans. The smoke
`examples/application-select-sql-preview.php` reports copied `wp_options` rows
selected by SQL text with projection aliases, scalar function expressions,
WHERE predicates, ORDER BY, and LIMIT without requiring ext/sqlite.

Status delta 2026-05-27 isolated SQL execution/planner slice: added
`SQLiteSelectSql` with 88 focused assertion growth over the latest accepted
5530-assertion baseline. Focused `SQLiteHeaderTest.php` passed at 5618
assertions, covering SELECT list parsing, wildcard/table-star projection,
string/integer/NULL literals, scalar functions, comparison/LIKE/IN/IS NULL
predicates, AND/OR composition, ORDER BY, LIMIT/OFFSET, strict unsupported SQL
guards, and direct plan inspection.

Dependency closure: no new support component is needed. This reuses lane-local
`SQLiteSelectQuery`, `SQLiteSelectProjection`, `SQLiteSelectPredicate`,
`SQLiteSelectResult`, and scalar function dispatch. Follow-up should broaden
parser/VDBE execution to JOIN clauses, GROUP BY SQL text, expression ORDER BY,
or range-cost planner decisions.

## SELECT SQL JOIN Text Preview Scenario

Copied Application database diagnostics can now execute bounded SELECT SQL text
with JOIN clauses through the native query-plan executor. The smoke
`examples/application-select-sql-join-preview.php` reports copied `wp_options`
rows joined to bounded option metadata with INNER JOIN, LEFT JOIN
null-extension, CROSS JOIN, chained joins, table aliases, table-qualified ON
predicates, table-star projection, ORDER BY, and LIMIT without requiring
ext/sqlite.

Status delta 2026-05-27 clean integration replay: extended `SQLiteSelectSql`
JOIN parsing and dispatch on current source `a708c39b`. Focused
`SQLiteHeaderTest.php` passed at 5918 assertions, up from the accepted 5842
assertion JSON host-join baseline. The Application SQL JOIN smoke emitted valid
JSON.

Dependency closure: no new shared support component is needed. This reuses
lane-local SELECT text parsing, accepted row-array JOIN production, projection,
predicate, result ordering, and scalar dispatch. Follow-up should broaden SQL
text execution to GROUP BY/HAVING text, expression ORDER BY, range-cost planner
decisions, or broader parser/VDBE execution without repeating this JOIN text
slice.

## Table-Interior Merge Delete Rebalance Scenario

Copied Application database repair tooling can now preview table b-tree interior
sibling merge materialization after delete underflow. The smoke
`examples/application-table-interior-merge-delete-rebalance.php` reports merged
child pointers, obsolete sibling release to the freelist, parent divider
removal, auto-vacuum pointer-map ownership rewrites, and secure-delete page
clearing without requiring ext/sqlite.

Status delta 2026-05-27 clean integration: added
`SQLiteBTreeInteriorMergePlan` and
`SQLiteBTreeInteriorMergeApplicationPlan` with focused assertions covering
table-interior merge eligibility, merged cell ordering, rightmost pointer
preservation, parent divider metadata, obsolete sibling freelist release,
pointer-map updates, secure-delete clearing, and malformed sibling/page-shape
guards. Focused `SQLiteHeaderTest.php` passed at 5765 assertions.

Dependency closure: no new shared support component is needed. This reuses
lane-local table interior cell/page assembly, database page access, freelist
mutation, pointer-map update planning, and secure-delete clearing. Follow-up
should move to page-move/pointer-map integration or broader delete/rebalance
write application.

## JSON Table Host Join Scenario

Copied Application import and repair tooling can now materialize host-row joins
against bounded `json_each()` and `json_tree()` scans without ext/sqlite. The
smoke `examples/application-json-table-host-join.php` reports copied
`wp_options` rows expanded with hidden `json`/`root` constraints, residual
predicates, JSONB and constructor-subtype payloads, rowid aliases, INNER and
LEFT join behavior, and SQL NULL or malformed payload diagnostics.

Status delta 2026-05-27 clean integration: extended
`SQLiteJsonTablePlan` with `hostJoinRows()` and focused assertions covering
host-column validation, projected JSON column prefixes, hidden constraint
composition, residual `LIKE`/`BETWEEN`/`IN`/`NOT IN`/`IS NOT NULL` filtering,
rowid/_rowid_/oid aliases, duplicate hidden-json constraints, no-match INNER
joins, NULL-extended LEFT joins, and malformed path/column guards.

Dependency closure: no new shared support component is needed. This reuses
lane-local JSON table hidden-column planning, JSONB/subtype conversion,
residual predicate dispatch, and copied Application JSON option smokes.
Follow-up should wire this bounded materialization into parser-level
`FROM json_each(...)` / `FROM json_tree(...)` SELECT execution or a native
virtual-table cursor.
## WAL Savepoint Byte Truncation Scenario

Copied Application import diagnostics can now materialize `ROLLBACK TO`
savepoints as concrete WAL sidecar byte truncation. The smoke
`examples/application-savepoint-option-import-diagnostics.php` reports retained
and discarded frame counts, truncated WAL byte length, nested draft-frame
removal, and commit-frame discard decisions for copied `wp_options` imports
without requiring ext/sqlite.

Status delta 2026-05-27 clean integration: extended `SQLiteSavepointStack`
with `walRollbackToByteTruncationPlan()` and `walRollbackToWalBytes()`.
Focused `SQLiteHeaderTest.php` passed at 5993 assertions on current source
`076e640c`, up from the accepted 5918 assertion SELECT SQL JOIN text baseline.

Dependency closure: no new shared support component is needed. This reuses
lane-local WAL parsing/checksum validation, WAL serialization, and savepoint
frame bookkeeping. Follow-up should apply these accepted truncated bytes
through bounded native VFS/file-handle writes without repeating accepted WAL
file-write planning.

## VFS Hot Rollback-Journal Apply Scenario

Copied Application repair tooling can now apply hot rollback-journal recovery to
real bounded PHP file handles without ext/sqlite. The smoke
`examples/application-vfs-rollback-journal-apply.php` reports restored
`wp_options` database bytes, pre-transaction database truncation, durable
database sync, rollback-journal sidecar deletion, directory persistence sync,
and clean/dirty option-page diagnostics.

Status delta 2026-05-27 isolated dependency-suite slice: extended
`SQLiteVfsFileWriter` with `applyHotRollbackJournal()` and `delete`
operations. Focused `SQLiteHeaderTest.php` selected coverage passed with 65
assertions, moving lane `phpPass` from 765 to 766 and mapped coverage from 427
to 428 on current source `e331c8db`.

Dependency closure: no new shared support component is needed. This reuses the
accepted rollback-journal recovery planner and VFS file-handle writer; the
next dependency/open target should be broader pager transaction or durable
lock/fsync coordination rather than another preview-only sidecar diagnostic.

## JSON Table Virtual Cursor Scenario

Copied Application import and repair tooling can now iterate planned
`json_each()` and `json_tree()` rowsets through a bounded virtual-table cursor
without ext/sqlite. The smoke `examples/application-json-table-cursor.php`
reports cursor open/filter/next/eof behavior, rowid aliases, column access,
rewind, JSONB payload iteration, SQL NULL empty cursors, and malformed JSONB
diagnostics for copied plugin settings.

Status delta 2026-05-27 isolated JSON-table slice: added
`SQLiteJsonTableCursor` with focused assertions covering validated planner
metadata, residual filtering, `json_tree` and `json_each` cursor iteration,
`rowid`/`_rowid_`/`oid` aliases, JSON subtype and JSONB inputs, missing-root
and SQL NULL empty cursors, malformed JSONB/text diagnostics, EOF guards, and
malformed argument guards. Focused selected coverage passed at 81 assertions.

Dependency closure: no new shared support component is needed. This reuses
lane-local JSON table planning, JSONB/text/subtype validation, residual
predicate filtering, and row materialization. Follow-up should connect this
cursor lifecycle to parser/VDBE-style execution with correlated host-column
arguments without repeating accepted host-row joins or literal SELECT/FROM
parser wiring.

## WAL Checkpoint Transaction Scenario

Copied Application import and repair tooling can now plan WAL checkpoint
admission before applying checkpoint bytes. The smoke
`examples/application-wal-checkpoint-transaction.php` reports a restart-ready
checkpoint with shared/reserved/pending/exclusive lock sequencing, WAL sidecar
write operation reasons, and a reader-blocked truncate checkpoint that surfaces
the blocking connection and busy outcome without requiring ext/sqlite.

Status delta 2026-05-27 isolated WAL slice: added
`SQLitePagerCheckpointTransactionPlan`. Focused `SQLiteHeaderTest.php` passed
at 6528 assertions, +71 over the accepted 6457 baseline, covering passive,
restart, truncate, reader-limited, pending-writer, shared-reader, malformed
input, read-only, immutable, and empty-path checkpoint transaction cases.

Dependency closure: no new shared support component is needed. The slice reuses
accepted lane-local WAL checkpoint planning, VFS write planning,
lock-coordinator, and busy-handler behavior.

## JSON Table SQL Hidden-Constraint Scenario

Copied Application plugin settings can now be queried through bare
`json_each`/`json_tree` virtual-table sources in SELECT SQL text when WHERE
terms provide usable hidden `json` and `root` equality constraints. The smoke
`examples/application-select-sql-json-hidden-constraints.php` reports priority
rows and grouped summaries over copied `wp_options` settings JSON without
requiring ext/sqlite.

Status delta 2026-05-27 isolated JSON-table slice: `SQLiteSelectSql` extracts
top-level hidden `json`/`root` WHERE equality constraints for bare JSON table
sources, feeds them into accepted `SQLiteJsonTablePlan` row materialization,
and preserves remaining WHERE terms as residual predicates. Focused selected
coverage passed at 334 assertions, adding 51 focused assertions over the
previous selected-test surface.

Dependency closure: no new shared support component is needed. This reuses
lane-local JSON table planning, JSON path validation, SELECT predicate
filtering, grouped aggregate execution, and JSON row materialization. Follow-up
should broaden virtual-table planner/VDBE cursor integration without repeating
accepted JSON cursor, literal function-source SELECT wiring, host joins,
LIMIT/OFFSET, window ranking, or duplicate hidden-constraint planning.

## B-tree Index-Interior Merge Apply Scenario

Copied Application repair tooling can now apply an index-interior sibling merge
directly after delete underflow. The smoke
`examples/application-index-interior-merge-apply.php` reports merged
`wp_options` autoload-index parent cells, obsolete sibling freelist release,
and auto-vacuum pointer-map ownership rewrites for children that moved from
the freed sibling into the merged parent without requiring ext/sqlite.

Status delta 2026-05-27 isolated B-tree slice: extended
`SQLiteBTreeInteriorMergePlan` with index-interior sibling merge
materialization and reused `SQLiteBTreeInteriorMergeApplicationPlan` for
freelist and pointer-map application. Focused `SQLiteHeaderTest.php` passed at
6863 assertions, +70 over the current accepted 6793 assertion baseline.

Dependency closure: no new shared support component is needed. This reuses
lane-local index interior cell/page assembly, freelist page-free planning, and
auto-vacuum pointer-map mutation. Follow-up should target B-tree freeblock,
freelist, or delete/rebalance apply behavior that is not covered by accepted
page moves, table-interior merge, or this index-interior merge application.

## SELECT Expression-Index Cost Scenario

Copied Application option import previews can now rank competing SQLite
expression-index plans before native row decoding. The smoke
`examples/application-select-expression-index-cost.php` reports a lower()
point lookup beating a broader partial index because it is covering, satisfies
the requested ORDER BY, and has lower estimated row/cost metadata, without
requiring ext/sqlite.

Status delta 2026-05-27 bounded replayed SQL planner slice: extended
`SQLiteSelectExpressionIndexPlan` with ranked point, range, IN, and BETWEEN
plans over lower()/upper()/length()/CAST() copied `wp_options` predicates while
preserving the existing first-usable `choose()` behavior. Focused
`SQLiteHeaderTest.php` passed at 6998 assertions, +53 over the current
accepted 6945 assertion baseline.

Dependency closure: no new shared support component is needed. This reuses
lane-local CREATE INDEX expression metadata, partial-index predicates, scalar
value coercion, and bounded SELECT planner arrays. Follow-up should wire these
ranked decisions into broader parser/executor planning without repeating
accepted expression ORDER BY, GROUP BY/HAVING, SQL text JOIN, or first-pass
expression-index planner work.

## B-tree Bulk Overflow Delete Freeblock Scenario

Copied Application transient cleanup can now delete an option and its timeout
partner from table and secondary-index leaf pages in one bounded mutation. The
smoke `examples/application-bulk-delete-overflow-freeblocks.php` reports
coalesced reusable freeblocks, secure-delete payload clearing, remaining
`siteurl`/`home` rows and index records, and obsolete table/index overflow page
numbers for a later freelist release path without requiring ext/sqlite.

Status delta 2026-05-27 isolated B-tree delete/rebalance slice: added
`SQLiteTableLeafPage::deleteCellsByRowIdsWithOverflowRelease()` and
`SQLiteIndexLeafPage::deleteCellsByRecordValuesWithOverflowRelease()` with
focused assertions for table and index leaf page images, overflow release
aggregation, freeblock integrity, secure-delete reports, and malformed bulk
delete guards. Focused `SQLiteHeaderTest.php` passed at 7054 assertions, +56
over the current accepted 6998 assertion baseline.

Dependency closure: no new shared support component is needed. This reuses the
lane-local table/index leaf encoders, overflow chain helpers, freeblock
integrity reports, and secure-delete mutation path. Follow-up should connect
the returned obsolete overflow pages to full freelist and auto-vacuum
pointer-map application for arbitrary SQL DELETE.

## VFS Rollback-Journal Commit Apply Scenario

Copied Application import tooling can now apply the forward rollback-journal
commit sequence through native PHP file handles. The smoke
`examples/application-vfs-rollback-commit-apply.php` reports rollback-journal
bytes written and synced before dirty `wp_options` database pages, database
pages synced before journal deletion, and directory-entry persistence without
requiring ext/sqlite.

Status delta 2026-05-27 isolated dependency/VFS slice: added
`SQLiteRollbackJournalCommitPlan` and `SQLiteVfsFileWriter::applyRollbackJournalCommit()`
for bounded rollback-journal commit ordering, sync mode, and journal mode
application. Focused `SQLiteHeaderTest.php` passed at 7368 assertions, +92
over the current accepted 7276 assertion baseline.

Dependency closure: no new shared support component is needed. This reuses the
lane-local VFS file-handle writer and rollback-journal durability evidence.
Follow-up should broaden pager/VFS transaction application and durable sync
policy without repeating accepted hot rollback-journal recovery, VFS file
writer, locked writer, process locks, savepoint rollback, WAL byte truncation,
or this rollback-journal commit path.

## VFS Super-Journal Commit Scenario

Copied Application multisite-style import tooling can now apply SQLite
super-journal commit ordering for attached databases through native PHP file
handles. The smoke `examples/application-vfs-super-journal-commit.php` reports a
master journal listing the main and site-meta rollback journals, durable syncs
for the super-journal, attached rollback journals, and database pages, then
super-journal deletion as the atomic commit point without requiring ext/sqlite.

Status delta 2026-05-27 isolated WAL/rollback slice: added
`SQLiteSuperJournalCommitPlan` and
`SQLiteVfsFileWriter::applySuperJournalCommit()` for bounded attached-database
rollback-journal commit ordering. The selected focused test passed with 83
assertions and 0 failures.

Dependency closure: no new shared support component is needed. This reuses the
lane-local VFS file-handle writer and rollback-journal durability evidence.
Follow-up should broaden pager/VFS atomic transaction and durable fsync
behavior without repeating accepted rollback-journal commit, hot rollback
recovery, savepoint rollback, WAL byte truncation, locked writer, process
locks, or this super-journal commit path.

## VFS Sync Plan Scenario

Copied Application database import tooling can now preview SQLite xSync decisions
for rollback-journal commit paths before applying file-handle writes. The smoke
`examples/application-vfs-sync-plan.php` reports database, rollback-journal, WAL,
directory, read-only, memory, powersafe-overwrite, and persist-journal sync
steps with FULL/NORMAL/DATAONLY flag names and durable dependency tags without
requiring ext/sqlite.

## VFS Sync Apply Scenario

Copied Application database import tooling can now apply planned SQLite xSync
barriers through native PHP file handles. The smoke
`examples/application-vfs-sync-apply.php` reports rollback-journal, database,
persisted journal-header, directory, and WAL sync application, including
FULL/NORMAL/DATAONLY flag evidence and skipped read-only handles, without
requiring ext/sqlite.

Status delta 2026-05-27 isolated dependency/VFS slice: added
`SQLiteVfsFileWriter::applySyncPlans()` for bounded file-handle application of
previously planned xSync barriers. Focused `SQLiteHeaderTest.php` passes at
7901 assertions with 0 failures; this slice adds 70 focused assertions over
the accepted 7831 assertion baseline.

Dependency closure: no new shared support component is needed. This reuses the
lane-local VFS sync planner, file-handle writer, and rollback/WAL durability
evidence. Follow-up should connect these sync barriers into broader pager
transaction apply paths without repeating accepted VFS file writer, locked
writer, rollback-journal commit, super-journal commit, savepoint rollback,
rollback-journal recovery, or this sync-application path.
## SELECT SQL Scalar Operators Scenario

Copied Application option diagnostics can now execute bounded SELECT SQL scalar
operators over row-array inputs. The smoke
`examples/application-select-sql-scalar-operators.php` reports arithmetic,
modulo, division, text concatenation, NULL propagation, hidden ORDER BY
expressions, WHERE expression operands, and grouped HAVING rewrites without
requiring ext/sqlite.

Status delta 2026-05-27 bounded scalar SQL slice: added parser-level binary
expression dispatch in `SQLiteSelectSql` plus shared scalar expression
evaluation through projection, predicate, hidden ORDER BY, and aggregate
rewrite paths. Focused `SQLiteHeaderTest.php` is expected to pass at 7708
assertions after replay, +67 over the current accepted 7641 assertion
baseline. Lane `phpPass` moves from 787 to 788 and mapped coverage moves from
441 to 442. Dependency closure: no new support component is needed; this
reuses lane-local SELECT SQL parsing, scalar function dispatch, projection,
predicate, grouped aggregate, and query-plan result evidence.

## B-tree Root Collapse Scenario

Copied Application `wp_options` repair tooling can now materialize the SQLite
delete/rebalance path where an empty interior root has only one child left. The
smoke `examples/application-btree-root-collapse.php` reports the child table leaf
copied into the root page, the obsolete child page released to the freelist, and
auto-vacuum pointer-map state rewritten to `free-page` without requiring
ext/sqlite.

Status delta 2026-05-27 isolated B-tree slice: added
`SQLiteBTreeRootCollapsePlan` for bounded table and index root-collapse page
images, freelist release, secure-delete-compatible free planning, and
auto-vacuum pointer-map ownership rewrites. Focused `SQLiteHeaderTest.php`
passes at 7831 assertions with 0 failures; this slice adds 73 focused
assertions over the local pre-slice test body. Lane `phpPass` moves from 788 to
789 and mapped coverage moves from 441 to 442.

Dependency closure: no new shared support component is needed. This reuses the
lane-local b-tree page encoders, freelist planner, pointer-map diagnostics, and
Application option row fixtures. Follow-up should broaden B-tree delete/rebalance
materialization without repeating accepted page relocation, index-interior
merge, overflow freelist release, bulk overflow freeblocks, or this
root-collapse path.

## B-tree Empty Leaf Freelist Release Scenario

Copied Application `wp_options` maintenance tooling can now materialize the
SQLite delete/rebalance path where a non-root table or index leaf becomes empty
after deletion. The smoke `examples/application-btree-empty-leaf-free.php`
reports the emptied leaf page and obsolete overflow pages released into the
freelist, secure-delete clearing for released leaf pages, next allocation
order, and auto-vacuum pointer-map entries rewritten to `free-page` without
requiring ext/sqlite.

Status delta 2026-05-27 isolated B-tree slice: added
`SQLiteBTreeEmptyLeafFreePlan` for bounded table/index empty-leaf release
after delete, obsolete overflow page co-release, secure-delete page clearing,
and auto-vacuum pointer-map free-page rewrites. Focused `SQLiteHeaderTest.php`
passes at 7881 assertions with 0 failures; this slice adds 50 focused
assertions over the accepted 7831-assertion B-tree root-collapse baseline.
Lane `phpPass` moves from 789 to 790 and mapped coverage moves from 442 to
443.

Dependency closure: no new shared support component is needed. This reuses
lane-local B-tree leaf delete helpers, freelist planning, overflow page
diagnostics, and pointer-map mutation machinery. Follow-up should broaden
B-tree delete/rebalance materialization without repeating accepted root
collapse, page relocation, index-interior merge, overflow freelist release,
bulk overflow freeblocks, or this empty-leaf release path.

## SELECT SQL JSON Dynamic Join Scenario

Copied Application `wp_options` diagnostics can now execute bounded SELECT SQL
where `json_each()` or `json_tree()` receives the current host row's
`option_value` as its JSON argument. The smoke
`examples/application-select-sql-json-dynamic-join.php` reports plugin setting
rows joined to row-correlated JSON table scans, preserving INNER JOIN, LEFT
JOIN NULL-extension, CROSS JOIN, ORDER BY, grouping, and malformed dynamic-root
guards without requiring ext/sqlite.

Status delta 2026-05-27 isolated JSON table slice: added row-dependent JSON
table JOIN sources in `SQLiteSelectSql` plus dynamic join application in
`SQLiteSelectQuery`. Focused `SQLiteHeaderTest.php` passes at 8076 assertions
with 0 failures; this slice adds 60 focused assertions over the 8016-assertion
lane-status baseline.

Dependency closure: no new shared support component is needed. This reuses the
lane-local SELECT SQL parser, JSON table planner/cursor rows, scalar
expression evaluator, join executor, and copied Application option fixtures.
Follow-up should broaden JSON planner/JSONB behavior without repeating
accepted JSON hidden/visible constraints, parser-level JSON table SELECT
sources, JSON cursor iteration, or this row-correlated dynamic JOIN path.

## B-tree Empty Leaf Batch Free Scenario

Copied Application `wp_options` diagnostics can now batch-release multiple
emptied B-tree leaves after a transient cleanup deletes the final table row and
the matching `option_name` index entry. The smoke
`examples/application-btree-empty-leaf-batch-free.php` reports both emptied
leaves plus obsolete overflow pages released through one freelist operation,
secure-delete clearing of released leaf/overflow pages, preserved existing
freelist trunk shape, and auto-vacuum pointer-map entries rewritten to
`free-page` without requiring ext/sqlite.

Status delta 2026-05-27 isolated B-tree slice: added
`SQLiteBTreeEmptyLeafBatchFreePlan` for bounded multi-leaf delete/rebalance
release. Focused `SQLiteHeaderTest.php` passes at 8136 assertions with
0 failures; this slice adds 60 focused assertions over the 8076-assertion
lane-status baseline.

Dependency closure: no new shared support component is needed. This reuses
lane-local B-tree leaf delete helpers, freelist planning, overflow page
diagnostics, secure-delete clearing, and pointer-map mutation machinery.
Follow-up should continue B-tree delete/rebalance materialization without
repeating page relocation, root collapse, overflow freelist release,
single-leaf empty release, or this batch free path.

## B-tree Parent Prune Scenario

Copied Application `wp_options` diagnostics can now prune an emptied table or
index child page from its interior parent after transient cleanup deletes the
last row or index entry. The smoke
`examples/application-btree-parent-prune.php` reports right-most and left-child
parent pointer repair, obsolete child and overflow page freelist release,
secure-delete clearing, and auto-vacuum pointer-map entries rewritten to
`free-page` without requiring ext/sqlite.

Status delta 2026-05-27 bounded B-tree replay: added
`SQLiteBTreeParentPrunePlan` for bounded parent interior page pruning after an
empty leaf delete. Focused `SQLiteHeaderTest.php` passes at 8194 assertions
with 0 failures; this slice adds 58 focused assertions over the
8136-assertion accepted empty-leaf batch baseline.

Dependency closure: no new shared support component is needed. This reuses
lane-local B-tree interior cell assembly/parsing, leaf delete results,
freelist planning, secure-delete clearing, and pointer-map mutation machinery.
Follow-up should continue multi-level delete/rebalance materialization without
repeating page relocation, root collapse, index-interior merge, empty-leaf
batch release, overflow freelist release, or this parent-prune path.

## WAL Recovery Apply Scenario

Copied Application `wp_options` diagnostics can now recover committed WAL frames
through bounded native PHP VFS file handles after a crash-style reopen. The
smoke `examples/application-wal-recovery-apply.php` reports committed frame
replay into the database image, superseded frame skips, preserved uncommitted
WAL tail frames, database/WAL syncs, and directory-entry persistence without
requiring ext/sqlite.

Status delta 2026-05-27 bounded WAL replay: added
`SQLiteWalRecoveryPlan` and `SQLiteVfsFileWriter::applyWalRecovery()` for
bounded WAL recovery application beyond accepted checkpoint, rollback-journal,
and savepoint writer paths. Focused `SQLiteHeaderTest.php` passes at 8273
assertions with 0 failures after replay on current `b530a4cf`; this slice
adds 79 focused assertions over the accepted parent-prune baseline.

Dependency closure: no new shared support component is needed. This reuses the
lane-local WAL parser/frame checksum code, checkpoint page-image materializer,
and native VFS file writer. Follow-up should broaden pager transaction and
crash-recovery integration without repeating accepted checkpoint transaction,
rollback-journal commit/apply, savepoint rollback, sync application, or this
WAL recovery apply path.

## JSON Dynamic Malformed JSONB Join Scenario

Copied Application `wp_options` diagnostics can now join row-sourced JSONB
option blobs through parser-level `json_tree(o.option_value, '$.rules')` while
malformed JSONB and SQL NULL option values follow the JSON table validated
planner path. The smoke
`examples/application-select-sql-json-malformed-jsonb-join.php` reports INNER
joins skipping invalid row-sourced JSON and LEFT joins preserving the host row
with NULL-extended JSON table columns, without requiring ext/sqlite.

Status delta 2026-05-27 isolated JSON table replay: dynamic SELECT SQL JSON
table source callbacks now call `SQLiteJsonTablePlan::validatedPlan()` before
row expansion. Focused `SQLiteHeaderTest.php` moves from 8404 to 8445
assertions, adding coverage for valid JSONB, malformed JSONB, text JSON, SQL
NULL, INNER joins, LEFT joins, and direct plan callback behavior.

Dependency closure: no new shared support component is needed. This reuses the
lane-local JSONB validator, JSON table planner, SELECT SQL parser/executor,
and row-correlated dynamic join callback machinery. Follow-up should continue
JSON planner pushdown or malformed JSONB behavior without repeating accepted
hidden/visible constraints, cursor/source wiring, or this dynamic validation
path.

## LIKE Pattern Plan Scenario

Copied Application `wp_options` diagnostics now expose SQLite LIKE pattern
planning for option-name scans. The smoke
`examples/application-option-name-like-glob.php` reports escaped literal `_` and
`%` handling, binary prefix ranges, NOCASE prefix ranges, wildcard detection,
and ASCII-only LIKE folding for non-ASCII option names such as `plugin_Å` and
`plugin_å` without requiring ext/sqlite.

Status delta 2026-05-27 isolated encoding/collation slice: added
`SQLiteDatabase::likePatternPlan()` and
`SQLiteDatabase::likeNoCasePrefixRangeBounds()`, and rewired the NOCASE indexed
LIKE prefix lookup through the explicit plan. Focused `SQLiteHeaderTest.php`
passes at 8477 assertions with 0 failures in the worker handoff; clean
integration reran the focused test on current source before commit.

Dependency closure: no new shared support component is needed. This reuses
lane-local UTF-8 character splitting, ASCII-only NOCASE folding, LIKE matching,
and existing copied Application option fixtures. Follow-up should continue
encoding closure with affinity/collation predicate behavior or malformed-text
comparison edges without repeating accepted Unicode GLOB ranges or this LIKE
planner surface.

## SELECT SQL Predicate Semantics Scenario

Copied Application `wp_options` diagnostics now preserve parser-level predicate
semantics for bounded SELECT SQL text. The smoke
`examples/application-select-sql-predicate-semantics.php` reports rows filtered
through `BETWEEN`, `NOT BETWEEN`, `GLOB`, `NOT GLOB`, `IS`, `IS NOT`, and
`LIKE ... ESCAPE` in WHERE and HAVING clauses, preserving SQLite three-valued
filtering behavior without requiring ext/sqlite.

Status delta 2026-05-27 clean integration: `SQLiteSelectSql` now lowers these
predicate spellings into the accepted `SQLiteSelectPredicate` execution
payloads while preserving the inner `AND` inside `BETWEEN` bounds. Focused
`SQLiteHeaderTest.php` passes at 8861 assertions with 0 failures on the
rebased current source after this slice.

Dependency closure: no new shared support component is needed. This reuses the
lane-local SELECT SQL parser/executor, predicate evaluator, LIKE/GLOB matcher,
and copied Application option fixtures. Follow-up should continue non-overlapping
planner pushdown or executor behavior beyond accepted subqueries, grouped
SELECT, expression ORDER BY, range costs, and this predicate parser semantics
surface.

## Savepoint Full Transaction Image Recovery Scenario

Copied Application `wp_options` import diagnostics now preview full transaction
rollback image recovery across nested savepoints. The smoke
`examples/application-savepoint-option-import-diagnostics.php` reports the
earliest captured page image for each dirty page across the whole savepoint
stack, identifies dirty pages without captured images, and materializes a
bounded restored database image for a failed outer import without requiring
ext/sqlite.

Status delta 2026-05-27 isolated rollback/savepoint slice:
`SQLiteSavepointStack` adds full transaction image recovery via
`rollbackPageImages()`, `rollbackImagePlan()`, and `rollbackDatabaseImage()`.
Focused `SQLiteHeaderTest.php` adds nested recovery coverage for direct
rollback, rollback-to followed by continued writes, RELEASE image propagation,
beyond-end image skips, malformed page images, inactive transactions, and
database page-size guards.

Dependency closure: no new shared support component is needed. This reuses the
lane-local savepoint stack, bounded page-image snapshots, and copied Application
option import diagnostics. Follow-up should continue pager/VFS transaction
application or WAL durability beyond accepted rollback-journal commit,
savepoint VFS apply, WAL byte truncation, and this full-transaction preview
surface.

## SELECT SQL JSON Negative Path Scenario

Copied Application `wp_options` diagnostics now execute SQLite SELECT text with
`json_extract()` and `jsonb_extract()` reverse array paths such as
`$.rules[#-1]`. The smoke
`examples/application-select-sql-json-negative-path.php` reports the last and
previous plugin rules from text JSON and JSONB option values, filtering and
ordering through parser-level SELECT SQL without requiring ext/sqlite.

Status delta 2026-05-27 isolated JSON-path execution slice:
`SQLiteSelectExpression` dispatches `json_extract` and `jsonb_extract` through
the lane-local JSON implementation after evaluating SELECT expression
arguments. Focused `SQLiteHeaderTest.php` adds coverage for projection, WHERE,
JOIN predicates, hidden ORDER BY expressions, multi-path summaries, JSONB
object results, missing reverse indexes, malformed paths, dynamic path guards,
and non-text JSON argument guards.

Dependency closure: no new shared support component is needed. This reuses the
lane-local SELECT SQL parser/executor, JSON path parser, JSON text/JSON5/JSONB
extractors, and copied Application option fixtures. Follow-up should continue
non-overlapping JSON planner/JSONB pushdown or broader SQL executor behavior
beyond accepted JSON table sources, hidden/visible constraints, and this scalar
JSON-path expression dispatch.

## Temp-store Sorter B-tree Scenario

Copied Application `wp_options` diagnostics now expose bounded SQLite temp-store
sorter spill planning. The smoke
`examples/application-temp-store-sorter-btree.php` reports option rows ordered by
NOCASE `option_name`, DESC `autoload`, and stable `option_id`/sequence
tie-breaks, plus generated temporary index-leaf page numbers for sort records
that cross the memory threshold, without requiring ext/sqlite.

Status delta 2026-05-27 isolated B-tree sorter slice:
`SQLiteTempStoreSorterBTreePlan` adds in-memory versus temp-B-tree admission,
sort-term validation for BINARY/NOCASE/RTRIM collations, stable row ordering,
and temp index-leaf page image assembly. Focused `SQLiteHeaderTest.php` passed
at 8971 assertions with 0 failures in the worker handoff.

Dependency closure: no new shared support component is needed. This reuses
lane-local SQLite record encoding and index leaf page/cell assembly. Follow-up
should wire the generated sorter B-tree page images into broader SELECT ORDER
BY spill execution without repeating accepted expression ORDER BY or B-tree
freeblock/freelist/page-move/root-collapse/overflow clusters.

## WAL Checkpoint Reader Visibility Scenario

Copied Application `wp_options` WAL diagnostics now report current-reader
visibility across checkpoint application. The smoke
`examples/application-wal-option-frame-diagnostics.php` shows that a pinned
reader keeps seeing the same page image before and after passive/full checkpoint
planning, while a truncate checkpoint with no active reader exposes the latest
checkpointed database page to a new reader without requiring ext/sqlite.

Status delta 2026-05-27 isolated WAL reader-visibility slice:
`SQLiteWal::checkpointReaderVisibility()` composes existing reader snapshot,
durable checkpoint, WAL preserve/restart/truncate, and database-page fallback
behavior into one bounded visibility result. Focused `SQLiteHeaderTest.php`
adds coverage for pinned reader stability, full-checkpoint busy preservation,
truncate-checkpoint latest visibility, dependency tags, and malformed inputs.

Dependency closure: no new shared support component is needed. This reuses the
lane-local WAL parser, checkpoint result materialization, reader snapshot
lookup, and copied Application option WAL diagnostics. Follow-up should continue
WAL/pager transaction application and durable recovery/checkpoint paths without
repeating accepted VFS writer, savepoint rollback, rollback-journal commit, or
this current-reader visibility composition.

## SELECT SQL JSONB Literal Malformed Edge Dispatch Scenario

Status delta 2026-05-27 isolated `jsonb-malformed-edge-dispatch` slice:
`SQLiteSelectSql` now parses SQLite `X'...'` BLOB literals and feeds SQL-literal
JSONB BLOBs through `json_each()` / `json_tree()` source and hidden-constraint
dispatch. Valid JSONB literals expand through parser-level SELECT SQL, while
malformed JSONB literals such as `X'1c00'` produce empty JSON table rowsets
instead of aborting copied import diagnostics. The updated
`examples/application-select-sql-json-malformed-jsonb-join.php` smoke covers both
row-sourced wp_options JSONB joins and SQL-literal malformed JSONB hidden
constraints. No new support component is needed; this reuses the existing
native PHP JSONB validator, `SQLiteBlobValue`, and SELECT SQL dispatcher.

## INSERT OR REPLACE Current Conflict Scenario

Copied Application `wp_options` diagnostics now expose bounded SQLite
`INSERT OR REPLACE` current-conflict behavior. The smoke
`examples/application-insert-or-replace-conflict-current.php` reports a UNIQUE
`option_name` conflict deleting the old option row before inserting the
incoming rowid and maintaining the automatic option_name index, without
requiring ext/sqlite.

Status delta 2026-05-27 isolated `insert-or-replace-conflict-current` slice:
`SQLiteDatabase::planOptionRowInsertOrReplaceCurrent()` removes current
rowid and UNIQUE `option_name` conflicts from bounded single-leaf `wp_options`
table/index images, then reuses the existing insert planner for the incoming
row. Focused `SQLiteHeaderTest.php` covers unique-name conflicts with a new
rowid, simultaneous rowid plus unique-name conflicts, no-conflict fallback,
change-count diagnostics, table rows, and automatic-index records.

Dependency closure: no new shared support component is needed. This reuses the
lane-local SQLite table/index leaf writers, automatic-index column inference,
record encoding, and copied Application option fixtures. Follow-up should wire
the conflict planner into parser-level INSERT SQL execution or broaden conflict
handling beyond this single-leaf current behavior without repeating accepted
unique-index replacement or B-tree delete/freeblock clusters.

## INSERT INTO SELECT Current Scenario

Copied Application `wp_options` diagnostics now expose bounded SQLite
`INSERT INTO ... SELECT ...` current behavior. The smoke
`examples/application-insert-select-current.php` reports archive/import staging
rows copied from `wp_options` through parser-level SELECT projection, WHERE,
LIMIT, CTE, subquery, and bind-parameter execution, then materialized into a
target row array without requiring ext/sqlite.

Status delta 2026-05-27 isolated `insert-select-current` slice:
`SQLiteInsertSelectSql` parses `INSERT INTO target [(columns)] SELECT ...`,
reuses the accepted SELECT SQL executor for row production, maps selected values
to explicit or inferred target columns, reports before/after rows and change
counts, and rejects unsupported conflict clauses, malformed target columns,
missing target tables, non-SELECT sources, empty inferred-column inserts, and
column-count mismatches. Focused `SQLiteHeaderTest.php` passed at 9561
assertions with 0 failures in the worker handoff, +45 over the previous
lane-status focused count of 9516.

Dependency closure: no new shared support component is needed. This reuses the
lane-local SELECT SQL parser/executor and copied Application option fixtures.
Follow-up should wire this row-array insert-select preview into lower-level
table/index page write planning without repeating accepted insert-or-replace,
SELECT SQL text, subquery, bind-parameter, or storage VFS clusters.

## UPDATE FROM Current Conflict Scenario

Copied Application `wp_options` staging diagnostics now expose bounded SQLite
`UPDATE ... FROM ...` current behavior. The smoke
`examples/application-update-from-conflict-current.php` reports duplicate staged
rows updating a target option once using the current SQLite last-match source
row, while `UPDATE OR REPLACE` deletes a current UNIQUE `option_name` conflict
before keeping the updated row, without requiring ext/sqlite.

Status delta 2026-05-27 isolated `update-from-conflict-current` slice:
`SQLiteUpdateFromSql` parses bounded `UPDATE [OR REPLACE] target SET ... FROM
source WHERE ...` row-array SQL, delegates joined target/source expression
evaluation to the accepted SELECT SQL executor, collapses duplicate source
matches to one update per target, and optionally applies current unique-column
conflict deletion. Focused `SQLiteHeaderTest.php` passed at 9701 assertions,
adding 41 assertions over the 9660-assertion base focused run.

Dependency closure: no new shared support component is needed. This reuses the
lane-local SELECT SQL parser/executor and copied Application option fixtures.
Follow-up should wire this row-array update preview into lower-level table/index
page write planning without repeating accepted INSERT OR REPLACE conflict
planning, UPDATE/DELETE LIMIT row selection, or this parser-level UPDATE FROM
current-conflict behavior.

## JSON Inspection SELECT SQL Scenario

Copied Application `wp_options` JSON diagnostics now expose parser-level SQLite
`json_type()` and `json_array_length()` expression dispatch. The smoke
`examples/application-json-inspection-select-sql.php` reports strict JSON and
JSONB plugin settings projected through SELECT SQL text with mode type/count
metadata, without requiring ext/sqlite.

Status delta 2026-05-27 isolated `json-inspection-select-sql` slice:
`SQLiteSelectExpression` dispatches `json_type()` and `json_array_length()`
through the existing native JSON inspection implementation after evaluating SQL
expression arguments. Focused `SQLiteJsonInspectionSqlTest.php` adds 33
TestRunner PASS cases covering strict JSON, JSON5, JSONB, cast-text BLOBs,
SQL BLOB literals, SQL NULL path handling, predicates, ORDER BY expressions,
aliases, uppercase function names, malformed text, malformed superficial JSONB,
and arity/type guards.

Dependency closure: no new shared support component is needed. This reuses the
lane-local JSON inspection, JSON5 parser, JSONB codec, `SQLiteBlobValue`, and
SELECT SQL parser/executor. Follow-up should target non-overlapping JSON
planner/JSONB behavior rather than accepted JSON table source/cursor/hidden or
visible-constraint clusters, or this inspection-function expression dispatch.
### 2026-05-27 ANALYZE sqlite_stat1 planner corpus

The `application-analyze-stat-planner.php` smoke reports copied Application
`wp_options` and `wp_postmeta` query probes choosing bounded native PHP index
plans from `ANALYZE` / `sqlite_stat1` cardinality before row decoding. This
keeps option-name, transient cleanup, and postmeta lookups deterministic on
hosts where `ext/sqlite` is unavailable.

Status delta 2026-05-27 isolated analyze/stat planner slice: added
`SQLiteAnalyzeStatPlanner`, `SQLiteAnalyzeStatPlannerCorpusTest.php`, and a
Application smoke. Focused verification:
`php tools/run-tests.php lanes/libsqlite/tests/SQLiteAnalyzeStatPlannerCorpusTest.php`
reported `1 test files, 105 assertions, 0 failures` with 50 PASS lines, so
`lane-status.json` `phpPass` moved from 1336 to 1386. Mapped upstream
denominator coverage is unchanged because this is a focused PHP corpus row, not
a newly hydrated upstream inventory unit. Non-overlap: this covers
ANALYZE/stat1 cardinality parsing and prefix index selection; it does not repeat
accepted expression-index range-cost ranking, SELECT expression `ORDER BY`,
JSON table constraints, VFS sync/write/lock application, WAL rollback/checkpoint
application, B-tree page relocation/root collapse/overflow freelist release, or
Unicode GLOB work. Dependency closure: no new support component is needed; the
slice reuses lane-local SELECT planner arrays and native PHP stat parsing only.

## FTS5 Option Search

`examples/application-fts5-option-search.php` previews copied `wp_options` text through bounded FTS5-style MATCH ranking and snippet diagnostics. It reports selected option ids, highlighted snippets, and ascending bm25-like ranks for `search cache` without requiring `ext/sqlite`.

## FTS5 Schema Import current next26

`examples/application-fts5-schema-import-current-next26.php` previews imported
Application FTS5 virtual-table DDL before full virtual-table execution. It
reports indexed and `UNINDEXED` columns, tokenizer options, prefix lengths,
external-content rebuild admission, content-rowid mapping, shadow-table names,
and contentless-table actions without requiring `ext/sqlite`.

Status delta 2026-05-27 isolated
`yield-sqlite-application-schema-import-fts-current-next26` slice: added
`SQLiteFts5SchemaImportPlan`, `SQLiteFts5SchemaImportCurrentNext26Test.php`,
and a Application smoke. Focused verification:
`php tools/run-tests.php lanes/libsqlite/tests/SQLiteFts5SchemaImportCurrentNext26Test.php`
reported `1 test files, 62 assertions, 0 failures` with 60 PASS lines, so
`lane-status.json` `phpPass` moved from 8739 to 8799. Mapped upstream denominator coverage moves
from 461 to 462 for the focused FTS5 virtual-table schema import row.

Dependency closure: no new shared support component is needed. This slice
reuses lane-local SQL tokenization and schema planning only. Follow-up should
wire imported FTS5 schema plans into parser-level virtual-table `MATCH`
execution or choose a distinct upstream FTS release blocker.
## JSON Scalar SQL Input Mutation next13 Scenario

Copied Application `wp_options` fixtures can contain numeric option values that
are later migrated into JSON plugin-setting documents. The
`application-json-scalar-input-mutation-next13.php` smoke reports those numeric
SQL values flowing through native `json_patch()`, `json_set()`,
`json_remove()`, and `jsonb_patch()` without `ext/sqlite`: object merge-patch
promotes a scalar into an object, root set replaces the scalar, nested set and
remove paths leave the scalar unchanged, root remove returns SQL NULL, and
`jsonb_patch()` preserves BLOB result typing.

Status delta 2026-05-27 isolated `yield-sqlite-json-patch-set-remove-edge-next13`
slice: added scalar SQL input normalization for JSON document arguments in
`SQLiteJsonPatch`, `SQLiteJsonMutation`, and `SQLiteJsonRemove`; added
`SQLiteJsonScalarInputMutationNext13Test.php` with 54 focused PASS cases; and
added the Application smoke. `lane-status.json` `phpPass` moved from 3796 to
3850. No mapped denominator change is claimed.

Dependency closure: no new shared support component is needed. This slice
reuses lane-local JSON parsing/JSONB/path mutation helpers and copied
Application option fixtures only. Follow-up should target non-overlapping JSON
planner/JSONB behavior rather than this scalar SQL input mutation boundary.

## WAL Reader Checkpoint Boundary current next19 Scenario

`examples/application-wal-reader-checkpoint-boundary.php` previews a copied
`wp_options` plugin-settings import that rolls back a savepoint, checkpoints
the retained WAL prefix, and compares current-reader visibility with the next
reader opened after the checkpoint. The current reader keeps WAL-backed page
images for retained frames, while the next reader sees the same content from
the checkpointed database image after `truncate_wal`; rolled-back plugin frames
remain invisible without requiring `ext/sqlite`.

Status delta 2026-05-27 isolated
`yield-sqlite-wal-reader-checkpoint-boundary-current-next19` slice: added
`SQLiteWalSavepointCheckpointPlan::readerBoundaryAfterRollbackTo()` with 58
focused PASS cases in `SQLiteWalReaderCheckpointBoundaryCurrentNext19Test.php`.
`lane-status.json` `phpPass` moved from 6444 to 6502. Mapped upstream
denominator coverage is unchanged because this is focused native PHP WAL
behavior growth, not a newly hydrated upstream inventory unit.

Dependency closure: no new shared support component is needed. This slice
reuses lane-local savepoint state, WAL parsing, and durable checkpoint helpers.

## JSONB Patch Indexed Generated current next27 Scenario

`examples/application-jsonb-patch-indexed-generated-current-next27.php` previews
copied `wp_options` imports that query generated columns derived from
`json_extract(jsonb_patch(option_value, PATCH), PATH)`. The planner chooses a
partial covering generated-column index for autoloaded plugin settings, reports
the canonical merge patch and JSON path, and keeps the behavior local to native
PHP without requiring `ext/sqlite`.

Status delta 2026-05-27 isolated
`yield-sqlite-jsonb-patch-indexed-generated-current-next27` slice: added
`SQLiteJsonbPatchGeneratedIndexPlan`, 53 focused PASS assertions in
`SQLiteJsonbPatchIndexedGeneratedCurrentNext27Test.php`, and the Application
smoke. `lane-status.json` `phpPass` moved from 9342 to 9395. Mapped upstream
denominator coverage is unchanged because this is focused native PHP planner
behavior, not a newly hydrated upstream inventory unit.

Dependency closure: no new support component is needed; this reuses the native
JSONB codec, JSON5 parser, merge-patch helper, JSON path validator, and
lane-local planner metadata. Non-overlap: this does not repeat JSON table
cursor/source/hidden/visible constraints, generic JSON scalar patch dispatch,
expression ORDER BY, expression-index range-cost, B-tree, WAL, or VFS clusters.

## Planner Skip-Scan Partial Current Next28

Copied `wp_options` plugin-option recovery scans can now use a partial
composite index such as `wp_options(autoload, option_name) WHERE kind='plugin'
AND option_name >= 'plugin_'` through skip-scan current/next loops. The planner
admits the partial index only when query terms imply the partial predicate,
filters the native PHP index image to rows that satisfy the predicate, and then
loops over each distinct unconstrained `autoload` prefix for the requested
`option_name` range. Unsafe broad predicates return an unusable plan so callers
can fall back to a table scan instead of reading an incomplete partial index.

Status delta 2026-05-27 isolated
`yield-sqlite-planner-skipscan-partial-current-next28` slice: added
`SQLiteIndexSkipScanPlan::betweenPartialRows()` plus
`SQLitePlannerSkipScanPartialCurrentNext28Test.php` with 54 focused PASS cases
and a Application smoke. No mapped denominator change is claimed.

Dependency closure: no new shared support component is needed. This reuses
lane-local `SQLiteIndexPredicate` proof logic and the existing skip-scan
current/next row materializer.

## JSON Validity SQL Scalar and Flag Coercion current next29 Scenario

`examples/application-json-validity-current-next29.php` previews copied
`wp_options` plugin settings validated through `json_valid(option_value,
flag_value)` where the option value may be JSON5 text, a JSONB BLOB, a
generated JSON subtype value, or a numeric SQL scalar. Row-provided flags use
SQLite-current coercion such as decimal strings, digit BLOBs, booleans, and
numeric prefixes before the `1..15` validity mask is applied, without requiring
`ext/sqlite`.

Status delta 2026-05-27 isolated
`yield-sqlite-jsonb-subtype-validity-current-next29` slice: added current
SQLite SQL-scalar JSON validity and flag coercion in `SQLiteJsonValidity`,
wired the expanded input set through `SQLiteSelectExpression`, added
`SQLiteJsonValidityCurrentNext29Test.php` with 44 focused PASS cases, and added
the Application smoke. `lane-status.json` `phpPass` moved from 10028 to 10072.
Mapped upstream denominator coverage is unchanged because this is focused
native PHP behavior growth, not a newly hydrated upstream inventory unit.

Dependency closure: no new shared support component is needed. This slice
reuses lane-local JSON validity, JSONB, JSON subtype, and SELECT expression
helpers. Follow-up should target non-overlapping JSON planner/JSONB behavior
rather than accepted JSON subtype admission, JSON table cursor/source/hidden or
visible constraints, or this `json_valid()` scalar/flag coercion boundary.
## Pager Master-Journal Reader Cache Current Source Next255

`examples/application-pager-master-journal-reader-cache-current-source-next255.php`
models a copied `wp_options` database after master-journal recovery. It keeps a
schema cache page only when the reader page-map digest was computed from the
recovered current source, reopens the stale options-root reader, and preserves
the older reader-snapshot fence for `active_plugins`.

Status delta 2026-05-28 isolated:
`pager-master-journal-reader-cache-current-source-next255` adds
`SQLitePagerMasterJournalReaderCacheCurrentSourceNext255Plan`, 62 focused PASS
assertions in `SQLitePagerMasterJournalReaderCacheCurrentSourceNext255Test.php`,
and the Application smoke. `lane-status.json` `phpPass` moves from `133054` to
`133116`. Mapped upstream coverage is unchanged.

Dependency closure: no new support component is needed; this reuses the
lane-local pager master-journal reader-cache family and existing current-source
snapshot/generation/provenance fences.
