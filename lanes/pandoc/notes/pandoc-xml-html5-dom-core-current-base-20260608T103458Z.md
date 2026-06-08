# Pandoc XML/HTML5 DOM Core Current Base - Base Target Review Metadata

Slice: `pandoc-xml-html5-dom-core-current-base-20260608T103458Z`

Base accepted HEAD: `1931c96c286e44f278624dd3e62f6ff3b6cb363b`

## Implementation

- Added bounded HTML `<base target>` handoff in `Html5DomFragment`.
- Preserves the first active base target as inert reviewer metadata:
  `data-pandoc-meta-name="base-target"` / `data-pandoc-meta-source="base"`.
- Ignores base elements inside inactive fallback containers such as `template`
  before selecting the active target.
- Normalizes malformed target values containing controls or `<` to `_blank`
  as review metadata, without emitting live `target=` attributes.
- Keeps existing active navigation target stripping for anchors and image-map
  areas unchanged.
- Extended the WordPress HTML5 DOM fragment handoff example self-test to cover
  the new reviewer metadata.

## Source Truth

This slice ports the bounded support-library contract Pandoc HTML readers need
for safe raw HTML handoff: document-level base navigation defaults should stay
visible to reviewers, but not as active browsing-context attributes in
WordPress output. It does not run Pandoc, Cabal/Haskell test binaries, browser
renderers, external XML/HTML tools, online services, live provider tests, or
live-service provider tests.

## Evidence

- No lane rework note existed under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  before editing.
- Baseline focused test before editing:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 1266 assertions, 0 failures`
- Red-first focused test after adding the new base-target case:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 1267 assertions, 1 failures`
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 1288 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  - Result: `html5 dom fragment handoff self-test ok`

## Mapping Delta

- `lane-status.json` `phpPass`: `1616 -> 1617`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2035 -> 2036`
- `xmlHtmlDomCoreCases`: `8 -> 9`
- `mappedXmlHtmlDomCoreCases`: `8 -> 9`
- `xmlHtmlDomCoreAssertions`: `124 -> 146`
- Added `mappedXmlHtmlDomBaseTargetCases: 1`
- Focused fragment test assertion delta: `1266 -> 1288` (`+22`)

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
- `git diff --check -- lanes/pandoc`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses `Html5DomFragment`,
DOM/libxml `NONET` parsing, the existing active base-element detection,
URL/base helpers, `AstNode` raw HTML handoff, and `WordPressBlockWriter`.

Full upstream Pandoc runner parity remains outside this micro-slice because it
would require a hydrated pinned Pandoc checkout and non-mutating Cabal/Haskell
runner plan.

## Non-Overlap

This slice does not repeat DTD/entity handling, processing instructions, XML
declarations, comments, raw text/RCDATA/plaintext handling, foreign-content
CDATA, base URL resolution, passive link relations, meta refresh, iframe
policy, image maps, form/select labels, style metadata, hidden/inert/popover,
language/direction metadata, source-line diagnostics, or named character
reference mapping.

It owns only first-active HTML `<base target>` metadata preservation as inert
reviewer output.

## Follow-Up

- Broader browser tree-builder parity remains open for cases not covered by
  the bounded DOM fragment normalizer.
- Keep future XML/HTML5 DOM slices separate from base URL/target, passive link,
  meta, iframe, image-map, language/direction, and foreign-content coverage.
