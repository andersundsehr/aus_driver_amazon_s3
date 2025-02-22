<?php
namespace Aws\DynamoDb;

trait SessionConnectionConfigTrait
{
    /** @var string Name of table to store the sessions */
    protected $tableName = 'sessions';

    /** @var string Name of hash key in table. Default: "id" */
    protected $hashKey = 'id';

    /** @var string Name of the data attribute in table. Default: "data" */
    protected $dataAttribute = 'data';

    /** @var string Type of the data attribute in table. Default: "string" */
    protected $dataAttributeType = 'string';

    /** @var integer Lifetime of inactive sessions expiration */
    protected $sessionLifetime;

    /** @var string Name of the session life time attribute in table. Default: "expires" */
    protected $sessionLifetimeAttribute = 'expires';

    /** @var string Whether or not to use consistent reads */
    protected $consistentRead = true;

    /** @var string Batch options used for garbage collection */
    protected $batchConfig = [];

    /** @var boolean Whether or not to use session locking */
    protected $locking = false;

    /** @var integer Max time (s) to wait for lock acquisition */
    protected $maxLockWaitTime = 10;

    /** @var integer Min time (µs) to wait between lock attempts */
    protected $minLockRetryMicrotime = 10000;

    /** @var integer Max time (µs) to wait between lock attempts */
    protected $maxLockRetryMicrotime = 50000;

    /**
     * It initialize the Config class and
     * it sets values in case of valid configurations.
     * 
     * It transforms parameters underscore separated in camelcase "this_is_a_test" => ThisIsATest
     * and it uses it in order to set the values.
     * 
     * @param array $config
     */
    public function initConfig( array $config = [] ): void
    {
        if (!empty($config))
        {
            foreach ($config as $key => $value)
            {
                $method = 'set' . str_replace('_', '', ucwords($key, '_'));
                if(method_exists($this,$method))
                {
                    call_user_func_array(array($this, $method), array($value));
                }
            }
        }

        // It applies the default PHP session lifetime, if no session lifetime config is provided
        if(!isset($config['session_lifetime']))
        {
            $this->setSessionLifetime((int) ini_get('session.gc_maxlifetime'));
        }
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * @param string $tableName
     */
    public function setTableName($tableName): void
    {
        $this->tableName = $tableName;
    }

    /**
     * @return string
     */
    public function getHashKey()
    {
        return $this->hashKey;
    }

    /**
     * @param string $hashKey
     */
    public function setHashKey($hashKey): void
    {
        $this->hashKey = $hashKey;
    }

    /**
     * @return string
     */
    public function getDataAttribute()
    {
        return $this->dataAttribute;
    }

    /**
     * @param string $dataAttribute
     */
    public function setDataAttribute($dataAttribute): void
    {
        $this->dataAttribute = $dataAttribute;
    }

    /**
     * @return string
     */
    public function getDataAttributeType()
    {
        return $this->dataAttributeType;
    }

    /**
     * @param string $dataAttributeType
     */
    public function setDataAttributeType($dataAttributeType): void
    {
        $this->dataAttributeType = $dataAttributeType;
    }

    /**
     * @return number
     */
    public function getSessionLifetime()
    {
        return $this->sessionLifetime;
    }

    /**
     * @param number $sessionLifetime
     */
    public function setSessionLifetime($sessionLifetime): void
    {
        $this->sessionLifetime = $sessionLifetime;
    }

    /**
     * @return string
     */
    public function getSessionLifetimeAttribute()
    {
        return $this->sessionLifetimeAttribute;
    }

    /**
     * @param string $sessionLifetimeAttribute
     */
    public function setSessionLifetimeAttribute($sessionLifetimeAttribute): void
    {
        $this->sessionLifetimeAttribute = $sessionLifetimeAttribute;
    }

    /**
     * @return boolean
     */
    public function isConsistentRead()
    {
        return $this->consistentRead;
    }

    /**
     * @param boolean $consistentRead
     */
    public function setConsistentRead($consistentRead): void
    {
        $this->consistentRead = $consistentRead;
    }

    /**
     * @return mixed
     */
    public function getBatchConfig()
    {
        return $this->batchConfig;
    }

    /**
     * @param mixed $batchConfig
     */
    public function setBatchConfig($batchConfig): void
    {
        $this->batchConfig = $batchConfig;
    }
    /**
     * @return boolean
     */
    public function isLocking()
    {
        return $this->locking;
    }

    /**
     * @param boolean $locking
     */
    public function setLocking($locking): void
    {
        $this->locking = $locking;
    }

    /**
     * @return number
     */
    public function getMaxLockWaitTime()
    {
        return $this->maxLockWaitTime;
    }

    /**
     * @param number $maxLockWaitTime
     */
    public function setMaxLockWaitTime($maxLockWaitTime): void
    {
        $this->maxLockWaitTime = $maxLockWaitTime;
    }

    /**
     * @return number
     */
    public function getMinLockRetryMicrotime()
    {
        return $this->minLockRetryMicrotime;
    }

    /**
     * @param number $minLockRetryMicrotime
     */
    public function setMinLockRetryMicrotime($minLockRetryMicrotime): void
    {
        $this->minLockRetryMicrotime = $minLockRetryMicrotime;
    }

    /**
     * @return number
     */
    public function getMaxLockRetryMicrotime()
    {
        return $this->maxLockRetryMicrotime;
    }

    /**
     * @param number $maxLockRetryMicrotime
     */
    public function setMaxLockRetryMicrotime($maxLockRetryMicrotime): void
    {
        $this->maxLockRetryMicrotime = $maxLockRetryMicrotime;
    }
}
