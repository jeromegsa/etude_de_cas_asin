<?php

namespace Rdv\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Rdv\Messages;
use Rdv\RegleRendezVous;

final class ChangementStatutTest extends TestCase
{
    private function rdv(string $statut): array
    {
        return [
            'npi' => '0330677555',
            'statut' => $statut,
            'dateHeure' => new DateTimeImmutable('2026-11-12 10:00', new DateTimeZone('+01:00')),
        ];
    }

    // TMA-105 : le RDV n°292, annulé, ne peut pas devenir « honoré »
    public function testRdvAnnuleNePeutPasDevenirHonore(): void
    {
        $r = (new RegleRendezVous())->changementStatut($this->rdv('annule'), 'honore');
        $this->assertSame(409, $r->code());
        $this->assertSame(Messages::DEJA_CLOTURE, $r->message());
    }

    public function testConfirmeDevientHonore(): void
    {
        $r = (new RegleRendezVous())->changementStatut($this->rdv('confirme'), 'honore');
        $this->assertTrue($r->estOk());
    }

    public function testConfirmeDevientAbsent(): void
    {
        $r = (new RegleRendezVous())->changementStatut($this->rdv('confirme'), 'absent');
        $this->assertTrue($r->estOk());
    }

    // « annule » n'est pas un statut qu'un agent peut demander : 400 et non 409
    public function testStatutAnnuleDemandeRefuse(): void
    {
        $r = (new RegleRendezVous())->changementStatut($this->rdv('confirme'), 'annule');
        $this->assertSame(400, $r->code());
        $this->assertSame(Messages::STATUT_INVALIDE, $r->message());
    }

    // Statut définitif : honore ne peut pas repasser en absent
    public function testHonoreNePeutPasDevenirAbsent(): void
    {
        $r = (new RegleRendezVous())->changementStatut($this->rdv('honore'), 'absent');
        $this->assertSame(409, $r->code());
        $this->assertSame(Messages::DEJA_CLOTURE, $r->message());
    }
}
