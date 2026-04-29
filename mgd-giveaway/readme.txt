=== MGD Giveaway ===
Contributors: michaelgahndesign
Tags: download, forms, ebook, pdf, shortcode
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.0.8
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MGD Giveaway erstellt einfache Download-Formulare fuer Gratis-eBooks und PDFs.

== Description ==

Mit MGD Giveaway koennen im WordPress-Backend Formulare angelegt, Felder ergaenzt und Dateien aus der Mediathek als Download hinterlegt werden. Das Formular wird per Shortcode eingebunden. Nach erfolgreicher Anmeldung wird der Download-Button direkt anstelle des Formulars angezeigt. Download-Links werden maskiert ausgegeben, damit kein direkter wp-content/uploads Pfad in Button oder E-Mail steht.

Anmeldungen werden in der Mail-Liste gespeichert und koennen als CSV importiert oder exportiert werden. Neue Anmeldungen werden an die in den Einstellungen hinterlegte Empfaengeradresse gesendet. Ein Log-Reiter protokolliert wichtige Aktionen und bietet Suche, Filter, Export, Speicheranzeige und Leeren-Funktion.

Der Formular-Editor enthaelt einen modernen Builder mit Tabs, Element-Palette, Formular-Canvas, Drag & Drop Feldkarten, Feld-Inspector, Datenschutz-Element und integriertem Spam-Schutz mit Honeypot und Zeitpruefung.

== Installation ==

1. Plugin-Ordner `mgd-giveaway` nach `wp-content/plugins/` kopieren oder ZIP hochladen.
2. Plugin aktivieren.
3. Unter `MGD Giveaway` ein Formular erstellen.
4. Shortcode wie `[mgd_giveaway id="123"]` in eine Seite einfuegen.

== Changelog ==

= 0.0.8 =
* Download-Links werden ueber einen signierten Plugin-Link maskiert.
* Dateien werden vom Plugin ausgeliefert, ohne im Button oder in der E-Mail den direkten Mediathek-Pfad zu zeigen.

= 0.0.7 =
* Formular-Editor in uebersichtliche Tabs fuer Felder, Formular, Download, E-Mail und Vorschau aufgeteilt.
* Builder-Ansicht verbreitert und Einstellungen aus dem Hauptscreen herausgenommen.

= 0.0.6 =
* Formular-Editor zu einem modernen Builder mit Element-Palette, Canvas und Feld-Inspector umgebaut.
* Felder koennen per Klick oder Drag & Drop aus der Palette hinzugefuegt und im Canvas sortiert werden.

= 0.0.5 =
* Download-Erfolgsmeldung wird nach der Anmeldung inline anstelle des Formulars angezeigt.
* Weiterleitung zur separaten Erfolgsseite durch sicheren Ruecksprung zur Ursprungsseite ersetzt.

= 0.0.4 =
* Formular-Editor modernisiert: Feldkarten, Element-Palette und Drag & Drop Sortierung.
* Neues Datenschutz-Element ergaenzt.
* Spam-Schutz mit Honeypot und Zeitpruefung ergaenzt.

= 0.0.3 =
* Sicherheitsverbesserungen: CSV-Formel-Schutz beim Export, CSV-Groessen- und Zeilenlimit beim Import, strengere E-Mail-Validierung.

= 0.0.2 =
* Mail-Liste mit CSV Import und Export ergaenzt.
* Benachrichtigung neuer Anmeldungen an einstellbaren Empfaenger ergaenzt.
* Log-Reiter mit Suche, Filter, Export, Speicheranzeige und Leeren-Funktion ergaenzt.
* Datenschutz-Hinweis zu lokalen Assets dokumentiert.

= 0.0.1 =
* Erste Version mit Formular-Editor, Shortcode, Download-Anzeige, SMTP/PHP-Mail-Einstellung und Credits.
