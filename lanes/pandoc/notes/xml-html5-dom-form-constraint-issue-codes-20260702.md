# XML/HTML5 DOM Form Constraint Issue Codes - 2026-07-02

Bead: `plib-58jb2`
Area: Pandoc XML/HTML5 DOM primitives

## Behavior

`XmlHtmlDom::summarizeHtmlFragment()` now emits metadata-only
`html-form-control-constraint-attribute-review` rollups for summarized
`input`, `textarea`, and `select` controls when constraint-adjacent attributes
are present. The new fields preserve existing raw per-attribute metadata and
add:

- `formControlConstraintAttributes` for the relevant attribute names present on
  the control;
- deterministic `formControlConstraintIssues` and
  `formControlConstraintIssueCodes`;
- `formControlConstraintIssueCount`, `formControlConstraintValid`, and
  `formControlConstraintConforming`;
- a no-browser-validation policy flag for reviewer handoff.

The rollup covers invalid length tokens, reversed length ranges, invalid
numeric min/max tokens, reversed min/max ranges, invalid step/dirname/size
tokens, and existing autocomplete diagnostics. Raw HTML and WordPress raw HTML
handoff remain unchanged.

No Pandoc, browser renderer, online sanitizer, external validator, online
service, live provider test, office suite, TeX engine, Node tool, zip/unzip, or
citeproc/BibTeX/Biber process was invoked.

## Accounting

- focused test file: `XmlHtmlDomFormConstraintIssueCodesTest.php`
- focused assertions: `+46`
- mapped XML/HTML5 DOM form constraint issue-code case: `+1`

## Verification

- Red-first `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFormConstraintIssueCodesTest.php`
  failed on missing `formControlConstraintReviewPolicy` with `1 test files, 1
  assertions, 1 failures`.
- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomFormConstraintIssueCodesTest.php`
- Focused `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFormConstraintIssueCodesTest.php`
  passed with `1 test files, 46 assertions, 0 failures`.
- Related form-control command:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomAutocompleteReviewTest.php lanes/pandoc/tests/XmlHtmlDomInputTypeProvenanceTest.php lanes/pandoc/tests/XmlHtmlDomFormSuccessfulControlReviewTest.php lanes/pandoc/tests/XmlHtmlDomFormSubmitterActionProvenanceTest.php lanes/pandoc/tests/XmlHtmlDomFormConstraintIssueCodesTest.php`
  passed with `5 test files, 240 assertions, 0 failures`.
- Related monolith `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  passed with `1 test files, 6356 assertions, 0 failures`.

## Non-Overlap

This does not repeat accepted form successful-control, submitter override,
autocomplete token, typed input value, textarea layout, select option-state, or
datalist association work. It owns only the aggregate issue-code review for
already exposed form control constraint-adjacent attributes.
