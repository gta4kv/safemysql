<?php
namespace SafeMySQL;

use mysqli;
use mysqli_result;
use SafeMySQL\Exception\ConnectionException;
use SafeMySQL\Exception\InvalidArgumentException;
use SafeMySQL\Exception\InvalidOptionException;
use SafeMySQL\Exception\QueryException;

/**
 * @author col.shrapnel@gmail.com
 * @link http://phpfaq.ru/safemysql
 *
 * Safe and convenient way to handle SQL queries utilizing type-hinted placeholders.
 *
 * Key features
 * - set of helper functions to get the desired result right out of query, like in PEAR::DB
 * - conditional query building using parse() method to build queries of whatever comlexity,
 *   while keeping extra safety of placeholders
 * - type-hinted placeholders
 *
 *  Type-hinted placeholders are great because
 * - safe, as any other [properly implemented] placeholders
 * - no need for manual escaping or binding, makes the code extra DRY
 * - allows support for non-standard types such as identifier or array, which saves A LOT of pain in the back.
 *
 * Supported placeholders at the moment are:
 *
 * ?s ("string")  - strings (also DATE, FLOAT and DECIMAL)
 * ?i ("integer") - the name says it all
 * ?n ("name")    - identifiers (table and field names)
 * ?a ("array")   - complex placeholder for IN() operator  (substituted with string of 'a','b','c' format, without parenthesis)
 * ?u ("update")  - complex placeholder for SET operator (substituted with string of `field`='value',`field`='value' format)
 * and
 * ?p ("parsed") - special type placeholder, for inserting already parsed statements without any processing, to avoid double parsing.
 *
 * Connection:
 *
 * $db = new SafeMySQL(); // with default settings
 *
 * $opts = array(
 *        'user'    => 'user',
 *        'pass'    => 'pass',
 *        'db'      => 'db',
 *        'charset' => 'latin1'
 * );
 * $db = new SafeMySQL($opts); // with some of the default settings overwritten
 *
 * Alternatively, you can just pass an existing mysqli instance that will be used to run queries
 * instead of creating a new connection.
 * Excellent choice for migration!
 *
 * $db = new SafeMySQL(['mysqli' => $mysqli]);
 *
 * Some examples:
 *
 * $name = $db->getOne('SELECT name FROM table WHERE id = ?i',$_GET['id']);
 * $data = $db->getInd('id','SELECT * FROM ?n WHERE id IN ?a','table', array(1,2));
 * $data = $db->getAll("SELECT * FROM ?n WHERE mod=?s LIMIT ?i",$table,$mod,$limit);
 *
 * $ids  = $db->getCol("SELECT id FROM tags WHERE tagname = ?s",$tag);
 * $data = $db->getAll("SELECT * FROM table WHERE category IN (?a)",$ids);
 *
 * $data = array('offers_in' => $in, 'offers_out' => $out);
 * $sql  = "INSERT INTO stats SET pid=?i,dt=CURDATE(),?u ON DUPLICATE KEY UPDATE ?u";
 * $db->query($sql,$pid,$data,$data);
 *
 * if ($var === NULL) {
 *     $sqlpart = "field is NULL";
 * } else {
 *     $sqlpart = $db->parse("field = ?s", $var);
 * }
 * $data = $db->getAll("SELECT * FROM table WHERE ?p", $bar, $sqlpart);
 *
 */
class SafeMySQL
{

    /**
     * @var integer
     */
    const RESULT_ASSOC = MYSQLI_ASSOC;
    /**
     * @var integer
     */
    const RESULT_NUM = MYSQLI_NUM;

    /**
     * @var mysqli
     */
    protected $connection;

    /**
     * @var
     */
    protected $stats;

    /**
     * @var Options
     */
    private $options;


    /**
     * SafeMySQL constructor.
     * @param Options $options
     * @throws ConnectionException
     * @throws InvalidOptionException
     */
    public function __construct(Options $options)
    {
        $this->options = $options;

        if ($this->options->getMySQLi()) {
            if ($this->options->getMySQLi() instanceof mysqli) {
                $this->connection = $this->options->getMySQLi();

                return;
            } else {
                throw new InvalidOptionException('MySQLi option must be valid instance of MySQLi class');
            }
        }

        if ($this->options->isUsePersistentConnect()) {
            $this->options->setDbHost('p:' . $this->options->getDbHost());
        }


        $this->connection = @new mysqli(
            $this->options->getDbHost(), $this->options->getDbUser(), $this->options->getDbPass(),
            $this->options->getDbName(), $this->options->getDbPort(), $this->options->getDbPort()
        );


        if ($this->connection->connect_error) {
            throw new ConnectionException($this->connection->connect_errno . ' ' . $this->connection->connect_error);
        }

        if (!$this->connection->set_charset($this->options->getDbCharset())) {
            throw new InvalidOptionException($this->connection->error);
        }
    }

