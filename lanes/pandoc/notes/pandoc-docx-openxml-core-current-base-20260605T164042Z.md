# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T164042Z`
Base accepted HEAD: `5461f13312d11b720990563e5f589783adb6e304`

## Behavior Added

- `DocxReader` now preserves WordprocessingML `w:lastRenderedPageBreak` run
  children as bounded reviewer spans.
- The marker is distinct from explicit `w:br` page and column breaks:
  `data-docx-break-type="rendered-page"` and
  `data-docx-last-rendered-page-break="true"`.
- Focused coverage proves AST, Markdown writer, and WordPress block output keep
  the rendered page-break marker between surrounding text runs.
- The WordPress DOCX body handoff smoke now includes the same rendered
  page-break marker in its layout-checkpoint paragraph.

## Source Truth And Non-Overlap

The bounded OpenXML contract for this slice is the WordprocessingML rendered
pagination marker `w:lastRenderedPageBreak`. This supports reviewer and import
handoffs that need to know where Word last rendered a page boundary while
leaving full layout pagination and Word-compatible page computation out of
scope.

This patch does not repeat accepted DOCX/OpenXML work for package loading,
styles, numbering, tables, media, VML, DrawingML, chart/diagram placeholders,
embedded OLE/package placeholders, normal note references, commentsExtended,
comment ranges, missing notes, note numbering policy, bookmarks, field-code
hyperlinks, tracked revisions, content controls, smart tags, custom XML,
OMML formulas, altChunk import, attached templates, document protection,
proof state, compatibility settings, section geometry, headers, footers, run
language, run review markup, paragraph bidi/layout, document variables, note
body reference markers, carriage returns, or explicit page/column break
metadata.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `zip`, `unzip`,
`ZipArchive`, external office tooling, browser renderer, online sanitizer, or
online service was executed.

## Red-First Evidence

Baseline before the new fixture:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1358 assertions, 0 failures
```

After adding the focused fixture and before changing `DocxReader`:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
FAIL preserves DOCX last rendered page break markers as reviewer spans
Expected: 3
Actual: 1
1 test files, 1360 assertions, 1 failures
```

## Verification

Focused DOCX test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1370 assertions, 0 failures
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
assertions, raising `DocxReaderTest.php` from `44 PASS / 1358 assertions` to
`45 PASS / 1370 assertions`.

## Status Delta

- `lane-status.json` `phpPass`: `1002` -> `1003`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1457` ->
  `1458`.
- `docxOpenXmlCoreCases`: `31` -> `32`.
- `mappedDocxOpenXmlCoreCases`: `31` -> `32`.
- `docxOpenXmlCoreAssertions`: `313` -> `325`.
- Added `mappedDocxRenderedPageBreakCases: 1` and
  `docxRenderedPageBreakAssertions: 12`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`ZipPackage`, OPC relationship graph, XML DOM helpers, `DocxReader`,
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
