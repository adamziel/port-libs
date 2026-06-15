# XML/HTML5 DOM Shadow Slot Review

Slice: `xml-html5-dom-shadow-slot-review`
Date: 2026-06-15

## Scope

`XmlHtmlDom::summarizeHtmlFragment()` now emits bounded declarative shadow-root
and slot fallback review metadata without changing raw HTML serialization.

The summary covers:

- `template shadowrootmode` mode validation for `open` and `closed`.
- Declarative shadow flags: `shadowrootdelegatesfocus`,
  `shadowrootclonable`, and `shadowrootserializable`.
- Shadow-root accessibility text and IDREF metadata.
- Named/default slot counts, normalized safe slot names, fallback text,
  fallback element names, fallback links, fallback images, and invalid slot-name
  diagnostics.

This parses inert template text only for review fields. The raw handoff keeps
template source escaped and does not invoke Pandoc, browser renderers, online
sanitizers, external validators, online services, live provider tests, or
live-service provider tests.

## Accounting

- Rebased main: `fade9a7ef6`
- `phpPass`: `3723 -> 3724`
- `phpFail`: `0`
- `upstream.mapped`: `3742 -> 3743`
- `mappedXmlHtmlDomShadowSlotReviewCases`: `0 -> 1`
- `xmlHtmlDomShadowSlotReviewAssertions`: `+39`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  passed with `1 test files, 4276 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed with `46 test files, 88312 assertions, 0 failures` after rebase.
- PHP JSON validation passed for `lane-status.json` and
  `UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check` and exact conflict-marker scan passed.
