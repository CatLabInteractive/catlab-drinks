// swagger-ui-dist is the prebuilt bundle with React inside. The plain
// swagger-ui package pulls two React versions into node_modules, which
// breaks its rendering.
// The bundle file is required directly: the package index needs Node's `path`.
const SwaggerUIBundle = require('swagger-ui-dist/swagger-ui-bundle');
const SwaggerUI = SwaggerUIBundle.default || SwaggerUIBundle;

var ui = SwaggerUI(swaggerConfiguration);
ui.initOAuth(oauthConfiguration);