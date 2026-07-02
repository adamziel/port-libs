# XML/HTML DOM Numeric Constraint Review

Work item: `plib-6o4bc`

## Summary

`XmlHtmlDom` now emits bounded review metadata for `input type=number` and
`input type=range` numeric constraints. The packet records static `min`, `max`,
`step`, and `value` provenance; invalid numeric tokens; declared range
validity; default step handling; `step="any"` handling; static below-min,
above-max, and step-mismatch diagnostics; disabled and readonly suppression;
and an explicit no-form-submission handoff.

The focused fixture maps eight controls: a valid number, a below-min number, a
step-mismatch number, invalid numeric tokens, `step="any"`, readonly
suppression, disabled suppression, and a range control. Direct-format parity
remains metadata-only for XML/HTML5 DOM: the lane records source semantics for
review before raw HTML/WordPress handoff but does not claim browser constraint
validation or Pandoc direct-reader parity.

## Non-overlap

This slice does not change form submission, browser constraint validation,
date/time input parsing, pattern regular-expression execution, length
constraints, required-value handling, dirname handling, autocomplete parsing,
or raw HTML serialization. It only adds static native PHP review metadata for
numeric/range controls already summarized by the form-control constraint path.

## Validation

- `php -l lanes/pandoc/src/XmlHtmlDom.php && php -l lanes/pandoc/tests/XmlHtmlDomNumericConstraintReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomNumericConstraintReviewTest.php`:
  1 file, 70 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomNumericConstraintReviewTest.php lanes/pandoc/tests/XmlHtmlDomLengthConstraintReviewTest.php lanes/pandoc/tests/XmlHtmlDomRequiredValueReviewTest.php lanes/pandoc/tests/XmlHtmlDomFormDirnameReviewTest.php lanes/pandoc/tests/XmlHtmlDomInputHintReviewTest.php lanes/pandoc/tests/XmlHtmlDomTest.php`:
  6 files, 6,529 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php lanes/pandoc/tests/Html5Dom*Test.php lanes/pandoc/tests/XmlHtml5DomTest.php`:
  80 files, 12,618 assertions, 0 failures.
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check -- lanes/pandoc`

No Pandoc binary, cmark/commonmark runner, office suite, TeX/browser engine,
archive tool, Jupyter, Node tooling, external validator, network fetch, or
online service was invoked.
