# XML/HTML5 DOM Comment Provenance

Bead: `plib-b67qr`
Date: 2026-07-01 UTC
Area: Pandoc XML/HTML5 DOM primitives
Base: `72a74c57b`

## Behavior

`XmlHtmlDom::summarizeHtmlFragmentComments()` now exposes a bounded native
review packet for HTML fragment comments:

- deterministic fragment-local comment paths such as `comment()[1]` and
  `section[1]/comment()[2]`;
- raw comment text byte length, line count, SHA-256 digest, and parent element
  context;
- declaration-looking inert text flags for doctype, DTD/entity declarations,
  and processing-instruction-looking payloads;
- serializer-boundary flags and safe serialization text for comments whose raw
  text contains `--` or ends with `-`;
- aggregate counts and issue-code rollups for reviewer handoff.

This is metadata-only DOM review support. It does not invoke Pandoc, browser
engines, online sanitizers, external validators, online services, live provider
tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `git diff --check -- lanes/pandoc/src/XmlHtmlDom.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/notes/xml-html5-dom-comment-provenance-20260701.md`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomClosedCommentDeclarationRecoveryTest.php`
  - `6 test files, 9500 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Current baseline remains red: `390 test files, 131475 assertions, 9252 failures`.

## Non-Overlap

This does not repeat accepted fragment source provenance, resource URL/base
provenance, active content filtering, declaration preflight, CDATA/raw text
recovery, table foster parenting, or whole-fragment serialization behavior. It
owns only the per-comment provenance packet exposed from `XmlHtmlDom`.
