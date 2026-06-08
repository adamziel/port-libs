# pandoc-doctemplates-core-current-base-20260608T131602Z

## Scope

Lane: pandoc
Micro-slice: pandoc-doctemplates-core-current-base-20260608T131602Z
Accepted base: d6ec1fb5ef671b6ea22e454e765ca0d7b78582a5

This slice maps the upstream doctemplates boolean fixture behavior into the
native PHP `DocTemplate`: interpolated `false` booleans now render as the
literal text `false`, while `$if(...)$` still treats `false` as false for
branch selection.

## Source Truth

- No current `port-pandoc-*.needs-lane-rework.md` note existed for this slice.
- The local pinned Pandoc cache was absent from
  `/home/claude/port-libs/.upstream-cache/pandoc`.
- Primary upstream references used:
  - `jgm/doctemplates` `src/Text/DocTemplates/Internal.hs`, where `BoolVal
    False` resolves with false truthiness and rendered text `false`.
  - `jgm/doctemplates` `test/boolean.test`, whose expected output renders
    `$bar$` as `false` while `$if(bar)$` takes the else branch.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, external
template engine, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Red-First Evidence

Baseline focused test:

`php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`

Result: `1 test files, 748 assertions, 0 failures`.

After adding the upstream boolean expectation and before the renderer fix:

`php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`

Result: `1 test files, 748 assertions, 1 failures`, showing false booleans
were still rendered as empty text in direct interpolation, list interpolation,
and loop interpolation.

## Final Evidence

`php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`

Result: `1 test files, 749 assertions, 0 failures`.

`php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`

Result: `OK wordpress doctemplate review packet`.

Focused delta: +1 named PHP PASS case and +1 focused assertion in
`DocTemplateTest.php` (`748` to `749`). The mapped denominator moves from
`2072` to `2073`; lane `phpPass` moves from `1652` to `1653`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native `DocTemplate`
parser/render path, the existing WordPress doctemplate review-packet example,
and the lane `TestRunner`.

Full upstream doctemplates/Pandoc runner parity remains out of scope for this
isolated worker because no hydrated pinned Pandoc checkout or approved
non-mutating Cabal plan was available.

## Non-Overlap

This slice does not change comments, delimiter whitespace, conditionals,
loops, partial resolution, nesting, breakable spaces, pipe parsing,
default-template fallback resources, filesystem resource loading, source
locations, or Unicode/colon/digit metadata lookup.

It owns only the bounded upstream false-boolean interpolation behavior and the
direct WordPress review-packet audit metadata smoke update.
