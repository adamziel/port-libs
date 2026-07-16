# XML/HTML5 DOM Core Current Base 2026-06-09T12:06:17Z

Slice: `pandoc-xml-html5-dom-core-current-base-20260609T120617Z`
Session: `port-dev-pandoc-xml-html5-dom-20260609T120617Z`
Base: `67d434edf3a4d801f81c24c8c2a09230a63f024a`

## Behavior

`Html5DomFragment` now converts safe HTML anchor browsing metadata into inert
review attributes:

- `<a target="...">` becomes `data-pandoc-link-target` when the target name is
  bounded and contains no control or markup characters.
- `<a download>` and `<a download="file.html">` become
  `data-pandoc-link-download="true"` or a bounded filename value when the source
  value has no path separators, markup, quotes, or control bytes.
- Invalid anchor target/download values are reported as unsafe attributes and
  omitted from the serialized review HTML.
- `ping` remains an unsafe active side-effect attribute and is still dropped.

The WordPress raw HTML handoff preserves the inert `data-pandoc-link-*`
metadata while continuing to reject live `target`, `download`, `ping`, unsafe
`javascript:` links, and `rel=opener` side effects.

## Red-First Evidence

Before the implementation, the new focused test failed because safe anchor
`target` and `download` metadata were dropped with the live attributes:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 2386 assertions, 1 failures`

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed
  with `1 test files, 2413 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php`
  passed with `5 test files, 2908 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed with `html5 dom fragment handoff self-test ok`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM work for unsafe XML/DTD rejection,
HTML5 named references, RCDATA/template/plaintext protection, SVG/MathML
foreign-content casing, table insertion-mode repair, base URL/target metadata,
meta refresh target links, passive link relations, referrer/resource policy
metadata, image-map area conversion, form/button/select/datalist metadata, or
iframe/object/embed/source handoff behavior. The new surface is only bounded
anchor `target`/`download` metadata conversion while preserving existing active
navigation-side-effect filtering.

## Dependency Closure

No new support component is needed. This reuses `Html5DomFragment`, `AstNode`,
`WordPressBlockWriter`, DOM/libxml `NONET` parsing, and the existing lane
`TestRunner`. No Pandoc binary, Haskell runner, browser engine, online
sanitizer, external template engine, Word/LibreOffice, zip/unzip, TeX/PDF
engine, online service, or live provider test was executed.

Next bounded XML/HTML5 DOM work should target a non-overlapping HTML reader gap
only when a Pandoc conversion path needs it, such as additional passive link
provenance, richer form owner handoff, or custom-element metadata.
