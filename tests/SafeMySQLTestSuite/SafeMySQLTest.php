<?php

use SafeMySQL\Options;
use SafeMySQL\SafeMySQL;

class SafeMySQLTest extends PHPUnit_Framework_TestCase
{
    public function testParseSuccess()
    {
        $db = $this->getSafeMySQL($this->getMySQLiMock());

        $this->assertEquals($db->parse('INSERT INTO table VALUES (?s)', 'My"String"Value'), 'INSERT INTO table VALUES (\'My\"String\"Value\')');

        $this->assertEquals($db->parse('INSERT INTO table VALUES (?i)', 123), 'INSERT INTO table VALUES (123)');

        $this->assertEquals($db->parse('INSERT INTO table (?n) VALUES ()', 'columnName'), 'INSERT INTO table (`columnName`) VALUES ()');

        $this->assertEquals($db->parse('INSERT INTO table SET ?u', ['name' => 'myParams', 'value' => 'My"String"Value']), 'INSERT INTO table SET `name`=\'myParams\',`value`=\'My\"String\"Value\'');

        $this->assertEquals($db->parse('SELECT * FROM table WHERE ?p', $db->parse('field = ?s', 'My"String"Value')), 'SELECT * FROM table WHERE field = \'My\"String\"Value\'');
    }

    private function getSafeMySQL(mysqli $mysqliMock)
    {
        $options = new Options();
        $options->setMySQLi($mysqliMock);

        return new SafeMySQL($options);
    }

    /**
     * @return mysqli
     */
    private function getMySQLiMock()
    {
        $mysqli = $this->getMockBuilder('mysqli')
            ->setMethods(['real_escape_string', 'query'])
            ->getMock();

        $mysqli->expects($this->any())
            ->method('real_escape_string')
            ->will($this->returnCallback(function ($string) {
                return addslashes($string);
            }));

        return $mysqli;
    }

    public function testFailedCreation()
    {
        $this->setExpectedException(\SafeMySQL\Exception\ConnectionException::class);

        new SafeMySQL(new Options());
    }

    public function testFailedQuery()
    {
        $mysqli = $this->getMySQLiMock();

        $mysqli->expects($this->any())
            ->method('query')
            ->will($this->returnCallback(function ($query) {
                return false;
            }));

        $db = $this->getSafeMySQL($mysqli);

        $this->setExpectedException(\SafeMySQL\Exception\QueryException::class);

        $db->query('INSERT INTO table VALUES (?s)', 'myString');
    }

    public function testSuccessQuery()
    {
        $mysqli = $this->getMySQLiMock();

        $mysqli->expects($this->any())
            ->method('query')
            ->will($this->returnCallback(function ($query) {
                return [['value' => 'yes']];
            }));

        $db = $this->getSafeMySQL($mysqli);

        $result = $db->query('SELECT * FROM ?n', 'users');

        $this->assertNotNull($result);
        $this->assertEquals($db->getLastQuery(), 'SELECT * FROM `users`');
    }
}