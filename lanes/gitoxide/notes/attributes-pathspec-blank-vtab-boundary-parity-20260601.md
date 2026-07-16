# Attributes/pathspec POSIX blank vertical-tab boundary parity

Slice: `gitoxide-attributes-pathspec-match-parity-20260601T160510Z`

Base accepted source: `60fde5b67432524a0e4cd56c0332c44c08d854a6`

Upstream source truth:

- Pinned Gitoxide upstream: `/home/claude/port-libs/.upstream-cache/gitoxide`
  at `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-glob/src/wildmatch.rs` maps POSIX `[:blank:]` through Rust
  `u8::is_ascii_whitespace()`.
- A focused local Rust probe confirmed the byte set matched by that upstream
  branch is HT (`0x09`), LF (`0x0a`), FF (`0x0c`), CR (`0x0d`), and space
  (`0x20`), and excludes VT (`0x0b`).

Red-first observation before the patch:

```text
GitAttributes::attributesForPath("wp-content/uploads/slot\v/photo.jpg") => whitespace attribute matched
PathspecSearch::fromSpecs([":(glob)wp-content/uploads/slot[[:blank:]]/photo.jpg"])->isIncluded("wp-content/uploads/slot\v/photo.jpg") => true
PathspecMatcher::matchesOne(":(glob)wp-content/uploads/slot[[:blank:]]/photo.jpg", "wp-content/uploads/slot\v/photo.jpg") => true
```

Implemented behavior:

- `GitAttributes` POSIX `[:blank:]` now matches HT, LF, FF, CR, and space,
  while excluding VT.
- `PathspecSearch` now uses the same `[:blank:]` byte set, which also corrects
  direct `PathspecMatcher` glob matching through `GitAttributes::globMatches()`.
- `wordpress-attributes-pathspec.php` now covers tab/form-feed positive paths
  and the vertical-tab negative path for upload pathspecs.

Focused verification:

```text
php -l lanes/gitoxide/src/GitAttributes.php
php -l lanes/gitoxide/src/PathspecSearch.php
php -l lanes/gitoxide/tests/AttributesPathspecTest.php
php -l lanes/gitoxide/tests/PathspecTreeWalkTest.php
php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php
```

All changed PHP files reported no syntax errors.

```text
php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php
```

Result: `1 test files, 369 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php
```

Result: `1 test files, 368 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php
```

Result: `3 test files, 1175 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/gitoxide/tests
```

Result: `40 test files, 10117 assertions, 0 failures`.

```text
php -r '$out = require "lanes/gitoxide/examples/wordpress-attributes-pathspec.php"; if (($out["verticalTabBlankUploadSkipped"] ?? null) !== true || ($out["formFeedBlankPathspecMatches"] ?? null) !== true || ($out["whitespaceUploadPathspecMatches"] ?? null) !== true) { fwrite(STDERR, "attributes pathspec blank boundary example failed\n"); exit(1); } echo "attributes pathspec blank boundary example ok\n";'
```

Result: `attributes pathspec blank boundary example ok`.

Dependency closure:

No new support component is needed. The slice reuses the existing native PHP
wildmatch/glob translation helpers in `GitAttributes` and `PathspecSearch`.

Exclusion:

The full upstream Cargo workspace was not executed. `SparseCheckoutSpec` has an
independent POSIX class translator and was intentionally left unchanged in this
attributes/pathspec slice; the related sparse-checkout PHP gate still passed.
