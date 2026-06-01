# Sparse checkout non-cone extra slash parity

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260601T061003Z`

Source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/parse.rs`
  consumes exactly one leading `/` for an anchored pattern and exactly one
  trailing `/` for `MUST_BE_DIR`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/tests/parse/mod.rs`
  includes `leading_slashes_mark_patterns_as_absolute` and
  `trailing_slashes_are_marked_and_removed`; the `dir///` case keeps `dir//`
  as literal pattern bytes after removing only the final directory marker.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-ignore/src/parse.rs`
  delegates non-cone ignore/sparse pattern lines to `gix_glob::Pattern::from_bytes`.

Delta:

- `SparseCheckoutSpec::fromNonConePatternFile()` no longer collapses all
  leading or trailing slash bytes. It removes only the upstream-consumed anchor
  slash and directory marker slash, leaving extra slash bytes literal.
- Added focused sparse-checkout assertions for `//wp-content/cache/**`,
  `wp-content/generated///`, and a single-leading-slash directory include.
- Updated the WordPress sparse-checkout example smoke to prove extra slash
  patterns do not accidentally materialize cache/generated paths while a normal
  anchored directory pattern still includes plugin content.

Red-first evidence:

- Before the parser change, `//wp-content/cache/**` included
  `wp-content/cache/page.html` and `wp-content/generated///` included
  `wp-content/generated/page.html` because PHP used `ltrim()` and `rtrim()`.
- Baseline focused sparse checkout test before the patch:
  `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  reported `1 test files, 338 assertions, 0 failures`.

Verification:

- `php -l lanes/gitoxide/src/SparseCheckoutSpec.php` passed.
- `php -l lanes/gitoxide/tests/SparseCheckoutTest.php` passed.
- `php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php` passed.
- `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  reported `1 test files, 346 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` reported
  `40 test files, 7723 assertions, 0 failures`.
- Example smoke:

  ```sh
  php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; foreach (["nonConeExtraLeadingSlashCacheSkipped", "nonConeExtraTrailingSlashGeneratedSkipped", "nonConeSingleLeadingSlashPluginIncluded"] as $key) { if (($out[$key] ?? null) !== true) { fwrite(STDERR, $key . " failed\n"); exit(1); } } if (($out["nonConeExtraSlashEntriesToMaterialize"] ?? null) !== ["plugins"]) { fwrite(STDERR, "nonConeExtraSlashEntriesToMaterialize failed\n"); exit(1); } echo "sparse extra slash example ok\n";'
  ```

  reported `sparse extra slash example ok`.
- `git diff --check -- lanes/gitoxide` passed.

Non-overlap:

- This is limited to non-cone sparse-checkout pattern file slash parsing. It
  does not touch the recently accepted pack-index/MIDX prefix range,
  loose-object candidate, reference transaction, tree-merge, transport, or
  URL/refspec surfaces.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP
  sparse-checkout/pathspec parser and tree-entry filtering helpers.
