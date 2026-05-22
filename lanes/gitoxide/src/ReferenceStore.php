<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ReferenceStore
{
    private readonly LooseReferenceStore $loose;

    public function __construct(
        private readonly string $gitDirectory,
        private readonly ?PackedReferences $packed = null,
    ) {
        $this->loose = new LooseReferenceStore($gitDirectory);
    }

    public static function at(string $gitDirectory, string $algorithm = 'sha1'): self
    {
        $packedPath = rtrim($gitDirectory, '/\\') . '/packed-refs';
        $packed = is_file($packedPath) ? PackedReferences::open($packedPath, $algorithm) : null;

        return new self($gitDirectory, $packed);
    }

    public function looseStore(): LooseReferenceStore
    {
        return $this->loose;
    }

    public function tryFind(string $name, string $algorithm = 'sha1'): ?ResolvedReference
    {
        foreach (self::lookupCandidates($name) as [$candidate, $allowPacked]) {
            $loose = $this->loose->tryRead($candidate, $algorithm);
            if ($loose !== null) {
                return ResolvedReference::fromLoose($loose);
            }

            if ($allowPacked && $this->packed !== null) {
                $packed = $this->packed->tryFind($candidate);
                if ($packed !== null) {
                    return ResolvedReference::fromPacked($packed);
                }
            }
        }

        return null;
    }

    public function find(string $name, string $algorithm = 'sha1'): ResolvedReference
    {
        $reference = $this->tryFind($name, $algorithm);
        if ($reference === null) {
            throw new \RuntimeException("Reference not found: {$name}");
        }

        return $reference;
    }

    /**
     * @return list<array{0:string,1:bool}>
     */
    private static function lookupCandidates(string $name): array
    {
        if (
            str_starts_with($name, 'refs/')
            || str_starts_with($name, 'main-worktree/')
            || str_starts_with($name, 'worktrees/')
        ) {
            ReferenceName::assertValid($name);
            return [[$name, true]];
        }

        ReferenceName::assertValidPartial($name);

        $candidates = [];
        if (ReferenceName::isPseudoRef($name)) {
            $candidates[] = [$name, false];
        }

        foreach (['', 'tags', 'heads', 'remotes'] as $inbetween) {
            $candidate = 'refs/' . ($inbetween === '' ? '' : $inbetween . '/') . $name;
            ReferenceName::assertValid($candidate);
            $candidates[] = [$candidate, true];
        }

        if ($name !== 'HEAD') {
            $remoteHead = 'refs/remotes/' . $name . '/HEAD';
            ReferenceName::assertValid($remoteHead);
            $candidates[] = [$remoteHead, false];
        }

        return $candidates;
    }
}
