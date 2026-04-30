# Versionen

## 2026-04-30 - Version 0.0.33

Beschreibung: E-Mail-Design im Formular-Backend anpassbar gemacht.
Begründung: Bestätigungs- und Download-E-Mails sollen ohne Codeänderung an Website/Branding angepasst werden können.
Betroffene Bereiche: Formular-Editor, E-Mail-Tab, E-Mail-Template, Dokumentation.
Rücknahme: Version 0.0.32 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-30 - Version 0.0.32

Beschreibung: Bestätigungs- und Download-E-Mails als gebrandete HTML-E-Mails im Stil der Website gestaltet.
Begründung: Die bisherigen reinen Textmails wirkten unpassend zur Landingpage und hatten keine klare Handlungsaufforderung.
Betroffene Bereiche: E-Mail-Versand, Double-Opt-In, Download-Mail, Dokumentation.
Rücknahme: Version 0.0.31 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-30 - Version 0.0.31

Beschreibung: Datenschutz-URL beim Speichern stabilisiert und Popup-Ausgabe strikt an die eingetragene URL gebunden.
Begründung: Der Inhalt des Datenschutz-Popups muss aus der im Datenschutz-Feld eingetragenen URL stammen und darf nicht unbeabsichtigt auf eine andere WordPress-Seite zurückfallen.
Betroffene Bereiche: Formular-Editor, Datenschutz-Feld, Datenschutz-Popup, Dokumentation.
Rücknahme: Version 0.0.30 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-30 - Version 0.0.30

Beschreibung: Rekursives Formular-Rendering im Datenschutz-Popup verhindert.
Begründung: Auf der Live-Seite lädt das Datenschutz-Popup erneut die Landingpage und damit ein zweites Formular mit derselben ID. Das kann den sichtbaren Absenden-Button an das falsche Formular binden.
Betroffene Bereiche: Frontend-Shortcode, Datenschutz-Popup, Formular-Submit, Dokumentation.
Rücknahme: Version 0.0.29 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-30 - Version 0.0.29

Beschreibung: Absenden-Button per Formular-ID fest mit dem Formular verknüpft.
Begründung: Elementor oder der Browser kann den Button im DOM außerhalb des Formulars einsortieren; ohne explizite Verknüpfung wird dann nichts abgesendet.
Betroffene Bereiche: Frontend-Shortcode, Button-Rendering, Formular-Submit, Dokumentation.
Rücknahme: Version 0.0.28 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-30 - Version 0.0.28

Beschreibung: Absenden-Button mit Inline-Abstand und direktem Submit-Fallback abgesichert.
Begründung: Theme-/Cache-Konflikte konnten verhindern, dass Abstand und JavaScript-Verhalten sichtbar wurden.
Betroffene Bereiche: Frontend-Shortcode, Button-Rendering, Dokumentation.
Rücknahme: Version 0.0.27 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-30 - Version 0.0.27

Beschreibung: Abstand über dem Absenden-Button vergrößert.
Begründung: Der Button soll optisch mehr Luft zum Datenschutztext haben.
Betroffene Bereiche: Frontend CSS, Backend-Vorschau, Dokumentation.
Rücknahme: Version 0.0.26 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-30 - Version 0.0.26

Beschreibung: Frontend-Absenden per JavaScript-Fallback robuster gemacht und sichtbare Fehlermeldung ergänzt.
Begründung: Der Absenden-Button soll auch dann zuverlässig reagieren, wenn Theme- oder Builder-Skripte normale Formular-Submits stören.
Betroffene Bereiche: Frontend JavaScript, Formular-Submit, Dokumentation.
Rücknahme: Version 0.0.25 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-30 - Version 0.0.25

Beschreibung: Formularspeicherung, Feldnamen, E-Mail-Validierung, Mail-Filter und Token-Gültigkeit robuster gemacht.
Begründung: Edge Cases sollen nicht zu kaputten Formularen, überschriebenen Daten, ungültigen E-Mail-Prozessen oder dauerhaft gültigen Links führen.
Betroffene Bereiche: Formular-Editor, Frontend-Submit, E-Mail-Versand, Download- und Bestätigungslinks, Dokumentation.
Rücknahme: Version 0.0.24 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-30 - Version 0.0.24

