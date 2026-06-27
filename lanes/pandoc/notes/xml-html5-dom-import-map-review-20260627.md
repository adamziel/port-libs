# XML/HTML5 DOM import-map review

Area: Pandoc XML/HTML5 DOM core blocker slice.

`XmlHtmlDom::summarizeHtmlFragment()` now carries bounded metadata for
`<script type="importmap">` sources through the existing inert script JSON
review path:

- import records preserve specifier, address type, URL kind/scheme, unsafe URL
  flags, prefix specifier/address state, issue records, and validity flags;
- scoped import records preserve scope prefix URL review metadata, nested import
  records, invalid scope mapping diagnostics, and validity flags;
- aggregate import-map issue codes surface unsafe addresses, empty/non-string
  addresses, prefix-address mismatches, unsafe scope prefixes, and invalid scope
  import maps before WordPress raw HTML handoff.

No module target is fetched, resolved against the network, or browser-loaded.
The raw HTML/WordPress handoff still preserves the original import-map source.

Accounting:

- `lane-status.json` `phpPass`: `461 -> 462`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2304 -> 2305`
- Added `xmlHtmlDomImportMapReviewCases: 1`
- Added `mappedXmlHtmlDomImportMapReviewCases: 1`
- Added `xmlHtmlDomImportMapReviewAssertions: 48`

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomImportMapReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomImportMapReviewTest.php`
  - Result: 1 file, 48 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php`
  - Result: 37 files, 7,571 assertions, 0 failures

No Pandoc binary, Haskell/Cabal runner, browser renderer, Node tooling,
external validator, module loader, or network fetch was executed.
