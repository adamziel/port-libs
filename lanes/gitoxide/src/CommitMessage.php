<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class CommitMessage
{
    private const RECOGNIZED_TRAILER_PREFIXES = [
        'Signed-off-by: ',
        '(cherry picked from commit ',
    ];

    public function __construct(
        public readonly string $title,
        public readonly ?string $body,
    ) {
    }

    public static function fromBytes(string $input): self
    {
        $length = strlen($input);
        for ($position = 0; $position < $length; $position++) {
            $first = self::newlineLengthAt($input, $position);
            if ($first === null) {
                continue;
            }

            $second = self::newlineLengthAt($input, $position + $first);
            if ($second === null) {
                continue;
            }

            $body = substr($input, $position + $first + $second);
            return new self(substr($input, 0, $position), $body === '' ? null : $body);
        }

        return new self($input, null);
    }

    public function summary(): string
    {
        return self::summaryOf($this->title);
    }

    public static function summaryOf(string $message): string
    {
        $message = self::trimGitoxideWhitespace($message);
        $position = strpos($message, "\n");
        if ($position === false) {
            return $message;
        }

        $out = '';
        $previousPosition = null;
        while (true) {
            if ($previousPosition !== null && $previousPosition + 1 === $position) {
                return rtrim($out, " \t\n\f\r");
            }

            $start = $previousPosition === null ? 0 : $previousPosition + 1;
            $line = substr($message, $start, $position - $start);
            $out .= rtrim($line, " \t\n\f\r") . ' ';
            $previousPosition = $position;

            $next = strpos($message, "\n", $position + 1);
            if ($next === false) {
                return $out . substr($message, $position + 1);
            }
            $position = $next;
        }
    }

    public function bodyWithoutTrailers(): ?string
    {
        if ($this->body === null) {
            return null;
        }

        return self::bodyWithoutTrailer($this->body);
    }

    /**
     * @return list<CommitTrailer>
     */
    public function trailers(): array
    {
        if ($this->body === null) {
            return [];
        }

        return self::trailersFromBody($this->body);
    }

    public static function bodyWithoutTrailer(string $body): string
    {
        [$bodyWithoutTrailer] = self::splitBodyAndTrailerCursor($body);

        return $bodyWithoutTrailer;
    }

    /**
     * @return list<CommitTrailer>
     */
    public static function trailersFromBody(string $body): array
    {
        [, $cursor] = self::splitBodyAndTrailerCursor($body);

        return self::parseTrailers($cursor);
    }

    /**
     * @return list<CommitTrailer>
     */
    public function signedOffByTrailers(): array
    {
        return array_values(array_filter($this->trailers(), static fn (CommitTrailer $trailer): bool => $trailer->isSignedOffBy()));
    }

    /**
     * @return list<CommitTrailer>
     */
    public function coAuthoredByTrailers(): array
    {
        return array_values(array_filter($this->trailers(), static fn (CommitTrailer $trailer): bool => $trailer->isCoAuthoredBy()));
    }

    /**
     * @return list<CommitTrailer>
     */
    public function ackedByTrailers(): array
    {
        return array_values(array_filter($this->trailers(), static fn (CommitTrailer $trailer): bool => $trailer->isAckedBy()));
    }

    /**
     * @return list<CommitTrailer>
     */
    public function reviewedByTrailers(): array
    {
        return array_values(array_filter($this->trailers(), static fn (CommitTrailer $trailer): bool => $trailer->isReviewedBy()));
    }

    /**
     * @return list<CommitTrailer>
     */
    public function testedByTrailers(): array
    {
        return array_values(array_filter($this->trailers(), static fn (CommitTrailer $trailer): bool => $trailer->isTestedBy()));
    }

    /**
     * @return list<CommitTrailer>
     */
    public function authorTrailers(): array
    {
        return array_values(array_filter($this->trailers(), static fn (CommitTrailer $trailer): bool => $trailer->isAuthorAttribution()));
    }

    /**
     * @return list<CommitTrailer>
     */
    public function attributionTrailers(): array
    {
        return array_values(array_filter($this->trailers(), static fn (CommitTrailer $trailer): bool => $trailer->isAttribution()));
    }

    private static function newlineLengthAt(string $input, int $position): ?int
    {
        if (substr($input, $position, 2) === "\r\n") {
            return 2;
        }
        if (($input[$position] ?? '') === "\n") {
            return 1;
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitBodyAndTrailerCursor(string $body): array
    {
        $start = self::trailerBlockStart($body);
        if ($start === null) {
            return [$body, ''];
        }

        return [substr($body, 0, $start), substr($body, $start)];
    }

    private static function trailerBlockStart(string $body): ?int
    {
        $lines = self::linesWithOffsets($body);
        $recognizedPrefix = false;
        $trailerLines = 0;
        $nonTrailerLines = 0;
        $possibleContinuationLines = 0;
        $sawNonBlankLine = false;

        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $line = $lines[$index];
            if (self::isBlankLine($line['text'])) {
                if (!$sawNonBlankLine) {
                    continue;
                }

                $nonTrailerLines += $possibleContinuationLines;
                if (!self::acceptsAsTrailerBlock($recognizedPrefix, $trailerLines, $nonTrailerLines)) {
                    return null;
                }

                return $index === 0 ? 0 : $lines[$index - 1]['start'] + strlen($lines[$index - 1]['text']);
            }

            $sawNonBlankLine = true;
            if (self::isRecognizedPrefix($line['text'])) {
                $trailerLines++;
                $possibleContinuationLines = 0;
                $recognizedPrefix = true;
                continue;
            }

            if (self::parseTrailerLine($line['text']) !== null) {
                $trailerLines++;
                $possibleContinuationLines = 0;
                continue;
            }

            if ($line['text'] !== '' && self::isGitoxideWhitespace($line['text'][0])) {
                $possibleContinuationLines++;
                continue;
            }

            $nonTrailerLines += 1 + $possibleContinuationLines;
            $possibleContinuationLines = 0;
        }

        $nonTrailerLines += $possibleContinuationLines;
        return self::acceptsAsTrailerBlock($recognizedPrefix, $trailerLines, $nonTrailerLines) ? 0 : null;
    }

    private static function acceptsAsTrailerBlock(bool $recognizedPrefix, int $trailerLines, int $nonTrailerLines): bool
    {
        return ($trailerLines > 0 && $nonTrailerLines === 0)
            || ($recognizedPrefix && $trailerLines * 3 >= $nonTrailerLines);
    }

    /**
     * @return list<array{text: string, start: int}>
     */
    private static function linesWithOffsets(string $input): array
    {
        $lines = [];
        $offset = 0;
        $length = strlen($input);

        while ($offset < $length) {
            [$raw, $text, $nextOffset] = self::lineWithTerminatorAt($input, $offset);
            $lines[] = ['text' => $text, 'start' => $offset];
            if ($raw === '') {
                break;
            }
            $offset = $nextOffset;
        }

        return $lines;
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    private static function lineWithTerminatorAt(string $input, int $offset): array
    {
        if ($offset >= strlen($input)) {
            return ['', '', $offset];
        }

        $newline = strpos($input, "\n", $offset);
        if ($newline === false) {
            $raw = substr($input, $offset);
            return [$raw, self::trimLineEnding($raw), strlen($input)];
        }

        $raw = substr($input, $offset, $newline - $offset + 1);
        return [$raw, self::trimLineEnding($raw), $newline + 1];
    }

    private static function trimLineEnding(string $line): string
    {
        if (str_ends_with($line, "\n")) {
            $line = substr($line, 0, -1);
            if (str_ends_with($line, "\r")) {
                $line = substr($line, 0, -1);
            }
            return $line;
        }

        if (str_ends_with($line, "\r")) {
            return substr($line, 0, -1);
        }

        return $line;
    }

    /**
     * @return list<CommitTrailer>
     */
    private static function parseTrailers(string $cursor): array
    {
        $trailers = [];
        $offset = 0;
        $length = strlen($cursor);

        while ($offset < $length) {
            [, $text, $nextOffset] = self::lineWithTerminatorAt($cursor, $offset);
            $parsed = self::parseTrailerLine($text);
            if ($parsed === null) {
                $offset = $nextOffset;
                continue;
            }

            [$token, $separator] = $parsed;
            $trailerEnd = $nextOffset;
            $peek = $nextOffset;
            while ($peek < $length) {
                [, $nextText, $afterNext] = self::lineWithTerminatorAt($cursor, $peek);
                if (self::isBlankLine($nextText) || $nextText === '' || !self::isGitoxideWhitespace($nextText[0])) {
                    break;
                }
                $trailerEnd = $afterNext;
                $peek = $afterNext;
            }

            $value = substr($cursor, $offset + $separator + 1, $trailerEnd - ($offset + $separator + 1));
            $trailers[] = new CommitTrailer($token, self::unfoldValue($value));
            $offset = $trailerEnd;
        }

        return $trailers;
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private static function parseTrailerLine(string $line): ?array
    {
        if ($line === '' || ctype_space($line[0])) {
            return null;
        }

        $whitespaceFound = false;
        $length = strlen($line);
        for ($index = 0; $index < $length; $index++) {
            $byte = $line[$index];
            if ($byte === ':') {
                return $index > 0 ? [self::trimGitoxideWhitespace(substr($line, 0, $index)), $index] : null;
            }
            if (!$whitespaceFound && (self::isAsciiAlphaNumeric($byte) || $byte === '-')) {
                continue;
            }
            if ($index !== 0 && ($byte === ' ' || $byte === "\t")) {
                $whitespaceFound = true;
                continue;
            }
            break;
        }

        return null;
    }

    private static function isBlankLine(string $line): bool
    {
        if ($line === '') {
            return true;
        }

        $length = strlen($line);
        for ($index = 0; $index < $length; $index++) {
            if (!self::isGitoxideWhitespace($line[$index])) {
                return false;
            }
        }

        return true;
    }

    private static function isRecognizedPrefix(string $line): bool
    {
        foreach (self::RECOGNIZED_TRAILER_PREFIXES as $prefix) {
            if (str_starts_with($line, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function unfoldValue(string $value): string
    {
        $lines = preg_split('/\r\n|\n|\r/', $value);
        if ($lines === false || $lines === []) {
            return '';
        }
        if (count($lines) === 1) {
            return self::trimGitoxideWhitespace($lines[0]);
        }

        $out = self::trimGitoxideWhitespace((string) array_shift($lines));
        foreach ($lines as $line) {
            $line = self::trimGitoxideWhitespace($line);
            if ($line === '') {
                continue;
            }
            if ($out !== '') {
                $out .= ' ';
            }
            $out .= $line;
        }

        return $out;
    }

    private static function trimGitoxideWhitespace(string $input): string
    {
        return trim($input, " \t\n\v\f\r");
    }

    private static function isGitoxideWhitespace(string $byte): bool
    {
        return $byte === ' '
            || $byte === "\t"
            || $byte === "\n"
            || $byte === "\v"
            || $byte === "\f"
            || $byte === "\r";
    }

    private static function isAsciiAlphaNumeric(string $byte): bool
    {
        $ord = ord($byte);

        return ($ord >= 48 && $ord <= 57)
            || ($ord >= 65 && $ord <= 90)
            || ($ord >= 97 && $ord <= 122);
    }
}
