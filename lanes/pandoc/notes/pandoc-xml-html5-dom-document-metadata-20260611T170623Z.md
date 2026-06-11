# Pandoc XML/HTML5 DOM Document Metadata

Slice: `pandoc-xml-html5-dom-document-metadata-20260611T170623Z`

## Implementation

- `XmlHtmlDom::summarizeHtmlFragment()` now exposes `documentMetadata`
  summaries for HTML `base`, `meta`, and `link` elements.
- `base` summaries preserve `href` and `target`.
- `meta` summaries classify `charset`, `name`, `property`, `http-equiv`,
  `itemprop`, and generic metadata while preserving `content`.
- `link` summaries preserve `href`, raw and tokenized `rel`, preload/resource
  hints, language/MIME/media values, image candidate attributes, integrity,
  referrer policy, fetch priority, blocking tokens, and disabled state.
- Deterministic HTML5 serialization remains unchanged and covered by the new
  fixture.

## Evidence

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result after rebase on `499fb850d`: `1 test files, 575 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase on `499fb850d`: `44 test files, 64235 assertions, 0 failures`

## Mapping Delta

- `phpPass`: `3077` -> `3078`
- `phpFail`: stays `0`
- Added one focused XML/HTML5 DOM PASS case.

## Non-Overlap

This slice does not repeat accepted DOM work for hyperlinks, form controls,
progress/meter, disclosure state, revision tags, media resources, embedded
resource candidates, foreign-content casing, RCDATA/raw-text handling, table
foster parenting, or unsafe XML/HTML declaration rejection. It owns only
document-level URL and metadata element summaries in the native PHP DOM path.

No Pandoc, browser renderer, online sanitizer, external validator, Node tool,
office suite, or live service was invoked.
