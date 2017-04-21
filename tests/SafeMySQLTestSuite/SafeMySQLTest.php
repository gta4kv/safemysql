<?php

use SafeMySQL\Options;
use SafeMySQL\SafeMySQL;

class SafeMySQLTest extends PHPUnit_Framework_TestCase
{
    /**
     * @return mysqli
     */
    private function getMySQLiMock()
    {
        $mysqli = $this->getMockBuilder('mysqli')
            ->setMethods(['real_escape_string'])
            ->getMock();

        $mysqli->expects($this->any())
            ->method('real_escape_string')
            ->will($this->returnCallback(function ($string) {
                return addslashes($string);
            }));

        return $mysqli;
    }

    private function getSafeMySQL()
    {
        $options = new Options();
        $options->setMySQLi($this->getMySQLiMock());

        return new SafeMySQL($options);
    }

    public function testParseSuccess()
    {
        $db = $this->getSafeMySQL();

        $this->assertEquals($db->parse('INSERT INTO table VALUES (?s)', 'My"String"Value'), 'INSERT INTO table VALUES (\'My\"String\"Value\')');

        $this->assertEquals($db->parse('INSERT INTO table VALUES (?i)', 123), 'INSERT INTO table VALUES (123)');

        $this->assertEquals($db->parse('INSERT INTO table (?n) VALUES ()', 'columnName'), 'INSERT INTO table (`columnName`) VALUES ()');

        $this->assertEquals($db->parse('INSERT INTO table SET ?u', ['name' => 'myParams', 'value' => 'My"String"Value']), 'INSERT INTO table SET `name`=\'myParams\',`value`=\'My\"String\"Value\'');

        $this->assertEquals($db->parse('SELECT * FROM table WHERE ?p', $db->parse('field = ?s', 'My"String"Value')), 'SELECT * FROM table WHERE field = \'My\"String\"Value\'');
    }
}