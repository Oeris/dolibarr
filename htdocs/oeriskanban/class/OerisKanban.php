<?php

namespace Oeris;


/**
 * Oeris Kanboard class
 *
 * @package Oeris
 * @author  Neil Orley
 */
class Kanboard
{
    /**
     * Client
     *
     * @access public
     * @var $client
     */
    public $client;
    
    /**
     *
     * @access public
     * @var $projectsList
     */
    public $projectsList = array();
    
    /**
     *
     * @access public
     * @var $tasksList
     */
    public $tasksList;
    
    /**
     *
     * @access public
     * @var $columnList
     */
    public $columnList;
    
    /**
     *
     * @access public
     * @var $usersList
     */
    public $usersList;
    
    /**
     *
     * @access private
     * @var timeElements
     */
    private $timeElementsWithFormat = array('last_modified' => "%F %T",
                                            'start_date' => "%F %T",
                                            'end_date' => "%F %T",
                                            'date_creation' => "%F %T",
                                            'date_modification' => "%F %T",
                                            'date_moved' => "%F %T",
                                            'date_due' => "%F");
    
    /**
     *
     * @access private
     * @var projectsHeaders
     */
    private $projectsHeaders = array('id','name','description','last_modified','start_date','end_date','priority_end');
    
    /**
     *
     * @access private
     * @var tasksHeaders
     */
    private $tasksHeaders = array('id','project_id','title','description','priority','date_modification','column_id','date_due','owner_id','creator_id');
    
     /**
     *
     * @access private
     * @var subTasksHeaders
     */
    private $subTasksHeaders = array('id','task_id','title','time_estimated','time_spent','user_id','date_due','status_name');

    /**
     * Constructor
     *
     * @access public
     */
    function __construct($Projects) {
        $this->name = "Kanboard";
        $this->projectsList = $Projects;
        return $this;
    }
    
    /**
     * Destructor
     *
     * @access public
     */
    function __destruct() {
    }
    
    
    /**
     *
     * @access private
     * @return $return
     */
    private function _kbAGetValueFromKey($array,$conditionkey='',$conditionvalue='',$searchkey='',$i=0)
    {
        global $langs;
        $return='';  
        $i++;
        
        if(!empty($array)) {
            foreach($array as $topkey=>$topvalue){
                if($array[$conditionkey] == $conditionvalue) {
                    $return = $array[$searchkey];
                    break;
                } else if(is_array($topvalue)){
                    $return = $this->_kbAGetValueFromKey($topvalue,$conditionkey,$conditionvalue,$searchkey,$i);
                    if(!empty($return)){ return $return; }
                } 
            }
        }                         
        
        return $return;    
    }
    
    
    /**
     *
     * @access private
     * @return $return
     */
    private function _kbArrayPopHeadersInHTML($filters = array())
    {
        global $langs;
        $return='';  
        
        //---- TABLE HEADER
        $return .= '         <thead>';
        //---- Displays headers using filters
        if(!empty($filters)) {
          $return .= '           <tr>';
          foreach($filters as $header){
              $return .= '             <td>'.$langs->trans($header).'</td>';
          }
          $return .= '           </tr>';
        }
        
        $return .= '         </thead>';
        //---- END TABLE HEADER
        
        return $return;    
    } 
    
    
    /**
     *
     * @access private
     * @return $return
     */
    private function _kbGetHTMLink($array)
    {
        global $langs;
        $return='';  
        
        //---- Add a link to the kanboard
        if(!empty($array)){
            if(is_array($array['url'])) {
                foreach($array['url'] as $urltype=>$urllink) {
                    switch ($urltype) {
                        case 'board':
                            $return .= '&nbsp;<a href="'.$array['url'][$urltype].'" target="_blank"><i class="fa fa-table" aria-hidden="true"></i></a>'; 
                            break;
                        case 'calendar':
                            $return .= '&nbsp;<a href="'.$array['url'][$urltype].'" target="_blank"><i class="fa fa-calendar" aria-hidden="true"></i></a>'; 
                            break;
                        case 'list':
                            $return .= '&nbsp;<a href="'.$array['url'][$urltype].'" target="_blank"><i class="fa fa-list" aria-hidden="true"></i></a>'; 
                            break;   
                    }
                }
            } else if(!empty($array['url'])) {
                $return .= '&nbsp;<a href="'.$array['url'].'" target="_blank"><i class="fa fa-eye" aria-hidden="true"></i></a>';
            }                                                  
        }   
        
        return $return;    
    } 
    
    
    
