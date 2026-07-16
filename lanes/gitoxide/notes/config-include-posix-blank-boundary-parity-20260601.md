# Gitoxide Config Include POSIX Blank Boundary Parity

Micro-slice: `gitoxide-config-include-conditional-parity-20260601T012313Z`

Accepted base: `b9bbeca66ecf5a12b5cede18d997f59a57398d59`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/file/includes/mod.rs`
  routes `gitdir`, `gitdir/i`, `onbranch`, and
  `hasconfig:remote.*.url` include conditions through
  `gix_glob::wildmatch` with `NO_MATCH_SLASH_LITERAL`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`
  evaluates POSIX bracket classes byte-by-byte. Its `[:blank:]` branch uses
  Rust ASCII whitespace, which matches HT, LF, FF, CR, and space, while
  vertical tab remains covered by `[:cntrl:]` rather than `[:blank:]`.

## Native PHP Movement

- `GitConfig::posixCharacterClassBytes()` now mirrors the upstream
  `[:blank:]` byte set and no longer treats vertical tab as a blank byte.
- `GitConfigTest.php` adds focused includeIf assertions for tab and vertical
  tab bytes in `gitdir` paths and `hasconfig:remote.*.url` values, preserving
  `[:cntrl:]` matching for vertical tab.
- The WordPress config include fixture/example now exposes tab, vertical-tab,
  and control-class remote URL policies for deployment config selection without
  shelling out to `git`.

## Focused Evidence

- Red-first probe before the matcher fix loaded the vertical-tab gitdir policy:
  `GitConfig::fromFile()` returned `'vt'` for
  `gitdir:deploy[[:blank:]]site/` against `deploy\x0Bsite/.git`.
- `php -l lanes/gitoxide/src/GitConfig.php`: no syntax errors.
- `php -l lanes/gitoxide/tests/GitConfigTest.php`: no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-config-include-conditional.php`:
  no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-config-include-conditional.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`: `1 test
  files, 185 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `40 test files, 6655
  assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`:
  exits `0`.
- `php -r 'foreach (["lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json",
  "lanes/gitoxide/lane-status.json"] as $f) { json_decode(file_get_contents($f),
  true, flags: JSON_THROW_ON_ERROR); echo $f, " ok\n"; }'`: both JSON files
  decode successfully.
- `git diff --check -- lanes/gitoxide`: exits `0`.

## Dependency Closure

No new support component is needed. This slice reuses the native Git config
parser, include resolver, and byte-oriented wildmatch compiler; no shared
dependency row or activation gate is proposed.

## Non-overlap

This extends accepted config include/includeIf escape, double-star,
bracket-slash, POSIX class, malformed bracket, byte-safe, optional-prefix,
backslash-gitdir, symlink-gitdir, reversed-range, and trailing-backslash slices
with the remaining POSIX `[:blank:]` byte-class boundary. It does not touch
protocol, transport, pack, reference, object database, index, sparse checkout,
pathspec, URL/refspec, or merge behavior. The old Gitoxide smart-HTTP rework
notes are stale for this slice because they target receive-pack redirect/status
metadata conflicts, not config include parity.
