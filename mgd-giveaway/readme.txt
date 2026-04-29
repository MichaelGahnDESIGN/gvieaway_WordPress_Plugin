=== MGD Giveaway ===
Contributors: michaelgahndesign
Tags: download, forms, ebook, pdf, shortcode
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.0.23
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MGD Giveaway erstellt einfache Download-Formulare für Gratis-eBooks und PDFs.

== Description ==

Mit MGD Giveaway können im WordPress-Backend Formulare angelegt, Felder ergänzt und Dateien aus der Mediathek als Download hinterlegt werden. Das Formular wird per Shortcode eingebunden. Nach erfolgreicher Anmeldung wird der Download-Button direkt anstelle des Formulars angezeigt. Download-Links werden maskiert ausgegeben und über eine geschützte Kopie ausgeliefert. Optional kann Double-Opt-In aktiviert werden.

Anmeldungen werden in der Mail-Liste gespeichert und können als CSV importiert oder exportiert werden. Neue Anmeldungen werden an die in den Einstellungen hinterlegte Empfängeradresse gesendet. Ein Log-Reiter protokolliert wichtige Aktionen und bietet Suche, Filter, Export, Speicheranzeige und Leeren-Funktion.

Der Formular-Editor enthält einen modernen Builder mit Tabs, Element-Palette, Formular-Canvas, Drag & Drop Feldkarten, Feld-Inspector, Datenschutz-Element und integriertem Spam-Schutz mit Honeypot und Zeitprüfung.

== Installation ==

1. Plugin-Ordner `mgd-giveaway` nach `wp-content/plugins/` kopieren oder ZIP hochladen.
2. Plugin aktivieren.
3. Unter `MGD Giveaway` ein Formular erstellen.
4. Shortcode wie `[mgd_giveaway id="123"]` in eine Seite einfügen.

== Changelog ==

= 0.0.23 =
* Frontend-Absenden cache-toleranter gemacht und Button-Farben robuster gegen Theme-Styles geschützt.

= 0.0.22 =
* Sichtbare deutsche Texte und Dokumentation auf UTF-8-Umlaute umgestellt.

= 0.0.21 =
* Mail-Liste um Ansehen-Button mit Popup für Nutzerdaten erweitert und JSON-Daten aus der Tabelle entfernt.

= 0.0.20 =
* Formular-Abstände im Frontend und in der Backend-Vorschau kompakter und konsistenter gestaltet.

= 0.0.19 =
* Absenden-Button und Download-Button textlich getrennt; Spam-Mindestzeit für bessere Bedienbarkeit reduziert.

= 0.0.18 =
* Datenschutz-Feld um eine eigene URL zur Datenschutzerklärung erweitert und Popup-Ausgabe darauf umgestellt.

= 0.0.17 =
* Feldreihenfolge beim Speichern stabilisiert, damit das im Inspector ausgewählte Feld nicht ans Ende rutscht.

= 0.0.16 =
* Frontend- und Backend-Vorschau-Styling angeglichen, damit Datenschutz-Link nicht wie der Download-Button gestylt wird.

= 0.0.15 =
* Backend-Vorschau mit eigenen Formular-Styles verbessert, damit Felder sauber untereinander angezeigt werden.

= 0.0.14 =
* Speichern-Buttons im Formular-Editor zu echten Submit-Buttons gemacht, damit Speichern auch ohne Admin-JavaScript funktioniert.

= 0.0.13 =
* Backend-Vorschau auf statische Ausgabe umgestellt, damit sie beim Speichern keinen Frontend-Submit auslösen kann.

= 0.0.12 =
* Speichern im Formular-Editor robuster gemacht, damit versteckte Tab-Felder den Submit nicht blockieren.

= 0.0.11 =
* Datenschutz-Element zeigt im Frontend einen Link zur Datenschutzerklärung.
* Datenschutzerklärung wird lokal in einem Popup aus der WordPress-Datenschutzseite angezeigt.

= 0.0.10 =
* Speichern-Feedback im Formular-Editor deutlicher gemacht und aktiven Tab nach dem Speichern beibehalten.
* Hilfetexte im Editor unter die zugehoerigen Felder gesetzt.

= 0.0.9 =
* Geschützte Download-Kopie mit Zugriffsschutz ergänzt.
* Optionales Double-Opt-In pro Formular ergänzt.
* Design-Einstellungen für Frontend-Formulare ergänzt.
* DSGVO-Werkzeuge für einzelnen Kontakt-Export und Kontakt-Löschung ergänzt.

= 0.0.8 =
* Download-Links werden über einen signierten Plugin-Link maskiert.
* Dateien werden vom Plugin ausgeliefert, ohne im Button oder in der E-Mail den direkten Mediathek-Pfad zu zeigen.

= 0.0.7 =
* Formular-Editor in übersichtliche Tabs für Felder, Formular, Download, E-Mail und Vorschau aufgeteilt.
* Builder-Ansicht verbreitert und Einstellungen aus dem Hauptscreen herausgenommen.

= 0.0.6 =
* Formular-Editor zu einem modernen Builder mit Element-Palette, Canvas und Feld-Inspector umgebaut.
* Felder können per Klick oder Drag & Drop aus der Palette hinzugefuegt und im Canvas sortiert werden.

= 0.0.5 =
* Download-Erfolgsmeldung wird nach der Anmeldung inline anstelle des Formulars angezeigt.
* Weiterleitung zur separaten Erfolgsseite durch sicheren Ruecksprung zur Ursprungsseite ersetzt.

= 0.0.4 =
* Formular-Editor modernisiert: Feldkarten, Element-Palette und Drag & Drop Sortierung.
* Neues Datenschutz-Element ergänzt.
* Spam-Schutz mit Honeypot und Zeitprüfung ergänzt.

= 0.0.3 =
* Sicherheitsverbesserungen: CSV-Formel-Schutz beim Export, CSV-Größen- und Zeilenlimit beim Import, strengere E-Mail-Validierung.

= 0.0.2 =
* Mail-Liste mit CSV Import und Export ergänzt.
* Benachrichtigung neuer Anmeldungen an einstellbaren Empfänger ergänzt.
* Log-Reiter mit Suche, Filter, Export, Speicheranzeige und Leeren-Funktion ergänzt.
* Datenschutz-Hinweis zu lokalen Assets dokumentiert.

= 0.0.1 =
* Erste Version mit Formular-Editor, Shortcode, Download-Anzeige, SMTP/PHP-Mail-Einstellung und Credits.
