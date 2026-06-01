# libsqlite Root Harness Notes

## Isolated JSON Aggregate Distinct Slice

Date: 2026-05-26

Focused lane verification for the `json_group_array(DISTINCT X)` helper slice
passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 2248 assertions, 0 failures.

The root no-argument harness was not run for this isolated micro-slice.

Date: 2026-05-23

Focused lane verification for the rowid-range slice passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1272 assertions, 0 failures.

The required duplicate-root preflight was run before the root harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned no active exact root process at that moment. The subsequent root
run initially reported a lock wait, then acquired the lock and completed red:

```sh
php tools/run-tests.php
```

Result observed by this worker: 198 test files, 21844 assertions, 1 failure.
The failure detail was outside the captured output tail. A filtered duplicate
rerun was not started because later preflights reported active root-harness
processes, most recently PID `2107158` running `php tools/run-tests.php` as
user `claude`.

## Multi-Page Table Replacement Slice

Focused lane verification for the multi-page table-root replacement slice
passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1282 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-multipage-table-option-replacement-plan.php
```

It reported updated page `[4]`, an unchanged `table-interior` root at page 2,
and a rewritten `blogname` option with `autoload='no'`.

The required duplicate-root preflight was run before the aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned no active exact root process, so this worker ran:

```sh
php tools/run-tests.php
```

Result observed by this worker: 198 test files, 22059 assertions, 0 failures.

## Table Leaf Split Replacement Slice

Focused lane verification for the table-leaf split replacement slice passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1304 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-table-leaf-split-option-replacement-plan.php
```

It reported updated page images `[1,2,3,5]`, database page count `5`, root
table separators for page 3 up to rowid 1 and page 5 up to rowid 3, and a
rewritten `blogname` option with `autoload='no'`.

The required duplicate-root preflight was run before the aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned no active exact root process, so this worker ran:

```sh
php tools/run-tests.php
```

Result observed by this worker: 198 test files, 22295 assertions, 0 failures.

## Table Root Leaf Growth Replacement Slice

Focused lane verification for the table-root leaf growth replacement slice
passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1321 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-table-root-split-option-replacement-plan.php
```

It reported updated page images `[1,2,3,4]`, database page count `4`, a
`table-interior` root at page 2, split leaf pages 3 and 4 with 1 and 2 cells,
and a rewritten `blogname` option with `autoload='no'`.

The required duplicate-root preflight was run before the aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It initially returned active root PID `2482310 php tools/run-tests.php`, but
that process exited before owner sampling. A second exact preflight returned
no active root process, so this worker ran:

```sh
php tools/run-tests.php
```

Result observed by this worker: 199 test files, 22444 assertions, 0 failures.

## Composite Parent-Root Replacement Split Slice

Focused lane verification for the composite parent-root replacement split
slice passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1340 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-composite-index-parent-root-split-option-replacement-plan.php
```

It reported updated page images `[1,2,3,4,10,11,12,13]`, database page count
`13`, split destination composite-index leaf pages 4 and 11, source leaf page
10 with 5 remaining cells, two new interior pages, and a rewritten option
with `autoload='no'`.

The required duplicate-root preflight was run before the aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned no active exact root process. The first aggregate run completed
red with the failure detail outside the captured output:

```sh
php tools/run-tests.php
```

Result observed by this worker: 199 test files, 22652 assertions, 1 failure.

A second duplicate-root preflight was also clear, so this worker reran the
full aggregate harness with bounded output:

```sh
php tools/run-tests.php 2>&1 | awk '/^FAIL / || /^[0-9]+ test files,/'
```

Result observed by this worker: 199 test files, 22669 assertions, 0 failures.

## Non-Root Table Parent Replacement Split Slice

Focused lane verification for the non-root table parent replacement split
slice passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1357 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-nonroot-table-split-option-replacement-plan.php
```

It reported updated page images `[1,3,5,7]`, database page count `7`, an
unchanged root separator on page 2, a lower non-root table parent with
separators `(4,2)` and `(5,3)`, new right-most leaf page 7, and a rewritten
`blogname` option with `autoload='no'`.

The required duplicate-root preflight was run before any aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned active root harness PID `2604228 php tools/run-tests.php` and a
concurrent focused readability lane command. Owner sampling showed PID
`2604228` running as user `claude` with command `php tools/run-tests.php`, so
this worker did not start a duplicate root harness. The aggregate result is
pending supervisor/integrator acceptance of the active run.

## Table Root Parent Replacement Split Slice

Focused lane verification for the table root parent replacement split slice
passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1379 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-table-parent-root-split-option-replacement-plan.php
```

