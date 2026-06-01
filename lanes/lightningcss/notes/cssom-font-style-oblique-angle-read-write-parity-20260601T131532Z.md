# CSSOM Font-Style Oblique Angle Read/Write Parity

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T131532Z`
- Base accepted HEAD: `a93e599b8ba28b765620aaefefa98a3cad05be92`
- Upstream source truth: `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Upstream Evidence

- `src/properties/font.rs` parses `font-style: oblique` with `FontStyle::default_oblique_angle()` of `Angle::Deg(14.0)`.
- The same `ToCss` implementation writes `oblique` and only appends an angle when the stored angle differs from the 14deg default.
- `src/values/angle.rs` serializes angles through dimension formatting and compares angle equality by degree conversion.

## Red-First Probe

Before the patch, the native PHP CSSOM path preserved the default angle instead of using the upstream serializer shape:

```text
php -r 'require "tools/bootstrap.php"; $b = new PortLibs\LightningCSS\DeclarationBlock(); var_export([$b->getProperty("font-style: Oblique 14deg", "font-style"), $b->setProperty("font-style: italic; color: red", "font-style", "Oblique 14deg"), $b->getProperty("font: oblique +014.000deg 600 16px Inter", "font")]); echo "\n";'

array (
  0 => array ('value' => 'oblique 14deg', 'important' => false),
  1 => 'font-style: oblique 14deg; color: red',
  2 => array ('value' => 'oblique +014.000deg 600 16px Inter', 'important' => false),
)
```

## Implementation

- Added native PHP font shorthand normalization for direct `font` declaration read/write paths.
- Canonicalized `font-style: oblique 14deg` and `oblique +014.000deg` to `oblique`.
- Preserved non-default oblique angles while normalizing numeric literals and units, e.g. `oblique 40.000deg` to `oblique 40deg` and `oblique +0.2500TURN` to `oblique .25turn`.
- Extended the existing WordPress font CSSOM smoke to cover default-oblique shorthand updates.

## Verification

- `php -l lanes/lightningcss/src/DeclarationBlock.php` -> no syntax errors
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` -> no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-font-cssom.php` -> no syntax errors
- `php lanes/lightningcss/examples/wordpress-font-cssom.php --self-test` -> `OK`
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 1234 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7994 assertions, 0 failures`
- `php -r 'foreach (["lanes/lightningcss/lane-status.json", "lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'` -> both JSON files OK
- `git diff --check -- lanes/lightningcss` -> passed

## Non-Overlap

This slice deepens the already represented DeclarationBlock CSSOM font cluster. It does not touch the recently accepted legacy flex CSSOM, source-map empty-span, bundle/import graph, CSS Modules, custom at-rule, media-query, or target-prefixing surfaces. Conservative mapped coverage remains `2392 / 3532`.

## Dependency Closure

No new support component is needed. The behavior reuses the existing native PHP `DeclarationBlock` font parser/serializer and adds focused CSSOM tests plus the lane-local WordPress font CSSOM smoke.
