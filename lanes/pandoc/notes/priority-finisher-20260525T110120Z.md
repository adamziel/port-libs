# priority-finisher-20260525T110120Z

## Status delta

- Reworked additively on top of the accepted Pandoc Markdown writer evidence instead of replaying stale Space/SoftBreak/LineBreak or table patches.
- Added native Markdown writer support for short-only table captions: `shortCaptionInlines` and escaped fallback `shortCaption` values now emit bracketed Pandoc captions without a dangling long-caption space.
- Updated the WordPress Markdown review handoff example so the visible pipe-table handoff path emits `: [Short-only **queue**]`.

## Focused evidence

- `php -l lanes/pandoc/src/MarkdownWriter.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
- `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php | rg -n "Short-only|Review|Migration review queue|Raw reviewer"`
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

Map another bounded Markdown writer branch after short-only table caption
fallback, such as additional raw block format variants, definition-list
reference/footnote placement boundaries, or more table caption fallback edge
cases with native upstream fixture parity.
