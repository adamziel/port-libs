# Pandoc XML/HTML5 DOM Core Current Base - ARIA Review Metadata

Slice: `pandoc-xml-html5-dom-core-current-base-20260608T194003Z`

Base: `ad5b11a3802902acf51a50b9d763682c8110442c`

## Behavior

- `Html5DomFragment` now converts source HTML `role` and bounded `aria-*` attributes into inert `data-pandoc-aria-*` review metadata before WordPress raw HTML handoff.
- Safe role tokens, IDREF lists, token states, integer attributes, and numeric values are normalized and deduplicated.
- Malformed role/idref/token/numeric values, unsupported `aria-*` attributes, and source-owned `data-pandoc-aria-*` spoofing are stripped with sanitizer diagnostics instead of serialized as live accessibility attributes.
- The WordPress HTML5 DOM fragment example now covers ARIA handoff and confirms live source ARIA attributes are not emitted.

## Source Truth And Scope

This is bounded support-library work for the lane-local XML/HTML5 DOM sanitizer and serializer contract used by Pandoc-like HTML reader and WordPress raw HTML handoff paths. A quick local upstream cache search did not find a direct pinned Pandoc ARIA fixture for this exact support behavior, so the slice maps native review-metadata support rather than upstream runner parity.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer, JavaScript runtime, external XML/HTML tool, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` -> `1 test files, 1386 assertions, 0 failures`.
- Red-first probe before implementation showed source `role`, `aria-label`, `aria-expanded`, and `aria-describedby` serialized as live attributes, with only source-owned `data-pandoc-aria-label` stripped.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` -> `1 test files, 1409 assertions, 0 failures`.
- Focused XML/HTML5 DOM family: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` -> `3 test files, 1724 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` -> `html5 dom fragment handoff self-test ok`.

## Metrics

- `phpPass`: `1750 -> 1751`.
- `benchmarkDenominator.mapped`: `2166 -> 2167`.
- `xmlHtmlDomCoreCases`: `8 -> 9`.
- `mappedXmlHtmlDomCoreCases`: `8 -> 9`.
- `xmlHtmlDomCoreAssertions`: `124 -> 147`.
- Added `mappedXmlHtmlDomAriaMetadataCases = 1`.
- Added `xmlHtmlDomAriaMetadataAssertions = 23`.

## Dependency Closure

No new native PHP support component is needed. The slice reuses `Html5DomFragment`, the existing libxml NONET parsing boundary, `AstNode`, `WordPressBlockWriter`, and the lane focused PHP `TestRunner`.

Full upstream Pandoc runner execution, browser tree-builder parity, ARIA accessibility API computation, external sanitizer validation, online services, live provider tests, and live-service provider tests remain intentionally out of scope.

## Non-overlap

This avoids accepted XML/HTML5 DOM slices for XML declarations/DTD rejection, RCDATA/raw text/plaintext, SVG/MathML/CDATA, URL/srcset/data-image normalization, base/link/meta/iframe/form/table/image map/details/dialog/popover/editing/translate/revision/time/language/figure/shadow/slot handoff, and passive link relation metadata. This slice owns only bounded ARIA role/state/idref review metadata.
