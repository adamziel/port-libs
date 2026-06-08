# Pandoc Doctemplates Core Current Base

Slice: `pandoc-doctemplates-core-current-base-20260608T165536Z`
Base: `ea4c73a25d285da2506e428c01a4b4360207672e`

## Source Truth

- Pinned Pandoc source has a separate `data/templates/default.chunkedhtml` resource at `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- Pandoc default-template lookup resolves `templates/default` through the writer format unless it is one of the explicitly unsupported native/json/xml/pptx-style formats.
- Upstream doctemplates partial parsing still requires exact `name()` syntax; whitespace inside the `()` token was checked and intentionally left out.

Source references:

- https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.chunkedhtml
- https://hackage-content.haskell.org/package/pandoc-3.8.3/docs/src/Text.Pandoc.Templates.html
- https://www.stackage.org/haddock/lts-24.45/doctemplates-0.11.0.1/src/Text.DocTemplates.Parser.html

## Implementation

- Added bounded native `default.chunkedhtml` resource rendering to `DocTemplate`.
- Registered `default.chunkedhtml` in default resource lookup and partial fallback enumeration.
- Covered format-selected `templates/default` lookup, direct `templates/default.chunkedhtml`, `chunkedhtml+...` extension-qualified lookup, top-page title-block suppression, nested `${ default.chunkedhtml() }` partial fallback, and custom override precedence.
- Added WordPress review-packet smoke coverage for chunked HTML navigation metadata and style/default partial handoff.

## Evidence

Red-first:

```text
php -r 'require "tools/bootstrap.php"; $r = new \PortLibs\Pandoc\DocTemplate(); try { echo $r->renderResource("templates/default", [], ["body" => "Chunked body"], null, "chunkedhtml"); } catch (\Throwable $e) { fwrite(STDERR, get_class($e) . ": " . $e->getMessage() . "\n"); exit(7); }'
UnexpectedValueException: Missing doctemplate resource templates/default
```

Baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php
1 test files, 774 assertions, 0 failures
```

Final focused tests:

```text
php -l lanes/pandoc/src/DocTemplate.php
No syntax errors detected in lanes/pandoc/src/DocTemplate.php

php -l lanes/pandoc/tests/DocTemplateTest.php
No syntax errors detected in lanes/pandoc/tests/DocTemplateTest.php

php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php
No syntax errors detected in lanes/pandoc/examples/wordpress-doctemplate-review-packet.php

php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php
1 test files, 810 assertions, 0 failures

php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test
OK wordpress doctemplate review packet
```

## Dependency Closure

No new support component is needed. This reuses the existing native PHP `DocTemplate` resource resolver, doctemplate renderer, default partial fallback machinery, and built-in `styles.html` partial. No Pandoc, Cabal solver/build/test command, Haskell runner, external template engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted default Markdown/CommonMark, html4->html5 alias, man/ms, legacy HTML slide, extension-qualified custom fallback, or applied-partial rebinding slices. It maps one separate pinned Pandoc default-template resource: `default.chunkedhtml`.
