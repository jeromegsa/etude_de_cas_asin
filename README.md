# Étude de cas TMA : prise de rendez-vous au guichet

Module de règles de gestion en PHP, indépendant de toute base de données et de tout framework.
Il corrige les anomalies des tickets TMA-101 à TMA-107 et applique la section 2 de l'énoncé.

## Lancer les tests

Prérequis : PHP 8.4 ou plus et Composer (testé avec PHP 8.5.11 et PHPUnit 13.4.5).

```
composer install
php vendor/phpunit/phpunit/phpunit
```

Résultat attendu : `OK (25 tests, 45 assertions)`.

## Organisation

| Fichier | Rôle |
|---|---|
| `src/Messages.php` | Les messages exacts de l'énoncé, en constantes |
| `src/Resultat.php` | Résultat d'un contrôle : `estOk()`, `code()` (HTTP), `message()` |
| `src/RegleRendezVous.php` | `priseRendezVous`, `annulation`, `changementStatut`, `listerRendezVous` |
| `tests/` | Un fichier de test par famille de règles |
| `requetes.sql` | Les 3 requêtes d'impact |
| `data/base_rendez_vous.sql` | Export fourni, à recharger pour rejouer les requêtes |

Chaque méthode reçoit l'heure courante en paramètre (`DateTimeImmutable $maintenant`), jamais l'heure système, pour que les tests soient déterministes. Toutes les heures sont celles du Bénin (GMT+1).

## Qualification et diagnostic des tickets

Verdict : 6 anomalies (TMA-101 à 106) et 1 comportement conforme (TMA-107).

### TMA-101 : rendez-vous un samedi
- **Qualification** : anomalie. Règle : « Le centre reçoit du lundi au vendredi ».
- **Cause probable** : le contrôle du jour ouvré est absent ou incomplet. Le 14/11/2026 est un samedi et la prise a été acceptée (201).
- **Correction** : `priseRendezVous` refuse le samedi et le dimanche avec `409 Le centre est fermé le week-end.`
- **Test** : `CreneauTest::testSamediRefuse`

