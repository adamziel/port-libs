# OPC Content-Type Override Part Provenance

Slice: `pandoc-opc-xml-relationships-core-current-base-20260609T032954Z`

Base accepted HEAD: `507b06f9840603abbb77bf4b360c0377f959830e`

## Behavior

This slice adds bounded package-part provenance to `OpcRelationshipGraph::preflightContentTypeOverrides()`.

Each content-type override row now reports:

- `packagePartName`: the stored package part name matched by the override, or `null` for stale overrides.
- `partNameExactMatch`: whether the override `PartName` exactly matches the stored package part name.
- `partNameEquivalentMatch`: whether the override resolves only through OPC ASCII case-equivalent package part matching.

The existing `exists`, `valid`, and `issues` behavior is preserved. `preflightPackageConsistency()` carries the same override rows so DOCX import review can distinguish exact matches, case-equivalent stored entries, and missing stale override declarations.

The WordPress DOCX OPC preflight example now surfaces the same provenance in its integrity summary for a package whose `[Content_Types].xml` declares lowercase override part names while the package stores `Word/Document.XML` and `Word/Styles.XML`.

## Evidence

- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `1 test files, 3074 assertions, 0 failures`.
- Focused delta: `+1` PHP PASS case, `+24` assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` -> `opc docx preflight self-test ok`.
- Root harness: not run - isolated micro-slice.

## Movement

- `lane-status.json` `phpPass`: `2233 -> 2234`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2642 -> 2643`.
- `opcRelationshipGraphSupportCases`: `13 -> 14`.
- `mappedOpcRelationshipGraphSupportCases`: `13 -> 14`.
- Added `mappedOpcContentTypeOverridePartProvenanceCases: 1`.
- `opcRelationshipGraphAssertions`: `210 -> 234`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `OpcRelationshipGraph`, `OpcContentTypes`, `ZipPackage`, the focused OPC test suite, and the lane-local WordPress DOCX OPC preflight example. No Pandoc, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, signing engine, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This does not repeat accepted OPC content-type parsing, duplicate override rejection, relationship target preflight, source-equivalent relationship loading, reachable closure traversal, signature relationship transforms, duplicate selector provenance, same-document SignedInfo references, embedded/encrypted package policy, XMLDSig cryptographic validation, or content-type inventory counts. It only adds override-to-stored-part provenance for content-type override preflight and consistency rows.

## Follow-up

Useful next OPC work is bounded DOCX reader consumption of override provenance, relationship source closure diagnostics in import reports, or another non-overlapping XMLDSig reference policy guard. Keep cryptographic XMLDSig verification, canonical XML engines, and external XML validators out of scope for this lane.
