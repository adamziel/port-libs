# PHP Dirty Root Evidence - capacity-dirty-root-54a1600947ac-20260523T1916Z

- UTC gate sample: `2026-05-23T19:17:14Z`
- HEAD: `54a1600947ac82d66ae6cd4f99ca70a6a99aac57`
- Scope: current dirty worktree no-argument root PHP verification.
- Scratch: `/home/claude/port-libs/.upstream-cache/capacity-dirty-root-54a1600947ac-20260523T1916Z`
- Log: `/home/claude/port-libs/.tmux-team/logs/port-capacity-dirty-root-54a1600947ac-20260523T1916Z.log`

## Gates

- loadavg: `5.11 3.90 3.38`
- MemAvailable: `14522912 kB`
- root free: `105897784 KiB`
- /tmp use: `66%`
- /tmp inode use: `25%`
- active PHP focused/root runners: `0`
- git status rows: `2795`

## Results

- Command: `php tools/run-tests.php`
- Command exit: `0`
- Elapsed seconds: `94`
- Test files: `263`
- Assertions: `35588`
- Failures: `0`
- Stdout bytes: `300230`
- Stderr bytes: `0`

## Tail

```text
PASS maps upstream sparse zero-block handling from TestPullEmptyBlock
PASS receive-encrypted planning does not reuse temporary file blocks
PASS rejects malformed pull work inputs
PASS stores ClusterConfig password side channel and encrypts outbound index metadata
PASS replaces encrypted folder keys on the next ClusterConfig password set
PASS uses promoted connection password side channel for later encrypted index frames
PASS encrypts trusted session requests and decrypts matching responses
PASS decrypts inbound receive-encrypted index metadata before model callbacks
PASS routes decrypted encrypted index updates through the connection coordinator
PASS decrypts inbound encrypted requests and encrypts session responses
PASS returns a generic response when encrypted request metadata cannot decrypt
PASS replaces keys and ignores encrypted-folder download progress
PASS maps upstream XChaCha20 encrypted bytes fixture
PASS maps encrypted request geometry and opaque hash token fields
PASS maps encryptedConnection request response padding and trim semantics
PASS maps encryptedModel inbound request decryption and response encryption
PASS serves encryptedModel requests through native request server
PASS maps upstream deterministic encrypted name fixtures and invalid cases
PASS maps upstream deterministic block hash token invariants
PASS maps encrypted inbound requests back to plaintext geometry
PASS maps upstream encrypted name slashification and parent detection
PASS maps receive-encrypted synthetic parent scan cleanup
PASS writes and extracts upstream receive-encrypted file trailers
PASS maps receive-encrypted finalization trailer and verification boundaries
PASS maps upstream encrypted file info wrapper invariants
PASS maps encrypted index and index update collection wrappers
PASS maps encryptedConnection DownloadProgress no-op for encrypted folders
PASS maps encryptedModel DownloadProgress no-op before temporary state mutation
PASS maps encrypted file info consistency for ignored symlink metadata
PASS maps upstream model download progress sharing guard and event summary
PASS maps temporary block availability and fromTemporary request planning
PASS prefers full-file availability before temporary candidates and validates inputs
PASS maps upstream disconnected device availability and temporary state cleanup
PASS fails pullBlock before requesting unavailable connected-device candidates
PASS maps pullBlock retry activity and response hash validation
PASS maps pullBlock final failure after response and callback errors
PASS skips network requests for upstream all-zero pull blocks
PASS receive-encrypted pullBlock accepts opaque hash-token responses
PASS maps upstream response error code conversion
PASS maps rawConnection outbound ids and response completions
PASS maps rawConnection close draining awaiting requests
PASS drops queued request ids when outbound request transformation fails
PASS maps dispatcher request validation boundaries before request handling
PASS serves valid fromTemporary requests from the temporary file first
PASS falls back to the final file when temporary data does not validate
PASS maps final-file hash mismatch and empty hash short reads
PASS rejects unshared devices internal paths traversal and negative ranges
PASS maps upstream request max-size guard before serving media bytes
PASS rejects explicit ignored request paths before disk reads
PASS receive-encrypted final requests skip hash validation after temporary mismatch
PASS uses upstream temporary filename hashing for long basenames
PASS maps upstream temporary file prefix recognition
PASS maps upstream sent download append diff and timestamp semantics
PASS maps upstream version changes and puller recreation forget append pairs
PASS maps upstream min block filtering inactive files and completed pull cleanup
PASS maps upstream folder cleanup forget messages
PASS rejects malformed sent download state inputs
PASS maps upstream service map add remove lifecycle
PASS maps upstream service map overwrite stop before replace
PASS maps upstream stop retention remove and wait boundaries
PASS maps upstream iteration with remove and wait
PASS manages wordpress folder services without dropping retained state
PASS propagates service map callback errors like upstream each errors
PASS sqlite checkpoint store persists snapshots across connections with FileInfo metadata
PASS sqlite checkpoint store rejects stale revisions and expires rows before reuse
PASS sqlite checkpoint store merges results and lists unexpired folders in stable order
PASS folder scan service resumes through a sqlite checkpoint store
PASS sqlite checkpoint store rejects unsafe table names and malformed payload rows
PASS maps upstream version update ordering semantics
PASS merges counters using upstream max-by-device rules
PASS compares version vectors including concurrent orderings
PASS detects concurrent wordpress edits before merge
PASS rejects invalid vector counters
PASS wordpress option store persists checkpoint payloads with FileInfo metadata
PASS wordpress option store rejects stale revisions and compare-and-swap conflicts
PASS wordpress option store expires snapshots and deletes stale options before reuse
PASS folder scan service resumes through a wordpress option checkpoint store
PASS wordpress option store hashes unsafe folder IDs and rejects malformed payloads

263 test files, 35588 assertions, 0 failures
```

## Diff Checks

- `git diff --check`: exit `0`, output bytes `0`
- `git diff --cached --check`: exit `0`, output bytes `0`

## Boundary

This is no-argument root verification against the current dirty worktree only. It does not claim clean-head parity, upstream-reference coverage, implementation completion, dashboard progress, or acceptance for unrelated moving changes.
