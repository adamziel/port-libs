# Pandoc OPC XML Relationships Core Current Base - 2026-06-08T033514Z

## Scope

Implemented one bounded OPC relationship role policy needed by richer DOCX/OpenXML package preflight: WordprocessingML `customXml` relationships are now reported by `OpcRelationshipGraph::preflightWordprocessingDocumentRelationships()` as `custom-xml` role rows instead of remaining only in the generic target preflight.

The role policy expects internal package targets with `application/xml` content type and preserves the generic target diagnostics. Focused coverage now includes valid custom XML data storage, wrong content type, external target mode, and missing target part diagnostics.

## Source Truth

- Upstream format contract: Open Packaging Conventions / WordprocessingML relationship type `http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml` identifies custom XML data storage parts in DOCX-style packages.
- Local bounded implementation source: `lanes/pandoc/src/OpcRelationshipGraph.php`.
- Focused verification source: `lanes/pandoc/tests/OpenPackagingConventionsTest.php`.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed for this slice before editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2025 assertions, 0 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2054 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` passed with `opc docx preflight self-test ok`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat prior OPC content-type inventory, Pack URI part-name validation, relationship target preflight, relationship XML record-shape diagnostics, reachable closure traversal, embedded package relationships, thumbnail relationships, digital-signature relationship-transform selectors, or signature reference content-type query preflight. It only adds the missing WordprocessingML custom XML role policy on top of existing OPC target and content-type primitives.

## Dependency Closure

No new support component is needed. The slice reuses native PHP OPC content-type lookup, relationship target resolution, and WordprocessingML relationship role preflight. Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external XML validators, online services, live provider tests, and live-service provider tests were not executed.

## Next

A useful follow-up is custom XML item-properties relationship preflight or surfacing custom XML role diagnostics in the DOCX import report, still bounded to native PHP package semantics.
