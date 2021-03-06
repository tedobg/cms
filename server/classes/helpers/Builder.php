<?php
/**
 * Helpers\Builder
 * 
 * A simple MySql query builder.
 * 
 * @version 1.0
 * @author Teodor Klissarov
 */
namespace Helpers;

class Builder {
    
    /**
     * The instance of the f3 database object
     * 
     * @var object
     */
    private $_db;
    
    /**
     * The from part of the query is stored here.
     * 
     * @var string
     */
    private $_from;
    
    /**
     * The select part of the query is stored here.
     * 
     * @var string
     */
    private $_select;
    
    /**
     * The join part of the query is stored here.
     * 
     * @var string
     */
    private $_join;
    
    /**
     * The where part of the query is stored here.
     * 
     * @var string
     */
    private $_where;
    
    /**
     * The order part of the query is stored here.
     * 
     * @var string
     */
    private $_order;
    
    /**
     * The limit part of the query is stored here.
     * 
     * @var string
     */
    private $_limit;
    
    /**
     * Query arguments (used to replace wildcards "?" throughout the query). 
     * Sorted and grouped in subarrays by function name.
     * 
     * @var array
     */
    private $_args;
    
    /**
     * The priority of functions when parsing the arguments
     * 
     * @var array
     */
    private $_args_priority = array('insert','update','join','where','order');
    
    /**
     * 1 indexed array of the arguments that is used in the final execution of 
     * the query.
     * 
     * @var array
     */
    private $_args_parsed;
    
    /**
     * The last executed query (wildcards are not replaced)
     * 
     * @var string
     */
    private $_last_query;
    
    /**
     * The result of the query
     * 
     * @var array
     */
    private $_result;
    
    /**
     * __construct
     * 
     * class constructor
     * @param object instance of the f3 db object
     */
    public function __construct($db)
    {
        $this->_db = $db;
    }
    
    /**
     * select function 
     * 
     * Takes one argument array or string and constructs the select sql
     * 
     * @param array|string
     * @return \Helpers\Builder
     */
    public function select($fields)
    {
        
        //If we have an array we need to implode it
        if(is_array($fields))
            $fields = implode(', ', $fields);
        
        $this->_select = "SELECT " . $fields;
        
        //returning $this to maintain chanability
        return $this;
    }
    
    /**
     * from function
     * 
     * Generates the from part of the query.
     * 
     * @param string which table to select from
     * @param string|null supply this if you want your table to be regarded with
     * an alias (ex. table1 as t1)
     * 
     * @return \Helpers\Builder
     */
    public function from($table, $as = NULL)
    {
        $this->_from = "FROM " . $table;
        
        if($as)
            $this->_from .= " AS ". $as;
        
        return $this;
    }
    
    /**
     * join function
     * 
     * creates a table join
     * 
     * @param string the joining table
     * @param string the joining condition
     * @param string the joining table alias if applicable
     * @param string join query arguments for wildcards
     * @param string type of the joihn
     * @return \Helpers\Builder
     */
    public function join($table, $on, $as = NULL, $args = array(), $type = "LEFT")
    {
        /**
         * applying arguments to the _args class variable
         * @see _add_args()
         */
        $this->_add_args($args, __FUNCTION__);
        
        //If the type is something we don't expect default it to LEFT
        $type = strtoupper($type);
        if(!in_array($type, array("LEFT","RIGHT","INNER")))
            $type = "LEFT";
        
            $this->_join .= (($this->_join) ? " " : NULL).$type . " JOIN " . $table;
        
        if($as)
            $this->_join .= " AS ". $as;
        
        $this->_join .= " ON " . $on;
        
        return $this;
    }
    
