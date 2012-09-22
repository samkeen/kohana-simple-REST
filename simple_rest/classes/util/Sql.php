<?php
/**
 * Created by JetBrains PhpStorm.
 * User: sam
 * Date: 9/9/12
 * Time: 12:56 PM
 * To change this template use File | Settings | File Templates.
 */
class Util_Sql
{
    static function build_insert($table_name, array $fields)
    {
        if( ! $fields)
        {
            throw new InvalidArgumentException("Param fields cannot be empty");
        }
        $fields_list = '`' . implode('`, `', array_keys($fields)) . '`';
        $field_placeholder_list = ':' . implode(', :', array_keys($fields));
        return "INSERT INTO `{$table_name}` ({$fields_list}) VALUES({$field_placeholder_list})";
    }

}
