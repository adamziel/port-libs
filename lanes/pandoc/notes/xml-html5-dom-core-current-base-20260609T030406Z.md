# XML/HTML5 DOM current-base leading-comment document metadata

Session: `port-dev-pandoc-xml-html5-dom-20260609T030406Z`

Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260609T030406Z`

Base accepted HEAD: `a9fa6b1b1922089bdf86c381badcec9119efdc2b`

## Scope

- Implemented one bounded XML/HTML5 DOM support behavior cluster for full-document HTML fragments whose safe export comments precede the `<html>` element.
- `Html5DomFragment` now skips leading whitespace and closed HTML comments before checking for document-level `<html lang dir>` metadata.
- The normalized tree keeps the source comment and whitespace visible in the raw HTML handoff, while language/direction reviewer spans are emitted before it.
- Unterminated leading comments do not unlock document metadata.
- Existing DTD/entity/processing-instruction rejection remains unchanged; `<!DOCTYPE html>` is still rejected on this fragment path.

## Source Truth

The lane-local Pandoc HTML reader inventory already maps full-document title/generator/language metadata and safe raw HTML handoff behavior. This slice ports the bounded support-library contract needed for WordPress review packets exported with generator comments before the document element, without implementing full browser tree-builder parity.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Baseline focused command before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1797 assertions, 0 failures`.
- Red-first behavior probe before implementation:
  `php -r 'require "tools/bootstrap.php"; $f=PortLibs\Pandoc\Html5DomFragment::fromHtml("<!-- exported by legacy CMS -->\n<html lang=\"fr-ca\" dir=\"AUTO\"><title>Bonjour</title><p>Salut</p></html>"); echo $f->serialize(), "\n"; echo $f->textContent(), "\n"; echo json_encode($f->diagnosticCodes()), "\n";'`
  serialized only the leading comment, title span, and paragraph; it did not emit `Language: fr-CA` or `Direction: auto`.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1824 assertions, 0 failures`.
- DOM family command:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `3 test files, 2175 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed with `html5 dom fragment handoff self-test ok`.
- PHP lint passed for:
  `php -l lanes/pandoc/src/Html5DomFragment.php`,
  `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`.
- JSON validation:
  `jq empty lanes/pandoc/lane-status.json && jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  passed.
- Diff hygiene:
  `git diff --check -- lanes/pandoc`
  passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2200 -> 2201`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2612 -> 2613`.
- `xmlHtmlDomCoreCases`: `8 -> 9`.
- `mappedXmlHtmlDomCoreCases`: `8 -> 9`.
- `xmlHtmlDomCoreAssertions`: `124 -> 151`.
- Added `mappedXmlHtmlDomLeadingCommentDocumentMetadataCases: 1`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `Html5DomFragment` parsing/serialization, DOM/libxml `NONET` parser paths, `WordPressBlockWriter` raw HTML handoff, focused DOM tests, and the WordPress HTML5 DOM fragment example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, browser renderer, external XML/HTML parser, external sanitizer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM slices for DTD/entity rejection, processing-instruction filtering, XML declaration preflight, comment-boundary serialization, raw text/RCDATA/plaintext handling, SVG/MathML foreign-content casing, foreign-content CDATA normalization, URL/srcset filtering, data-image handling, base URL resolution, inactive fallback base isolation, SVG resource filtering, form/embed/object/applet/noscript/template fallback unwrapping, iframe srcdoc/source/policy handoff, table foster-parenting and orphan table repair, XML namespace serialization, obsolete media URL attributes, image maps, meta/link metadata, shadow-root/slot metadata, figure/ruby/math annotations, responsive image metadata, portal/source-set handoff, or editing/focus/ARIA/custom-element metadata.

## Next Task

Choose a non-overlapping XML/HTML5 DOM gap such as a remaining table insertion-mode repair outside accepted row/cell and section/column wrapping, another bounded inert metadata handoff, or a raw HTML document-reader edge that does not relax DTD/entity rejection.