Beschreibung: Absenden ohne Mindestwartezeit erlaubt, Button-Design inline abgesichert und Farbfelder im Backend korrigiert.
Begründung: Schnelle Tests sollen nicht durch die Spam-Zeitprüfung scheitern; Theme-Styles sollen die gewählte Buttonfarbe nicht überschreiben; Farbfelder sollen im Backend als kompakte Picker erscheinen.
Betroffene Bereiche: Frontend Formularausgabe, Spam-Schutz, Design-Einstellungen, Admin CSS, Dokumentation.
Rücknahme: Version 0.0.23 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-30 - Version 0.0.23

Beschreibung: Frontend-Absenden cache-toleranter gemacht und Button-Farben robuster gegen Theme-Styles geschützt.
Begründung: Der Absenden-Button darf durch Seiten-Caches nicht blockiert werden; Design-Einstellungen sollen im Frontend sichtbar greifen.
Betroffene Bereiche: Frontend Formularausgabe, Spam-Schutz, Frontend CSS, Backend-Vorschau, Dokumentation.
Rücknahme: Version 0.0.22 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-30 - Version 0.0.22

Beschreibung: Sichtbare deutsche Texte und Dokumentation auf UTF-8-Umlaute umgestellt.
Begründung: Umlaute wie ä, ö, ü und ß sollen gemäß Projektregel korrekt dargestellt werden.
Betroffene Bereiche: Backend-Texte, Frontend-Texte, README, Dokumentation.
Rücknahme: Version 0.0.21 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-30 - Version 0.0.21

Beschreibung: Mail-Liste um Ansehen-Button mit Popup für Nutzerdaten erweitert und JSON-Daten aus der Tabelle entfernt.
Begründung: Personenbezogene Daten sollen im Backend übersichtlich einsehbar sein, ohne die Tabellenansicht zu überladen.
Betroffene Bereiche: Mail-Liste, Admin JavaScript, Admin CSS, Dokumentation.
Rücknahme: Version 0.0.20 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-30 - Version 0.0.20

Beschreibung: Formular-Abstände im Frontend und in der Backend-Vorschau kompakter und konsistenter gestaltet.
Begründung: Das Formular soll ruhiger wirken; Datenschutztext und Button-Abstand sollen optisch besser zum Layout passen.
Betroffene Bereiche: Frontend CSS, Admin CSS, Formular-Vorschau, Dokumentation.
Rücknahme: Version 0.0.19 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.19

Beschreibung: Absenden-Button und Download-Button textlich getrennt; Spam-Mindestzeit für bessere Bedienbarkeit reduziert.
Begründung: Der erste Button sendet zunächst die Formulardaten ab und soll nicht wie ein direkter Download wirken. Zu strenge Mindestzeit kann schnelles Absenden wie einen defekten Button erscheinen lassen.
Betroffene Bereiche: Formular-Editor, Frontend Formularausgabe, Spam-Schutz, Vorschau, Dokumentation.
Rücknahme: Version 0.0.18 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.18

Beschreibung: Datenschutz-Feld um eine eigene URL zur Datenschutzerklärung erweitert und Popup-Ausgabe darauf umgestellt.
Begründung: Das Popup soll nicht versehentlich die falsche WordPress-Datenschutzseite verwenden, sondern die im Feld konfigurierte Datenschutzerklärung anzeigen.
Betroffene Bereiche: Formular-Editor, Datenschutz-Feld, Frontend Popup, Admin CSS, Frontend CSS, Dokumentation.
Rücknahme: Version 0.0.17 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.17

Beschreibung: Feldreihenfolge beim Speichern stabilisiert, damit das im Inspector ausgewählte Feld nicht ans Ende rutscht.
Begründung: Der Inspector verschiebt Feld-Einstellungen zur Bearbeitung in eine Seitenleiste; vor dem Submit müssen diese Inputs wieder in der Canvas-Reihenfolge stehen.
Betroffene Bereiche: Admin JavaScript, Formular-Editor, Feldspeicherung, Dokumentation.
Rücknahme: Version 0.0.16 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.16

