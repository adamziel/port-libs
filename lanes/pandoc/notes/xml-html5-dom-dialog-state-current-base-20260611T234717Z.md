# Pandoc XML/HTML5 DOM Dialog State

Slice: `xml-html5-dom-dialog-state-current-base-20260611T234717Z`

## Summary

`XmlHtmlDom::summarizeHtmlFragment()` now exposes bounded reviewer metadata for
HTML `dialog` elements:

- open/closed state;
- first scoped heading text/tag/level;
- descendant `form method="dialog"` provenance;
- submitter labels, names, values, effective `formmethod` overrides, disabled
  state, and close-return values.

The serialized raw HTML remains deterministic and the WordPress raw-block handoff
keeps the original fragment. This is native PHP DOM/libxml behavior only; no
Pandoc, browser renderer, online sanitizer, external validator, online service,
or live provider test was invoked.

## Accounting

- `phpPass`: `3147 -> 3148`
- mapped denominator: `3222 -> 3223`
- `mappedXmlHtmlDomDialogStateCases`: `1`
- `xmlHtmlDomDialogStateAssertions`: `34`
- focused `XmlHtmlDomTest.php`: `944` assertions, `0` failures
- full `lanes/pandoc/tests`: `44` files, `67477` assertions, `0` failures

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`