    /**
     * where function
     * 
     * @param string|array if a string is passed it is passed to the f3 db object
     * withouth further editing, otherwise a special where parser is applied 
     * @see _parse_where
     * 
     * @param array|null if the first argument is a string with wildcards this argument
     * needs to be supplied
     * 
     * @return \Helpers\Builder
     */
    public function where($where, $args = array())
    {
        
        if(!is_array($where))
        {
            //We have a string, adding the arguments and we are done
            $this->_add_args($args, __FUNCTION__);
            $this->_where = "WHERE " . $where;
        }
        else
        {
            //We go down the recursion hole
            $this->_where = $this->_parse_where($where);
            
            //In the event of incorrect input _parse_where may return empty result
            if($this->_where)
                $this->_where = "WHERE ".$this->_where;
        }
        
        return $this;
    }
    
    /**
     * order function
     * 
     * Nothing special here. Creates order part of the query
     * 
     * @param string by which field are we ordering
     * @param string asc|desc
     * @return \Helpers\Builder
     */
    public function order($by, $dir = "ASC")
    {
        //Let's see if $by is a name of a field or an SQL injection
        if(!preg_match('#^([a-zA-Z0-9_\.]*)$#', $by))
            return $this;
        
        //Is someone trying to order in "CHICKEN" direction ?
        if(!in_array($dir, array("ASC", "DESC")))
            $type = "ASC";
        
        $this->_order = "ORDER BY ? ".$dir;
        
        $this->_add_args($by, __FUNCTION__);
        return $this;
    }
    
    /**
     * limit function
     * 
     * Adds a limit to the query
     * 
     * @param type limit
     * @param type offset
     * @return \Helpers\Builder
     */
    public function limit($limit, $offset = 0)
    {
        $this->_limit = "LIMIT ";
        
        //If we have anything different than number, someone made a mistake
        if($offset && is_numeric($offset) && $offset > 0)   
            $this->_limit .= $offset.", ";
        
        $this->_limit .= is_numeric($limit) && $limit > 0 ? $limit : 1;
        
        return $this;
    }
    
    /**
     * Executes an insert query
     * 
     * @param string the table to insert into
     * @param array the column => value pairs to insert
     * @return null
     */
    public function insert($table, $args)
    {
        
        if(!$args)
            return NULL;
        $this->_last_query = "INSERT INTO ".$table;
        
        $columns = array();
        $fields  = array();
        $this->_add_args($args, __FUNCTION__);
        $this->_parse_args();
        
        foreach($args as $key => $val)
        {
            $columns[] = $key;
            $fields[] = '?';
        }
        
        $columns = ' (' . implode(',', $columns) . ') ';
        $fields = ' (' . implode(',', $fields) . ') ';
        
        $this->_last_query .= $columns . "VALUES" . $fields;
        $this->_db->exec($this->_last_query, $this->_args_parsed);
        
        $this->clear();
        
        return TRUE;
    }
    
    /**
     * This function will execute an update query
     * 
     * @param string
     * @param array
     * @return null
     */
    public function update($table, $args)
    {
        if(!$args)
            return NULL;
        
        $this->_last_query = "UPDATE ".$table." SET ";
        
        $set = array();
        foreach ($args as $key => $val)
            $set[] = $key." = ?";
        
        $this->_last_query .= implode(', ', $set);
        
        if($this->_where)
            $this->_last_query .= " ".$this->_where;
        
        $this->_add_args($args, __FUNCTION__);
        $this->_parse_args();
        
        $this->_db->exec($this->_last_query, $this->_args_parsed);
        $this->clear();
        
        return TRUE;
    }
    
    public function delete($table)
    {
        if(!$this->_where)
            return NULL;
        
        $this->_last_query = "DELETE FROM ".$table." ".$this->_where;
        $this->_parse_args();
        
        $this->_db->exec($this->_last_query, $this->_args_parsed);
        $this->clear();
        
        return TRUE;
    }
    
    /**
     * run function
     * 
     * Parses arguments. Constructs the query and passes it to the underlying 
     * f3 db object
     * 
     * @return \Helpers\Builder
     */
    public function run()
    {
        $this->_parse_args();
        
        $this->_last_query = $this->_select. " " .
                             $this->_from. " " .
                             $this->_join. " " .
                             $this->_where. " " .
                             $this->_order. " " .
                             $this->_limit;
        
        $this->_result = $this->_db->exec($this->_last_query, $this->_args_parsed);
        
        //We clear all query parts and arguments for the next query
        $this->clear();
        
        return $this;
    }
    