It reported updated page images `[1,2,36,37,38,39]`, database page count `39`,
a one-cell `table-interior` root pointing at two new lower interior parent
pages, split leaf pages 36 and 37, and a rewritten `blogname` option with
`autoload='no'`.

The required duplicate-root preflight was run before the aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned no active exact root process, so this worker ran:

```sh
php tools/run-tests.php
```

Result observed by this worker: 200 test files, 22989 assertions, 0 failures.

## Non-Root Table Parent Overflow Replacement Slice

Focused lane verification for the non-root table parent overflow replacement
slice passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1401 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-nonroot-table-parent-split-option-replacement-plan.php
```

It reported updated page images `[1,2,3,37,39,40]`, database page count `40`,
a two-cell `table-interior` root, split non-root table parent pages with 16
and 17 cells, split target leaves, and a rewritten `blogname` option with
`autoload='no'`.

The required duplicate-root preflight was run before any aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned active root harness PID `2663027 php tools/run-tests.php`. Owner
sampling showed PID `2663027` running as user `claude` with command
`php tools/run-tests.php`, elapsed `00:26`, so this worker did not start a
duplicate root harness. The aggregate result is pending supervisor/integrator
acceptance of the active run.

## Non-Root Index Parent Insert Split Slice

Focused lane verification for the non-root index parent insert split slice
passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1421 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-nonroot-index-parent-split-option-insert-plan.php
```

It reported updated page images `[1,2,3,4,11,13,14]`, database page count
`14`, a two-cell `index-interior` root, split non-root index parent pages
with three cells each, split target leaves with three cells each, and an
inserted autoloaded option reachable through the composite secondary index.

The required duplicate-root preflight was run before any aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned active root harness PID `2694170 php tools/run-tests.php`. Owner
sampling showed PID `2694170` running as user `claude` with command
`php tools/run-tests.php`, elapsed `00:16`, so this worker did not start a
duplicate root harness. The aggregate result is pending supervisor/integrator
acceptance of the active run.

## Composite Index Root Collapse Replacement Slice

Focused lane verification for the composite-index root-collapse replacement
slice passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1433 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-index-root-collapse-option-replacement-plan.php
```

It reported updated page images `[1,2,3,4]`, an `index-leaf` root at page 3,
obsolete child pages `[4,5]` on the freelist, and a rewritten `siteurl`
option with `autoload='no'`.

The required duplicate-root preflight was run before any aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned active root harness PID `2762418 php tools/run-tests.php`. Owner
sampling showed PID `2762418` running as user `claude` with command
`php tools/run-tests.php`, elapsed `00:25`, so this worker did not start a
duplicate root harness. The aggregate result is pending supervisor/integrator
acceptance of the active run.

## Auto-Vacuum Pointer Map Slice

Focused lane verification for the auto-vacuum pointer-map metadata slice
passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1456 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-pointer-map-diagnostics.php
```

It reported auto-vacuum and incremental-vacuum header state, page 2 as the
pointer-map page, root/free/btree/overflow pointer-map entries for pages 3
through 7, and a readable `siteurl` row from `wp_options`.

The required duplicate-root preflight was run before any aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned no active exact `php tools/run-tests.php` process, so this worker
started the root harness:

```sh
php tools/run-tests.php
```

Result: 205 test files, 23753 assertions, 0 failures.

## Multi-Sibling Index Leaf Redistribution Replacement Slice

