# pandoc-opc-xml-relationships-core-current-base-20260609T083503Z

Date: 2026-06-09 UTC

Base accepted HEAD: `436db66ac9717cbf75ff2ec29905ae0ddef22b3a`

## Scope

Implemented a bounded OPC signed relationship type-policy summary for importer
review. `OpcRelationshipGraph::signedRelationshipPolicySummary()` now reuses
existing signature RelationshipTransform preflight rows and reports:

- allowed signed relationship types supplied by the caller;
- selected signed relationship ids, types, internal target parts, and external
  targets;
- disallowed relationship types and disallowed relationship row details;
- external signed targets, unsafe external signed targets, missing selected
  targets, invalid selected relationships, issue buckets, and per-transform
  policy validity.

The existing RelationshipTransform materialization behavior is unchanged.

## WordPress Scenario

`wordpress-docx-opc-preflight.php --self-test` now exposes
`signedRelationshipPolicySummary` and a compact
`wordpressImport.signedRelationshipPolicy` view. The smoke fixture allows signed
embedded packages and signed images while flagging the signed reviewer hyperlink
as a disallowed external relationship for import review.

## Evidence

Red-first focused run:

`php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`

Result: `1 test files, 3569 assertions, 1 failures`; failure was only the
missing `OpcRelationshipGraph::signedRelationshipPolicySummary()` method.

After implementation:

`php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`

Result: `1 test files, 3594 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`

Result: `opc docx preflight self-test ok`.

Focused assertion delta: `+25`.
Focused PASS delta: `+1`.
Manifest mapped OPC relationship graph support delta: `+1`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`OpcRelationshipGraph`, `OpcRelationships`, existing signature
RelationshipTransform preflight, external target policy, content-type lookup,
and `ZipPackage` fixtures. Pandoc, Cabal/Haskell runners, Word, LibreOffice,
zip/unzip, external template engines, TeX/PDF engines, browser renderers,
online services, live providers, and office automation were not executed.

## Non-Overlap

This slice avoids the accepted OPC clusters for relationship transform
fingerprints, transform provenance summaries, digest-policy summaries, reference
URI shape guards, enveloped-signature guards, content-type query checks, and
relationship part canonicalization. The new behavior is only the importer
allowlist policy layer over already-selected signed relationships.

## Root Harness

Not run - isolated micro-slice.

## Follow-Up

Potential next OPC work: relationship-part canonicalization edge cases, higher
level DOCX import-gate wiring for signed relationship policies, or additional
relationship role allowlists for ODT/EPUB package preflight.
