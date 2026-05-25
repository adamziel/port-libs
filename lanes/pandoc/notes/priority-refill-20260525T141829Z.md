# priority-refill-20260525T141829Z

## Status delta

- Preserved accepted Markdown writer Space/SoftBreak/LineBreak, table, definition-list, underline, short-caption, raw Markdown-family alias, Roman overflow, and heading-attribute evidence.
- Added bounded Markdown writer support for extension-qualified raw Markdown family formats. `MarkdownWriter` now treats `markdown`, `commonmark`, `commonmark_x`, `gfm`, and related Markdown-family raw formats with `+` or `-` extension suffixes as Markdown-preserving raw output.
- Added focused coverage for extension-qualified raw inlines and blocks such as `markdown+tex_math_dollars`, `markdown+pipe_tables`, `commonmark_x-smart`, `gfm+pipe_tables`, and `gfm+task_lists`, while incompatible raw HTML remains suppressed in Markdown output.
- Updated the WordPress reviewer handoff example so extension-qualified raw Markdown appears on a user-visible import path.

## Focused evidence

- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed: 1 file, 2,302 assertions, 0 failures.
- Pending final verification in this worktree: PHP syntax checks, example smoke, and `git diff --check -- lanes/pandoc`.

## Blocker

- Pandoc-local PHP blocker: none for this slice.
- Full upstream Pandoc runner remains unexecuted. The upstream test-pandoc/test-pandoc-lua-engine suites require building Haskell Tasty executables from a hydrated checkout and dependency graph; this isolated micro-slice uses the existing cloned static inventory plus focused PHP parity checks.
- Root harness not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This slice reuses the existing native Markdown raw block/inline writer path and only expands format-family matching for Pandoc's Markdown-family raw format names with extension suffixes.

## Next task

Map another bounded Markdown writer branch, preferably writer option variants, definition-list reference/footnote placement boundaries, or table caption fallback edge cases with native upstream fixture parity.