    /**
     * Conventional function to run a query with placeholders. A mysqli_query wrapper with placeholders support
     *
     * Examples:
     * $db->query("DELETE FROM table WHERE id=?i", $id);
     *
     * @param string $query - an SQL query with placeholders
     * @param mixed ...$args unlimited number of arguments to match placeholders in the query
     * @return mysqli|FALSE whatever mysqli_query returns
     */
    public function query($query, ...$args)
    {
        return $this->rawQuery($this->prepareQuery($query, $args));
    }

    /**
     * protected function which actually runs a query against Mysql server.
     * also logs some stats like profiling info and error message
     *
     * @param string $query - a regular SQL query
     * @return bool|mysqli_result result resource or FALSE on error
     * @throws QueryException
     */
    protected function rawQuery($query)
    {
        $start = microtime(true);
        $res = $this->connection->query($this->connection, $query);
        $timer = microtime(true) - $start;

        $this->stats[] = [
            'query' => $query,
            'start' => $start,
            'timer' => $timer,
        ];

        if (!$res) {
            $error = $this->connection->error;

            end($this->stats);
            $key = key($this->stats);
            $this->stats[$key]['error'] = $error;
            $this->cutStats();

            throw new QueryException("$error; Full query: [$query]");
        }

        $this->cutStats();

        return $res;
    }

    /**
     * On a long run we can eat up too much memory with mere statistics
     * Let's keep it at reasonable size, leaving only last 100 entries.
     */
    protected function cutStats()
    {
        if (count($this->stats) > 100) {
            reset($this->stats);
            $first = key($this->stats);
            unset($this->stats[$first]);
        }
    }

    /**
     * @param string $query
     * @param array $args
     * @return string
     * @throws InvalidArgumentException
     */
    protected function prepareQuery($query, $args)
    {
        $prepared = '';

        $array = preg_split('~(\?[nsiuap])~u', $query, null, PREG_SPLIT_DELIM_CAPTURE);

        $arguments = count($args);
        $placeholders = floor(count($array) / 2);

        if ($arguments != $placeholders) {
            throw new InvalidArgumentException("Number of args ($arguments) doesn't match number of placeholders ($placeholders) in [$query]");
        }

        foreach ($array as $i => $part) {
            if (($i % 2) == 0) {
                $prepared .= $part;
                continue;
            }

            $value = array_shift($args);

            switch ($part) {
                case '?n':
                    $part = $this->escapeIdent($value);
                    break;
                case '?s':
                    $part = $this->escapeString($value);
                    break;
                case '?i':
                    $part = $this->escapeInt($value);
                    break;
                case '?a':
                    $part = $this->createIn($value);
                    break;
                case '?u':
                    $part = $this->createSet($value);
                    break;
                case '?p':
                    $part = $value;
                    break;
            }
            $prepared .= $part;
        }

        return $prepared;
    }

    /**
     * @param $value
     * @return string
     * @throws InvalidArgumentException
     */
    protected function escapeIdent($value)
    {
        if ($value) {
            return '`' . str_replace('`', '``', $value) . '`';
        }

        throw new InvalidArgumentException('Empty value for identifier (?n) placeholder');
    }

    /**
     * @param string|null $value
     * @return string
     */
    protected function escapeString($value)
    {
        if ($value === null) {
            return 'NULL';
        }

        return "'" . $this->connection->real_escape_string($value) . "'";
    }

    /**
     * @param null|integer $value
     * @return bool|string
     * @throws InvalidArgumentException
     */
    protected function escapeInt($value)
    {
        if ($value === null) {
            return 'NULL';
        }
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Integer (?i) placeholder expects numeric value, ' . gettype($value) . ' given');
        }
        if (is_float($value)) {
            $value = number_format($value, 0, '.', ''); // may lose precision on big numbers
        }

