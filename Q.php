<?php
/*
Garrulity Object Relational Mapping System.
*/
namespace Fratello\DB;

use Closure;
use Fratello\Collection\Collection;
use Exception;
use stdClass;

class Q{
    private $pdo;
    private $sql = '';
    private $term = [];
    private $database;
    private $tbname;
    private $col_name;
    private $transaction;
    private $already_orderby = false;
    private $already_groupby = false;
    private $property_class = 'stdClass';
    private $more_already = false;
    private $stmt;

    public function __construct(bool $create_mode = false, string $bootstrap_sql = '', array $bootstrap_sql_value = []){
        $rdb = 'mysql';
        $db_user = 'root';
        $db_pass = 'root';
        $db_name = 'test_db';
        $db_host = 'localhost';
        $db_port = '3306';
        $db_option = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if($create_mode){
            $pdo = new \PDO($rdb.':host='.$db_host, $db_user, $db_pass, $db_option);
            $this->pdo = $pdo;
        }else{
            $pdo = new \PDO($rdb.':dbname='.$db_name.';host='.$db_host.';port='.$db_port, $db_user, $db_pass, $db_option);
            $this->pdo = $pdo;
        }

        $this->pdo->beginTransaction();
        if($create_mode){
            $stmt = $this->pdo->prepare($bootstrap_sql);
            $stmt->execute($bootstrap_sql_value);
            $this->confirm();
        }
    }

    public static function table(string $tbname){
        $me = new static();
        $me->tbname = $tbname;
        return $me;
    }

    public static function create_table(string $table_name, array $table_setting, array $option, bool $safe = true){
        if($safe){
            $SQL = 'CREATE TABLE IF NOT EXISTS `'.trim($table_name).'` (';
        }else{
            $SQL = 'CREATE TABLE `'.trim($table_name).'` (';
        }
        foreach($table_setting as $col_name => $table_option){
            $SQL .= '`'.trim($col_name).'` '.trim($table_option).', ';
        }
        $SQL = trim($SQL, ',').')';
        foreach($option as $tb_options){
            $SQL .= trim($tb_options).', ';
        }
        $SQL = trim($SQL, ',').')';
        new static(true, $SQL);
    }

    public static function create_db(string $db_name, array $db_option, bool $safe = true){
        if($safe){
            $SQL = 'CREATE DATABASE IF NOT EXISTS `'.trim($db_name).'`';
        }else{
            $SQL = 'CREATE DATABASE `'.trim($db_name).'` ';
        }
        foreach($db_option as $options){
            $SQL .= trim($options).', ';
        }
        $SQL = trim($SQL, ',').')';
        new static(true, $SQL);
    }

    public static function raw_sql(string $sql, array $value = []){
        $me = new static();
        $me->set_sql($sql, $value);
        return $me;
    }

    public function set_sql(string $sql, array $value = []){
        $this->sql = $sql;
        $this->term = $value;
        return $this;
    }

    public function select(string ...$col){
        $col_names = [];
        if(count($col) == 0){
            $col = ['*'];
        }

        foreach($col as $col_name){
            if(trim($col_name) != ''){
                array_push($col_names, '`'.$col_name.'`');
            }
        }

        if(count($col_names) == 0){
            $col_names = ['*'];
        }

        $this->$col_name = $col_names;

        $this->sql = 'SELECT '.implode(', ', $col_names).'FROM '.trim($this->tbname);
        
        return $this;
    }

    public function insert(array $value){
        $SQL = 'INSERT INTO '.trim($this->tbname);
        $col = [];
        $val = [];
        foreach($value as $column => $values){
            array_push($col, $column);
            array_push($val, $values);
        }

        $SQL .= ' (`'.implode('`, `', $col).'`) VALUES ('.implode(', ', $val).')';

        $this->sql = $SQL;
        return $this;
    }

    public function update(array $value){
        $SQL = 'UPDATE '.trim($this->tbname).' SET ';
        foreach($value as $column => $values){
            $SQL .= '`'.trim($column).'` = '.trim($values);
        }

        $this->sql = $SQL;
        return $this;
    }

    public function delete(){
        $this->sql = 'DELETE FROM '.trim($this->tbname);
        return $this;
    }

    public function truncate(bool $unconfirmed_execute = false){
        $SQL = 'TRUNCATE TABLE '.trim($this->tbname);
        if($unconfirmed_execute){
            $stmt = $this->pdo->prepare($SQL);
            $stmt->execute();
            $this->confirm();
        }else{
            return $this;
        }
    }

    public function safety_lock(){
        //TODO
    }

    public function join(string $join_out_table, string $join_out_col, string $join_col_basetbl, bool $inner = false,  bool $right = false){
        if($inner){
            $SQL = 'INNER JOIN ';
        }else{
            $SQL = 'OUTER JOIN ';
        }

        if(!$inner && $right){
            $SQL .= 'RIGHT ';
        }else if(!$inner && !$right){
            $SQL .= 'LEFT ';
        }

        $this->sql .= ' '.$SQL.trim($join_out_table).' ON '.trim($this->table).'.'.trim($join_col_basetbl).' = '.trim($join_out_table).'.'.trim($join_out_col);
        return $this;
    }

    public function order_by(string $col_name, bool $asc = false){
        $SQL = '`'.trim($col_name).'` ';
        if($asc){
            $SQL .= 'ASC';
        }else{
            $SQL .= 'DESC';
        }

        if($this->already_orderby){
            $this->sql .= ', ';
        }else{
            $this->sql .= 'ORDER BY '.$SQL;
        }
        return $this;
    }

