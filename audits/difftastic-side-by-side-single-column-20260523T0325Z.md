# Difftastic Side-by-Side Single-Column Evidence 2026-05-23T0325Z

Session: `port-difftastic`

Scope: native PHP Difftastic side-by-side display slice for upstream
created/deleted empty-side behavior, building on the current context-window
side-by-side batch.

## Upstream Basis

- `.upstream-cache/port-difftastic-rust-wide7-20260523T012456Z/difftastic/src/options.rs`
  documents `side-by-side` using a single column for exclusively additions or
  removals, and `side-by-side-show-both` always using two columns.
- `.upstream-cache/port-difftastic-rust-wide7-20260523T012456Z/difftastic/src/display/side_by_side.rs`
  implements `display_single_column`, the empty-LHS/empty-RHS switch, and a
  `test_display_single_column` smoke test.

## Commands Run

Focused new tests:

```text
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests = require "lanes/difftastic/tests/TokenDifferTest.php"; $names = ["maps upstream side by side created files as single column by default", "maps upstream side by side deleted files as single column by default", "maps upstream side by side show both mode for created files", "wordpress created import report side by side uses single column"]; $selected = array_intersect_key($tests, array_flip($names)); $runner = new TestRunner(); $runner->runTests($selected, "lanes/difftastic/tests/TokenDifferTest.php"); fwrite(STDOUT, "\nfocused assertions=" . $runner->assertions() . " failures=" . $runner->failures() . "\n"); exit($runner->failures() === 0 ? 0 : 1);'
```

Result:

```text
4 tests, 7 assertions, 0 failures
```

Full Difftastic file:

```text
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $runner = new TestRunner(); $tests = require "lanes/difftastic/tests/TokenDifferTest.php"; $runner->runTests($tests, "lanes/difftastic/tests/TokenDifferTest.php"); fwrite(STDOUT, "\n1 test file, " . $runner->assertions() . " assertions, " . $runner->failures() . " failures\n"); exit($runner->failures() === 0 ? 0 : 1);'
```

Result:

```text
147 tests, 773 assertions, 0 failures
```

Touched examples:

```text
php lanes/difftastic/examples/wordpress-pattern-context-side-by-side.php
php lanes/difftastic/examples/wordpress-created-import-report-side-by-side.php
```

Results:

```text
wordpress-pattern-context-side-by-side.php emitted 677 bytes
wordpress-created-import-report-side-by-side.php emitted 174 bytes
```

Root-suite availability check:

```text
ps -ef | rg "php tools/run-tests\.php|tools/run-tests\.php"
```

Result:

```text
claude 3324750 ... php tools/run-tests.php
claude 3324769 ... php tools/run-tests.php lanes/quadrable/tests/QuadbStoreTest.php
claude 3375937 ... php tools/run-tests.php
```

The required root suite was not started because another root harness was already
active when checked at 03:25 UTC, and another root harness was still active when
checked again at 03:28 UTC. The bounded alternative for this slice was the full
Difftastic test file plus the two touched examples.

## Behavior Added

- Created files (`old === ""`, `new !== ""`) render as one numbered source
  column by default.
- Deleted files (`new === ""`, `old !== ""`) render as one numbered source
  column by default.
- `showBoth => true` keeps a two-column display and pads the missing left side
  to the configured column width.
- Tab expansion is still applied to single-column output.
- The WordPress import-report example demonstrates created Data Liberation CSV
  output without a blank opposite column.

## Blockers

- Full upstream Rust runner parity remains unavailable for the previously
  recorded Cargo/dependency reasons.
- Root-green acceptance is not claimed in this artifact because a root PHP suite
  was already active in the shared checkout.
