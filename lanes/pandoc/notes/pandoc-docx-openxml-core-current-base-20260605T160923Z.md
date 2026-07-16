# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T160923Z`
Base accepted HEAD: `48a89663839470a7f859e92d82aaf22dbf92f634`

## Behavior Added

- `DocxReader` now maps WordprocessingML `w:cr` run children to normal
  linebreak nodes.
- `DocxReader` now preserves note-body `w:footnoteRef`, `w:endnoteRef`, and
  `w:annotationRef` markers as bounded reviewer spans with
  `data-docx-reference-marker`.
- The focused fixture proves AST, import-report plain-text, Markdown writer,
  and WordPress block output keep the marker spans and note-body linebreaks.
- The WordPress DOCX body handoff smoke now includes the same marker spans in
  footnote, endnote, and comment output.

## Source Truth And Non-Overlap

The bounded OpenXML contract for this slice is the local WordprocessingML
fixture shape for run children: `w:cr`, `w:footnoteRef`, `w:endnoteRef`, and
`w:annotationRef`. This is a DOCX reader support-library behavior needed for
Word note/comment review packets.

This patch does not repeat accepted DOCX/OpenXML work for package loading,
styles, numbering, tables, media, VML, DrawingML, chart/diagram placeholders,
embedded OLE/package placeholders, normal note references, commentsExtended,
comment ranges, missing notes, note numbering policy, bookmarks, field-code
hyperlinks, tracked revisions, content controls, smart tags, custom XML,
OMML formulas, altChunk import, attached templates, document protection,
proof state, compatibility settings, section geometry, headers, footers,
run language, run review markup, paragraph bidi/layout, document variables, or
page/column break metadata.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `zip`, `unzip`,
`ZipArchive`, external office tooling, browser renderer, online sanitizer, or
online service was executed.

## Red-First Evidence

After adding the focused fixture and before changing `DocxReader`:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
FAIL preserves DOCX note-body reference markers and carriage returns
Expected: 3
Actual: 1
1 test files, 1324 assertions, 1 failures
```

Baseline before the new fixture:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1322 assertions, 0 failures
```

## Verification

Focused DOCX test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1358 assertions, 0 failures
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

Focused delta: one new DOCX/OpenXML PHP PASS case and `+36` focused
assertions, raising `DocxReaderTest.php` from `43 PASS / 1322 assertions` to
`44 PASS / 1358 assertions`.

## Status Delta

- `lane-status.json` `phpPass`: `991` -> `992`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1446` ->
  `1447`.
- `docxOpenXmlCoreCases`: `31` -> `32`.
- `mappedDocxOpenXmlCoreCases`: `31` -> `32`.
- `docxOpenXmlCoreAssertions`: `313` -> `349`.
- Added `mappedDocxNoteBodyReferenceMarkerCases: 1` and
  `docxNoteBodyReferenceMarkerAssertions: 36`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`ZipPackage`, OPC relationship graph, `DocxReader`, `MarkdownWriter`, and
`WordPressBlockWriter`.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal`.

## Follow-Up

Keep footnote/endnote separator part materialization, glossary document parts,
theme font inheritance, tracked formatting-change metadata, commentsExt anchor
reconciliation beyond paragraph IDs, richer drawing text extraction, and full
upstream Pandoc Haskell runner parity as separate bounded slices.

Root harness: not run - isolated micro-slice.