    private function where_create(string $type, string $col, string $operator, $value){
        if($this->sql == ''){
            $this->select('*');
        }
        $SQL = $type.'`'.trim($col).'` '.trim($operator).' ?';
        $this->sql = $SQL;
        $this->term = [$value];
    }

    public function having(string $col, $operator, $value = ''){
        $having = ' HAVING ';

        if($value == ''){
            $value = $operator;
            $operator = '=';
        }

        $this->where_create($having, $col, $operator, $value);

        return $this;
    }

    public function where(string $col, $operator, $value = '', bool $isNot = false){        
        if($isNot){
            $where = ' WHERE NOT ';
        }else{
            $where = ' WHERE ';
        }

        if($value == ''){
            $value = $operator;
            $operator = '=';
        }

        $this->where_create($where, $col, $operator, $value);
        
        return $this;
    }

    public function and(string $col, $operator, $value = '', bool $isNot = false){
        if($isNot){
            $and = ' AND NOT ';
        }else{
            $and = ' AND ';
        }
        
        if($value == ''){
            $value = $operator;
            $operator = '=';
        }

        $this->where_create($and, $col, $operator, $value);
        
        return $this;
    }

    public function or(string $col, $operator, $value = '', bool $isNot = false){
        if($isNot){
            $or = ' OR NOT ';
        }else{
            $or = ' OR ';
        }

        if($value == ''){
            $value = $operator;
            $operator = '=';
        }

        $this->where_create($or, $col, $operator, $value);
        
        return $this;
    }

    protected function ClassSet(string $className){
        $this->property_class = $className;
    }

    public function to_sql(){
        return $this->sql;
    }

    public function pull(int $id){
        $SQL = 'SELECT * FROM `'.trim($this->tbname).'` WHERE id = ?';
        $stmt = $this->pdo->prepare($SQL);
        $stmt->execute([$id]);
        $stmt->setFetchMode(\PDO::FETCH_CLASS, $this->property_class);
        $result = $stmt->fetch();
        $this->sql = $SQL;
        $this->confirm();
        return $result;
    }

    public function top(){       
        if($this->sql == ''){
            $this->select('*');
        }
        
        $stmt = $this->pdo->prepare($this->sql);
        $stmt->execute($this->term);
        $stmt->setFetchMode(\PDO::FETCH_CLASS, $this->property_class);
        $result = $stmt->fetch();

        $this->confirm();

        return $result;
    }

    public function gather(){
        if($this->sql == ''){
            $this->select('*');
        }
        $stmt = $this->pdo->prepare($this->sql);
        $stmt->execute($this->term);
        $stmt->setFetchMode(\PDO::FETCH_CLASS, $this->property_class);
        $result = $stmt->fetchAll();
        $result = new Collection($result);

        $this->confirm();

        return $result;
    }

    public function designate(string $col_name){
        $get = $this->top;
        
        return $get->$$col_name;
    }

    public function specify(string $value, string $key = ''){
        $base = $this->gather();
        $result = $base->specify($value, $key);
        return $result;
    }

    public function more(){
        if(!$this->more_already){
            if($this->sql == ''){
                $this->select('*');
            }
            $stmt = $this->pdo->prepare($this->sql);
            $stmt->execute($this->term);
            $stmt->setFetchMode(\PDO::FETCH_CLASS, $this->property_class);
            $this->stmt = $stmt;
        }
        return $this->stmt->fetch();
    }

    public function dominate_up(int $limit, Closure $function){
        $next_id = 0;

        while(true){
            $limit = ' LIMIT ';
            if($next_id == 0){
                $limit .= $limit;
            }else{
                $limit .= $next_id.', '.$limit;
            }

            if($this->sql == ''){
                $this->select('*');
            }
            $stmt = $this->pdo->prepare($this->sql.$limit);
            $stmt->execute($this->term);
            $stmt->setFetchMode(\PDO::FETCH_CLASS, $this->property_class);
            $result = $stmt->fetchAll();
            $result = new Collection($result);

            $this->confirm();

            $next_id += $limit;

            $is_stop = $function($result);

            if(is_bool($is_stop) && !$is_stop || $result->count() == 0){
                break;
            }
        }
    }

    public function union(Q $garrulity, bool $type = false){
        if($type){
            $SQL = ' UNION ALL ';
        }else{
            $SQL = ' UNION ';
        }

        $SQL .= $garrulity->to_sql();
        $this->sql = $SQL;

        return $this;
    }

    public function group_by(string $colname){
        $SQL = '`'.trim($colname).'` ';

        if($this->already_groupby){
            $this->sql .= ', ';
        }else{
            $this->sql .= 'GROUP BY '.$SQL;
        }

        return $this;
    }

    public function IsIn(){
        if($this->sql == ''){
            $this->select('*');
        }
        $count = $this->gather()->count();
        if($count != 0){
            return true;
        }else{
            return false;
        }
    }

    public function enter(){
        $stmt = $this->pdo->prepare($this->sql);
        $stmt->execute($this->term);
        $this->confirm();
    }

    private function confirm(){
        try{
            if($this->transaction){
                $this->pdo->commit();
            }else{
                $this->pdo->rollBack();
            }
        }catch(\PDOException $e){
            throw new Exception($e->getMessage());
            $this->pdo->rollBack();
        }
        return $this;
    }
}