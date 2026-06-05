# Pandoc OPC Part Name Equivalence Slice 2026-06-05

## Scope

Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260605T134700Z`.

Accepted base: `09789c95d9b9938ab902a637c46d97251cf0b7ee`.

This slice stays inside the bounded native PHP OPC/XML relationship package
support row. It does not run Pandoc, Word, LibreOffice, `zip`/`unzip`, Haskell
test binaries, XMLDSig validators, office tools, online conversion services,
or external package tooling.

## Behavior Added

- `OpcContentTypes` now treats override part names as ASCII case-insensitive
  equivalent keys for lookup while preserving the original override spelling
  for diagnostics and XML serialization.
- Duplicate equivalent content-type overrides such as `/Word/Document.XML` and
  `/word/document.xml` are rejected during builder use and XML parsing.
- `OpcRelationshipGraph::preflightPackagePartNameEquivalence()` reports package
  parts whose OPC part names differ only by ASCII case, including
  `equivalentPartNames` and `equivalent-part-name-case-collision` diagnostics.
- `OpcRelationshipGraph::fromPackage()` rejects case-colliding package parts
  before loading content types or relationship graphs, so a DOCX/OPC importer
  cannot silently trust one ambiguous part over another.
- The WordPress DOCX OPC preflight smoke exposes the case-collision guard for
  document and media package parts before they become importable review bytes.

## Source Truth

OPC part-name equivalence is case-insensitive over ASCII characters. Microsoft
documents the .NET `System.IO.Packaging` change as aligning package-part URI
comparisons with the OPC requirement that a package "must not have multiple
equivalent part names"; see
https://learn.microsoft.com/en-us/dotnet/core/compatibility/core-libraries/8.0/system-io-packaging-case-insensitive-uri.

This slice ports the package contract, not full Office suite behavior. Broader
XML canonicalization, digital-signature validation, markup-compat filtering,
and complete upstream Pandoc DOCX parity remain separate support rows.

## Verification

- Baseline focused OPC test before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 804 assertions, 0 failures`
- Red-first focused OPC test with the new part-name equivalence case before the
  implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Failed as expected on `preflights case-insensitive OPC part-name equivalence collisions`
  - `Expected: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'`
  - `Actual: 'application/xml'`
  - `1 test files, 805 assertions, 1 failures`
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 825 assertions, 0 failures`
- WordPress DOCX OPC preflight smoke:
  `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - `opc docx preflight self-test ok`
- PHP lint:
  `php -l lanes/pandoc/src/OpcContentTypes.php`
  `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- JSON validation:
  `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
  - Result: both JSON files parsed successfully.
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused OPC assertions moved from 804 to 825.
- Focused OPC test cases moved from 50 to 51.
- `lane-status.json` `phpPass` moves from 928 to 929.
- `UPSTREAM_TEST_MANIFEST.json` mapped native inventory moves from 1,385 to
  1,386 with one bounded OPC part-name equivalence preflight case.

## Dependency Closure

No new native support component is needed. The slice reuses the existing
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
and `OpcPackagePath` support rows.

The full upstream Pandoc runner gate remains open because this worktree has no
hydrated Pandoc checkout or Haskell runner build closure. That does not block
this bounded native OPC package behavior.

## Non-Overlap

This does not repeat accepted OPC content-type parsing, relationship-source
alias preflight, XML relationship Id validation, target integrity preflight,
reachable closure traversal, embedded package placeholders, digital signature
origin relationship handling, relationship-transform selector preflight, strict
XML shape checks, DOCX body parsing, media import reporting, or archive/ZIP
metadata slices.

## Follow-Up

Future OPC slices can separately cover unique case-equivalent relationship
target resolution, broader markup-compatibility relationship filtering, XML
signature canonicalization and digest validation, or richer DOCX relationship
consumption. Full Pandoc DOCX runner parity still needs the upstream-runner
dependency gate described in the upstream-runner notes.