    /**
     * result function
     * 
     * What do you think this does ?
     * 
     * @return type
     */
    public function result()
    {
        return $this->_result;
    }
    
    /**
     * This is a helper function to construct trivial queries. Well trivial in this
     * system at least. Abstraction layer over the Builder.
     * 
     * @param type $table
     * @param type $locale
     * @return \Helper\Builder
     */
    public function default_select($table, $locale)
    {
        
        return $this->select($table."_lang.*, ".$table.".*")
                    ->from($table)
                    ->join($table . '_lang',
                           $table.".id = " . $table . "_lang.id_ AND " . $table . "_lang.locale = ?",
                           NULL,
                           $locale);
    }
    
    /**
     * This is another abstraction over the builder specifically made to work with 
     * @see default_select. It takes a specially constructed array and constructs 
     * where, order and limit query parts. After that it runs the query
     * 
     * @param array the array used for query construction
     * @return array
     */
    public function default_filters($data)
    {
        //The filter subarray of the data array will contain where filters
        if(isset($data['filter']))
         {
             //AngularJS calls encode each multidimesional array as json before 
             //sending it, so we need to check if filter is a string
             
             if(!is_array($data['filter']))
                 $data['filter'] = json_decode(urldecode ($data['filter']), TRUE);
             
             //If we get an empty result, there is some sort of faulty request
             if($data['filter'])
             {
                //If we are trying to search by id, we are going to get error
                //about ambiguous field, because it exists in both the table
                //and language table, thus renaming it to id_
                //This helps retain user friendly input (We don't want to make
                //the user sort by t1.id)
                foreach($data['filter'] as $key => $value)
                {
                    $new_key = preg_replace('#^!{0,1}id($|>|<|!|%)#','id_$1', $key);
                    unset($data['filter'][$key]);

                    $data['filter'][$new_key] = $value;
                }

                $this->where($data['filter']);
             }
         }
         
         //Setting order by
         if(isset($data['order'][0]))
         {
             $dir = isset($data['order'][1]) ? $data['order'][1] : 'ASC';
             $this->order($data['order'][0],$dir);
         }
         
         //Settign limit
         if(isset($data['limit'][0]))
         {
             $offset = isset($data['limit'][1]) ? $data['limit'][1] : 0;
             $this->limit($data['limit'][0], $offset);
         }
         
         //Running query and returning result
         return $this->run()->result();
    }
    
    /**
     * This function creates a 'default' insert in the table set as the first 
     * parameter. By default insert I mean that it filters the data to allowed
     * fields only and inserts in both the main and language tables. Requires 
     * alias field in the table. 
     * 
     * @param string the name of the table in which is being inserted
     * @param array the data to be inserted
     * @param array allowed fields in the main table
     * @param array allowed fields in the language table
     * @param string the current locale
     * @return array the message that the operation got
     */
    public function default_insert($table, $data, $allowed, $allowed_lang, $locale)
    {
        //If we don't have an alias sent, this is invalid input
        if(!isset($data['alias']))
            return array('success' => 0, 'msg' => 'INP_INV');
        
        //Does the entry exist?
        $check = $this->select('id')
                      ->from($table)
                      ->where(array('alias' => $data['alias']))
                      ->run()->result();
        //Yes? Goodbye.
        if($check)
            return array('success' => 0, 'msg' => 'E_EXISTS');
        
        //Prepping the data for insertion
        $ins_data = $this->prep_data($data, $allowed);
        
        //Inserting the data in the page table
        $insert = $this->insert($table, $ins_data);

        if($insert)
        {
            //Prepping the language data for insertion
            $ins_data_lang = array(
                'locale' => $locale,
                'id_' => $this->_db->lastInsertId()
            );

            $ins_data_lang = array_merge($ins_data_lang, $this->prep_data($data, $allowed_lang));
            
            //Inserting the data in the page language table
            $insert = $this->insert($table."_lang", $ins_data_lang);
        }
        
        //There was some error?
        if(!$insert)
            return array('success' => 0, 'msg' => 'DB_FAIL');
        
        //Everything went better than expected.
        return array('success' => 1, 'msg' => 'ACT_OK');
    }
    
