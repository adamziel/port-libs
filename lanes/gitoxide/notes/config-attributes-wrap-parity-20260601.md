# Gitoxide Config Attributes Wrap Parity

Micro-slice: `gitoxide-current-base-config-attrs-wrap-20260601T2320Z`
Base accepted HEAD: `571edf7283b7ed665e6c811d380f6aae0875b7ff`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/parse/from_bytes/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/parse/from_bytes/tests.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/parse/events.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-attributes/src/parse.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-attributes/tests/parse/mod.rs`

Upstream `gix-config` value parsing accepts backslash-LF, backslash-CRLF, and
trailing-backslash-at-EOF continuations, keeps quote state across continuation,
and rejects a backslash followed by bare CR. It also ends unquoted values at `#`
or `;` once quote state has closed. Upstream `gix-attributes` parses physical
lines without backslash continuation, so a trailing-backslash pattern line must
not join with the next physical line.

## Implementation

- `GitConfig::logicalLines()` now scans raw bytes so LF/CRLF continuation and
  trailing EOF continuation still work while bare-CR continuation throws before
  silently joining the next line.
- `GitConfig::stripInlineComment()` now follows the gix-config value parser by
  treating `#` and `;` as comment delimiters whenever they are outside quotes,
  not only after whitespace.
- `GitConfigTest.php` adds LF, CRLF, EOF, post-quote `;comment`, raw `#comment`,
  and CR-only rejection assertions.
- `AttributesPathspecTest.php` adds the paired guard that `.gitattributes`
  trailing backslash lines do not wrap into the next physical line.
- WordPress-facing config and attributes examples expose wrapped deployment
  notice and no-wrap path policy results.

## Verification

- `php -l lanes/gitoxide/src/GitConfig.php`: no syntax errors.
- `php -l lanes/gitoxide/tests/GitConfigTest.php`: no syntax errors.
- `php -l lanes/gitoxide/tests/AttributesPathspecTest.php`: no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-config-include-conditional.php`: no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-config-include-conditional.php`: no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php`: no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php lanes/gitoxide/tests/AttributesPathspecTest.php`: `2 test files, 737 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`: exited `0`.
- `php lanes/gitoxide/examples/wordpress-attributes-pathspec.php`: exited `0`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `40 test files, 10803 assertions, 0 failures`.
- `git diff --check -- lanes/gitoxide`: passed.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP Git config
parser, attributes parser, pathspec matcher, and fixture examples. It does not
shell out to Git, run network transports, or read credential/provider state.

## Non-Overlap

This extends the accepted config include/includeIf and attributes/pathspec
clusters without repeating include path interpolation, gitdir/hasconfig glob
classes, attribute quoted-pattern parsing, POSIX class matching, sparse
checkout, tree pathspec, loose-object, pack/MIDX, reference transaction,
transport, merge-base, or tree-merge behavior. The attributes half is a guard
for upstream no-wrap parsing and is counted inside the same config/attributes
wrap cluster; mapped coverage moves conservatively by one upstream gix-config
value parser behavior.
