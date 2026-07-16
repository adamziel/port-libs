# Gitoxide Config Include Hasconfig URL Byte Parity

Slice: `gitoxide-config-include-conditional-parity-20260531T132741Z`

## Upstream Source Truth

- `gix-config/src/file/includes/mod.rs` evaluates
  `hasconfig:remote.*.url` by passing each remote URL value directly to
  `gix_glob::wildmatch(value_glob, url, NO_MATCH_SLASH_LITERAL)`.
- The same file keeps path separator handling inside `gitdir_matches`; remote
  URLs are not normalized as filesystem paths.
- `gix-glob/src/wildmatch.rs` treats `/` as the only slash boundary for
  `NO_MATCH_SLASH_LITERAL`; a backslash in the matched URL remains an ordinary
  byte unless the pattern explicitly escapes it.

## Native Change

- `GitConfig::wildmatch()` no longer normalizes every candidate string with
  filesystem slash conversion. `gitdir` matching still passes normalized git
  directory paths explicitly, while `hasconfig` preserves remote URL bytes.
- `GitConfigTest.php` adds a focused red-first hasconfig case where
  `https://git.example.test/wp-content.git` must not match a remote URL
  containing `\`, but an explicitly escaped backslash pattern and `?` still
  match the non-slash byte.
- The WordPress config include fixture/example records both the rejected slash
  policy and the accepted literal-backslash policy for deployment remotes.

## Evidence

- Red-first probe before the implementation returned `string(6) "loaded"` for
  the slash condition against a backslash-bearing remote URL.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`
  - `1 test files, 106 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 4637 assertions, 0 failures`
- `php -l lanes/gitoxide/src/GitConfig.php`
- `php -l lanes/gitoxide/tests/GitConfigTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-config-include-conditional.php`
- `php -l lanes/gitoxide/examples/wordpress-config-include-conditional.php`
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`
- `git diff --check -- lanes/gitoxide`

## Dependency Closure

No new support component is needed. This reuses the existing native config
parser, include resolver, and bounded byte-oriented glob matcher.

## Non-overlap

This extends accepted config include escape, double-star, bracket slash, POSIX
class, malformed bracket, and byte-safe malformed-UTF-8 slices without
repeating them. It does not touch protocol, transport, object database, pack,
reference, sparse checkout, pathspec, or merge behavior. The old Gitoxide
smart-HTTP rework notes are stale for this slice because they target transport
status/redirect metadata conflicts, not config include parity.
