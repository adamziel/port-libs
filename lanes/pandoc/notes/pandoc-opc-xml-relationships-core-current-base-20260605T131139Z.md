# Pandoc OPC Relationship Source Alias Slice 2026-06-05

## Scope

Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260605T131139Z`.

Accepted base: `6a7cca96e3041d70a102c1990a9f40af70809228`.

This slice stays inside the bounded native PHP OPC/XML relationship package
support row. It does not run Pandoc, Word, LibreOffice, `zip`/`unzip`, Haskell
test binaries, XMLDSig validators, office tools, online conversion services,
or external package tooling.

## Behavior Added

- `OpcRelationshipGraph::fromPackage()` now rejects multiple `.rels` parts
  that resolve to the same OPC relationship source after package path URI
  decoding.
- `OpcRelationshipGraph::preflightRelationshipPartsInPackage()` now reports
  `duplicateRelationshipPartNames` and the issue
  `duplicate-relationship-source` for every colliding relationship part.
- Duplicate-source relationship parts remain unparsed during preflight so a
  DOCX/OPC importer cannot silently trust one relationship set while another
  alias is overwritten.
- The WordPress DOCX OPC preflight smoke exposes the guard for a package with
  both `word/_rels/review%20source.xml.rels` and
  `word/_rels/review source.xml.rels`.

## Source Truth

OPC relationship parts identify the source part represented by their `_rels`
part name. In this bounded PHP package model, percent-encoded package path
segments are decoded to the canonical part name used by relationship target
resolution. Allowing two distinct relationship-part entry names to resolve to
one source would make relationship closure ambiguous and previously let the
later parsed set overwrite the earlier set.

This slice ports the package contract, not full Office suite behavior. Broader
XML canonicalization, digital-signature validation, and complete upstream
Pandoc DOCX parity remain separate support rows.

## Verification

- Baseline focused OPC test before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 785 assertions, 0 failures`
- Red-first focused OPC test with the new duplicate-source case before the
  implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Failed as expected on `preflights duplicate OPC relationship parts resolving to the same source`
  - `Expected: false`
  - `Actual: true`
  - `1 test files, 792 assertions, 1 failures`
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 804 assertions, 0 failures`
- WordPress DOCX OPC preflight smoke:
  `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - `opc docx preflight self-test ok`

Final lint, JSON validation, and whitespace verification are recorded in the
handoff response for this worker.

## Status Delta

- Focused OPC assertions moved from 785 to 804.
- Focused OPC test cases moved from 49 to 50.
- `lane-status.json` `phpPass` moves from 914 to 915.
- `UPSTREAM_TEST_MANIFEST.json` mapped native inventory moves from 1,372 to
  1,373 with one bounded OPC relationship-source alias preflight case.

## Dependency Closure

No new native support component is needed. The slice reuses the existing
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
and `OpcPackagePath` support rows.

The full upstream Pandoc runner gate remains open because this worktree has no
hydrated Pandoc checkout or Haskell runner build closure. That does not block
this bounded native OPC relationship package behavior.

## Non-Overlap

This does not repeat accepted OPC content-type parsing, XML relationship Id
validation, target integrity preflight, reachable closure traversal, embedded
package placeholders, digital signature origin relationship handling,
relationship-transform selector preflight, strict XML shape checks, DOCX body
parsing, media import reporting, or archive/ZIP metadata slices.

## Follow-Up

Future OPC slices can separately cover case-collision handling for package part
names, broader markup-compatibility relationship filtering, or XML signature
relationship transform validation. Full Pandoc DOCX runner parity still needs
the upstream-runner dependency gate described in the upstream-runner notes.
