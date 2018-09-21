<?php
/**
 * Yasmin
 * Copyright 2017-2018 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
*/

namespace CharlotteDunois\Yasmin\Models;

/**
 * Guild Storage to store guilds, utilizes Collection.
 */
class GuildStorage extends Storage implements \CharlotteDunois\Yasmin\Interfaces\GuildStorageInterface {
    /**
     * Resolves given data to a guild.
     * @param \CharlotteDunois\Yasmin\Models\Guild|string|int  $guild  string/int = guild ID
     * @return \CharlotteDunois\Yasmin\Models\Guild
     * @throws \InvalidArgumentException
     */
    function resolve($guild) {
        if($guild instanceof \CharlotteDunois\Yasmin\Models\Guild) {
            return $guild;
        }
        
        if(\is_int($guild)) {
            $guild = (string) $guild;
        }
        
        if(\is_string($guild) && $this->has($guild)) {
            return $this->get($guild);
        }
        
        throw new \InvalidArgumentException('Unable to resolve unknown guild');
    }
    
    /**
     * {@inheritdoc}
     * @param string  $key
     * @return \CharlotteDunois\Yasmin\Models\Guild|null
     */
    function get($key) {
        return parent::get($key);
    }
    
    /**
     * {@inheritdoc}
     * @param string                                $key
     * @param \CharlotteDunois\Yasmin\Models\Guild  $value
     * @return $this
     */
    function set($key, $value) {
        parent::set($key, $value);
        return $this;
    }
    
    /**
     * {@inheritdoc}
     * @param string  $key
     * @return $this
     */
    function delete($key) {
        parent::delete($key);
        if($this !== $this->client->guilds) {
            $this->client->guilds->delete($key);
        }
        
        return $this;
    }
    
    /**
     * Factory to create (or retrieve existing) guilds.
     * @param array     $data
     * @param int|null  $shardID
     * @return \CharlotteDunois\Yasmin\Models\Guild
     * @internal
     */
    function factory(array $data, ?int $shardID = null) {
        if($this->has($data['id'])) {
            $guild = $this->get($data['id']);
            $guild->_patch($data);
            return $guild;
        }
        
        $guild = new \CharlotteDunois\Yasmin\Models\Guild($this->client, $data, $shardID);
        $this->set($guild->id, $guild);
        return $guild;
    }
}
