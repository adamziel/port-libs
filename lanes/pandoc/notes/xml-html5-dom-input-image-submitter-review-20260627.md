# XML/HTML5 DOM Input Image Submitter Review

Implemented one bounded native PHP XML/HTML5 DOM support slice for
`<input type="image">` controls.

## Scope

- `XmlHtmlDom` now summarizes image submitter source URL provenance without
  fetching image resources.
- Image submitter summaries include `src` URL kind/scheme/unsafe state,
  `alt` presence and empty-state diagnostics, non-negative integer
  `width`/`height` metadata, coordinate parameter names, raw `value`, and
  issue codes.
- Existing form submitter action provenance remains in place, so image buttons
  still report owner form, effective action/method/enctype/target, and
  validation state beside their image metadata.
- Raw HTML serialization and WordPress raw HTML handoff preserve the source
  fragment as reviewer-visible markup.

## Validation

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomInputImageSubmitterReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomInputImageSubmitterReviewTest.php`
  - `1 test files, 59 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFormSubmitterActionProvenanceTest.php lanes/pandoc/tests/XmlHtmlDomFileInputReviewTest.php lanes/pandoc/tests/XmlHtmlDomInputImageSubmitterReviewTest.php`
  - `3 test files, 172 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomInputImageSubmitterReviewTest.php`
  - `2 test files, 6283 assertions, 0 failures`

## Parity Delta

- Focused PHP pass cases: `462 -> 463`.
- Static mapped behavior checks: `2305 -> 2306`.
- XML/HTML DOM input image submitter review cases: `+1`.
- XML/HTML DOM input image submitter review assertions: `+59`.

## Non-Overlap

This does not repeat existing `<img>` loading/resource-policy metadata, file
input accept/capture metadata, generic form-control constraints, form submitter
action review, image-map handling, canvas fallback review, iframe policy
metadata, or browser loading behavior. The slice owns only the HTML
`input type=image` submitter surface needed before raw HTML and WordPress
handoff.