    /**
     *
     * @access private
     * @return $return
     */
    private function _kbArrayPopContentInHTML($array,$filters = array())
    {
        global $langs,$tasksList,$subTasksList,$usersList,$columnList;
        $return='';  
        
        //---- TABLE HEADER
        $return .= '         <tbody>';
        if(!empty($filters)) {
            foreach($array as $topkey=>$topvalue){
                if(is_array($topvalue) && is_numeric($topkey)){
                    //print 'topkey : '.$topkey.', ';
                    $return .= $this->_kbArrayPopContentInHTML($topvalue,$filters);
                }  
            }
            
            if(!is_array(reset($array))) {
                $return .= '           <tr>';
                foreach($filters as $filter){
                    if(!is_array($array[$filter])){
                                
                        //---- Displays ids as row headers
                        if($filter=='id'){
                            if(!isset($array[$filter])) { break; }              //---- Break if id is empty
                            $return .= '             <th scope="row">'.$langs->trans($array[$filter]);
                            $return .= $this->_kbGetHTMLink($array);
                            $return .= '</th>';
                        }
                        //---- Displays project name instead of id
                        else if($filter=='project_id'){
                            $project_name = $this->_kbAGetValueFromKey($this->projectsList,'id',$array[$filter],'name');
                            $return .= '             <td>'.$project_name;
                            $links['url'] = $this->_kbAGetValueFromKey($this->projectsList,'id',$array[$filter],'url');
                            $return .= $this->_kbGetHTMLink($links);
                            $return .= '</td>'; 
                        }
                        //---- Displays task name instead of id
                        else if($filter=='task_id'){
                            $task_name = $this->_kbAGetValueFromKey($tasksList,'id',$array[$filter],'title');
                            $return .= '             <td>'.$task_name;
                            $links['url'] = $this->_kbAGetValueFromKey($tasksList,'id',$array[$filter],'url');
                            $return .= $this->_kbGetHTMLink($tasksList);
                            $return .= '</td>'; 
                        }
                        //---- Displays column name instead of id
                        else if($filter=='column_id'){
                        
                            //---- getColumn foreach column_id
                            if( empty($columnList[$array[$filter]]) ) {
                                $columnList[$array[$filter]] = $this->client->getColumn($array[$filter]);
                            }
                            
                            $column_name = $this->_kbAGetValueFromKey($columnList[$array[$filter]],'id',$array[$filter],'title');
                            $return .= '             <td>'.$column_name.'</td>'; 
                        }
                        //---- Displays column name instead of id
                        else if($filter=='owner_id' || $filter=='creator_id' || $filter=='user_id'){
                            //---- getColumn foreach column_id
                            if(empty($array[$filter])) {
                                $usersList[$array[$filter]] = Array( 'id'=>0, "username"=> $langs->trans("Unassigned") );
                            }
                              
                            if( empty($usersList[$array[$filter]]) ) {
                                $usersList[$array[$filter]] = $this->client->getUser($array[$filter]);
                            }
                            //var_dump($usersList);
                            $user_name = $this->_kbAGetValueFromKey($usersList[$array[$filter]],'id',$array[$filter],'username');
                            $return .= '             <td '.(empty($array[$filter])? 'class="danger"' : '').'>'.$user_name.'</td>'; 
                        }
                        //---- Displays priority : Px
                        else if($filter=='priority' || $filter=='priority_default' || $filter=='priority_start' || $filter=='priority_end'){
                            $return .= '             <td>P'.$array[$filter].'</td>'; 
                        }
                        //---- Displays priority : Px
                        else if($filter=='time_estimated' || $filter=='time_spent'){
                            $return .= '             <td>'.$array[$filter].'h</td>'; 
                        }
                        //---- Displays timestamps as date
                        else if(is_numeric($array[$filter]) && array_key_exists($filter, $this->timeElementsWithFormat)) {
                            if($array[$filter]>0) {
                                $return .= '             <td>'.strftime($this->timeElementsWithFormat[$filter],$array[$filter]).'</td>';
                            } else {
                                $return .= '             <td>&nbsp;</td>';
                            }
                        }
                        //---- Displays default elements
                        else if(isset($array[$filter])) {
                            $return .= '             <td>'.$langs->trans($array[$filter]).'</td>';
                        } 
                        //---- Displays empty elements
                        else {
                            $return .= '             <td>&nbsp;</td>';
                        }
                    }
                }
                $return .= '           </tr>';
            }
        }                                                                             
        $return .= '         </tbody>';
        //---- END TABLE HEADER
        
        return $return;    
    }
    
    
    /**
     *
     * @access private
     * @return $tasksList
     */
    private function _getTasks() 
    {
        global $tasksList;
        
        //---- Get tasks for each project
        foreach($this->projectsList as $topkey=>$topvalue){
            if(is_array($topvalue)){
                $project_id = $topvalue['id'];
                if( empty($tasksList[$project_id]) ) {
                    $tasksList[$project_id] = $this->client->getAllTasks($project_id,1);
                }
            }
        }
        return $tasksList;
    }
    
