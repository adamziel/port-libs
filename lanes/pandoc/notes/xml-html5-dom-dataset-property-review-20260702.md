# XML/HTML5 DOM dataset property review

Slice: `plib-bjn74` XML/HTML5 DOM core blocker.

`XmlHtmlDom::summarizeHtmlFragment()` now emits metadata-only
`html-data-attribute-dataset-property-review` fields for HTML `data-*`
attributes. The review packet records raw data attribute names, dataset
property names, dot-notation-safe and bracket-only property buckets, empty data
attribute values, aggregate value byte counts, and per-attribute records.

This slice does not invoke browser dataset APIs, mutate DOM state, fetch
resources, or run external validators. Existing `dataset` maps, raw HTML
serialization, and WordPress raw block handoff remain unchanged.

Validation for `plib-bjn74` passed on 2026-07-02:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomDatasetPropertyReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomDatasetPropertyReviewTest.php`
  - Result: 1 file, 19 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: 1 file, 6,224 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 89 files, 12,795 assertions, 0 failures.

Manifest accounting:

- `mappedXmlHtmlDomDatasetPropertyReviewCases`: `1`
- `xmlHtmlDomDatasetPropertyReviewAssertions`: `19`
- Benchmark mapped denominator: `2325 -> 2326`

No external Pandoc, office-suite, TeX/browser-engine, Typst, Jupyter, Node,
ZIP/unzip, validator, browser dataset API, or live-service tooling was used.