    /**
     * This function creates a 'default' update in the table set as the first 
     * parameter. By default update I mean that it filters the data to allowed
     * fields only and updates both the main and language tables. Requires 
     * alias field in the table. 
     * 
     * @param string the name of the table in which is being inserted
     * @param string|int the id or alias of the entry being updated
     * @param array the data to be inserted
     * @param array allowed fields in the main table
     * @param array allowed fields in the language table
     * @param string the current locale
     * @return array the message that the operation got
     */
    public function default_update($table, $id, $data , $allowed, $allowed_lang, $locale)
    {
        //We take the alias from the id parameter if it isn't numeric. Otherwise we need it
        //through the POST data
        $data['alias'] = is_numeric($id) ? (isset($data['alias']) ? $data['alias'] : NULL) : $id;
        if(!$data['alias'])
            //No alias? Invalid input.
            return array('success' => 0, 'msg' => 'INP_INV');
        
        //Setting the update key
        $key = 'id';
        if(!is_numeric($id))
            $key = 'alias';
        
        //Does the page exist?
        $check = $this->select('id')
                      ->from($table)
                      ->where(array($key => $id))
                      ->run()->result();
        
        //No? Sayoonara.
        if(!$check)
            return array('success' => 0, 'msg' => 'E_FOUND');
        
        //Prepping the data for update
        $upd_data = $this->prep_data($data, $allowed);

        //Updating the page table
        $update = $this->where(array($key => $id))
                       ->update($table, $upd_data);
        
        if($update)
        {
            //Is there an entry for this locale ?
            $check_lang = $this->select('*')
                               ->from($table."_lang")
                               ->where(array('id_' => $check[0]['id'], 'locale' => $locale))
                               ->run()->result();
            
            //Prepping the language data for insertion
            $upd_data_lang = $this->prep_data($data, $allowed_lang);
            
            //Yes ! There is already an entry for this locale. Updating ...
            if($check_lang)
                $update = $this->where(array('id_' => $check[0]['id'], 'locale' => $locale))
                               ->update($table."_lang", $upd_data_lang);
            else
            {
                //Nop... Inserting a new entry.
                $upd_data_lang['id_'] = $check[0]['id'];
                $upd_data_lang['locale'] = $locale;
                
                $update = $this->insert($table."_lang", $upd_data_lang);
            }
        }
        
        //The hell? Something went wrong.
        if(!$update)
            return array('success' => 0, 'msg' => 'DB_FAIL');
        
        //Nice!
        return array('success' => 1, 'msg' => 'ACT_OK');
    }
    
    /**
     * This function takes a data array and a template with allowed columns. The 
     * function returns an array of the type allowed_column => value.
     * 
     * @param array the data to be prepped for insert/update
     * @param array the columns that are allowed to be modified
     * @return array
     */
    public function prep_data($data, $allowed)
    {
        $prepped = array();
        foreach($allowed as $col)
            $prepped[$col] = isset($data[$col]) ? (is_array($data[$col]) 
                                                    ? json_encode($data[$col]) 
                                                    : $data[$col]) 
                                                 : '';
        return $prepped;
    }
    
    /**
     * _add_args function
     * 
     * Adds arguments to the _args class variable grouped by function name.
     * 
     * @param array|string The arguments that need to be added
     * @param string function name, used for grouping the arguments by function name
     */
    private function _add_args($args, $func)
    {
        if(!is_array($args))
             $this->_args[$func][] = $args;
        else
            foreach($args as $arg)
                $this->_args[$func][] = $arg;
    }
    
    /**
     * _parse_args function 
     * 
     * Parses _args class variable into 1 indexed array
     */
    private function _parse_args()
    {
 
        foreach($this->_args_priority as $func)
            if(isset($this->_args[$func]))
                foreach($this->_args[$func] as $arg)
                    $this->_args_parsed[count($this->_args_parsed) + 1] = $arg;
    }
    
