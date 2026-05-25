# Pandoc Priority Keeper 2026-05-25T12:28:34Z

## Slice

- Rebased on the accepted pandoc baseline in this isolated worktree.
- Preserved accepted Space/SoftBreak/LineBreak, table, definition-list, underline, short-caption, and raw Markdown-family alias evidence.
- Added bounded `Text.Pandoc.Writers.Markdown` heading attribute coverage: MarkdownWriter now emits id, class, and key-value attributes for ATX and setext headings while preserving inline heading markup.
- Updated the WordPress Markdown reviewer handoff example with an attributed review-packet heading.

## Evidence

- `php -l lanes/pandoc/src/MarkdownWriter.php` passed.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed: 1 file, 2,301 assertions, 0 failures.
- Local example smoke emitted `Review Packet {#review-packet .wp-import .needs-review data-source="batch-42"}`.
- `git diff --check -- lanes/pandoc` passed.

## Blocker

- Pandoc-local PHP blocker: none for this slice.
- Root harness not run - isolated micro-slice.
- Full upstream Pandoc runner remains unexecuted because upstream `test-pandoc` and `test-pandoc-lua-engine` require a hydrated Haskell checkout plus Cabal dependency build.

## Dependency Closure

- No new support component is needed. This slice reuses the existing Pandoc AST attributes and MarkdownWriter attribute tuple renderer.

## Next Task

- Map another bounded Markdown writer branch after heading attributes, such as additional writer option variants, definition-list reference/footnote placement boundaries, or table caption fallback edge cases with native upstream fixture parity.
