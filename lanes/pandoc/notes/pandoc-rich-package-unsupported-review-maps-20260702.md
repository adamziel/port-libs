# Pandoc Rich Package Unsupported Review Maps

2026-07-02 UTC

- `RichPackageUnsupportedFormatRegistry::unsupportedFormatSummary()` now
  includes reverse maps from unsupported diagnostic codes and gate names to the
  affected rich package formats.
- The maps are derived from the existing bounded registry rows and sorted for
  deterministic review handoff. They do not add converter support or claim
  direct support for unsupported writers.
- Focused coverage pins diagnostic-to-format and gate-to-format buckets for
  DOCX, ODT/OpenDocument, EPUB2, IPYNB, PPTX, chunked HTML, ICML, and PDF.

Validation:

- `php -l lanes/pandoc/src/RichPackageUnsupportedFormatRegistry.php`
- `php -l lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php`
  - 1 test file, 162 assertions, 0 failures

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node,
zip/unzip, validators, fetchers, or live services were invoked.
