# Gitoxide URL/Refspec From-Bytes Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260601T051230Z`

Accepted base: `b6e9f0ce57867f58750508c9437be4ae03b4d9e1`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/lib.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/access.rs`

`gix-url` exposes `Url::from_bytes()` as the byte deserialization boundary for
URLs that already came from serialized URL bytes. Its focused access tests
round-trip both ordinary canonical URL bytes and a local file path containing
non-UTF-8 bytes.

## Native PHP Delta

- `GitUrl::fromBytes()` now provides the explicit byte-deserialization API and
  reuses the existing parser semantics.
- `UrlRefSpecTest.php` maps the upstream `from_bytes_roundtrip` and
  `from_bytes_with_non_utf8_path` checks: canonical HTTPS bytes match normal
  parse output, and non-UTF-8 local path bytes remain byte-for-byte stable.
- The WordPress URL/refspec fixture/example now records a deployment remote
  supplied as serialized URL bytes, round-trips it through `fromBytes()`, and
  reparses the resulting bytes without invoking the Git binary.

## Focused Evidence

- Baseline focused URL/refspec check before this patch:
  `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed
  `1 test files, 629 assertions, 0 failures`.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed
  `1 test files, 637 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 7546
  assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test`
  exited 0.
- Syntax and whitespace checks passed:
  `php -l` for changed PHP files and `git diff --check -- lanes/gitoxide`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
URL parser, canonical serializer, and byte-safe local path handling. It does
not inspect live environments, credential stores, provider config, OAuth state,
external remotes, or network services.

## Non-Overlap

This maps one additional `gix-url` access-helper behavior cluster and does not
repeat accepted URL/refspec file authority, forced fetch-only, one-sided push
writer, short-hex prefix, URL length guard, empty SSH port, SCP bracket
boundary, home-path expansion, canonical file path, FTP host-required,
pathless extension remote, argument-safety/root-path, credential mutation, or
alternate serialization work. The historical May 25 smart HTTP receive-pack
rework notes target stale transport/status metadata conflicts and are not part
of this URL/refspec cluster.
