# pandoc-pdf-engine-handoff-core-current-base-20260608T173550Z

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T173550Z`
- Accepted base: `1a3a20a4d97a813d29b077097195ea208a489f6a`
- Implemented one bounded native PDF fake-runner handoff cluster: catalog `/DSS` document security store metadata, certificate/OCSP/CRL stream summaries, VRI reference groupings, and DSS diagnostics.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 883 assertions, 0 failures`
- Final: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 893 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  - `pdf engine handoff self-test ok`

## Non-Overlap

This slice does not repeat existing produced-PDF handoff clusters for signatures, catalog permissions, XMP/PDF-A/PDF-UA, output intents, tagged structure, legal attestation, page display/timings/viewports, optional content, AcroForm, active actions, RichMedia, embedded files, annotations, URI base, name trees, WebCapture/SpiderInfo, or collection metadata. It only adds the validation-info handoff surrounding signed PDFs: DSS root references and VRI group summaries.

## Dependency Closure

No new support component is needed. The implementation reuses `PdfEngineHandoff` bounded PDF catalog/object/dictionary parsing, reference resolution, stream extraction, and structural stream hashing. Cryptographic signature validation, certificate path validation, OCSP/CRL verification, Pandoc execution, TeX/PDF engines, external PDF validators, online services, live provider tests, and live-service provider tests remain out of scope.

## Next

If the PDF-engine lane continues, choose a non-overlapping produced-PDF gap such as incremental signature revision provenance, DSS validation-policy edge metadata, or another bounded catalog/page output feature that can be inspected without external engines or validators.
