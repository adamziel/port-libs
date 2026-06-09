# OPC Relationships Current-Base Package-Signature Object Policy

Slice: `pandoc-opc-xml-relationships-core-current-base-20260608T234643Z`
Base accepted HEAD: `8de98f7be6b3061163f59a877ceb2d0185a29890`

## Behavior

- Extended `OpcRelationshipGraph::preflightDigitalSignatureMetadata()` with bounded package-signature object policy metadata.
- Reports direct `ds:Object` IDs, duplicate object IDs, per-object ID occurrence counts, `SignatureProperty` target policy, Manifest IDs, duplicate Manifest IDs, and missing Manifest ID counts.
- Fails closed with explicit policy issues for duplicate signature-object IDs, missing/unmatched/ambiguous/non-same-document `SignatureProperty Target` values, and duplicate Manifest IDs.
- Updated the WordPress DOCX OPC preflight example to surface a compact `wordpressImport.digitalSignatureObjectPolicy` summary for importer review.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Baseline focused command: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 2792 assertions, 0 failures`
- Final focused command: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 2863 assertions, 0 failures`
  - Delta: `+71` focused assertions and one lane PASS case.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`
- PHP lint: changed PHP files passed `php -l`.
- JSON validation: `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` parsed with `JSON_THROW_ON_ERROR`.
- Diff whitespace: `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native OPC graph preflight, existing DOM parsing, `ZipPackage` fixtures, focused PHP tests, and the existing WordPress DOCX OPC preflight example. No Pandoc, Word, LibreOffice, zip/unzip, external XML tooling, XMLDSig validator, Cabal/Haskell runner, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat the accepted OPC content-type inventory, Pack URI validation, relationship target preflight, nested `_rels` payload-segment rejection, relationship-transform content-type query, digest algorithm/value policy, encrypted/embedded package policy, or DOCX DrawingML hyperlink handoff slices.

## Follow-Up

- If package signature byte canonicalization is later added, connect this metadata to digest comparison without shelling out to XMLDSig validators.
- Consider threading the object policy summary into DOCX import reports so package-signature review metadata is available beside core DOCX body/properties output.
