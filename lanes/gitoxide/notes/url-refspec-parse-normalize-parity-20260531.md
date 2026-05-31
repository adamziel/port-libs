# Gitoxide URL/Refspec Parse Normalize Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260531T091856Z`

Accepted base: `0098ded681a4eb1c42c3ee09d87f3167111f8b69`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/file.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/ssh.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/parse.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/write.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/tests/refspec/parse/fetch.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/tests/refspec/parse/push.rs`

## Native PHP Delta

- `GitUrl::parse()` now splits `file://User@[::1]/repo` into a file URL with user `User`, host `[::1]`, and path `/repo`, matching the focused `gix-url` file URL authority cases.
- SCP-like IPv6 remains accepted without a user, for example `[::1]:repo`, but `user@[::1]:repo` is rejected like upstream and Git.
- `RefSpec::parseFetch('+')` now resolves to forced fetch-only `HEAD` instead of an empty source.
- `RefSpec::toString()` follows the `gix-refspec` instruction writer boundary for forced fetch-only and forced delete instructions: `+` and `+refs/heads/main:` normalize to `HEAD` and `refs/heads/main`, while `+:refs/heads/old` normalizes to `:refs/heads/old`.
- The WordPress URL/refspec example now includes a local file-authority mirror URL plus forced fetch-only and forced delete refspec normalization.

## Focused Evidence

- Red-first check after adding assertions and before implementation: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` failed with `Expected: 'User' Actual: NULL` for file URL authority parsing and `Expected: 'HEAD' Actual: NULL` for forced fetch-only parsing.
- Focused after fix: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed `1 test files, 407 assertions, 0 failures`.
- Full Gitoxide lane after fix: `php tools/run-tests.php lanes/gitoxide/tests` passed `38 test files, 3838 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test` exited 0.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP URL authority parser and refspec parser/writer model; no shared dependency row or activation gate is proposed.

## Non-Overlap

This extends the accepted `acedf069` URL/refspec parse-normalize slice with missing `gix-url` file-authority/SCP IPv6 boundaries and `gix-refspec` forced instruction writer normalization. It does not touch transport, protocol v2, pack/object database, references, merge, pathspec, sparse checkout, or the stale smart HTTP receive-pack rework notes from May 25.
