# XML/HTML5 DOM form-owner current-base slice

Issue: `plib-8p080`
Base: `407d7449945672e0605a25fb4a4b5888a14c2249`

This slice extends the native XML/HTML5 DOM reviewer summary for form-associated
controls. `select`, `input`, `textarea`, `button`, and `output` summaries now
include a `formOwner` packet when a control belongs to an ancestor `<form>` or
declares an explicit `form` attribute.

The packet preserves the source of ownership (`ancestor` or `attribute`),
resolution state, requested form id for explicit owners, and bounded form
metadata (`id`, `name`, `action`, `method`, `enctype`, `target`). Unresolved
explicit form ids remain visible as reviewer handoff diagnostics instead of
being silently dropped. Disabled-fieldset state remains independent from the
owner relationship.

Regression coverage added one `XmlHtmlDomTest` case for:

- an input owned by its ancestor form;
- a button inside a disabled fieldset owned by an external form;
- an external input sharing the same explicit owner packet;
- a select pointing at a missing form id;
- an output with explicit owner and preserved `for` tokens;
- unchanged deterministic HTML serialization.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test files, 426 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 62530 assertions, 0 failures`

No Pandoc binary, Cabal/Haskell runner, browser renderer, external validator,
online service, or live-provider test was invoked.
