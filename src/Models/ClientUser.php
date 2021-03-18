<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Models;

use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\Utils\DataHelpers;
use CharlotteDunois\Yasmin\Utils\FileHelpers;
use CharlotteDunois\Yasmin\WebSocket\WSManager;
use InvalidArgumentException;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\Promise;
use RuntimeException;
use function array_key_exists;
use function property_exists;
use function React\Promise\all;
use function React\Promise\reject;

/**
 * Represents the Client User.
 */
class ClientUser extends User
{
    /**
     * The client's presence.
     * @var array
     * @internal
     */
    protected $clientPresence;

    /**
     * @param Client $client
     * @param array  $user
     *
     * @internal
     */
    function __construct(Client $client, $user)
    {
        parent::__construct($client, $user);

        $presence = $this->client->getOption('ws.presence', []);
        $this->clientPresence = [
            'afk' => (isset($presence['afk']) ? ((bool)$presence['afk']) : false),
            'since' => (isset($presence['since']) ? $presence['since'] : null),
            'status' => (!empty($presence['status']) ? $presence['status'] : 'online'),
            'activities' => (!empty($presence['activities']) ? $presence['activities'] : null),
        ];
    }

    /**
     * {@inheritdoc}
     * @return mixed
     * @throws RuntimeException
     * @internal
     */
    function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        return parent::__get($name);
    }

    /**
     * @return mixed
     * @internal
     */
    function __debugInfo()
    {
        $vars = parent::__debugInfo();
        unset($vars['clientPresence'], $vars['firstPresence'], $vars['firstPresencePromise'], $vars['firstPresenceCount'], $vars['firstPresenceTime']);
        return $vars;
    }

    /**
     * Set your avatar. Resolves with $this.
     *
     * @param string|null $avatar An URL or the filepath or the data. Null resets your avatar.
     *
     * @return ExtendedPromiseInterface
     * @example ../../examples/docs-examples.php 15 4
     */
    function setAvatar(?string $avatar)
    {
        if ($avatar === null) {
            return $this->client->apimanager()->endpoints->user->modifyCurrentUser(['avatar' => null])->then(function (
            ) {
                return $this;
            });
        }

        return (new Promise(function (callable $resolve, callable $reject) use ($avatar) {
            FileHelpers::resolveFileResolvable($avatar)->done(function ($data) use (
                $resolve,
                $reject
            ) {
                $image = DataHelpers::makeBase64URI($data);

                $this->client->apimanager()->endpoints->user->modifyCurrentUser(['avatar' => $image])->done(function (
                ) use ($resolve) {
                    $resolve($this);
                }, $reject);
            }, $reject);
        }));
    }

    /**
     * Set your status. Resolves with $this.
     *
     * @param string $status Valid values are: `online`, `idle`, `dnd` and `invisible`.
     *
     * @return ExtendedPromiseInterface
     * @example ../../examples/docs-examples.php 25 2
     */
    function setStatus(string $status)
    {
        $presence = [
            'status' => $status,
        ];

        return $this->setPresence($presence);
    }

    /**
     * Set your presence. Ratelimit is 5/60s, the gateway drops all further presence updates. Resolves with $this.
     *
     * ```
     * array(
     *     'afk' => bool,
     *     'since' => int|null,
     *     'status' => string,
     *     'activities' => [[
     *         'name' => string,
     *         'type' => int,
     *         'url' => string|null
     *     ]]|null
     * )
     * ```
     *
     *  Any field in the first dimension is optional and will be automatically filled with the last known value.
     *
     * @param array    $presence
     * @param int|null $shardID Unless explicitely given, all presences will be fanned out to all shards.
     *
     * @return ExtendedPromiseInterface
     * @example ../../examples/docs-examples.php 29 10
     */
    function setPresence(array $presence, ?int $shardID = null)
    {
        if (empty($presence)) {
            return reject(new InvalidArgumentException('Presence argument can not be empty'));
        }

        $packet = [
            'op' => WSManager::OPCODES['STATUS_UPDATE'],
            'd' => [
                'afk' => (array_key_exists('afk',
                    $presence) ? ((bool)$presence['afk']) : $this->clientPresence['afk']),
                'since' => (array_key_exists('since',
                    $presence) ? $presence['since'] : $this->clientPresence['since']),
                'status' => (array_key_exists('status',
                    $presence) ? $presence['status'] : $this->clientPresence['status']),
                'activities' => (array_key_exists('activities',
                    $presence) ? $presence['activities'] : $this->clientPresence['activities']),
            ],
        ];

        $this->clientPresence = $packet['d'];

        $presence = $this->getPresence();
        if ($presence) {
            $presence->_patch($this->clientPresence);
        }

        if ($shardID === null) {
            $prms = [];
            foreach ($this->client->shards as $shard) {
                $prms[] = $shard->ws->send($packet);
            }

            return all($prms)->then(function () {
                return $this;
            });
        }

        return $this->client->shards->get($shardID)->ws->send($packet)->then(function () {
            return $this;
        });
    }

    /**
     * Set your activity. Resolves with $this.
     *
     * @param Activity|string|null $name                                   The activity name.
     * @param int                  $type                                   Optional if first argument is an Activity.
     *                                                                     The type of your activity.
     * @param int|null             $shardID                                Unless explicitely given, all presences will
     *                                                                     be fanned out to all shards.
     *
     * @return ExtendedPromiseInterface
     */
    function setActivity($name, int $type = 0, ?int $shardID = null)
    {
        if ($name === null) {
            return $this->setPresence([
                'activities' => [],
            ], $shardID);
        } elseif ($name instanceof Activity) {
            return $this->setPresence([
                'activities' => [$name->jsonSerialize()],
            ], $shardID);
        }

        $presence = [
            'activities' => [
                [
                    'name' => $name,
                    'type' => $type,
                    'url' => null,
                ],
            ],
        ];

        return $this->setPresence($presence, $shardID);
    }

    /**
     * Set your username. Resolves with $this.
     *
     * @param string $username
     *
     * @return ExtendedPromiseInterface
     * @example ../../examples/docs-examples.php 41 2
     */
    function setUsername(string $username)
    {
        return (new Promise(function (callable $resolve, callable $reject) use ($username) {
            $this->client->apimanager()->endpoints->user->modifyCurrentUser(['username' => $username])->done(function (
            ) use ($resolve) {
                $resolve($this);
            }, $reject);
        }));
    }

    /**
     * Creates a new Group DM with the owner of the access tokens. Resolves with an instance of GroupDMChannel. The
     * structure of the array is as following:
     *
     * ```
     * array(
     *    accessToken => \CharlotteDunois\Yasmin\Models\User|string (user ID)
     * )
     * ```
     *
     * The nicks array is an associative array of userID => nick. The nick defaults to the username.
     *
     * @param array $userWithAccessTokens
     * @param array $nicks
     *
     * @return ExtendedPromiseInterface
     * @see \CharlotteDunois\Yasmin\Models\GroupDMChannel
     */
    function createGroupDM(array $userWithAccessTokens, array $nicks = [])
    {
        return (new Promise(function (callable $resolve, callable $reject) use (
            $nicks,
            $userWithAccessTokens
        ) {
            $tokens = [];
            $users = [];

            foreach ($userWithAccessTokens as $token => $user) {
                $user = $this->client->users->resolve($user);

                $tokens[] = $token;
                $users[$user->id] = (!empty($nicks[$user->id]) ? $nicks[$user->id] : $user->username);
            }

            $this->client->apimanager()->endpoints->user->createGroupDM($tokens, $users)->done(function ($data) use (
                $resolve
            ) {
                $channel = $this->client->channels->factory($data);
                $resolve($channel);
            }, $reject);
        }));
    }

    /**
     * Making these methods throw if someone tries to use them. They also get hidden due to the Sami Renderer removing
     * them.
     */

    /**
     * @return void
     * @throws RuntimeException
     * @internal
     */
    function createDM()
    {
        throw new RuntimeException('Can not use this method in ClientUser');
    }

    /**
     * @return void
     * @throws RuntimeException
     * @internal
     */
    function deleteDM()
    {
        throw new RuntimeException('Can not use this method in ClientUser');
    }

    /**
     * @return void
     * @throws RuntimeException
     * @internal
     */
    function fetchUserConnections(string $accessToken)
    {
        throw new RuntimeException('Can not use this method in ClientUser');
    }
}
