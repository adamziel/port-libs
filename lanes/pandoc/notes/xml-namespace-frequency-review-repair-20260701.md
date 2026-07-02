# XML Namespace Frequency Review Repair - 2026-07-01

## Slice

`plib-4uy94` was previously rejected because the merge request targeted the wrong branch/source issue. Current `main` already contains the namespace frequency implementation and later XML-family reader registration, so this repair preserves that newer mainline state and pins the bounded review-packet behavior in a focused fixture.

## Evidence

- `XmlHtmlDom::summarizeXmlNamespaceUsage()` exposes bounded namespace prefix and URI frequency summaries, default namespace usage, same-URI/multiple-prefix and same-prefix/multiple-URI diagnostics, unused declaration diagnostics, and source-level review fields.
- The namespace usage packet remains metadata-only and reports `directReaderParity=false`.
- `PandocFormatRegistry` keeps the current bounded XML-family reader registration for XML/JATS/BITS instead of reverting later direct-reader progress from `main`.

## Validation

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/XmlNamespaceFrequencyReviewRepairTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlNamespaceFrequencyReviewRepairTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlNamespaceFrequencyReviewRepairTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php`

The full `php tools/run-tests.php lanes/pandoc/tests` gate remains baseline-red on current `main`; the 1842 repair run covered 535 files with 142,328 assertions and 8,912 failures concentrated outside this XML namespace repair, including Markdown raw-extension surge failures. The focused XML namespace repair and XML/registry gates pass.
