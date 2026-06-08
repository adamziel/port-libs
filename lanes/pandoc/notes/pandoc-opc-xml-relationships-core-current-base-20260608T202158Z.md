# OPC custom XML properties relationships handoff

Slice: `pandoc-opc-xml-relationships-core-current-base-20260608T202158Z`
Base: `e804d88dd32d5db061bbd8258db113c523e8f8c3`

## Behavior

- Added native `customXmlProps` relationship role support in `OpcRelationshipGraph`.
- Custom XML data storage properties targets must be internal package targets.
- Targets must resolve to `application/vnd.openxmlformats-officedocument.customXmlProperties+xml`.
- Sources must be custom XML data storage parts with `application/xml` content type.
- The WordPress DOCX OPC preflight example now reports the valid itemProps target part and package/content-type/reference provenance.

Source-truth evidence:

- ECMA-376 custom XML data storage properties summary records the content type `application/vnd.openxmlformats-officedocument.customXmlProperties+xml`, source relationship `http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps`, and internal target requirement: https://c-rex.net/samples/ooxml/e1/Part1/OOXML_P1_Fundamentals_Custom_topic_ID0EEUAO.html
- OOXML Info mirrors the same part summary and example `customXmlProps` relationship to `itemProps1.xml`: https://ooxml.info/docs/15/15.2/15.2.6/

## Verification

- Baseline before implementation: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 2474 assertions, 0 failures`
- Red-first after adding the focused test: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 2479 assertions, 1 failures`
  - Failure: `customXmlProps` relationships were not yet mapped by WordprocessingML role preflight.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 2515 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - `opc docx preflight self-test ok`

## Non-overlap

This slice avoids the already accepted OPC signature relationship-transform, content-type inventory, fixed content-types item, package path validation, and digital-signature package-role clusters. It only maps the custom XML data storage properties relationship role/content-type handoff.

## Dependency closure

No new support component is needed. The implementation reuses native `OpcRelationshipGraph`, `OpcRelationships`, `OpcContentTypes`, `ZipPackage`, and lane-local fixture/example code. No Pandoc, Word, LibreOffice, zip/unzip, XMLDSig validators, external XML tools, Cabal/Haskell runners, online services, live provider tests, or live-service provider tests were executed.

## Follow-up

Possible non-overlapping OPC follow-up: parse and report the `datastoreItem` root metadata from custom XML properties parts, or map another fixed content-types/package-signature policy gap that is not already covered by the accepted relationship-transform slices.
