<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
*/

namespace CharlotteDunois\Yasmin;

/**
 * Used to reference instances through a static method and improve GC success.
 * @internal
 */
class Reference implements \Serializable {
    /**
     * @var string
     */
    protected $ref;
    
    /**
     * @var array
     */
    protected static $references = array();
    
    /**
     * Destructor.
     * @return void
     */
    function __destruct() {
        static::$references[$this->ref] = null;
        unset(static::$references[$this->ref]);
    }
    
    /**
     * @internal
     * @return object
     */
    function __debugInfo() {
        return array('data' => static::$references[$this->ref]);
    }
    
    /**
     * Get the instance's property.
     * @param string  $name
     * @return mixed
     * @throws \Throwable
     */
    function __get($name) {
        return $this->acquireReferencedInstance()->$name;
    }
    
    /**
     * Set the instance's property.
     * @param string  $name
     * @param mixed   $value
     * @return mixed
     * @throws \Throwable
     */
    function __set($name, $value) {
        return ($this->acquireReferencedInstance()->$name = $value);
    }
    
    /**
     * Call the instance's method.
     * @param string  $name
     * @param array   $args
     * @return mixed
     * @throws \Throwable
     */
    function __call($name, $args) {
        return $this->acquireReferencedInstance()->$name(...$args);
    }
    
    /**
     * Get the hold instance.
     * @return object
     */
    function acquireReferencedInstance() {
        return static::$references[$this->ref];
    }
    
    /**
     * Creates a new reference for the instance.
     * @param object  $parent
     * @param string  $name
     * @param object  $instance
     * @return self
     * @throws \DomainException
     */
    static function create($parent, string $name, $instance): self {
        if($instance instanceof self) {
            return $instance;
        }
        
        $key = \spl_object_hash($parent).'-'.$name;
        
        if(isset(static::$references[$key])) {
            throw new \DomainException('Specified parent has already registered specified name');
        }
        
        $ref = new static();
        $ref->ref = $key;
        
        static::$references[$key] = $instance;
        return $ref;
    }
    
    /**
     * Releases all of a parent's registered instances, or only a specific instance.
     * @param object       $parent
     * @param string|null  $name
     * @return void
     */
    static function release($parent, ?string $name = null): void {
        $key = \spl_object_hash($parent);
        
        if($name !== null) {
            $key .= '-'.$name;
            
            static::$references[$key] = null;
            unset(static::$references[$key]);
            
            return;
        }
        
        $length = \strlen($key);
        
        foreach(static::$references as $key => $_) {
            if(\substr($key, 0, $length) === $key) {
                static::$references[$key] = null;
                unset(static::$references[$key]);
            }
        }
    }
    
    /**
     * Stringify on the instance.
     * @return string
     */
    function __toString() {
        return $this->acquireReferencedInstance()->__toString();
    }
    
    /**
     * @return string
     * @internal
     */
    function serialize() {
        $vars = array(
            'key' => $this->key,
            'instance' => static::$references[$this->key]
        );
        
        return \serialize($vars);
    }
    
    /**
     * @param string  $data
     * @return void
     * @internal
     */
    function unserialize($data) {
        $vars = \unserialize($data);
        
        $this->key = $vars['key'];
        static::$references[$this->key] = $vars['instance'];
    }
}
