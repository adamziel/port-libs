# XML/HTML5 DOM Figure Metadata Slice

Slice: `pandoc-xml-html5-dom-core-current-base-20260608T175207Z`

Accepted base: `196aeee97e13991dc56717436cd4ee56caa47808`

## Behavior

- Added native `Html5DomFragment` figure review metadata for safe HTML figure handoff.
- Safe legacy `figure align="left|right|center"` values are stripped from live HTML and preserved as inert `data-pandoc-figure-align` metadata.
- The first direct `figcaption` child contributes bounded `data-pandoc-figure-caption` text and safe `data-pandoc-figure-caption-id` metadata for reviewer queues.
- Source-owned `data-pandoc-figure-*` spoofing is still stripped before the sanitizer emits trusted metadata.
- Invalid legacy alignment values remain diagnostics-only, and unsafe caption links remain stripped.

## Evidence

- Red-first probe before patch:
  - `php -r 'require "tools/bootstrap.php"; $f=PortLibs\Pandoc\Html5DomFragment::fromHtml("<figure align=\"right\" aria-describedby=\"cap-note\" data-pandoc-figure-caption=\"spoof\"><img src=\"./cover.png\" alt=\"Cover\"><figcaption id=\"cap-note\">  Cover <em>caption</em>  </figcaption></figure>", "https://source.example.test/import/posts/post.html"); echo $f->serialize(), "\n"; var_export($f->diagnosticCodes()); echo "\n";'`
  - Output kept live `align="right"` and emitted no trusted figure metadata: `array (0 => 'libxml-repair', 1 => 'libxml-repair', 2 => 'unsafe-attribute')`.
- Final focused check: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - `1 test files, 1386 assertions, 0 failures`
  - Delta over the prior focused file baseline: `+21` focused assertions and `+1` PHP PASS case.
- Focused DOM-family check: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - `3 test files, 1701 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  - `html5 dom fragment handoff self-test ok`

## Dependency Closure

No new native PHP support component is needed. This slice reuses `Html5DomFragment`, existing URL and attribute policy filtering, AST raw HTML handoff, and `WordPressBlockWriter`.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer, external XML/HTML tool, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat earlier XML/HTML5 DOM work for unsafe XML declarations, DTD/entity rejection, raw text/RCDATA/plaintext, SVG/MathML foreign casing, CDATA, URL/srcset/data-image filtering, base URL and target metadata, iframe srcdoc/source/policy metadata, form/select labels, noscript/template fallback unwrapping, table foster parenting, meta/link metadata, image maps, hidden/inert/details/dialog/popover, microdata/RDFa, time/revision/language metadata, media tracks, ruby annotations, source-line diagnostics, declarative shadow-root and slot fallback metadata, or reserved `data-pandoc-*` filtering.

Next useful XML/HTML5 DOM follow-up: ARIA role/state review metadata, custom-element import provenance, or bounded accessibility metadata.
