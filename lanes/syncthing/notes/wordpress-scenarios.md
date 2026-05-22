# syncthing WordPress Scenario

Resumable media/content synchronization for local-first WordPress and Playground folders.

## Current Native Slice

Native scanner-style content blocks now match Syncthing's `lib/scanner/blocks_test.go`
fixtures for empty files, SHA-256 block hashes, exact coverage checks, block-list
hashing, optional hash validation, and upstream per-file block size selection.
Protocol version vectors now cover update/merge/compare ordering, and FileInfo
conflict decisions now cover invalid flag handling, block lineage conflicts,
winner ordering, and tombstone construction. FileInfo equivalence now maps a
focused slice of upstream `lib/protocol/bep_fileinfo_test.go`: block-list
equality shortcuts, local invalid flag equivalence, permission/block ignore
options, symlink target checks, modification time windows, and Unix ownership
matching by numeric IDs or resolved names. Protocol validation now maps focused
upstream `protocol_test.go` and `wireformat.go` behavior: filename canonicality,
request maximum-size and traversal rejection, FileInfo index consistency checks,
and slash/NFC normalization for outgoing request and index update names. The
BEP wire slice now maps upstream `bep_hello.go`, `bep_hello_test.go`,
`bep_request_response.go`, `bep_clusterconfig.go`, `proto/bep/bep.proto`, and
`protocol.go` behavior: Hello magic/length/protobuf frames, old/unknown hello
magic rejection, Request/Response proto3 field numbers, ClusterConfig folder
and device field numbers, Header message type/compression fields, and
uncompressed post-auth frame lengths. The compressed BEP slice now maps focused
upstream `TestWriteCompressed`, `TestLZ4Compression`, and
`TestLZ4CompressionUpdate` behavior: raw LZ4 blocks carry the upstream
big-endian uncompressed-length prefix, the Syncthing 1.18.6 compatibility
fixture decodes and re-encodes exactly, LZ4 post-auth frames decompress before
protobuf decoding, compression is skipped below the 128-byte threshold, metadata
mode leaves responses uncompressed, and incompressible payloads fall back to
uncompressed frames. The control-message slice now maps focused upstream
`proto/bep/bep.proto`, `protocol.go`, and `protocol_test.go` behavior for Ping
and Close messages: Ping frames use an empty protobuf payload with BEP message
type 6, Close frames preserve the reason string in protobuf field 1 with BEP
message type 7, close reasons participate in the normal metadata compression
decision path, and the static inventory counted eight upstream ping/close and
close-race test functions without hydrating the full checkout. The upstream
denominator is still a static inventory rather
than runner parity, but this slice also counted 658 static Go test/benchmark
entry points across 141 upstream `_test.go` files. The raw request/response
exchange slice now maps focused upstream `protocol.go`, `errors.go`,
`protocol_test.go`, `model.go`, and `proto/bep/bep.proto` behavior: outbound
requests receive monotonically increasing IDs, matching responses resolve only
pending requests, late unknown responses are ignored, no-error/generic/
no-such-file/invalid-file codes map to the same error classes as upstream,
connection close drains pending requests as closed, and dispatcher request
size/filename validation rejects invalid messages before request handling. The
device identity slice
now maps focused upstream `deviceid.go`, `deviceid_test.go`, `luhn.go`, and
`luhn_test.go` behavior: raw certificate bytes hash to a 32-byte device ID,
canonical IDs use Syncthing's base32 plus four Luhn32 check digits and
seven-character chunks, old no-check-digit IDs and copy/paste variants with
spaces, lowercase, or common typo digits parse back to the same canonical form,
short IDs expose the first seven base32 characters, and malformed lengths,
base32 data, or check digits are rejected before a peer is accepted. The
Index/IndexUpdate slice
now maps focused upstream `bep_index_updates.go`, `bep_fileinfo.go`,
`vector.go`, and `proto/bep/bep.proto` behavior: Index and IndexUpdate frame
types, repeated FileInfo payloads, last/previous sequence fields, block
offset/size/hash payloads, sorted version vector counters, invalid flag
projection from local flags, no-permission and deleted bits, modified_by, raw
block size, symlink targets, blocks_hash and previous_blocks_hash, and Unix
owner/group UID/GID platform data. The DownloadProgress slice now maps focused
upstream `bep_download_progress.go`, `proto/bep/bep.proto`,
`TestUnmarshalFDPUv16v17`, and `lib/model/devicedownloadstate.go` behavior:
message type 5 frames, folder/update payloads, append and forget update types,
version vector payloads, unpacked repeated block indexes including index zero,
block size byte accounting with the upstream minimum-block fallback, same-version
append accumulation, version replacement, and version-matched forget deletion.
The old v0.14.16/v0.14.17 update fixtures are decoded without rejecting legacy
enum/string/index shapes that Go protobuf unmarshalling accepts. The outgoing
sent-download slice now maps focused upstream `sentdownloadstate.go`,
`progressemitter.go`, `progressemitter_test.go`, and `sharedpullerstate.go`
behavior: active puller filtering by folder, file kind, and `TempIndexMinBlocks`;
no update for new files with no temporary blocks; new-block-only append deltas
when `AvailableUpdated` advances; no update when block lists change without the
timestamp invariant; forget+append replacement when the version or puller
creation identity changes, including empty append updates that clear old
temporary availability; and versioned forgets for completed or errored pulls.
The progress-emitter boundary now maps the adjacent upstream
`ProgressEmitter.computeProgressUpdates`, temporary-index subscribe/unsubscribe,
`clearLocked`, `Deregister`, `BytesCompleted`, and `sharedPullerState.Progress`
semantics: per-device folder subscriptions receive grouped DownloadProgress
messages; disconnected devices have sent state discarded without forget traffic;
unshared folders are silently removed from sent state; disabling the emitter
returns cleanup forget updates before clearing state; and event-style progress
summaries expose Syncthing's block-to-byte estimate for WordPress import UI.
The scheduler/connection boundary now maps the upstream `ProgressEmitter.Serve`
timer gate and `progressUpdate.send` ordering: no event is emitted until the
registry count or latest puller update changes, active pulls schedule the next
interval, idle state does not, unchanged block lists are not retried, deregister
cleanup emits a final forget and stops the interval, and DownloadProgress
messages are converted to native BEP wire frames by a connection adapter after
the local sent-state has already advanced.
The inbound temporary-block slice now maps the adjacent upstream
`model.DownloadProgress`, `blockAvailabilityFromTemporaryRLocked`, and
`RequestGlobal` boundary: DownloadProgress messages from unknown or unshared
folders are ignored, accepted updates emit RemoteDownloadProgress-style
per-file block-count summaries, availability includes `fromTemporary`
candidates from shared devices with matching advertised file versions, and a
planned WordPress media block request sets `Request.fromTemporary` when the
only source is a peer's temporary file.
The inbound request-serving slice now maps focused upstream `model.Request`,
`readOffsetIntoBuf`, `scanner.Validate`, `fs.IsInternal`, and `fs.TempName`
behavior: shared devices can read regular file ranges, `fromTemporary` requests
try the `.syncthing.<basename>.tmp` sibling first, the temporary bytes must
match the requested SHA-256 block hash, stale or short temporary reads fall
back to the finalized file, final-file hash mismatches return no-such-file, and
internal/traversal/symlink paths are rejected before disk reads.
The request-serving boundary now also maps upstream ignore and receive-encrypted
guards from `model.Request` and `lib/ignore`: explicit ignored matches are
rejected with invalid-file before any temporary or finalized bytes are served,
basic `.stignore` prefixes such as `(?i)`, `(?d)`, and unrooted/rooted glob
expansion produce explicit match results, included ignore snippets are loaded
relative to the current ignore file, `#escape=` directives can switch the
escape character before local patterns, escaped glob metacharacters/bracket
ranges/brace alternatives follow focused upstream `gobwas/glob` behavior, and
receive-encrypted folders skip finalized-file hash validation for encrypted
hash tokens while still requiring temporary bytes to validate before they can
be served.
The receive-encrypted envelope slice maps focused upstream
`lib/protocol/encryption.go`, `lib/protocol/encryption_test.go`,
`lib/model/sharedpullerstate.go`, and `lib/model/folder_sendrecv.go`
boundaries without claiming full crypto parity: encrypted requests add the
40-byte nonce/tag overhead per block and pad small plaintext requests to the
upstream 1024-byte minimum, inbound encrypted request geometry subtracts that
overhead before dispatch, synthetic encrypted parent directories are detected
with the upstream `.syncthing-enc` and 200-character component rules, and
receive-encrypted file trailers append a FileInfo wire payload followed by a
big-endian payload length.
The encrypted request/response data slice now maps the adjacent
`encryptedConnection.Request` and `encryptedModel.Request` behavior: outgoing
trusted requests derive the encrypted name/hash token, inflate offsets by the
per-block 40-byte overhead, request at least 1024 plaintext bytes plus
nonce/tag overhead from the untrusted peer, decrypt returned bytes, and trim the
padding back to the original requested WordPress media block size. Inbound
receive-encrypted requests decrypt the name/hash/geometry before model serving,
then pad short plaintext responses to 1024 bytes before XChaCha20-Poly1305
encryption.
The encrypted request-serving integration now maps the adjacent
`encryptedModel.Request` to the native disk boundary: an untrusted encrypted BEP
request is decrypted to the plaintext WordPress media name, hash, offset, and
size; `RequestServer` performs the normal shared-folder, path, and hash checks;
successful bytes are padded and encrypted for the peer; and stale-hash errors
remain no-such-file responses without encrypted payload data.
The cluster-config encryption consistency slice maps focused upstream
`model.ccCheckEncryption`, `TestCcCheckEncryption`, and adjacent
`TestClusterConfigEncrypted` behavior: untrusted devices cannot share plain
folders, both advertised encryption tokens are impossible, local
receive-encrypted plus remote encrypted configuration is impossible, token/plain
configuration mismatches return the upstream error boundaries, receive-encrypted
folders adopt a cluster-advertised token before requesting a cluster-config
resend, stored-token mismatches surface the upstream different-password error,
and remote encrypted peers can derive and compare exact `PasswordToken` bytes
from configured passwords. The password-token slice maps
`lib/protocol/encryption.go` and `TestKeyDerivation`: folder keys are derived
with Syncthing's scrypt parameters (`N=32768`, `r=8`, `p=1`, `keyLen=32`) over
`knownBytes(folderID)`, then encrypted with native AES-CMAC-SIV using the same
zero-length AEAD nonce associated-data boundary as upstream. A temporary Go
oracle produced fixed fixture bytes only; the PHP implementation does not call
Go at runtime. During probing, `sudo -n dnf install -y php-sodium` installed
`php-sodium`/`libsodium`, but this PHP build exposes only high-level scrypt, so
the exact N/r/p KDF is implemented in lane PHP rather than delegated to sodium.
The deterministic receive-encrypted transform slice now maps the adjacent
`encryptName`, `decryptName`, `KeyGenerator.FileKey`, `encryptBlockHash`, and
legacy block-hash fallback boundaries from `lib/protocol/encryption.go` and
`lib/protocol/encryption_test.go`: filenames are AES-SIV encrypted, encoded as
unpadded base32-hex, and slashified with `.syncthing-enc` synthetic parent
components; invalid encrypted-name paths and invalid base32 quanta fail before
plaintext is exposed; per-file keys use HKDF-SHA256 over `folderKey ||
filename` with salt `syncthing`; and encrypted block hash tokens are stable for
the same hash at the same offset while differing for the same hash at a
different offset because the big-endian offset is associated data.
The encrypted metadata slice now maps upstream `encryptBytes`, `DecryptBytes`,
`encryptFileInfo`, `DecryptFileInfo`, `TestEnDecryptBytes`,
`TestEnDecryptFileInfo`, and `TestEncryptedFileInfoConsistency`: XChaCha20-
Poly1305 payloads use Syncthing's 24-byte nonce prefix plus 16-byte tag
overhead, the upstream encrypted `hello world` fixture decrypts with the
derived file key, FileInfo protobuf field 19 carries encrypted metadata, fake
encrypted FileInfo wrappers expose encrypted names, 0644 permissions, the
upstream fixed modified time, sequence numbers, deterministic fake versions,
padded encrypted block sizes, encrypted byte offsets, and offset-bound block
hash tokens, and decryption restores the plaintext metadata while preserving
the untrusted peer's sequence number.
The receive-encrypted finalization slice now maps the adjacent upstream
`sharedPullerState.finalizeEncrypted`, `writeEncryptionTrailer`,
`prepareFileInfoForIndex`, and decrypt-tool trailer verification boundaries:
completed encrypted file bytes get a FileInfo wire trailer written at the
encrypted data size, the locally indexed size includes that trailer, outbound
index metadata subtracts the host-local trailer size, and verification reloads
the trailer, decrypts FileInfo metadata, decrypts every encrypted block, trims
only the final padded block, and validates plaintext SHA-256 block hashes before
recovering the WordPress media bytes.
The encrypted index collection slice maps upstream `encryptedConnection.Index`,
`encryptedConnection.IndexUpdate`, `encryptedModel.Index`,
`encryptedModel.IndexUpdate`, `encryptFileInfos`, and `decryptFileInfos`:
outgoing Index and IndexUpdate payloads normalize WordPress paths before
encrypting every FileInfo wrapper for the untrusted peer, while incoming
encrypted collections decrypt those wrappers and keep folder, last sequence,
previous sequence, and peer-controlled FileInfo sequence metadata intact.
The encrypted DownloadProgress slice maps upstream
`encryptedConnection.DownloadProgress` and `encryptedModel.DownloadProgress`:
temporary-block progress for folders with registered encryption keys is
intentionally dropped instead of translated to encrypted temporary files, while
plain WordPress media folders still forward unchanged to the normal progress
connection and model-level temporary-download state.
The receive-encrypted scan cleanup slice maps upstream `folder.go` and
`folder_sendrecv.go` boundaries for synthetic encrypted parent directories:
missing `.syncthing-enc` parent trees are created for pulled encrypted files
without scheduling a follow-up scan, scanned synthetic parent directories are
never written into the local index, and empty synthetic parent directories are
removed while non-empty parents remain on disk as container paths only.

