# Protocol V2 ls-refs Refspec Prefix Parity

Micro-slice: `gitoxide-protocol-v2-ls-refs-advertisement-parity-20260531T093050Z`

## Upstream Source Truth

- Inspected upstream `/home/claude/port-libs/.upstream-cache/gitoxide/gix-protocol/src/ls_refs.rs`.
- Inspected upstream `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/spec.rs`.
- Inspected upstream `/home/claude/port-libs/.upstream-cache/gitoxide/gix-protocol/tests/protocol/command.rs`.

## Native PHP Delta

- Added `LsRefsCommand::createFromFetchRefspecs()` to build an `ls-refs` command from fetch refspecs.
- Added `LsRefsCommand::refPrefixesFromFetchRefspecs()` to expand shorthand fetch refspecs into Gitoxide-style DWIM prefixes while preserving first-seen prefix order and de-duplicating repeats.
- Updated the WordPress protocol-v2 `ls-refs` fixture and example so shorthand `main` and `wp-release` fetch refspecs produce the expanded request advertisement prefixes before parsing SHA-256 refs.

## Verification

- Red-first focused check after the fixture switch and before expected-list update: `php tools/run-tests.php lanes/gitoxide/tests/ProtocolV2Test.php` => `1 test files, 61 assertions, 1 failures`.
- Focused test after implementation: `php tools/run-tests.php lanes/gitoxide/tests/ProtocolV2Test.php` => `1 test files, 67 assertions, 0 failures`.
- Full Gitoxide lane: `php tools/run-tests.php lanes/gitoxide/tests` => `38 test files, 3801 assertions, 0 failures`.
- PHP lint: `php -l lanes/gitoxide/src/LsRefsCommand.php`, `php -l lanes/gitoxide/tests/ProtocolV2Test.php`, `php -l lanes/gitoxide/fixtures/wordpress-protocol-v2-ls-refs.php`, and `php -l lanes/gitoxide/examples/wordpress-protocol-v2-ls-refs.php` => no syntax errors.
- Example smoke: `php lanes/gitoxide/examples/wordpress-protocol-v2-ls-refs.php` => exit `0`.
- Lane diff check: `git diff --check -- lanes/gitoxide` => exit `0`.
- JSON check: `lanes/gitoxide/lane-status.json` and `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json` decode successfully.

## Dependency Closure

No new support component is needed. This reuses native PHP protocol framing plus the existing lane-local `RefSpec` parser and prefix expansion.

## Non-Overlap

This does not repeat accepted protocol-v2 packet capability parsing, request-byte framing, packet-line ref advertisement parsing, SHA-256 ref parsing, protocol-v2 fetch sideband response parsing, send-pack report-status-v2 SHA-256 handling, smart HTTP redirect/cookie work, SSH auth-boundary handling, sparse checkout pathspec behavior, or attributes/pathspec state filtering. It is bounded to the upstream `RefPrefixes::from_refspecs()` behavior that expands fetch refspecs into `ls-refs` `ref-prefix` request arguments.
