# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T085107Z`

Base accepted HEAD: `0ecf84ad404315cb58c4b0b6e028a4e3a9dcf224`

## Behavior Added

- Added bounded OPC relationship selector preflight on
  `OpcRelationshipGraph`.
- `preflightRelationshipSelector()` selects loaded relationships for a source
  part by `SourceId` values and relationship `SourceType` URI values, dedupes
  overlapping selectors into one relationship row, and reuses existing target
  validity diagnostics.
- Selector summaries report unmatched SourceIds, unmatched SourceTypes, empty
  selectors, missing relationship sources, selected-by-id/type flags, target
  parts, content types, external target policy, and relationship target issues.
- Updated the WordPress DOCX OPC preflight example so review packets expose
  selected media, embedded package, and external reviewer-link relationships
  plus an unmatched selector diagnostic before import.

## Source Truth

- OPC digital-signature and review flows identify package relationships by
  relationship Id and relationship Type. This slice ports that selector policy
  as a native PHP preflight helper without implementing cryptographic
  signature validation or canonical XML transform output.
- Pandoc DOCX import already depends on OPC relationship traversal to locate
  the office document, media, metadata, signatures, and embedded package
  resources. Selector preflight is additive on that package graph and does not
  run Pandoc or office tooling.

## Verification

- Baseline focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before this slice: `1 test files, 570 assertions, 0 failures`.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 606 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- Broader lane-local check:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 9409 assertions, 0 failures`.
- PHP syntax checks:
  - `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- Lane JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- Diff whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused OPC tests moved from 41 to 42 PASS cases.
- Focused OPC assertions moved from 570 to 606, adding 36 assertions.
- Lane `phpPass` moved from `786` to `787`.
- Manifest mapped native checks moved from `1246` to `1247`.
- Added `mappedOpcRelationshipSelectorCases = 1` and
  `opcRelationshipSelectorAssertions = 36`.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
and `OpcPackagePath` primitives.

This slice did not invoke Pandoc, Cabal solver/build/test commands, Haskell
runners, Word, LibreOffice, `zip`, `unzip`, external office tools, external
template engines, TeX/PDF engines, browser renderers, online sanitizers, or
online services.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, MIME
content-type validation, Pack URI override and relationship part normalization,
relationship XML namespace/shape parsing, XML NCName Id validation, same-source
target handling, target integrity preflight, relationship-part source
validation, external target policy, package-part and package-consistency
preflight, digital-signature origin/signature preflight, embedded
package/object preflight, relationship Type URI diagnostics, root
office-document preflight, markup-compatibility extension policy, and reachable
relationship closure traversal.

It does not touch Markdown/HTML reader/writer, doctemplate, YAML metadata,
CSL/BibTeX, DOCX body parsing beyond the OPC preflight example, ODT, EPUB3,
PDF, math, legacy DOC/CFB, archive compression, syntax highlighting, charset,
or upstream-runner dependency-audit surfaces.

## Follow-Up

Keep full digital-signature relationship transform serialization and
canonicalization, cryptographic signature validation, encrypted package policy,
embedded package/OLE expansion, and higher-level DOCX UI treatment of
package-consistency diagnostics as separate bounded slices.
