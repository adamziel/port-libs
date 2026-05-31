# Gitoxide URL/Refspec Push Implicit Destination Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260531T094926Z`

Accepted base: `39bb58e3950abcc0370640338af645050eeb5116`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/parse.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/spec.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/write.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/tests/refspec/parse/push.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/tests/refspec/write.rs`

## Native PHP Delta

- `RefSpec::parsePush()` keeps upstream parse shape for one-sided push refspecs: `@`, `+@`, and `refs/heads/wp-content` have a source but no stored destination.
- `RefSpec::toString()` now follows the upstream instruction writer boundary for source-only push refspecs by serializing the implicit same-name destination: `@` becomes `HEAD:HEAD`, `+@` becomes `+HEAD:HEAD`, and `refs/heads/wp-content` becomes `refs/heads/wp-content:refs/heads/wp-content`.
- The WordPress URL/refspec example now includes a same-name content branch push so deployment previews normalize push instructions without shelling out to Git.

## Focused Evidence

- Red-first check after adding assertions and before implementation: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` failed because `@` normalized to `HEAD` instead of upstream-shaped `HEAD:HEAD`.
- Focused after fix: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed `1 test files, 431 assertions, 0 failures`.
- Full Gitoxide lane after fix: `php tools/run-tests.php lanes/gitoxide/tests` passed `38 test files, 4013 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test` exited 0.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP refspec parser/writer model; no shared dependency row or activation gate is proposed.

## Non-Overlap

This extends URL/refspec parity beyond the accepted file-authority/SCP IPv6 and forced fetch-only work by covering the upstream `gix-refspec` one-sided push instruction writer. It does not touch transport, protocol v2, pack/object database, references, merge, pathspec, sparse checkout, or the stale smart HTTP receive-pack rework notes from May 25.
