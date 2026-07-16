# Pandoc ODF OpenDocument Typed Field Values 2026-06-08

## Scope

Micro-slice: `pandoc-odf-open-document-core-current-base-20260608T105109Z`.

Accepted base: `3e2219427f9e954ae09385b0581a7f2931695954`.

This is a native PHP ODF/OpenDocument behavior slice. No Pandoc binary, Cabal
solver/build/test command, Haskell test binary, Word, LibreOffice, `zip` or
`unzip`, external converter, online service, live provider test,
live-service provider test, or office tool was executed as progress.

## Source Truth

The accepted local support-library contract maps ODF text fields to
reviewer-visible Pandoc-like spans while preserving source metadata for
Markdown and WordPress import handoff. ODF field value attributes use
`office:value-type`, `office:value`, `office:boolean-value`, and
`office:currency`; the existing table-cell and user-field declaration paths
already preserve those typed value attributes.

## Implemented Behavior

`OdfReader` now preserves typed ODF text-field values on field review spans:

- `office:boolean-value` is exposed as `fieldMetadata['booleanValue']`;
- `office:currency` is exposed as `fieldMetadata['currency']`;
- boolean `true` and `false` fields with no child text now produce visible
  fallback text instead of disappearing;
- Markdown and WordPress handoff attributes include
  `data-odf-field-boolean-value` and `data-odf-field-currency`.

## WordPress Handoff

The WordPress ODF open-document example now includes a typed currency
`text:expression` field. The self-test checks that WordPress output renders:

`data-odf-field-value-type="currency" data-odf-field-value="42.50" data-odf-field-currency="USD"`

on the existing `odf-field odf-field-expression` review span.

## Dependency Closure

No new support component is needed. This slice reuses:

- `OdfReader` field-span mapping;
- `MarkdownWriter` bracketed-span attributes;
- `WordPressBlockWriter` safe span attributes;
- in-process ODT package fixtures.

Full upstream runner parity remains out of scope until a hydrated pinned
Pandoc checkout and a reviewed non-mutating Cabal plan are available.

## Non-Overlap

This patch only changes ODF typed text-field value metadata and fallback text.
It deliberately does not repeat accepted ODF text:tab normalization, heading
auto identifiers, heading source ids, paragraph blockquote mapping,
conditional/hidden text field handoff, drop-down field handoff, field
style-name metadata, page-variable/statistic fields, database fields, form
controls, generated indexes, embedded objects, chart metadata, table styles,
linked/protected sections, or tracked changes.

## Verification

- Baseline focused test before edits:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1846 assertions, 0 failures`
- Red-first focused run after adding the failing fixture:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1847 assertions, 1 failures`
  - Failure cause: boolean fields with no child text were dropped and
    currency/boolean field metadata was incomplete.
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1877 assertions, 0 failures`
  - Focused delta: `+1` PASS case / `+31` assertions
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`
- Root harness: not run - isolated micro-slice.

## Next Task

Continue ODF/OpenDocument core with a non-overlapping content/styles/meta XML
mapping gap such as numeric/date field formatting metadata, list/table
metadata, or package relationship provenance.
