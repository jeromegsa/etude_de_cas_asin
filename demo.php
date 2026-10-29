<?php
// Rejoue les 7 tickets avec les données des traces. Usage : php demo.php
require __DIR__ . '/vendor/autoload.php';

use Rdv\RegleRendezVous;
use Rdv\Resultat;

$fuseau = new DateTimeZone('+01:00');
$date = fn (string $texte) => new DateTimeImmutable($texte, $fuseau);
$rdv = fn (string $npi, string $statut, string $texte) => ['npi' => $npi, 'statut' => $statut, 'dateHeure' => $date($texte)];
$affiche = fn (Resultat $r) => $r->estOk() ? 'accepté' : $r->code() . ' ' . $r->message();

$regle = new RegleRendezVous();

// TMA-101 : prise pour le samedi 14/11/2026 (demande faite le 09/11)
echo "TMA-101 : ", $affiche($regle->priseRendezVous('0614253647', 'PASSEPORT', '2026-11-14T10:00', [], $date('2026-11-09 12:00'))), "\n";

// TMA-102 : 4e usager sur le créneau du 10/11/2026 à 09:00 (632, 644, 681 déjà pris)
$existants = [
    $rdv('0638958971', 'confirme', '2026-11-10 09:00'),
    $rdv('0671968463', 'confirme', '2026-11-10 09:00'),
    $rdv('0609394161', 'confirme', '2026-11-10 09:00'),
];
echo "TMA-102 : ", $affiche($regle->priseRendezVous('0620214467', 'CNI', '2026-11-10T09:00', $existants, $date('2026-11-04 23:00'))), "\n";

// TMA-103 : NPI contenant une lettre
echo "TMA-103 : ", $affiche($regle->priseRendezVous('06142A3647', 'CNI', '2026-11-23T09:00', [], $date('2026-11-20 18:00'))), "\n";

// TMA-104 : annulation le 17/11 à 18:00 du RDV n° 709 du 19/11 à 10:00
echo "TMA-104 : ", $affiche($regle->annulation($rdv('0339205992', 'confirme', '2026-11-19 10:00'), $date('2026-11-17 18:00'))), "\n";

// TMA-105 : passage à « honore » du RDV n° 292, au statut annule
echo "TMA-105 : ", $affiche($regle->changementStatut($rdv('0330677555', 'annule', '2026-11-12 10:00'), 'honore')), "\n";

// TMA-106 : liste de l'usager 0203040506 (13 RDV), page 1
$dates = ['2026-07-28 09:30', '2026-09-25 11:00', '2026-04-21 09:00', '2026-05-19 15:00', '2026-06-16 13:30',
    '2026-02-12 10:00', '2026-08-11 14:00', '2026-05-08 11:30', '2026-07-07 10:30', '2026-01-05 08:30',
    '2026-03-03 14:30', '2026-06-02 08:00', '2026-12-02 08:30'];
$liste = $regle->listerRendezVous(array_map(fn ($d) => $rdv('0203040506', 'confirme', $d), $dates), 1);
echo "TMA-106 : total ", $liste['total'], ", page 1 de ", count($liste['rendezVous']), " rdv, premier = ",
    $liste['rendezVous'][0]['dateHeure']->format('d/m/Y H:i'), "\n";

// TMA-107 : nouveau RDV (permis) alors que le n° 800 (passeport, 26/11) est confirmé
$existants = [$rdv('0102030405', 'confirme', '2026-11-26 10:00')];
echo "TMA-107 : ", $affiche($regle->priseRendezVous('0102030405', 'PERMIS', '2026-11-27T09:00', $existants, $date('2026-11-16 09:00'))), "\n";
