=== Bloomtech Stock Sync ===
Contributors: hanfjack
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Hält den Warenbestand von Bloomtech-Produkten aktuell und führt einen Katalog
aller Bloomtech-Artikelnummern.

== Beschreibung ==

Das Plugin holt die Bestandsliste mehrmals täglich direkt aus der Nextcloud und
gleicht sie über die Artikelnummer mit den WooCommerce-Produkten ab.

Zwei Grundregeln gelten immer:

1. Angefasst werden ausschließlich Produkte, bei denen eine Bloomtech-
   Artikelnummer hinterlegt ist. Produkte aus eigenem Lagerbestand bleiben
   unberührt, solange das Feld leer ist.
2. Geschrieben werden ausschließlich Bestandsmenge und Bestandsstatus.
   Preise, Texte, Status, Permalinks, Kategorien und Eigenschaften werden nie
   verändert.

== Installation ==

1. Ordner `bloomtech-stock-sync` nach `wp-content/plugins/` hochladen
   (oder das ZIP über Plugins → Installieren → Plugin hochladen einspielen).
2. Plugin aktivieren.
3. Menü „Bloomtech" → Einstellungen.

== Einrichtung in drei Schritten ==

**Schritt 1 – Freigabe-Link in Nextcloud erzeugen**

In Nextcloud den Ordner `Bloomtech/export` (oder direkt die CSV) auswählen →
*Teilen* → *Link teilen* → Berechtigung *Nur Lesen*. Der Link sieht so aus:

    https://nx58197.your-storageshare.de/s/XXXXXXXXXXXX

Diesen Link im Plugin eintragen. Es wird **kein Kontopasswort in WordPress
gespeichert**, und der Link lässt sich in Nextcloud jederzeit mit einem Klick
wieder entziehen.

Die Adresse aus der Adresszeile der Nextcloud-Weboberfläche
(`/apps/files/files/1965908?dir=…`) funktioniert nicht — das ist die Ansicht im
Browser, keine abrufbare Datei.

**Schritt 2 – Verbindung testen**

Der Knopf *Verbindung testen & Datei ansehen* lädt die Datei, erkennt
Trennzeichen und Zeichensatz automatisch und zeigt die ersten fünf Zeilen.
Danach stehen die Spaltennamen in den Auswahllisten bereit.

**Schritt 3 – Spalten zuordnen und Trockenlauf**

Pflicht sind *Artikelnummer* und *Bestand*. Anschließend *Trockenlauf* —
der zeigt im Protokoll genau, was ein echter Lauf ändern würde, ohne etwas zu
speichern. Erst wenn das Ergebnis stimmt, die Automatik einschalten.

== Produkte verknüpfen ==

Im Produkt unter *Produktdaten → Lagerbestand* steht das Feld
**Bloomtech-Artikelnummer**. Bei variablen Produkten gibt es das Feld zusätzlich
je Variante, weil Bloomtech Gebindegrößen als eigene Artikelnummern führt.

Ein Häkchen **Eigenbestand** schließt ein Produkt dauerhaft aus, selbst wenn
eine Artikelnummer eingetragen ist.

In der Produktübersicht zeigt die Spalte *Bloomtech* auf einen Blick, was
verknüpft ist und was nicht.

== Artikelkatalog ==

Jede Artikelnummer, die je in einem Export stand, wird dauerhaft gespeichert –
auch dann, wenn es im Shop noch kein passendes Produkt gibt. Beim Anlegen neuer
Bloomtech-Produkte lässt sich die Nummer dort nachschlagen und direkt verknüpfen.

== Notbremsen ==

Ein fehlerhafter oder halb geschriebener Export darf den Shop nicht leerräumen.
Deshalb bricht der Lauf ab, **ohne irgendetwas zu ändern**, wenn

* die Datei weniger Zeilen hat als eingestellt (Standard 20),
* mehr als X Prozent der verknüpften Produkte auf 0 fallen würden (Standard 30 %).

Zusätzlich warnt das Plugin, wenn die Datei älter ist als eingestellt, und
verschickt bei Problemen eine E-Mail.

Der *Sicherheitspuffer* zieht eine feste Stückzahl vom gemeldeten Lieferanten-
bestand ab und fängt so den Zeitversatz zwischen zwei Exporten auf — das ist das
wirksamste Mittel gegen Verkäufe von Ware, die beim Händler schon weg ist.

== Zugangsdaten (nur beim WebDAV-Weg nötig) ==

Wer statt des Freigabe-Links ein Konto nutzen will, sollte in Nextcloud unter
*Einstellungen → Sicherheit* ein **App-Passwort** anlegen und niemals das
Hauptpasswort eintragen. Am sichersten ist die `wp-config.php`:

    define( 'BLOOMTECH_DAV_USER', 'benutzername' );
    define( 'BLOOMTECH_DAV_PASS', 'app-passwort' );

Konstanten haben Vorrang vor der Datenbank. Wird stattdessen im Backend
gespeichert, liegt das Passwort mit AES-256 verschlüsselt in den Optionen und
wird nie im Klartext angezeigt.

== Changelog ==

= 1.0.0 =
* Erste Fassung.
