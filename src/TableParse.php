<?php

namespace Meioa\Table\Parse;

use Meioa\Tools\Db;

/**
 * 数据库表字段、表主键解析
 */
class TableParse
{
    private $_db;
    public function __construct($config)
    {

        $this->_db = new Db($config);
    }

    /**
     * @param $database string 数据库名称
     * @return array[]
     */
    public function run($database){

        $field = '*';
        $sql = "SELECT ".$field." FROM INFORMATION_SCHEMA.COLUMNS where TABLE_SCHEMA = :database";
        $columnsList = $this->_db->query($sql,['database'=>$database]);
        $tableColumns = [];
        $tablePks = [];
        foreach ($columnsList as $columnItem){
            $table = $columnItem['TABLE_NAME'];
            if(!isset($tableColumns[$table])){
                $tableColumns[$table] = [];
            }
            if($columnItem['COLUMN_KEY'] == 'PRI' && !isset($tablePks[$table])){
                $tablePks[$table] = $columnItem['COLUMN_NAME'];
            }
            $tmp = $this->_getShowColumn($columnItem);
            array_push($tableColumns[$table],$tmp);
        }

        foreach (array_keys($tableColumns) as $table ){
            if(empty($tablePks[$table])){
                $tablePks[$table] = $this->_getTablePk($table);
            }
        }

        foreach ($tableColumns as $table =>$columns){
            foreach ($columns as $key =>$column){
                if($column['COLUMN_NAME'] == $tablePks[$table]){
                    $tableColumns[$table][$key]['IS_PRI'] = true;
                }else{
                    $tableColumns[$table][$key]['IS_PRI'] = false;
                }
            }
        }
        return ['columns'=>$tableColumns,'pks'=>$tablePks];
    }

    private function _getTablePk($table){
        $sql = "SHOW INDEX FROM `".$table."` WHERE Key_name =:keyname";
        $res = $this->_db->query($sql,['keyname'=>"PRIMARY"]);
        //echo json_encode($res);die;
        if(!is_array($res) || count($res)<1){
            return '';
        }
        $tablePk = '';
        foreach ($res as $item){
            if($item['Seq_in_index'] == 1){
                $tablePk = $item['Column_name'];
                break;
            }
        }
        return $tablePk;
    }


    private function _getColumnOptionArrs($columnType,$dataType){

        preg_match("/".$dataType."\((.*)\)/", $columnType,$enumStr);
        if(isset($enumStr[1])){
            $Arr = explode(',',$enumStr[1]);
            $list = [];
            foreach($Arr as $item){
                $val = trim($item,'\'');
                $Tmp = [];
                $Tmp['value'] = $val;
                $Tmp['label'] = $val;
                array_push($list,$Tmp);
            }
            return $list;
        }

        return false;
    }
    private function _getShowColumn($columnItem){
        $columnFieldArr = ['COLUMN_NAME','COLUMN_COMMENT','DATA_TYPE'];
        $columnTmp = [];
        foreach ($columnFieldArr as $columnField)
        {
            if(isset($columnItem[$columnField])){
                $columnTmp[$columnField] = $columnItem[$columnField];
            }
        }


        if($columnItem['DATA_TYPE'] == 'enum' ){

            $columnTmp['ENUM_LIST'] = $this->_getColumnOptionArrs($columnItem['COLUMN_TYPE'],$columnItem['DATA_TYPE']);
        }

        if($columnItem['DATA_TYPE'] == 'set' ){

            $columnTmp['SET_LIST'] = $this->_getColumnOptionArrs($columnItem['COLUMN_TYPE'],$columnItem['DATA_TYPE']);
        }
        return $columnTmp;
    }
}
