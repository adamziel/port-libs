# pandoc-doctemplates-core-current-base-20260607T131157Z

## Slice

Implemented bounded native doctemplate eager validation on accepted base
`424ab745ada40d29ec0ac2fa6607911652c2bb35`.

`DocTemplate` now tokenizes and validates the full template before render-time
data selection. Unsupported pipes, missing partials, and broken partial syntax
inside inactive `$if$` branches or empty `$for$` loops now fail with source
locations instead of being hidden by the selected render path.

## Source Truth

- doctemplates documents a compile-before-render flow through
  `compileTemplate` followed by `renderTemplate`:
  https://www.stackage.org/package/doctemplates
- doctemplates partials are resolved through the template source path and may
  include other partials, with a bounded depth guard:
  https://www.stackage.org/package/doctemplates
- doctemplates defines the supported pipe names and parameterized block pipes:
  https://www.stackage.org/package/doctemplates

No Pandoc, Cabal solver/build/test command, Haskell runner, external template
engine, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Evidence

Baseline focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php
1 test files, 399 assertions, 0 failures
```

Red-first probe before implementation:

```text
inactive branch rendered: 'ok'
```

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php
1 test files, 403 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test
OK wordpress doctemplate review packet
```

PHP lint:

```text
php -l lanes/pandoc/src/DocTemplate.php
No syntax errors detected in lanes/pandoc/src/DocTemplate.php
php -l lanes/pandoc/tests/DocTemplateTest.php
No syntax errors detected in lanes/pandoc/tests/DocTemplateTest.php
php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php
No syntax errors detected in lanes/pandoc/examples/wordpress-doctemplate-review-packet.php
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiter parsing,
truthiness, loop rendering, nested control collection, partial rendering,
applied-partial rebinding, final-newline stripping, resource/default-template
fallbacks, braced separators, or pipe transformation output. The change is
limited to compile-style parser validation before runtime branch selection.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `DocTemplate`
tokenization/rendering, focused `DocTemplateTest.php`, and the existing
WordPress doctemplate review-packet example.

Next doctemplate work should stay bounded to non-overlapping parser/resource
behavior such as remaining compile-time diagnostics, writer default resources,
or doclayout wrapping parity.
