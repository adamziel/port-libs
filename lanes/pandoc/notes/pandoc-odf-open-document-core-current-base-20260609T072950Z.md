# ODF/OpenDocument RDF Metadata Sidecar Handoff

Slice: `pandoc-odf-open-document-core-current-base-20260609T072950Z`
Base accepted HEAD: `7e30824b38b73655628a135f3cb7279a6bf5d6b4`
Date: 2026-06-09 UTC

## Behavior

Native `OdfReader` now inventories OpenDocument package RDF metadata sidecars
declared as `application/rdf+xml` manifest parts.

- Preserves RDF part provenance, existence, byte counts, and parseability in
  document metadata and the import report.
- Parses bounded RDF/XML `rdf:RDF` description/property triples into
  subject/predicate/object metadata.
- Distinguishes literal and resource objects, preserves `xml:lang`,
  `rdf:datatype`, and `rdf:parseType` metadata when present, and groups
  predicates by subject for reviewer audit.
- Keeps malformed, missing, or encrypted RDF sidecars as explicit diagnostics
  instead of blocking ODT conversion.
- Keeps RDF XML sidecars out of media byte handoff.

This is metadata preservation only. It does not implement a generic RDF engine,
semantic reasoning, remote resource fetching, office rendering, or Pandoc ODT
runner parity.

The pinned upstream Pandoc checkout was not hydrated at
`/home/claude/port-libs/.upstream-cache/pandoc` in this isolated worktree, so
no local Haskell source inspection or upstream runner command was available for
this slice. The bounded source contract used here is OpenDocument package
semantics for manifest-declared RDF/XML metadata parts.

## Focused Evidence

Focused ODF test:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS maps ODT RDF metadata sidecars into package review metadata
...
1 test files, 3268 assertions, 0 failures
```

WordPress ODF smoke:

```text
php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok
```

Syntax checks:

```text
php -l lanes/pandoc/src/OdfReader.php
No syntax errors detected in lanes/pandoc/src/OdfReader.php

php -l lanes/pandoc/tests/OdfReaderTest.php
No syntax errors detected in lanes/pandoc/tests/OdfReaderTest.php

php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-odf-open-document-handoff.php
```

Status delta:

- `phpPass`: `2494 -> 2495`
- `benchmarkDenominator.mapped`: `2871 -> 2872`
- `odfOpenDocumentCoreCases`: `13 -> 14`
- `mappedOdfOpenDocumentCoreCases`: `13 -> 14`
- `odfOpenDocumentCoreAssertions`: `295 -> 328`
- Focused ODF test growth: `+1` PASS case and `+33` assertions.

## Dependency Closure

No new native PHP support component is needed. This reuses `OdfReader`,
`ZipPackage`, safe DOM XML parsing, the shared Pandoc-like document metadata
handoff, and the existing WordPress ODF smoke.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, external template engine, TeX/PDF engine,
browser renderer, online service, live provider test, live-service provider
test, BibTeX, Biber, or citeproc process was executed.

## Non-Overlap And Follow-Up

This slice does not repeat accepted ODF handling for manifest media,
settings/meta XML, table formulas, sheet-name fields, page continuation,
dropdown fields, metadata fields, database ranges, data pilots, table tracked
changes, draw layers, charts, embedded objects, table cell style provenance,
or table-cell data-style metadata.

Useful ODF follow-up should stay non-overlapping: RDF subject linkage to
inline `text:meta` ranges, formula diagnostics, richer package provenance, or
table style policy.
