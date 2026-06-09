# Doctemplate Current Base Partial Recursion Limit

Micro-slice: `pandoc-doctemplates-core-current-base-20260609T000859Z`
Base accepted HEAD: `35d557737dc1b88c45279aeb585788c53834812d`

## Source Truth

Pandoc doctemplate partial expansion uses a bounded recursion guard and renders
the literal `(loop)` once the partial stack reaches the limit. The native PHP
renderer already capped known recursive partials at 50 levels, but a chain that
hit the limit and then referenced a missing partial checked for missing partials
before checking the recursion limit.

Red-first probe before implementation:

```bash
php <<'PHP'
<?php
require 'tools/bootstrap.php';
$r = new PortLibs\Pandoc\DocTemplate();
$partials = [];
for ($i = 0; $i < 49; $i++) {
    $partials['p' . $i] = '${ p' . ($i + 1) . '() }';
}
$partials['p49'] = '${ missing() }';
try {
    echo $r->render('${ p0() }', [], $partials), "\n";
} catch (Throwable $e) {
    echo $e->getMessage(), "\n";
}
PHP
```

Observed before fix: `Missing doctemplate partial missing at p49:1:1`.

## Implementation

- `DocTemplate::renderPartial()` now checks `MAX_PARTIAL_DEPTH` before partial
  lookup and default fallback lookup.
- Missing partials below the bounded depth still raise the existing
  `UnexpectedValueException` diagnostics.
- The WordPress doctemplate review-packet self-test now covers the over-limit
  missing-partial path without changing regular example output.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` notes were present.
- Baseline focused command before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1093 assertions, 0 failures`.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1095 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP lint passed for:
  `lanes/pandoc/src/DocTemplate.php`,
  `lanes/pandoc/tests/DocTemplateTest.php`, and
  `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP doctemplate
renderer, partial-stack accounting, default partial fallback handling, and the
existing WordPress review-packet smoke. No Pandoc executable, Cabal/Haskell
runner, external template engine, online service, live provider test, or
live-service provider test was run.

## Non-overlap

This slice does not repeat the accepted doctemplate map-pairs, applied-partial
rebinding, breakable-space wrapping, braced separator, default Markdown,
Beamer, man, or ms fallback slices. It only changes the partial recursion-limit
ordering when the next over-limit partial would otherwise be missing.
