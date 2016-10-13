<?php

namespace Moloquent\Passport;

class TokenRepository extends \Laravel\Passport\TokenRepository
{
    /**
     * Get a token by the given ID.
     *
     * @param string $id
     *
     * @return Token
     */
    public function find($id)
    {
        return Token::where('id', $id)->first();
    }
}