        return $value;
    }

    /**
     * @param $data
     * @return string|void
     * @throws InvalidArgumentException
     */
    protected function createIn(array $data)
    {
        if (!$data) {
            return 'NULL';
        }

        $query = $comma = '';
        foreach ($data as $value) {
            $query .= $comma . $this->escapeString($value);
            $comma = ",";
        }

        return $query;
    }

    /**
     * @param array $data
     * @return null|string
     * @throws InvalidArgumentException
     */
    protected function createSet(array $data)
    {
        if (!$data) {
            throw new InvalidArgumentException('Empty array for SET (?u) placeholder');
        }

        $query = $comma = '';
        foreach ($data as $key => $value) {
            $query .= $comma . $this->escapeIdent($key) . '=' . $this->escapeString($value);
            $comma = ',';
        }

        return $query;
    }

    /**
     * Conventional function to get number of affected rows.
     *
     * @return int
     */
    public function affectedRows()
    {
        return $this->connection->affected_rows;
    }

    /**
     * Conventional function to get last insert id.
     *
     * @return int|string
     */
    public function insertId()
    {
        return $this->connection->insert_id;
    }

    /**
     * Conventional function to get number of rows in the resultset.
     *
     * @param mysqli_result $result - myqli result
     * @return int
     */
    public function numRows(mysqli_result $result)
    {
        $result->num_rows;
    }

    /**
     * Helper function to get scalar value right out of query and optional arguments
     *
     * Examples:
     * $name = $db->getOne("SELECT name FROM table WHERE id=1");
     * $name = $db->getOne("SELECT name FROM table WHERE id=?i", $id);
     *
     * @param string $query - an SQL query with placeholders
     * @param mixed ...$args unlimited number of arguments to match placeholders in the query
     * @return string|FALSE either first column of the first row of resultset or FALSE if none found
     */
    public function getOne($query, ...$args)
    {
        $query = $this->prepareQuery($query, $args);
        if ($res = $this->rawQuery($query)) {
            $row = $this->fetch($res);
            if (is_array($row)) {
                return reset($row);
            }
            $this->free($res);
        }

        return false;
    }

    /**
     * Conventional function to fetch single row.
     *
     * @param mysqli_result $result
     * @param int $mode - optional fetch mode, RESULT_ASSOC|RESULT_NUM, default RESULT_ASSOC
     * @return array|null
     */
    public function fetch(mysqli_result $result, $mode = self::RESULT_ASSOC)
    {
        return $result->fetch_array($mode);
    }

    /**
     * Conventional function to free the resultset.
     * @param mysqli_result $result
     */
    public function free(mysqli_result $result)
    {
        $result->free();
    }

    /**
     * Helper function to get single row right out of query and optional arguments
     *
     * Examples:
     * $data = $db->getRow("SELECT * FROM table WHERE id=1");
     * $data = $db->getOne("SELECT * FROM table WHERE id=?i", $id);
     *
     * @param string $query - an SQL query with placeholders
     * @param mixed ...$args unlimited number of arguments to match placeholders in the query
     * @return array|FALSE either associative array contains first row of resultset or FALSE if none found
     */
    public function getRow($query, ...$args)
    {
        $query = $this->prepareQuery($query, $args);

        if ($res = $this->rawQuery($query)) {
            $ret = $this->fetch($res);
            $this->free($res);

            return $ret;
        }

        return false;
    }

    /**
     * Helper function to get single column right out of query and optional arguments
     *
     * Examples:
     * $ids = $db->getCol("SELECT id FROM table WHERE cat=1");
     * $ids = $db->getCol("SELECT id FROM tags WHERE tagname = ?s", $tag);
     *
     * @param string $query - an SQL query with placeholders
     * @param mixed ...$args unlimited number of arguments to match placeholders in the query
     * @return array|FALSE either enumerated array of first fields of all rows of resultset or FALSE if none found
     */
    public function getCol($query, ...$args)
    {
        $ret = [];
        $query = $this->prepareQuery(func_get_args());

        if ($res = $this->rawQuery($query)) {
            while ($row = $this->fetch($res)) {
                $ret[] = reset($row);
            }
            $this->free($res);
        }

        return $ret;
    }

    /**
     * Helper function to get all the rows of resultset right out of query and optional arguments
     *
     * Examples:
     * $data = $db->getAll("SELECT * FROM table");
     * $data = $db->getAll("SELECT * FROM table LIMIT ?i,?i", $start, $rows);
     *
     * @param string $query - an SQL query with placeholders
     * @param mixed ...$args unlimited number of arguments to match placeholders in the query
     * @return array enumerated 2d array contains the resultset. Empty if no rows found.
     */
    public function getAll($query, ...$args)
    {
        $ret = [];
        $query = $this->prepareQuery(func_get_args());

        if ($res = $this->rawQuery($query)) {
            while ($row = $this->fetch($res)) {
                $ret[] = $row;
            }
            $this->free($res);
        }

        return $ret;
    }

    /**
     * Helper function to get all the rows of resultset into indexed array right out of query and optional arguments
     *
     * Examples:
     * $data = $db->getInd("id", "SELECT * FROM table");
     * $data = $db->getInd("id", "SELECT * FROM table LIMIT ?i,?i", $start, $rows);
     *
     * @param string $index - name of the field which value is used to index resulting array
     * @param string $query - an SQL query with placeholders
     * @param mixed ...$args unlimited number of arguments to match placeholders in the query
     * @return array - associative 2d array contains the resultset. Empty if no rows found.
     */
    public function getInd($index, $query, ...$args)
    {
        $query = $this->prepareQuery($query, $args);

        $ret = [];
        if ($res = $this->rawQuery($query)) {
            while ($row = $this->fetch($res)) {
                $ret[$row[$index]] = $row;
            }
            $this->free($res);
        }

        return $ret;
    }

    /**
     * Helper function to get a dictionary-style array right out of query and optional arguments
     *
     * Examples:
     * $data = $db->getIndCol("name", "SELECT name, id FROM cities");
     *
     * @param string $index - name of the field which value is used to index resulting array
     * @param string $query - an SQL query with placeholders
     * @param mixed ...$args unlimited number of arguments to match placeholders in the query
     * @return array - associative array contains key=value pairs out of resultset. Empty if no rows found.
     */
    public function getIndCol($index, $query, ...$args)
    {
        $query = $this->prepareQuery($query, $args);

        $ret = [];
        if ($res = $this->rawQuery($query)) {
            while ($row = $this->fetch($res)) {
                $key = $row[$index];
                unset($row[$index]);
                $ret[$key] = reset($row);
            }
            $this->free($res);
        }

        return $ret;
    }

    /**
     * Function to parse placeholders either in the full query or a query part
     * unlike native prepared statements, allows ANY query part to be parsed
     *
     * useful for debug
     * and EXTREMELY useful for conditional query building
     * like adding various query parts using loops, conditions, etc.
     * already parsed parts have to be added via ?p placeholder
     *
     * Examples:
     * $query = $db->parse("SELECT * FROM table WHERE foo=?s AND bar=?s", $foo, $bar);
     * echo $query;
     *
     * if ($foo) {
     *     $qpart = $db->parse(" AND foo=?s", $foo);
     * }
     * $data = $db->getAll("SELECT * FROM table WHERE bar=?s ?p", $bar, $qpart);
     *
     * @param string $query - whatever expression contains placeholders
     * @param mixed ...$args unlimited number of arguments to match placeholders in the expression
     * @return string - initial expression with placeholders substituted with data.
     */
    public function parse($query, ...$args)
    {
        return $this->prepareQuery($query, $args);
    }

    /**
     * function to implement whitelisting feature
     * sometimes we can't allow a non-validated user-supplied data to the query even through placeholder
     * especially if it comes down to SQL OPERATORS
     *
     * Example:
     *
     * $order = $db->whiteList($_GET['order'], array('name','price'));
     * $dir   = $db->whiteList($_GET['dir'],   array('ASC','DESC'));
     * if (!$order || !dir) {
     *     throw new http404(); //non-expected values should cause 404 or similar response
     * }
     * $sql  = "SELECT * FROM table ORDER BY ?p ?p LIMIT ?i,?i"
     * $data = $db->getArr($sql, $order, $dir, $start, $per_page);
     *
     * @param string $input - field name to test
     * @param  array $allowed - an array with allowed variants
     * @param  boolean $default - optional variable to set if no match found. Default to false.
     * @return string|FALSE    - either sanitized value or FALSE
     */
    public function whiteList($input, $allowed, $default = false)
    {
        $found = array_search($input, $allowed);

        return ($found === false) ? $default : $allowed[$found];
    }

    /**
     * function to filter out arrays, for the whitelisting purposes
     * useful to pass entire superglobal to the INSERT or UPDATE query
     * OUGHT to be used for this purpose,
     * as there could be fields to which user should have no access to.
     *
     * Example:
     * $allowed = array('title','url','body','rating','term','type');
     * $data    = $db->filterArray($_POST,$allowed);
     * $sql     = "INSERT INTO ?n SET ?u";
     * $db->query($sql,$table,$data);
     *
     * @param  array $input - source array
     * @param  array $allowed - an array with allowed field names
     * @return array filtered out source array
     */
    public function filterArray($input, $allowed)
    {
        foreach (array_keys($input) as $key) {
            if (!in_array($key, $allowed)) {
                unset($input[$key]);
            }
        }

        return $input;
    }

    /**
     * Function to get last executed query.
     *
     * @return string|null either last executed query or NULL if were none
     */
    public function lastQuery()
    {
        $last = end($this->stats);

        return $last['query'];
    }

    /**
     * Function to get all query statistics.
     *
     * @return array contains all executed queries with timings and errors
     */
    public function getStats()
    {
        return $this->stats;
    }

    /**
     * @return string
     */
    protected function getCaller()
    {
        $traces = debug_backtrace();
        $caller = '';
        foreach ($traces as $trace) {
            if (isset($trace['class']) && $trace['class'] == __CLASS__) {
                $caller = $trace['file'] . ' on line ' . $trace['line'];
            } else {
                break;
            }
        }

        return $caller;
    }
}
