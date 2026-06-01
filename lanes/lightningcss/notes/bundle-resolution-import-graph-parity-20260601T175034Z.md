# Bundle/import graph parity - CSS Modules empty from specifiers

Slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T175034Z`

Source truth:

- Upstream `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/css_modules.rs::Specifier::parse` accepts any CSS string as `Specifier::File(file)`, including `""`.
- A local upstream NAPI probe against `lightningcss.linux-x64-gnu.node` resolved `composes: token from ""` with resolver input `["", "/modules/card.css"]`, read the returned file, emitted the dependency CSS before the importing module, and rewrote the export compose reference to the local dependency class.

Implemented:

- Kept empty CSS Modules dependency specifiers instead of filtering them out of the PHP bundle graph.
- Added focused `CssBundlerTest.php` coverage proving `composes: token from ""` reaches the resolver, hoists dependency CSS, and rewrites exports to the resolved local class.
- Added the same behavior to the WordPress block CSS import-graph smoke.

Verification:

- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - `1 test files, 854 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`
  - exited 0 and printed `css-modules-empty-from: resolved`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 8876 assertions, 0 failures`

Dependency closure:

- No new support component is needed. The existing native PHP CSS Modules transformer already emits the empty dependency specifier; this slice fixes the bundler graph filter so the existing resolver/read path handles it.

Non-overlap:

- Avoids the accepted source-map reused inline input sourcesContent slice, bundle `sourceMapUrls` metadata, CSS Modules direct-style dependency location diagnostics, resolver result-shape diagnostics, and repeated layered import graph work.
