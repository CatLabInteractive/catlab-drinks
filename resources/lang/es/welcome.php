<?php

return [

    'title' => 'CatLab Drinks',
    'tagline' => 'Sistema open-source de automatización de bar y punto de venta',
    'subtitle' => 'Un sistema de punto de venta moderno y autoalojado, diseñado para eventos, fiestas y hostelería — con soporte de pago NFC, pedidos por smartphone y seguimiento de ventas en tiempo real.',
    'open_web_app' => 'Abrir aplicación web',
    'install_android' => 'Instalar app Android',
    'view_on_github' => 'Ver en GitHub',

    'why_title' => '¿Por qué CatLab Drinks?',
    'why_intro' => 'Gestionar un bar en un evento no debería significar hacer malabares con el efectivo, contar mal el cambio o perder el control de las ventas. CatLab Drinks te ofrece un sistema completo de gestión digital de bar que puedes desplegar en tu propio servidor en minutos.',
    'why_1_title' => 'Elimina errores',
    'why_1_desc' => 'No más cambio mal contado ni totales incorrectos. Cada pedido se registra digitalmente con precios precisos.',
    'why_2_title' => 'Seguimiento de ventas en tiempo real',
    'why_2_desc' => 'Vea exactamente qué se vende, cuántos ingresos genera y de dónde vienen sus pedidos — todo en tiempo real.',
    'why_3_title' => 'Pedidos por smartphone',
    'why_3_desc' => 'Deje que sus asistentes pidan bebidas desde su propio teléfono. Los pedidos van directamente a la barra — sin hacer cola.',
    'why_4_title' => 'Pagos con tarjeta NFC',
    'why_4_desc' => 'Emita tarjetas NFC prepago para pagos sin efectivo. Recargue, pague y controle saldos — sin necesidad de efectivo.',
    'why_5_title' => 'Funciona sin conexión',
    'why_5_desc' => '¿Mal WiFi en el lugar? No hay problema. El sistema de punto de venta sigue funcionando sin conexión y se sincroniza cuando se reconecta.',
    'why_6_title' => 'Autoalojado y open source',
    'why_6_desc' => 'Sus datos permanecen en su servidor. Despliegue su propia instancia, personalícela según sus necesidades y mantenga el control total.',

    'deploy_title' => 'Despliegue su propia instancia',
    'deploy_intro' => 'CatLab Drinks está diseñado para ser autoalojado. Lo despliega en su propio servidor, manteniendo el control total sobre sus datos y configuración. Comenzar es sencillo:',
    'deploy_step_1' => 'Clone el repositorio desde GitHub',
    'deploy_step_2' => 'Configure su entorno y base de datos',
    'deploy_step_3' => 'Ejecute las migraciones y compile los assets del frontend',
    'deploy_step_4' => 'Cree una cuenta y configure su primer evento',
    'deploy_docker' => 'Se incluye una configuración de Docker Compose para un despliegue rápido. Consulte el repositorio para instrucciones detalladas de configuración.',

    'nfc_title' => 'Cómo funcionan las tarjetas NFC',
    'nfc_intro' => 'CatLab Drinks implementa un sistema de pago NFC en circuito cerrado usando chips NTAG213. Así es como funciona técnicamente:',
    'nfc_1_title' => 'Estructura de la tarjeta',
    'nfc_1_desc' => 'Cada chip NFC NTAG213 almacena un ID único de tarjeta, saldo, contador de transacciones y una firma criptográfica. Los datos se escriben directamente en los sectores de memoria compatibles con NDEF de la tarjeta.',
    'nfc_2_title' => 'Cifrado e integridad',
    'nfc_2_desc' => 'Los datos de la tarjeta están protegidos mediante cifrado AES con una clave secreta a nivel de organización. Cada transacción actualiza el saldo y un contador rotativo, que se firma para prevenir manipulación o ataques de repetición. Las tarjetas de una organización no pueden usarse en otra.',
    'nfc_3_title' => 'Requisitos de hardware',
    'nfc_3_desc' => 'Necesita etiquetas NFC NTAG213 para cada asistente. Para leer las tarjetas, puede usar la aplicación Android CatLab Drinks en cualquier dispositivo con lector NFC incorporado. Alternativamente, puede usar un lector de tarjetas NFC USB ACR122U (o compatible) con un servicio acompañante ligero que se comunica con el navegador POS a través de una conexión socket.io.',
    'nfc_4_title' => 'Soporte sin conexión',
    'nfc_4_desc' => 'Como el saldo se almacena en la propia tarjeta, las transacciones pueden procesarse incluso cuando la conexión a Internet es intermitente. Las transacciones se sincronizan con el servidor cuando se restablece la conectividad.',
    'nfc_companion' => 'Para lectores NFC USB, el servicio acompañante funciona en una Raspberry Pi o cualquier máquina con acceso USB. Vea el',
    'nfc_companion_link' => 'repositorio del servicio NFC socket.io',
    'nfc_companion_after' => 'para instrucciones de instalación.',

    'screenshots_title' => 'Capturas de pantalla',

    'license_title' => 'Licencia',
    'license_text' => 'CatLab Drinks es software libre publicado bajo la Licencia Pública General GNU v3. Es libre de usarlo, modificarlo y distribuirlo.',
    'license_warranty' => 'EL SOFTWARE SE PROPORCIONA "TAL CUAL", SIN GARANTÍA DE NINGÚN TIPO, EXPRESA O IMPLÍCITA, INCLUYENDO PERO NO LIMITÁNDOSE A LAS GARANTÍAS DE COMERCIABILIDAD, IDONEIDAD PARA UN PROPÓSITO PARTICULAR Y NO INFRACCIÓN.',

    'language' => 'Idioma',
];
