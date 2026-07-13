<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitPacketLine;

$assertComplete = static function (TestRunner $t, array $result, int $expectedConsumed, array $expectedLine): void {
    $t->same('complete', $result['status']);
    $t->same($expectedConsumed, $result['bytesConsumed']);
    $t->same($expectedLine, $result['line']);
};

$assertIncomplete = static function (TestRunner $t, array $result, int $expectedMissing): void {
    $t->same('incomplete', $result['status']);
    $t->same($expectedMissing, $result['bytesNeeded']);
};

$assertThrowsMessage = static function (TestRunner $t, string $expectedMessage, callable $callback): void {
    try {
        $callback();
    } catch (Throwable $throwable) {
        $t->same($expectedMessage, $throwable->getMessage());
        return;
    }

    throw new RuntimeException('Expected exception was not thrown');
};

return [
    'gix-packetline decode round_trip trailing_line_feeds_are_removed_explicitly' => static function (TestRunner $t): void {
        $line = GitPacketLine::decode("0006a\n");

        $t->same('a', GitPacketLine::asText($line));
        $t->same("0006a\n", GitPacketLine::encodeText(GitPacketLine::asText($line) ?? ''));
    },
    'gix-packetline decode round_trip all_kinds_of_packetlines' => static function (TestRunner $t) use ($assertComplete): void {
        $cases = [
            [GitPacketLine::responseEndLine(), 4],
            [GitPacketLine::delimiterLine(), 4],
            [GitPacketLine::flushLine(), 4],
            [GitPacketLine::dataLine('hello there'), 15],
        ];

        foreach ($cases as [$line, $bytes]) {
            $assertComplete($t, GitPacketLine::streaming(GitPacketLine::encodeLine($line)), $bytes, $line);
        }
    },
    'gix-packetline decode round_trip error_line' => static function (TestRunner $t): void {
        $line = GitPacketLine::decode(GitPacketLine::encodeError('the error'));

        $t->same('the error', GitPacketLine::checkError($line));
    },
    'gix-packetline decode round_trip side_bands' => static function (TestRunner $t): void {
        foreach ([GitPacketLine::CHANNEL_DATA, GitPacketLine::CHANNEL_ERROR, GitPacketLine::CHANNEL_PROGRESS] as $channel) {
            $band = GitPacketLine::band($channel, 'band data');
            $line = GitPacketLine::decode(GitPacketLine::encodeBandLine($band));

            $t->same($band, GitPacketLine::decodeBand($line));
        }
    },
    'gix-packetline decode streaming flush' => static function (TestRunner $t) use ($assertComplete): void {
        $assertComplete($t, GitPacketLine::streaming('0000someotherstuff'), 4, GitPacketLine::flushLine());
    },
    'gix-packetline decode streaming trailing_line_feeds_are_not_removed_automatically' => static function (TestRunner $t) use ($assertComplete): void {
        $assertComplete($t, GitPacketLine::streaming("0006a\n"), 6, GitPacketLine::dataLine("a\n"));
    },
    'gix-packetline decode streaming ignore_extra_bytes' => static function (TestRunner $t) use ($assertComplete): void {
        $assertComplete($t, GitPacketLine::streaming("0006a\nhello"), 6, GitPacketLine::dataLine("a\n"));
    },
    'gix-packetline decode streaming error_on_oversized_line' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $assertThrowsMessage(
            $t,
            'The data received claims to be larger than the maximum allowed size: got 65535, exceeds 65516',
            static fn () => GitPacketLine::streaming('ffff')
        );
    },
    'gix-packetline decode streaming error_on_error_line' => static function (TestRunner $t) use ($assertComplete): void {
        $line = GitPacketLine::dataLine('ERR the error');

        $assertComplete($t, GitPacketLine::streaming('0011ERR the error-and just ignored because not part of the size'), 17, $line);
        $t->same('the error', GitPacketLine::checkError($line));
    },
    'gix-packetline decode streaming error_on_invalid_hex' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $assertThrowsMessage(
            $t,
            'Failed to decode the first four hex bytes indicating the line length: Invalid character',
            static fn () => GitPacketLine::streaming('fooo')
        );
    },
    'gix-packetline decode streaming error_on_empty_line' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $assertThrowsMessage(
            $t,
            'Received an invalid empty line',
            static fn () => GitPacketLine::streaming('0004')
        );
    },
    'gix-packetline decode streaming incomplete missing_hex_bytes' => static function (TestRunner $t) use ($assertIncomplete): void {
        $assertIncomplete($t, GitPacketLine::streaming('0'), 3);
        $assertIncomplete($t, GitPacketLine::streaming('00'), 2);
    },
    'gix-packetline decode streaming incomplete missing_data_bytes' => static function (TestRunner $t) use ($assertIncomplete): void {
        $assertIncomplete($t, GitPacketLine::streaming('0005'), 1);
        $assertIncomplete($t, GitPacketLine::streaming('0006a'), 1);
    },
    'gix-packetline encode data_to_write binary_and_non_binary' => static function (TestRunner $t): void {
        $binary = GitPacketLine::encodeData("\0");
        $text = GitPacketLine::encodeData("hello world, it works\n");

        $t->same(5, strlen($binary));
        $t->same("0005\0", $binary);
        $t->same(26, strlen($text));
        $t->same("001ahello world, it works\n", $text);
    },
    'gix-packetline encode data_to_write error_if_data_exceeds_limit' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $assertThrowsMessage(
            $t,
            'Cannot encode more than 65516 bytes, got 65517',
            static fn () => GitPacketLine::encodeData(str_repeat("\0", GitPacketLine::MAX_DATA_LENGTH + 1))
        );
    },
    'gix-packetline encode data_to_write error_if_data_is_empty' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $assertThrowsMessage(
            $t,
            'Empty lines are invalid',
            static fn () => GitPacketLine::encodeData('')
        );
    },
    'gix-packetline encode text_to_write always_appends_a_newline' => static function (TestRunner $t): void {
        $single = GitPacketLine::encodeText('a');
        $alreadyNewline = GitPacketLine::encodeText("a\n");

        $t->same(6, strlen($single));
        $t->same("0006a\n", $single);
        $t->same(7, strlen($alreadyNewline));
        $t->same("0007a\n\n", $alreadyNewline);
    },
    'gix-packetline encode error write_line' => static function (TestRunner $t): void {
        $line = GitPacketLine::encodeError('hello error');

        $t->same(19, strlen($line));
        $t->same('0013ERR hello error', $line);
    },
    'gix-packetline encode flush_delim_response_end success_flush_delim_response_end' => static function (TestRunner $t): void {
        $t->same(4, strlen(GitPacketLine::encodeFlush()));
        $t->same('0000', GitPacketLine::encodeFlush());
        $t->same(4, strlen(GitPacketLine::encodeDelimiter()));
        $t->same('0001', GitPacketLine::encodeDelimiter());
        $t->same(4, strlen(GitPacketLine::encodeResponseEnd()));
        $t->same('0002', GitPacketLine::encodeResponseEnd());
    },
    'gix-packetline supplemental invalid length and decode-all coverage' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $t->same([
            GitPacketLine::dataLine('a'),
            GitPacketLine::delimiterLine(),
            GitPacketLine::dataLine('b'),
            GitPacketLine::responseEndLine(),
            GitPacketLine::flushLine(),
        ], GitPacketLine::decodeAll('0005a00010005b00020000'));
        $t->same(null, GitPacketLine::asText(GitPacketLine::flushLine()));
        $t->same(null, GitPacketLine::checkError(GitPacketLine::dataLine('OK still fine')));
        $assertThrowsMessage(
            $t,
            'Received an invalid line of length 3',
            static fn () => GitPacketLine::streaming('0003')
        );
        $assertThrowsMessage(
            $t,
            'attempt to decode a non-side channel line or input was malformed: 9',
            static fn () => GitPacketLine::decodeBand(GitPacketLine::dataLine("\x09bad"))
        );
    },
];
