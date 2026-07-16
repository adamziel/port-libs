# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T095947Z`
Base accepted HEAD: `c313fcf5e2b5d64af7de1df996c22543831c360f`

## Behavior Added

- Added bounded URL-attribute normalization for accepted XML/HTML5 DOM fragment attributes before serialization and WordPress raw HTML block handoff.
- Safe URL attributes now remove C0 controls and edge whitespace before base-URL resolution and output serialization.
- Control/space-obfuscated safe absolute schemes such as `h&#9;ttps://example.test/review` are canonicalized before handoff.
- Relative `href`, `src`, `cite`, and `srcset` entries are normalized before trusted base URL resolution.
- Unsafe control-separated URLs such as `java&#10;script:alert(1)` remain filtered with `unsafe-url`.
- The fragment diagnostic stream records `normalized-url` entries when a serialized URL differs from the parsed source.

## Source Truth

- Reused the accepted lane-local XML/HTML5 DOM support contract for safe raw HTML handoff and WordPress block serialization.
- No external Pandoc, browser, Haskell runner, or online service was invoked.
- This slice intentionally ports bounded fragment sanitizer behavior, not full HTML5 tree-builder parity or a generic URL parser ecosystem.

## Red-First Evidence

Before the implementation, the current fragment serializer preserved control/edge whitespace in URL attributes and emitted no diagnostics:

```text
php -r 'require "tools/bootstrap.php"; $f=PortLibs\Pandoc\Html5DomFragment::fromHtml("<p><a href=\" h&#9;ttps://example.test/review \">review</a><img src=\" /media/cover.png&#10;\" alt=\"Cover\"></p>"); echo $f->serialize(), "\n"; var_export($f->diagnosticCodes()); echo "\n";'

<p><a href=" h	ttps://example.test/review ">review</a><img src=" /media/cover.png
" alt="Cover"></p>
array (
)
```

Baseline focused DOM-family check before the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php
3 test files, 495 assertions, 0 failures
```

## Delta

- Added one focused PHP PASS case for control-separated URL attributes in `Html5DomFragmentTest`.
- `Html5DomFragmentTest` now has 294 assertions.
- Focused DOM-family assertions moved from 495 to 510.
- Lane `phpPass` moved from 820 to 821.
- Native mapped inventory moved from 1,280 to 1,281.

## Verification

```text
php -l lanes/pandoc/src/Html5DomFragment.php
No syntax errors detected in lanes/pandoc/src/Html5DomFragment.php

php -l lanes/pandoc/tests/Html5DomFragmentTest.php
No syntax errors detected in lanes/pandoc/tests/Html5DomFragmentTest.php

php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php

php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php
1 test files, 294 assertions, 0 failures

php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test
html5 dom fragment handoff self-test ok

php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php
3 test files, 510 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests
20 test files, 10050 assertions, 0 failures
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

This does not repeat accepted XML/HTML5 DOM work for DTD/entity rejection, processing-instruction filtering, XML declaration preflight, comment-boundary serialization, `srcset` descriptor filtering, URL attribute allowlist expansion, base URL resolution, form/embed/template/noscript unwrapping, raw-text/RCDATA/plaintext handling, SVG/MathML namespace handoff, table foster-parenting, or malformed fragment recovery. The new behavior is limited to canonicalizing accepted URL attributes before serialization and trusted base resolution.

## Follow-Up

- Full upstream HTML5 tree-builder parity remains out of scope for this bounded support-library slice.
- Future DOM work can extend this into richer XHTML-to-AST mapping, media/resource policy metadata, and broader sanitizer policy fixtures without shelling out to Pandoc or browser engines.
