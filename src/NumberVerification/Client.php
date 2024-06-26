<?php

declare(strict_types=1);

namespace Vonage\NumberVerification;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Vonage\Client\APIClient;
use Vonage\Client\APIResource;
use Vonage\Client\Credentials\CredentialsInterface;
use Vonage\Client\Credentials\Gnp;
use Vonage\Client\Exception\Credentials;
use Vonage\Client\Exception\Exception;
use Vonage\Webhook\Factory;

class Client implements APIClient
{
    public function __construct(protected APIResource $api)
    {
    }

    public function getAPIResource(): APIResource
    {
        return $this->api;
    }

    /**
     * You are expected to call this code when you are consuming the webhook that has been
     * received from the frontend call being made
     *
     * @param string $phoneNumber
     * @return bool
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function verifyNumber(string $phoneNumber): bool
    {
        $webhook = Factory::createFromGlobals();

        if (!isset($webhook['code'])) {
            throw new Exception('Required field code not found in webhook');
        };

        /** @var Gnp $credentials */
        $credentials = $this->getAPIResource()->getClient()->getCredentials();
        $credentials->setCode($webhook['code']);
        $credentials->setState($webhook['state']);

        $phoneNumberKey = 'phoneNumber';

        if ($this->isHashedPhoneNumber($phoneNumber)) {
            $phoneNumberKey = 'hashedPhoneNumber';
        }

        // This request will now contain a valid CAMARA token
        $response = $this->getAPIResource()->create([
            $phoneNumberKey => $phoneNumber
        ]);

        return $response['devicePhoneNumberVerified'];
    }

    public function isHashedPhoneNumber(string $phoneNumber): bool
    {
        return (strlen($phoneNumber) >= 13);
    }

    /**
     * This method is the start of the process of Number Verification
     * It builds the correct Front End Auth request for OIDC CAMARA request
     *
     * @param string $phoneNumber
     * @param string $redirectUrl
     * @param string $state
     * @return string
     * @throws Credentials
     */
    public function buildFrontEndUrl(string $phoneNumber, string $redirectUrl, string $state = ''): string
    {
        /** @var Gnp $credentials */
        $credentials = $this->getAPIResource()->getClient()->getCredentials();
        $this->enforceCredentials($credentials);

        $applicationId = $credentials->getApplication();

        $query = http_build_query([
            'client_id' => $applicationId,
            'redirect_uri' => $redirectUrl,
            'state' => $state,
            'scope' => 'openid dpv:FraudPreventionAndDetection#number-verification-verify-read',
            'response_type' => 'code',
            'login_hint' => $phoneNumber
        ]);

        return 'https://oidc.idp.vonage.com/oauth2/auth' . $query;
    }

    protected function enforceCredentials(CredentialsInterface $credentials): void
    {
        if (!$credentials instanceof Gnp) {
            throw new Credentials('You can only use GNP Credentials with the Number Verification API');
        }
    }
}
