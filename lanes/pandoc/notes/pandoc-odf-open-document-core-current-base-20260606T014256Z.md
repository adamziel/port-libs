# ODF OpenDocument Core Placeholder Handoff

Slice: `pandoc-odf-open-document-core-current-base-20260606T014256Z`
Base: `574bee50882c28fc71f3d812f497f9a400759fcd`

## Behavior

- Added bounded native `text:placeholder` handling in `OdfReader`.
- Preserves placeholder source text in paragraph and heading inline content instead of dropping it.
- Emits review spans with `odf-placeholder` classes plus placeholder type, description, and style-name metadata.
- Surfaces `placeholderCount` in the ODF import report.
- Verified Markdown and WordPress handoff attributes through focused tests and the existing ODF WordPress example.

## Source Truth

- Existing lane ODF contract and manifest rows identify the bounded OpenDocument reader support surface under `Text.Pandoc.Readers.ODT.ContentReader`.
- The local upstream cache still does not contain a hydrated Pandoc checkout, so no upstream Haskell runner or golden-file runner was executed.
- This slice reuses native PHP ODT package/content XML parsing and the established ODF review-span handoff shape used by fields, sequence fields, index marks, annotations, and ruby spans.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` failed with `1 test files, 1053 assertions, 1 failures` because `text:placeholder` content was omitted from paragraph text.
- After implementation: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 1070 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test` passed with `odf open document handoff self-test ok`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP ZIP package reader, ODF XML reader, shared AST span model, Markdown attribute writer, and WordPress block writer. Full upstream Pandoc parity remains blocked on a hydrated Pandoc checkout plus Cabal runner closure and was intentionally not attempted in this isolated lane.

## Non-Overlap

This avoids the recent accepted ODF clusters for `text:tab` normalization, paragraph blockquote style mapping, table-template metadata, MathML object handoff, generated indexes, bibliography marks, fields, sections, annotations, frame images, and form controls. Broader placeholder editing semantics, conditional placeholders, and upstream golden-file parity remain separate follow-up slices.
