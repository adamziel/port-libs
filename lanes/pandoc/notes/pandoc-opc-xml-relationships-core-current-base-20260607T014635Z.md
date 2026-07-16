# OPC Relationships XML Serialization Slice

- Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260607T014635Z`
- Base accepted HEAD: `ee38ac4e40d34d8ace81ef748756b7c6f6cb32f9`
- Lane: `pandoc`

## Behavior

`OpcRelationships::toXml()` now serializes internal relationship `Target` path bytes as URI-escaped XML attribute values while preserving query and fragment suffixes, existing valid percent escapes, external targets, and omitted `TargetMode="Internal"` semantics.

The focused case covers raw spaces and UTF-8 bytes in programmatic internal targets, round-trips those serialized targets through `fromXml()`, preserves already escaped package-path segments, and rejects malformed percent escapes before writing invalid relationship XML.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 1502 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 1512 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` passed with `opc docx preflight self-test ok`.
- PHP lint: `php -l` passed for changed PHP files.
- Diff check: `git diff --check -- lanes/pandoc` passed with no output.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1430 -> 1431`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1846 -> 1847`.
- Added one mapped OPC relationship XML serialization support case.
- Added `+1` PHP PASS case and `+10` focused assertions in `OpenPackagingConventionsTest.php`.

## Dependency Closure

No new support component is needed for this slice. It reuses native PHP `ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`, `OpcPackagePath`, the existing WordPress DOCX OPC preflight example, and the focused lane PHP harness.

Full Pandoc package-reader parity, XML digital signature canonicalization/digest/trust validation, encrypted package handling, office-suite validation, external XML tooling, and upstream Pandoc/Haskell runner parity remain separate bounded follow-up work.

## Non-Overlap

This patch avoids the accepted OPC content-type inventory, signature reference ContentType query preflight, role content-type matching, Pack URI part-name validation, relationship Id validation, target-mode guard, package-part reference inventory, reachable closure traversal, and target preflight diagnostic slices.

The only owned behavior here is serialization of programmatic internal relationship target path bytes in `OpcRelationships::toXml()` plus the WordPress DOCX OPC preflight smoke guard for that serialization path.

## Follow-Up

Potential follow-up work should remain bounded to non-overlapping package XML semantics such as relationship-transform canonicalization details, stricter relationship target query/fragment URI diagnostics, or DOCX reader wiring for existing OPC preflight rows. Do not execute Pandoc, Word, LibreOffice, zip/unzip, XMLDSig validators, external XML tools, online services, or live provider tests from this lane.
