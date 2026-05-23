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
