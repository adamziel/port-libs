<?php

declare(strict_types=1);

$rawTarget = 'ABCDEFABCDEFABCDEFABCDEFABCDEFABCDEFABCD';
$target = strtolower($rawTarget);

$body = "object {$rawTarget}\n"
    . "type commit\n"
    . "tag wp-release-2026.05-signed\n"
    . "tagger WordPress Release Bot <release@example.test> 1770000000 +0000\n"
    . "\n"
    . "Release WordPress 2026.05 deployment bundle\n\n"
    . "Includes block templates and plugin packages.\n"
    . "-----BEGIN PGP SIGNATURE-----\n"
    . "wp-release-signature\n"
    . "-----END PGP SIGNATURE-----";

return [
    'tagBody' => $body,
    'draftReleaseName' => 'WordPress Export: v2026.05? beta.lock',
    'expectedSanitizedDraftReleaseName' => 'WordPress-Export--v2026.05--beta',
    'expectedSanitizedDraftReleaseTarget' => $target,
    'expectedOwnedReleaseTarget' => $target,
    'expectedName' => 'wp-release-2026.05-signed',
    'expectedTarget' => $target,
    'expectedRawTarget' => $rawTarget,
    'expectedKind' => 'commit',
    'expectedTagger' => 'WordPress Release Bot',
    'expectedMessage' => "Release WordPress 2026.05 deployment bundle\n\nIncludes block templates and plugin packages.",
    'expectedSignature' => "-----BEGIN PGP SIGNATURE-----\nwp-release-signature\n-----END PGP SIGNATURE-----",
    'expectedStorageSha1' => sha1($body),
    'expectedObjectSha1' => sha1('tag ' . strlen($body) . "\0" . $body),
    'expectedSize' => strlen($body),
    'wordpressUse' => 'A WordPress deployment tool can reject invalid draft release names, sanitize them with Gitoxide tag-name byte rules, then inspect, roundtrip, and hash a signed release tag for provenance without invoking git tag or git cat-file.',
];