    /**
     * _parse_where function
     * 
     * This is a recursive array parser. It recognises the following keys:
     * 
     * key => val        equals to (AND) key = val <br/>
     * key>|<|! => val   equals to (AND) key >|<|!= val <br/>
     * key% => val       equals tp (AND) key LIKE %val% <br/>
     * !key => val       equals to (OR)  key = val <br/>
     * key-in => array() equals to       key IN (array()) <br/>
     * key => array()    equals to (AND) (recusrsion on array()) <br/>
     * !key => array()   equals to (OR)  (recusrsion on array()) <br/>
     * 
     * <b> example: </b> <br/>
     * 
     * array(<br/>
     *      key1>  => val1<br/>
     *      !key2% => val2<br/>
     *      !sub1  => array(<br/>
     *          key3!  => val3<br/>
     *          key4< => val4<br/>
     *      )<br/>
     * )<br/>
     * 
     * <b> equals to: </b> <br/>
     * 
     * WHERE key1 > val1 OR key2 LIKE val2 OR (key3 != val3 AND key4 = val4)
     * 
     * @param array the where data
     * @return string the parsed where string
     */
    private function _parse_where($data)
    {
        //This as the array that will contain all conditions
        $conds = array();
        
        //Cycling through the data
        foreach($data as $key => $val)
        {
            //The index of the current condition
            $index = count($conds);
            
            //If this is an array we have to process it recursively
            if(is_array($val))
            {
                //We evaluate to see if it is an "IN" request
                if(preg_match('#^([a-zA-Z0-9_\.]*)-in$#', $key, $matches))
                {
                    $conds[$index] = $matches[1] . ' IN (' . implode(',', $val) . ')';
                }
                else
                    //Recursion
                    $conds[$index] = "(" . $this->_parse_where ($val) . ')';
                
                //Are there previous conditions ?
                if($index > 0)
                    if(preg_match('#^!.*#', $key))
                        //If the key starts with a '!' then we need to add an OR
                        $conds[$index] = "OR " . $conds[$index];
                    else
                        //Otherwise - AND
                        $conds[$index] = "AND " . $conds[$index];     
            }
            else
            {
                //The values are automatically sanitized, but let's check the 
                //key. The regex matches: table.val OR (!)table.val(<|>|!|%)
                if(!preg_match('#^(([a-zA-Z0-9_\.]*)|((![a-zA-Z0-9_\.]*|[a-zA-Z0-9_\.]*)(>|<|!|%)))$#', $key))
                    continue;
                    
                $conds[$index] = '';

                if(preg_match('#^!.*#', $key))
                {     
                    //Are there previous conditions ?
                    if($index > 0)
                        //If the key starts with a '!' then we need to add an OR
                        $conds[$index] .= "OR ";
                    
                    $key = substr($key,1);
                }
                else
                    if($index > 0)
                        //Otherwise - AND
                        $conds[$index] .= "AND ";   
                 
                
                //Next we check for an expression
                if(preg_match('#^(![a-zA-Z0-9_\.]*|[a-zA-Z0-9_\.]*)(>|<|!|%)$#',$key,$matches))
                {      
                     $key = str_replace($matches[2], '', $key);
                     if($matches[2] == "%") 
                     {
                         $conds[$index] .= $key. " LIKE ?";
                         $val = "%".$val."%";
                     }
                     elseif($matches[2] == "!")
                         $conds[$index] .= $key. " != ?";
                     else
                         $conds[$index] .= $key . " ".$matches[2]." ?";
                }    
                else
                    $conds[$index] .= $key . " = ?";
                
                $this->_add_args($val, 'where');
            }
        }
        
        return implode(" ",$conds);
    }
    
    /**
     * clear function
     * 
     * clears all parts of the query as well as query arguments.
     * 
     */
    public function clear()
    {
        $this->_select = NULL;
        $this->_from = NULL;
        $this->_join = NULL;
        $this->_where = NULL;
        $this->_order = NULL;
        $this->_limit = NULL;
        $this->_args = array();
        $this->_args_parsed = array();
    }
}
