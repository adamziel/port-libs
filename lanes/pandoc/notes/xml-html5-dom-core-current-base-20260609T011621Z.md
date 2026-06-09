# XML/HTML5 DOM current-base orphan table row/cell repair

Session: `port-dev-pandoc-xml-html5-dom-20260609T011621Z`

Base accepted HEAD: `403bbfa850b87a30b18d0488738d4e785be58580`

## Scope

- Implemented one bounded XML/HTML5 DOM support behavior cluster: malformed HTML fragments with orphan `tr`, `td`, or `th` nodes are repaired into generated table/row wrappers before sanitized WordPress raw HTML handoff.
- Direct `td` and `th` children under `table`, `thead`, `tbody`, or `tfoot` are grouped into generated `tr` rows instead of reaching the serializer as invalid table-cell siblings.
- The orphan-wrapper pass is scoped to normal HTML integration contexts, so SVG/MathML foreign-content normalization keeps its existing casing/resource behavior.

## Non-overlap

- Avoids previously accepted XML/HTML5 DOM work for foreign-content CDATA, SVG raster data-image resources, select/option label fallback, passive link relations, iframe policy metadata, portal/source-set recovery, hidden/inert/popover/dialog/details metadata, raw-text/plaintext unwrap, and existing table foster-parenting of loose text/paragraphs.
- Owns only orphan table row/cell repair and direct table-cell row generation.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` rework notes existed before editing.
- Baseline before adding this focused assertion:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1725 assertions, 0 failures`.
- Red-first after adding the focused test before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  failed with `1 test files, 1726 assertions, 1 failures`; orphan `tr`, sibling `td`/`th`, and direct table cells were still serialized as invalid raw markup.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1748 assertions, 0 failures`.
- DOM family check:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `3 test files, 2076 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed with `html5 dom fragment handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/Html5DomFragment.php`,
  `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
  all reported no syntax errors.

## Status delta

- `lane-status.json` `phpPass`: `2029` -> `2030`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2444` -> `2445`.
- XML/HTML DOM manifest counters: `xmlHtmlDomCoreCases` `8` -> `9`, `mappedXmlHtmlDomCoreCases` `8` -> `9`, and `xmlHtmlDomCoreAssertions` `124` -> `147`.

## Dependency closure

No new support component is needed. This reuses the native PHP `Html5DomFragment` sanitizer, existing table foster-parenting path, diagnostics, and WordPress raw HTML handoff example. No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer, external XML/HTML tool, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Next task

A next non-overlapping XML/HTML5 DOM slice could target parser-level foreign-content edge cases not already covered by SVG/MathML CDATA/data-image resources, or another bounded table insertion-mode case outside orphan `tr`/`td`/`th` repair.
