# XML/HTML5 DOM Form Control Constraint Provenance

Bead: `plib-738bc`
Date: 2026-06-12 UTC
Area: Pandoc XML/HTML5 DOM primitives
Base: `4fc495371b`

## Behavior

`XmlHtmlDom` form summaries now preserve bounded constraint-review provenance
for `select`, `input`, and `textarea` fragments:

- `constraintValidation`, `readonly`, and `multiple` control-state flags;
- raw and parsed `minlength`, `maxlength`, `min`, `max`, and `step` tokens with
  validity and range diagnostics;
- raw `pattern` source length and a `pattern-source-no-regex-execution` review
  policy;
- autocomplete token inventories, normalized tokens, invalid tokens, and final
  autocomplete state;
- `dirname` review fields and positive `size` token provenance;
- deterministic raw HTML and WordPress raw-block propagation.

This slice does not execute regular expressions, run browser constraint
validation, fetch resources, or invoke external validators. It only exposes
native PHP DOM provenance before WordPress raw HTML handoff.

## Accounting

- `phpPass`: `3252 -> 3253`
- `phpFail`: `0`
- `mappedXmlHtmlDomFormControlConstraintCases`: `+1`
- `xmlHtmlDomFormControlConstraintAssertions`: `62`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- Focused `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  passed with `1 test files, 1612 assertions, 0 failures`.
- Full `php tools/run-tests.php lanes/pandoc/tests` passed with `44 test files,
  72676 assertions, 0 failures`.
- Pandoc lane JSON validation passed for `lane-status.json` and
  `UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc`

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM work for form ownership,
label/datalist/effective-disabled provenance, basic input/textarea/button state,
submitter overrides, output controls, progress/meter state, active-resource
filtering, HTML5 fragment sanitation, passive link relations, microdata, ARIA,
focus, inert, custom-element export attributes, or media/source summaries. It
owns only summary-level form-control constraint metadata.
