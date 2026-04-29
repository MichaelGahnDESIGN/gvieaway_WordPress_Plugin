# Versionen

## 2026-04-29 - Version 0.0.11

Beschreibung: Datenschutz-Element im Frontend um Link und Popup zur WordPress-Datenschutzerklaerung erweitert.
Begruendung: Nutzer sollen die Datenschutzerklaerung direkt beim Datenschutz-Hinweis lesen koennen, ohne die Seite zu verlassen.
Betroffene Bereiche: Frontend Formularausgabe, Frontend JavaScript, Frontend CSS, Dokumentation.
Ruecknahme: Version 0.0.10 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.10

Beschreibung: Speichern-Feedback im Formular-Editor verbessert, aktiven Tab nach dem Speichern beibehalten und Hilfetexte unter die Felder gesetzt.
Begruendung: Rueckmeldung und Lesbarkeit im Backend verbessern.
Betroffene Bereiche: Backend Formular-Editor, Admin JavaScript, Admin CSS, Dokumentation.
Ruecknahme: Version 0.0.9 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.9

Beschreibung: Geschuetzte Download-Kopie, optionales Double-Opt-In, Frontend-Design-Einstellungen und DSGVO-Kontaktaktionen umgesetzt.
Begruendung: Download-Auslieferung soll besser geschuetzt, E-Mail-Einwilligung optional bestaetigt, Formularoptik steuerbar und personenbezogene Daten besser verwaltbar werden.
Betroffene Bereiche: Datenbank, Formular-Editor, Frontend-Ausgabe, Download-Auslieferung, E-Mail-Prozess, Mail-Liste, Dokumentation.
Ruecknahme: Version 0.0.8 aus Git-Historie oder Backup-ZIP wiederherstellen; Datenbankspalten `status`, `confirmed_at` und `download_count` koennen bestehen bleiben.

## 2026-04-29 - Version 0.0.8

Beschreibung: Download-Links werden ueber einen signierten Plugin-Link maskiert und nicht mehr als direkter Mediathek-Pfad in Button oder E-Mail ausgegeben.
Begruendung: Empfaenger sollen keine sichtbaren WordPress-Upload-Pfade wie `wp-content/uploads` sehen.
Betroffene Bereiche: Frontend Download-Link, Download-E-Mail, Download-Auslieferung, Logs, Dokumentation.
Ruecknahme: Version 0.0.7 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.7

Beschreibung: Formular-Editor in Tabs fuer Felder, Formular, Download, E-Mail und Vorschau aufgeteilt.
Begruendung: Die Builder-Ansicht soll uebersichtlicher werden und nicht gleichzeitig alle Formular-, Download- und E-Mail-Einstellungen anzeigen.
Betroffene Bereiche: Backend Formular-Editor, Admin JavaScript, Admin CSS, Dokumentation.
Ruecknahme: Version 0.0.6 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.6

Beschreibung: Formular-Editor zu einem modernen Builder mit Element-Palette, Canvas, Drag & Drop und Feld-Inspector umgebaut.
Begruendung: Die Formularerstellung soll sich eher wie WPForms anfuehlen und weniger wie eine technische Feldliste.
Betroffene Bereiche: Backend Formular-Editor, Admin JavaScript, Admin CSS, Dokumentation.
Ruecknahme: Version 0.0.5 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.5

Beschreibung: Erfolgsmeldung und Download-Button werden nach der Anmeldung direkt im Shortcode-Bereich anstelle des Formulars angezeigt.
Begruendung: Die Nutzer sollen auf der urspruenglichen Landingpage bleiben und nicht auf eine separate technische Erfolgsseite wechseln.
Betroffene Bereiche: Frontend Formularausgabe, Formular-Submit-Weiterleitung, Assets, Dokumentation.
Ruecknahme: Version 0.0.4 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.4

Beschreibung: Formular-Editor mit Drag & Drop Feldkarten modernisiert, Datenschutz-Element und Spam-Schutz ergaenzt.  
Begruendung: Bedienung im Backend verbessern und Frontend-Anmeldungen besser gegen einfache Bots schuetzen.  
Betroffene Bereiche: Backend Formular-Editor, Frontend Formularausgabe, Formularvalidierung, Assets, Dokumentation.  
Ruecknahme: Version 0.0.3 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.3

Beschreibung: Sicherheitsverbesserungen fuer CSV-Import/Export und Formular-E-Mail-Validierung umgesetzt.  
Begruendung: Vor Live-Installation muessen CSV-Formel-Injection, sehr grosse CSV-Uploads und ungueltige Pflicht-E-Mail-Adressen reduziert werden.  
Betroffene Bereiche: CSV Import, CSV Export, Frontend Formularvalidierung, Dokumentation.  
Ruecknahme: Version 0.0.2 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.2

Beschreibung: Mail-Liste mit CSV Import/Export, Benachrichtigungs-Empfaenger, Log-Reiter mit Suche/Filter/Export/Speicheranzeige/Leeren und lokale Asset-Dokumentation ergaenzt.  
Begruendung: Anforderungen an Empfaengerverwaltung, Nachvollziehbarkeit und Datenschutz wurden erweitert.  
Betroffene Bereiche: WordPress Plugin, Backend, E-Mail, CSV, Logs, Dokumentation.  
Ruecknahme: Version 0.0.1 aus Git-Historie oder ZIP wiederherstellen; bei Datenbank-Rollback Tabellen `wp_mgd_giveaway_submissions` und `wp_mgd_giveaway_logs` pruefen.

## 2026-04-29 - Version 0.0.1

Beschreibung: Erste Version von MGD Giveaway erstellt.  
Begruendung: Basis-Plugin fuer Giveaway-Formulare mit PDF/eBook-Download.  
Betroffene Bereiche: WordPress Plugin, Backend, Frontend, E-Mail, Dokumentation.  
Ruecknahme: Plugin deaktivieren und Ordner `mgd-giveaway` entfernen; bei Bedarf Datenbanktabelle `wp_mgd_giveaway_submissions` pruefen.
