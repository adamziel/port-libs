# Gitoxide URL/Refspec Generated Diagnostic Authority Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260601T185627Z`

Accepted base: `49a34e82e4d1b09b82d83bf257144a16faadfd06`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/baseline.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/fixtures/make_baseline.sh`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/fixtures/generated-do-not-edit/make_baseline/sha1/2409820380-unix/git-baseline.unix`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/file.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/ssh.rs`

The upstream `gix-url` baseline compares parsed URLs against Git's
`fetch-pack --diag-url` output and then reparses lossless serialized bytes.
This slice maps the authority and path normalization edges most relevant to
URL/refspec deployment remotes: file authorities with IPv6/user text,
legacy `ssh+git` / `git+ssh` scheme normalization, empty SSH port markers,
SCP-like IPv6 paths with `~` expansion syntax, and the maximum valid SSH port.

## Native PHP Delta

- Added focused `UrlRefSpecTest.php` coverage for generated diagnostic
  authority cases:
  - `file://User@[::1]/~re:po`
  - `file://[::1]/repo`
  - `ssh+git://host:/~repo`
  - `git+ssh://User@[::1]:/re:po`
  - `git://User@[::1]:22/repo`
  - `host.xz:/~repo`
  - `[::1]:/~re/po`
  - `./[::1]:re/po`
  - `ssh://host.xz:65535/repo`
- Updated the WordPress URL/refspec example fixture with a legacy
  `git+ssh://Deploy.User@[::1]:/~wp-content/site.git` deployment remote,
  proving canonical SSH scheme normalization and empty-port stripping without
  invoking the Git binary.

## Focused Evidence

- Baseline before this slice:
  `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed
  `1 test files, 844 assertions, 0 failures`.
- Focused after this slice:
  `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed
  `1 test files, 940 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` passed
  `40 test files, 10665 assertions, 0 failures`.
- PHP lint:
  `php -l lanes/gitoxide/tests/UrlRefSpecTest.php`,
  `php -l lanes/gitoxide/fixtures/wordpress-url-refspec-normalize.php`, and
  `php -l lanes/gitoxide/examples/wordpress-url-refspec-normalize.php` passed.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test`
  exited 0.
- Whitespace check:
  `git diff --check -- lanes/gitoxide` passed.
- Root harness:
  not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`GitUrl` parser/serializer and refspec example plumbing. It does not inspect
live remotes, credential stores, provider configs, OAuth state, or network
services, and it does not shell out to Git.

## Non-Overlap

This deepens the already represented URL/refspec parse-normalize cluster with
generated `gix-url` diagnostic-baseline authority forms. It does not repeat
accepted URL/refspec from-bytes, from-parts, credential mutation, alternate
serialization, file-authority IPv6 user, empty SSH port, pathless extension,
argument-safety/root-path, URL-length, UTF-8 writer, SCP bracket rejection,
short-hex, forced fetch-only, one-sided push, fetch match, or fetch validation
work. It does not touch transport/protocol, pack/object database, references,
merge, sparse checkout, config include, attributes, or pathspec behavior.
