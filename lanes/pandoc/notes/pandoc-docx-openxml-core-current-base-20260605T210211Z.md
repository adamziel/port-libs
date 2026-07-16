# DOCX Tracked Formatting Changes

Slice: `pandoc-docx-openxml-core-current-base-20260605T210211Z`
Base accepted HEAD: `470bfe2d22ad15ffa9128c64066e7df3d316c776`

## Behavior

- `DocxReader` now preserves bounded WordprocessingML paragraph formatting
  revisions from `w:pPrChange` as reviewer span metadata.
- `DocxReader` now preserves bounded run formatting revisions from
  `w:rPrChange` as reviewer span metadata around the visible current run.
- Paragraph formatting-change spans expose current paragraph metadata plus
  `data-docx-formatting-change="paragraph"`, change id/author/date, previous
  paragraph style, and selected previous paragraph metadata such as alignment.
- Run formatting-change spans expose `data-docx-formatting-change="run"`,
  change id/author/date, selected previous run toggles, and previous review
  metadata such as highlight/language.
- The DOCX import report now includes `formattingCount` and records
  `paragraph-formatting` / `run-formatting` revision items without rendering
  prior formatting as body text.
- The WordPress DOCX handoff example self-test now covers both tracked
  paragraph and tracked run formatting revision spans.

## Source Truth And Non-Overlap

This maps the bounded OpenXML contract where current paragraph/run properties
remain active and `w:pPrChange` / `w:rPrChange` carry previous formatting
metadata for tracked revision review.

This does not overlap accepted package loading, OPC relationships,
styles/numbering, tables, media, VML/DrawingML images, chart/diagram
placeholders, embedded objects, footnotes, endnotes, comments, commentsExtended,
comment ranges, note markers, bookmarks, bookmark column ranges, field-code
hyperlinks, tracked insert/delete/move rendering, content controls, smart tags,
custom XML, OMML math, altChunk import, settings, document variables, section
properties, run language/RTL, paragraph bidi/layout, page/column/rendered page
breaks, proof-error ranges, or permission ranges.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external office tool, browser renderer, online sanitizer, online
service, or live provider test was executed.

## Evidence

Baseline before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1420 assertions, 0 failures
```

Focused DOCX test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1464 assertions, 0 failures
```

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test
docx body handoff self-test ok
```

PHP lint passed for changed PHP files:

```text
php -l lanes/pandoc/src/DocxReader.php
php -l lanes/pandoc/tests/DocxReaderTest.php
php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php
```

JSON validation passed for `lanes/pandoc/lane-status.json` and
`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.

Whitespace check passed:

```text
git diff --check -- lanes/pandoc
```

Focused delta: one new DOCX/OpenXML PHP PASS case and `+44` focused
assertions.

## Status Delta

- `lane-status.json` `phpPass`: `1075` -> `1076`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1527` ->
  `1528`.
- `docxOpenXmlCoreCases`: `31` -> `32`.
- `mappedDocxOpenXmlCoreCases`: `31` -> `32`.
- `docxOpenXmlCoreAssertions`: `313` -> `357`.
- Added `mappedDocxFormattingChangeCases: 1` and
  `docxFormattingChangeAssertions: 44`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
OPC relationship graph, XML DOM helpers, `DocxReader`, `MarkdownWriter`, and
`WordPressBlockWriter`.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`,
`test/test-pandoc.hs`, and
`pandoc-lua-engine/test/test-pandoc-lua-engine.hs` present before any
non-mutating Cabal solver/build plan.

## Follow-Up

Keep glossary document parts, theme font inheritance, cross-paragraph
proof/permission repair, richer DrawingML text extraction, commentsExt anchor
reconciliation beyond paragraph IDs, and full upstream Pandoc Haskell runner
parity as separate bounded slices.

Root harness: not run - isolated micro-slice.
