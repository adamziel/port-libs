# Pandoc ODF OpenDocument Core Slice

Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T143327Z`

Base accepted HEAD: `381798fcad9b34f8ddd3161bb0f61bf77da880ad`

## Behavior

Implemented bounded OpenDocument Text table-template handoff in the native
`OdfReader`:

- Parses `table:table-template` style definitions from `styles.xml` and
  `content.xml` style collections.
- Preserves common table-template style slots such as first/last row,
  first/last column, body, odd/even rows, and first-row start/end columns.
- Attaches `table:template-name` references to table AST nodes, including
  missing-template diagnostics.
- Reports table template definitions and table template references through the
  import report.
- Emits safe `data-odf-table-template-*` attributes on WordPress table output.

This is bounded to OpenDocument package/styles/content XML mapping. It does not
apply template styling, render ODT, or invoke any office/converter tool.

## Red-First Evidence

Baseline before the focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 825 assertions, 0 failures
```

After adding the focused test before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
FAIL maps ODT table templates into table review metadata
Expected: 1
Actual: NULL
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 849 assertions, 0 failures
```

Delta: +1 focused PASS case and +24 focused assertions.

## Verification

```text
php -l lanes/pandoc/src/OdfReader.php
No syntax errors detected in lanes/pandoc/src/OdfReader.php

php -l lanes/pandoc/tests/OdfReaderTest.php
No syntax errors detected in lanes/pandoc/tests/OdfReaderTest.php

php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-odf-open-document-handoff.php

php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
pandoc json ok

php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 849 assertions, 0 failures

php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok

git diff --check -- lanes/pandoc
passed with no output
```

Root harness: not run - isolated micro-slice.

## Manifest / Status Delta

- `phpPass`: 947 -> 948.
- `benchmarkDenominator.mapped`: 1402 -> 1403.
- `odfOpenDocumentCoreCases`: 10 -> 11.
- `mappedOdfOpenDocumentCoreCases`: 10 -> 11.
- `odfOpenDocumentCoreAssertions`: 217 -> 241.

The focused local ODF file itself now runs at 34 PASS cases / 849 assertions.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
ODF package reader, `ZipPackage`, shared AST, `MarkdownWriter`, and
`WordPressBlockWriter`. The local upstream cache still has no hydrated Pandoc
checkout or Cabal package files, so no upstream Haskell runner could be run.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online validator, or online service was
executed.

## Non-Overlap

This avoids the accepted ODT mimetype/content/manifest/media/table/list base
cluster and the later bookmark/reference, sequence, field, ruby, soft-page-break,
form-control, table-of-contents, linked/protected section, tracked-change,
encrypted-manifest, MathML object, chart object, OLE object, URI normalization,
image-dimension, and table cell formula/value clusters. It adds only bounded
OpenDocument table-template style/reference provenance.

Remaining ODT follow-up stays separate: chart data extraction, richer table
template style application, table continuation semantics, form submission action
metadata, export-side ODT writing, and full Pandoc ODT reader parity.
