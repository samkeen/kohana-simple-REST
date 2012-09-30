<?php
/**
 *
 */
class Util_Sql
{
    /**
     * @param string $table_name
     * @param array $fields i.e. array('name' => 'bob', ...)
     * @return string
     * @throws InvalidArgumentException
     */
    static function build_insert($table_name, array $fields)
    {
        if( ! $fields)
        {
            throw new InvalidArgumentException("Param: \$fields cannot be empty");
        }
        $fields_list = '`' . implode('`, `', array_keys($fields)) . '`';
        $field_placeholder_list = ':' . implode(', :', array_keys($fields));
        return "INSERT INTO `{$table_name}` ({$fields_list}) VALUES({$field_placeholder_list})";
    }

    /**
     * @param string $table_name
     * @param string $identifier_field
     * @param array $fields i.e. array('name' => 'bob', ...)
     * @return string
     * @throws InvalidArgumentException
     */
    static function build_update($table_name, $identifier_field, array $fields)
    {
        //UPDATE `ingredient_spellchanges` SET `new` = 'dashx' WHERE `id` = '1';
        if( ! $fields)
        {
            throw new InvalidArgumentException("Param: \$fields cannot be empty");
        }
        $fields_list = array();
        foreach(array_keys($fields) as $field_name)
        {
           $fields_list[] = "`{$field_name}` = :{$field_name}";
        }
        $fields_set_statements = "SET " . implode(', ', $fields_list);
        $where_statement = "WHERE `{$identifier_field}` = :{$identifier_field}";
        return "UPDATE `{$table_name}` {$fields_set_statements} {$where_statement}";
    }

    /**
     * @param string $table_name
     * @param string $identifier_field
     * @return string
     */
    static function build_delete($table_name, $identifier_field)
    {
        // DELETE FROM `ingredient_spellchanges` WHERE `id` = '2';
        $where_statement = "WHERE `{$identifier_field}` = :{$identifier_field}";
        return "DELETE FROM `{$table_name}` {$where_statement}";
    }

}