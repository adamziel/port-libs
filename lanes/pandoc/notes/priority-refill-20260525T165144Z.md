# Priority refill 2026-05-25T16:51Z

## Slice

- Rebased on accepted Pandoc lane evidence that already includes the stale rework note behaviors for Space/SoftBreak/LineBreak, table span degradation, and current manifest/status evidence.
- Added a bounded Markdown writer option: `softBreak => space` emits SoftBreak nodes as compact spaces for reviewer handoff Markdown.
- Preserved default SoftBreak newline output and hard LineBreak `\\` output, including table-cell normalization.
- Updated the WordPress Markdown review handoff example to exercise compact reviewer soft-break prose while retaining explicit hard line breaks.

## Evidence

- `php -l lanes/pandoc/src/MarkdownWriter.php` - passed.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php` - passed.
- `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php` - passed.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` - passed: 1 test file, 2,309 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php | rg -n "Reviewer spacing packet: soft boundary|hard boundary follows\\\\$|^next reviewer line$"` - passed; emitted compact soft-break prose and preserved the hard Markdown line break.

## Blocker

- Pandoc-local PHP blocker: none for this slice.
- Full upstream runner not executed. The upstream `test-pandoc` and `test-pandoc-lua-engine` executables still require a hydrated Haskell checkout and Cabal dependency build; this micro-slice uses the existing static upstream inventory and focused native PHP parity checks.
- Root harness not run - isolated micro-slice.

## Dependency Closure

- No new support component is needed. The slice reuses the existing native Markdown inline writer path and the existing WordPress Markdown handoff example path.

## Next Task

- Map another bounded Markdown writer branch after soft-break option coverage, such as additional wrapping modes, definition-list reference/footnote placement boundaries, or table caption fallback edge cases with native upstream fixture parity.
