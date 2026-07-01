# XML/HTML5 DOM Media Preload Policy

Bead: `plib-zfnd8`
Date: 2026-06-30 UTC
Area: Pandoc XML/HTML5 DOM primitives

## Behavior

`XmlHtmlDom` media summaries now expose bounded reviewer provenance for the
HTML `preload` state on `audio` and `video` elements:

- raw `preload` attribute values;
- normalized keyword/state values for `none`, `metadata`, `auto`, and empty
  attribute defaults;
- missing-attribute and invalid-token default reasons;
- invalid-token issue codes while preserving the existing `preload` fallback to
  `auto`;
- autoplay override hints for reviewers.

This remains metadata-only DOM provenance. It does not fetch media resources,
decode media payloads, invoke a browser, or claim full Pandoc direct-reader
parity for HTML media loading behavior.

## Accounting

- Direct reader parity: unchanged; HTML media loading remains a bounded review
  packet rather than full direct-reader parity.
- Focused mapped coverage: `XmlHtmlDomMediaPreloadPolicyTest.php`.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomMediaPreloadPolicyTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomMediaPreloadPolicyTest.php`
  passed with `1 test files, 41 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomMediaControlsPolicyTest.php lanes/pandoc/tests/XmlHtmlDomMediaPreloadPolicyTest.php`
  passed with `3 test files, 6309 assertions, 0 failures`.

## Non-Overlap

This does not duplicate existing media source, text-track, controlslist,
image-loading, iframe, link, script, or style loading policy provenance. It owns
only `audio`/`video` `preload` defaulting and validity metadata.
