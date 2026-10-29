<?php

namespace Rdv;

use DateTimeImmutable;
use DateTimeZone;

final class RegleRendezVous
{
    public const SERVICES = ['PASSEPORT', 'CNI', 'PERMIS'];
    public const CAPACITE_CRENEAU = 3;
    private const PREMIER_CRENEAU = 8 * 60;       // 08:00
    private const DERNIER_CRENEAU = 15 * 60 + 30; // 15:30
    private const PAS_CRENEAU = 30;

    private const DELAI_ANNULATION = 24 * 3600; // 24 h en secondes


    public function priseRendezVous(
        string $npi,
        string $service,
        string $dateHeure,
        array $rdvExistants,
        DateTimeImmutable $maintenant
    ): Resultat {
        // Règle 1 (TMA-103) : NPI = exactement 10 chiffres, refusé en 400 et non en erreur interne
        if (!preg_match('/^\d{10}\z/', $npi)) {
            return Resultat::refus(400, Messages::NPI_INVALIDE);
        }

        // Règle 1 : service parmi PASSEPORT, CNI, PERMIS
        if (!in_array($service, self::SERVICES, true)) {
            return Resultat::refus(400, Messages::SERVICE_INCONNU);
        }

        // Format AAAA-MM-JJTHH:MM strict (400, date illisible), heure du Bénin (GMT+1)
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $dateHeure, new DateTimeZone('+01:00'));
        $erreurs = DateTimeImmutable::getLastErrors();
        if ($date === false || ($erreurs && ($erreurs['warning_count'] || $erreurs['error_count']))) {
            return Resultat::refus(400, Messages::DATE_INVALIDE);
        }

        // Règle : pas de rendez-vous dans le passé (409)
        if ($date < $maintenant) {
            return Resultat::refus(409, Messages::DATE_PASSEE);
        }

        // Règle 2 (TMA-101) : le centre reçoit du lundi au vendredi (N = 6 samedi, 7 dimanche)
        if ((int) $date->format('N') >= 6) {
            return Resultat::refus(409, Messages::WEEK_END);
        }

        // Règle 2 : créneaux de 30 min, de 08:00 à 15:30 inclus
        $minutes = (int) $date->format('G') * 60 + (int) $date->format('i');
        if (
            $minutes < self::PREMIER_CRENEAU
            || $minutes > self::DERNIER_CRENEAU
            || $minutes % self::PAS_CRENEAU !== 0
        ) {
            return Resultat::refus(409, Messages::HORS_GRILLE);
        }

        // Règle 4 (TMA-107, conforme) : un seul RDV à venir par usager, tous services confondus
        foreach ($rdvExistants as $rdv) {
            if ($rdv['npi'] === $npi && $rdv['statut'] === 'confirme' && $rdv['dateHeure'] > $maintenant) {
                return Resultat::refus(409, Messages::DEJA_A_VENIR);
            }
        }

        // Règle 3 (TMA-102) : 3 usagers au plus par créneau, un RDV annulé libère sa place
        $occupes = 0;
        foreach ($rdvExistants as $rdv) {
            if ($rdv['dateHeure'] == $date && $rdv['statut'] !== 'annule') {
                $occupes++;
            }
        }
        if ($occupes >= self::CAPACITE_CRENEAU) {
            return Resultat::refus(409, Messages::CRENEAU_PLEIN);
        }

        return Resultat::ok();
    }

    public function annulation(array $rdv, DateTimeImmutable $maintenant): Resultat
    {
        // Règle 5 : seul un RDV « confirme » peut être annulé (annule, honore, absent sont définitifs)
        if ($rdv['statut'] !== 'confirme') {
            return Resultat::refus(409, Messages::ANNULATION_IMPOSSIBLE);
        }

        // Règle 5 (TMA-104) : annulation possible jusqu'à 24 h avant, pile 24 h incluse.
        // Écart calculé sur la date ET l'heure complètes, en secondes.
        $ecart = $rdv['dateHeure']->getTimestamp() - $maintenant->getTimestamp();
        if ($ecart < self::DELAI_ANNULATION) {
            return Resultat::refus(409, Messages::ANNULATION_TARDIVE);
        }

        return Resultat::ok();
    }

}
