<?php
/**
 * Ordering Element
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.element.ordering
 * @copyright   Copyright (C) 2024 Jlowcode Org - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Layout\LayoutInterface;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Profiler\Profiler;
use Joomla\CMS\Factory;
use Joomla\String\StringHelper;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Fabrik\Helpers\Php;

require_once JPATH_PLUGINS . '/fabrik_element/ordering/nested-set-model.php';

/**
 * 	Plugin element to render a tree of the data that user can select the order of the elements
 * 
 * @package     	Joomla.Plugin
 * @subpackage  	Fabrik.element.ordering
 * @since       	4.0
 */
class PlgFabrik_ElementOrdering extends PlgFabrik_ElementList
{
	/**
	 * Value to default id tree on form
	 *
	 * @var 	Array
	 */
	private $defaultTree;

	/**
	 * Nested set model object
	 * 
	 * @var		Object
	 */
	private $nested;

	/**
	 * Constructor
	 *
	 * @param   	Object 		&$subject 		The object to observe
	 * @param   	Array		$config   		An array that holds the plugin configuration
	 *
	 * @return		Null
	 */	 
	public function __construct(&$subject, $config) 
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$conn = $db->getConnection();

		$nested = new NestedSet($conn);
		$this->nested = $nested;
	
		parent::__construct($subject, $config);
	}

	/**
	 * This method get set configuration to use nested set model to order
	 * 
	 * @return		Null;
	 */
	private function setNestedConfig()
	{
		$params = $this->getParams();
		$listModel = $this->getListModel();

		$elements = $listModel->getElements('id');
        $refTree = $elements[$params->get('ref_tree')];
		$paramsTree = $refTree->getParams();
		$joinTree = $refTree->getJoin();

		$this->nested->idColumnName = $listModel->getPrimaryKeyAndExtra()[0]['colname'];
		$this->nested->parentIdColumnName = $joinTree->table_key;
		$this->nested->leftColumnName = $this->getElement()->name;
		$this->nested->rightColumnName = $this->getElement()->name . '_rgt';
		$this->nested->levelColumnName = $this->getElement()->name . '_lvl';
		$this->nested->positionColumnName = $this->getElement()->name . '_pos';
		$this->nested->tableName = $listModel->getTable()->db_table_name;
	}

	/**
	 * Draws the html form element
	 *
	 * @param       Array           $data                   To pre-populate element with
	 * @param       Int             $repeatCounter          Repeat group counter
	 *
	 * @return      String
	 */
	public function render($data, $repeatCounter = 0)
	{	
		$params = $this->getParams();
		$formModel = $this->getFormModel();
		$listModel = $this->getListModel();
		$id = $this->getFullName();

        $elements = $listModel->getElements('id');
        $refTree = $elements[$params->get('ref_tree')];
		$nameRefTree = $refTree->getFullName();

		$this->defaultTree = FArrayHelper::getValue($data, $nameRefTree . '_raw');
		$this->defaultTree = is_array($this->defaultTree) ? $this->defaultTree[0] : $this->defaultTree;

		$d = new stdClass;

		if (($formModel->isEditable() || $this->isEditable()) && $this->canUse()) {
			$layout = $this->getLayout('form-tree');
			$d->id = $id;
			$d->data = $data;

			$render = $layout->render($d);
		}

		return $render;
	}

	/**
	 * Check user can view the read only element OR view in list view
	 *
	 * @param   	String 		$view 		View list/form
	 *
	 * @return  	Bool
	 */
	public function canView($view='form') 
	{
		return false;
	}

	/**
	 * Shows the data formatted for the list view
	 * Improve later if needed
	 * 
	 * @param   	String   		$data     		Elements data
	 * @param   	stdClass 		&$thisRow 		All the data in the lists current row
	 * @param   	Array    		$opts     		Rendering options
	 *
	 * @return  	String
	 */
	public function renderListData($data, stdClass &$thisRow, $opts = array())
	{
		return '';
	}

    /**
	 * Returns javascript which creates an instance of the class defined in formJavascriptClass()
	 * 
	 * @param       Int         $repeatCounter          Repeat group counter
	 * 
	 * @return      Array
	 */
	public function elementJavascript($repeatCounter)
	{
		$id = $this->getHTMLId($repeatCounter);
        $params = $this->getParams();
        $listModel = $this->getListModel();
        $elements = $listModel->getElements('id');
        $refTree = $elements[$params->get('ref_tree')];
        $elParams = $refTree->getParams();

        $opts = new stdClass();
        $opts->listId = $listModel->getId();
        $opts->refTree = strpos($refTree->getName(), 'Databasejoin') > -1 && $elParams->get('database_join_display_type') == 'auto-complete' && $elParams->get('database_join_display_style') == 'only-treeview' ? $refTree->getHTMLid() : false;
		$opts->refTreeId = $params->get('ref_tree');
		$opts->defaultTree = $this->defaultTree;
		$opts->elName = $this->getHTMLid().'[]';
 
        HTMLHelper::script('plugins/fabrik_element/ordering/dist/js/tree.jquery.js');
        HTMLHelper::stylesheet('plugins/fabrik_element/ordering/dist/css/tags.css');
        HTMLHelper::stylesheet('plugins/fabrik_element/ordering/dist/css/jqtree.css');

        $this->jsScriptTranslation($refTree->getRawLabel());

		return Array('FbOrdering', $id, $opts);
	}

    /**
     * Method sends text messages to javascript file
     *
	 * @param		String			$nameRefTree			Name of the reference tree
	 * 
	 * @return  	Null
     */
    function jsScriptTranslation($nameRefTree)
    {
        Text::script('PLG_FABRIK_ELEMENT_ORDERING_TYPE_REF_ELEMENT_ERROR');
		Text::sprintf('PLG_FABRIK_ELEMENT_ORDERING_NO_CHILDREN', $nameRefTree, Array('script'=>true));
		Text::sprintf('PLG_FABRIK_ELEMENT_ORDERING_FIRST_STEP', $nameRefTree, Array('script'=>true));
    }

    /**
     * This method called by ajax process the parent node and send the children to rebuild the tree
     * 
     * @return      Json
     */
    public function onGetTree() 
    {
		$listModel = JModelLegacy::getInstance('List', 'FabrikFEModel');
        $app = Factory::getApplication();

		$input = $app->input;
		$r = new stdClass;

        $node = $input->getInt('value');
        $listId = $input->getInt('listId');
        $refTreeId = $input->getInt('refTreeId');
        $htmlName = explode('___', $input->getString('htmlName'))[1];

		$listModel->setId($listId);
		$table = $listModel->getFormModel()->getTableName();

		try {
			$r->data = $this->getChildrenNodes($node, $listId, $refTreeId, $htmlName);
			$r->htmlName = $table . '___' . $htmlName;
			$r->success = true;
		} catch (\Throwable $th) {
			$r->msg = $th->getMessage();
			$r->success = false;
		}

		echo json_encode($r);
    }

	/**
	 * This method get from database the children nodes
	 * 
	 * @param		Int			$id				Parent node to search
	 * @param		Int			$listId			List id to get params
	 * @param		Int			$refTreeId		Element id to reference tree
	 * @param		String		$order			Column to order results
	 * 
	 * @return		Array
	 */
	private function getChildrenNodes($id, $listId, $refTreeId, $order)
	{
		$listModel = JModelLegacy::getInstance('List', 'FabrikFEModel');
		$db = Factory::getContainer()->get('DatabaseDriver');
		$first = Array(-1, Text::_("PLG_FABRIK_ELEMENT_ORDERING_FIRST"));

		$listModel->setId($listId);
		$table = $listModel->getFormModel()->getTableName();
		$elements = $listModel->getElements('id');
		$refTree = $elements[$refTreeId];
		$paramsTree = $refTree->getParams();

		$joinKey = $paramsTree->get('join_key_column');
		$joinVal = $paramsTree->get('join_val_column');
		$joinParent = $paramsTree->get('tree_parent_id');

		$query = $db->getQuery(true);
		$query->select($db->qn([$joinKey, $joinVal]))
			->from($db->qn($table))
			->where($db->qn($joinParent) . ' = ' . $db->q($id))
			->order($db->qn($order));
		$db->setQuery($query);
		$children = $db->loadRowList();

		if(!empty($children)) array_unshift($children, $first);

		return $children;
	}

	/**
     * Is the element consider to be empty for purposes of rendering on the form,
     * i.e. for assigning classes, etc.  Can be overridden by individual elements.
     * 
     * @param   	Array 		$data          		Data to test against
     * @param   	Int   		$repeatCounter 		Repeat group #
     * 
     * @return  	Bool
     */
    public function dataConsideredEmpty($data, $repeatCounter)
    {
		return false;
    }

	/**
	 * Run before the element is saved
	 * Here we need add another column in database
	 * 
	 * @param   	Object  	&$row  		That is going to be updated
	 *
	 * @return 		Null
	 */
	public function beforeSave(&$row) 
	{
		$listModel = $this->getListModel();
		$table = $listModel->getTable()->get('db_table_name');

		$this->saveNewColumn($row, $table, '_lvl');
		$this->saveNewColumn($row, $table, '_rgt');
		$this->saveNewColumn($row, $table, '_pos');
	}

	/**
	 * This method verify if the column exists and if not create it in database
	 * 
	 * @param		Object		$row			That is going to be updated
	 * @param		String		$table			Actual table
	 * @param		String		$sufix			Column sufix to check
	 * 
	 * @return		Bool
	 */
	private function saveNewColumn($row, $table, $sufix)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$query = $db->getQuery(true);
		$query->select($db->qn('COLUMN_NAME'))
			->from($db->qn('INFORMATION_SCHEMA') . '.' . $db->qn('COLUMNS'))
			->where($db->qn('TABLE_NAME') . ' = ' . $db->q($table))
			->where($db->qn('COLUMN_NAME') . ' = ' . $db->q($row->name.$sufix))
			->where($db->qn('TABLE_SCHEMA') . ' = (SELECT DATABASE())');
		$db->setQuery($query);
		$column = $db->loadResult();

		if($column) return;

		$sql = "ALTER TABLE {$db->qn($table)} ADD COLUMN {$db->qn($row->name.$sufix)} INT AFTER {$db->qn($row->name)}";
		$db->setQuery($sql);
		return $db->execute();
	}

	/**
	 * Called when the element is saved
	 * 
	 * @param   	Array 		$data 		Posted element save data
	 *
	 * @return  	Bool
	 */
	public function onSave($data)
	{
		return true;
	}

	/**
	 * Get database field description
	 *
	 * @return  	String
	 */
	public function getFieldDescription()
	{
		return 'INT';
	}

	/**
	 * Run right before the form processing
	 * keeps the data to be processed or sent if consent is not given
	 *
	 * @return		Bool
	 */
	public function onBeforeProcess()
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$listModel = $this->getListModel();
		$formModel = $this->getFormModel();
		$params = $this->getParams();
		$elementName = $this->getHTMLid();
		$columnName = $this->getElement()->name;
		$table = $listModel->getTable()->get('db_table_name');

		$elements = $listModel->getElements('id');
		$refTreeId = $params->get('ref_tree');
		$refTree = $elements[$refTreeId];
		$paramsTree = $refTree->getParams();
		$joinKey = $paramsTree->get('join_key_column');

		$query = $db->getQuery(true);
		$query->select($db->qn($columnName))
			->from($db->qn($table))
			->where($db->qn($joinKey) . ' = ' . $db->q($formModel->formData['rowid']));
		$db->setQuery($query);
		$value = $db->loadResult();

		$orig = $formModel->formData[$elementName][0];
		$formModel->formData[$elementName][0] = empty($orig) ? '0' : $value;
		$formModel->formData[$elementName.'_raw'][0] = empty($orig) ? '0' : $value;
		$formModel->formData[$elementName.'_orig'][0] = empty($orig) ? '0' : $orig;

		return true;
	}

	/**
	 * Run right at the end of the form processing
	 * form needs to be set to record in database for this to hook to be called
	 * 
	 * @return    	Bool
	 */
	public function onAfterProcess()
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$this->setNestedConfig();

		$listModel = $this->getListModel();
		$formModel = $this->getFormModel();
		$element = $this->getElement();
		$params = $this->getParams();
		$elementName = $this->getHTMLid();
		$columnName = $this->getElement()->name;
		$columns = [$columnName, $columnName.'_pos', $columnName.'_lvl', $columnName.'_rgt'];

		$data = $formModel->formDataWithTableName;
		$value = (int) $data[$elementName.'_orig'][0];
		$table = $listModel->getTable()->get('db_table_name');

		$elements = $listModel->getElements('id');
		$refTreeId = $params->get('ref_tree');
		$refTree = $elements[$refTreeId];
		$paramsTree = $refTree->getParams();
		$joinKey = $paramsTree->get('join_key_column');
		$nameTree = $refTree->getHTMLid();
		$valueTree = (int) $data[$nameTree][0];

		if($value == -1) {
			$pos = 1;
		} elseif ($value) {
			$query = $db->getQuery(true);
			$query->select($db->qn($columnName.'_pos'))
				->from($db->qn($table))
				->where($db->qn($joinKey) . ' = ' . $db->q($value));
			$db->setQuery($query);
			$pos = (int) $db->loadResult()+1;
		} else {
			$pos = $db->q($this->nested->getNewPosition($valueTree));
		}

		$query = $db->getQuery(true);
		$query->update($db->qn($table));
		$query->set($db->qn($columnName.'_pos') . ' = ' . $pos);
		$query->where($db->qn($joinKey) . ' = ' . $data['rowid']);
		$db->setQuery($query);
		$db->execute();

		$this->nested->rebuild();

		return true;
	}

	/**
	 * Trigger called when a row is deleted, if a join (multiselect/checbox) then remove
	 * rows from _repeat_foo table.
	 * 
	 * @param   	Array 		$groups 		Grouped data of rows to delete
	 * 
	 * @return  	Bool
	 */
	public function onDeleteRows($groups)
	{
		$this->setNestedConfig();

		foreach($groups as $group) {
			foreach ($group as $row) {
				$id = $row->__pk_val;
				$this->nested->deletePullUpChildren($id);
				$this->nested->rebuild();
			}
		}

		return true;
	}
}