<?php

namespace Rdv\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Rdv\RegleRendezVous;

final class ListeTest extends TestCase
{
    // Les 13 rendez-vous de l'usager 0203040506, dans l'ordre désordonné de la trace TMA-106
    private function rdvUsager(): array
    {
        $dates = [
            '2026-07-28 09:30', '2026-09-25 11:00', '2026-04-21 09:00',
            '2026-05-19 15:00', '2026-06-16 13:30', '2026-02-12 10:00',
            '2026-08-11 14:00', '2026-05-08 11:30', '2026-07-07 10:30',
            '2026-01-05 08:30', '2026-03-03 14:30', '2026-06-02 08:00',
            '2026-12-02 08:30',
        ];

        return array_map(fn (string $d) => [
            'npi' => '0203040506',
            'statut' => 'confirme',
            'dateHeure' => new DateTimeImmutable($d, new DateTimeZone('+01:00')),
        ], $dates);
    }

    private function dates(array $resultat): array
    {
        return array_map(fn (array $r) => $r['dateHeure']->format('Y-m-d H:i'), $resultat['rendezVous']);
    }

    // TMA-106 : le RDV du 2 décembre doit être en tête de la première page
    public function testProchainRdvEnTeteDePremierePage(): void
    {
        $page1 = (new RegleRendezVous())->listerRendezVous($this->rdvUsager(), 1);

        $this->assertSame(13, $page1['total']);
        $this->assertSame(10, $page1['parPage']);
        $this->assertCount(10, $page1['rendezVous']);
        $this->assertSame('2026-12-02 08:30', $this->dates($page1)[0]);
    }

    public function testOrdreDecroissantSurLaPage1(): void
    {
        $page1 = (new RegleRendezVous())->listerRendezVous($this->rdvUsager(), 1);

        $this->assertSame([
            '2026-12-02 08:30', '2026-09-25 11:00', '2026-08-11 14:00', '2026-07-28 09:30',
            '2026-07-07 10:30', '2026-06-16 13:30', '2026-06-02 08:00', '2026-05-19 15:00',
            '2026-05-08 11:30', '2026-04-21 09:00',
        ], $this->dates($page1));
    }

    public function testPage2ContientLesTroisPlusAnciens(): void
    {
        $page2 = (new RegleRendezVous())->listerRendezVous($this->rdvUsager(), 2);

        $this->assertSame(['2026-03-03 14:30', '2026-02-12 10:00', '2026-01-05 08:30'], $this->dates($page2));
        $this->assertSame(13, $page2['total']);
    }

    public function testPageHorsLimiteVideMaisTotalExact(): void
    {
        $page3 = (new RegleRendezVous())->listerRendezVous($this->rdvUsager(), 3);

        $this->assertSame([], $page3['rendezVous']);
        $this->assertSame(13, $page3['total']);
    }

    public function testPageInferieureAUneEstLaPremiere(): void
    {
        $page = (new RegleRendezVous())->listerRendezVous($this->rdvUsager(), 0);

        $this->assertSame(1, $page['page']);
        $this->assertSame('2026-12-02 08:30', $this->dates($page)[0]);
    }
}
