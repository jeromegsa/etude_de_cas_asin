<?php

namespace Rdv\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rdv\Messages;
use Rdv\RegleRendezVous;

final class ValidationSaisieTest extends TestCase
{
    private function maintenant(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-20 18:00', new \DateTimeZone('+01:00'));
    }

    // TMA-103 : un NPI contenant une lettre doit être refusé en 400 avec le message prévu
    public function testNpiAvecLettreRefuse(): void
    {
        $r = (new RegleRendezVous())->priseRendezVous('06142A3647', 'CNI', '2026-11-23T09:00', [], $this->maintenant());
        $this->assertFalse($r->estOk());
        $this->assertSame(400, $r->code());
        $this->assertSame(Messages::NPI_INVALIDE, $r->message());
    }

    public function testServiceInconnuRefuse(): void
    {
        $r = (new RegleRendezVous())->priseRendezVous('0614253647', 'VISA', '2026-11-23T09:00', [], $this->maintenant());
        $this->assertSame(400, $r->code());
        $this->assertSame(Messages::SERVICE_INCONNU, $r->message());
    }

    public function testDateIllisibleRefusee(): void
    {
        $r = (new RegleRendezVous())->priseRendezVous('0614253647', 'CNI', '2026-13-40T09:00', [], $this->maintenant());
        $this->assertSame(400, $r->code());
        $this->assertSame(Messages::DATE_INVALIDE, $r->message());
    }
}
