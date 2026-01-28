=== CampX Booking ===
Contributors: Upsidedown Webdesign
Tags: booking, calendar, camping, reservations, wp
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 1.3.5
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.txt

Modernes Buchungssystem für Campingplätze: Ressourcen/Parzellen/Zimmer, Kapazitäten, Mehrmonats-Kalender, HTML-E-Mail-Vorlagen, ICS-Sync und Anti-Überbuchung per eigener DB.

== Beschreibung ==
CampX Booking ist ein leichtgewichtiges, schnelles und modernes Booking-Plugin für Campingplätze – mit Fokus auf Ressourcen/Parzellen/Zimmer. Moderner Look, aber auf das Wesentliche reduziert.

**Features**
- Eigene Tabellen (`campx_bookings`, `campx_occupancy`) – stabil & schnell
- Anti-Überbuchung: atomare Reservierungen pro Nacht
- Admin-Workflow: Anfrage → bestätigt/abgelehnt/abgelaufen
- HTML-E-Mail-Vorlagen (editierbar im Backend, Platzhalter)
- Mehrmonats-Kalender (2–3 Monate) + Range-Picker
- Personen- und Einheiten-Limits, Mindestnächte
- ICS-Feeds für Outlook/Apple/Google

**Autor/Support**
Upsidedown Webdesign (Damian Trötschler)  
Luzernerstrasse 82A, 6030 Ebikon, Schweiz  
+41 79 929 84 01 – info@upsidedown-webdesign.ch  
https://upsidedown-webdesign.ch/

== Installation ==
1. Zip hochladen und aktivieren
2. Unter **CampX → Einstellungen** Farben, E-Mail-Vorlagen und Kalendereinstellungen setzen
3. Ressourcen anlegen (Kapazität, Limits, Mindestnächte)
4. Shortcodes auf einer Seite platzieren:
   - `[campx_booking_form id="RESOURCE_ID"]`
   - `[campx_calendar id="RESOURCE_ID"]`
   - `[campx_catalog]`

== Changelog ==
= 1.4.1 =
* Konsolidierte Fixes: stabiler Range-Picker, Live-Availability, One-Shortcode-Flow, Farben via CSS-Variablen

= 1.3.5 =
* Fix: Parse Errors (Admin/Frontend-Klassen sauber gekapselt)
* Fix: seed_samples() als public (Hook-fähig)
* Neu: Ressourcenkatalog `[campx_catalog]` (Cards, Bilder, Verfügbarkeitscheck)
* Neu: Buchungsformular mit Range-Datum, Personen, Einheiten, Kontaktfeldern
* Neu: Danke-Seite (einstellbar in Settings)
* Neu: Modernes CSS (Cards, Buttons)

= 1.3.4 =
* Fix: Aktivierungs-Stabilität (CPT-Menüs, Admin-Screen-Guards)
* Neu: Option „Beim Löschen alle Daten entfernen“ (opt-in)

= 1.3.3 =
* Fix: stabile Admin-Menüs (Ressourcen/Buchungen) als Unterpunkte von CampX
* Fix: sichereres load_plugin_textdomain Pfad-Handling
* Fix: CPT-Registrierung vereinfacht (Menü-Verlinkung via Admin-Menü)

= 1.3.2 =
* Backend „Buchungen“: eigene Spalten, Auto-Titel, Editor ausgeblendet
* Einstellung: Danke-Seite auswählen (Redirect nach Anfrage)

= 1.3.1 =
* Ressourcen-Katalog mit Datumsprüfung ([campx_catalog])
* Seed: Stellplatz Zelt, Doppelzimmer, Einzelzimmer, Schlafsaal
* Backend: CPT-Menü unter CampX + klare Metaboxen

= 1.3.0 =
* Eigene Tabellen (Anti-Überbuchung), Mehrmonats-Kalender, HTML-Mailvorlagen, Auto-Expiry, A11y, .pot

== Lizenz ==
GPL-2.0+
