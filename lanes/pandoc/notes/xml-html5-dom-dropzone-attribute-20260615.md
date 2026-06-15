# XML/HTML5 DOM dropzone attribute review

Slice: `plib-lzbr8`, XML/HTML5 DOM core blocker.

## Behavior

- `XmlHtmlDom` now summarizes HTML `dropzone` global attributes as review
  metadata.
- The summary preserves raw tokens, drag effects (`copy`, `move`, `link`),
  string MIME types, file MIME types, invalid tokens, and multiple-effect
  detection.
- This is metadata-only; raw HTML serialization and WordPress raw block handoff
  remain unchanged.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: `1 test files, 4562 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `46 test files, 90358 assertions, 0 failures`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check` passed.

## Delta

- `phpPass`: `3801 -> 3802`
- mapped upstream cases: `3792 -> 3793`
- `mappedXmlHtmlDomDropzoneAttributeCases = 1`
- `xmlHtmlDomDropzoneAttributeAssertions = 25`
- `mappedXmlHtmlDomCoreCases`: `14 -> 15`
- `xmlHtmlDomCoreAssertions`: `266 -> 291`

## Non-Overlap

This does not repeat existing form, fieldset, label, datalist, select, output,
progress/meter, dialog/popover, button command, media, iframe, table, template,
slot, microdata, RDFa, ARIA, input-hint, or writing-assistance work. It only
closes the bounded HTML global `dropzone` token provenance gap.

No Pandoc, browser renderers, external validators, online services, live
provider tests, or live-service provider tests were run.
