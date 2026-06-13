# XML namespace usage diagnostics

`XmlHtmlDom::summarizeXmlNamespaceUsage()` adds a bounded, review-only namespace
packet over raw XML source. It records element and attribute namespace usage
counts, in-scope namespace declarations, unbound element/attribute prefixes,
unused declarations, and reserved `xml`/`xmlns` usage diagnostics before a DOM
parse would reject malformed prefix bindings.

The XML registry surface now names the namespace usage packet while keeping
`directReaderParity=false`, `inputStatus=unsupported`, and an empty direct reader
implementation for `xml`. This does not register an XML direct reader and does
not invoke Pandoc, XML validators, browser renderers, Node tooling, online
services, live providers, or external validators.

Counters:
- `phpPass`: `3350 -> 3351`
- `phpFail`: `0`
- `mapped`: `3310 -> 3311`
- `mappedXmlHtmlDomNamespaceUsageCases`: `1`
- `xmlHtmlDomNamespaceUsageAssertions`: `50`

Verification:
- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
