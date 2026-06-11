# XML/HTML5 DOM Parse Adapter Provenance

Current base: `592488d646306dddcb4f4ddb49e196583fdbab7a`

Slice:

- Added `XmlHtmlDom::parseXmlDocument()` and `XmlHtmlDom::parseHtmlFragment()` adapter methods that return the parsed DOM alongside bounded source provenance.
- Kept `loadXmlDocument()` and `loadHtmlFragment()` as compatible DOM-only wrappers.
- Added `XmlHtmlDom::elementProvenance()` lookup for package/XML readers that need deterministic element paths, source labels, source names, attributes, namespace metadata, and line/column hints where practical.
- Kept parsing native PHP/libxml only: no Pandoc, Cabal/Haskell runners, browser renderers, office suites, zip/unzip, external validators, online services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test files, 459 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 62466 assertions, 0 failures`

Metric update:

- `phpPass`: 3047 -> 3049
- mapped denominator: 3184 -> 3186
- `mappedXmlHtmlDomDomAdapterProvenanceCases = 2`
- `xmlHtmlDomDomAdapterProvenanceAssertions = 45`
- `mappedXmlHtmlDomCoreCases = 14`
- `xmlHtmlDomCoreAssertions = 244`
