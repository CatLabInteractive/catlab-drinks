const SwaggerUI = require('swagger-ui')

var ui = SwaggerUI(swaggerConfiguration);
ui.initOAuth(oauthConfiguration);