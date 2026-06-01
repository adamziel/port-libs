<?php

declare(strict_types=1);

$main = '73a6868963993a3328e7d8fe94e5a6ac5078a944';
$installed = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
$packData = 'PACK' . pack('N', 2) . pack('N', 1) . 'wordpress-blobless-pack' . hex2bin('3b4b12f4cf6262d95e165b4517d71d0b9df20789');
$sha256Installed = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
$sha256Main = 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210';
$sha256Shallow = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
$sha256PackTrailer = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
$sha256PackData = 'PACK' . pack('N', 2) . pack('N', 1) . 'wordpress-sha256-pack' . hex2bin($sha256PackTrailer);
$unicodeSpace = "\xE2\x80\x83";

$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$delimiter = '0001';
$flush = '0000';
$smartHttpUploadPackBody = $packet("acknowledgments\n")
    . $packet("ACK {$installed}\n")
    . $packet("ACK {$main}\n")
    . $packet("ready\n")
    . $delimiter
    . $packet("packfile\n")
    . $packet("\x02Counting objects: 100% (1/1)\rCounting objects: 100% (1/1), done.\n")
    . $packet("\x01" . $packData)
    . $flush;

return [
    'sidebandAll' => true,
    'response' => $packet("\x02remote: preparing WordPress blobless pack\n")
        . $packet("\x01acknowledgments\n")
        . $packet("\x01ACK {$installed} common\n")
        . $packet("\x01ready\n")
        . $delimiter
        . $packet("\x01shallow-info\n")
        . $packet("\x01shallow {$main}\n")
        . $delimiter
        . $packet("\x01wanted-refs\n")
        . $packet("\x01{$main} refs/heads/main\n")
        . $delimiter
        . $packet("\x01packfile\n")
        . $packet("\x01")
        . $packet("\x02Enumerating objects: 1, done.\n")
        . $packet("\x01" . $packData)
        . $flush,
    'suffixlessAckResponse' => $packet("acknowledgments\n")
        . $packet("ACK {$installed}\n")
        . $packet("ACK {$main}\n")
        . $packet("ready\n")
        . $delimiter
        . $packet("packfile\n")
        . $packet("\x02Counting objects: 100% (1/1)\rCounting objects: 100% (1/1), done.\n")
        . $packet("\x01" . $packData)
        . $flush,
    'refInWantResponse' => $packet("wanted-refs\n")
        . $packet("{$main} refs/heads/main\n")
        . $delimiter
        . $packet("packfile\n")
        . $packet("\x01" . $packData)
        . $flush,
    'sha256Response' => $packet("acknowledgments\n")
        . $packet("ACK {$sha256Installed}\n")
        . $packet("ACK {$sha256Main} common\n")
        . $packet("ready\n")
        . $delimiter
        . $packet("shallow-info\n")
        . $packet("shallow {$sha256Shallow}\n")
        . $delimiter
        . $packet("wanted-refs\n")
        . $packet("{$sha256Main} refs/heads/main\n")
        . $delimiter
        . $packet("packfile\n")
        . $packet("\x02Resolving deltas: 100% (1/1)\n")
        . $packet("\x01" . $sha256PackData)
        . $flush,
    'cloneExchangeResponse' => $packet("version 2\n")
        . $packet("agent=port-libs/0.1\n")
        . $packet("ls-refs\n")
        . $packet("fetch=shallow\n")
        . $packet("object-format=sha1\n")
        . $flush
        . $packet("{$main} HEAD symref-target:refs/heads/main\n")
        . $packet("{$main} refs/heads/main\n")
        . $flush
        . $packet("packfile\n")
        . $packet("\x02Enumerating objects: 1, done.\n")
        . $packet("\x01" . $packData)
        . $flush,
    'rawUploadPackErrorResponse' => $packet('ERR raw WordPress fetch failure' . "\n"),
    'emptyErrorSidebandResponse' => $packet("packfile\n")
        . $packet("\x03")
        . $packet("\x01" . $packData)
        . $flush,
    'truncatedPackResponse' => $packet("packfile\n")
        . $packet("\x02Counting objects: 100% (1/1)\r")
        . $packet("\x01" . $packData),
    'overflowProgressResponse' => $packet("packfile\n")
        . $packet("\x02Counting objects: 4294967295% (4/10)\r")
        . $packet("\x02Counting objects: 4294967296% (5/10)\r")
        . $packet("\x01" . $packData)
        . $flush,
    'progressCancelResponse' => $packet("packfile\n")
        . $packet("\x02remote: WordPress deployment fetch can be cancelled\n")
        . $packet("\x01" . $packData)
        . $flush,
    'responseEndNoPackResponse' => $packet("acknowledgments\n")
        . $packet("NAK\n")
        . '0002',
    'responseEndPackResponse' => $packet("acknowledgments\n")
        . $packet("ACK {$installed}\n")
        . $delimiter
        . $packet("packfile\n")
        . $packet("\x02Counting objects: 100% (1/1)\n")
        . $packet("\x01" . $packData)
        . '0002',
    'sidebandAllResponseEndResponse' => $packet("\x02remote: response-end aware negotiation\n")
        . $packet("\x01packfile\n")
        . $packet("\x03remote: deployment warning before pack\n")
        . $packet("\x01" . $packData)
        . '0002',
    'trailingWhitespaceResponse' => $packet("\x01acknowledgments \t\r\n")
        . $packet("\x01ACK {$installed} common \t\r\n")
        . $packet("\x01ready \t\r\n")
        . $delimiter
        . $packet("\x01shallow-info \t\r\n")
        . $packet("\x01shallow {$main} \t\r\n")
        . $delimiter
        . $packet("\x01wanted-refs \t\r\n")
        . $packet("\x01{$main} refs/heads/main \t\r\n")
        . $delimiter
        . $packet("\x01packfile \t\r\n")
        . $packet("\x02Counting objects: 100% (1/1) \t\r\n")
        . $packet("\x01" . $packData)
        . $flush,
    'unicodeWhitespaceResponse' => $packet("\x01acknowledgments{$unicodeSpace}\n")
        . $packet("\x01ACK {$installed} common{$unicodeSpace}\n")
        . $packet("\x01ready{$unicodeSpace}\n")
        . $delimiter
        . $packet("\x01shallow-info{$unicodeSpace}\n")
        . $packet("\x01shallow {$main}{$unicodeSpace}\n")
        . $delimiter
        . $packet("\x01wanted-refs{$unicodeSpace}\n")
        . $packet("\x01{$main} refs/heads/main{$unicodeSpace}\n")
        . $delimiter
        . $packet("\x01packfile{$unicodeSpace}\n")
        . $packet("\x02Counting objects: 100% (1/1)\n")
        . $packet("\x01" . $packData)
        . $flush,
    'delimiterPackResponse' => $packet("packfile\n")
        . $packet("\x02Counting objects: 100% (1/1)\n")
        . $packet("\x01" . $packData)
        . $delimiter,
    'smartHttpUploadPackResponse' => "HTTP/1.1 200 OK\r\n"
        . "Content-Type: application/x-git-upload-pack-result\r\n"
        . 'Content-Length: ' . strlen($smartHttpUploadPackBody) . "\r\n"
        . "Cache-Control: no-cache\r\n"
        . "\r\n"
        . $smartHttpUploadPackBody,
    'objects' => [
        'main' => $main,
        'installed' => $installed,
    ],
    'objectsSha256' => [
        'main' => $sha256Main,
        'installed' => $sha256Installed,
        'shallow' => $sha256Shallow,
    ],
    'packData' => $packData,
    'sha256PackData' => $sha256PackData,
    'sha256PackTrailer' => $sha256PackTrailer,
    'packetLineMaxBytes' => 65520,
    'wordpressUse' => 'A PHP deployment tool can parse protocol v2 sideband-all fetch response sections, confirm the wanted WordPress branch object, collect shallow boundary updates, surface remote progress, and hand channel-1 pack bytes to the object database layer.',
    'packetLineBoundUse' => 'Fetch response packet-lines are bounded to Gitoxide gix-packetline 64k framing before sideband decoding, so an oversized remote payload cannot be interpreted as pack or progress data.',
    'rawUploadPackErrorUse' => 'Raw upload-pack ERR pkt-lines are surfaced before sideband decoding, so WordPress deployment fetch diagnostics report the server failure text instead of a misleading sideband channel error.',
    'emptyErrorSidebandUse' => 'Empty channel-3 sideband keepalive/error packets are ignored instead of creating a blank deployment error, matching Gitoxide remote-progress handling.',
    'truncatedPackUse' => 'A truncated protocol v2 sideband pack response without a flush is rejected before WordPress deployment tooling can import a partial pack.',
    'overflowProgressUse' => 'Remote progress percentages larger than Gitoxide u32 progress bounds are ignored while step and maximum counters are retained for WordPress deployment diagnostics.',
    'progressCancelUse' => 'A WordPress deployment fetch can abort while reading sideband progress, matching Gitoxide sideband reader interruption behavior before pack bytes are imported.',
    'responseEndUse' => 'Stateless protocol v2 fetch responses can end with a response-end packet after acknowledgements or sidebanded pack bytes, so WordPress deployment tooling does not require a flush-only terminator.',
    'delimiterPackUse' => 'Protocol v2 sideband readers preserve delimiter stop-packet state after pack bytes, so WordPress deployment tooling can distinguish a section boundary from a flush-only response end.',
    'suffixlessAckUse' => 'Suffixless protocol v2 ACK lines are treated as common acknowledgements before the packfile, matching Gitoxide fetch.response fixture behavior for deployment fetch negotiation.',
    'refInWantUse' => 'A WordPress deployment fetch using ref-in-want can parse the wanted-refs section and still hand the following sideband pack bytes to object import without requiring a separate ls-refs advertisement.',
    'cloneExchangeUse' => 'A WordPress deployment fetch can parse a persistent protocol v2 upload-pack exchange from capability advertisement through ls-refs and the following sidebanded fetch response before importing pack bytes.',
    'cloneExchangeProgressHandlerUse' => 'A WordPress deployment fetch parsing a full protocol v2 exchange can stream sideband progress through the same caller cancellation handler used by lower-level fetch response readers.',
    'sha256ObjectFormatUse' => 'A WordPress deployment fetch from a SHA-256 object-format repository can parse 64-hex acknowledgements, shallow updates, and wanted refs before preserving sidebanded pack bytes.',
    'smartHttpUploadPackUse' => 'A WordPress deployment fetch can unwrap a smart HTTP upload-pack result response, validate the upload-pack result content type and length, then parse the sidebanded protocol v2 fetch response without invoking git.',
    'trailingWhitespaceUse' => 'Protocol v2 fetch response section lines with trailing spaces or tabs are trimmed like Gitoxide before WordPress deployment tooling validates ACKs, shallow updates, wanted refs, and the following sideband pack bytes.',
    'unicodeWhitespaceUse' => 'Protocol v2 fetch response sideband-all section lines with trailing UTF-8 whitespace are trimmed like Gitoxide before WordPress deployment tooling validates ACKs, shallow updates, wanted refs, and pack bytes.',
];
