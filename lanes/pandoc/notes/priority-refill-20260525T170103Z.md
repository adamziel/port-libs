# Pandoc priority refill 2026-05-25T17:01:03Z

## Slice

- Reworked the stale Space/SoftBreak/LineBreak Markdown writer handoff additively on top of the accepted Pandoc lane state.
- Preserved accepted table, definition-list, underline, caption, raw Markdown-family, Roman/alpha overflow, bullet-list marker, decimal zero-start, heading-attribute, and `softBreak => space` evidence.
- Added a direct regression for `MarkdownWriter` inline break emission: `space` emits a literal space, default `softbreak` emits a newline, `softBreak => space` emits a compact space, and `linebreak` emits a hard Markdown break.

## Verification

- `php -l lanes/pandoc/tests/MarkdownReaderTest.php` - passed.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` - passed: 1 test file, 2,311 assertions, 0 failures.
- Root harness: not run - isolated micro-slice.
- Upstream Haskell runner: not run; the lane still relies on cloned static inventory evidence.

## Dependency closure

No new support component is needed. This rework reuses the existing native Markdown inline writer path and the already accepted Markdown handoff example coverage.

## Next task

Map another bounded Markdown writer branch after the rebased break coverage, such as additional wrapping modes, definition-list reference/footnote placement boundaries, or table caption fallback edge cases with native upstream fixture parity.
