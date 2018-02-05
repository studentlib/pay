<?php
defined('IN_PHPCMS') or exit('No permission resources.');
pc_base::load_sys_class('model', '', 0);
class orders_list_model extends model {
	public function __construct() {
		$this->db_config = pc_base::load_config('database');
		$this->db_setting = 'default';
		$this->table_name = 'orders_list';
		parent::__construct();
	}
	/**
	 * @param string $table
	 */
	public function setTable($table)
	{
	    $this->table_name=$table;
	}
	/**
	 * @return string
	 */
	public function getTable()
	{
	    return $this->table_name;
	}
}
?>