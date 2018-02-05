<?php
/**
 *  mysqli.class.php 数据库实现类
 *
 * @copyright			(C) 2014-2015 DjGame
 * @license				MIT2.0
 * @lastmodify			2015-11-03
 */
final class mysqlx
{
    /**
     * 数据库配置信息
     * 
     * @var array
     */
    private $config = null;
    /**
     * 数据库连接资源句柄
     * 
     * @var mysqli
     */
    public $link = null;
    /**
     * 最近一次查询资源句柄
     * 
     * @var mysqli_result
     */
    public $last_result = null;
    /**
     * 统计数据库查询次数
     * 
     * @var int
     */
    public $querycount = 0;
    public function __construct ()
    {}
    /**
     * 打开数据库连接,有可能不真实连接数据库
     * 
     * @param $config 数据库连接参数            
     *
     * @return void
     */
    public function open ($config)
    {
        $this->config = $config;
        if ($config['autoconnect'] == 1) {
            $this->connect();
        }
    }
    /**
     * 真正开启数据库连接
     *
     * @return void
     */
    public function connect ()
    {
        $func = $this->config['pconnect'] == 1 ? 'mysqli_pconnect' : 'mysqli_connect';
        if (! $this->link = @$func($this->config['hostname'], 
        $this->config['username'], $this->config['password'], 
        $this->config['database'])) {
            $this->halt('Can not connect to MySQL server');
            return false;
        }
        $this->database = $this->config['database'];
        $this->link->set_charset($this->config['charset']);
        return $this->link;
    }
    public function ping ()
    {
        if (! mysqli_ping($this->link)) {
            $this->connect();
        }
        mysqli_ping($this->link);
    }
    /**
     * 数据库查询执行方法
     * 
     * @param $sql 要执行的sql语句            
     * @return mysqli_result
     */
    private function execute ($sql)
    {
        if (! is_object($this->link)) {
            $this->connect();
        }
        $this->last_result = mysqli_query($this->link, $sql) or
         $this->halt(mysqli_error($this->link), $sql);
        $this->querycount ++;
        return $this->last_result;
    }
    /**
     * 执行sql查询
     * 
     * @param $data 需要查询的字段值[例`name`,`gender`,`birthday`]            
     * @param $table 数据表            
     * @param $where 查询条件[例`name`='$name']            
     * @param $limit 返回结果范围[例：10或10,10
     *            默认为空]
     * @param $order 排序方式
     *            [默认按数据库默认方式排序]
     * @param $group 分组方式
     *            [默认为空]
     * @param $key 返回数组按键名排序            
     * @return array 查询结果集数组
     */
    public function select ($data, $table, $where = '', $limit = '', $order = '', 
    $group = '', $key = '')
    {
        $where = $where == '' ? '' : ' WHERE ' . $where;
        $order = $order == '' ? '' : ' ORDER BY ' . $order;
        $group = $group == '' ? '' : ' GROUP BY ' . $group;
        $limit = $limit == '' ? '' : ' LIMIT ' . $limit;
        $field = explode(',', $data);
        array_walk($field, array($this, 'add_special_char'));
        $data = implode(',', $field);
        $sql = 'SELECT ' . $data . ' FROM `' . $this->config['database'] . '`.`' .
         $table . '`' . $where . $group . $order . $limit;
        $this->execute($sql);
        $datalist = $this->fetch_array();
        return $datalist;
    }
    /**
     * 获取单条记录查询
     * 
     * @param $data 需要查询的字段值[例`name`,`gender`,`birthday`]            
     * @param $table 数据表            
     * @param $where 查询条件            
     * @param $order 排序方式
     *            [默认按数据库默认方式排序]
     * @param $group 分组方式
     *            [默认为空]
     * @return array/null 数据查询结果集,如果不存在，则返回空
     */
    public function get_one ($data, $table, $where = '', $order = '', $group = '')
    {
        $where = $where == '' ? '' : ' WHERE ' . $where;
        $order = $order == '' ? '' : ' ORDER BY ' . $order;
        $group = $group == '' ? '' : ' GROUP BY ' . $group;
        $limit = ' LIMIT 1';
        $field = explode(',', $data);
        array_walk($field, array($this, 'add_special_char'));
        $data = implode(',', $field);
        $sql = 'SELECT ' . $data . ' FROM `' . $this->config['database'] . '`.`' .
         $table . '`' . $where . $group . $order . $limit;
        $this->execute($sql);
        $res = $this->fetch_next();
        $this->free_result();
        return $res;
    }
    /**
     * 遍历查询结果集
     * 
     * @param $type 返回结果集类型
     *            MYSQL_ASSOC，MYSQL_NUM 和 MYSQL_BOTH
     * @return array
     */
    public function fetch_next ($type = MYSQLI_ASSOC)
    {
        $res = $this->last_result->fetch_assoc();
        if (! $res) {
            $this->free_result();
        }
        return $res;
    }
    /**
     * 释放查询资源
     * 
     * @return void
     */
    public function free_result ()
    {
        if (is_object($this->last_result)) {
            $this->last_result->free_result();
            $this->last_result = null;
        }
    }
    /**
     * 直接执行sql查询
     * 
     * @param $sql 查询sql语句            
     * @return mysqli_result
     */
    public function query ($sql)
    {
        return $this->execute($sql);
    }
    /**
     * 执行添加记录操作
     * 
     * @param $data 要增加的数据，参数为数组。数组key为字段值，数组值为数据取值            
     * @param $table 数据表            
     * @return boolean
     */
    public function insert ($data, $table, $return_insert_id = false, 
    $replace = false)
    {
        if (! is_array($data) || $table == '' || count($data) == 0) {
            return false;
        }
        $fielddata = array_keys($data);
        $valuedata = array_values($data);
        array_walk($fielddata, array($this, 'add_special_char'));
        array_walk($valuedata, array($this, 'escape_string'));
        $field = implode(',', $fielddata);
        $value = implode(',', $valuedata);
        $cmd = $replace ? 'REPLACE INTO' : 'INSERT INTO';
        $sql = $cmd . ' `' . $this->config['database'] . '`.`' . $table . '`(' .
         $field . ') VALUES (' . $value . ')';
        $return = $this->execute($sql);
        return $return_insert_id ? $this->insert_id() : $return;
    }
    /**
     * 执行添加记录操作
     * 
     * @param $data 要增加的数据，参数为数组。数组key为字段值，数组值为数据取值            
     * @param $table 数据表            
     * @return boolean
     */
    public function insertArray ($data, $table, $return_insert_id = false, 
    $replace = false)
    {
        if (! is_array($data) || $table == '' || count($data) == 0) {
            return false;
        }
        $first = array_shift($data);
        $fielddata = array_keys($first);
        array_walk($fielddata, array($this, 'add_special_char'));
        array_unshift($data, $first);
        $field = implode(',', $fielddata);
        // $value = implode (',', $valuedata);
        $values = '';
        foreach ($data as $value) {
            array_walk($value, array($this, 'escape_string'));
            $values .= '(' . implode(',', $value) . '),';
        }
        $values = substr($values, 0, strlen($values) - 1);
        $cmd = $replace ? 'REPLACE INTO' : 'INSERT INTO';
        $sql = $cmd . ' `' . $this->config['database'] . '`.`' . $table . '`(' .
         $field . ') VALUES ' . $values;
        // file_put_contents('D:\\sql.sql', $sql);
        $return = $this->execute($sql);
        return $return_insert_id ? $this->insert_id() : $return;
    }
    /**
     * 获取最后一次添加记录的主键号
     * 
     * @return int
     */
    public function insert_id ()
    {
        return mysqli_insert_id($this->link);
    }
    /**
     * 执行更新记录操作
     * 
     * @param $data 要更新的数据内容，参数可以为数组也可以为字符串，建议数组。
     *            为数组时数组key为字段值，数组值为数据取值
     *            为字符串时[例：`name`='phpcms',`hits`=`hits`+1]。
     *            为数组时[例: array('name'=>'phpcms','password'=>'123456')]
     *            数组可使用array('name'=>'+=1', 'base'=>'-=1');程序会自动解析为`name` = `name` + 1, `base` = `base` - 1
     * @param $table 数据表            
     * @param $where 更新数据时的条件            
     * @return boolean
     */
    public function update ($data, $table, $where = '')
    {
        if ($table == '' or $where == '') {
            return false;
        }
        $where = ' WHERE ' . $where;
        $field = '';
        if (is_string($data) && $data != '') {
            $field = $data;
        } elseif (is_array($data) && count($data) > 0) {
            $fields = array();
            foreach ($data as $k => $v) {
                switch (substr($v, 0, 2)) {
                    case '+=':
                        $v = substr($v, 2);
                        if (is_numeric($v)) {
                            $fields[] = $this->add_special_char($k) . '=' .
                             $this->add_special_char($k) . '+' .
                             $this->escape_string($v, '', false);
                        } else {
                            continue;
                        }
                        break;
                    case '-=':
                        $v = substr($v, 2);
                        if (is_numeric($v)) {
                            $fields[] = $this->add_special_char($k) . '=' .
                             $this->add_special_char($k) . '-' .
                             $this->escape_string($v, '', false);
                        } else {
                            continue;
                        }
                        break;
                    default:
                        $fields[] = $this->add_special_char($k) . '=' .
                         $this->escape_string($v);
                }
            }
            $field = implode(',', $fields);
        } else {
            return false;
        }
        $sql = 'UPDATE `' . $this->config['database'] . '`.`' . $table . '` SET ' .
         $field . $where;
        return $this->execute($sql);
    }
    /**
     * 执行删除记录操作
     * 
     * @param $table 数据表            
     * @param $where 删除数据条件,不充许为空。
     *            如果要清空表，使用empty方法
     * @return boolean
     */
    public function delete ($table, $where)
    {
        if ($table == '' || $where == '') {
            return false;
        }
        $where = ' WHERE ' . $where;
        $sql = 'DELETE FROM `' . $this->config['database'] . '`.`' . $table . '`' .
         $where;
        return $this->execute($sql);
    }
    /**
     * 获取最后数据库操作影响到的条数
     * 
     * @return int
     */
    public function affected_rows ()
    {
        return mysqli_affected_rows($this->link);
    }
    /**
     * 获取数据表主键
     * 
     * @param $table 数据表            
     * @return array
     */
    public function get_primary ($table)
    {
        $this->execute("SHOW COLUMNS FROM $table");
        while (($r = $this->fetch_next()) != FALSE) {
            if ($r['Key'] == 'PRI')
                break;
        }
        return $r['Field'];
    }
    /**
     * 获取表字段
     * 
     * @param $table 数据表            
     * @return array
     */
    public function get_fields ($table)
    {
        $fields = array();
        $this->execute("SHOW COLUMNS FROM $table");
        while (($r = $this->fetch_next()) != FALSE) {
            $fields[$r['Field']] = $r['Type'];
        }
        return $fields;
    }
    public function fetch_array ()
    {
        $data = array();
        if (is_object($this->last_result)) {
            if (method_exists($this->last_result, 'fetch_all')) {
                $data = $this->last_result->fetch_all(MYSQLI_ASSOC);
                $this->last_result->free_result();
            } else {
                while (($r = $this->last_result->fetch_array(MYSQLI_ASSOC)) !=
                 FALSE) {
                    $data[] = $r;
                }
            }
        }
        return $data;
    }
    /**
     * 检查不存在的字段
     * 
     * @param $table 表名            
     * @return array
     */
    public function check_fields ($table, $array)
    {
        $fields = $this->get_fields($table);
        $nofields = array();
        foreach ($array as $v) {
            if (! array_key_exists($v, $fields)) {
                $nofields[] = $v;
            }
        }
        return $nofields;
    }
    /**
     * 检查表是否存在
     * 
     * @param $table 表名            
     * @return boolean
     */
    public function table_exists ($table)
    {
        $this->execute("SHOW TABLES like '$table'");
        $data = $this->fetch_array();
        return $data;
    }
    public function list_tables ()
    {
        $tables = array();
        $this->execute("SHOW TABLES");
        while (($r = $this->fetch_next()) != FALSE) {
            $tables[] = $r['Tables_in_' . $this->config['database']];
        }
        return $tables;
    }
    /**
     * 检查字段是否存在
     * 
     * @param $table 表名            
     * @return boolean
     */
    public function field_exists ($table, $field)
    {
        $fields = $this->get_fields($table);
        return array_key_exists($field, $fields);
    }
    public function num_rows ($sql)
    {
        $this->last_result = $this->execute($sql);
        return mysqli_num_rows($this->last_result);
    }
    public function num_fields ($sql)
    {
        $this->last_result = $this->execute($sql);
        return mysqli_num_fields($this->last_result);
    }
    public function result ($sql, $row)
    {
        $this->last_result = $this->execute($sql);
        return $this->fetch_array();
    }
    public function error ()
    {
        return @mysqli_error($this->link);
    }
    public function errno ()
    {
        return intval(@mysqli_errno($this->link));
    }
    public function version ()
    {
        if (! is_resource($this->link)) {
            $this->connect();
        }
        return mysqli_get_server_info($this->link);
    }
    public function close ()
    {
        if (is_resource($this->link)) {
            @mysqli_close($this->link);
        }
    }
    public function halt ($message = '', $sql = '')
    {
        if ($this->config['debug']) {
            $this->errormsg = "<b>MySQL Query : </b> $sql <br /><b> MySQL Error : </b>" .
             $this->error() . " <br /> <b>MySQL Errno : </b>" . $this->errno() .
             " <br /><b> Message : </b> $message <br /><a href='http://faq.phpcms.cn/?errno=" .
             $this->errno() . "&msg=" . urlencode($this->error()) .
             "' target='_blank' style='color:red'>Need Help?</a>";
            $msg = $this->errormsg;
            echo '<div style="font-size:12px;text-align:left; border:1px solid #9cc9e0; padding:1px 4px;color:#000000;font-family:Arial, Helvetica,sans-serif;"><span>' .
             $msg . '</span></div>';
            exit();
        } else {
            return false;
        }
    }
    /**
     * 对字段两边加反引号，以保证数据库安全
     * 
     * @param $value 数组值            
     */
    public function add_special_char (&$value)
    {
        if ('*' == $value || false !== strpos($value, '(') ||
         false !== strpos($value, '.') || false !== strpos($value, '`')) {
            // 不处理包含* 或者 使用了sql方法。
        } else {
            $value = '`' . trim($value) . '`';
        }
        if (preg_match('/\b(select|insert|update|delete)\b/i', $value)) {
            $value = preg_replace('/\b(select|insert|update|delete)\b/i', '', 
            $value);
        }
        return $value;
    }
    /**
     * 对字段值两边加引号，以保证数据库安全
     * 
     * @param $value 数组值            
     * @param $key 数组key            
     * @param
     *            $quotation
     */
    public function escape_string (&$value, $key = '', $quotation = 1)
    {
        if ($quotation) {
            $q = '\'';
        } else {
            $q = '';
        }
        $value = $q . $value . $q;
        return $value;
    }
    /**
     * @param array $querys            
     * @return bool
     */
    public function multi_query (array $querys = array())
    {
        if(!count($querys))
        {
            return FALSE;
        }
        if (! is_object($this->link)) {
            $this->connect();
        }
        $sqls = join(";\n", $querys);
        return $this->link->multi_query($sqls);
    }
    /**
     * @param array $querys            
     * @return array()
     */
    public function get_multi_result ()
    {
       $results=array();
       
       do 
       {
           $data = array();
           $result=$this->link->store_result();
           if (is_object($result)) {
              
               if (method_exists($result, 'fetch_all')) {
                   $data = $result->fetch_all(MYSQLI_ASSOC);
                   $result->free_result();
               } else {
                 
                   while (($r = $result->fetch_array(MYSQLI_ASSOC))!=FALSE) {
                       $data[] = $r;
                   }
               }
            }
            $results[]=$data;
            if(!$this->link->more_results())
            {
                break;
            }
       }while(($result=$this->link->next_result())!=FALSE);
       return $results;
    }
}
?>