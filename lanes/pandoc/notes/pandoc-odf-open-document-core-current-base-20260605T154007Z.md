# Pandoc ODF OpenDocument Core Slice

Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T154007Z`

Base accepted HEAD: `0017586f0ec4000005e9e8925bd3a65b36b8c8d2`

## Behavior

Implemented bounded OpenDocument Text generated-index handoff in the native
`OdfReader`:

- Preserves non-TOC generated index containers such as
  `text:illustration-index`, `text:alphabetical-index`, `text:table-index`,
  `text:object-index`, `text:user-index`, and `text:bibliography` as review
  `div` nodes.
- Carries index name/style/protected metadata and safe `data-odf-index-*`
  attributes into Markdown and WordPress output.
- Preserves generated-index source attributes such as
  `text:caption-sequence-name`, `text:use-caption`, alphabetical combine flags,
  and sort algorithm metadata.
- Preserves generated-index title/body blocks that already exist in the ODT
  package, including body links, without recalculating or regenerating the
  index.
- Adds recursive `importReport.content.generatedIndexCount` accounting.

This is bounded to OpenDocument package/content XML mapping. It does not
rebuild indexes, resolve page numbers, run office layout, apply index styles,
or invoke any external converter.

## Red-First Evidence

Baseline before the focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 873 assertions, 0 failures
```

After adding the generated-index expectation before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
FAIL maps ODT generated indexes beyond table of contents into review div metadata
Expected: 2
Actual: 0
1 test files, 874 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 931 assertions, 0 failures
```

Delta: +1 focused PASS case and +58 focused assertions.

## Verification

Focused checks run:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 931 assertions, 0 failures

php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok
```

Additional lint/JSON/diff checks are recorded by the worker final handoff.

Root harness: not run - isolated micro-slice.

## Manifest / Status Delta

- `lane-status.json` `phpPass`: `977 -> 978`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1432 -> 1433`.
- ODF OpenDocument core cases: `10 -> 11`.
- Mapped ODF OpenDocument core cases: `10 -> 11`.
- ODF OpenDocument core assertions: `217 -> 275`.
- Focused local `OdfReaderTest.php`: `35` PASS cases / `873` assertions ->
  `36` PASS cases / `931` assertions.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`OdfReader`, `ZipPackage`, shared AST, `MarkdownWriter`, `WordPressBlockWriter`,
and lane test harness.

The local upstream Pandoc checkout was absent in this isolated worktree, so no
upstream Haskell runner could be run. No Pandoc, Cabal solver/build/test
command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool,
external converter, online validator, or online service was executed.

## Non-Overlap

This avoids the accepted ODT mimetype/content/manifest/media/table/list base
cluster and later ODF bookmark/reference, sequence, field, ruby, soft-page-break,
form-control placeholder, form submission metadata, table-of-contents,
linked/protected section, tracked-change, encrypted-manifest, MathML object,
chart object, OLE object, URI normalization, image-dimension, table cell
formula/value, and table-template clusters. It adds only bounded generated-index
review handoff for non-TOC index containers and their materialized title/body.

Remaining ODF follow-up stays separate: generated-index regeneration,
page-number recalculation, alphabetical collation fidelity, bibliography index
data extraction, table/figure/object index style application, table continuation
rendering, chart data extraction, export-side ODT writing, and full Pandoc ODT
reader parity.
