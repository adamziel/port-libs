# Pandoc JSON/native textual cite sidecars

`plib-d9pse` preserves textual Native `Cite` and `Citation` constructor
payloads on the shared AST. `NativeReader` now carries the canonical `Cite`
wrapper, citation record list, record payloads, citation mode helpers, and
prefix/suffix/display inline native payloads through the same sidecar fields
used by JSON-native packet input.

This is additive constructor provenance only. Citation node shapes, rendered
Markdown/WordPress behavior, and generated writer output remain unchanged for
the existing textual Native citation fixture.

Accounting:

- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedJsonNativeTextualCitationSidecarCases`: `0 -> 1`
- `UPSTREAM_TEST_MANIFEST.json` `inventory.jsonNativeTextualCitationSidecarAssertions`: `0 -> 49`

Validation:

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderTextualCitationConstructorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTextualCitationConstructorTest.php`
  passed: 1 file, 49 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTextConstructorProvenanceTest.php`
  passed: 1 file, 166 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/NativeWriterCitationGroupInlineTest.php`
  passed: 1 file, 11 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PandocNativeWriterJsonProvenanceTest.php`
  passed: 1 file, 15 assertions, 0 failures.

No Pandoc binary, JSON filters, Cabal/Haskell runners, citeproc, BibTeX, Biber,
browser renderers, office suites, TeX/PDF engines, Typst engines, Node tooling,
zip/unzip tools, online services, or external validators were invoked.
