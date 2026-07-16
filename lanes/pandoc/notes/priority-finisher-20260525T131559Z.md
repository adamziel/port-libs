# Pandoc Priority Finisher 2026-05-25T13:15:59Z

## Slice

- Rebased on the accepted pandoc baseline in this isolated worktree.
- Preserved accepted Space/SoftBreak/LineBreak, table, definition-list, underline, short-caption, raw Markdown-family alias, and heading-attribute evidence.
- Added bounded `Text.Pandoc.Shared` `orderedListMarkers` coverage: MarkdownWriter now emits `?` for upper/lower Roman ordered-list markers at 4000 and above, matching upstream's `toRomanNumeral` bound.
- Updated the WordPress Markdown reviewer handoff example with a Roman reviewer packet list that crosses the overflow boundary.

## Evidence

- `php -l lanes/pandoc/src/MarkdownWriter.php` passed.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed: 1 file, 2,302 assertions, 0 failures.
- Local example smoke emitted `MMMCMXCIX. Final Roman reviewer packet before overflow` and `?.  Overflow reviewer packet keeps Pandoc marker semantics`.
- `git diff --check -- lanes/pandoc` passed.

## Blocker

- Pandoc-local PHP blocker: none for this slice.
- Root harness not run - isolated micro-slice.
- Full upstream Pandoc runner remains unexecuted because upstream `test-pandoc` and `test-pandoc-lua-engine` require a hydrated Haskell checkout plus Cabal dependency build.

## Dependency Closure

- No new support component is needed. This slice reuses the existing ordered-list marker renderer and Markdown handoff example path.

## Next Task

- Map another bounded Markdown writer branch after Roman list overflow, such as additional writer option variants, definition-list reference/footnote placement boundaries, or table caption fallback edge cases with native upstream fixture parity.