Focused lane verification for the multi-sibling composite-index redistribution
replacement slice passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1470 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-index-redistribute-option-replacement-plan.php
```

It reported updated page images `[2,3,4,5,6]`, a two-cell `index-interior`
root, redistributed source/sibling index leaves with three cells each, a
destination leaf with four cells, and a rewritten long cached option reachable
through `wordpressOptionByIndexedAutoloadAndName('no', $optionName)`.

The required duplicate-root preflight was run before any aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It first returned active root harness PID `2961330 php tools/run-tests.php`,
but that process exited before owner sampling. A second exact preflight
returned no active root process, so this worker ran:

```sh
php tools/run-tests.php
```

Result observed by this worker: 207 test files, 23960 assertions, 2 failures.
Both visible failures were outside libsqlite in
`lanes/quadrable/tests/QuadbStoreTest.php`: `native quadb store emits and
applies tracked string-key patch lines` and `native quadb store honors
noTrackKeys for export diff dump and full-key proofs`, each failing because
`PortLibs\Quadrable\QuadbStore::diffNodeIds()` is undefined. The libsqlite
focused harness remains green; aggregate acceptance is blocked on the
unrelated Quadrable root-harness failures.

## Root-Parent Index Leaf Merge Replacement Slice

Focused lane verification for the root-parent composite-index merge
replacement slice passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1487 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-index-merge-option-replacement-plan.php
```

It reported updated page images `[1,2,3,4,5,6]`, a one-cell
`index-interior` root at page 3, merged index leaf pages 4 and 5, page 6 as
the first freelist trunk, and the rewritten option reachable through
`wordpressOptionByIndexedAutoloadAndName('no', $optionName)`.

The required duplicate-root preflight was run before the aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned no active exact root process, so this worker ran:

```sh
php tools/run-tests.php
```

Result observed by this worker: 210 test files, 24139 assertions, 0 failures.

## Auto-Vacuum Pointer-Map Mutation Slice

Focused lane verification for the auto-vacuum pointer-map mutation planning
slice passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1508 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-pointer-map-mutation-plan.php
```

It reported updated page images `[1,2,6]`, page 6 as the new freelist trunk,
the pointer-map entry for page 6 rewritten to `free-page`, and the existing
`siteurl` row still readable through the native table reader.

The focused upstream SQLite runner also passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick autovacuum.test incrvacuum.test
```

Result: 0 errors out of 507 tests.

The required duplicate-root preflight was run before any aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned active exact root harness processes:

```text
3056982 php tools/run-tests.php
3057590 php tools/run-tests.php
```

Owner sampling showed both owned by `claude`:

```text
PID      USER    ELAPSED CMD
3056982 claude  00:15   php tools/run-tests.php
3057590 claude  00:13   php tools/run-tests.php
```

This worker did not start a duplicate root harness. Aggregate root status is
pending supervisor/integrator acceptance of the active run.

## Non-Root Composite Index Leaf Merge Slice

Focused lane verification for the non-root composite-index source-leaf merge
slice passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests
```

Result: 1 test file, 1555 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-nonroot-index-merge-option-replacement-plan.php
```

It reported updated page images `[1,2,4,5,8,9]`, lower index parent page 4
with 3 cells and right-most pointer 8, merged leaf page 8 with 3 cells,
obsolete page 9 on the freelist, and a readable rewritten option with
`autoload='no'`.

The focused upstream SQLite runner also passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test index.test btree01.test
```

Result: 0 errors out of 761 tests.

The required duplicate-root preflight was run before the aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned no active exact root process, so this worker ran:

```sh
php tools/run-tests.php
```

Result: 216 test files, 25005 assertions, 0 failures.

## Auto-Vacuum Overflow Insert Pointer-Map Slice

Focused lane verification for the auto-vacuum overflow insert pointer-map
slice passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1519 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-autovacuum-overflow-option-insert-plan.php
```

It reported updated page images `[1,2,3,4,5,6]`, overflow pages `[4,5,6]`,
pointer-map entries for the first overflow page pointing to page 3 and
continuation overflow pages pointing to pages 4 and 5, and a readable
`theme_mods_twentyfive` option.

The focused upstream SQLite runner also passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  autovacuum.test incrvacuum.test insert.test corrupt3.test
```

Result: 0 errors out of 839 tests.

The required duplicate-root preflight was run before the aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned no active exact root process, so this worker ran:

```sh
php tools/run-tests.php
```

Result: 213 test files, 24527 assertions, 0 failures.

## Auto-Vacuum Overflow Replacement Pointer-Map Slice

Focused lane verification for the auto-vacuum overflow replacement pointer-map
slice passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1539 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-autovacuum-overflow-option-replacement-plan.php
```

