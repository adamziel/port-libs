# Pandoc OPC XML Relationships Current-Base Slice

Slice: `pandoc-opc-xml-relationships-core-current-base-20260606T223348Z`
Base: `d6cb1115b3a57bbc22a114ba70f49c1e4b8a243d`
Lane: `pandoc`

## Behavior

- Added bounded OPC digital-signature metadata preflight in `OpcRelationshipGraph::preflightDigitalSignatureMetadata()`.
- The preflight summarizes direct `ds:Object` package-signature metadata, `mdssi:SignatureTime` format/value validity, and `ds:X509Certificate` base64 payload length, decoded byte count, and SHA-256 digest.
- The method validates signature part existence and content type but does not perform cryptographic XMLDSig validation, certificate trust-chain validation, or external validator execution.
- Updated the WordPress DOCX OPC preflight example to expose signature object/certificate metadata and WordPress import shortcuts for signature certificate count and signing time.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Failed with `1 test files, 1427 assertions, 1 failures` because `PortLibs\Pandoc\OpcRelationshipGraph::preflightDigitalSignatureMetadata()` was missing.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Passed with `1 test files, 1461 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Passed with `opc docx preflight self-test ok`.

## Status Delta

- `lane-status.json` `phpPass`: `1409 -> 1410`.
- `UPSTREAM_TEST_MANIFEST.json` mapped count: `1822 -> 1823`.
- Latest focused OPC test coverage: `OpenPackagingConventionsTest.php` now reports `1 test files, 1461 assertions, 0 failures`.

## Non-Overlap

This slice does not repeat the previously accepted OPC record-shape diagnostics, content-type inventory, Pack URI part-name validation, relationship transform selector/reference handling, or digital-signature origin/signature relationship graph checks. It covers package-signature object/certificate metadata only.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`, `OpcRelationships`, `OpcRelationshipGraph`, and `XmlHtmlDom` helpers. Pandoc/Cabal/Haskell runners, Word, LibreOffice, zip/unzip, XMLDSig validators, external XML tools, online services, live provider tests, and live-service provider tests were not run.

Next bounded follow-up: wire the existing OPC relationship and package-signature metadata preflight results into DOCX reader import reports, or choose another non-overlapping OPC package semantic.
