# XML/HTML5 DOM hidden subtree review

Slice: `plib-fk8po`, XML/HTML5 DOM primitives.

## Summary

`XmlHtmlDom::summarizeHtmlFragment()` now reports effective hidden-subtree
provenance for each summarized element.

The new metadata mirrors existing effective global-attribute review surfaces:

- direct `hidden` elements report `effectiveHidden*` fields with
  `hiddenSource: self-hidden`;
- descendants of hidden ancestors report the ancestor source element/id and
  `hiddenSource: ancestor-hidden`;
- `hidden="until-found"` is preserved distinctly from ordinary hidden content;
- invalid hidden values remain defaulted to the hidden state while surfacing
  `effectiveHiddenInvalidValueDefaulted`.

This is metadata-only review state. Serialization, WordPress raw HTML handoff,
and hidden subtree payload exposure remain unchanged.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomHiddenSubtreeReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomHiddenSubtreeReviewTest.php`
  passed: 1 test file, 42 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomHiddenSubtreeReviewTest.php`
  passed: 5 test files, 9,334 assertions, 0 failures.

No Pandoc, office suites, TeX/browser engines, Node tooling, external
validators, or live services were invoked.

## Non-Overlap

This does not repeat accepted hidden/inert raw HTML handoff diagnostics,
effective inert inheritance, translation inheritance, title inheritance,
autocapitalize inheritance, or active sanitizer behavior. It only adds bounded
effective hidden-subtree provenance to the native DOM summary surface.
