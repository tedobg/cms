<?php
/**
 * Api\Module
 * 
 * This class handles API calls that require display and/or manipulation of a
 * module's settings or data.
 * 
 * @version 1.0
 * @author Teodor Klissarov
 */

namespace Api;

class Module {
    
    /**
     * The name of the table containing settings for modules
     * @var string
     */
    private $mod_t = "modules";
    
    /**
     * The modules' namespace as a string
     * @var string
     */
    private $mod_ns = "\\Api\\Modules\\";
    
    /**
     * If a module class exists in the appropriate namespace, it will be 
     * instanced here
     * 
     * @var type 
     */
    private $mod_ch;
    
    /**
     * The columns of the main table that are editable through the API
     * 
     * @var type 
     */
    private $columns = array("bundle", "alias", "data_tables");
    
    /**
     * The columns of the language table that are editable through the API
     * @var type 
     */
    private $lang_columns = array("title", "description");
    
    /**
     * get function 
     * 
     * This function is an entry point for get requests at the module node. 
     * It routes them to the appropriate handler.
     * 
     * @param object the f3 object instance
     */
    public function get($f3)
    {
        $result = array();
        
        if(!$f3->get("PARAMS.id"))
            //If no module id is specified fetch a list of modules (settings only, 
            //no data)
            $result['settings'] = $this->_get_settings_list($f3);
        else
        {
           //We have a module id, so lets get the settings first
           $result['settings'] = $this->_get_settings_single($f3);
           
           //If we have a result, then the module exists. If the table param is
           //specified then we need to retrieve the data.
           if($result['settings'] && $f3->get('PARAMS.table'))
           {
               //Ofcourse we have to check if the specified table belongs to the module 
               if(!in_array($f3->get('PARAMS.table'), json_decode($result['settings'][0]['data_tables'])))
                    //If this table does not belong to this module fire a table not found error
                    $f3->get('messages')->msg('T_FOUND');
               else
               {
                    //We need to fetch data
                    
                    //This will be the class name (including namespace) of the module in question
                    $class = $this->mod_ns .  ucfirst($result['settings'][0]['bundle']) . '\\' 
                             . ucfirst($result['settings'][0]['alias']);
                    if(class_exists($class))
                    {
                        //If the class exists we will create an instance of it 
                        //for later use
                        $this->mod_ch = new $class();
                        
                        //If a method named get exists in the child, it will be 
                        //used to fetch table data
                        if(method_exists($class, 'get'))
                            $result['mdata'] = $this->mod_ch->get($f3);
                    }
                    if(!class_exists($class) || !method_exists($class, 'get'))
                        //Otherwile we fallback to default
                        $result['mdata'] = $this->_get_data($f3);
               }
           }  
        }
        
        //If any errors occured sent them as response
        if($f3->get('messages')->errcount())
            $f3->get('utils')->reserrors($f3->get('messages')->clear());
        else
            //Otherwise send the data
            $f3->get('utils')->respond($result);  
    }
    
    public function post($f3)
    {
         if(!$f3->get("PARAMS.id"))
            //If no module id is specified then we will be inserting a new module
            $this->_post_settings($f3);
         else
         {
             //Let's get the module's settigns
             $settings = $this->_get_settings_single($f3);
             
             //Ofcourse we have to check if the specified table belongs to the module 
             if(!in_array($f3->get('PARAMS.table'), json_decode($settings[0]['data_tables'])))
             {
                 //If this table does not belong to this module fire a table not found error
                 $f3->get('messages')->msg('T_FOUND');
                 $f3->get('utils')->reserrors($f3->get('messages')->clear());
                 return NULL;
             }
             
             //This will be the class name (including namespace) of the module in question
             $class = $this->mod_ns . ucfirst($settings[0]['bundle']) . '\\' 
                      . ucfirst($settings[0]['alias']);
             
             if(class_exists($class))
             {
                 //If the class exists we will create an instance of it 
                 //for later use
                 $this->mod_ch = new $class();

                 //If a method named get exists in the child, it will be 
                 //used to fetch table data
                 if(method_exists($class, 'post'))
                     $result['mdata'] = $this->mod_ch->post($f3);
             }
             if(!class_exists($class) || !method_exists($class, 'post'))
                 //Otherwile we fallback to default
                 $result['mdata'] = $this->_post_data($f3);
         }
    }
    
