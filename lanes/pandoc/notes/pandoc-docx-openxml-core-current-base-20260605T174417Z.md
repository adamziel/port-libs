# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T174417Z`
Base accepted HEAD: `468f67cc261481eaaf7f76a7fa67c6e0dfff4edd`

## Behavior Added

- `DocxReader` now preserves WordprocessingML `w:bookmarkStart` table-column
  range metadata when `w:colFirst` and/or `w:colLast` are present.
- The bounded metadata is attached to the same reviewer anchor span used for
  ordinary DOCX bookmarks with deterministic attributes:
  `data-docx-bookmark-id`, `data-docx-bookmark-name`,
  `data-docx-bookmark-col-first`, and `data-docx-bookmark-col-last`.
- Column-range bookmarks gain `docx-bookmark` and
  `docx-bookmark-column-range` classes while ordinary non-range bookmarks keep
  their existing `anchor`-only output, and `_GoBack` bookmarks remain
  suppressed.
- Focused coverage proves AST, Markdown writer, and WordPress block output
  preserve the table-scoped reviewer anchor metadata.
- The WordPress DOCX body handoff smoke now includes the same table bookmark
  column-range marker for import review.

## Source Truth And Non-Overlap

The bounded OpenXML contract for this slice is the `w:bookmarkStart`
`w:colFirst`/`w:colLast` metadata used by WordprocessingML to scope a bookmark
to table columns. This preserves reviewer/import provenance without attempting
full Word table-selection editing semantics.

This patch does not repeat accepted DOCX/OpenXML work for package loading,
styles, numbering, tables, media, VML, DrawingML, chart/diagram placeholders,
embedded OLE/package placeholders, normal note references, commentsExtended,
comment ranges, missing notes, note numbering policy, ordinary bookmark
anchors, field-code hyperlinks, tracked revisions, content controls, smart
tags, custom XML, OMML formulas, altChunk import, attached templates, document
protection, proof state, compatibility settings, section geometry, headers,
footers, run language, run review markup, paragraph bidi/layout, document
variables, note body reference markers, carriage returns, explicit page/column
break metadata, or rendered page-break markers.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `zip`, `unzip`,
`ZipArchive`, external office tooling, browser renderer, online sanitizer, or
online service was executed.

## Red-First Evidence

After adding the focused table-column bookmark fixture and before changing
`DocxReader`:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
FAIL preserves DOCX bookmarks as anchor spans for internal hyperlink targets
Expected: [
    "anchor",
    "docx-bookmark",
    "docx-bookmark-column-range"
]
Actual: [
    "anchor"
]
1 test files, 1368 assertions, 1 failures
```

## Verification

Focused DOCX test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1382 assertions, 0 failures
```

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test
docx body handoff self-test ok
```

PHP lint:

```text
php -l lanes/pandoc/src/DocxReader.php
No syntax errors detected in lanes/pandoc/src/DocxReader.php

php -l lanes/pandoc/tests/DocxReaderTest.php
No syntax errors detected in lanes/pandoc/tests/DocxReaderTest.php

php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-docx-body-handoff.php
```

JSON validation:

```text
php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
json ok
```

Whitespace check:

```text
git diff --check -- lanes/pandoc
no output
```

Focused delta: one new DOCX/OpenXML PHP PASS case and `+12` focused
assertions, raising `DocxReaderTest.php` from `45 PASS / 1370 assertions` to
`46 PASS / 1382 assertions`.

## Status Delta

- `lane-status.json` `phpPass`: `1021` -> `1022`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1475` ->
  `1476`.
- `docxOpenXmlCoreCases`: `31` -> `32`.
- `mappedDocxOpenXmlCoreCases`: `31` -> `32`.
- `docxOpenXmlCoreAssertions`: `313` -> `325`.
- Added `mappedDocxBookmarkColumnRangeCases: 1` and
  `docxBookmarkColumnRangeAssertions: 12`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`ZipPackage`, OPC package primitives, XML DOM helpers, `DocxReader`,
`MarkdownWriter`, and `WordPressBlockWriter`.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal`.

## Follow-Up

Keep glossary document parts, theme font inheritance, tracked formatting-change
metadata, proof-error and permission-range metadata, commentsExt anchor
reconciliation beyond paragraph IDs, richer DrawingML text extraction, and full
upstream Pandoc Haskell runner parity as separate bounded slices.

Root harness: not run - isolated micro-slice.
