# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T103207Z`
Base accepted HEAD: `edbd54e9448f3320ec7b627467caded1fab93ac8`

## Behavior Added

- Added tag-aware SVG resource/reference URL policy to `Html5DomFragment`.
- SVG non-anchor `href` and `xlink:href` values now use the existing fetch/reference URL policy, so `mailto:` and `tel:` resource values are stripped from nodes such as `image` and `feImage`.
- Local SVG references such as `use href="#icon"` and `textPath href="#label"` remain local even when an HTML `<base>` URL is present for surrounding document links.
- SVG anchor navigation remains on the existing navigational-link policy, so reviewer mail links inside SVG anchors can still be preserved.

## Source Truth

- Reused the accepted lane-local XML/HTML5 DOM support contract for safe raw HTML fragment handoff, SVG/MathML foreign-content casing, base URL metadata, URL filtering, and WordPress raw HTML block serialization.
- This is a bounded native PHP sanitizer/serializer support slice. It does not attempt full browser tree-builder parity, CSS resource loading, Pandoc HTML-reader AST conversion, or a generic URL parser ecosystem.
- No Pandoc, Haskell runner, browser renderer, online sanitizer, or online service was invoked.

## Red-First Evidence

Before the implementation, SVG resource URLs were treated like navigational links and local SVG references were base-expanded:

```text
php -r 'require "tools/bootstrap.php"; $h="<base href=\"https://source.example.test/import/post.html\"><figure><svg xmlns:xlink=\"http://www.w3.org/1999/xlink\"><symbol id=\"icon\"><path d=\"M0 0\"></path></symbol><use href=\"#icon\"></use><image href=\"mailto:cover@example.test\" xlink:href=\"https://cdn.example.test/cover.svg\"></image><feImage href=\"tel:+15550100\"></feImage><textPath href=\"#label\">Logo</textPath><a href=\"mailto:review@example.test\">mail</a></svg></figure>"; $f=PortLibs\Pandoc\Html5DomFragment::fromHtml($h); echo $f->serialize(), "\n";'
<figure><svg xmlns:xlink="http://www.w3.org/1999/xlink"><symbol id="icon"><path d="M0 0"></path></symbol><use href="https://source.example.test/import/post.html#icon"></use><image href="mailto:cover@example.test" xlink:href="https://cdn.example.test/cover.svg"></image><feImage href="tel:+15550100"></feImage><textPath href="https://source.example.test/import/post.html#label">Logo</textPath><a href="mailto:review@example.test">mail</a></svg></figure>
```

Baseline focused DOM-family check before this patch was the previous XML/HTML5 DOM note result:

```text
php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php
3 test files, 510 assertions, 0 failures
```

## Delta

- Added one focused PHP PASS case in `Html5DomFragmentTest`.
- `Html5DomFragmentTest` moved from 294 to 311 assertions.
- Focused DOM-family assertions moved from 510 to 527.
- Lane `phpPass` moved from 836 to 837.
- Native mapped inventory moved from 1,296 to 1,297.

## Verification

```text
php -l lanes/pandoc/src/Html5DomFragment.php
No syntax errors detected in lanes/pandoc/src/Html5DomFragment.php

php -l lanes/pandoc/tests/Html5DomFragmentTest.php
No syntax errors detected in lanes/pandoc/tests/Html5DomFragmentTest.php

php -l lanes/pandoc/examples/wordpress-html5-dom-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-html5-dom-handoff.php

php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php
1 test files, 311 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php
3 test files, 527 assertions, 0 failures

php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test
wordpress-html5-dom-handoff self-test passed
```

Final JSON validation and diff whitespace checks were run after this note was added:

```text
php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
pandoc json ok

git diff --check -- lanes/pandoc
passed with no output
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET` parser path, and lane-local manifest/status machinery.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM work for DTD/entity rejection, processing-instruction filtering, XML declaration preflight, comment-boundary serialization, `srcset` descriptor filtering, control-separated URL normalization, URL attribute allowlist expansion, base URL resolution for normal HTML links, form/embed/template/noscript unwrapping, raw-text/RCDATA/plaintext handling, SVG/MathML namespace casing, table foster-parenting, or malformed fragment recovery. The new behavior is limited to SVG non-anchor resource/reference URL handling and local SVG fragment-reference preservation.

## Follow-Up

- Full upstream HTML5 tree-builder parity remains out of scope for this bounded support-library slice.
- Future DOM work can extend this into richer XHTML-to-AST mapping, CSS/media resource policy metadata, and broader sanitizer policy fixtures without shelling out to Pandoc or browser engines.
