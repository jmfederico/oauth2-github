<?php

namespace League\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\Exception\GithubIdentityProviderException;
use League\OAuth2\Client\Token\AccessTokenInterface;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

/**
 * @extends AbstractProvider<GithubResourceOwner>
 */
class Github extends AbstractProvider
{
    use BearerAuthorizationTrait;

    /**
     * Domain
     *
     * @var string
     */
    public $domain = 'https://github.com';

    /**
     * Api domain
     *
     * @var string
     */
    public $apiDomain = 'https://api.github.com';

    /**
     * Get authorization url to begin OAuth flow
     *
     * @return string
     */
    public function getBaseAuthorizationUrl()
    {
        return $this->domain . '/login/oauth/authorize';
    }

    /**
     * Get access token url to retrieve token
     *
     * @param array $params
     *
     * @return string
     */
    public function getBaseAccessTokenUrl(array $params)
    {
        return $this->domain . '/login/oauth/access_token';
    }

    /**
     * Get provider url to fetch user details
     *
     * @param AccessTokenInterface $token
     *
     * @return string
     */
    public function getResourceOwnerDetailsUrl(AccessTokenInterface $token)
    {
        if ($this->domain === 'https://github.com') {
            return $this->apiDomain . '/user';
        }
        return $this->domain . '/api/v3/user';
    }

    protected function fetchResourceOwnerDetails(AccessTokenInterface $token)
    {
        $response = parent::fetchResourceOwnerDetails($token);

        if (empty($response['email'])) {
            $url = $this->getResourceOwnerDetailsUrl($token) . '/emails';

            $request = $this->getAuthenticatedRequest(self::METHOD_GET, $url, $token);

            $responseEmail = $this->getParsedResponse($request);

            $response['email'] = null;
            if (($responseEmail[0]['primary'] ?? false) === true && ($responseEmail[0]['verified']  ?? false) === true) {
                $response['email'] = $responseEmail[0]['email'];
            }
        }

        return $response;
    }

    /**
     * Get the default scopes used by this provider.
     *
     * This should not be a complete list of all scopes, but the minimum
     * required for the provider user interface!
     *
     * @return array
     */
    protected function getDefaultScopes()
    {
        return [
            'user.email',
        ];
    }

    /**
     * Check a provider response for errors.
     *
     * @link   https://developer.github.com/v3/#client-errors
     * @link   https://developer.github.com/v3/oauth/#common-errors-for-the-access-token-request
     * @throws IdentityProviderException
     * @param  ResponseInterface $response
     * @param  array             $data     Parsed response data
     * @return void
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        if ($response->getStatusCode() >= 400) {
            throw GithubIdentityProviderException::clientException($response, $data);
        } elseif (isset($data['error'])) {
            throw GithubIdentityProviderException::oauthException($response, $data);
        }
    }

    /**
     * Generate a user object from a successful user details request.
     *
     * @param  array       $response
     * @param  AccessTokenInterface $token
     * @return GithubResourceOwner
     */
    protected function createResourceOwner(array $response, AccessTokenInterface $token): GithubResourceOwner
    {
        $user = new GithubResourceOwner($response);

        return $user->setDomain($this->domain);
    }
}
