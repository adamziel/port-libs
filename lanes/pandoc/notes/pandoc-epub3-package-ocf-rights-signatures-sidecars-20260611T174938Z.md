# EPUB OCF Rights And Signatures Sidecars

Slice: `plib-e1eyb`, 2026-06-11.

Implemented compact `EpubPackage` package-ingestion coverage for fixed OCF
sidecars at `/META-INF/rights.xml` and `/META-INF/signatures.xml`.

The package summary now exposes metadata-only `ocfSidecars` records with:

- sidecar kind and package part name;
- expected OCF root element and namespace;
- ZIP byte length, compressed byte length, CRC32, compression method, and
  compression support;
- metadata-only byte exposure policy and WordPress import handoff fields;
- unsupported-compression diagnostics without reading or exposing sidecar
  payload bytes.

This intentionally does not validate DRM rights semantics, XML signatures,
canonicalization, digests, certificates, or trust policy. It also does not
repeat OPF metadata links, OCF `metadata.xml` link provenance, encryption.xml
resource policy, nav/NCX validation, or the richer `EpubReader` sidecar
reference parser.

Verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 file, 1306 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 64762 assertions, 0 failures.

Accounting:

- `phpPass`: 3087 -> 3088.
- `mapped`: 3201 -> 3202.
- `mappedEpubOcfRightsSignaturesSidecarCases`: 1.
- `epubOcfRightsSignaturesSidecarAssertions`: 45.
