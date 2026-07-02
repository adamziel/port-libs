# XML/HTML5 DOM Form Control Constraint Issue Review

Bead: `plib-gh7b7`
Date: 2026-07-02 UTC
Area: Pandoc XML/HTML5 DOM primitives
Base: `89949a7748`

## Behavior

`XmlHtmlDom` form-control constraint summaries now carry additive reviewer issue
rollups for controls that already expose constraint metadata. The existing raw
and parsed fields remain intact, and the new handoff adds:

- `constraintReviewPolicy` and no-browser-state-mutation provenance;
- ordered `constraintAttributeNames` and attribute counts;
- `constraintIssues`, `constraintIssueCodes`, `constraintIssueCount`, and
  `constraintValid`;
- issue records for invalid `minlength`, `maxlength`, `step`, `dirname`, and
  `size` tokens, reversed length/range bounds, and nested autocomplete token
  review issues;
- numeric `min`/`max` issue reporting for number/range controls while leaving
  temporal input bounds to the existing typed-input review.

This remains metadata-only. It does not execute patterns, perform browser
constraint validation, submit forms, fetch resources, or invoke Pandoc,
browser engines, Node, validators, or external tools.

## Accounting

- `mappedXmlHtmlDomFormControlConstraintIssueReviewCases`: `1`
- `xmlHtmlDomFormControlConstraintIssueReviewAssertions`: `52`
- Direct-format parity accounting remains active in `UPSTREAM_TEST_MANIFEST`.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomFormControlConstraintIssueReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFormControlConstraintIssueReviewTest.php`: 1 file, 52 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFormControlConstraintIssueReviewTest.php lanes/pandoc/tests/XmlHtmlDomAutocompleteReviewTest.php lanes/pandoc/tests/XmlHtmlDomTypedInputValueReviewTest.php lanes/pandoc/tests/XmlHtmlDomTextareaLayoutReviewTest.php`: 4 files, 257 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php`: 82 files, 9925 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomFormControlConstraintIssueReviewTest.php`: 5 files, 3212 assertions, 0 failures
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`

## Non-Overlap

This extends the existing form-control constraint provenance slice with issue
rollups only. It does not repeat landed work for autocomplete token parsing,
typed input value validation, textarea layout review, successful-control
submission candidates, fieldset disabled inheritance, output references,
dialog/popover/inert handling, measurement elements, or XML namespace review.
