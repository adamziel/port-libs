# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T120801Z`

Base accepted HEAD: `ac57b742ddcfa5621469edef183d1ac0986a433b`

## Behavior Added

- `Html5DomFragment` now applies blocked HTML tag/attribute and URL sanitizer
  policy only when normalizing HTML fragments.
- XML fragments preserve safe XML package/review elements whose names overlap
  active HTML tags, including `link`, `meta`, `script`, and `style`.
- XML attributes whose names overlap HTML sanitizer policy, including `onload`,
  `style`, and `data-pandoc-fragment-root`, now round-trip as XML metadata.
- Existing HTML-mode sanitizer tests still prove active HTML elements,
  attributes, and unsafe URLs are blocked before WordPress raw HTML handoff.
- The WordPress XML/HTML DOM handoff smoke now proves policy-overlap XML
  package markup serializes without HTML sanitizer diagnostics.

## Source Truth

Source truth is the lane-local Pandoc XML/HTML5 DOM support contract for
document-reader handoff: XML snippets from OOXML, OPF, ODT, XHTML, or review
packets must remain XML-preserving after safe parsing. DTDs, entities,
processing instructions, and NUL bytes remain rejected, but HTML active-content
policy must not erase ordinary XML element or attribute names.

Pre-edit red probe:

```text
Html5DomFragment::fromXml("<packet><link href=\"rId1\">media</link><meta name=\"review\" content=\"ok\"/><script>source text</script><style>source style</style></packet>")->serialize()
<packet/>

diagnosticCodes()
['blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag']
```

This is bounded XML-mode preservation, not full HTML5 tree-builder parity,
browser sanitization parity, CSS/media handling, exact original namespace
declaration placement, XHTML-to-AST conversion, or upstream Pandoc runner
parity.

## Evidence

Baseline focused DOM-family verification before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php
3 test files, 552 assertions, 0 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php
1 test files, 341 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php
3 test files, 567 assertions, 0 failures

php lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php --self-test
xml/html5 dom handoff self-test ok
```

Delta:

- Adds 1 focused PHP PASS case.
- Adds 15 XML/HTML DOM assertions.
- Updates `phpPass` from `888` to `889`.
- Updates mapped static inventory coverage from `1346` to `1347`.

## Verification

```text
php -l lanes/pandoc/src/Html5DomFragment.php
No syntax errors detected in lanes/pandoc/src/Html5DomFragment.php

php -l lanes/pandoc/tests/Html5DomFragmentTest.php
No syntax errors detected in lanes/pandoc/tests/Html5DomFragmentTest.php

php -l lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php

php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
pandoc json ok

git diff --check -- lanes/pandoc
passed with no output
```

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP DOM/libxml parsing
with network access disabled plus the existing `Html5DomFragment`,
`XmlHtmlDom`, `AstNode`, `WordPressBlockWriter`, and lane-local manifest/status
machinery. No Pandoc, Cabal build, Haskell runner, browser renderer, online
sanitizer, external XML tool, Word, LibreOffice, zip/unzip, TeX/PDF engine, or
online service was executed.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, processing
instruction rejection, complete HTML document unsafe-declaration preflight, HTML
fragment declaration preflight, raw text `script`/`style` HTML serialization,
RCDATA handling for `title`/`textarea`, obsolete raw-text fallback handling,
plaintext-state source protection, HTML5 void/boolean attribute serialization,
SVG/MathML foreign-content casing, integration-point casing, URL/srcset
filtering, base URL resolution, SVG local-resource URL policy,
comment-boundary-safe serialization, visible form/embed/noscript/template
unwrapping, table foster-parenting, charset/Unicode width handling,
Markdown/HTML reader AST coverage, ZIP/OPC package behavior, DOCX/ODT/EPUB
readers, archive compression, math/TeX, PDF handoff, BibTeX/CSL, YAML, table
geometry, syntax highlighting, or legacy DOC/CFB work. It owns only XML-mode
preservation for element and attribute names that overlap HTML sanitizer
policy.

## Follow-Up

Keep full HTML5 tree-builder parity, richer sanitizer policy, CSS/media
resource handling, exact original XML namespace declaration placement,
native XHTML-to-AST conversion, and upstream Pandoc runner dependency closure
as separate bounded slices.
