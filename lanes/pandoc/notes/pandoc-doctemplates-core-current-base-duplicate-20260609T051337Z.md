# pandoc-doctemplates-core-current-base-duplicate-20260609T051337Z

## Source Truth

- Lane: `pandoc`
- Base accepted HEAD: `40ecdbe743809a1f1af99ee730ab306fb571c756`
- Upstream support source: `jgm/doctemplates` tag `0.11.0.1`, `test/nest.test`, plus `Text/DocTemplates/Parser.hs` nested template parsing.
- No lane rework note matched `port-pandoc-*.needs-lane-rework.md` before edits.

## Behavior

This slice fixes a bounded doctemplate parity gap from upstream `nest.test`.
When a template uses inline explicit nesting such as:

```text
$bim.zub$ $^$$foo$
          bar $sup$
```

upstream renders the literal continuation line at the active `$^$` nesting
column, not at the original surplus template-source indentation. The PHP
renderer previously produced ten spaces before `bar`; it now produces the
four-space active nesting column and still nests multiline `$sup$` continuation
lines at the same column.

The WordPress review-packet example now includes the same source-aligned
continuation smoke path so import review templates do not over-indent queued
follow-up lines.

## Evidence

- Baseline focused command before adding the red test:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 1135 assertions, 0 failures`
- Red-first focused command after adding the upstream-shaped assertion and
  before the renderer fix:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 1136 assertions, 1 failures`
  - Failure: expected `    bar a multiline`, actual `          bar a multiline`
- Final focused command after the renderer fix:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 1136 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/pandoc/src/DocTemplate.php` passed
  - `php -l lanes/pandoc/tests/DocTemplateTest.php` passed
  - `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php` passed
- JSON status/manifest validation:
  - `lanes/pandoc/lane-status.json OK`
  - `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json OK`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  - Result: passed with no output

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`DocTemplate` renderer, existing Unicode/display-width helpers, the focused PHP
test runner, and the existing WordPress doctemplate review-packet example smoke.
Full upstream Pandoc/doctemplates runner parity remains out of scope for this
slice and belongs to the upstream-runner dependency lane with hydrated pinned
upstream sources and Haskell test executables.

## Non-Overlap

This does not repeat recent doctemplate slices for block-pipe horizontal
composition, quoted pipe diagnostics, recursive bare partial loop sentinels, or
default template resources. It owns only source-aligned literal continuation
indentation after inline explicit nesting.
