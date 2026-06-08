# XML/HTML5 DOM Form Metadata Slice

Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260608T222234Z`
Base accepted HEAD: `91b78f3dc934ff8feae3a865af2ae3a69c9e09f5`

## Behavior

- `Html5DomFragment` now converts explicit legacy HTML form submission
  provenance into inert reviewer metadata before unwrapping blocked form
  controls.
- Safe `method`, `action`, `target`, `autocomplete`, and `name` values are
  emitted on a reviewer `span` as `data-pandoc-form-*` attributes.
- Relative safe form actions resolve through trusted HTML base metadata.
- Unsafe form actions, invalid methods/targets/autocomplete/name values, and
  source-owned `data-pandoc-form-*` spoofing remain diagnostics-only and do
  not serialize as live attributes.
- The WordPress HTML5 DOM fragment smoke now exercises the same raw HTML
  handoff path.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note existed
  for this lane before work began.
- Baseline focused check:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1584 assertions, 0 failures`.
- Final focused check:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1627 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed with `html5 dom fragment handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Mapping Delta

- `phpPass`: `1918 -> 1919`.
- `benchmarkDenominator.mapped`: `2341 -> 2342`.
- `xmlHtmlDomCoreCases`: `8 -> 9`.
- `mappedXmlHtmlDomCoreCases`: `8 -> 9`.
- `xmlHtmlDomCoreAssertions`: `124 -> 167`.
- Added `mappedXmlHtmlDomFormMetadataCases: 1`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`Html5DomFragment`, existing URL/base-resolution helpers, `AstNode` raw HTML
handoff, `WordPressBlockWriter`, the focused DOM fragment test, and the
existing WordPress HTML5 DOM fragment example.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML tool, online sanitizer, online service, live provider test,
or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM work for DTD/entity rejection,
processing instruction filtering, raw text/RCDATA/plaintext handling,
SVG/MathML foreign-content casing, foreign-content CDATA, URL/srcset/data-image
filtering, base URL/target metadata, iframe srcdoc/source/policy metadata,
form select/option labels, fieldset grouping metadata, output metadata, passive
meta/link handoff, figure metadata, quote cite metadata, image maps, details/
dialog/popover metadata, ARIA/language/revision/ruby/custom-element metadata,
shadow-root and slot metadata, source-line diagnostics, or reserved
`data-pandoc-*` filtering.

It owns only bounded form submission provenance conversion for sanitized
reviewer fragments.

## Follow-Up

Keep browser sanitizer parity, full HTML5 tree-builder parity, XHTML-to-AST
conversion, CSS/media execution, richer datalist/form-associated widget
provenance, and upstream Haskell runner dependency closure as separate bounded
slices.