It reported updated page images `[1,2,3,4,6,7,8,9]`, obsolete overflow pages
`[4,5]` rewritten to `free-page` pointer-map entries, new overflow pages
`[6,7,8,9]` with parent links `3,6,7,8`, and a readable rewritten
`theme_mods_twentyfive` option.

The focused upstream SQLite runner also passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  autovacuum.test incrvacuum.test update.test corrupt3.test
```

Result: 0 errors out of 791 tests.

The required duplicate-root preflight was run before any aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned active exact root harness PID `3188250 php tools/run-tests.php`.
Owner sampling showed it was owned by `claude`:

```text
PID      USER    ELAPSED CMD
3188250 claude  00:47   php tools/run-tests.php
```

This worker did not start a duplicate root harness. Aggregate root status is
pending supervisor/integrator acceptance of the active run.

## Auto-Vacuum B-Tree Pointer-Map Slice

Focused lane verification for the auto-vacuum b-tree pointer-map ownership
slice passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1564 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-autovacuum-table-root-split-option-replacement-plan.php
```

It reported updated page images `[1,2,3,4,5]`, a table-interior `wp_options`
root at page 3, new child leaf pages 4 and 5, and `btree-page` pointer-map
entries for pages 4 and 5 pointing back to page 3.

The focused upstream SQLite runner also passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  autovacuum.test incrvacuum.test update.test btree01.test
```

Result: 0 errors out of 997 tests.

The required duplicate-root preflight was run before any aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned active exact root harness PID `3336966 php tools/run-tests.php`.
Owner sampling showed it was owned by `claude`:

```text
PID      USER    ELAPSED CMD
3336966 claude  00:27   php tools/run-tests.php
```

This worker did not start a duplicate root harness. Aggregate root status is
pending supervisor/integrator acceptance of the active run.

## Secure-Delete Page-Free Slice

Focused lane verification for the secure-delete page-free slice passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1578 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-secure-delete-obsolete-overflow-pages.php
```

It reported updated page images `[1,2,3,4]`, obsolete overflow pages `[3,4]`
on the freelist, zeroed obsolete overflow page `[4]`, and a readable
rewritten `obsolete_large_cache` option.

The focused upstream SQLite runner also passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  securedel.test securedel2.test delete.test update.test
```

Result: 0 errors out of 821 tests.

The required duplicate-root preflight was run before the aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned no active exact root process, so this worker ran:

```sh
php tools/run-tests.php
```

Result: 219 test files, 25359 assertions, 0 failures.

## Non-Root Composite Index Parent Collapse Slice

Focused lane verification for the non-root composite-index parent collapse
slice passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1596 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-index-parent-collapse-option-replacement-plan.php
```

It reported updated page images `[1,2,3,5,6,7]`, a collapsed `index-interior`
root at page 3 with left children `[5,6,9]`, merged leaf page 6 with 3 cells,
obsolete pages `[7,4,8]` on the freelist, and a readable rewritten option.

The focused upstream SQLite runner also passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test index.test btree01.test delete2.test delete3.test delete4.test
```

Result: 0 errors out of 804 tests.

The required duplicate-root preflight was run before the aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned no active exact root process, so this worker ran:

```sh
php tools/run-tests.php
```

Result: 223 test files, 25567 assertions, 0 failures.

## Multi-Child Root Composite Index Parent Merge Slice

Focused lane verification for the multi-child-root composite-index parent
merge slice passed:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: 1 test file, 1617 assertions, 0 failures.

The WordPress example also ran successfully:

```sh
php lanes/libsqlite/examples/wordpress-index-parent-merge-option-replacement-plan.php
```

It reported updated page images `[1,2,3,4,5,6,7]`, an `index-interior` root
at page 3 with left child `[4]` and right-most pointer 11, merged lower parent
page 4 with left children `[5,6,9]`, obsolete pages `[7,8]` on the freelist,
and a readable rewritten option.

The focused upstream SQLite runner also passed:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  update.test index.test btree01.test delete2.test delete3.test delete4.test
```

Result: 0 errors out of 804 tests.

The required duplicate-root preflight was run before the aggregate harness:

```sh
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned no active exact root process, so this worker ran:

```sh
php tools/run-tests.php
```

Result: 224 test files, 25764 assertions, 0 failures.
