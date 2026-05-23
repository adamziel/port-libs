# Difftastic Side-by-Side Novel Color Evidence 2026-05-23T0346Z

Session: `port-difftastic`

Scope: native PHP Difftastic side-by-side display slice for opt-in novel
line-number and intraline span coloring, focused on renderer/token display
semantics and WordPress review output.

## Upstream Basis

- `.upstream-cache/port-difftastic-rust-wide8-20260523T0340Z/difftastic/src/display/side_by_side.rs`
  defines `lines_with_novel`, `highlight_positions`, `highlight_as_novel`,
  `display_line_nums`, continuation line-number styling, and the side-by-side
  rendering path that applies novel styles to changed source positions.
- `.upstream-cache/port-difftastic-rust-wide8-20260523T0340Z/difftastic/src/display/style.rs`
  defines `novel_style`, `color_positions`, and `split_and_apply`, combining
  source byte spans with red/green novel-side ANSI styling.
- `.upstream-cache/port-difftastic-rust-wide8-20260523T0340Z/difftastic/src/display/hunks.rs`
  uses `lines_with_novel` when deciding which hunk line numbers contain novel
  content.
- `.upstream-cache/port-difftastic-rust-wide8-20260523T0340Z/difftastic/src/options.rs`
  defines `DisplayOptions::use_color`, `DisplayOptions::syntax_highlight`, and
  `side-by-side` / `side-by-side-show-both` display modes.
- Upstream test boundary: `src/display/side_by_side.rs::test_display_hunks`
  constructs novel `MatchedPos` ranges and exercises side-by-side display
  plumbing. No `sample_files/*` fixture specifically defines the ANSI novel
  color boundary; this slice is source-defined rather than fixture-golden.

## Behavior Added

- `SideBySideDiffRenderer::renderTextDiff()` accepts `useColor => true`.
- Changed line numbers are ANSI red on the left and ANSI green on the right.
- Intraline novel word spans are highlighted while stable prefixes, suffixes,
  and context lines remain uncolored.
- Created/deleted single-column side-by-side output also colors its numbered
  source column when `useColor` is enabled.
- The WordPress highlighted readme example applies this to plugin copy review,
  highlighting `legacy` / `modern` wording changes while retaining stable FAQ
  footer context.

## Commands Run

PHP lint:

```text
php -l lanes/difftastic/src/SideBySideDiffRenderer.php
php -l lanes/difftastic/tests/TokenDifferTest.php
php -l lanes/difftastic/examples/wordpress-highlighted-side-by-side.php
```

Result:

```text
No syntax errors detected in all 3 touched PHP files
```

Focused new tests:

```text
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests = require "lanes/difftastic/tests/TokenDifferTest.php"; $names = ["maps upstream side by side novel spans with ansi colors", "wordpress highlighted side by side review colors only changed copy"]; $selected = array_intersect_key($tests, array_flip($names)); $runner = new TestRunner(); $runner->runTests($selected, "lanes/difftastic/tests/TokenDifferTest.php"); fwrite(STDOUT, "\nfocused assertions=" . $runner->assertions() . " failures=" . $runner->failures() . "\n"); exit($runner->failures() === 0 ? 0 : 1);'
```

Result:

```text
2 tests, 10 assertions, 0 failures
```

Full Difftastic test file:

```text
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $runner = new TestRunner(); $tests = require "lanes/difftastic/tests/TokenDifferTest.php"; $runner->runTests($tests, "lanes/difftastic/tests/TokenDifferTest.php"); fwrite(STDOUT, "\n1 test file, " . count($tests) . " tests, " . $runner->assertions() . " assertions, " . $runner->failures() . " failures\n"); exit($runner->failures() === 0 ? 0 : 1);'
```

Result:

```text
1 test file, 149 tests, 783 assertions, 0 failures
```

Touched example:

```text
php lanes/difftastic/examples/wordpress-highlighted-side-by-side.php | wc -c
```

Result:

```text
395 bytes emitted
```

JSON validation:

```text
php -r 'foreach (["lanes/difftastic/UPSTREAM_TEST_MANIFEST.json", "lanes/difftastic/lane-status.json"] as $file) { json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " valid\n"; }'
```

Result:

```text
lanes/difftastic/UPSTREAM_TEST_MANIFEST.json valid
lanes/difftastic/lane-status.json valid
```

Whitespace check:

```text
git diff --check -- lanes/difftastic
```

Result: exit 0, no output.

Initial root-suite availability check:

```text
ps -ef | rg "php tools/run-tests\.php|tools/run-tests\.php"
```

Result:

```text
claude 3593471 ... php tools/run-tests.php lanes/rclone/tests
claude 3599536 ... php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/EquationReplacerTest.php
```

The required root suite was not started because worker-owned root-style PHP
suites were already active in the shared checkout at 03:47 UTC. The root slot
cleared after the lane-local checks, so the required root suite was run:

```text
timeout 900s php tools/run-tests.php
```

Result:

```text
181 test files, 17532 assertions, 0 failures
```

## Blockers

- Full upstream Rust runner parity remains unavailable for the previously
  recorded Cargo/dependency reasons.
- No Difftastic-local PHP or root-test blocker remains for this slice.
