<?php

namespace OAuth\OAuth2\Service;

use OAuth\OAuth2\Service\AbstractService;
use OAuth\OAuth2\Token\StdOAuth2Token;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use OAuth\Common\Consumer\CredentialsInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Storage\TokenStorageInterface;
use OAuth\Common\Http\Uri\UriInterface;

class Salesforce extends AbstractService
{
    /**
     * Scopes
     *
     * @var string
     */
    const   SCOPE_API           =   'api',
            SCOPE_REFRESH_TOKEN =   'refresh_token';

    /**
     * Are we connecting to Salesforce?
     * @var boolean
     */
    var     $sandbox            =   false;

    /**
     * {@inheritdoc}
     */
    public function getAuthorizationEndpoint()
    {
        if ( $this->sandbox ) {
            return new Uri('https://test.salesforce.com/services/oauth2/authorize');
        } else {
            return new Uri('https://login.salesforce.com/services/oauth2/authorize');
        }
    }

    /**
     * Define whether using Salesforce Sandbox or not.
     *
     * @param  boolean     $is_sandbox Should the connection use Salesforce?
     */
    public function setSandbox( $is_sandbox = false ) {
        $this->sandbox = !empty( $is_sandbox );
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessTokenEndpoint()
    {
        if ( $this->sandbox ) {
            return new Uri('https://test.salesforce.com/services/oauth2/token');
        } else {
            return new Uri('https://na1.salesforce.com/services/oauth2/token');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function parseRequestTokenResponse($responseBody)
    {
        parse_str($responseBody, $data);

        if (null === $data || !is_array($data)) {
            throw new TokenResponseException('Unable to parse response.');
        } elseif (!isset($data['oauth_callback_confirmed']) || $data['oauth_callback_confirmed'] !== 'true') {
            throw new TokenResponseException('Error in retrieving token.');
        }

        return $this->parseAccessTokenResponse($responseBody);
    }

    /**
     * {@inheritdoc}
     */
    protected function parseAccessTokenResponse($responseBody)
    {
        $data = json_decode($responseBody, true);

        if (null === $data || !is_array($data)) {
            throw new TokenResponseException('Unable to parse response.');
        } elseif (isset($data['error'])) {
            throw new TokenResponseException('Error in retrieving token: "' . $data['error'] . '"');
        }

        $token = new StdOAuth2Token();
        $token->setAccessToken($data['access_token']);

        // Salesforce access tokens depend on the session timeout settings.
        // The session timeout for an access token can be configured in Salesforce from Setup by clicking Security Controls | Session Settings.
        $token->setEndOfLife(StdOAuth2Token::EOL_UNKNOWN);

        unset($data['access_token']);

        if (isset($data['refresh_token'])) {
            $token->setRefreshToken($data['refresh_token']);
            // Save Refresh Token persistently until it is cleared manually
            update_option( 'gf_salesforce_refreshtoken', $data['refresh_token'] );
            unset($data['refresh_token']);
        } else {
            $refresh_token = get_option( 'gf_salesforce_refreshtoken' );
            if( !empty( $refresh_token ) ) {
                $token->setRefreshToken( $refresh_token );
            }
        }

        $token->setExtraParams($data);

        return $token;
    }

    /**
     * {@inheritdoc}
     */
    protected function getExtraOAuthHeaders()
    {
        return array('Accept' => 'application/json');
    }
}
