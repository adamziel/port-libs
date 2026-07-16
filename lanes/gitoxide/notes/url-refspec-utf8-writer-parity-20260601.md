# Gitoxide URL/Refspec UTF-8 Writer Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260601T084735Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/lib.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/http.rs`

Pinned `gix-url` percent-decodes valid UTF-8 path and userinfo components, then
the canonical writer re-escapes only the configured ASCII/control encode sets.
That means valid non-ASCII UTF-8 bytes such as `\xC3\xA9` are preserved in the
serialized URL instead of being re-encoded as `%C3%A9`.

## Native Delta

- `GitUrl::percentEncode()` now leaves valid non-ASCII UTF-8 bytes untouched
  while continuing to escape ASCII controls, DEL, spaces, `%`, and the
  component-specific URL delimiters.
- `UrlRefSpecTest.php` now covers percent-decoded UTF-8 path, username, and
  password serialization plus redacted display output.
- The WordPress URL/refspec fixture/example now includes a deployment remote
  with a UTF-8 user and plugin path, proving native normalization without a Git
  binary or live remote.

## Verification

- Baseline probe before implementation:
  `php -r 'require "tools/bootstrap.php"; ...'` showed
  `https://example.com/caf%C3%A9/...` and
  `https://deploy%C3%A9:p%C3%A4ss@...`, confirming the PHP writer was still
  over-escaping valid UTF-8 bytes.
- Syntax:
  `php -l lanes/gitoxide/src/GitUrl.php && php -l lanes/gitoxide/tests/UrlRefSpecTest.php && php -l lanes/gitoxide/fixtures/wordpress-url-refspec-normalize.php && php -l lanes/gitoxide/examples/wordpress-url-refspec-normalize.php`
  passed.
- Focused:
  `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed
  `1 test files, 709 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test`
  exited `0`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` passed
  `40 test files, 8320 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. This reuses the existing native URL parser,
UTF-8 validation, and canonical writer; no credential store, live remote,
network service, upstream binary, or shared support activation gate is required.

## Non-Overlap

This is bounded to canonical URL UTF-8 writer parity after successful parse. It
does not repeat accepted file-authority parsing, alternate serialization,
from-bytes/from-parts construction, credential mutation, pathless extension
remotes, URL length guards, empty SSH port normalization, SCP bracket
boundaries, home-path expansion, root-path argument safety, FTP host-required
validation, one-sided push writer normalization, short-hex refspec prefix
handling, smart HTTP/SSH transport safety, protocol, object database, pack,
reference, merge, sparse-checkout, or pathspec slices.