Beschreibung: Frontend- und Backend-Vorschau-Styling angeglichen, damit der Datenschutz-Link nicht wie der Download-Button gestaltet wird.
Begründung: Nur der echte Formular-Submit soll das Button-Design erhalten; der Datenschutz-Link soll in Vorschau und Frontend als Textlink erscheinen.
Betroffene Bereiche: Frontend CSS, Admin CSS, Formularausgabe, Dokumentation.
Rücknahme: Version 0.0.15 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.15

Beschreibung: Backend-Vorschau mit eigenen Formular-Styles verbessert, damit Felder sauber untereinander angezeigt werden.
Begründung: Die statische Vorschau soll visuell verständlich bleiben und nicht durch fehlende Frontend-Styles kaputt wirken.
Betroffene Bereiche: Backend Formular-Editor, Vorschau, Admin CSS, Dokumentation.
Rücknahme: Version 0.0.14 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.14

Beschreibung: Speichern-Buttons im Formular-Editor zu echten Submit-Buttons gemacht.
Begründung: Speichern soll auch funktionieren, wenn das Admin-JavaScript nicht geladen, blockiert oder noch veraltet aus dem Cache geladen wird.
Betroffene Bereiche: Backend Formular-Editor, Admin JavaScript, Dokumentation.
Rücknahme: Version 0.0.13 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.13

Beschreibung: Backend-Vorschau auf statische Formularausgabe umgestellt, damit beim Speichern kein Frontend-Submit ausgelöst wird.
Begründung: Die echte Shortcode-Ausgabe in der Vorschau konnte das Backend-Speichern stören und Pflichtfeldfehler auslösen.
Betroffene Bereiche: Backend Formular-Editor, Vorschau, Dokumentation.
Rücknahme: Version 0.0.12 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.12

Beschreibung: Speichern im Formular-Editor robuster gemacht und Browser-Validierung für versteckte Tab-Felder deaktiviert.
Begründung: Der Speichern-Button soll unabhängig vom aktiven Tab zuverlässig absenden.
Betroffene Bereiche: Backend Formular-Editor, Admin JavaScript, Dokumentation.
Rücknahme: Version 0.0.11 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.11

Beschreibung: Datenschutz-Element im Frontend um Link und Popup zur WordPress-Datenschutzerklärung erweitert.
Begründung: Nutzer sollen die Datenschutzerklärung direkt beim Datenschutz-Hinweis lesen können, ohne die Seite zu verlassen.
Betroffene Bereiche: Frontend Formularausgabe, Frontend JavaScript, Frontend CSS, Dokumentation.
Rücknahme: Version 0.0.10 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.10

Beschreibung: Speichern-Feedback im Formular-Editor verbessert, aktiven Tab nach dem Speichern beibehalten und Hilfetexte unter die Felder gesetzt.
Begründung: Rückmeldung und Lesbarkeit im Backend verbessern.
Betroffene Bereiche: Backend Formular-Editor, Admin JavaScript, Admin CSS, Dokumentation.
Rücknahme: Version 0.0.9 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.9

Beschreibung: Geschützte Download-Kopie, optionales Double-Opt-In, Frontend-Design-Einstellungen und DSGVO-Kontaktaktionen umgesetzt.
Begründung: Download-Auslieferung soll besser geschützt, E-Mail-Einwilligung optional bestätigt, Formularoptik steuerbar und personenbezogene Daten besser verwaltbar werden.
Betroffene Bereiche: Datenbank, Formular-Editor, Frontend-Ausgabe, Download-Auslieferung, E-Mail-Prozess, Mail-Liste, Dokumentation.
Rücknahme: Version 0.0.8 aus Git-Historie oder Backup-ZIP wiederherstellen; Datenbankspalten `status`, `confirmed_at` und `download_count` können bestehen bleiben.

## 2026-04-29 - Version 0.0.8

