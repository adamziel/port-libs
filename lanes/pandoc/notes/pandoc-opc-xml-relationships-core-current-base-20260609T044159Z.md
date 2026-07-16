# Pandoc OPC XML Relationships Current-Base Slice

Session: `port-dev-pandoc-opc-relationships-20260609T044159Z`
Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260609T044159Z`
Base accepted HEAD: `7c6ac18f8d3be98468babe4130239bcc5539af33`

## Implementation

This slice adds bounded OPC content-type resolution provenance for package
review and relationship-target preflight:

- `OpcContentTypes::contentTypeResolutionForPart()` now reports the canonical
  part name, resolved content type, whether the match came from a package
  default, an explicit override, or no declaration, and the matching default
  extension or override part-name provenance.
- `OpcRelationshipGraph::preflightPackageParts()` and
  `preflightTargetsForSource()` now carry the same provenance fields alongside
  existing `contentType` values.
- `preflightPackageConsistency()` receives the provenance through
  `preflightAllRelationshipTargets()`, so DOCX/OPC import review packets can
  distinguish default-derived media/XML parts from explicit content-type
  overrides.
- The WordPress DOCX OPC preflight smoke exposes the provenance for relationship
  parts, the main document override, image defaults, and package-consistency
  relationship targets.

## Evidence

Red-first probe after adding the focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
1 test files, 3211 assertions, 1 failures
Call to undefined method PortLibs\Pandoc\OpcContentTypes::contentTypeResolutionForPart()
```

Final focused verification:

```text
php -l lanes/pandoc/src/OpcContentTypes.php
php -l lanes/pandoc/src/OpcRelationshipGraph.php
php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php
php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php
No syntax errors detected in changed PHP files

php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
1 test files, 3237 assertions, 0 failures

php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test
opc docx preflight self-test ok

php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
pandoc json ok

git diff --check -- lanes/pandoc
passed with no output
```

Focused movement:

- `phpPass`: `2317 -> 2318`
- `benchmarkDenominator.mapped`: `2717 -> 2718`
- `mappedOpcXmlRelationshipContentTypeCases`: `11 -> 12`
- New `mappedOpcContentTypeResolutionProvenanceCases`: `1`
- New `opcContentTypeResolutionProvenanceAssertions`: `26`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`OpcContentTypes`, `OpcRelationshipGraph`, focused OPC package tests, and the
existing WordPress DOCX OPC preflight example.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive,
XMLDSig validator, external XML tool, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This does not repeat accepted OPC content-type parsing, duplicate override
rejection, override part-name provenance, relationship target preflight,
internal target query/fragment metadata, external target policy, relationship
transform selectors, signature manifest/SignedInfo references, embedded or
encrypted package policy, relationship source closure traversal, relationship
part load summaries, DrawingML role inventory, or write-time relationship
serialization guards. It only maps default/override resolution provenance for
already-resolved OPC content types.

## Follow-Up

Next OPC work should target a separate package-semantics gap, such as
higher-level DOCX consumption of existing OPC diagnostics, source-closure import
policy, or a distinct XMLDSig reference-policy edge.
