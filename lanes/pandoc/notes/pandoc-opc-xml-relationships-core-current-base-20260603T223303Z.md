# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260603T223303Z`

Base accepted HEAD: `37107328aa2e26664de5326c647d0bffd6b7b48c`

## Behavior Added

- Added `OpcRelationshipGraph::preflightTargetsForSource()` as a bounded
  native PHP OPC package integrity helper.
- The preflight keeps accepted relationship resolution behavior intact while
  adding diagnostics for:
  - valid internal targets with package entry and content-type confirmation;
  - internal targets missing from the ZIP package;
  - internal targets present in the package but missing a content-type mapping;
  - relationships that target `.rels` relationship parts;
  - external targets that bypass package-entry checks;
  - invalid internal traversal targets that are reported without crashing the
    preflight call.
- Updated the WordPress DOCX OPC preflight smoke to expose document
  relationship integrity status before DOCX import handoff.

## Source Truth

- Existing accepted Pandoc lane evidence records upstream Pandoc DOCX reader
  behavior from `src/Text/Pandoc/Readers/Docx/Parse.hs`: read `_rels/.rels`,
  locate the `officeDocument` relationship by type, and use that target as the
  document XML part.
- This slice stays within OPC package semantics needed by that conversion
  path: relationship targets must resolve safely, internal targets should
  correspond to package parts with content types, external targets are not ZIP
  entries, and relationship parts are package infrastructure rather than
  conversion payload targets.

## Verification

- Before focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 128 assertions, 0 failures`.
- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 157 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `6 test files, 2779 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new external support component is needed. This slice reuses accepted native
PHP `ZipPackage`, `OpcContentTypes`, `OpcRelationships`, and
`OpcRelationshipGraph` primitives. It does not invoke Pandoc, Word,
LibreOffice, zip/unzip, external template engines, TeX/PDF engines, Haskell
test binaries, bibliography tools, or online services.

## Non-Overlap

This patch is additive on top of accepted ZIP read/write, OPC content-type and
relationship parsing/loading, OPC graph summaries, doctemplate, YAML metadata,
CSL citation, and DOCX body/core-property parsing. It does not edit root
dashboard/progress files and does not touch Markdown/HTML reader/writer,
EPUB/ODT, PDF, BibTeX, math, syntax highlighting, or upstream-runner audit
surfaces.

## Follow-Up

Wire these OPC preflight diagnostics into higher-level DOCX import reports when
the lane adds style/numbering/list/media-policy handling. Keep comments,
endnotes, richer table grid spans, CSL style XML/locales, BibTeX/BibLaTeX, and
broader Cabal/upstream-runner dependency planning as separate bounded gates.