    public function put($f3)
    {
        if(!$f3->get("PARAMS.table"))
            //If no module id is specified then we will be update a module
            $this->_put_settings($f3);
         else
         {
             //Let's get the module's settigns
             $settings = $this->_get_settings_single($f3);
             
             //Ofcourse we have to check if the specified table belongs to the module 
             if(!in_array($f3->get('PARAMS.table'), json_decode($settings[0]['data_tables'])))
             {
                 //If this table does not belong to this module fire a table not found error
                 $f3->get('messages')->msg('T_FOUND');
                 $f3->get('utils')->reserrors($f3->get('messages')->clear());
                 return NULL;
             }
             
             //This will be the class name (including namespace) of the module in question
             $class = $this->mod_ns . ucfirst($settings[0]['bundle']) . '\\' 
                      . ucfirst($settings[0]['alias']);
             
             if(class_exists($class))
             {
                 //If the class exists we will create an instance of it 
                 //for later use
                 $this->mod_ch = new $class();

                 //If a method named get exists in the child, it will be 
                 //used to fetch table data
                 if(method_exists($class, 'put'))
                     $result['mdata'] = $this->mod_ch->put($f3);
             }
             if(!class_exists($class) || !method_exists($class, 'put'))
                 //Otherwile we fallback to default
                 $result['mdata'] = $this->_put_data($f3);
         }
    }
    
    public function delete($f3)
    {
         if(!$f3->get("PARAMS.table"))
            //If no module id is specified then we will be delete a module
            $this->_delete_settings($f3);
         else
         {
             //Let's get the module's settigns
             $settings = $this->_get_settings_single($f3);
             
             //Ofcourse we have to check if the specified table belongs to the module 
             if(!in_array($f3->get('PARAMS.table'), json_decode($settings[0]['data_tables'])))
             {
                 //If this table does not belong to this module fire a table not found error
                 $f3->get('messages')->msg('T_FOUND');
                 $f3->get('utils')->reserrors($f3->get('messages')->clear());
                 return NULL;
             }
             
             //This will be the class name (including namespace) of the module in question
             $class = $this->mod_ns . ucfirst($settings[0]['bundle']) . '\\' 
                      . ucfirst($settings[0]['alias']);
             
             if(class_exists($class))
             {
                 //If the class exists we will create an instance of it 
                 //for later use
                 $this->mod_ch = new $class();

                 //If a method named get exists in the child, it will be 
                 //used to fetch table data
                 if(method_exists($class, 'delete'))
                     $result['mdata'] = $this->mod_ch->delete($f3);
             }
             if(!class_exists($class) || !method_exists($class, 'delete'))
                 //Otherwile we fallback to default
                 $result['mdata'] = $this->_delete_data($f3);
         }
    }
    
    private function _get_settings_list($f3)
    {
        
    }
    
    /**
     * _get_settings_single function
     * 
     * This function is used to retrieve the settings of a single module from the
     * module table
     * 
     * @param object f3 instance
     * @return array
     */
    private function _get_settings_single($f3)
    {
        $mod_id = $f3->get('PARAMS.id');
        
        $sql = $f3->get('dbb')->default_select($this->mod_t, $f3->get('locale'));
        
         if(is_numeric($mod_id))
            //The first parameter is an id
            $sql->where(array($this->mod_t.'.id' => $mod_id));
        else
        {
             //Here we use the alias.bundle syntax, because there might be modules
             //in different bundles with the same alias
             $mod = explode('.', $mod_id);
             if(!isset($mod[1]))
             {
                 $sql->clear();
                 $f3->get('messages')->msg('M_FOUND');
                 
                 return NULL;
             }
             
             $sql->where(array($this->mod_t.'.bundle' => 0, $this->mod_t.'.alias' => $mod[1]));
        }
           
        
        $result = $sql->run()->result(); 
        
        //Module not found
        if(!$result)
            $f3->get('messages')->msg('M_FOUND');
        
        return $result;
    }
    
    /**
     * _get_data function 
     * 
     * This is the default data retriever entry point for modules. Can be overrided
     * by creating a module file in the mod_ns namespace with a function get
     * 
     * @param object f3 instance
     * @return array
     */
    private function _get_data($f3)
    {
       //If d_id parameter is set we return a single data entry
       //otherwise we return a list
       return (!$f3->get("PARAMS.d_id")) ? $this->_get_data_list($f3) :
                                           $this->_get_data_single($f3);    
    }
    
    /**
     * _get_data_list
     * 
     * Returns a list of data entries by using \Helper\Builder::default_select() 
     * and \Helper\Builder::default_filters()
     * 
     * @see \Helper\Builder::default_select(), \Helper\Builder::default_filters()
     * @param object f3 instance
     * @return array
     */
    private function _get_data_list($f3)
    {
        $result = $f3->get('dbb')->default_select($f3->get('PARAMS.table'), $f3->get('locale'))
                                 ->default_filters($f3->get('GET'));

        if(!$result)
            $f3->get('messages')->msg('D_NONE');
        
        if($this->mod_ch && isset($this->mod_ch->fk_relations))
            return $this->_load_relations($f3, $result);
        
        return $result;
    }
    