Beschreibung: Download-Links werden über einen signierten Plugin-Link maskiert und nicht mehr als direkter Mediathek-Pfad in Button oder E-Mail ausgegeben.
Begründung: Empfänger sollen keine sichtbaren WordPress-Upload-Pfade wie `wp-content/uploads` sehen.
Betroffene Bereiche: Frontend Download-Link, Download-E-Mail, Download-Auslieferung, Logs, Dokumentation.
Rücknahme: Version 0.0.7 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.7

Beschreibung: Formular-Editor in Tabs für Felder, Formular, Download, E-Mail und Vorschau aufgeteilt.
Begründung: Die Builder-Ansicht soll übersichtlicher werden und nicht gleichzeitig alle Formular-, Download- und E-Mail-Einstellungen anzeigen.
Betroffene Bereiche: Backend Formular-Editor, Admin JavaScript, Admin CSS, Dokumentation.
Rücknahme: Version 0.0.6 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.6

Beschreibung: Formular-Editor zu einem modernen Builder mit Element-Palette, Canvas, Drag & Drop und Feld-Inspector umgebaut.
Begründung: Die Formularerstellung soll sich eher wie WPForms anfühlen und weniger wie eine technische Feldliste.
Betroffene Bereiche: Backend Formular-Editor, Admin JavaScript, Admin CSS, Dokumentation.
Rücknahme: Version 0.0.5 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.5

Beschreibung: Erfolgsmeldung und Download-Button werden nach der Anmeldung direkt im Shortcode-Bereich anstelle des Formulars angezeigt.
Begründung: Die Nutzer sollen auf der ursprünglichen Landingpage bleiben und nicht auf eine separate technische Erfolgsseite wechseln.
Betroffene Bereiche: Frontend Formularausgabe, Formular-Submit-Weiterleitung, Assets, Dokumentation.
Rücknahme: Version 0.0.4 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.4

Beschreibung: Formular-Editor mit Drag & Drop Feldkarten modernisiert, Datenschutz-Element und Spam-Schutz ergänzt.
Begründung: Bedienung im Backend verbessern und Frontend-Anmeldungen besser gegen einfache Bots schützen.
Betroffene Bereiche: Backend Formular-Editor, Frontend Formularausgabe, Formularvalidierung, Assets, Dokumentation.
Rücknahme: Version 0.0.3 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.3

Beschreibung: Sicherheitsverbesserungen für CSV-Import/Export und Formular-E-Mail-Validierung umgesetzt.
Begründung: Vor Live-Installation müssen CSV-Formel-Injection, sehr große CSV-Uploads und ungültige Pflicht-E-Mail-Adressen reduziert werden.
Betroffene Bereiche: CSV Import, CSV Export, Frontend Formularvalidierung, Dokumentation.
Rücknahme: Version 0.0.2 aus Git-Historie oder Backup-ZIP wiederherstellen.

## 2026-04-29 - Version 0.0.2

Beschreibung: Mail-Liste mit CSV Import/Export, Benachrichtigungs-Empfänger, Log-Reiter mit Suche/Filter/Export/Speicheranzeige/Leeren und lokale Asset-Dokumentation ergänzt.
Begründung: Anforderungen an Empfängerverwaltung, Nachvollziehbarkeit und Datenschutz wurden erweitert.
Betroffene Bereiche: WordPress Plugin, Backend, E-Mail, CSV, Logs, Dokumentation.
Rücknahme: Version 0.0.1 aus Git-Historie oder ZIP wiederherstellen; bei Datenbank-Rollback Tabellen `wp_mgd_giveaway_submissions` und `wp_mgd_giveaway_logs` prüfen.

## 2026-04-29 - Version 0.0.1

Beschreibung: Erste Version von MGD Giveaway erstellt.
Begründung: Basis-Plugin für Giveaway-Formulare mit PDF/eBook-Download.
Betroffene Bereiche: WordPress Plugin, Backend, Frontend, E-Mail, Dokumentation.
Rücknahme: Plugin deaktivieren und Ordner `mgd-giveaway` entfernen; bei Bedarf Datenbanktabelle `wp_mgd_giveaway_submissions` prüfen.
