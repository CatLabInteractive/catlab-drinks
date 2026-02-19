<?php

return [

    'title' => 'CatLab Drinks',
    'tagline' => 'Système open-source d\'automatisation de bar et point de vente',
    'subtitle' => 'Un système de point de vente moderne et auto-hébergé, conçu pour les événements, fêtes et l\'hôtellerie — avec paiement NFC, commandes par smartphone et suivi des ventes en temps réel.',
    'open_web_app' => 'Ouvrir l\'application web',
    'install_android' => 'Installer l\'app Android',
    'view_on_github' => 'Voir sur GitHub',

    'why_title' => 'Pourquoi CatLab Drinks ?',
    'why_intro' => 'Gérer un bar lors d\'un événement ne devrait pas signifier jongler avec l\'argent liquide, mal compter la monnaie ou perdre le suivi des ventes. CatLab Drinks vous offre un système complet de gestion de bar numérique que vous pouvez déployer sur votre propre serveur en quelques minutes.',
    'why_1_title' => 'Éliminez les erreurs',
    'why_1_desc' => 'Plus de monnaie mal comptée ni de totaux incorrects. Chaque commande est suivie numériquement avec une tarification précise.',
    'why_2_title' => 'Suivi des ventes en temps réel',
    'why_2_desc' => 'Voyez exactement ce qui se vend, combien de revenus vous générez et d\'où viennent vos commandes — le tout en temps réel.',
    'why_3_title' => 'Commandes par smartphone',
    'why_3_desc' => 'Laissez vos participants commander des boissons depuis leur téléphone. Les commandes arrivent directement au bar — sans faire la queue.',
    'why_4_title' => 'Paiements par carte NFC',
    'why_4_desc' => 'Distribuez des cartes NFC prépayées pour les paiements sans contact. Rechargez, payez et suivez les soldes — pas besoin d\'espèces.',
    'why_5_title' => 'Fonctionne hors ligne',
    'why_5_desc' => 'Mauvais WiFi sur le lieu ? Pas de problème. Le système de caisse continue de fonctionner hors ligne et se synchronise une fois reconnecté.',
    'why_6_title' => 'Auto-hébergé & open source',
    'why_6_desc' => 'Vos données restent sur votre serveur. Déployez votre propre instance, personnalisez-la selon vos besoins et gardez le contrôle total.',

    'deploy_title' => 'Déployez votre propre instance',
    'deploy_intro' => 'CatLab Drinks est conçu pour être auto-hébergé. Vous le déployez sur votre propre serveur, en gardant le contrôle total sur vos données et votre configuration. Pour commencer, c\'est simple :',
    'deploy_step_1' => 'Clonez le dépôt depuis GitHub',
    'deploy_step_2' => 'Configurez votre environnement et votre base de données',
    'deploy_step_3' => 'Exécutez les migrations et compilez les assets frontend',
    'deploy_step_4' => 'Créez un compte et configurez votre premier événement',
    'deploy_docker' => 'Une configuration Docker Compose est incluse pour un déploiement rapide. Consultez le dépôt pour les instructions de configuration détaillées.',

    'nfc_title' => 'Comment fonctionnent les cartes NFC',
    'nfc_intro' => 'CatLab Drinks implémente un système de paiement NFC en boucle fermée utilisant des puces NTAG213. Voici comment cela fonctionne techniquement :',
    'nfc_1_title' => 'Structure de la carte',
    'nfc_1_desc' => 'Chaque puce NFC NTAG213 stocke un identifiant unique, un solde, un compteur de transactions et une signature cryptographique. Les données sont écrites directement dans les secteurs mémoire compatibles NDEF de la carte.',
    'nfc_2_title' => 'Chiffrement et intégrité',
    'nfc_2_desc' => 'Les données de la carte sont protégées par un chiffrement AES avec une clé secrète au niveau de l\'organisation. Chaque transaction met à jour le solde et un compteur rotatif, qui est signé pour empêcher la falsification ou les attaques par rejeu. Les cartes d\'une organisation ne peuvent pas être utilisées dans une autre.',
    'nfc_3_title' => 'Matériel requis',
    'nfc_3_desc' => 'Vous avez besoin de tags NFC NTAG213 pour chaque participant. Pour lire les cartes, vous pouvez utiliser l\'application Android CatLab Drinks sur tout appareil doté d\'un lecteur NFC intégré. Alternativement, vous pouvez utiliser un lecteur de cartes NFC USB ACR122U (ou compatible) avec un service compagnon léger qui communique avec le navigateur POS via une connexion socket.io.',
    'nfc_4_title' => 'Support hors ligne',
    'nfc_4_desc' => 'Le solde étant stocké sur la carte elle-même, les transactions peuvent être traitées même lorsque la connexion Internet est intermittente. Les transactions sont synchronisées avec le serveur lorsque la connectivité est rétablie.',
    'nfc_companion' => 'Pour les lecteurs NFC USB, le service compagnon fonctionne sur un Raspberry Pi ou toute machine avec accès USB. Voir le',
    'nfc_companion_link' => 'dépôt du service NFC socket.io',
    'nfc_companion_after' => 'pour les instructions d\'installation.',

    'screenshots_title' => 'Captures d\'écran',

    'license_title' => 'Licence',
    'license_text' => 'CatLab Drinks est un logiciel libre distribué sous la licence GNU General Public License v3. Vous êtes libre de l\'utiliser, de le modifier et de le distribuer.',
    'license_warranty' => 'LE LOGICIEL EST FOURNI "TEL QUEL", SANS GARANTIE D\'AUCUNE SORTE, EXPRESSE OU IMPLICITE, Y COMPRIS MAIS SANS S\'Y LIMITER AUX GARANTIES DE QUALITÉ MARCHANDE, D\'ADÉQUATION À UN USAGE PARTICULIER ET DE NON-CONTREFAÇON.',

    'language' => 'Langue',
];
