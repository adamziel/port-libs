# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T003611Z`

Base accepted HEAD: `20dfe2be1051f3aa7ba6cdf25cd8a0bf19059ec8`

## Behavior Added

- Added bounded external OPC relationship target policy preflight.
- `OpcRelationship::externalTargetPreflight()` now classifies external targets
  as `absolute-uri`, `network-path-reference`, `relative-reference`, or
  `fragment-reference`, exposes the lowercase scheme when present, and reports
  unsafe `data`, `file`, `javascript`, and `vbscript` schemes.
- `OpcRelationshipGraph::preflightTargetsForSource()` and
  `reachableTargetsForSource()` now include `externalTargetKind`,
  `externalTargetScheme`, and `externalTargetAllowed` fields for every target.
  Internal package targets keep those fields as `null`.
- Updated `wordpress-docx-opc-preflight.php` so WordPress import audit packets
  surface safe reviewer links and unsafe external relationship targets instead
  of treating every `TargetMode="External"` relationship as safe.

## Source Truth

- Microsoft `System.IO.Packaging.PackageRelationship.TargetUri` documents OPC
  behavior: `Internal` targets are relative references, while `External`
  targets may be relative references or fully qualified URIs:
  https://learn.microsoft.com/en-us/dotnet/api/system.io.packaging.packagerelationship.targeturi
- Microsoft `TargetMode` documents that `External` relationships target
  resources outside the package:
  https://learn.microsoft.com/en-us/dotnet/api/system.io.packaging.targetmode
- The existing Pandoc lane package contract remains bounded native PHP OPC
  semantics for DOCX/EPUB package preflight; no upstream Haskell runner parity
  is claimed by this slice.

## Red Check

- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before implementation: failed with missing `externalTargetKind`
    fields in 2 tests; `1 test files, 245 assertions, 2 failures`.

## Verification

- `php -l lanes/pandoc/src/OpcRelationship.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/DocxReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 279 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 4,723 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused OPC tests moved from 23 to 24 PASS cases.
- Focused OPC assertions moved from 248 to 279, adding 31 assertions.
- Lane `phpPass` moved from `470` to `471`.
- Manifest mapped native checks moved from `942` to `943`.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
`OpcPackagePath`, and `XmlHtmlDom` package helpers.

This slice did not invoke Pandoc, Cabal, Haskell runners, Skylighting,
citeproc, BibTeX, Biber, Word, LibreOffice, `zip`, `unzip`, `tar`, `lz4`,
external template engines, TeX/PDF engines, browser renderers, roff, Typst,
MathJax, KaTeX, online sanitizers, or online services.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, content-type
parsing, relationship XML parsing, XML NCName-style Id validation, URI target
decoding, target integrity preflight, package-part orphan/content-type
preflight, relationship-part source validation, and reachable relationship
closure traversal.

It does not touch Markdown/HTML reader/writer, doctemplate, YAML metadata,
CSL/BibTeX, DOCX body parsing beyond type annotations, ODT, EPUB3, PDF, math,
legacy DOC/CFB, archive compression, syntax highlighting, charset, or
upstream-runner dependency-audit surfaces.

## Follow-Up

Keep digital signature origin relationships, embedded package policy, external
relative-reference rewrite policy, stricter MIME grammar validation, and any
higher-level DocxReader UI treatment of unsafe external targets as separate
bounded slices.