The example in `examples/wordpress-media-resume.php` shows how WordPress or
Playground import tooling can resume a partially synchronized upload by trusting
only blocks whose hashes still match the local bytes, then continuing at the
next byte offset. The FileInfo slice gives that same workflow a native boundary
for concurrent media edits, deleted uploads, and remote-invalid entries before a
higher-level sync planner chooses the final WordPress object state.
`examples/wordpress-fileinfo-equivalence.php` shows the adjacent decision:
scanner-only metadata noise can be treated as equivalent while actual media byte
changes still force a sync decision.
`examples/wordpress-index-validation.php` shows a WordPress media index update
being normalized to Syncthing wire paths and checked before dispatch while a
traversal request for `wp-config.php` is rejected.
`examples/wordpress-bep-request-frame.php` emits a native BEP Request frame for
the next missing WordPress media block, then decodes it back to prove the
folder, wire path, block number, byte range, and SHA-256 block hash survive the
wire boundary without shelling out.
`examples/wordpress-request-response-exchange.php` tracks the next layer of
that request lifecycle: a stale media block response maps to no-such-file for a
retry, the retry response completes with restored bytes, and a disconnected
peer drains an outstanding WordPress media request as connection-closed.
`examples/wordpress-cluster-config.php` advertises a WordPress media folder and
Playground importer device as a native BEP ClusterConfig frame, then decodes it
back to prove the folder label, device addresses, compression preference, max
sequence, and frame type survive the wire boundary.
`examples/wordpress-compressed-metadata-frame.php` sends a larger WordPress
media ClusterConfig through metadata compression and decodes it back, showing
native LZ4 reduces repeated folder/device metadata while preserving the same
BEP message type and protobuf payload semantics.
`examples/wordpress-close-frame.php` emits a native BEP Close frame with a
WordPress media maintenance reason and decodes it back so import tooling can
notify a peer before intentionally disconnecting.
`examples/wordpress-device-id.php` derives a Syncthing device ID from raw
WordPress Playground peer certificate bytes, accepts a lowercase space-separated
copy from an admin surface, and exposes the canonical and short peer IDs used
for pairing.
`examples/wordpress-index-update-frame.php` sends a native BEP IndexUpdate for
a WordPress media upload, preserving normalized wire paths, FileInfo sequence
metadata, version counters, block hashes, and aggregate blocks_hash values
across the protobuf and post-auth frame boundary.
`examples/wordpress-download-progress-frame.php` sends a native BEP
DownloadProgress append update for a partially downloaded WordPress media file,
decodes it back, applies it to the remote temporary-download state, and shows
that the advertised temporary block and byte count can be used before a later
forget update clears the remote state.
`examples/wordpress-sent-download-progress.php` shows the inverse outgoing
state: a WordPress media pull first advertises temporary blocks 0 and 1, then
emits only the newly available block 4, and finally sends the versioned forget
that clears the remote peer's temporary availability when the pull completes or
errors.
`examples/wordpress-progress-emitter.php` coordinates two subscribed WordPress
peers, grouping native DownloadProgress frames per device/folder, emitting only
the newly available media block on the second pass, and exposing completed-byte
progress for an import status surface.
`examples/wordpress-progress-scheduler.php` wraps that emitter in the native
scheduler and wire connection adapter, showing an idle timer pass, an initial
WordPress media temporary-block advertisement, and a later delta frame that
contains only the newly available block.
`examples/wordpress-inbound-temporary-request.php` applies a remote peer's
temporary-block advertisement to a shared WordPress media folder, emits the
same event summary the model would expose, and plans a native BEP Request frame
with `fromTemporary` set for the advertised media block.
`examples/wordpress-temporary-request-server.php` serves the other side of that
flow: a WordPress media restore request arrives with `fromTemporary` set, stale
temporary bytes are rejected by the block hash, the finalized media file is
served as a native BEP Response frame, and any restore error is surfaced as a
response code rather than a shell command failure.
`examples/wordpress-encrypted-media-request.php` adds the encrypted-folder
variant: a private export path is blocked by a native ignore match, while a
receive-encrypted media object can be restored from finalized encrypted bytes
even when the request hash is an opaque encrypted token instead of a SHA-256
digest.
`examples/wordpress-ignore-include-escape.php` shows a WordPress media folder
loading a shared private-export ignore snippet via `#include`, then using a
custom `#escape=|` rule to block a literal filename containing `*` without
blocking an ordinary public media request.
`examples/wordpress-receive-encrypted-envelope.php` shows the adjacent
untrusted-peer boundary: a WordPress media block request is reshaped into an
encrypted-name request with padded size, per-block overhead, a deterministic
encrypted filename, and an offset-bound block-hash token, while the encrypted
file bytes carry a recoverable normalized FileInfo trailer for later metadata
reconstruction. It also wraps the WordPress media FileInfo into Syncthing's
encrypted metadata field 19, showing the fake untrusted-peer name, fake version,
encrypted size, block size, metadata byte count, decrypted plaintext name, and a
full encrypted IndexUpdate collection round trip. The same example now includes
an encrypted response block, showing 1024-byte padded plaintext, 40-byte
nonce/tag overhead, and trusted-side trimming back to the requested media block.
`examples/wordpress-encrypted-request-server.php` runs that encrypted request
through the native request server: the encrypted BEP request is decoded,
plaintext media bytes are served only when the decrypted hash matches disk, the
wire response stays encrypted for the untrusted peer, and a stale encrypted hash
token becomes a no-such-file response.
`examples/wordpress-receive-encrypted-finalization.php` shows the completed
receive-encrypted file boundary for a private WordPress export: native encrypted
block bytes are finalized with a metadata trailer, the local index keeps the
trailer bytes while the remote index size strips them, and verification rejects
extra bytes before the trailer before recovering the plaintext media payload and
block hash.
`examples/wordpress-encrypted-download-progress.php` shows why receive-encrypted
private media folders do not advertise or accept temporary-block progress:
encrypted-folder progress is suppressed before a BEP frame or remote progress
event is created, while a plain WordPress media folder still emits a message
type 5 frame and updates temporary block counts.
`examples/wordpress-encryption-consistency.php` shows the cluster-config
boundary for that same WordPress media folder: a plain untrusted peer is
rejected, a receive-encrypted peer's real Syncthing password token is accepted
and marked for cluster-config resend, and a stale local token produces the
upstream different-password error.
`examples/wordpress-receive-encrypted-parent-cleanup.php` shows the scanned
parent-directory boundary: a private WordPress media object creates its
synthetic encrypted parent path without a scan event, the non-empty parent is
not indexed, and an abandoned empty synthetic parent is removed.

## Next Task

Broaden encrypted request serving into temporary-source encrypted-file reads and
raw connection request/response error mapping, then reassess whether a bounded
upstream Go package runner is affordable.
