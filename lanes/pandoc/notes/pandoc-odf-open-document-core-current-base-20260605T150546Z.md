# Pandoc ODF OpenDocument Core Slice

Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T150546Z`

Base accepted HEAD: `5a991c6da2d8839a1ad2d97f632a71cdab021dd6`

## Behavior

Implemented bounded OpenDocument Text form submission metadata handoff in the
native `OdfReader`:

- Parses enclosing `form:form` metadata from `office:forms`.
- Preserves review-only form action, method, enctype, target frame, command,
  command type, datasource, filter, order, navigation, tab-cycle, ignore-result,
  escape-processing, and master/detail binding fields.
- Carries that form metadata onto resolved `draw:control` review placeholders.
- Emits safe `data-odf-control-form-*` attributes in Markdown and WordPress
  output for migration audit.

This is bounded to OpenDocument package/content XML mapping. It does not submit
forms, run scripts, execute database queries, render live widgets, or invoke any
office/converter tool.

## Red-First Evidence

Baseline before the focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 849 assertions, 0 failures
```

After adding the focused test before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
FAIL maps ODT form submission metadata onto review controls
Expected: 'https://example.test/import-review'
Actual: NULL
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 873 assertions, 0 failures
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

php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 873 assertions, 0 failures

php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Manifest / Status Delta

- `phpPass`: 965 -> 966.
- `benchmarkDenominator.mapped`: 1420 -> 1421.
- `odfOpenDocumentCoreCases`: 10 -> 11.
- `mappedOdfOpenDocumentCoreCases`: 10 -> 11.
- `odfOpenDocumentCoreAssertions`: 217 -> 241.

The focused local ODF file itself now runs at 35 PASS cases / 873 assertions.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
ODF package reader, `ZipPackage`, shared AST, `MarkdownWriter`, and
`WordPressBlockWriter`. The local upstream cache is absent in this isolated
worktree, so no upstream Haskell runner could be run.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online validator, or online service was
executed.

## Non-Overlap

This avoids the accepted ODT mimetype/content/manifest/media/table/list base
cluster and the later bookmark/reference, sequence, field, ruby,
soft-page-break, form-control placeholder, table-of-contents, linked/protected
section, tracked-change, encrypted-manifest, MathML object, chart object, OLE
object, URI normalization, image-dimension, table cell formula/value, and
table-template clusters. It adds only bounded enclosing `form:form`
submission/source metadata provenance for already visible controls.

Remaining ODT follow-up stays separate: live form submission, validation and
scripting, database query execution, richer table-template style application,
table continuation rendering, chart data extraction, export-side ODT writing,
and full Pandoc ODT reader parity.
