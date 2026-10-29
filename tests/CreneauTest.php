<?php

namespace Rdv\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Rdv\Messages;
use Rdv\RegleRendezVous;

final class CreneauTest extends TestCase
{
    private function date(string $texte): DateTimeImmutable
    {
        return new DateTimeImmutable($texte, new DateTimeZone('+01:00'));
    }

    private function rdv(string $npi, string $statut, string $dateHeure): array
    {
        return ['npi' => $npi, 'statut' => $statut, 'dateHeure' => $this->date($dateHeure)];
    }

    // TMA-101 : le samedi 14/11/2026 doit être refusé (centre fermé le week-end)
    public function testSamediRefuse(): void
    {
        $r = (new RegleRendezVous())->priseRendezVous(
            '0614253647', 'PASSEPORT', '2026-11-14T10:00', [], $this->date('2026-11-09 12:00')
        );
        $this->assertSame(409, $r->code());
        $this->assertSame(Messages::WEEK_END, $r->message());
    }

    // TMA-102 : 4e inscription refusée quand 3 RDV non annulés occupent le créneau
    public function testCreneauPleinRefuse(): void
    {
        $existants = [
            $this->rdv('0000000001', 'confirme', '2026-11-10 09:00'),
            $this->rdv('0000000002', 'confirme', '2026-11-10 09:00'),
            $this->rdv('0000000003', 'confirme', '2026-11-10 09:00'),
        ];
        $r = (new RegleRendezVous())->priseRendezVous(
            '0620214467', 'CNI', '2026-11-10T09:00', $existants, $this->date('2026-11-04 23:00')
        );
        $this->assertSame(409, $r->code());
        $this->assertSame(Messages::CRENEAU_PLEIN, $r->message());
    }

    // Un RDV annulé libère sa place : 2 confirmés + 1 annulé, la 3e place est libre
    public function testRdvAnnuleLiberePlace(): void
    {
        $existants = [
            $this->rdv('0000000001', 'confirme', '2026-11-10 09:00'),
            $this->rdv('0000000002', 'confirme', '2026-11-10 09:00'),
            $this->rdv('0000000003', 'annule', '2026-11-10 09:00'),
        ];
        $r = (new RegleRendezVous())->priseRendezVous(
            '0620214467', 'CNI', '2026-11-10T09:00', $existants, $this->date('2026-11-04 23:00')
        );
        $this->assertTrue($r->estOk());
    }

    // TMA-107 (conforme) : un RDV passeport à venir interdit un RDV permis, refus correct
    public function testDejaUnRdvAVenirRefuse(): void
    {
        $existants = [$this->rdv('0102030405', 'confirme', '2026-11-26 10:00')];
        $r = (new RegleRendezVous())->priseRendezVous(
            '0102030405', 'PERMIS', '2026-11-27T09:00', $existants, $this->date('2026-11-16 09:00')
        );
        $this->assertSame(409, $r->code());
        $this->assertSame(Messages::DEJA_A_VENIR, $r->message());
    }

    public function testHeureHorsGrilleRefusee(): void
    {
        $r = (new RegleRendezVous())->priseRendezVous(
            '0614253647', 'CNI', '2026-11-23T16:00', [], $this->date('2026-11-20 18:00')
        );
        $this->assertSame(409, $r->code());
        $this->assertSame(Messages::HORS_GRILLE, $r->message());
    }

    public function testDatePasseeRefusee(): void
    {
        $r = (new RegleRendezVous())->priseRendezVous(
            '0614253647', 'CNI', '2026-11-16T09:00', [], $this->date('2026-11-20 18:00')
        );
        $this->assertSame(409, $r->code());
        $this->assertSame(Messages::DATE_PASSEE, $r->message());
    }

    // Cas valide : sans lui, un module qui refuse tout passerait tous les tests
    public function testLundiValideAccepte(): void
    {
        $r = (new RegleRendezVous())->priseRendezVous(
            '0614253647', 'CNI', '2026-11-23T09:00', [], $this->date('2026-11-20 18:00')
        );
        $this->assertTrue($r->estOk());
    }
}
