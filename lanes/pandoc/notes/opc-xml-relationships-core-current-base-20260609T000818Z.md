# OPC XML Relationships Core Current Base - Office Document Readiness

Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260609T000818Z`
Base accepted HEAD: `35d557737dc1b88c45279aeb585788c53834812d`

## Behavior

Added `OpcRelationshipGraph::preflightOfficeDocumentRelationshipReadiness()` as a native PHP importer handoff for DOCX package relationship readiness. The report composes existing OPC primitives:

- package-root `officeDocument` relationship cardinality and content-type preflight;
- selected relationship-source closure counts and issues from the office document root;
- WordprocessingML document relationship role counts, invalid role rows, and importer-facing issue aggregation.

The WordPress DOCX OPC preflight smoke now exposes the readiness report at top level and in `wordpressImport.officeDocumentRelationshipReadiness`.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `1 test files, 2863 assertions, 0 failures`
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `1 test files, 2906 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` -> `opc docx preflight self-test ok`
- PHP lint for changed PHP files passed.

## Dependency Closure

No new support component is needed. This reuses native PHP OPC relationship loading, content-type matching, relationship-source closure traversal, and WordprocessingML role preflight. No Pandoc, Word, LibreOffice, zip/unzip, external XML tooling, XMLDSig validators, online services, live provider tests, or live-service provider tests were run.

## Non-Overlap

This does not repeat accepted OPC slices for signature reference content-type, content-type inventory, Pack URI part-name validation, relationship target preflight, nested `_rels` payload guards, relationship transform selectors, digital signature object policy, or package signature manifest cross-checks. It adds only a composed office-document readiness handoff for DOCX import review.
