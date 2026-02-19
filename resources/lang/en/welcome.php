<?php

return [

    'title' => 'CatLab Drinks',
    'tagline' => 'Open-Source Bar Automation & POS System',
    'subtitle' => 'A modern, self-hosted point-of-sale system designed for events, parties, and hospitality — with NFC payment support, smartphone ordering, and real-time sales tracking.',
    'open_web_app' => 'Open Web App',
    'install_android' => 'Install Android App',
    'view_on_github' => 'View on GitHub',

    'why_title' => 'Why CatLab Drinks?',
    'why_intro' => 'Running a bar at an event shouldn\'t mean juggling cash, miscounting change, or losing track of sales. CatLab Drinks gives you a complete digital bar management system that you can deploy on your own server in minutes.',
    'why_1_title' => 'Eliminate errors',
    'why_1_desc' => 'No more miscounted change or incorrect totals. Every order is tracked digitally with accurate pricing.',
    'why_2_title' => 'Real-time sales tracking',
    'why_2_desc' => 'See exactly what\'s selling, how much revenue you\'re generating, and where your orders are coming from — all in real-time.',
    'why_3_title' => 'Smartphone ordering',
    'why_3_desc' => 'Let your attendees order drinks from their own phone. Orders go straight to the bar — no waiting in line.',
    'why_4_title' => 'NFC card payments',
    'why_4_desc' => 'Issue prepaid NFC cards for cashless payments. Top up, pay, and track balances — no cash needed.',
    'why_5_title' => 'Works offline',
    'why_5_desc' => 'Bad WiFi at the venue? No problem. The POS system continues to work offline and syncs when reconnected.',
    'why_6_title' => 'Self-hosted & open source',
    'why_6_desc' => 'Your data stays on your server. Deploy your own instance, customise it to your needs, and keep full control.',

    'deploy_title' => 'Deploy Your Own Instance',
    'deploy_intro' => 'CatLab Drinks is designed to be self-hosted. You deploy it on your own server, keeping full control over your data and configuration. Getting started is straightforward:',
    'deploy_step_1' => 'Clone the repository from GitHub',
    'deploy_step_2' => 'Configure your environment and database',
    'deploy_step_3' => 'Run migrations and build the frontend assets',
    'deploy_step_4' => 'Create an account and set up your first event',
    'deploy_docker' => 'A Docker Compose configuration is included for quick deployment. Check the repository for detailed setup instructions.',

    'nfc_title' => 'How NFC Cards Work',
    'nfc_intro' => 'CatLab Drinks implements a closed-loop NFC payment system using NTAG213 chips. Here\'s how it works technically:',
    'nfc_1_title' => 'Card structure',
    'nfc_1_desc' => 'Each NTAG213 NFC chip stores a unique card ID, balance, transaction counter, and a cryptographic signature. Data is written directly to the card\'s NDEF-compatible memory sectors.',
    'nfc_2_title' => 'Encryption & integrity',
    'nfc_2_desc' => 'Card data is protected using AES encryption with an organisation-level secret key. Each transaction updates the balance and a rolling counter, which is signed to prevent tampering or replay attacks. Cards from one organisation cannot be used at another.',
    'nfc_3_title' => 'Hardware requirements',
    'nfc_3_desc' => 'You need NTAG213 NFC tags for each attendee. For reading cards, you can use the CatLab Drinks Android app on any device with a built-in NFC reader. Alternatively, you can use an ACR122U (or compatible) USB NFC card reader with a lightweight companion service that communicates with the POS browser over a socket.io connection.',
    'nfc_4_title' => 'Offline support',
    'nfc_4_desc' => 'Because the balance is stored on the card itself, transactions can be processed even when the internet connection is intermittent. Transactions are synced to the server when connectivity is restored.',
    'nfc_companion' => 'For USB NFC readers, the companion service runs on a Raspberry Pi or any machine with USB access. See the',
    'nfc_companion_link' => 'NFC socket.io service repository',
    'nfc_companion_after' => 'for setup instructions.',

    'screenshots_title' => 'Screenshots',

    'license_title' => 'License',
    'license_text' => 'CatLab Drinks is free software released under the GNU General Public License v3. You are free to use, modify, and distribute it.',
    'license_warranty' => 'THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.',

    'language' => 'Language',
];
