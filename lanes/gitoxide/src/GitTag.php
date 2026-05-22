<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitTag
{
    private const OBJECT_KINDS = ['blob', 'tree', 'commit', 'tag'];
    private const PGP_SIGNATURE_BEGIN = '-----BEGIN PGP SIGNATURE-----';

    public function __construct(
        public readonly string $target,
        public readonly string $targetKind,
        public readonly string $name,
        public readonly ?string $tagger,
        public readonly string $message,
        public readonly ?string $pgpSignature = null,
        public readonly ?string $rawBody = null,
    ) {
        if (!in_array($targetKind, self::OBJECT_KINDS, true)) {
            throw new \InvalidArgumentException("Unsupported Git tag target kind: {$targetKind}");
        }
        if ($name === '') {
            throw new \InvalidArgumentException('Git tag name cannot be empty');
        }
    }

    public static function parse(string $body, string $algorithm = 'sha1'): self
    {
        $offset = 0;
        $target = self::readRequiredHeader($body, $offset, 'object');
        $length = ReferenceTarget::hashHexLength($algorithm);
        if (preg_match('/^[0-9a-fA-F]{' . $length . '}$/', $target) !== 1) {
            throw new \InvalidArgumentException("Git tag target must be a {$length}-character {$algorithm} hex object id");
        }

        $targetKind = self::readRequiredHeader($body, $offset, 'type');
        if (!in_array($targetKind, self::OBJECT_KINDS, true)) {
            throw new \InvalidArgumentException("Unsupported Git tag target kind: {$targetKind}");
        }

        $name = self::readRequiredHeader($body, $offset, 'tag');
        if ($name === '') {
            throw new \InvalidArgumentException('Git tag name cannot be empty');
        }

        $tagger = null;
        if (substr($body, $offset, 7) === 'tagger ') {
            $tagger = self::readRequiredHeader($body, $offset, 'tagger');
            CommitSignature::parse($tagger);
        }

        [$message, $pgpSignature] = self::parseMessageAndSignature(substr($body, $offset));

        return new self(strtolower($target), $targetKind, $name, $tagger, $message, $pgpSignature, $body);
    }

    public function taggerSignature(): ?CommitSignature
    {
        return $this->tagger === null ? null : CommitSignature::parse($this->tagger);
    }

    private static function readRequiredHeader(string $input, int &$offset, string $name): string
    {
        $newline = strpos($input, "\n", $offset);
        if ($newline === false) {
            throw new \InvalidArgumentException("Git tag {$name} header is not newline terminated");
        }

        [, $line, $nextOffset] = self::lineWithTerminatorAt($input, $offset);
        $prefix = $name . ' ';
        if (!str_starts_with($line, $prefix)) {
            throw new \InvalidArgumentException("Git tag is missing required {$name} header");
        }

        $offset = $nextOffset;
        return substr($line, strlen($prefix));
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private static function parseMessageAndSignature(string $tail): array
    {
        if ($tail === '' || strspn($tail, "\n") === strlen($tail)) {
            return [$tail, null];
        }

        if ($tail[0] !== "\n") {
            throw new \InvalidArgumentException('Git tag message is missing header separator');
        }

        $message = substr($tail, 1);
        $signatureStart = self::findPgpSignatureStart($message);
        if ($signatureStart === null) {
            return [$message, null];
        }

        $messageEnd = $signatureStart;
        if ($messageEnd > 0 && $message[$messageEnd - 1] === "\n") {
            $messageEnd--;
        }

        return [substr($message, 0, $messageEnd), substr($message, $signatureStart)];
    }

    private static function findPgpSignatureStart(string $message): ?int
    {
        if (str_starts_with($message, self::PGP_SIGNATURE_BEGIN)) {
            return 0;
        }

        $offset = 0;
        while (($newline = strpos($message, "\n", $offset)) !== false) {
            $candidate = $newline + 1;
            if (substr($message, $candidate, strlen(self::PGP_SIGNATURE_BEGIN)) === self::PGP_SIGNATURE_BEGIN) {
                return $candidate;
            }
            $offset = $candidate;
        }

        return null;
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
}
