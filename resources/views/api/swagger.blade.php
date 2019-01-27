<!DOCTYPE html>
<html>
<head>

    <meta charset="UTF-8">
    <title>Swagger UI</title>

    <link href="swaggerui/swagger.css" rel="stylesheet" type="text/css" />

</head>

<body>

    <div id="swagger-ui"></div>

    <script type="text/javascript">

        var swaggerConfiguration = {
            dom_id: '#swagger-ui',

            url: 'api/v1/description.json',
            oauth2RedirectUrl: '{{ $oauth2_redirect_url }}'
        };

        var oauthConfiguration = {
            clientId: '{{ $oauth2_client_id }}'
        };

    </script>
    <script type="text/javascript" src="swaggerui/swagger-ui.js"></script>

</body>

</html>