    /**
     * _get_data_single
     * 
     * Returns a single data entry by using \Helper\Builder::default_select() 
     * 
     * @see \Helper\Builder::default_select()
     * @param object f3 instance
     * @return array
     */
    private function _get_data_single($f3)
    {
        $id = $f3->get('PARAMS.d_id');
        $data_table = $f3->get('PARAMS.table');
        
        //Getting the requested entry
        $result = $f3->get('dbb')->default_select($data_table, $f3->get('locale'));
                                
        
        if(is_numeric($id))
            $result->where(array($data_table.'.id' => $id));
        else
            $result->where(array($data_table.'.alias' => $id));
        
        $result = $result->run()->result();   
        if(!$result)
        {
            //If the result is empty no entry was found. Fire an error.
            $f3->get('messages')->msg('D_FOUND');
            return NULL;
        }
        
        //If an active child object exists and has a relationship array set
        //We need to fetch related data
        if($this->mod_ch && isset($this->mod_ch->fk_relations))
            return $this->_load_relations($f3, $result);
        
        return $result;
    }
    
    private function _load_relations($f3, $result)
    {
        $data_table = $f3->get('PARAMS.table');
        
        //$table is the current table $rel is an array with the relations on
        //this table
        foreach($this->mod_ch->fk_relations as $table => $rel)
        {
            //rel_one = true means we need to get 'one on one' relations
            if($f3->get('GET.rel_one') && $table == $data_table)
                //column shows which field is the foreign key and rel_tb
                //is the foreign table
                foreach($rel as $column => $rel_tb)
                {
                    //We fetch the data from the foreign table
                    $rel_data = $f3->get('dbb')->default_select($rel_tb, $f3->get('locale'));
                    
                    //Do we have one or many items in the result?
                    if(count($result) == 1)
                        $rel_data->where(array($rel_tb.".id" => $result[0][$column]));
                    else
                    {
                        $rel_data->where(array($rel_tb.".id-in" => array_map(function($v) use ($column){
                            return $v[$column]; 
                        },$result)));
                    }
                    
                    $rel_data = $rel_data->run()->result();
                     
                   //Set it in the appropriate field of the result
                   foreach($result as $key => &$val)
                   {
                       $filter = array_filter($rel_data, function($v) use ($val, $column){
                            return $v['id_'] == $val[$column];
                       });
                       
                       $val[$column] = $filter[0];
                   }
                }
            //rel_many = true means we have to get 'one to many' relations 
            if($table != $data_table && $f3->get('GET.rel_many'))
            {
                //we need to see if the current $rel contains mention of our 
                //data table
                $rel_column = array_search($data_table, $rel);
                //If rel_info is NULL no match was found
                if($rel_column)
                {
                    //Fetching data
                    $rel_data = $f3->get('dbb')->default_select($table, $f3->get('locale'));           
                    //Do we have one or many items in the result?
                    if(count($result) == 1)
                        $rel_data->where(array($table.".".$rel_column => $result[0]['id_']));
                    else
                    {
                        $rel_data->where(array($table.".".$rel_column."-in" => array_map(function($v) {
                            return $v['id_']; 
                        },$result)));
                    }
                    
                    $rel_data = $rel_data->run()->result();
                    //Setting the $table field of the result to rel data
                    foreach($result as $key => &$val)
                    {
                       $filter = array_filter($rel_data, function($v) use ($val, $rel_column){
                            return $v[$rel_column] == $val['id_'];
                       });
                       
                       $val[$table] = $filter;
                    }
                }
            }
        }

        return $result;
    }
    
    private function _post_settings($f3)
    {
        $data = $f3->get('POST');
        $result = $f3->get('dbb')->default_insert($this->mod_t, 
                                                  $data, $this->columns, 
                                                  $this->lang_columns, 
                                                  $f3->get('locale'));
        
        
        $f3->get('messages')->msg($result['msg']);
        if($result['success'])
            $f3->get('utils')->respond($f3->get('messages')->clear());
        else
            $f3->get('utils')->reserrors($f3->get('messages')->clear());
    }
    
