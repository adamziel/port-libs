# LightningCSS Property Values: Font Oblique Default Angle

## Source truth

- Upstream pinned commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source behavior: `src/properties/font.rs` defines the default oblique angle as `14deg` and omits the angle during serialization only when the parsed oblique angle equals that default.

## Implemented behavior

- `font-style: oblique 14deg` serializes as `font-style:oblique`.
- `font-style: oblique 0deg` and other non-default angles remain explicit.
- `@font-face` oblique ranges omit the angle only when both endpoints equal the upstream default; equal non-default endpoints serialize as one explicit angle.
- `font` shorthand parsing now consumes `oblique <angle>` before the size token, so `font: oblique 14deg 22px Helvetica` serializes as `font:oblique 22px Helvetica` while preserving non-default angles.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php`
  - `1 test files, 1921 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-font-target-fallback.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6817 assertions, 0 failures`

## Non-overlap

This slice avoided the already integrated media query math-function range/layer and grid auto-flow row shorthand property-value clusters. It only changes font-style oblique default-angle serialization and `font` shorthand parsing.

## Dependency closure

No new support component is needed. The behavior is implemented in the existing native PHP CSS minifier and covered by the existing WordPress font fallback example.
