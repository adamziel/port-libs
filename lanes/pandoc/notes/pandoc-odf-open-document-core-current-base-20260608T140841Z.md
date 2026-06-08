# pandoc-odf-open-document-core-current-base-20260608T140841Z

## Summary

Implemented one bounded ODF/OpenDocument table-cell style mapping slice in native PHP.

`OdfReader` now parses `style:table-cell-properties` on ODF table-cell styles, merges inherited cell-style properties through the existing style resolver, and preserves the resolved metadata on `table_cell` AST nodes, table-geometry review packets, import-report counters, and WordPress table output. The mapped properties are background color, border, padding, vertical alignment, writing mode, cell protection, print-content, repeat-content, and shrink-to-fit.

CSS handoff is intentionally bounded: only safe color, length, border, and vertical-align values are emitted into inline WordPress table-cell styles. Other properties stay as inert `data-odf-*` reviewer metadata.

## Source Truth

The slice follows the accepted lane ODF reader contract for native namespace-aware OpenDocument parsing and the ODF table-cell style vocabulary already present in source fixtures. The local pinned Pandoc upstream checkout was not available at `/home/claude/port-libs/.upstream-cache/pandoc` in this isolated worktree, so no upstream runner or Haskell source command was executed.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was executed.

## Verification

Baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 1961 assertions, 0 failures
```

Red-first check:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 1963 assertions, 1 failures
```

The failure showed table cells had `styleName` but no resolved inherited table-cell style metadata.

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 1985 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php
2 test files, 2080 assertions, 0 failures

php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok
```

Final syntax/status/diff checks:

```text
php -l lanes/pandoc/src/OdfReader.php
php -l lanes/pandoc/tests/OdfReaderTest.php
php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php
php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
git diff --check -- lanes/pandoc
```

All final checks passed.

## Counters

- `lane-status.json` `phpPass`: `1661 -> 1662`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2081 -> 2082`
- `odfOpenDocumentCoreCases`: `13 -> 14`
- `mappedOdfOpenDocumentCoreCases`: `13 -> 14`
- `odfOpenDocumentCoreAssertions`: `295 -> 319`
- Focused ODF test growth: `+1` PASS case and `+24` assertions.

## Dependency Closure

No new native PHP support component is needed. This reuses `OdfReader`, inherited style resolution, `AstNode` table-cell metadata, `TableGeometry`, `WordPressBlockWriter`, `MarkdownWriter`, and the existing in-memory ODT ZIP fixture builder.

## Non-Overlap And Follow-Up

This slice does not repeat accepted ODF table formulas/typed values, table templates, row/column repeat visibility, table captions, database subtotal metadata, dynamic script/macro/DDE fields, dropdown fields, hidden paragraphs, conditional/hidden text, heading anchors, tab normalization, generated-index metadata, form controls, chart objects, MathML objects, or media/package preflight.

Useful next ODF work should stay on non-overlapping native reader gaps such as data-pilot metadata, tracked table changes, covered-cell provenance, or richer page/table style policy.
