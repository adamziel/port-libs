# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T000554Z`

Base accepted HEAD: `d93839bf1059e9e384bbc118a734c74a08e4f5ec`

## Behavior Added

- Added native relationship-part source validation for OPC relationship sets.
- `OpcRelationships` now rejects `.rels` package parts as relationship sources,
  while still allowing the package root `/` and ordinary source parts.
- `OpcRelationshipGraph::fromPackage()` skips nested relationship metadata such
  as `/word/_rels/_rels/document.xml.rels.rels` instead of treating a
  relationship part as a traversable source part.
- `OpcRelationshipGraph::preflightPackageParts()` now reports
  `relationshipSourceIsRelationshipPart` plus a `relationship-part-source`
  issue so DOCX/OPC import review packets surface the invalid package
  structure without silently walking it.
- Updated the WordPress DOCX OPC preflight smoke to expose the new
  relationship-source classification field.

## Source Truth

- Existing Pandoc DOCX package loading depends on OPC package relationships:
  read `_rels/.rels`, locate the `officeDocument` relationship, then load
  part-local relationships for concrete source parts.
- OPC relationship parts are package infrastructure. A relationship part belongs
  to the package root or to a concrete source part; it is not itself a content
  source with a second nested relationship part.
- This slice stays within bounded native PHP OPC package semantics and does not
  attempt upstream Haskell runner parity.

## Verification

- `php -l lanes/pandoc/src/OpcRelationships.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 248 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `18 test files, 4,345 assertions, 0 failures` and 436 PASS lines.

Focused OPC tests moved from 22 to 23 PASS cases and from 230 to 248 assertions,
adding 1 focused PASS case and 18 assertions. Lane status records `phpPass`
`441 -> 442` and mapped native checks `909 -> 910`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
`OpcPackagePath`, and `XmlHtmlDom` primitives.

It did not invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice,
zip/unzip, external template engines, TeX/PDF engines, browser renderers, or
online services.

## Non-Overlap

This patch is additive on top of accepted ZIP/OPC package primitives,
content-type parsing, relationship XML parsing, XML NCName-style Id validation,
URI target decoding, target integrity preflight, package-part orphan/content-type
preflight, and reachable relationship closure traversal. It does not touch
Markdown/HTML reader/writer, doctemplate, YAML metadata, CSL/BibTeX, DOCX body
parsing, ODT, EPUB3, PDF, math, legacy DOC/CFB, archive compression, syntax
highlighting, charset, or upstream-runner dependency-audit surfaces.

## Follow-Up

Keep digital signature origin relationships, embedded package policy, external
target allow/deny policy, and stricter MIME grammar validation as separate
bounded OPC slices.
