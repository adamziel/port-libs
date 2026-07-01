# XML/HTML5 DOM search region control rollup

Slice: `plib-f14wi` XML/HTML5 DOM core blocker.
Base: current main `c010065fc`.

`XmlHtmlDom::summarizeHtmlFragment()` now emits additive metadata-only rollups
for HTML `search` regions. Existing `searchForms` and `searchControls` entries
remain intact, while search summaries now also expose:

- form ids, actions, normalized methods, and per-form control counts;
- region-level control count, unique control tags, names, and control types;
- submitter counts and submitter names at both the region and form level.

The slice does not submit forms, fetch action URLs, run browser search/form
algorithms, infer accessible names beyond existing label text, or change
serialized HTML. It only preserves bounded reviewer metadata for direct
XML/HTML DOM handoff while direct-format parity remains blocked/accounted for in
the lane notes.

Focused validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomSearchRegionControlRollupTest.php`
- `git diff --check -- lanes/pandoc`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomSearchRegionControlRollupTest.php`
  - Result: 1 file, 24 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomSearchRegionControlRollupTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomFormSubmitterActionProvenanceTest.php lanes/pandoc/tests/XmlHtmlDomFormAcceptCharsetReviewTest.php lanes/pandoc/tests/XmlHtmlDomLabelControlAssociationReviewTest.php`
  - Result: 5 files, 6496 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 78 files, 12467 assertions, 0 failures.

No external Pandoc, office-suite, TeX/browser-engine, Typst, Jupyter, Node,
ZIP/unzip, validator, or live-service tooling was used.
