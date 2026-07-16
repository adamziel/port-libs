# Gitoxide SSH Receive-Pack SCP Bracket Boundary Parity

Micro-slice: `gitoxide-ssh-receive-pack-boundary-parity-20260601T130030Z`

Accepted base: `27cf721c25e91c9dcac0b599677df25582e922d2`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/parse.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/ssh.rs`

`gix-url::parse::find_scheme()` only skips an IPv6 bracket block for SCP-like
delimiter detection when the URL starts with `[` and the closing `]` is found.
An unmatched leading bracket still exposes the first `:` to SCP-like parsing,
then the authority parse rejects the malformed bracketed host. Upstream accepts
`[::1]:repo` but rejects user-qualified SCP-like bracketed IPv6 authorities.

## Native PHP Delta

- `SshReceivePackTransport::parseScpLikeUrl()` now validates leading
  bracketed SCP-like hosts before connector planning.
- `[::1:wp-content.git` fails with a missing-closing-bracket exception instead
  of being split into host `[` and path `:1:wp-content.git`.
- `[::1]suffix:wp-content.git` fails with a bracket-suffix exception instead
  of being accepted as host `[::1]suffix`.
- The WordPress receive-pack fixture/example now exposes both unsafe boundary
  checks alongside the existing bracketed IPv6 and user-qualified IPv6 guards.

## Focused Evidence

- Baseline focused test: `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php` passed 1 file / 1176 assertions / 0 failures.
- Red probe before fix: `SshReceivePackTransport::parseRepositoryUrl("[::1:repo")` returned host `[` and path `:1:repo`.
- Red probe before fix: `SshReceivePackTransport::parseRepositoryUrl("[::1]extra:repo")` returned host `[::1]extra`.
- Focused after fix: `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php` passed 1 file / 1180 assertions / 0 failures.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
SSH receive-pack URL parser and caller-approved connector boundary; no live SSH
process, network credential, Cargo workspace, or shared dependency row is
required.

## Non-Overlap

This does not repeat accepted SSH receive-pack program-kind planning,
protocol-v2 environment propagation, feature probes, identity username
boundaries, root-authority parsing, IPv6 argv formatting, invocation quoting,
nonnumeric URL-port handling, smart HTTP, git-daemon, send-pack, packet-line,
URL/refspec parsing, merge-base, sparse-checkout, pathspec, or object-database
work. It is bounded to malformed SCP-like bracketed SSH receive-pack authority
rejection before connector handoff.
