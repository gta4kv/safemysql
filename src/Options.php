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
     */
    public function setMySQLi($mySQLi)
    {
        $this->mySQLi = $mySQLi;
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
     */
    public function setDbHost($dbHost)
    {
        $this->dbHost = $dbHost;
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
     */
    public function setDbUser($dbUser)
    {
        $this->dbUser = $dbUser;
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
     */
    public function setDbPass($dbPass)
    {
        $this->dbPass = $dbPass;
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
     */
    public function setDbName($dbName)
    {
        $this->dbName = $dbName;
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
     */
    public function setDbPort($dbPort)
    {
        $this->dbPort = $dbPort;
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
     */
    public function setDbSocket($dbSocket)
    {
        $this->dbSocket = $dbSocket;
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
     */
    public function setDbCharset($dbCharset)
    {
        $this->dbCharset = $dbCharset;
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
     */
    public function setUsePersistentConnect($usePersistentConnect)
    {
        $this->usePersistentConnect = $usePersistentConnect;
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
     */
    public function setExceptionClass($exceptionClass)
    {
        $this->exceptionClass = $exceptionClass;
    }
}