# XML/HTML5 DOM Fragment Source Provenance

Bead: `plib-gal8b`
Date: 2026-07-01 UTC
Area: Pandoc XML/HTML5 DOM primitives
Base: `f3562206f`

## Behavior

`Html5DomFragment` now records deterministic source provenance for native
HTML/XML fragment handoff:

- original source format, byte length, and SHA-256 digest;
- sanitized serialized byte length and SHA-256 digest;
- a `serializedChanged` flag for reviewer-visible sanitizer changes;
- diagnostic count, diagnostic code order, and resolved base URL;
- the same provenance packet on `raw_html` AST nodes as
  `fragmentProvenance`.

This is bounded native PHP DOM review metadata only. It does not invoke Pandoc,
browser engines, online sanitizers, external validators, online services, live
provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - `1 test files, 2779 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test files, 6224 assertions, 0 failures`
- `git diff --check -- lanes/pandoc/src/Html5DomFragment.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/notes/xml-html5-dom-fragment-source-provenance-20260701.md`

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM work for URL sanitization, image
fallbacks, media policy metadata, image maps, table foster parenting, document
metadata, source-line diagnostics, or active content filtering. It owns only the
fragment-level source/serialization provenance packet exposed by
`Html5DomFragment` and its raw HTML AST handoff.
