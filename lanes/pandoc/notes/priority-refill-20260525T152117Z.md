# Priority refill 2026-05-25T15:21Z

## Slice

- Rebased on accepted Pandoc lane evidence that already includes the stale Space/SoftBreak/LineBreak rework-note behavior and later table/list writer slices.
- Added Markdown writer decimal ordered-list zero-start support: `start => 0` now emits `0.` followed by `1.` for decimal lists, while negative decimal starts clamp to `0` and non-decimal marker families still begin at their first valid marker.
- Updated the WordPress Markdown review handoff example with a zero-indexed import preflight queue.

## Evidence

- `php -l lanes/pandoc/src/MarkdownWriter.php` - passed.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php` - passed.
- `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php` - passed.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` - passed: 1 test file, 2,307 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php | rg -n "^0\\.  Zero-indexed import preflight|^1\\.  First publish step"` - passed; emitted the `0.` and `1.` reviewer rows.

## Blocker

- Pandoc-local PHP blocker: none for this slice.
- Full upstream runner not executed. The upstream `test-pandoc` and `test-pandoc-lua-engine` executables still require a hydrated Haskell checkout and Cabal dependency build; this micro-slice uses the existing static upstream inventory and focused native PHP parity checks.
- Root harness not run - isolated micro-slice.

## Dependency Closure

- No new support component is needed. The slice reuses the existing native Markdown ordered-list writer path and the existing WordPress Markdown handoff example path.

## Next Task

- Map another bounded Markdown writer branch after decimal zero-start ordered lists, such as wrapping/soft-break writer options, definition-list reference/footnote placement boundaries, or table caption fallback edge cases with native upstream fixture parity.
