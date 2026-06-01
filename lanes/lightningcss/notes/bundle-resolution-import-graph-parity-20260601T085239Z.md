# LightningCSS bundle resolution/import graph parity - 2026-06-01T08:52:39Z

## Source truth

- Upstream pinned checkout: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream native-addon probes for malformed `@import ... supports(...)` conditions reject before resolver/source-provider reads and report token-specific parser diagnostics:
  - `supports()` -> `Unexpected end of input` at line 1, column 31.
  - `supports((display: grid) and)` -> `Unexpected token Ident("and")` at line 1, column 46.
  - `supports((display: grid) or (color: red) and (foo: bar))` -> `Unexpected token Ident("and")` at line 1, column 62.
  - `supports(not display: grid)` and `supports(not/**/display: grid)` -> `Unexpected token Ident("display")` at line 1, column 34.
- Valid native cases remain accepted, including `not selector(.a)`, `not (selector(.a))`, `not (display: grid)`, and top-level `and` / `or` compositions whose operands are valid supports conditions.

## Implemented behavior

- `CssBundler` now keeps the raw `supports(...)` import condition while validating the normalized condition, so parser-error diagnostics can be reported at upstream token locations.
- Invalid import supports conditions now map common upstream parser errors for empty conditions, trailing logical operators, mixed top-level logical operators, and invalid bare `not` operands.
- Resolver/source-provider callbacks still read only the entry file for these parser failures.
- The WordPress bundle/import graph smoke now covers a malformed block stylesheet import with `supports(not/**/display: grid)` and expects the upstream-style token diagnostic.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php` - pass
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - pass
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - pass
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - 1 file, 653 assertions, 0 failures
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - pass, including `bad-import-supports: rejected`
- `php tools/run-tests.php lanes/lightningcss/tests` - 13 files, 7004 assertions, 0 failures
- `php -r '$path="lanes/lightningcss/lane-status.json"; json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` - pass
- `git diff --check -- lanes/lightningcss` - pass

## Status delta

- `lane-status.json` `phpPass`: `6998` -> `7004`
- Expected dashboard movement: +6 focused LightningCSS assertions; no mapped-manifest denominator change.

## Non-overlap

- Avoided recent accepted source-map pruning, unicode-bidi target prefixing, CSS Modules escaped custom-ident composition, target-prefix browser-boundary, CSSOM, custom at-rule, and property-value slices.
- This patch is scoped to bundle/import graph parser-diagnostic parity for malformed `@import supports(...)` conditions.

## Dependency closure

- No new support component is needed. The change reuses the existing native PHP bundler parser helpers, resolver callback plumbing, and diagnostic location mapper.

## Follow-up

- Continue import graph parity around remaining resolver/layer/media diagnostic edges and CSS Modules dependency import diagnostics.
