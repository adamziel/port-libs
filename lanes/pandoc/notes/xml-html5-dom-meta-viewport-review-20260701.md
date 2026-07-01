# XML/HTML5 DOM Meta Viewport Review

Bead: `plib-cum6o`
Date: 2026-07-01 UTC
Area: Pandoc XML/HTML5 DOM primitives

## Behavior

`XmlHtmlDom` now exposes bounded reviewer metadata for HTML
`<meta name="viewport">` declarations:

- comma/semicolon-separated directive parsing for `key=value` entries;
- normalized directive names and last effective values;
- duplicate, unknown, invalid-name, missing-value, and invalid-value issue
  codes;
- typed width/height, scale, `user-scalable`, `viewport-fit`,
  `interactive-widget`, and legacy `shrink-to-fit` summaries;
- zoom restriction review hints for disabled user scaling and maximum scale
  below the accessibility minimum used by the lane metadata review.

This remains a metadata-only DOM review packet. It does not render a page,
measure a viewport, invoke a browser, or claim direct-reader parity for browser
layout behavior.

## Source Notes

The bounded directive names and value ranges follow the current CSS viewport
and MDN HTML reference descriptions checked during the slice:

- https://www.w3.org/TR/css-viewport-1/
- https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/meta/name/viewport

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomMetaViewportReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomMetaViewportReviewTest.php lanes/pandoc/tests/XmlHtmlDomMetaReferrerPolicyReviewTest.php lanes/pandoc/tests/XmlHtmlDomContentSecurityPolicyReviewTest.php lanes/pandoc/tests/XmlHtmlDomMetaPermissionsPolicyReviewTest.php lanes/pandoc/tests/XmlHtmlDomBaseMetadataReviewTest.php lanes/pandoc/tests/XmlHtmlDomLinkFetchPolicyReviewTest.php`
  passed with `6 test files, 330 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php`
  passed with `60 test files, 8865 assertions, 0 failures`.
- `git diff --check -- lanes/pandoc`

## Non-Overlap

This does not repeat existing meta CSP, Permissions-Policy, referrer, base,
link fetch/render-blocking, script/style loading, iframe policy, image/media
loading, or generic resource URL review metadata. It owns only the
`meta name="viewport"` directive review surface.
