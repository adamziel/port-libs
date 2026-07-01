# XML Namespace Collision Bounds - 2026-07-01

## Scope

This slice tightens `XmlHtmlDom::summarizeXmlNamespaceUsage()` for bounded XML
direct-reader review packets. Element local-name collisions, attribute
local-name collisions, and default namespace transitions now keep total count
fields separate from the bounded 25-item summary arrays.

The packet exposes explicit limit, summary-count, and truncation fields for
those three review surfaces while preserving `directReaderParity=false` and the
existing XML family partial registry status. No XML conversion parity is
claimed.

## Evidence

- Added `XmlHtmlDomNamespaceCollisionBoundsTest.php`.
- Focused bounded packet coverage: 1 file, 40 assertions, 0 failures.
- Combined XML/registry coverage remains green: 3 files, 6,756 assertions, 0
  failures.
- Broader XML/HTML DOM family coverage remains green: 83 files, 12,705
  assertions, 0 failures.
- Full `lanes/pandoc/tests/*.php` was attempted and remains current-base red
  outside this XML namespace slice: 535 files, 142,334 assertions, 8,912
  failures.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomNamespaceCollisionBoundsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomNamespaceCollisionBoundsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/*.php` (attempted; baseline-red
  outside this slice)
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check origin/main...HEAD -- lanes/pandoc`
- `git diff --check -- lanes/pandoc`
- Conflict-marker scan of touched lane files

No Pandoc binary, XML validator, browser renderer, Node tooling, online
service, live provider, or external validator was invoked.
