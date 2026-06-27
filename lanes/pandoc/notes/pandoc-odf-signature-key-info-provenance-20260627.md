# Pandoc ODF/ODT Signature KeyInfo Package Slice - 2026-06-27

## Behavior

- `OdfReader` now preserves metadata-only `dsig:KeyInfo` provenance from ODT signature sidecars.
- Signature summaries carry key-name counts, retrieval-method URI/type rows, X.509 subject names, issuer/serial rows, and certificate base64 length plus SHA-256 digests.
- Certificate payload text remains omitted from the document handoff under `odf-signature-key-info-metadata-only`.

## Evidence

- `php -l lanes/pandoc/src/OdfReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OdfReaderSignatureKeyInfoProvenanceTest.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderSignatureKeyInfoProvenanceTest.php lanes/pandoc/tests/OdfReaderSignatureTransformProvenanceTest.php`
  - Result: `2 test files, 61 assertions, 0 failures`.

## Dependency Closure

No external signing tools, office suites, Pandoc runner, certificate validators, network fetches, `zip`/`unzip`, or browser engines were invoked. The slice stays in native PHP ODF package parsing under `lanes/pandoc`.
