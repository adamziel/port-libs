# pandoc-docx-openxml-core-current-base-20260605T125026Z

Accepted base: `af2575a57bdb5d0f0b53fdb98a89256019c109bb`

## Behavior

This slice adds bounded native DOCX/OpenXML table metadata handoff for `w:tblPr/w:tblCaption` and `w:tblDescription`.

- `DocxReader` now maps `w:tblCaption` into table `caption`.
- `DocxReader` now maps `w:tblDescription` into reviewer metadata:
  - `attributes.data-docx-table-description`
  - `htmlAttributes.aria-description`
  - `docx-table-metadata` class
- `TableGeometry::withReviewPacket()` now receives those table attributes, so geometry captions and source-attribute packets preserve the DOCX table summary.
- Markdown output now gets the caption through the existing table writer path.
- WordPress output now gets a table class, `aria-description`, and figcaption through the existing block writer path.

Source truth: WordprocessingML stores table caption/description in `w:tblPr` as `w:tblCaption` and `w:tblDescription`; this patch ports that bounded DOCX package contract into the existing PHP AST/WordPress handoff without invoking office tooling.

## Red-First Evidence

Before the reader change, the focused test failed on the added DOCX table metadata fixture:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
FAIL preserves DOCX table caption and description metadata for reviewer handoff
Expected: 'DOCX migration status'
Actual: ''
1 test files, 1178 assertions, 1 failures
```

## Verification

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1191 assertions, 0 failures
```

```text
php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test
docx body handoff self-test ok
```

```text
php -l lanes/pandoc/src/DocxReader.php
No syntax errors detected in lanes/pandoc/src/DocxReader.php
```

```text
php -l lanes/pandoc/tests/DocxReaderTest.php
No syntax errors detected in lanes/pandoc/tests/DocxReaderTest.php
```

```text
php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-docx-body-handoff.php
```

```text
php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
json ok
```

```text
git diff --check -- lanes/pandoc
no output
```

Focused assertion delta: accepted `DocxReaderTest.php` baseline `1176` assertions to `1191` assertions, +15 assertions and +1 focused PASS case.

## Status Delta

- `lane-status.json` `phpPass`: `903` -> `904`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1361` -> `1362`
- `docxOpenXmlCoreCases`: `31` -> `32`
- `docxOpenXmlCoreAssertions`: `313` -> `328`

## Dependency Closure

No new support component is needed. This slice reuses existing bounded PHP support rows:

- `ZipPackage` for in-memory DOCX package parts.
- Existing safe XML/DOM helpers in `DocxReader`.
- Existing `TableGeometry`, `MarkdownWriter`, and `WordPressBlockWriter` table handoff paths.

No Pandoc, Word, LibreOffice, zip/unzip, ZipArchive, Cabal build, Haskell runner, external office tool, or online service was executed.

## Non-Overlap

This is separate from accepted/current DOCX slices for run language, document settings, section metadata, embedded OLE/package placeholders, altChunk import/reporting, comment ranges, bookmarks, field hyperlinks, OMML math, and table span geometry. It only owns bounded table caption/description metadata in `w:tblPr`.

## Follow-Up

Keep broader DOCX table style inheritance, table look/style IDs, commentsExt metadata, theme font inheritance, glossary document parts, drawing text extraction, and tracked formatting changes as separate bounded slices.

Root harness: not run - isolated micro-slice.
