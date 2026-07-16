# Pandoc EPUB3 OCF Sidecar Root Preflight

Slice: `plib-1bhb8`

This slice extends native EPUB package ingestion for OCF sidecars under
`META-INF/rights.xml` and `META-INF/signatures.xml`.

- Readable sidecars now carry XML root preflight metadata:
  `xmlRootChecked`, `xmlWellFormed`, `rootName`, `rootNamespace`,
  `rootValid`, `rootReport`, and `rootDiagnostics`.
- Unsupported ZIP compression methods remain metadata-only and are not inflated.
- Malformed sidecar XML and unexpected roots are preserved as review diagnostics
  instead of aborting package ingestion.

Focused coverage lives in `lanes/pandoc/tests/EpubPackageTest.php` and asserts
summary plus WordPress import propagation for the new diagnostics.
