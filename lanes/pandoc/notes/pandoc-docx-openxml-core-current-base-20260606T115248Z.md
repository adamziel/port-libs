# DOCX/OpenXML Deleted Field Instruction Audit

Slice: `pandoc-docx-openxml-core-current-base-20260606T115248Z`
Base accepted HEAD: `7b9b6e5a2c6885b2398accee1db59fa1d0384094`

## Behavior Added

`DocxReader` now preserves `w:delInstrText` content inside tracked deletions when building the DOCX import-report revision audit text. The deleted field instruction remains suppressed from the accepted AST, Markdown output, and WordPress block output, matching the existing deleted-text rendering policy while keeping the source field code visible for reviewer audit.

Covered fixture:

- A deleted `HYPERLINK` field instruction in `w:del/w:r/w:delInstrText`.
- A following accepted `w:ins` run that still renders as a `docx-insertion` span.
- Import-report revision ordering and metadata for deletion/insertion items.
- Negative Markdown and WordPress block checks proving the deleted field target is not rendered.

## Source Truth

This is bounded native WordprocessingML support for deleted field-code text in tracked revisions. The existing lane policy already suppresses `w:del`, `w:moveFrom`, and deleted range content from rendered output while reporting revision audit text. This slice extends the audit extractor from `w:delText` to `w:delInstrText` without enabling rendered deleted field instructions.

No hydrated Pandoc checkout was present in this worktree for a Haskell runner comparison, and this slice did not run Pandoc, Cabal, Word, LibreOffice, zip/unzip, external office tools, online services, live provider tests, or live-service provider tests.

## Verification

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1791 assertions, 1 failures
Expected: 'HYPERLINK "https://legacy.example.test/source" \\o "Legacy source"'
Actual: ''
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1795 assertions, 0 failures

php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test
docx body handoff self-test ok
```

Final syntax and diff hygiene were run after this note was written:

```text
php -l lanes/pandoc/src/DocxReader.php
php -l lanes/pandoc/tests/DocxReaderTest.php
php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php
php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/pandoc
```

## Status Delta

- `lane-status.json` `phpPass`: `1320 -> 1321`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1734 -> 1735`.
- DOCX/OpenXML mapped support cases: `32 -> 33`.
- DOCX/OpenXML focused assertion inventory: `357 -> 384`.
- Focused `DocxReaderTest.php`: `1768 -> 1795` assertions.

## Non-Overlap

This does not repeat the accepted DOCX tracked insertion/deletion text case, move/move-range cases, paragraph/run formatting revisions, hyperlink field-result conversion, cross-reference field provenance, direct hyperlink metadata, comments, bookmarks, OMML, media, altChunk, settings, embedded objects, or section geometry work. It owns only deleted field-instruction audit text for `w:delInstrText`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`, OPC package loading, `DocxReader`, the existing AST model, `MarkdownWriter`, `WordPressBlockWriter`, and the focused PHP `TestRunner`.

## Follow-Up

Keep accept/reject revision policy, deleted field-result rendering decisions, field recalculation, relationship-backed deleted field target resolution, and full upstream DOCX runner parity as separate bounded slices.
