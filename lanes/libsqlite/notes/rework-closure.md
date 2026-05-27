# Libsqlite Rework Closure Notes

## 2026-05-26 JSON Dispatch Rework Markers

This isolated closure slice checked the outstanding handoff rework markers:

- `port-libsqlite-20260525T071150Z.needs-lane-rework.md`
- `port-libsqlite-20260525T100407Z.needs-lane-rework.md`
- `port-libsqlite-current-rebase-20260525T054020Z-02383337bcf4.needs-lane-rework.md`
- `port-libsqlite-finisher-20260525T092629Z.needs-lane-rework.md`
- `port-libsqlite-rework-20260525T082910Z.needs-lane-rework.md`
- `port-libsqlite-rework-20260525T083258Z.needs-lane-rework.md`
- `port-libsqlite-rework-20260525T093834Z.needs-lane-rework.md`
- `port-libsqlite-rework-20260525T100451Z.needs-lane-rework.md`
- `port-libsqlite-rework-20260525T105622Z.needs-lane-rework.md`

Current accepted lane files already contain the requested rebased behavior:

- `SQLiteJsonCanonical::jsonSqlFunction()` and `jsonSqlFunctionArguments()` cover case-insensitive `json`/`jsonb` dispatch, SQL NULL propagation, JSON5 text, text BLOB fallback, JSONB passthrough, and malformed input rejection.
- `SQLiteJsonPretty::jsonPrettySqlFunction()` and `jsonPrettySqlFunctionArguments()` cover case-insensitive `json_pretty` dispatch, one-or-two argument SQL arity, scalar SQL coercion including booleans and whole REAL values, JSON subtype input, text/JSONB BLOB input, custom indentation, SQL NULL propagation, and invalid function-name rejection.
- `SQLiteJsonExtract::extractSqlFunction()` and `extractJsonArgumentSqlFunction()` preserve the accepted `json_extract`/`jsonb_extract` SQL result typing and constructor-argument subtype propagation.
- `examples/wordpress-json-canonical-option-preflight.php`, `examples/wordpress-json-pretty-option-review.php`, and `examples/wordpress-json-extract-subtype-option-diagnostics.php` retain the WordPress-visible smoke paths referenced by the stale rework markers.

The only additive behavior in this closure patch is a focused assertion that
direct `JSON_PRETTY` dispatch and argument-vector `json_pretty` dispatch remain
equivalent for a JSONB BLOB input with a BLOB custom indent. That guards the
conflict-prone rework boundary without changing manifest denominators.

Focused verification for this closure slice:

```sh
php -l lanes/libsqlite/src/SQLiteJsonPretty.php
php -l lanes/libsqlite/src/SQLiteJsonCanonical.php
php -l lanes/libsqlite/src/SQLiteJsonExtract.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/wordpress-json-pretty-option-review.php
php -l lanes/libsqlite/examples/wordpress-json-canonical-option-preflight.php
php -l lanes/libsqlite/examples/wordpress-json-extract-subtype-option-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-json-pretty-option-review.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
JSON canonicalization, JSON5 parsing, JSONB encoding/decoding, JSON subtype
wrappers, BLOB value wrappers, and SQL scalar coercion helpers without
activating shared support-library work.

## 2026-05-26 JSON Pretty NULL Wrapper-Indent Rework Refresh

This isolated priority refresh keeps the same outstanding rework-marker scope
and adds one bounded guard to the already accepted `json_pretty()` SQL-dispatch
cluster: when the first SQL argument is NULL, direct dispatch and
argument-vector dispatch now have focused assertions proving that BLOB and JSON
subtype indentation wrappers are ignored and the result remains SQL NULL. The
WordPress option review smoke reports the same NULL-with-wrapper-indent paths.

No new upstream denominator is claimed. The rework remains additive on top of
the accepted `json`/`jsonb`, `json_pretty`, and `json_extract`/`jsonb_extract`
dispatch behavior and exists to make the stale May 25 conflict boundary easier
for the clean integrator to accept without replaying old manifest/status text.

Focused verification for this refresh:

```sh
php -l lanes/libsqlite/src/SQLiteJsonPretty.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/wordpress-json-pretty-option-review.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-json-pretty-option-review.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses the
lane-local SQL NULL propagation path plus existing BLOB and JSON subtype wrapper
coercion for the optional indentation argument.

## 2026-05-26 JSON Table Duplicate Hidden Constraints

This isolated JSON table/window slice adds planner behavior for repeated hidden
`json` and `root` constraints on `json_each`/`json_tree`: only the first usable
hidden equality is consumed as the virtual-table argument vector, while later
duplicate hidden constraints remain residual filters. That keeps composed
WordPress query-builder predicates from silently retargeting expansion when a
second hidden `root` or `json` predicate conflicts with the selected argv.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 3918 assertions,
up from the lane-status recorded 3876 baseline (`+42`). The new assertions cover
duplicate `root` plan shape, conflicting residual suppression, matching
duplicate roots, projection/order after residual filtering, duplicate `json`
residuals, and unusable hidden predicates before later usable hidden argv
selection.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/wordpress-json-table-duplicate-hidden-constraints.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-json-table-duplicate-hidden-constraints.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses existing
lane-local JSON table planning, JSON path validation, JSONB/BLOB wrappers, and
residual predicate evaluation.

## 2026-05-27 B-tree Leaf Sibling Merge Materialization

This isolated planner/WAL/B-tree closure slice adds bounded B-tree leaf sibling
merge planning after delete underflow without repeating the accepted rebalance
summary-only work. `SQLiteBTreeLeafMergePlan` materializes merged table-leaf
and index-leaf pages from sibling page images, preserves rowid/record order,
reports the parent divider removal, and emits the obsolete right-sibling
free-page action for later freelist/pointer-map application.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 4628 assertions,
up from the lane-status recorded 4560 baseline (`+68`). The new assertions
cover table leaf merge page materialization, index leaf merge page
materialization, merged row/record order, free-space deltas, parent divider
metadata, obsolete sibling page actions, and malformed unordered/type/page
number guards.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteBTreeLeafMergePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/wordpress-btree-leaf-merge-plan.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-btree-leaf-merge-plan.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses existing
lane-local B-tree page headers, table/index leaf page assemblers, cell parsers,
record encoding, and free-space accounting; pointer-map/freelist application is
left as the next B-tree storage slice.

## 2026-05-27 B-tree Leaf Merge Pointer-Map Application

This isolated B-tree closure slice builds on accepted leaf merge materialization
without repeating the summary-only rebalance work. `SQLiteBTreeLeafMergeApplicationPlan`
composes the merged left sibling page with existing freelist free-page planning
so the obsolete right sibling is placed on the freelist and, for auto-vacuum
databases, its pointer-map entry is rewritten to `free-page`.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 4683 assertions,
up from the accepted 4628 baseline (`+55`). The new assertions cover table and
index leaf merge application, merged page images, freelist first-trunk/count
updates, auto-vacuum pointer-map page rewrites, obsolete sibling free-page
metadata, missing-page/page-size guards, and copied wp_options autoload-index
smoke diagnostics.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteBTreeLeafMergeApplicationPlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/wordpress-btree-leaf-merge-apply.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-btree-leaf-merge-apply.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses existing
lane-local B-tree leaf merge materialization, SQLiteDatabase freelist mutation,
pointer-map update planning, page header parsing, and record/cell encoders.
Next B-tree work should move to broader redistribution or parent divider/
rightmost write application.
