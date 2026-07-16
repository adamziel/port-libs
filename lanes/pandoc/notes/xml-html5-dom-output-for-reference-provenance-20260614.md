# XML/HTML5 DOM Output For-Token Provenance

Bead: `plib-uotv0`
Date: 2026-06-14 UTC
Area: Pandoc XML/HTML5 DOM primitives
Base: `c0df71ce5c`

## Behavior

`XmlHtmlDom` output-control summaries now preserve bounded `output[for]`
IDREF provenance for reviewer handoff:

- raw token order plus unique valid reference IDs;
- resolved form-control targets with compact input/button/select/textarea/output
  summaries;
- duplicate `for` tokens with first-index provenance;
- missing target diagnostics;
- non-control target diagnostics;
- invalid IDREF token diagnostics;
- deterministic raw HTML serialization and WordPress raw-block propagation.

This is native PHP DOM review metadata only. It does not claim full HTML reader
parity and does not invoke Pandoc, browser renderers, online sanitizers,
external validators, online services, live provider tests, or live-service
provider tests.

## Accounting

- `phpPass`: `3495 -> 3496`
- `phpFail`: `0`
- `mappedXmlHtmlDomOutputForReferenceCases`: `1`
- `xmlHtmlDomOutputForReferenceAssertions`: `41`
- `mapped` upstream inventory: `3421 -> 3422`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test file, 3938 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 82204 assertions, 0 failures`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check -- lanes/pandoc/src/XmlHtmlDom.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/notes/xml-html5-dom-output-for-reference-provenance-20260614.md`

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM work for basic output value and raw
`for` token preservation, explicit/implicit labels, datalist options, disabled
fieldset inheritance, form ownership, submitter overrides, progress/meter
measurements, active-resource filtering, inert template/noscript content,
hyperlink/image-map diagnostics, table header references, object param review,
or PDF/Typst boundary provenance. It owns only output-control `for` target
resolution and diagnostics.
