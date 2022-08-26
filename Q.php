<?php
/*
Garrulity Object Relational Mapping System.
*/
namespace Fratello\DB;

class Q{
    private $pdo;
    private $database;
    private $tbname;
    private $col_name;

    public function __construct(bool $create_mode = false){

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
        $me = new static(true);
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
        $me = new static(true);
    }

    public function col(array $col = ['*']){
        $col_names = [];
        if(count($col) == 0){
            $col = ['*'];
        }

        foreach($col as $col_name){
            if(trim($col_name) != ''){
                array_push($col_names, $col_name);
            }
        }

        if(count($col_names) == 0){
            $col_names = ['*'];
        }

        $this->$col_name = $col_names;
        
        return $this;
    }
}