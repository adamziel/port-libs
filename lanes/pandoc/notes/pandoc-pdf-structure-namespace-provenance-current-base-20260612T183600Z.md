# Pandoc PDF Structure Namespace Provenance Current Base

Slice: `plib-d000x`, PDF/Typst boundary provenance.

This slice extends native `PdfEngineHandoff` produced-PDF inspection with
bounded PDF 2.0 structure namespace provenance. Fake runs now expose
`pdfStructureNamespaces` and `fakeRunSequence()` carries
`finalPdfStructureNamespaces`.

The namespace summary records referenced and inline `StructTreeRoot`
`/Namespaces` dictionaries, including namespace object references, namespace
URIs, namespace-local role maps, schema references, schema types, and dictionary
keys. Diagnostics summarize namespace counts, namespace URIs, role-map entries,
and schema references.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 2165 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 72783 assertions, 0 failures`

Metric/accounting:

- `phpPass`: `3256 -> 3257`
- `phpFail`: `0`
- `mappedPdfStructureNamespaceCases`: `1`
- `pdfStructureNamespaceAssertions`: `9`

This does not run Pandoc, Cabal/Haskell runners, Typst, TeX/PDF engines,
browser renderers, external PDF validators, office suites, zip/unzip, online
services, live provider tests, or live-service provider tests. It is limited to
bounded native PHP fake-runner provenance at the PDF/Typst handoff boundary.
