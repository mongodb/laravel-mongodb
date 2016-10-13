<?php

namespace Moloquent\Passport\Bridge;

use DateTime;
use Illuminate\Database\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Moloquent\Passport\Token;

class AccessTokenRepository extends \Laravel\Passport\Bridge\AccessTokenRepository
{

    /**
     * {@inheritdoc}
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity)
    {
        // Use Token model to save token instead of direct database insert
        $token = new Token([
            'id' => $accessTokenEntity->getIdentifier(),
            'user_id' => $accessTokenEntity->getUserIdentifier(),
            'client_id' => $accessTokenEntity->getClient()->getIdentifier(),
            'scopes' => $this->formatScopesForStorage($accessTokenEntity->getScopes()),
            'revoked' => false,
        ]);

        $token->save();
    }

    public function formatScopesForStorage(array $scopes)
    {
        return array_map(function ($scope) {
            return $scope->getIdentifier();
        }, $scopes);
    }

}
