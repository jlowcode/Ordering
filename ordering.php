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
	 * @param		Object		$listModel			List Model
	 * @param		String		$elName				Element name
	 * 
	 * @return		Null;
	 */
	private function setNestedConfig($listModel=null, $elName=null)
	{
		$params = $this->getParams();
		$listModel = isset($listModel) ? $listModel : $this->getListModel();
		$elName = isset($elName) ? $elName : $this->getElement()->name;

		$elements = $listModel->getElements('id');
        $refTree = $elements[$params->get('ref_tree')];
		$paramsTree = $refTree->getParams();
		$joinTree = $refTree->getJoin();

		$this->nested->idColumnName = $listModel->getPrimaryKeyAndExtra()[0]['colname'];
		$this->nested->parentIdColumnName = $joinTree->table_key;
		$this->nested->leftColumnName = $elName;
		$this->nested->rightColumnName = $elName . '_rgt';
		$this->nested->levelColumnName = $elName . '_lvl';
		$this->nested->positionColumnName = $elName . '_pos';
		$this->nested->positionParentColumnName = $elName . '_prt';
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

        $node = $input->getString('value');
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
	 * @param		String			$id				Parent node to search
	 * @param		Int				$listId			List id to get params
	 * @param		Int				$refTreeId		Element id to reference tree
	 * @param		String			$order			Column to order results
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
			->order($db->qn($order));
		$id == '' ? $query->where($db->qn($joinParent) . ' IS NULL') : $query->where($db->qn($joinParent) . ' = ' . $db->q($id));
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
		$db = Factory::getContainer()->get('DatabaseDriver');

		$listModel = $this->getListModel();
		$params = $this->getParams();
		$table = $listModel->getTable()->get('db_table_name');
		$columnName = $this->getElement()->name;

		$elements = $listModel->getElements('id');
		$refTreeId = $params->get('ref_tree');

		if(empty($refTreeId)) {
			$this->app->enqueueMessage(Text::_('PLG_FABRIK_ELEMENT_ORDERING_WARNING_SAVE'), 'warning');
			return;
		}

		$refTree = $elements[$refTreeId];
		$paramsTree = $refTree->getParams();
		$joinKey = $paramsTree->get('join_key_column');

		if(!$listModel->canShowTutorialTemplate()) {
			$this->app->enqueueMessage(Text::_('PLG_FABRIK_ELEMENT_ORDERING_ERROR_SAVE'), 'notice');
		}

		$this->saveNewColumn($row, $table, '_lvl');
		$this->saveNewColumn($row, $table, '_rgt');
		$this->saveNewColumn($row, $table, '_pos');
		$this->saveNewColumn($row, $table, '_prt');

		$query = $db->getQuery(true);
		$query->select('COUNT(*)')->from($db->qn($table));
		$db->setQuery($query);
		$rows = $db->loadResult();
		if($rows) {
			$this->setNestedConfig();
			$this->nested->rebuild();

			$data = $listModel->dataTemplateTutorial();
			$x = 1;
			foreach ($data as $val) {
				$query = $db->getQuery(true);
				$query->update($db->qn($table));
				$query->set($db->qn($columnName.'_pos') . ' = ' . $db->q('1'));
				$query->set($db->qn($columnName.'_prt') . ' = ' . $db->q($x++));
				$query->where($db->qn($joinKey) . ' = ' . $val['id']);
				$db->setQuery($query);
				$db->execute();

				$y = 2;
				$this->setPositionToChildren($table, $columnName, $joinKey, $y, $val['children']);
			}
		}
	}

	/**
	 * This method set position column to children nodes
	 * 
	 * @param		String			$table				Table to update
	 * @param		String			$column				Columns to set
	 * @param		String			$joinKey			Primary key
	 * @param		Int				$pos				Position to update
	 * @param		Array			$val				Row data
	 * 
	 * @return		Null
	 */
	private function setPositionToChildren($table, $column, $joinKey, &$pos, $val)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		if(!empty($val)) {
			foreach ($val as $v) {
				$query = $db->getQuery(true);
				$query->update($db->qn($table));
				$query->set($db->qn($column.'_pos') . ' = ' . $pos++);
				$query->where($db->qn($joinKey) . ' = ' . $v['id']);
				$db->setQuery($query);
				$db->execute();

				$this->setPositionToChildren($table, $column, $joinKey, $pos, $v['children']);
			}

		}		
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
		$listModel = $this->getListModel();
		$formModel = $this->getFormModel();
		$element = $this->getElement();
		$params = $this->getParams();
		$elementName = $this->getHTMLid();
		$columnName = $this->getElement()->name;

		$data = $formModel->formDataWithTableName;
		$value = (int) $data[$elementName.'_orig'][0];

		$elements = $listModel->getElements('id');
		$refTreeId = $params->get('ref_tree');
		$refTree = $elements[$refTreeId];
		$nameTree = $refTree->getHTMLid();
		$valueTree = $data[$nameTree][0];

		$this->makeOrdering($valueTree, $value, $columnName, $listModel, $refTree, $data['rowid']);

		return true;
	}

	/**
	 * This method redirect to makeOrdering function to ordering from draggable tree
	 * 
	 * @return		Null
	 */
	public function onMakeOrdering()
	{
		$listModel = JModelLegacy::getInstance('List', 'FabrikFEModel');
        $app = Factory::getApplication();

		$input = $app->input;
		$r = new stdClass;

        $refId = $input->getString('value');
        $refParentId = $input->getString('refParentId');
        $listId = $input->getInt('listId');
        $rowId = $input->getInt('rowId');

		$listModel->setId($listId);

		if(!$listModel->canShowTutorialTemplate()) {
			$this->app->enqueueMessage(Text::_('PLG_FABRIK_ELEMENT_ORDERING_ERROR_SAVE'), 'notice');
		}

		$elements = $listModel->getElements('id');
		$fields = $listModel->fieldsTemplateTutorial;
		$elTree = $elements[$fields->tree];
		$elOrder = $elements[$fields->ordering];
		$columnName = $elOrder->getElement()->name;

		$this->setParams($elOrder->getParams(), 0);

		try {
			$r->success = $this->makeOrdering($refParentId, $refId, $columnName, $listModel, $elTree, $rowId);
		} catch (\Throwable $th) {
			$r->msg = $th->getMessage();
			$r->success = false;
		}

		echo json_encode($r);
	}

	/**
	 * This method make the ordering to form view and to list view when tree is draggable
	 * 
	 * @param		String			$refParentId			Parent id 
	 * @param		Ints			$refId				Node id
	 * @param		String			$columnName			Name of the column used by this plugin
	 * @param		Object			$listModel			List model
	 * @param		Object			$refTree			Model of the reference tree
	 * @param		String			$rowId				Current record id
	 * 
	 * @return		Bool
	 */
	private function makeOrdering($refParentId, $refId, $columnName, $listModel, $refTree, $rowId='')
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$this->setNestedConfig($listModel, $columnName);
		$addPos = false;

		$table = $listModel->getTable()->get('db_table_name');
		$paramsTree = $refTree->getParams();
		$joinKey = $paramsTree->get('join_key_column');
		$joinParent = $paramsTree->get('tree_parent_id');

		if(!empty($refParentId)) {
			$fullColumnName = $columnName.'_pos';

			if($refId == -1) {
				$pos = 1;
				$addPos = true;
			} elseif ($refId) {
				$query = $db->getQuery(true);
				$query->select($db->qn($fullColumnName))
					->from($db->qn($table))
					->where($db->qn($joinKey) . ' = ' . $db->q($refId));
				$db->setQuery($query);
				$pos = (int) $db->loadResult()+1;
				$addPos = true;
			} else {
				$pos = $db->q($this->nested->getNewPosition((int) $refParentId));
			}

			if($addPos) {
				$query = $db->getQuery(true);
				$query->update($db->qn($table))
					->set($db->qn($fullColumnName) . ' = ' . $fullColumnName . ' + 1')
					->where($db->qn($fullColumnName) . ' >= ' . $db->q($pos));
				$db->setQuery($query);
				$db->execute();
			}

			$query = $db->getQuery(true);
			$query->update($db->qn($table))
				->set($db->qn($fullColumnName) . ' = ' . $pos)
				->where($db->qn($joinKey) . ' = ' . $rowId);
			$db->setQuery($query);
			$db->execute();
		} else {
			$fullColumnName = $columnName.'_prt';

			if($refId == -1) {
				$query = $db->getQuery(true);
				$query->select($db->qn($fullColumnName))
					->from($db->qn($table))
					->order($db->qn($fullColumnName) . ' ASC LIMIT 1');
				$db->setQuery($query);
				$pos = (int) $db->loadResult();
				$addPos = true;
			} elseif ($refId) {
				$query = $db->getQuery(true);
				$query->select($db->qn($fullColumnName))
					->from($db->qn($table))
					->where($db->qn($joinKey) . ' = ' . $db->q($refId));
				$db->setQuery($query);
				$pos = (int) $db->loadResult()+1;
				$addPos = true;
			} else {
				$pos = $db->q($this->nested->getNewPosition($refId));
			}

			if($addPos) {
				$query = $db->getQuery(true);
				$query->update($db->qn($table))
					->set($db->qn($fullColumnName) . ' = ' . $fullColumnName . ' + 1')
					->where($db->qn($fullColumnName) . ' >= ' . $db->q($pos));
				$db->setQuery($query);
				$db->execute();
			}

			$query = $db->getQuery(true);
			$query->update($db->qn($table))
				->set($db->qn($fullColumnName) . ' = ' . $pos)
				->where($db->qn($joinKey) . ' = ' . $rowId);
			$db->setQuery($query);
			$db->execute();
		}

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