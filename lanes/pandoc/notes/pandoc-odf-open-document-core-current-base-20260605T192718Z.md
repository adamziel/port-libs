# ODF/OpenDocument Table-Caption Style Handoff

Slice: `pandoc-odf-open-document-core-current-base-20260605T192718Z`
Base accepted HEAD: `7ff7539e2567bc373fd1da7e9c45048d2ea63ec9`

## Source Truth

Pinned Pandoc source: `src/Text/Pandoc/Readers/ODT/ContentReader.hs` at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

The ODT reader `constructPara` path treats paragraphs whose resolved style name is `Table` as caption divs. This slice ports that bounded contract into the native PHP ODF reader without shelling out to Pandoc or office tools.

Reference URL: https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/ODT/ContentReader.hs

## Implementation

- `OdfReader` now wraps `text:p text:style-name="Table"` paragraphs in a `div` with classes `caption odf-table-caption`.
- The original styled paragraph remains the child block so inline spans, strong/emphasis conversion, and paragraph text stay stable.
- The wrapper carries `tableCaption=true`, `styleName=Table`, `text`, and `data-odf-table-caption-style-name="Table"` for Markdown and WordPress handoff.
- `importReport.content.tableCaptionCount` counts the caption wrapper.
- The WordPress ODF example now includes a table-caption style paragraph and self-test assertions for the import-report count and rendered caption div.

## Evidence

Baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 992 assertions, 0 failures
```

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
Expected: 'div'
Actual: 'paragraph'
1 test files, 994 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 1008 assertions, 0 failures
```

Coupled ODF focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php
2 test files, 1103 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok
```

## Status Delta

- `lane-status.json` `phpPass`: `1048 -> 1049`.
- `UPSTREAM_TEST_MANIFEST.json` mapped checks: `1501 -> 1502`.
- ODF/OpenDocument mapped core cases: `10 -> 11`.
- ODF/OpenDocument focused assertions: `217 -> 233`.

## Dependency Closure

No new support component is needed. This reuses native PHP `OdfReader`, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter`.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, office tool, or online service was executed.

## Non-Overlap

This slice avoids the accepted ODF text:tab normalization, paragraph blockquote mapping, table/list/section/form/field/citation/index/object coverage, and the table-geometry vertical-alignment slice. It only ports the `Table` paragraph-style caption modifier.

Follow-up: attach style-derived caption divs to adjacent table metadata only if a later bounded ODF slice explicitly owns table caption association. Richer ODT table style/layout rendering and full upstream Haskell runner parity remain separate work.
