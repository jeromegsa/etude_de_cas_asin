<?php

namespace Rdv\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Rdv\Messages;
use Rdv\RegleRendezVous;

final class AnnulationTest extends TestCase
{
    private function date(string $texte): DateTimeImmutable
    {
        return new DateTimeImmutable($texte, new DateTimeZone('+01:00'));
    }

    private function rdv(string $statut, string $dateHeure): array
    {
        return ['npi' => '0339205992', 'statut' => $statut, 'dateHeure' => $this->date($dateHeure)];
    }

    // TMA-104 : le 17/11 à 18:00, le RDV du 19/11 à 10:00 est à 40 h, l'annulation doit être acceptée
    public function testAnnulationA40hAcceptee(): void
    {
        $r = (new RegleRendezVous())->annulation(
            $this->rdv('confirme', '2026-11-19 10:00'), $this->date('2026-11-17 18:00')
        );
        $this->assertTrue($r->estOk());
    }

    // Borne : pile 24 h avant, l'annulation est encore permise
    public function testAnnulationPile24hAcceptee(): void
    {
        $r = (new RegleRendezVous())->annulation(
            $this->rdv('confirme', '2026-11-19 10:00'), $this->date('2026-11-18 10:00')
        );
        $this->assertTrue($r->estOk());
    }

    // Borne : 23 h 59 avant, refusée
    public function testAnnulationA23h59Refusee(): void
    {
        $r = (new RegleRendezVous())->annulation(
            $this->rdv('confirme', '2026-11-19 10:00'), $this->date('2026-11-18 10:01')
        );
        $this->assertSame(409, $r->code());
        $this->assertSame(Messages::ANNULATION_TARDIVE, $r->message());
    }

    public function testAnnulationRdvHonoreRefusee(): void
    {
        $r = (new RegleRendezVous())->annulation(
            $this->rdv('honore', '2026-11-30 10:00'), $this->date('2026-11-17 18:00')
        );
        $this->assertSame(409, $r->code());
        $this->assertSame(Messages::ANNULATION_IMPOSSIBLE, $r->message());
    }

    public function testAnnulationRdvDejaAnnuleRefusee(): void
    {
        $r = (new RegleRendezVous())->annulation(
            $this->rdv('annule', '2026-11-30 10:00'), $this->date('2026-11-17 18:00')
        );
        $this->assertSame(Messages::ANNULATION_IMPOSSIBLE, $r->message());
    }
}
