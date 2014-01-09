<?php

namespace OAuth\Common\Storage;

use OAuth\Common\Token\TokenInterface;
use OAuth\Common\Storage\Exception\TokenNotFoundException;

/*
 * Stores a token in-memory only (destroyed at end of script execution).
 */
class WordPressMemory implements TokenStorageInterface
{
    /**
     * @var object|TokenInterface
     */
    protected $tokens;

    public function __construct()
    {
        $this->tokens = (array)maybe_unserialize( get_option( 'kws_oauth_tokens' ) );
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveAccessToken($service)
    {
        if ($this->hasAccessToken($service)) {
            return $this->tokens[$service];
        }

        throw new TokenNotFoundException('Token not stored');
    }

    /**
     * {@inheritDoc}
     */
    public function storeAccessToken($service, TokenInterface $token)
    {

        $this->tokens[$service] = $token;

        update_option( 'kws_oauth_tokens', $this->tokens);

        // allow chaining
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function hasAccessToken($service)
    {
        return isset($this->tokens[$service]) && $this->tokens[$service] instanceof TokenInterface;
    }

    /**
     * {@inheritDoc}
     */
    public function clearToken($service)
    {
        if (array_key_exists($service, $this->tokens)) {
            unset($this->tokens[$service]);
        }

        update_option( 'kws_oauth_tokens', $this->tokens);

        // allow chaining
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function clearAllTokens()
    {

        $this->tokens = array();

        update_option( 'kws_oauth_tokens', $this->tokens);

        // allow chaining
        return $this;
    }
}
