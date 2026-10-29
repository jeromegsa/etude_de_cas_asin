-- Requêtes d'impact. Date de l'export : vendredi 20 novembre 2026 à 18:00 (heure du Bénin).
-- Exécutées sur SQLite. Seule l'expression « jour de la semaine » change selon le moteur.

-- 1. Rendez-vous confirmés à venir, pris un samedi ou un dimanche, pour prévenir les usagers (TMA-101).
-- « À venir » = après l'export. Le jour retenu est celui du rendez-vous (date_heure), pas celui de cree_le.
-- Variante PostgreSQL : EXTRACT(DOW FROM date_heure) IN (0, 6)
-- Variante MySQL : DAYOFWEEK(date_heure) IN (1, 7)
SELECT id, npi, service, date_heure, statut
FROM rendez_vous
WHERE statut = 'confirme'
  AND date_heure > '2026-11-20 18:00:00'
  AND strftime('%w', date_heure) IN ('0', '6')
ORDER BY date_heure;

-- 2. Créneaux qui comptent plus de 3 rendez-vous non annulés (TMA-102).
SELECT date_heure, COUNT(*) AS nb_rdv
FROM rendez_vous
WHERE statut <> 'annule'
GROUP BY date_heure
HAVING COUNT(*) > 3
ORDER BY date_heure;

-- 3. Rendez-vous passés du statut annulé à un autre statut (TMA-105).
SELECT r.id, r.npi, r.date_heure, h.ancien_statut, h.nouveau_statut, h.modifie_le, h.modifie_par
FROM historique_statuts h
JOIN rendez_vous r ON r.id = h.rendez_vous_id
WHERE h.ancien_statut = 'annule'
ORDER BY h.modifie_le;
