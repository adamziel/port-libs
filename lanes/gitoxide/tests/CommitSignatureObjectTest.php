<?php

declare(strict_types=1);

use PortLibs\Gitoxide\Commit;
use PortLibs\Gitoxide\GitObject;

$signedSingleLine = "tree 00fc39317701176e326974ce44f5bd545a32ec0b\n"
    . "parent 09d8d3a12e161a7f6afb522dbe8900a9c09bce06\n"
    . "author Sebastian Thiel <sebastian.thiel@icloud.com> 1592391367 +0800\n"
    . "committer Sebastian Thiel <sebastian.thiel@icloud.com> 1592391367 +0800\n"
    . "gpgsig magic:signature\n"
    . "\n"
    . "update tasks\n";

$messageWithFooter = "tree 25a19c29c5e36884c1ad85d8faf23f1246b7961b\n"
    . "parent 699ae71105dddfcbb9711ed3a92df09e91a04e90\n"
    . "author Kim Altintop <kim@eagain.st> 1631514803 +0200\n"
    . "committer Kim Altintop <kim@eagain.st> 1631514803 +0200\n"
    . "gpgsig -----BEGIN PGP SIGNATURE-----\n"
    . " \n"
    . " iHUEABYIAB0WIQSuZwcGWSQItmusNgR5URpSUCnwXQUCYT7xpAAKCRB5URpSUCnw\n"
    . " XWB3AP9q323HlxnI8MyqszNOeYDwa7Y3yEZaUM2y/IRjz+z4YQEAq0yr1Syt3mrK\n"
    . " OSFCqL2vDm3uStP+vF31f6FnzayhNg0=\n"
    . " =Mhpp\n"
    . " -----END PGP SIGNATURE-----\n"
    . "\n"
    . "test: use gitoxide for link-git-protocol tests\n"
    . "\n"
    . "Showcases commit verification payload extraction.\n"
    . "\n"
    . "Signed-off-by: Sebastian Thiel <sebastian.thiel@icloud.com>\n"
    . "Signed-off-by: Kim Altintop <kim@eagain.st>\n";

$twoMultilineHeaders = "tree a76e23b60ecceed68b5fa4904f677fe20e304b6e\n"
    . "parent 83a196773b8bc6702f49df1eddc848180e350340\n"
    . "parent 8f3d9f354286745c751374f5f1fcafee6b3f3136\n"
    . "author Maxime Ripard <maxime@cerno.tech> 1586848790 +0200\n"
    . "committer Maxime Ripard <maxime@cerno.tech> 1586848790 +0200\n"
    . "mergetag object 8f3d9f354286745c751374f5f1fcafee6b3f3136\n"
    . " type commit\n"
    . " tag v5.7-rc1\n"
    . " tagger Linus Torvalds <torvalds@linux-foundation.org> 1586720165 -0700\n"
    . " \n"
    . " Linux 5.7-rc1\n"
    . " -----BEGIN PGP SIGNATURE-----\n"
    . " \n"
    . " iQFSBAABCAA8FiEEq68RxlopcLEwq+PEeb4+QwBBGIYFAl6TbaUeHHRvcnZhbGRz\n"
    . " QGxpbnV4LWZvdW5kYXRpb24ub3JnAAoJEHm+PkMAQRiGhgkH/iWpiKvosA20HJjC\n"
    . " rBqYeJPxQsgZTuBieWJ+MeVxbpcF7RlM4c+glyvg3QJhHwIEG58dl6LBrQbAyBAR\n"
    . " =whbL\n"
    . " -----END PGP SIGNATURE-----\n"
    . "gpgsig -----BEGIN PGP SIGNATURE-----\n"
    . " \n"
    . " iHUEABYIAB0WIQRcEzekXsqa64kGDp7j7w1vZxhRxQUCXpVkIwAKCRDj7w1vZxhR\n"
    . " xR+MAQDRKtTCM649g2QANnUo5O8XRKPQNuY+ARF7z0uwzZCqigD/ZjwYxRTBv9qE\n"
    . " mJ+4jgVi2U1wPlvwbzkWGvzxxzyJOAo=\n"
    . " =QkyO\n"
    . " -----END PGP SIGNATURE-----\n"
    . "\n"
    . "Merge v5.7-rc1 into drm-misc-fixes\n"
    . "\n"
    . "Signed-off-by: Maxime Ripard <maxime@cerno.tech>\n";

$sha256Signed = "tree 0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef\n"
    . "author Ada Lovelace <ada@example.com> 1710000000 +0000\n"
    . "committer Grace Hopper <grace@example.com> 1710003600 -0230\n"
    . "gpgsig -----BEGIN SSH SIGNATURE-----\n"
    . " U1NIU0lHAAAAAQAAADMAAAALc3NoLWVkMjU1MTkAAAAgZXhhbXBsZS1zaGEyNTY=\n"
    . " -----END SSH SIGNATURE-----\n"
    . "\n"
    . "sha256 signed import\n";

