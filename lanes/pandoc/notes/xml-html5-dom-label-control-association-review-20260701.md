# XML/HTML5 DOM label control association review

Slice: `plib-yv7j3` XML/HTML5 DOM core blocker.

`XmlHtmlDom::summarizeHtmlFragment()` now emits additive metadata-only review
fields for HTML `label` control association. Existing label fields remain
unchanged, while each label now also exposes:

- explicit `for` reference validity, target counts, target element summaries,
  and duplicate/non-labelable target diagnostics;
- implicit nested labelable control counts and unassociated nested-control
  diagnostics when explicit `for` takes precedence;
- association mode, resolution state, associated control id, issue codes, and a
  validity flag for reviewer handoff.

The slice does not run browser form algorithms, submit forms, infer accessible
names beyond existing normalized label text, or change serialized HTML. It only
preserves bounded DOM metadata for direct XML/HTML handoff while direct-format
parity remains blocked.

Focused validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomLabelControlAssociationReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomLabelControlAssociationReviewTest.php`
  - Result: 1 file, 47 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomLabelControlAssociationReviewTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomFormSubmitterActionProvenanceTest.php lanes/pandoc/tests/XmlHtmlDomFormAcceptCharsetReviewTest.php lanes/pandoc/tests/XmlHtmlDomSelectedContentReviewTest.php`
  - Result: 5 files, 6510 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 64 files, 11802 assertions, 0 failures.

No external Pandoc, office-suite, TeX/browser-engine, Typst, Jupyter, Node,
validator, or live-service tooling was used.
