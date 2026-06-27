# Pandoc XML/HTML5 DOM Meta Referrer Policy Slice - 2026-06-27

Slice: `plib-087w4`, XML/HTML5 DOM core blocker.

Added bounded native PHP review metadata for `<meta name="referrer">` document
metadata. `XmlHtmlDom::summarizeHtmlFragment()` now records:

- current referrer-policy token normalization;
- legacy HTML Standard aliases: `never`, `default`, `always`, and
  `origin-when-crossorigin`;
- invalid, empty, and missing `content` diagnostics;
- raw HTML and WordPress handoff preservation without fetching or enforcing
  navigation policy.

This is metadata-only XML/HTML DOM provenance. It does not implement browser
policy containers, fetch resources, invoke Pandoc, launch a browser, or expand
broader sanitizer/tree-builder parity.

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomMetaReferrerPolicyReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomMetaReferrerPolicyReviewTest.php`
  - Result: 1 test file, 50 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtml5DomTest.php`
  - Result: 36 test files, 10,464 assertions, 0 failures.

Status delta:

- `lane-status.json` `phpPass`: `453 -> 454` on the rebased main base.
- `phpFail`: stays `0`.