$stripFirstGpgsigHeader = static function (string $body): string {
    $offset = strpos($body, "\ngpgsig ");
    $start = $offset === false
        ? (str_starts_with($body, 'gpgsig ') ? 0 : false)
        : $offset + 1;
    if ($start === false) {
        throw new RuntimeException('fixture has no gpgsig header');
    }

    $end = strpos($body, "\n", $start);
    if ($end === false) {
        throw new RuntimeException('fixture gpgsig header is not terminated');
    }
    $end++;
    while (substr($body, $end, 1) === ' ') {
        $next = strpos($body, "\n", $end);
        if ($next === false) {
            throw new RuntimeException('fixture gpgsig continuation is not terminated');
        }
        $end = $next + 1;
    }

    return substr($body, 0, $start) . substr($body, $end);
};

return [
    'commit object signature helper mirrors gix object commit signature' => static function (TestRunner $t) use ($signedSingleLine, $messageWithFooter, $stripFirstGpgsigHeader): void {
        $singleObject = new GitObject('commit', $signedSingleLine);
        $single = Commit::signatureForVerificationFromObject($singleObject);
        $singleFromStorage = Commit::signatureForVerificationFromStorageBytes($singleObject->storageBytes());

        $t->same('magic:signature', $single['signature'] ?? null);
        $t->same($stripFirstGpgsigHeader($signedSingleLine), $single['signedData'] ?? null);
        $t->same($single, $singleFromStorage);

        $footerObject = new GitObject('commit', $messageWithFooter);
        $footer = Commit::signatureForVerificationFromObject($footerObject);
        $t->same("-----BEGIN PGP SIGNATURE-----\n\niHUEABYIAB0WIQSuZwcGWSQItmusNgR5URpSUCnwXQUCYT7xpAAKCRB5URpSUCnw\nXWB3AP9q323HlxnI8MyqszNOeYDwa7Y3yEZaUM2y/IRjz+z4YQEAq0yr1Syt3mrK\nOSFCqL2vDm3uStP+vF31f6FnzayhNg0=\n=Mhpp\n-----END PGP SIGNATURE-----\n", $footer['signature'] ?? null);
        $t->same($stripFirstGpgsigHeader($messageWithFooter), $footer['signedData'] ?? null);
        $t->same(false, str_contains($footer['signedData'] ?? '', 'gpgsig '));
        $t->contains('Signed-off-by: Kim Altintop <kim@eagain.st>', $footer['signedData'] ?? '');
    },
    'commit object signature helper preserves prior multiline headers in signed data' => static function (TestRunner $t) use ($twoMultilineHeaders, $stripFirstGpgsigHeader): void {
        $object = new GitObject('commit', $twoMultilineHeaders);
        $signature = Commit::signatureForVerificationFromObject($object);

        $t->same("-----BEGIN PGP SIGNATURE-----\n\niHUEABYIAB0WIQRcEzekXsqa64kGDp7j7w1vZxhRxQUCXpVkIwAKCRDj7w1vZxhR\nxR+MAQDRKtTCM649g2QANnUo5O8XRKPQNuY+ARF7z0uwzZCqigD/ZjwYxRTBv9qE\nmJ+4jgVi2U1wPlvwbzkWGvzxxzyJOAo=\n=QkyO\n-----END PGP SIGNATURE-----\n", $signature['signature'] ?? null);
        $t->same($stripFirstGpgsigHeader($twoMultilineHeaders), $signature['signedData'] ?? null);
        $t->contains('mergetag object 8f3d9f354286745c751374f5f1fcafee6b3f3136', $signature['signedData'] ?? '');
        $t->contains('Linux 5.7-rc1', $signature['signedData'] ?? '');
        $t->same(false, str_contains($signature['signedData'] ?? '', "\ngpgsig "));
    },
    'commit object signature helper rejects non commit objects and unsigned commits' => static function (TestRunner $t) use ($signedSingleLine): void {
        $t->throws(InvalidArgumentException::class, static fn () => Commit::signatureForVerificationFromObject(new GitObject('tag', $signedSingleLine)));
        $t->throws(InvalidArgumentException::class, static fn () => Commit::signatureForVerificationFromStorageBytes((new GitObject('blob', $signedSingleLine))->storageBytes()));

        $unsigned = new GitObject('commit', "tree 0123456789abcdef0123456789abcdef01234567\n"
            . "author A <a@example.test> 1700000000 +0000\n"
            . "committer C <c@example.test> 1700000000 +0000\n"
            . "\n"
            . "unsigned\n");
        $t->same(null, Commit::signatureForVerificationFromObject($unsigned));
        $t->same(null, Commit::signatureForVerificationFromStorageBytes($unsigned->storageBytes()));
    },
    'commit object signature helper honors sha256 object format validation' => static function (TestRunner $t) use ($sha256Signed, $stripFirstGpgsigHeader): void {
        $object = new GitObject('commit', $sha256Signed);
        $signature = Commit::signatureForVerificationFromObject($object, 'sha256');

        $t->same("-----BEGIN SSH SIGNATURE-----\nU1NIU0lHAAAAAQAAADMAAAALc3NoLWVkMjU1MTkAAAAgZXhhbXBsZS1zaGEyNTY=\n-----END SSH SIGNATURE-----\n", $signature['signature'] ?? null);
        $t->same($stripFirstGpgsigHeader($sha256Signed), $signature['signedData'] ?? null);
        $t->same($signature, Commit::signatureForVerificationFromStorageBytes($object->storageBytes(), 'sha256'));
        $t->throws(InvalidArgumentException::class, static fn () => Commit::signatureForVerificationFromObject($object));
    },
];
