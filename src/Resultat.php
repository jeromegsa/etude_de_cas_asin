<?php

namespace Rdv;

final class Resultat
{
    private function __construct(
        private bool $ok,
        private int $code,
        private string $message
    ) {
    }

    public static function ok(): self
    {
        return new self(true, 200, '');
    }

    public static function refus(int $code, string $message): self
    {
        return new self(false, $code, $message);
    }

    public function estOk(): bool
    {
        return $this->ok;
    }

    public function code(): int
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }
}
