# CSSOM Font Palette Read/Write Parity

Source truth: upstream `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/properties/mod.rs` maps `font-palette` to `FontPalette(DashedIdentReference)`, and `src/values/ident.rs` parses and serializes dashed identifiers through the dashed-ident code path.

Behavior ported:
- `DeclarationBlock` now canonicalizes `font-palette` values that are a single dashed identifier.
- Escaped dashed identifiers are decoded and serialized on `parse()`, `getProperty()`, and `setProperty()` for the real property.
- Custom properties with similar names keep their raw CSS token stream.
- Invalid or partially consumed values remain trimmed rather than throwing, matching the existing declaration-block CSSOM behavior.

Red-first evidence:
- Before the source change, `php -r 'require "tools/bootstrap.php"; $b = new PortLibs\LightningCSS\DeclarationBlock(); var_export([$b->parse("font-palette: --\\\\43 ooler; color: red"), $b->getProperty("font-palette: --\\\\43 ooler", "font-palette"), $b->setProperty("font-palette: --Old; color: red", "font-palette", "--\\\\43 ooler")]);'` preserved raw `--\43 ooler` instead of serializing `--Cooler`.

Focused evidence:
- `php -l lanes/lightningcss/src/DeclarationBlock.php` passed.
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-font-palette-cssom.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` passed with 1 test file, 1177 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-font-palette-cssom.php --self-test` passed.
- `php tools/run-tests.php lanes/lightningcss/tests` passed with 13 test files, 7503 assertions, 0 failures.
- `git diff --check -- lanes/lightningcss` passed.

WordPress smoke:
- `wordpress-font-palette-cssom.php` models theme/editor typography CSS using escaped `font-palette` tokens. The smoke verifies read, important write, normal write, and remove behavior while preserving the WordPress custom property token stream without Node/WASM.

Dependency closure: no new support component is needed; this reuses native PHP `DeclarationBlock`, the existing CSS escape reader, and a bounded identifier serializer for this CSSOM property.

Non-overlap: avoids accepted bundle/import graph, source-map, CSS Modules, custom-at-rule, media-query, target-prefix, grid value minifier, SVG paint CSSOM, flex CSSOM, and source-map rejected-child merge slices. This slice is limited to upstream `font-palette` dashed-ident CSSOM read/write parity.

Next task: continue CSSOM declaration parity with another unmapped upstream property/value read-write gap, or pivot to bundle/import graph, source-map, CSS Modules, visitor/custom at-rule, target-prefixing, media-query, selector, parser recovery, or property/value parity per supervisor priority.
