# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260604T134226Z`

Base accepted HEAD: `88b69a502e4423c09832726fbb04801b90bc0bf5`

## Behavior Added

- Added `OpcRelationshipGraph::reachableTargetsForSource()` as a bounded native
  PHP OPC relationship closure helper.
- The closure starts from a source part, optionally filters the first
  relationship hop by type, then walks reachable internal target parts that have
  their own `.rels` part.
- Each reachable relationship keeps the existing preflight diagnostics and adds:
  source part, traversal depth, resolved internal `targetPart`, content type,
  existence, external flag, relationship-part-target flag, validity, and issues.
- Missing package parts, unsafe internal targets, and targets that point at
  relationship infrastructure are reported but not traversed.
- Cyclic package graphs are bounded by visited/queued source tracking.
- Updated the WordPress DOCX OPC preflight example so nested footnote media is
  discovered from the package-root `officeDocument` relationship closure.

## Source Truth

- Existing accepted Pandoc lane evidence records upstream Pandoc DOCX reader
  behavior from `src/Text/Pandoc/Readers/Docx/Parse.hs`: read `_rels/.rels`,
  locate the `officeDocument` relationship by type, and use that target as the
  document XML part.
- This slice stays in the OPC package layer needed by DOCX/OpenXML readers:
  relationship parts form a directed graph from source parts to internal package
  parts or external URIs, and conversion preflight needs a safe reachable
  dependency closure before handing document/body/media parts to readers.

## Verification

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 188 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `10 test files, 3136 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Focused OPC assertions moved from the prior accepted `157` to `188`, adding 31
assertions across 3 new PASS cases. Root harness not run - isolated
micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, and
`OpcRelationshipGraph` primitives. It does not invoke Pandoc, Word,
LibreOffice, zip/unzip, external template engines, TeX/PDF engines, Haskell
test binaries, bibliography tools, or online services.

## Non-Overlap

This patch is additive on top of accepted ZIP read/write, OPC content-type and
relationship XML parsing/loading, graph summaries, and target preflight. It does
not edit root dashboard/progress files and does not touch Markdown/HTML
reader/writer, doctemplate, YAML metadata, CSL, DOCX body parsing, ODT, PDF,
math, legacy DOC/CFB, archive compression, or upstream-runner dependency-audit
surfaces.

## Follow-Up

Wire reachable OPC closure diagnostics into higher-level `DocxReader` import
reports and media extraction policy in a separate bounded DOCX/OpenXML slice.
Keep ZIP64/symlink/NTFS extra-field decisions, richer DOCX nested numbering,
ODT embedded objects, CSL style XML/locales, BibTeX/BibLaTeX, and the Pandoc
Haskell runner dependency plan as separate gates.
