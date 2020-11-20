const ApiService = Shopware.Classes.ApiService;


class LampsCryptogateApiCredentialsService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'cryptogate') {
        super(httpClient, loginService, apiEndpoint);
    }

    checkCredentials(clientId, clientSecret, sandboxActive) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/checkCredentials`,
                {
                    params: { },
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default LampsCryptogateApiCredentialsService;

const { Application } = Shopware;

const initContainer = Application.getContainer('init');

Application.addServiceProvider(
    'LampsCryptogateApiCredentialsService',
    (container) => new LampsCryptogateApiCredentialsService(initContainer.httpClient, container.loginService)
);

