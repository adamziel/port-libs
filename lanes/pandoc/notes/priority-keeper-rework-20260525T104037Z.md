# priority-keeper-rework-20260525T104037Z

## Status delta

- Reworked on top of accepted pandoc Markdown writer evidence instead of replaying stale Space/SoftBreak/LineBreak, table-span, or definition-list patches.
- Added native Markdown writer support for `underline` inline nodes as Pandoc-compatible attributed bracketed spans.
- Updated the WordPress Markdown review handoff example with an attributed underline reviewer note so the user-visible handoff path exercises the new writer behavior.

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

No new support component is needed. This slice reuses the existing Pandoc AST, Markdown inline renderer, and attribute tuple renderer. It does not activate DOCX/OpenXML, legacy DOC/CFB, PDF, EPUB/ODT, citation, math, YAML/JSON metadata, archive, compression, or charset support rows.

## Next task

Map another bounded Markdown writer branch after underline output, such as table short-caption writer edge cases, additional raw block format variants, or definition-list reference/footnote placement boundaries with native upstream fixture parity.
