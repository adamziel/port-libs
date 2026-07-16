# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T150345Z`
Base accepted HEAD: `5e277f7985f08bbea655de828433799334fd1a1e`

## Behavior Added

- `DocxReader` now preserves bounded `w:settings/w:docVars/w:docVar`
  document-variable metadata in `metadata.docxSettings` and the import report.
- The handoff keeps ordered document-variable items, ignores nameless variables,
  preserves named empty values, exposes first-value `byName` lookup, and reports
  duplicate variable names.
- The WordPress DOCX body handoff smoke now exercises reviewer routing flags
  such as `ReviewStatus` without invoking external office tooling.

## Source Truth And Non-Overlap

The bounded OpenXML contract for this slice is the WordprocessingML settings
shape `w:settings/w:docVars/w:docVar` with `w:name` and `w:val` attributes.
This supports DOCX packages that carry import/review state in document
variables while leaving full document-property synchronization policy out of
scope.

This patch does not repeat accepted DOCX/OpenXML work for package loading,
styles, numbering, tables, media, VML, DrawingML, chart/diagram placeholders,
embedded OLE/package placeholders, normal comments, commentsExtended,
comment ranges, missing notes, note numbering policy, bookmarks, field-code
hyperlinks, tracked revisions, content controls, smart tags, custom XML,
OMML formulas, altChunk import, attached templates, document protection,
proof state, compatibility settings, section geometry, headers, footers,
run language, run review markup, or page/column breaks.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `zip`, `unzip`,
`ZipArchive`, external office tooling, browser renderer, online sanitizer, or
online service was executed.

## Red-First Evidence

After adding the document-variable fixture and before changing `DocxReader`:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
FAIL reports DOCX settings document variables for reviewer handoff
Expected: 3
Actual: NULL
1 test files, 1303 assertions, 1 failures
```

The expected count was then corrected to `4` because named empty variables are
part of the preserved reviewer metadata; only nameless variables are ignored.

## Verification

Focused DOCX test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1322 assertions, 0 failures
```

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test
docx body handoff self-test ok
```

Additional required checks:

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

Focused delta: one new DOCX/OpenXML PHP PASS case and `+20` focused
assertions.

## Status Delta

- `lane-status.json` `phpPass`: `962` -> `963`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1417` ->
  `1418`.
- `docxOpenXmlCoreCases`: `31` -> `32`.
- `mappedDocxOpenXmlCoreCases`: `31` -> `32`.
- `docxOpenXmlCoreAssertions`: `313` -> `333`.
- Added `mappedDocxSettingsDocumentVariableCases: 1` and
  `docxSettingsDocumentVariableAssertions: 20`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
ZIP/OPC package reader, relationship graph, XML DOM helpers, `DocxReader`,
`MarkdownWriter`, and `WordPressBlockWriter`.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal`.

## Follow-Up

Keep glossary document parts, theme font inheritance, tracked formatting-change
metadata, commentsExt anchor reconciliation beyond paragraph IDs, richer
drawing text extraction, and full upstream Pandoc Haskell runner parity as
separate bounded slices.

Root harness: not run - isolated micro-slice.
