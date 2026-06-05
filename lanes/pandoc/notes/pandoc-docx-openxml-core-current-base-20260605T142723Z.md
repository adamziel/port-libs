# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T142723Z`
Base accepted HEAD: `f8a1e538a1954a09ee175dc3aed07914bf3ae416`

## Behavior Added

- `DocxReader` now loads `word/commentsExtended.xml` through the DOCX document
  relationship graph using the OpenXML commentsExtended relationship type.
- Comment paragraphs with Word extension `paraId` metadata now attach bounded
  commentsExtended state to comment note AST nodes:
  - `commentParaId`
  - `commentParentParaId`
  - `commentResolved`
  - `commentsExtendedPart`
- DOCX comment range spans now expose the same review-thread metadata as
  WordPress-safe `data-docx-comment-*` attributes.
- The DOCX import report now preserves commentsExtended paragraph IDs, resolved
  state, reply parent IDs, and source part provenance per comment note item.
- The WordPress DOCX body handoff smoke now exercises resolved and threaded
  reviewer comments without invoking external office tooling.

## Source Truth And Non-Overlap

The bounded OpenXML contract for this slice is the Word document relationship
to a commentsExtended part plus the `w15:commentsEx/w15:commentEx` metadata
linked back to comment paragraphs by Word extension `paraId` attributes.

This patch does not repeat accepted DOCX/OpenXML work for package loading,
styles, numbering, table spans or captions, media, VML, DrawingML, chart or
diagram placeholders, embedded OLE/package placeholders, normal comments,
comment ranges, missing notes, note numbering policy, bookmarks, field-code
hyperlinks, tracked insert/delete/move rendering, content controls, smart tags,
custom XML, OMML formulas, altChunk import, document settings, section
geometry, headers, footers, run language, run review markup, or page/column
breaks.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `zip`, `unzip`,
`ZipArchive`, external office tooling, browser renderer, online sanitizer, or
online service was executed.

## Red-First Evidence

Baseline before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1254 assertions, 0 failures
```

After adding the commentsExtended fixture and before changing `DocxReader`:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
FAIL preserves DOCX commentsExtended resolution and thread metadata
Expected: '00ABCDEF'
Actual: NULL
1 test files, 1262 assertions, 1 failures
```

## Verification

Focused DOCX test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1302 assertions, 0 failures
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

Focused delta: one new DOCX/OpenXML PHP PASS case and `+48` focused assertions.

## Status Delta

- `lane-status.json` `phpPass`: `943` -> `944`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1399` ->
  `1400`.
- `docxOpenXmlCoreCases`: `31` -> `32`.
- `mappedDocxOpenXmlCoreCases`: `31` -> `32`.
- `docxOpenXmlCoreAssertions`: `313` -> `361`.
- Added `mappedDocxCommentsExtendedCases: 1` and
  `docxCommentsExtendedAssertions: 48`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
ZIP/OPC package reader, relationship graph, XML DOM helpers, `DocxReader`,
`MarkdownWriter`, and `WordPressBlockWriter`.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal`.

## Follow-Up

Keep theme font inheritance, tracked formatting-change metadata, glossary
document parts, richer drawing text extraction, comment anchor reconciliation
beyond paragraph IDs, and full upstream Pandoc Haskell runner parity as
separate bounded slices.

Root harness: not run - isolated micro-slice.
