# Pandoc Priority Refill - 2026-05-25 09:50 UTC

## Behavior Target

The stale Space/SoftBreak/LineBreak rework markers were already represented in
the accepted lane code and tests in this worktree, including list item
continuation coverage. This refill therefore adds the next bounded Markdown
writer branch named by the lane notes: table span degradation for pipe-table
output.

## Behavior Added

The native `MarkdownWriter` now expands table cells with `colspan` and
`rowspan` metadata into rectangular Markdown pipe-table rows. Because Pandoc
Markdown pipe tables cannot encode structural spans directly, the degradation
policy is:

- Keep the spanned cell's rendered content in the first covered column.
- Emit empty placeholder cells for covered colspan columns.
- Emit empty placeholder cells in subsequent rows covered by rowspan metadata.
- Preserve existing column alignment, width padding, escaped pipe characters,
  and caption rendering.

The focused test covers a headed table with a two-column header span, a
two-row body span, escaped pipes in body text, and a full-width reviewer note.

## Dependency Closure

No new support component is needed. The slice reuses the existing lane-local
table AST, Markdown inline renderer, pipe-table width/alignment logic, caption
renderer, and table-cell newline/pipe escaping. It does not activate DOCX,
legacy DOC/CFB, PDF, EPUB/ODT, citation, YAML/JSON metadata, archive,
compression, Unicode, or charset support rows.

## Verification

Focused verification for this worktree:

- `php -l lanes/pandoc/src/MarkdownWriter.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
- `git diff --check -- lanes/pandoc`

Root verification was not run for this isolated micro-slice.

## Next Task

Map another bounded Markdown writer branch after pipe-table span degradation,
such as multi-block table-cell fallback or additional raw block format variants
with native upstream fixture parity.
