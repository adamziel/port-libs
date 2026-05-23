# libsqlite Root Harness Notes

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