    /**
     *
     * @access private
     * @return $subTasksList
     */
    private function _getSubTasks() 
    {
        global $tasksList,$subTasksList;
        
        if(empty($tasksList)) {
            $tasksList = $this->_getTasks();
        }
        $subTasksList = Array();
        
        //---- Get tasks for each project
        foreach($tasksList as $taskkey=>$tasks){
            if(is_array($tasks)){
                foreach($tasks as $key=>$task){
                    if(is_array($task)){
                        $task_id = $task['id'];
                        if( empty($subTasksList[$task_id]) ) {
                            $subTasksList[$task_id] = $this->client->getAllSubtasks($task_id);
                        }
                    }
                }
            }
        }
        
        return $subTasksList;
    }
    
    
    /**
     *
     * @access public
     * @return $return
     */
    public function showAllProjects()
    {
        global $langs;
        $return='';   
        //---- TABLE
        $return .= '     <div class="table-responsive">';
        $return .= '       <table class="table table-hover">';
        if(empty($this->projectsList)) {
            $return .= '<tr class="warning"><td>'.$langs->trans("NoProject").'</td></tr>';
        } else {
            //---- TABLE HEADERS
            $return .= $this->_kbArrayPopHeadersInHTML($this->projectsHeaders);
            //---- TABLE CONTENT
            $return .= $this->_kbArrayPopContentInHTML($this->projectsList,$this->projectsHeaders);
        }
        $return .= '       </table>';
        $return .= '     </div>';
        
        return $return;
        
    }
    
    
    /**
     *
     * @access public
     * @return $return
     */
    public function showAllTasks()
    {
        global $langs,$tasksList;
        $return='';

        $tasksList = $this->_getTasks();
        $tableContent .= $this->_kbArrayPopContentInHTML($tasksList,$this->tasksHeaders);
          
        //---- Print tasks              
        //---- TABLE
        $return .= '     <div class="table-responsive">';
        $return .= '       <table class="table table-hover">';
        if(empty($tableContent)) {
            $return .= '<tr class="warning"><td>'.$langs->trans("NoTask").'</td></tr>';
        } else {
          //---- TABLE HEADERS
          $return .= $this->_kbArrayPopHeadersInHTML($this->tasksHeaders);
          //---- TABLE CONTENT
          $return .= $tableContent;
        }
        $return .= '       </table>';
        $return .= '     </div>';
        
        
        return $return;
        
    }
    
    
    /**
     *
     * @access public
     * @return $return
     */
    public function showAllSubTasks()
    {
        global $langs,$subTasksList;
        $return='';
        
        $subTasksList = $this->_getSubTasks();
        $tableContent .= $this->_kbArrayPopContentInHTML($subTasksList,$this->subTasksHeaders);
        
        
        //---- Print tasks              
        //---- TABLE
        $return .= '     <div class="table-responsive">';
        $return .= '       <table class="table table-hover">';
        if(empty($tableContent)) {
            $return .= '<tr class="warning"><td>'.$langs->trans("NoTask").'</td></tr>';
        } else {
          //---- TABLE HEADERS
          $return .= $this->_kbArrayPopHeadersInHTML($this->subTasksHeaders);
          //---- TABLE CONTENT
          $return .= $tableContent;
        }
        $return .= '       </table>';
        $return .= '     </div>';
        
        
        return $return;
        
    }
    
    /**
     *
     * @access public
     * @return $return
     */
    public function showBoardHeader()
    {
        global $langs;
        $return='';
        
        if(empty($this->projectsList)) {
            $return .= '<div class="warning">'.$langs->trans("NoProject").'</div>';
        } else {
            //---- Get tasks for each project
            foreach($this->projectsList as $topkey=>$topvalue){
                if(is_array($topvalue)){
                    $project_id = $topvalue['id'];
                    $project_name = $topvalue['name'];
                    $return .= '<button type="button" data-id="'.$project_id.'" class="btn btn-info">'.$project_name.'</button>&nbsp;';
                }
            }
        }
        
        return $return;
        
    }
    
    /**
     *
     * @access public
     * @return $return
     */
    public function showBoardContent($project_id)
    {
        global $langs,$subTasksList;
        $return='';
        
        $subTasksList = $this->_getSubTasks();
        $tableContent .= $this->_kbArrayPopContentInHTML($subTasksList,$this->subTasksHeaders);
        
        
        //---- Print tasks              
        //---- TABLE
        $return .= '     <div class="table-responsive">';
        $return .= '       <table class="table table-hover">';
        if(empty($tableContent)) {
            $return .= '<tr class="warning"><td>'.$langs->trans("NoTask").'</td></tr>';
        } else {
          //---- TABLE HEADERS
          $return .= $this->_kbArrayPopHeadersInHTML($this->subTasksHeaders);
          //---- TABLE CONTENT
          $return .= $tableContent;
        }
        $return .= '       </table>';
        $return .= '     </div>';
        
        
        return $return;
        
    }
}
