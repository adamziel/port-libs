# Gitoxide URL/Refspec Short Hex Prefix Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260531T102558Z`

Accepted base: `abe349fe4c5a6f978b53aa40c7bbfdcb020ef0a8`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/spec.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/parse.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/tests/refspec/spec.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-hash/src/object_id.rs`

`gix-refspec` treats negative short-hex sources such as `^dead` as object-like
prefixes, but `expand_prefixes()` suppresses only full object IDs accepted by
`gix_hash::ObjectId::from_hex()`. Short hex-looking branch names still expand
through the partial-name DWIM list.

## Native PHP Delta

- Split the PHP refspec helper into object-prefix detection for negative
  validation and full object-id detection for prefix expansion.
- `RefSpec::expandPrefixes()` now expands short hex-like fetch and push
  destinations such as `dead` and `20260531` into the same partial-name prefix
  list as upstream.
- Full SHA-1 and SHA-256 object IDs still expand to no prefixes.
- The WordPress URL/refspec example now includes a date-shaped branch
  `20260531`, proving deployment tooling will probe branch/tag/remotes
  prefixes instead of treating the name as an object ID.

## Focused Evidence

- Red-first after adding assertions: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` failed with `Expected: array (...) Actual: array ()` for `dead` prefix expansion.
- Upstream focused probe: `cargo test -p gix-refspec --test refspec expand_prefixes -- --nocapture` passed `8 passed; 0 failed; 66 filtered out`.
- Focused after fix: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed `1 test files, 441 assertions, 0 failures`.
- Full Gitoxide lane after fix: `php tools/run-tests.php lanes/gitoxide/tests` passed `38 test files, 4142 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test` exited 0.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
refspec parser/writer model and object-id length guards; no shared dependency
row or activation gate is proposed.

## Non-Overlap

This extends URL/refspec parity beyond accepted file-authority/SCP IPv6,
forced fetch-only normalization, and one-sided push writer work. It is bounded
to `gix-refspec` prefix expansion for short hex-looking partial ref names and
does not touch transport, protocol v2, pack/object database, references, merge,
pathspec, sparse checkout, or the stale smart HTTP receive-pack rework notes
from May 25.
