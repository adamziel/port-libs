# Pandoc XML/HTML5 DOM Core Current Base - Element Language Direction Metadata

Slice: `pandoc-xml-html5-dom-core-current-base-20260608T095731Z`

Base accepted HEAD: `f70b19bb2fdd4ee45a2724fea0c7460448562a62`

## Implementation

- Added bounded HTML element-level language/direction handoff in `Html5DomFragment`.
- Converts valid HTML `lang`, `xml:lang`, and `dir` attributes into inert reviewer metadata: `data-pandoc-lang` and `data-pandoc-dir`.
- Normalizes BCP-47-style casing through the existing language-tag helper and accepts `dir` values `ltr`, `rtl`, and `auto`.
- Drops invalid or spoofed raw language/direction attributes through existing sanitizer diagnostics before WordPress raw HTML block handoff.
- Leaves XML fragment behavior unchanged, including `xml:lang` and namespace-sensitive attributes in XML mode.
- Extended the WordPress fragment example self-test to cover localized fragment metadata.

## Source Truth

This slice ports the bounded support-library contract Pandoc readers need for safe HTML fragment handoff: language and direction metadata must survive review while active/raw HTML attributes are not exposed directly to WordPress. It does not run Pandoc, Cabal/Haskell test binaries, browser renderers, external XML/HTML tools, online services, or live provider tests.

## Evidence

- No lane rework note existed under `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` before editing.
- Red-first focused test before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 1244 assertions, 1 failures`
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 1266 assertions, 0 failures`
- DOM family check:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 1567 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  - Result: `html5 dom fragment handoff self-test ok`

## Mapping Delta

- `lane-status.json` `phpPass`: `1604 -> 1605`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2023 -> 2024`
- `xmlHtmlDomCoreCases`: `8 -> 9`
- `mappedXmlHtmlDomCoreCases`: `8 -> 9`
- `xmlHtmlDomCoreAssertions`: `124 -> 147`
- Added `mappedXmlHtmlDomElementLanguageDirectionCases: 1`
- Focused fragment test assertion delta: `1243 -> 1266` (`+23`)

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

No new support component is needed. The slice reuses `Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET` parsing, and the existing bounded language-tag and URL/base helpers.

Full upstream Pandoc runner parity remains outside this micro-slice because it would require a hydrated pinned Pandoc checkout and non-mutating Cabal/Haskell runner plan.

## Non-Overlap

This slice does not repeat DTD/entity handling, processing instructions, XML declarations, comments, raw text/RCDATA/plaintext handling, foreign-content CDATA, URL/srcset/data-image/base URL handling, forms/embed/noscript/template/iframe handling, table foster parenting, namespace serialization, media/meta/link handoff, image maps, details/dialog/hidden/inert/popover handling, microdata/RDFa metadata, time/revision/editing metadata, source-line diagnostics, or bounded named character reference mapping.

It owns only HTML element-level `lang`, `xml:lang`, and `dir` metadata normalization into inert reviewer attributes.

## Follow-Up

- Broader browser tree-builder parity remains open for cases not covered by the bounded DOM fragment normalizer.
- XHTML-to-AST handoff, passive CSS/media metadata expansion, more named character references, and cite/provenance metadata for `q`/`blockquote` should remain separate slices.