### TMA-102 : quatre usagers sur un même créneau
- **Qualification** : anomalie. Règle : « Un créneau accueille au plus 3 usagers ».
- **Cause probable** : erreur de borne (décalage d'un). Le contrôle refuse quand le nombre d'inscrits est supérieur à 3 au lieu de supérieur ou égal à 3. Avec 3 inscrits existants, le 4e passe. Les 3 RDV déjà présents (632, 644, 681) n'étaient pas annulés.
- **Correction** : refus `409 Ce créneau est complet.` à partir de 3 RDV non annulés. Un RDV `annule` ne compte pas (« un rendez-vous annulé libère sa place »).
- **Tests** : `CreneauTest::testCreneauPleinRefuse`, `testRdvAnnuleLiberePlace`

### TMA-103 : « Erreur interne » avec une lettre dans le NPI
- **Qualification** : anomalie. Règle : le NPI est composé d'exactement 10 chiffres, et une saisie invalide est refusée avec un message, jamais par une erreur interne.
- **Cause probable** : contrôle de format absent. Le NPI `06142A3647` part tel quel dans le traitement (conversion numérique ou insertion en base) qui lève une exception, d'où le 500.
- **Correction** : validation `^\d{10}\z` avant tout traitement, `400 Le NPI doit comporter exactement 10 chiffres.` Le `\z` empêche aussi un retour à la ligne final.
- **Test** : `ValidationSaisieTest::testNpiAvecLettreRefuse`

### TMA-104 : annulation refusée alors qu'il reste 40 h
- **Qualification** : anomalie. Règle : « L'usager peut annuler son rendez-vous jusqu'à 24 heures avant l'heure prévue ». Le 17/11 à 18:00, le RDV du 19/11 à 10:00 est à 40 h.
- **Cause probable** : erreur de calcul du délai. L'application compare probablement les heures de la journée sans tenir compte de la date (10:00 − 18:00 donne −8 h, inférieur à 24 h) au lieu de comparer les dates complètes.
- **Correction** : l'écart est calculé en secondes entre deux dates complètes. Refus seulement si l'écart est inférieur à 24 h, donc pile 24 h est acceptée.
- **Tests** : `AnnulationTest::testAnnulationA40hAcceptee`, `testAnnulationPile24hAcceptee`, `testAnnulationA23h59Refusee`

### TMA-105 : un rendez-vous annulé devient « honoré »
- **Qualification** : anomalie. Règle : honoré, absent et annulé sont définitifs.
- **Cause probable** : contrôle absent. Le statut courant n'est pas vérifié avant la transition : toute demande de statut valide est acceptée.
- **Correction** : `changementStatut` n'accepte que `honore` ou `absent` (sinon `400 Statut invalide : honore ou absent.`), et seulement depuis `confirme` (sinon `409 Ce rendez-vous est déjà clôturé.`).
- **Test** : `ChangementStatutTest::testRdvAnnuleNePeutPasDevenirHonore`

### TMA-106 : le prochain rendez-vous n'est pas en tête
- **Qualification** : anomalie. Règle : liste du plus récent au plus ancien, le prochain rendez-vous en tête, par pages de 10.
- **Cause probable** : tri absent ou appliqué trop tard. Les dates de la trace sont dans un ordre quelconque, et le rendez-vous du 02/12 n'est même pas sur la page 1. La découpe en pages se fait probablement avant le tri, ou sans tri sur `date_heure`.
- **Correction** : `listerRendezVous` trie sur `dateHeure` décroissant sur l'ensemble des rendez-vous, puis découpe en pages de 10.
- **Tests** : `ListeTest::testProchainRdvEnTeteDePremierePage`, `testOrdreDecroissantSurLaPage1`, `testPage2ContientLesTroisPlusAnciens`

### TMA-107 : refus d'un second rendez-vous (CONFORME)
- **Qualification** : **comportement conforme, pas une anomalie**. Règle : « Un usager ne peut avoir qu'un seul rendez-vous à venir, tous services confondus ».
- **Justification** : le NPI `0102030405` a déjà le rendez-vous n° 800 (passeport, 26/11/2026, confirmé). Le refus `409 Vous avez déjà un rendez-vous à venir.` est exact, même si le service demandé (permis) diffère.
- **Aucune correction.**
- **Test** (verrouille le comportement attendu) : `CreneauTest::testDejaUnRdvAVenirRefuse`

## Requêtes d'impact

Fichier `requetes.sql`, exécuté sur SQLite (`sqlite3 rdv.db < data/base_rendez_vous.sql` puis `sqlite3 rdv.db < requetes.sql`). Seule l'expression du jour de la semaine change selon le moteur : les variantes PostgreSQL et MySQL sont en commentaire. L'export date du 20/11/2026 à 18:00 : « à venir » signifie après cette date.

1. **Confirmés à venir, un samedi ou un dimanche (5)** : n° 680 (21/11), 730 (28/11), 834 (05/12), 857 (05/12), 874 (12/12). Ces usagers doivent être prévenus.
2. **Créneaux de plus de 3 RDV non annulés (3, 4 RDV chacun)** : `2026-10-22 10:30`, `2026-11-10 09:00` (le cas du ticket TMA-102) et `2026-11-24 14:00`. Ce dernier est à venir : les usagers concernés sont à prévenir.
3. **Passés de « annulé » à un autre statut (4)** : n° 208 (honoré), 231 (honoré), 262 (absent), 292 (honoré, le cas du ticket TMA-105).

## Bonus : anomalies non signalées

1. **NPI non conformes en base** : n° 459 (`01234567890`, 11 chiffres) et n° 642 (`061425364711`, 12 chiffres). Ils violent la règle des 10 chiffres. La validation du module les refuse.
2. **Sorties du statut « annulé »** : n° 208, 231 et 262, en plus du 292 (voir requête 3), alors que ce statut est définitif. `changementStatut` l'interdit.
3. **Heures hors grille** : n° 490 (`16:00`, honoré), n° 698 (`16:30`, absent) et n° 842 (`16:00`, **confirmé, à venir le 08/12**) alors que le dernier créneau est à 15:30. `priseRendezVous` refuse ces heures. L'usager du n° 842 est à prévenir.
4. **Rendez-vous passés restés « confirmé »** : n° 475 (31/10), 715 et 742 (14/11), tous des samedis. Ce sont des conséquences de TMA-101 : le centre étant fermé, les agents n'ont pas pu les clôturer. Au total, les 8 rendez-vous de week-end de la base (ces 3 plus les 5 à venir de la requête 1) proviennent de ce défaut.
5. **Créneaux saturés hors ticket** : `2026-10-22 10:30` et `2026-11-24 14:00` comptent 4 RDV non annulés (requête 2).
