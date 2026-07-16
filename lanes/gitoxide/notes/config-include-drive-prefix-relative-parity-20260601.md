# Gitoxide Config Include Drive Prefix Relative Parity

Micro-slice: `gitoxide-config-include-conditional-parity-20260601T045131Z`

Accepted base: `4a27fd6be5ffa953c7d918d551ebb545b1ce7b8d`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/file/includes/mod.rs`
  resolves both `gitdir:` conditional patterns and `include.path` values with
  platform path semantics. On Unix, `C:/...` is not absolute, so it receives
  the same relative-path handling as any other path component.
- The same source treats drive-style absolute paths as platform-dependent
  instead of globally absolute; this keeps literal Unix directory names such as
  `C:` matchable through `gitdir:` conditions.

Native PHP movement:

- `GitConfig::isAbsolutePath()` now treats drive-letter paths as absolute only
  on Windows. Slash-rooted paths remain absolute on all platforms.
- `GitConfigTest.php` adds a Unix-only parity case proving `include.path =
  C:/include.config` resolves relative to the containing config file and
  `includeIf "gitdir:C:/wp-content.git/"` matches a literal Unix `C:`
  directory repository.
- The WordPress config include fixture/example now exposes a literal
  `C:/wp-content-drive.git` deployment policy on Unix, proving the WordPress
  smoke path exercises the same platform-specific absolute-path boundary.

Focused evidence:

- Red-first probe before the fix returned `'base'` for
  `includeIf "gitdir:C:/wp-content.git/"` against a Unix repository rooted at
  `.../C:/wp-content.git/.git`.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`: `1 test
  files, 219 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `40 test files, 7453
  assertions, 0 failures`.

Dependency closure:

No new support component is needed. This slice reuses the existing native Git
config parser, include resolver, path interpolation, and byte-oriented
wildmatch behavior; no shared dependency row or activation gate is proposed.

Non-overlap:

This extends the accepted config include/includeIf escape, wildcard, POSIX,
symlink, sentinel, optional-prefix, and trailing-backslash slices with a
platform absolute-path boundary. It does not touch protocol, transport, pack,
reference, object database, sparse checkout, pathspec, URL/refspec, or merge
behavior. The old Gitoxide smart-HTTP rework notes are stale metadata conflicts
and do not apply to this config/include slice.
