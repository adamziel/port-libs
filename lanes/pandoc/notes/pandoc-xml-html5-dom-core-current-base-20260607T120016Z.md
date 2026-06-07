# Pandoc XML/HTML5 DOM Core Current Base - Document Title Metadata

Slice: `pandoc-xml-html5-dom-core-current-base-20260607T120016Z`

Base accepted HEAD: `37b6b8cba9853ec530d73a609e75241368314341`

## Implementation

- `Html5DomFragment` now converts HTML document `title` elements into inert
  reviewer-visible metadata spans before raw HTML and WordPress handoff.
- The original `title` element is stripped and reported as a blocked tag, while
  non-empty title text is preserved as `data-pandoc-meta-name="title"` and
  `data-pandoc-meta-content`.
- Tag-looking RCDATA title content stays escaped in serialized HTML and
  WordPress blocks, closing the previous gap where title source survived as an
  active document metadata element.
- The WordPress HTML5 DOM fragment smoke now includes a legacy title and
  verifies that reviewer metadata is visible while `<title>` does not survive.

## Source Truth

Source truth is the lane-local XML/HTML5 DOM support contract plus the existing
bounded upstream HTML-reader mapping for full-document title/generator metadata.
Recovered HTML fragments should not hand active or invisible document metadata
to WordPress as raw markup, but bounded text metadata is useful reviewer
provenance during import.

This is native PHP support-library behavior for Pandoc-reader review handoff.
It is not full HTML5 tree-builder parity, browser sanitizer parity, CSS/media
loading, XHTML-to-AST conversion, or upstream Pandoc runner parity.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present for this
  lane before editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 911 assertions, 0 failures`.
- Pre-edit exploratory check:
  `php -r 'require "tools/bootstrap.php"; $f=PortLibs\Pandoc\Html5DomFragment::fromHtml("<title>Legacy &amp; review <b>title</b></title><p>after</p>"); echo $f->serialize(), "\n"; echo json_encode($f->diagnosticCodes(), JSON_UNESCAPED_SLASHES), "\n";'`
  emitted `<title>Legacy &amp; review &lt;b&gt;title&lt;/b&gt;</title><p>after</p>`
  and `[]`.
- Focused green after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed
  with `1 test files, 925 assertions, 0 failures`.
- Coupled DOM family:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `3 test files, 1198 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed with `html5 dom fragment handoff self-test ok`.

## Mapping Delta

- `benchmarkDenominator.mapped`: `1915` -> `1916`.
- `phpPass`: `1495` -> `1496`.
- `xmlHtmlDomCoreCases`: `6` -> `7`.
- `mappedXmlHtmlDomCoreCases`: `6` -> `7`.
- `xmlHtmlDomCoreAssertions`: `89` -> `103`.
- Focused `Html5DomFragmentTest.php`: `911` -> `925` assertions.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`: no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`: no
  syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`: `1
  test files, 925 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`:
  `3 test files, 1198 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`:
  `html5 dom fragment handoff self-test ok`.
- `git diff --check -- lanes/pandoc`: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET`
parser paths, existing metadata cleaning, and the focused PHP lane test
harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML parser, external sanitizer, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat DTD/entity rejection, processing-instruction or XML
declaration filtering, comment-boundary serialization, raw text/RCDATA/
plaintext handling, SVG/MathML foreign-content casing, foreign-content CDATA,
URL/srcset filtering, raster SVG data images, base URL resolution, inactive
fallback base isolation, SVG resource filtering, form/embed/noscript/template
unwrap, table foster-parenting, XML namespace serialization, obsolete media
URL attributes, picture-source pruning, explicit input/select labels, meta
refresh filtering, passive named meta fields, passive OpenGraph/Twitter
properties, social image metadata, passive link relations, navigation
side-effect stripping, image-map area handoff, hidden/details review metadata,
iframe policy provenance, or SVG CSS resource escape handling.

It owns only bounded HTML `title` text metadata handoff for sanitized reviewer
fragments.

## Follow-Up

Keep full HTML5 tree-builder parity, richer sanitizer policy, CSS cascade and
media resource loading, source-position diagnostics, additional inert document
metadata, XHTML-to-AST conversion, and full upstream-runner parity as separate
bounded slices.
