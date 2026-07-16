# ODF/OpenDocument RDF Inline Metadata Handoff

Slice: `pandoc-odf-open-document-core-current-base-20260609T083432Z`
Base accepted HEAD: `436db66ac9717cbf75ff2ec29905ae0ddef22b3a`
Date: 2026-06-09 UTC

## Behavior

Native `OdfReader` now links inline OpenDocument `text:meta` ranges to parsed
package RDF sidecar subjects.

- Preserves XHTML RDFa `about`, `property`, `content`, and `datatype`
  attributes on `text:meta` spans.
- Matches RDF sidecar subjects by explicit RDFa `about`, fragment ids, or
  `content.xml#xml-id` fallbacks.
- Adds inert review metadata for matched subject, part count, triple count,
  literal/resource counts, parts, and predicates.
- Carries that provenance into Markdown attributes and WordPress
  `data-odf-meta-*` span attributes.

This remains metadata-only. The reader does not perform RDF reasoning, fetch
remote resources, execute formulas, render office documents, or invoke
external conversion tools.

## Evidence

Red-first focused check after adding the new fixture and before the reader
change:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 3272 assertions, 1 failures
```

The failure was the missing inline `rdfAbout` metadata on the new `text:meta`
span.

Final focused ODF reader check:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 3287 assertions, 0 failures
```

WordPress ODF smoke:

```text
php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok
```

Status delta:

- `phpPass`: `2530 -> 2531`
- `benchmarkDenominator.mapped`: `2898 -> 2899`
- `mappedOdfOpenDocumentCoreCases`: `13 -> 14`
- `odfOpenDocumentCoreAssertions`: `295 -> 314`
- Focused ODF test growth: `+1` PASS case and `+19` assertions.

## Dependency Closure

No new native support component is needed. This slice reuses `OdfReader`,
the existing safe XML/RDF sidecar parser, `ZipPackage`, `AstNode` span
metadata, `MarkdownWriter`, `WordPressBlockWriter`, and focused ODF tests.

The local upstream Pandoc cache was unavailable in this isolated worktree. No
Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, external template engine, TeX/PDF engine,
browser renderer, online service, live provider test, or live-service provider
test was executed.

## Non-Overlap

This does not repeat accepted ODF handling for manifest-declared RDF sidecar
inventory, settings/meta XML, table formulas, sheet-name fields, page
continuation, dropdown fields, metadata fields, database ranges, data pilots,
tracked changes, draw layers, charts, embedded objects, table cell style
provenance, table-cell data-style metadata, or YAML metadata merge provenance.
It only closes the inline `text:meta` to RDF sidecar subject linkage path.

## Follow-Up

Good ODF/OpenDocument follow-ups remain formula diagnostics as metadata,
richer package provenance, data-pilot grouping metadata, or table style policy.

Root harness: not run - isolated micro-slice.
