# XML Namespace Scope Diagnostics - 2026-06-13

## Scope

This slice extends the XML/HTML5 DOM direct-reader diagnostic surface without
claiming XML, JATS, or BITS reader parity. `XmlHtmlDom` now emits a bounded
namespace-scope review packet for XML DOMs, including namespace declarations,
default namespace scopes, active prefix bindings, prefix redefinitions,
duplicate prefix/URI summaries, and reserved `xml`/`xmlns` misuse diagnostics.

`summarizeJatsFrontMatter()` now carries stable namespace review fields beside
the existing `directReaderParity=false` and direct-reader diagnostic fields.
`PandocFormatRegistry` advertises those packet fields for `xml`, `jats`, and
`bits` while preserving unsupported direct-reader parity.

## Evidence

- New mapped slice: `mappedXmlHtmlDomNamespaceScopeDiagnosticCases = 1`
- Focused assertions added: `xmlHtmlDomNamespaceScopeDiagnosticAssertions = 49`
- Focused XML/registry run passed: `2 files, 4825 assertions, 0 failures`
- Full Pandoc PHP run passed: `46 files, 78400 assertions, 0 failures`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`

No Pandoc binary, XML validator, browser renderer, Node tooling, online
service, live provider, or external validator was invoked.

## Remaining Work

XML, JATS, and BITS remain unsupported as direct readers. Follow-up work should
implement real XML/JATS/BITS body, back matter, table, figure, reference,
citation, and shared AST conversion before changing `directReaderParity` or
registering a direct reader implementation.
