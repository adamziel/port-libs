# priority-keeper-rework-20260525T104921Z

## Status delta

- Reworked on top of the accepted Pandoc Markdown writer evidence instead of replaying stale Space/SoftBreak/LineBreak, table-span, definition-list, or underline patches.
- Added native Markdown writer support for table short captions: tables with `shortCaptionInlines` or `shortCaption` now emit Pandoc-compatible bracketed short captions before the long caption.
- Updated the WordPress Markdown review handoff example so the visible pipe-table handoff path emits `: [Review **queue**] Migration **review** queue`.

## Focused evidence

- `php -l lanes/pandoc/src/MarkdownWriter.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
- `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- `git diff --check -- lanes/pandoc`

## Blocker

- Pandoc-local PHP blocker: none for this slice.
- Full upstream Pandoc runner remains unexecuted; the lane still relies on cloned static upstream inventory plus focused native PHP parity checks.
- Root harness not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This slice reuses the existing Pandoc AST,
Markdown table renderer, inline renderer, and existing escaping helpers. It does
not activate DOCX/OpenXML, legacy DOC/CFB, PDF, EPUB/ODT, citation, math,
YAML/JSON metadata, archive, compression, or charset support rows.

## Next task

Map another bounded Markdown writer branch after table short-caption output, such
as additional raw block format variants, definition-list reference/footnote
placement boundaries, or table caption fallback edge cases with native upstream
fixture parity.
