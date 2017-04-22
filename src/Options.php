<?php
namespace SafeMySQL;


use Exception;
use mysqli;

/**
 * Class Options
 * @package SafeMySQL
 */
class Options
{
    /**
     * @var string
     */
    private $dbHost = 'localhost';

    /**
     * @var string
     */
    private $dbUser = 'root';

    /**
     * @var string
     */
    private $dbPass = '';

    /**
     * @var string
     */
    private $dbName = 'test';

    /**
     * @var string
     */
    private $dbPort = null;

    /**
     * @var string
     */
    private $dbSocket = null;

    /**
     * @var string
     */
    private $dbCharset = 'utf8';

    /**
     * @var boolean
     */
    private $usePersistentConnect = false;

    /**
     * @var string
     */
    private $exceptionClass;

    /**
     * @var null|mysqli
     */
    private $mySQLi = null;

    /**
     * @return mysqli|null
     */
    public function getMySQLi()
    {
        return $this->mySQLi;
    }

    /**
     * @param mysqli|null $mySQLi
     * @return $this
     */
    public function setMySQLi($mySQLi)
    {
        $this->mySQLi = $mySQLi;

        return $this;
    }

    /**
     * @return string
     */
    public function getDbHost()
    {
        return $this->dbHost;
    }

    /**
     * @param string $dbHost
     * @return $this
     */
    public function setDbHost($dbHost)
    {
        $this->dbHost = $dbHost;

        return $this;
    }

    /**
     * @return string
     */
    public function getDbUser()
    {
        return $this->dbUser;
    }

    /**
     * @param string $dbUser
     * @return $this
     */
    public function setDbUser($dbUser)
    {
        $this->dbUser = $dbUser;

        return $this;
    }

    /**
     * @return string
     */
    public function getDbPass()
    {
        return $this->dbPass;
    }

    /**
     * @param string $dbPass
     * @return $this
     */
    public function setDbPass($dbPass)
    {
        $this->dbPass = $dbPass;

        return $this;
    }

    /**
     * @return string
     */
    public function getDbName()
    {
        return $this->dbName;
    }

    /**
     * @param string $dbName
     * @return $this
     */
    public function setDbName($dbName)
    {
        $this->dbName = $dbName;

        return $this;
    }

    /**
     * @return string
     */
    public function getDbPort()
    {
        return $this->dbPort;
    }

    /**
     * @param string $dbPort
     * @return $this
     */
    public function setDbPort($dbPort)
    {
        $this->dbPort = $dbPort;

        return $this;
    }

    /**
     * @return string
     */
    public function getDbSocket()
    {
        return $this->dbSocket;
    }

    /**
     * @param string $dbSocket
     * @return $this
     */
    public function setDbSocket($dbSocket)
    {
        $this->dbSocket = $dbSocket;

        return $this;
    }

    /**
     * @return string
     */
    public function getDbCharset()
    {
        return $this->dbCharset;
    }

    /**
     * @param string $dbCharset
     * @return $this
     */
    public function setDbCharset($dbCharset)
    {
        $this->dbCharset = $dbCharset;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isUsePersistentConnect()
    {
        return $this->usePersistentConnect;
    }

    /**
     * @param boolean $usePersistentConnect
     * @return $this
     */
    public function setUsePersistentConnect($usePersistentConnect)
    {
        $this->usePersistentConnect = $usePersistentConnect;

        return $this;
    }

    /**
     * @return string
     */
    public function getExceptionClass()
    {
        return $this->exceptionClass;
    }

    /**
     * @param string $exceptionClass
     * @return $this
     */
    public function setExceptionClass($exceptionClass)
    {
        $this->exceptionClass = $exceptionClass;

        return $this;
    }
}