# DOCX/OpenXML Section Pagination And Grid Metadata

Slice: `pandoc-docx-openxml-core-current-base-20260606T122406Z`
Base accepted HEAD: `156d1212214e5caf0af65cda79c528799799824c`

## Behavior Added

`DocxReader` now preserves additional `w:sectPr` metadata in the document `sectionProperties` attribute and import-report `sections.items`:

- `w:type` as `sectionType`.
- `w:titlePg` as a boolean `titlePage`, including explicit disabled values.
- `w:pgNumType` page numbering start, format, chapter style, and chapter separator.
- `w:lnNumType` line numbering start, count-by, restart policy, and distance.
- `w:docGrid` type, line pitch, and character spacing.

This keeps Word section pagination and layout-review cues visible for WordPress import review without changing rendered body text.

## Source Truth

This is bounded native WordprocessingML support for section property parsing. It extends the already accepted DOCX section page size, margins, columns, header/footer references, and note-numbering policy import report.

No hydrated Pandoc checkout was present in this worktree for a Haskell runner comparison, and this slice did not run Pandoc, Cabal, Word, LibreOffice, zip/unzip, external office tools, online services, live provider tests, or live-service provider tests.

## Verification

Baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1795 assertions, 0 failures
```

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1772 assertions, 1 failures
Expected: 'nextPage'
Actual: NULL
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1830 assertions, 0 failures

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

- `lane-status.json` `phpPass`: `1328 -> 1329`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1742 -> 1743`.
- DOCX/OpenXML mapped support cases: `32 -> 33`.
- DOCX/OpenXML focused assertion inventory: `357 -> 392`.
- Focused `DocxReaderTest.php`: `1795 -> 1830` assertions.

## Non-Overlap

This does not repeat accepted DOCX section page geometry, margins, columns, header/footer relationship parsing, note numbering policies, tracked revisions, field-code hyperlinks, comments, bookmarks, OMML, media, altChunk, settings, glossary, embedded objects, or run/paragraph metadata. It owns only the section pagination, line-numbering, and document-grid metadata cluster.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`, OPC package loading, `DocxReader`, the existing AST/import-report model, `WordPressBlockWriter`, and the focused PHP `TestRunner`.

## Follow-Up

Keep section page borders, paper source, form protection, mirror margins, deeper style defaults, field recalculation, and full upstream DOCX reader parity as separate bounded slices.
