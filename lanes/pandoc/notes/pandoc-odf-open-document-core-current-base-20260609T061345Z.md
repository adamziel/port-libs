# ODF OpenDocument Page Continuation Fields

Slice: `pandoc-odf-open-document-core-current-base-20260609T061345Z`
Base accepted HEAD: `ad25c5c67f0859a34d555620436625e00d668451`
Date: 2026-06-09 UTC

## Behavior

This slice adds bounded native PHP handling for OpenDocument `text:page-continuation` fields.

- `OdfReader` now maps `text:page-continuation` to inert `odf-field` spans.
- Visible character data remains in paragraph text.
- Empty elements fall back to `text:string-value`.
- `text:string-value` and `text:select-page` are preserved in AST metadata, Markdown attributes, and WordPress block attributes.

The implementation is metadata-only. It does not evaluate page layout, decide whether a page actually continues, or invoke office/converter tooling.

Source truth: OASIS OpenDocument 1.3 Part 3 lists `text:page-continuation` as a document field with `text:select-page` and `text:string-value` attributes, usable in paragraphs/headings/spans and carrying character data.

## Evidence

Baseline focused test before this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 3156 assertions, 0 failures
```

Red-first focused test after adding expectations:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 3157 assertions, 1 failures
```

Failure showed the missing behavior:

```text
Expected: 'Continuation continued on next page and fallback continued from previous page stay reviewable.'
Actual: 'Continuation  and fallback  stay reviewable.'
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 3175 assertions, 0 failures
```

WordPress ODF smoke:

```text
php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2428 -> 2429`
- `benchmarkDenominator.mapped`: `2817 -> 2818`
- `mappedOdfOpenDocumentCoreCases`: `13 -> 14`
- `odfOpenDocumentCoreAssertions`: `295 -> 314`
- Focused ODF assertion delta: `+19`

## Dependency Closure

No new support component is needed. This reuses `OdfReader` DOM parsing, existing field-span metadata handling, in-memory ODT `ZipPackage` fixtures, `MarkdownWriter`, and `WordPressBlockWriter`.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external converter, external template engine, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat accepted ODF support for sequence/note references, page variables, page numbers, chapter/file-name/statistic fields, metadata fields, sender fields, hidden/conditional fields, dropdowns, database ranges, data pilots, named expressions, label ranges, data styles, table tracked changes, table cell detective metadata, or style-driven table cell provenance.

Good follow-up ODF slices: remaining `text:sheet-name` / `text:table-formula` fields, RDF/metadata extraction, or package-level provenance.
