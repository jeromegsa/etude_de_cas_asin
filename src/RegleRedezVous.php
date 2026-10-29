<?php

namespace Rdv;

use DateTimeImmutable;

final class RegleRendezVous
{
    public const SERVICES = ['PASSEPORT', 'CNI', 'PERMIS'];

    public function priseRendezVous(
        string $npi,
        string $service,
        string $dateHeure,
        array $rdvExistants,
        DateTimeImmutable $maintenant
    ): Resultat {
        // Règle 1 (TMA-103) : NPI = exactement 10 chiffres, refusé en 400 et non en erreur interne
        if (!preg_match('/^\d{10}$/', $npi)) {
            return Resultat::refus(400, Messages::NPI_INVALIDE);
        }

        // Règle 1 : service parmi PASSEPORT, CNI, PERMIS
        if (!in_array($service, self::SERVICES, true)) {
            return Resultat::refus(400, Messages::SERVICE_INCONNU);
        }

        // Règle format : AAAA-MM-JJTHH:MM strict (rejette aussi 2026-13-40T09:00)
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $dateHeure);
        $erreurs = DateTimeImmutable::getLastErrors();
        if ($date === false || ($erreurs && ($erreurs['warning_count'] || $erreurs['error_count']))) {
            return Resultat::refus(400, Messages::DATE_INVALIDE);
        }

        return Resultat::ok();
    }
}
