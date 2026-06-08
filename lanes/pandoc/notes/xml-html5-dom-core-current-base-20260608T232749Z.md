# XML/HTML5 DOM MathML Annotation Metadata Slice

Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260608T232749Z`
Base accepted HEAD: `72ddd104de73563cbfd9ef3ec17976bf6afc1676`

## Behavior

- Added bounded native `Html5DomFragment` review metadata for MathML `<semantics>` annotations.
- Safe textual `<annotation encoding="...">` source text is summarized on the containing `<math>` element as inert `data-pandoc-math-source-format` and `data-pandoc-math-source`.
- Safe `<annotation-xml encoding="...">` provenance is summarized as inert `data-pandoc-math-annotation-xml-encoding`.
- Raw MathML annotation children remain serialized for review; this only adds reviewer metadata for WordPress raw HTML handoff.
- Source-owned `data-pandoc-math-*` spoofing remains stripped before trusted metadata is emitted.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note existed for this lane before work began.
- Upstream cache: no local Pandoc upstream cache was present under `/home/claude/port-libs/.upstream-cache`, so this worker did not cite or execute upstream fixtures.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1680 assertions, 0 failures`.
- Red-first: same focused command failed after adding the new case with `1 test files, 1681 assertions, 1 failures` because MathML annotation metadata was absent.
- Final: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1698 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed.
- Root harness: not run - isolated micro-slice.

## Mapping Delta

- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2389 -> 2390`.
- XML/HTML5 DOM core mapped cases: `8 -> 9`.
- XML/HTML5 DOM core focused assertion counter: `124 -> 142`.
- Added `mappedXmlHtmlDomMathAnnotationMetadataCases: 1`.
- `lane-status.json` `phpPass`: `1968 -> 1969`.

## Dependency Closure

No new support component is needed. The patch reuses native HTML fragment parsing, MathML foreign-content normalization, sanitizer diagnostics, raw HTML AST handoff, and `WordPressBlockWriter`. No Pandoc, Cabal/Haskell runner, browser renderer, external XML/HTML parser, online sanitizer, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM slices for datalist reviewer metadata, select/optgroup label fallback, iframe policy metadata, passive link relations, output/form/fieldset metadata, SVG data-image resources, foreign-content CDATA preservation, picture/source pruning, image maps, time/data/meter/progress value metadata, ruby annotations, shadow-root accessibility metadata, or MathML/SVG foreign-content casing.

## Follow-Up

Next XML/HTML5 DOM work should target a non-overlapping native fragment parser or serializer gap, such as remaining HTML5 tree-repair edge cases, tokenizer/entity handling, or other document-reader raw HTML handoff behavior.
