# pandoc-docx-openxml-core-current-base-20260608T112335Z

## Scope

Implemented one bounded DOCX/OpenXML body parsing cluster: `w:moveToRangeStart` and `w:moveFromRangeStart` ranges now stay active across paragraph boundaries in `DocxReader`.

The visible accepted moved-to text is emitted as one reviewer span per paragraph segment, preserving `data-docx-change`, id, author, date, and move range name metadata. Suppressed moved-from source text remains out of the AST, Markdown, and WordPress block output until the matching range end is seen.

## Source Truth

No hydrated Pandoc upstream checkout was present at `/home/claude/port-libs/.upstream-cache/pandoc`, so this slice used the lane's existing static upstream inventory and current DOCX/OpenXML `DocxReader` format contract as source truth. This is a native PHP support-library handoff and did not invoke Pandoc, Word, LibreOffice, zip/unzip, Cabal, Haskell runners, external office tools, online services, live provider tests, or live-service provider tests.

## Evidence

- Baseline focused run before edits:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 2526 assertions, 0 failures`
- Red-first after adding the cross-paragraph move-range fixture:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 2538 assertions, 1 failures`
  - Failure: second moved-to paragraph rendered as plain `text` instead of a `span`.
- Final focused run:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 2553 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - `docx body handoff self-test ok`

## Dependency Closure

No new support component is needed. The slice reuses the native `DocxReader` WordprocessingML traversal, in-process DOCX OPC fixtures, `MarkdownWriter` bracketed-span output, and `WordPressBlockWriter` safe span attributes.

## Non-Overlap

This does not repeat the 2026-06-08 cross-paragraph proof-error/permission-range handoff. It applies the same paragraph-boundary state pattern to DOCX tracked move ranges and keeps the behavior bounded to DOCX body parsing.

## Follow-Up

Next DOCX/OpenXML work should choose a non-overlapping gap such as cross-container reviewer ranges, section-linked header/footer edge cases, or additional field/revision metadata.
