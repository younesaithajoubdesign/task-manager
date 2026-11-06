# Prompts for Claude Code — steps 11 to 14

Continues `PROMPTS.md`. Requires `SPEC-ADDENDUM-MOBILE.md` at the project root.
One step at a time, as before.

---

## Step 11 — LAN test (do this yourself, no Claude Code)

Ten minutes, no code. It decides how much work step 12 is.

```bash
cd api && php artisan serve --host=0.0.0.0
```

Find the Mac's local address: System Settings → Network, something like
`192.168.1.14`. From the phone on the same WiFi, open:

```
http://192.168.1.14:5173
```

(Vite also needs `--host`: `npm run dev -- --host`.)

Then write down, literally, everything unusable: text too small, buttons
unreachable, sidebar eating the screen, horizontal scrolling. That list is the
brief for step 12.

---

## Step 12 — Mobile layout

```
Lis SPEC-ADDENDUM-MOBILE.md à la racine, en plus de SPEC.md.

Étape 12 : la mise en page mobile, section 12 de l'addendum.

Un seul breakpoint à 768px. En dessous : barre d'onglets en bas
(Today / Browse / Add / Stats), Browse en navigation par
enfoncement (catégorie → tâches) et non en sidebar.

Respecte les cibles tactiles de 44px minimum, y compris les quatre
boutons du timer, et le safe-area-inset-bottom pour l'encoche iOS.

Au-dessus de 768px, la mise en page actuelle ne change pas.

Voici ce qui est inutilisable sur mon téléphone aujourd'hui :
[colle ici ta liste de l'étape 11]
```

**Check**: on the real phone, not Chrome's emulator. Everything reachable with
the thumb, no horizontal scroll, quick capture opens the keyboard immediately.

---

## Step 13 — Deployment + security

```
Étape 13 : déploiement, sections 11 et 14 de l'addendum.

VPS, Caddy pour le HTTPS automatique, PHP-FPM, SQLite conservé.
React buildé et servi en statique par Caddy, Laravel sur /api/*.

Ajoute aussi les points de sécurité de la section 14 : throttle:5,1
sur la route de login, APP_DEBUG=false, expiration du token à 90
jours, route de déconnexion.

Écris-moi un fichier DEPLOY.md avec la procédure complète, que je
puisse la refaire seul.
```

**Check**: the site opens in HTTPS from mobile data, with the phone's WiFi off.
The backup cron is in place.

---

## Step 14 — PWA + offline reading

```
Étape 14 : PWA et lecture hors ligne, section 13 de l'addendum.

vite-plugin-pwa, manifest avec les icônes 192/512 et une maskable,
apple-touch-icon pour iOS.

Stratégie de cache : app shell en precache, GET /api/* en
NetworkFirst avec repli sur le cache, mutations jamais mises en
cache ni mises en file.

Soigne particulièrement l'état hors ligne : bandeau permanent avec
l'heure des données affichées, et désactivation visible de la
saisie rapide, des cases à cocher et des boutons du timer. Détecte
hors ligne avec navigator.onLine ET l'échec d'un appel API.
```

**Check**: install to the home screen, switch the phone to airplane mode, open
the app. Your tasks are there, the banner says offline, nothing is clickable
that shouldn't be. Back online, it refetches by itself.

---

## Reminder

If Claude Code suggests adding an offline write queue, IndexedDB mutations,
a sync engine or UUID primary keys: **no**. It was decided against on purpose
and it is written in section 10 of the addendum.
