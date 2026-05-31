# Gitoxide Config Include Double-Star Component Boundary Parity

Slice: `gitoxide-config-include-conditional-parity-20260531T115517Z`

Accepted base: `ab384a0d481bd4acef6592a38a3540df9d0cc3f2`

Upstream source truth:

- `gix-config/src/file/includes/mod.rs` routes `gitdir`, `gitdir/i`,
  `onbranch`, and `hasconfig:remote.*.url` conditional include patterns
  through `gix_glob::wildmatch` with `NO_MATCH_SLASH_LITERAL`.
- `gix-glob/src/wildmatch.rs` only lets a run of `**` match slash separators
  when the run is a full path component boundary: at pattern start or after
  `/`, and followed by `/`, escaped `/`, or the end of the pattern. Otherwise
  the star run remains component-local like `*`.

Native PHP movement:

- `GitConfig::wildmatch()` now collapses star runs and only emits a
  slash-crossing matcher for path-component `**` boundaries. Unbounded patterns
  such as `wp**content`, `release**candidate`, and `site**content.git` no
  longer cross `/`.
- `GitConfigTest.php` adds focused conditional include assertions for
  `gitdir`, `onbranch`, and `hasconfig:remote.*.url` rejecting unbounded
  `**` slash crossing while preserving bounded `**/` matches.
- The WordPress config include fixture/example now includes a nested content
  remote and proves an unbounded `wp/site**content.git` policy is not loaded.

Focused evidence:

- `php -l lanes/gitoxide/src/GitConfig.php` passed.
- `php -l lanes/gitoxide/tests/GitConfigTest.php` passed.
- `php -l lanes/gitoxide/fixtures/wordpress-config-include-conditional.php`
  passed.
- `php -l lanes/gitoxide/examples/wordpress-config-include-conditional.php`
  passed.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php` passed
  `1 test files, 89 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed
  `39 test files, 4456 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`
  exited 0.
- `git diff --check -- lanes/gitoxide` passed.

Dependency closure:

No new support component is needed. This slice reuses the native Git config
parser, include resolver, and bounded byte-oriented glob matcher; no shared
dependency row or activation gate is proposed.

Non-overlap:

This extends the accepted config include escape, `**/` zero-component,
bracket-slash, byte-safe, and POSIX class slices by tightening the remaining
non-component `**` boundary. It does not touch protocol, transport, pack,
reference, object database, index, sparse checkout, pathspec, URL/refspec, or
merge behavior. The old Gitoxide smart-HTTP rework notes are stale for this
slice because they target receive-pack redirect/status metadata conflicts, not
config include parity.