    private function _post_data($f3)
    {
        $data = $f3->get('GET.main');
        $data_lang = $f3->get('GET.lang');
        $table = $f3->get('PARAMS.table');
        
        if(isset($this->mod_ch->allowed))
            $data = $f3->get('dbb')->prep_data($data, $this->mod_ch->allowed);
        else 
            $data = $f3->get('utils')->sanitize($data);
        
        if(isset($this->mod_ch->allowed_lang))
            $data_lang = $f3->get('dbb')->prep_data($data_lang, $this->mod_ch->allowed_lang);
        else
            $data_lang = $f3->get('utils')->sanitize($data_lang);
        
        $result = $f3->get('dbb')->insert($table,$data);
        
        if($result)
        {
            $data_lang['locale'] = $f3->get('locale');
            $data_lang['id_'] = $f3->get('db')->lastInsertId();
            $result = $f3->get('dbb')->insert($table."_lang", $data_lang);
        }
        
        if(!$result)
        {
            $f3->get('messages')->msg('DB_FAIL');
            $f3->get('utils')->reserrors($f3->get('messages')->clear());
            return NULL;
        }
        
        //TODO: ADD FILE UPLOAD
        
        $f3->get('messages')->msg('ACT_OK');
        $f3->get('utils')->respond($f3->get('messages')->clear());   
    }
    
    private function _put_settings($f3)
    {
        $data = $f3->get('POST');
        $id = $f3->get('PARAMS.id');
        
        $result = $f3->get('dbb')->default_update($this->mod_t, $id,
                                                  $data, $this->columns, 
                                                  $this->lang_columns, 
                                                  $f3->get('locale'));
        
        
        $f3->get('messages')->msg($result['msg']);
        if($result['success'])
            $f3->get('utils')->respond($f3->get('messages')->clear());
        else
            $f3->get('utils')->reserrors($f3->get('messages')->clear());
    }
    
    private function _put_data($f3)
    {
        $data = $f3->get('GET.main');
        $data_lang = $f3->get('GET.lang');
        $table = $f3->get('PARAMS.table');
        $id = $f3->get('PARAMS.d_id');
        
        if(isset($this->mod_ch->allowed))
            $data = $f3->get('dbb')->prep_data($data, $this->mod_ch->allowed);
        else 
            $data = $f3->get('utils')->sanitize($data);
        
        if(isset($this->mod_ch->allowed_lang))
            $data_lang = $f3->get('dbb')->prep_data($data_lang, $this->mod_ch->allowed_lang);
        else
            $data_lang = $f3->get('utils')->sanitize($data_lang);
        
        $result = $f3->get('dbb')->where(array('id' => $id))->update($table,$data);
        
        if($result)
        {
            $check = $f3->get('dbb')->select('*')
                                    ->from($table."_lang")
                                    ->where(array('locale' => $f3->get('locale'), 'id_' => $id))
                                    ->run()->result();
            
            if(!$check)
                $result = $f3->get('dbb')->insert($table."_lang", $data_lang);
            else
            {
                $data_lang['locale'] = $f3->get('locale');
                $data_lang['id_'] = $id;
                $result = $f3->get('dbb')->where(array('locale' => $f3->get('locale'), 'id_' => $id))
                                         ->update($table."_lang", $data_lang);
            }
        }
        
        if(!$result)
        {
            $f3->get('messages')->msg('DB_FAIL');
            $f3->get('utils')->reserrors($f3->get('messages')->clear());
            return NULL;
        }
        
        //TODO: ADD FILE UPLOAD
        
        $f3->get('messages')->msg('ACT_OK');
        $f3->get('utils')->respond($f3->get('messages')->clear());
    }
    
    private function _delete_settings($f3)
    {
        $id = $f3->get('PARAMS.id');
        
        $key = 'id';
        if(!is_numeric($id))
            $key = 'alias';
        
        //Tables set on foreign keys to cascade. No need to delete the language data
        //separately
        $delete = $f3->get('dbb')->where(array($key => $id))->delete($this->mod_t);
        
        //Huh?
        if(!$delete)
        {    
            $f3->get('messages')->msg('DB_FAIL');
            $f3->get('utils')->reserrors($f3->get('messages')->clear());
            return NULL;
        }
        
        //Ok. You deleted the module. Happy?
        $f3->get('messages')->msg('ACT_OK');
        $f3->get('utils')->respond($f3->get('messages')->clear());
    }
    
    private function _delete_data($f3)
    {
        $table = $f3->get('PARAMS.table');
        $id = $f3->get('PARAMS.d_id');
        
        $delete = $f3->get('dbb')->where(array('id' => $id))->delete($table);
 
        if(!$delete)
        {    
            $f3->get('messages')->msg('DB_FAIL');
            $f3->get('utils')->reserrors($f3->get('messages')->clear());
            return NULL;
        }
        
        $f3->get('messages')->msg('ACT_OK');
        $f3->get('utils')->respond($f3->get('messages')->clear());
    }
}

