<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitTag
{
    private const OBJECT_KINDS = ['blob', 'tree', 'commit', 'tag'];
    private const PGP_SIGNATURE_BEGIN = '-----BEGIN PGP SIGNATURE-----';

    public readonly string $target;
    public readonly string $targetKind;
    public readonly string $name;
    public readonly ?string $tagger;
    public readonly string $message;
    public readonly ?string $pgpSignature;
    public readonly ?string $rawBody;
    public readonly string $rawTarget;

    public function __construct(
        string $target,
        string $targetKind,
        string $name,
        ?string $tagger,
        string $message,
        ?string $pgpSignature = null,
        ?string $rawBody = null,
        ?string $rawTarget = null,
    ) {
        if (!in_array($targetKind, self::OBJECT_KINDS, true)) {
            throw new \InvalidArgumentException("Unsupported Git tag target kind: {$targetKind}");
        }
        if ($name === '') {
            throw new \InvalidArgumentException('Git tag name cannot be empty');
        }

        $this->target = strtolower($target);
        $this->targetKind = $targetKind;
        $this->name = $name;
        $this->tagger = $tagger;
        $this->message = $message;
        $this->pgpSignature = $pgpSignature;
        $this->rawBody = $rawBody;
        $this->rawTarget = $rawTarget ?? strtolower($target);
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

        return new self(strtolower($target), $targetKind, $name, $tagger, $message, $pgpSignature, $body, $target);
    }

    /**
     * @return list<array{ok: bool, token?: array<string, mixed>, error?: string}>
     */
    public static function iterateTokens(string $body, string $algorithm = 'sha1'): array
    {
        $offset = 0;
        $tokens = [];

        if ($body === '') {
            return [];
        }

        try {
            $target = self::readRequiredHeader($body, $offset, 'object');
            $length = ReferenceTarget::hashHexLength($algorithm);
            if (preg_match('/^[0-9a-fA-F]{' . $length . '}$/', $target) !== 1) {
                throw new \InvalidArgumentException("Git tag target must be a {$length}-character {$algorithm} hex object id");
            }
            $tokens[] = self::okToken(['type' => 'target', 'id' => strtolower($target), 'rawId' => $target]);

            $targetKind = self::readRequiredHeader($body, $offset, 'type');
            if (!in_array($targetKind, self::OBJECT_KINDS, true)) {
                throw new \InvalidArgumentException("Unsupported Git tag target kind: {$targetKind}");
            }
            $tokens[] = self::okToken(['type' => 'targetKind', 'kind' => $targetKind]);

            $name = self::readRequiredHeader($body, $offset, 'tag');
            if ($name === '') {
                throw new \InvalidArgumentException('Git tag name cannot be empty');
            }
            $tokens[] = self::okToken(['type' => 'name', 'name' => $name]);

            $tagger = null;
            if (substr($body, $offset, 7) === 'tagger ') {
                $tagger = self::readRequiredHeader($body, $offset, 'tagger');
                CommitSignature::parse($tagger);
            }
            $tokens[] = self::okToken(['type' => 'tagger', 'signature' => $tagger]);

            [$message, $pgpSignature] = self::parseMessageAndSignature(substr($body, $offset));
            $tokens[] = self::okToken(['type' => 'body', 'message' => $message, 'pgpSignature' => $pgpSignature]);
        } catch (\InvalidArgumentException $exception) {
            $tokens[] = ['ok' => false, 'error' => $exception->getMessage()];
        }

        return $tokens;
    }

    public function taggerSignature(): ?CommitSignature
    {
        return $this->tagger === null ? null : CommitSignature::parse($this->tagger);
    }

    public function toOwned(): self
    {
        return new self($this->target, $this->targetKind, $this->name, $this->tagger, $this->message, $this->pgpSignature);
    }

    public function storageBytes(): string
    {
        self::validateWritableName($this->name);
        self::validateTarget($this->rawTarget);

        $out = "object {$this->rawTarget}\n"
            . "type {$this->targetKind}\n"
            . "tag {$this->name}\n";
        if ($this->tagger !== null) {
            CommitSignature::parse($this->tagger);
            $out .= "tagger {$this->tagger}\n";
        }

        if (!self::isOnlyNewlines($this->message)) {
            $out .= "\n";
        }
        $out .= $this->message;

        if ($this->pgpSignature !== null) {
            $out .= "\n" . $this->pgpSignature;
        }

        return $out;
    }

    public function size(): int
    {
        return strlen($this->storageBytes());
    }

    public function object(): GitObject
    {
        return new GitObject('tag', $this->storageBytes());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tokens(): array
    {
        return [
            ['type' => 'target', 'id' => $this->target],
            ['type' => 'targetKind', 'kind' => $this->targetKind],
            ['type' => 'name', 'name' => $this->name],
            ['type' => 'tagger', 'signature' => $this->tagger],
            ['type' => 'body', 'message' => $this->message, 'pgpSignature' => $this->pgpSignature],
        ];
    }

    public static function isValidName(string $name): bool
    {
        try {
            self::validateName($name);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public static function validateName(string $name): void
    {
        self::validateWritableName($name);
    }

    public static function sanitizeName(string $name): string
    {
        if ($name === '') {
            return '-';
        }

        $out = '';
        $previous = "\0";
        $componentStart = 0;
        $componentEnd = 0;
        $length = strlen($name);
        $last = $length - 1;

        for ($index = 0; $index < $length; $index++) {
            $byte = $name[$index];
            $ord = ord($byte);

            if (
                $byte === '\\'
                || $byte === '^'
                || $byte === ':'
                || $byte === '['
                || $byte === '?'
                || $byte === ' '
                || $byte === '~'
                || $byte === '*'
                || $ord <= 0x1f
                || $ord === 0x7f
            ) {
                $out .= '-';
            } elseif ($byte === '.' && $previous === '.') {
                // Consecutive dots collapse during sanitization.
            } elseif ($byte === '.' && $previous === '/') {
                $out .= '-';
            } elseif ($byte === '{' && $previous === '@') {
                $out .= '-';
            } elseif ($byte === '/' && $previous === '/') {
                // Repeated slashes collapse during sanitization.
            } else {
                if ($byte === '/') {
                    $componentStart = $componentEnd;
                    $componentEnd = $index;
                    $component = substr($name, $componentStart, $componentEnd - $componentStart);
                    if (str_ends_with($component, '.lock')) {
                        $out = self::trimRepeatedSuffix($out, '.lock');
                    }
                }

                $out .= $byte;

                if ($index === $last) {
                    $component = substr($name, $componentEnd + 1);
                    if (str_ends_with($component, '.lock')) {
                        $out = self::trimRepeatedSuffix($out, '.lock');
                    }
                }
            }

            $previous = $byte;
        }

        $out = ltrim(rtrim($out, '/'), '/');
        if ($out === '') {
            return '-';
        }

        if ($out[0] === '.') {
            $out = '-' . substr($out, 1);
        }

        $lastIndex = strlen($out) - 1;
        if ($out[$lastIndex] === '.') {
            $out = substr($out, 0, -1) . '-';
        }

        return $out === '' ? '-' : $out;
    }

    /**
     * @param array<string, mixed> $token
     * @return array{ok: bool, token: array<string, mixed>}
     */
    private static function okToken(array $token): array
    {
        return ['ok' => true, 'token' => $token];
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

    private static function validateTarget(string $target): void
    {
        if (preg_match('/^(?:[0-9a-fA-F]{40}|[0-9a-fA-F]{64})$/', $target) !== 1) {
            throw new \InvalidArgumentException('Git tag target must be a SHA-1 or SHA-256 hex object id');
        }
    }

    private static function validateWritableName(string $name): void
    {
        if ($name === '') {
            throw new \InvalidArgumentException('Git tag name cannot be empty');
        }
        if ($name[0] === '-') {
            throw new \InvalidArgumentException("Git tag name must not start with '-'");
        }
        if ($name[0] === '/') {
            throw new \InvalidArgumentException("Git tag name must not start with '/'");
        }
        if (str_ends_with($name, '/')) {
            throw new \InvalidArgumentException("Git tag name must not end with '/'");
        }
        if (str_ends_with($name, '.')) {
            throw new \InvalidArgumentException("Git tag name must not end with '.'");
        }
        if (str_contains($name, '//')) {
            throw new \InvalidArgumentException("Git tag name must not contain repeated '/'");
        }
        if (str_contains($name, '..')) {
            throw new \InvalidArgumentException("Git tag name must not contain '..'");
        }
        if (str_contains($name, '@{')) {
            throw new \InvalidArgumentException("Git tag name must not contain '@{'");
        }
        foreach (explode('/', $name) as $component) {
            if ($component === '' || $component[0] === '.') {
                throw new \InvalidArgumentException("Git tag name component must not start with '.'");
            }
            if (str_ends_with($component, '.lock')) {
                throw new \InvalidArgumentException("Git tag name component must not end with '.lock'");
            }
        }

        $length = strlen($name);
        for ($index = 0; $index < $length; $index++) {
            $ord = ord($name[$index]);
            if ($ord <= 0x1f || $ord === 0x7f || str_contains('\\^:[? ~*', $name[$index])) {
                throw new \InvalidArgumentException('Git tag name contains an invalid byte');
            }
        }
    }

    private static function trimRepeatedSuffix(string $input, string $suffix): string
    {
        while (str_ends_with($input, $suffix)) {
            $input = substr($input, 0, -strlen($suffix));
        }

        return $input;
    }

    private static function isOnlyNewlines(string $value): bool
    {
        return strspn($value, "\n") === strlen($value);
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
            return substr($line, 0, -1);
        }

        return $line;
    }
}
