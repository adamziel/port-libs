# Pandoc Priority Keeper Rework - 2026-05-25 09:38 UTC

## Rework Target

This isolated lane started from accepted HEAD
`5f21ea9c01aa38e54c79de7b6622eb3b7884eb38` and prioritized the stale Pandoc
handoff markers for Markdown writer `Space`, `SoftBreak`, and `LineBreak`
inline emission.

The accepted worktree already contained direct paragraph, nested
emphasis/strong, and blockquote coverage. This rework keeps that behavior and
adds the same inline-break cluster in list item context, where continuation
lines must remain under the list marker instead of starting as fresh list
items.

## Behavior Added

The native `MarkdownWriter` now treats `space` as an inline node for list item
grouping and renders multiline inline content through a shared list-item helper.
That keeps:

- `space` as one literal source space.
- `softbreak` as a physical Markdown newline.
- `linebreak` as Pandoc's backslash-newline hard-break marker.
- continuation lines indented under bullet and ordered-list markers.

## Dependency Closure

No new support component is needed. This rework reuses the existing Markdown
inline renderer, list renderer, blockquote renderer, delimiter helpers, and
block writer newline handling. It does not activate DOCX/OpenXML, legacy
DOC/CFB, PDF, EPUB/ODT, citation, math, YAML/JSON metadata, archive,
compression, Unicode, or charset support rows.

## Verification

Focused verification for this worktree:

- `php -l lanes/pandoc/src/MarkdownWriter.php` - passed.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php` - passed.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` - passed,
  1 test file, 2,292 assertions, 0 failures.

Root verification was not run for this isolated micro-slice.

## Next Task

Map another bounded Markdown writer branch after the accepted
Space/SoftBreak/LineBreak and line-block evidence, such as multi-block
table-cell fallback, table span degradation policy, or additional raw block
format variants with native upstream fixture parity.
