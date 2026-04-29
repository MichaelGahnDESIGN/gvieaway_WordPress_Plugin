# MGD Giveaway

Ein einfaches WordPress Plugin von Michael Gahn DESIGN zum Erstellen von Formularen, die nach Anmeldung einen Download-Link fuer Gratis-eBooks oder PDFs bereitstellen.

## Funktionen

- Backend-Dashboard mit Formularliste und Statistik
- Formular erstellen, bearbeiten, duplizieren und loeschen
- Feldtypen: Text, E-Mail, Zahl, Datum, Checkbox, Textarea, Datenschutz
- Moderner Formular-Editor mit Feldkarten, Element-Palette und Drag & Drop Sortierung
- Spam-Schutz mit Honeypot und Zeitpruefung
- Datei-Auswahl ueber die WordPress-Mediathek
- Shortcode-Ausgabe im Frontend: `[mgd_giveaway id="123"]`
- Download-Button nach erfolgreicher Anmeldung direkt anstelle des Formulars
- Optionaler Versand des Download-Links per E-Mail
- Neue Anmeldungen per E-Mail an den in den Einstellungen festgelegten Empfaenger
- E-Mail-Versand ueber WordPress/PHP-Mail oder SMTP
- Mail-Liste im Backend mit Suche, CSV-Import und CSV-Export
- Log-Reiter mit Suche, Level-Filter, CSV-Export, Speicheranzeige und Leeren-Button
- Backend-Reiter `Credits` mit Impressum und Tool-/Lizenzuebersicht

## Installation

1. ZIP-Datei aus `dist/mgd-giveaway-v0.0.5.zip` in WordPress hochladen.
2. Plugin aktivieren.
3. Unter `MGD Giveaway` ein Formular anlegen.
4. Den angezeigten Shortcode in eine Seite oder einen Beitrag einfuegen.

## Datenschutz und Recht

Das Plugin speichert Formularanmeldungen lokal in der WordPress-Datenbank. E-Mail-Adressen und Formulardaten koennen personenbezogene Daten sein. Vor produktiver Nutzung muessen Datenschutzerklaerung, Einwilligungstexte, Speicherfristen und E-Mail-Prozesse fuer Deutschland/EU rechtlich geprueft werden.

## Kosten und Lizenzen

Die erste Version nutzt nur kostenlose, kommerziell nutzbare Komponenten:

- WordPress, GPL-2.0-or-later
- PHP, PHP License
- PHPMailer, LGPL-2.1-only, ueber WordPress
- Dashicons, GPL-2.0-or-later, ueber WordPress

## Datenschutzkonforme lokale Assets

Das Plugin laedt keine externen Schriften, Icons, Skripte oder Stylesheets. Genutzt werden lokale Plugin-Dateien, WordPress-Core-APIs und lokale SVG-Logos im Plugin-Ordner. Schriften werden als System-Fonts des Endgeraets referenziert und nicht von Drittservern geladen.

## CSV Mail-Liste

Der Import erwartet eine CSV-Datei mit Kopfzeile. Mindestens die Spalte `email` muss vorhanden sein. Weitere Spalten werden als Daten gespeichert. Der Export enthaelt `id`, `form_id`, `email`, `data` und `created_at`. CSV-Importe sind auf 2 MB und 5000 Zeilen begrenzt. CSV-Exports schuetzen Werte, die in Tabellenkalkulationen als Formel interpretiert werden koennten.

## Version

Aktuelle Version: `0.0.5`

## Autor

Michael Gahn DESIGN  
Website: https://Michael-Gahn.de  
Impressum: https://michael-gahn.de/impressum
