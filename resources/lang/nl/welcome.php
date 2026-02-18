<?php

return [

    'title' => 'CatLab Drinks',
    'tagline' => 'Open-source barautomatisering & kassasysteem',
    'subtitle' => 'Een modern, zelf-gehost kassasysteem ontworpen voor evenementen, feesten en horeca — met NFC-betalingen, smartphone-bestellingen en realtime verkoopoverzichten.',
    'open_web_app' => 'Open webapp',
    'install_android' => 'Installeer Android-app',
    'view_on_github' => 'Bekijk op GitHub',

    'why_title' => 'Waarom CatLab Drinks?',
    'why_intro' => 'Een bar runnen op een evenement hoeft niet te betekenen dat je met cash worstelt, wisselgeld verkeerd telt of het overzicht van de verkoop verliest. CatLab Drinks geeft je een compleet digitaal barmanagement-systeem dat je in enkele minuten op je eigen server kunt installeren.',
    'why_1_title' => 'Elimineer fouten',
    'why_1_desc' => 'Geen verkeerd geteld wisselgeld of onjuiste totalen meer. Elke bestelling wordt digitaal bijgehouden met nauwkeurige prijzen.',
    'why_2_title' => 'Realtime verkoopoverzicht',
    'why_2_desc' => 'Zie precies wat er wordt verkocht, hoeveel omzet je maakt en waar je bestellingen vandaan komen — allemaal in realtime.',
    'why_3_title' => 'Smartphone-bestellingen',
    'why_3_desc' => 'Laat je deelnemers drankjes bestellen vanaf hun eigen telefoon. Bestellingen gaan rechtstreeks naar de bar — geen wachtrij.',
    'why_4_title' => 'NFC-kaartbetalingen',
    'why_4_desc' => 'Geef prepaid NFC-kaarten uit voor contactloze betalingen. Opwaarderen, betalen en saldo\'s bijhouden — geen contant geld nodig.',
    'why_5_title' => 'Werkt offline',
    'why_5_desc' => 'Slecht WiFi op de locatie? Geen probleem. Het kassasysteem blijft offline werken en synchroniseert wanneer de verbinding hersteld is.',
    'why_6_title' => 'Zelf-gehost & open source',
    'why_6_desc' => 'Je gegevens blijven op jouw server. Installeer je eigen instantie, pas het aan naar je behoeften en behoud volledige controle.',

    'deploy_title' => 'Installeer je eigen instantie',
    'deploy_intro' => 'CatLab Drinks is ontworpen om zelf te hosten. Je installeert het op je eigen server en behoudt volledige controle over je gegevens en configuratie. Aan de slag gaan is eenvoudig:',
    'deploy_step_1' => 'Kloon de repository van GitHub',
    'deploy_step_2' => 'Configureer je omgeving en database',
    'deploy_step_3' => 'Voer migraties uit en bouw de frontend-assets',
    'deploy_step_4' => 'Maak een account aan en stel je eerste evenement in',
    'deploy_docker' => 'Een Docker Compose-configuratie is inbegrepen voor snelle installatie. Bekijk de repository voor gedetailleerde installatie-instructies.',

    'nfc_title' => 'Hoe NFC-kaarten werken',
    'nfc_intro' => 'CatLab Drinks implementeert een gesloten NFC-betalingssysteem met NTAG213-chips. Zo werkt het technisch:',
    'nfc_1_title' => 'Kaartstructuur',
    'nfc_1_desc' => 'Elke NTAG213 NFC-chip slaat een uniek kaart-ID, saldo, transactieteller en een cryptografische handtekening op. Gegevens worden rechtstreeks naar de NDEF-compatibele geheugensectoren van de kaart geschreven.',
    'nfc_2_title' => 'Encryptie & integriteit',
    'nfc_2_desc' => 'Kaartgegevens worden beschermd met AES-encryptie met een organisatiebrede geheime sleutel. Elke transactie werkt het saldo en een rollende teller bij, die wordt ondertekend om manipulatie of replay-aanvallen te voorkomen. Kaarten van de ene organisatie kunnen niet bij een andere worden gebruikt.',
    'nfc_3_title' => 'Hardware-vereisten',
    'nfc_3_desc' => 'Je hebt een ACR122U (of compatibele) NFC-kaartlezer nodig bij elk verkooppunt, en NTAG213 NFC-tags voor elke deelnemer. Communicatie tussen de lezer en de POS-browser verloopt via een socket.io-verbinding door een lichtgewicht begeleidingsservice.',
    'nfc_4_title' => 'Offline-ondersteuning',
    'nfc_4_desc' => 'Omdat het saldo op de kaart zelf is opgeslagen, kunnen transacties worden verwerkt zelfs wanneer de internetverbinding onderbroken is. Transacties worden gesynchroniseerd met de server wanneer de verbinding hersteld is.',
    'nfc_companion' => 'De NFC-lezer begeleidingsservice draait op een Raspberry Pi of een machine met USB-toegang. Zie de',
    'nfc_companion_link' => 'NFC socket.io service-repository',
    'nfc_companion_after' => 'voor installatie-instructies.',

    'screenshots_title' => 'Screenshots',

    'license_title' => 'Licentie',
    'license_text' => 'CatLab Drinks is vrije software uitgebracht onder de GNU General Public License v3. Je bent vrij om het te gebruiken, aan te passen en te verspreiden.',
    'license_warranty' => 'DE SOFTWARE WORDT GELEVERD "ZOALS ZE IS", ZONDER ENIGE GARANTIE, EXPLICIET OF IMPLICIET, INCLUSIEF MAAR NIET BEPERKT TOT DE GARANTIES VAN VERKOOPBAARHEID, GESCHIKTHEID VOOR EEN BEPAALD DOEL EN NIET-INBREUK.',

    'language' => 'Taal',
];
