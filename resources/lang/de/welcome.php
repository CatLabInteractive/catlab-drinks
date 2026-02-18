<?php

return [

    'title' => 'CatLab Drinks',
    'tagline' => 'Open-Source Bar-Automatisierung & Kassensystem',
    'subtitle' => 'Ein modernes, selbst gehostetes Kassensystem für Veranstaltungen, Partys und Gastronomie — mit NFC-Zahlungsunterstützung, Smartphone-Bestellungen und Echtzeit-Verkaufsverfolgung.',
    'open_web_app' => 'Web-App öffnen',
    'install_android' => 'Android-App installieren',
    'view_on_github' => 'Auf GitHub ansehen',

    'why_title' => 'Warum CatLab Drinks?',
    'why_intro' => 'Eine Bar bei einer Veranstaltung zu betreiben sollte nicht bedeuten, mit Bargeld zu jonglieren, Wechselgeld falsch zu zählen oder den Überblick über die Verkäufe zu verlieren. CatLab Drinks bietet Ihnen ein komplettes digitales Bar-Management-System, das Sie in wenigen Minuten auf Ihrem eigenen Server einrichten können.',
    'why_1_title' => 'Fehler eliminieren',
    'why_1_desc' => 'Kein falsch gezähltes Wechselgeld oder fehlerhafte Summen mehr. Jede Bestellung wird digital mit korrekten Preisen erfasst.',
    'why_2_title' => 'Echtzeit-Verkaufsverfolgung',
    'why_2_desc' => 'Sehen Sie genau, was sich verkauft, wie viel Umsatz Sie machen und woher Ihre Bestellungen kommen — alles in Echtzeit.',
    'why_3_title' => 'Smartphone-Bestellungen',
    'why_3_desc' => 'Lassen Sie Ihre Teilnehmer Getränke von ihrem eigenen Handy bestellen. Bestellungen gehen direkt an die Bar — kein Anstehen nötig.',
    'why_4_title' => 'NFC-Kartenzahlung',
    'why_4_desc' => 'Geben Sie Prepaid-NFC-Karten für bargeldloses Bezahlen aus. Aufladen, bezahlen und Guthaben verfolgen — kein Bargeld nötig.',
    'why_5_title' => 'Funktioniert offline',
    'why_5_desc' => 'Schlechtes WLAN am Veranstaltungsort? Kein Problem. Das Kassensystem funktioniert offline weiter und synchronisiert sich bei Wiederverbindung.',
    'why_6_title' => 'Selbst gehostet & Open Source',
    'why_6_desc' => 'Ihre Daten bleiben auf Ihrem Server. Richten Sie Ihre eigene Instanz ein, passen Sie sie nach Ihren Bedürfnissen an und behalten Sie die volle Kontrolle.',

    'deploy_title' => 'Eigene Instanz einrichten',
    'deploy_intro' => 'CatLab Drinks ist für Selbst-Hosting konzipiert. Sie installieren es auf Ihrem eigenen Server und behalten die volle Kontrolle über Ihre Daten und Konfiguration. Der Einstieg ist unkompliziert:',
    'deploy_step_1' => 'Repository von GitHub klonen',
    'deploy_step_2' => 'Umgebung und Datenbank konfigurieren',
    'deploy_step_3' => 'Migrationen ausführen und Frontend-Assets erstellen',
    'deploy_step_4' => 'Konto erstellen und erste Veranstaltung einrichten',
    'deploy_docker' => 'Eine Docker-Compose-Konfiguration ist für die schnelle Einrichtung enthalten. Im Repository finden Sie detaillierte Installationsanleitungen.',

    'nfc_title' => 'Wie NFC-Karten funktionieren',
    'nfc_intro' => 'CatLab Drinks implementiert ein geschlossenes NFC-Zahlungssystem mit NTAG213-Chips. So funktioniert es technisch:',
    'nfc_1_title' => 'Kartenstruktur',
    'nfc_1_desc' => 'Jeder NTAG213 NFC-Chip speichert eine eindeutige Karten-ID, ein Guthaben, einen Transaktionszähler und eine kryptografische Signatur. Daten werden direkt in die NDEF-kompatiblen Speichersektoren der Karte geschrieben.',
    'nfc_2_title' => 'Verschlüsselung & Integrität',
    'nfc_2_desc' => 'Kartendaten werden durch AES-Verschlüsselung mit einem organisationsweiten Geheimschlüssel geschützt. Jede Transaktion aktualisiert das Guthaben und einen rollierenden Zähler, der signiert wird, um Manipulation oder Replay-Angriffe zu verhindern. Karten einer Organisation können nicht bei einer anderen verwendet werden.',
    'nfc_3_title' => 'Hardware-Anforderungen',
    'nfc_3_desc' => 'Sie benötigen einen ACR122U (oder kompatiblen) NFC-Kartenleser an jedem Verkaufspunkt und NTAG213 NFC-Tags für jeden Teilnehmer. Die Kommunikation zwischen Leser und POS-Browser erfolgt über eine socket.io-Verbindung durch einen leichtgewichtigen Begleitdienst.',
    'nfc_4_title' => 'Offline-Unterstützung',
    'nfc_4_desc' => 'Da das Guthaben auf der Karte selbst gespeichert wird, können Transaktionen auch bei unterbrochener Internetverbindung verarbeitet werden. Transaktionen werden bei Wiederherstellung der Verbindung mit dem Server synchronisiert.',
    'nfc_companion' => 'Der NFC-Leser-Begleitdienst läuft auf einem Raspberry Pi oder einem beliebigen Rechner mit USB-Zugang. Siehe das',
    'nfc_companion_link' => 'NFC socket.io Service-Repository',
    'nfc_companion_after' => 'für Installationsanleitungen.',

    'screenshots_title' => 'Screenshots',

    'license_title' => 'Lizenz',
    'license_text' => 'CatLab Drinks ist freie Software, veröffentlicht unter der GNU General Public License v3. Sie dürfen sie frei verwenden, ändern und verbreiten.',
    'license_warranty' => 'DIE SOFTWARE WIRD "WIE BESEHEN" BEREITGESTELLT, OHNE JEGLICHE GEWÄHRLEISTUNG, WEDER AUSDRÜCKLICH NOCH STILLSCHWEIGEND, EINSCHLIESSLICH, ABER NICHT BESCHRÄNKT AUF DIE GEWÄHRLEISTUNGEN DER MARKTGÄNGIGKEIT, DER EIGNUNG FÜR EINEN BESTIMMTEN ZWECK UND DER NICHTVERLETZUNG.',

    'language' => 'Sprache',
];
