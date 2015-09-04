<?php

namespace EllisLab\ExpressionEngine\Controller\Channels;

use CP_Controller;
use EllisLab\ExpressionEngine\Library\CP\Table;
use EllisLab\ExpressionEngine\Service\Model\Query\Builder;

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2015, EllisLab, Inc.
 * @license		https://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 3.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine CP Abstract Channel Class
 *
 * @package		ExpressionEngine
 * @subpackage	Control Panel
 * @category	Control Panel
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */
abstract class AbstractChannels extends CP_Controller {

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		// Allow AJAX requests for category editing
		if (AJAX_REQUEST && in_array(ee()->router->method, array('createCat', 'editCat')))
		{
			if ( ! $this->cp->allowed_group('can_edit_categories'))
			{
				show_error(lang('unauthorized_access'));
			}
		}
		elseif ( ! ee()->cp->allowed_group_any(
			'can_admin_channels', 
			'can_access_content_prefs'
			'can_admin_channels',
			'can_create_channels',
			'can_edit_channels',
			'can_delete_channels',
			'can_create_channel_fields',
			'can_edit_channel_fields',
			'can_delete_channel_fields',
			'can_create_statuses',
			'can_delete_statuses',
			'can_edit_statuses',
			'can_create_categories',
			'can_edit_categories',
			'can_delete_categories'
		))
		{
			show_error(lang('unauthorized_access'));
		}

		ee()->lang->loadfile('content');
		ee()->lang->loadfile('admin_content');
		ee()->lang->loadfile('channel');
		ee()->load->library('form_validation');

		// This header is section-wide
		ee()->view->header = array(
			'title' => lang('channel_manager'),
			'form_url' => ee('CP/URL', 'channels/search'),
			'toolbar_items' => array(
				'settings' => array(
					'href' => ee('CP/URL', 'settings/content-design'),
					'title' => lang('settings')
				)
			)
		);
	}

	protected function generateSidebar($active = NULL)
	{
		$sidebar = ee('CP/Sidebar')->make();

		$header = $sidebar->addHeader(lang('channels'), ee('CP/URL', 'channels'))
			->withButton(lang('new'), ee('CP/URL', 'channels/create'));

		if ($active == 'channel')
		{
			$header->isActive();
		}

		$header = $sidebar->addHeader(lang('field_groups'), ee('CP/URL', 'channels/fields/groups'))
			->withButton(lang('new'), ee('CP/URL', 'channels/fields/groups/create'));

		if ($active == 'field')
		{
			$header->isActive();
		}

		$header = $sidebar->addHeader(lang('category_groups'), ee('CP/URL', 'channels/cat'))
			->withButton(lang('new'), ee('CP/URL', 'channels/cat/create'));

		if ($active == 'category')
		{
			$header->isActive();
		}

		$header = $sidebar->addHeader(lang('status_groups'), ee('CP/URL', 'channels/status'))
			->withButton(lang('new'), ee('CP/URL', 'channels/status/create'));

		if ($active == 'status')
		{
			$header->isActive();
		}
	}

	/**
	 * Builds and returns a Table object for use of displaying a list of channels
	 *
	 * @param	Builder 	$channels	Query builder object for Channels
	 * @param	array 		$config		Optional Table class config overrides
	 * @param	boolean 	$mutable	Whether or not the data in the table is mutable, currently
	 *	determines whether or not checkboxes will be shown
	 */
	protected function buildTableFromChannelQuery(Builder $channels, $config = array(), $mutable = TRUE)
	{
		$table = ee('CP/Table', $config);

		$columns = array(
			'col_id',
			'channel',
			'short_name',
			'manage' => array(
				'type'	=> Table::COL_TOOLBAR
			)
		);

		if ($mutable)
		{
			$columns[] = array(
				'type'	=> Table::COL_CHECKBOX
			);
		}

		$table->setColumns($columns);
		$table->setNoResultsText('no_channels', 'create_channel', ee('CP/URL', 'channels/create'));

		$sort_map = array(
			'col_id' => 'channel_id',
			'channel' => 'channel_title',
			'short_name' => 'channel_name'
		);

		$channels = $channels->order($sort_map[$table->sort_col], $table->sort_dir)
			->limit($table->config['limit'])
			->offset(($table->config['page'] - 1) * $table->config['limit'])
			->all();

		$data = array();
		foreach ($channels as $channel)
		{
			$edit_url = ee('CP/URL', 'channels/edit/'.$channel->getId());

			$columns = array(
				$channel->getId(),
				array(
					'content' => $channel->channel_title,
					'href' => $edit_url
				),
				$channel->channel_name,
				array('toolbar_items' => array(
					'edit' => array(
						'href' => $edit_url,
						'title' => lang('edit')
					),
					'settings' => array(
						'href' => ee('CP/URL', 'channels/settings/'.$channel->getId()),
						'title' => lang('settings')
					),
					'txt-only' => array(
						'href' => ee('CP/URL', 'channels/layouts/'.$channel->getId()),
						'title' => (lang('layouts')),
						'content' => strtolower(lang('layouts'))
					)
				))
			);

			if ($mutable)
			{
				$columns[] = array(
					'name' => 'channels[]',
					'value' => $channel->getId(),
					'data'	=> array(
						'confirm' => lang('channel') . ': <b>' . htmlentities($channel->channel_title, ENT_QUOTES) . '</b>'
					)
				);
			}

			$attrs = array();
			if (ee()->session->flashdata('highlight_id') == $channel->getId())
			{
				$attrs = array('class' => 'selected');
			}

			$data[] = array(
				'attrs' => $attrs,
				'columns' => $columns
			);
		}

		$table->setData($data);

		return $table;
	}

	/**
	 * Builds and returns a Table object for use of displaying a list of channel fields
	 *
	 * @param	Builder 	$fields		Query builder object for channel fields
	 * @param	array 		$config		Optional Table class config overrides
	 * @param	boolean 	$mutable	Whether or not the data in the table is mutable, currently
	 *	determines whether or not checkboxes will be shown
	 */
	protected function buildTableFromChannelFieldsQuery(Builder $fields, $config = array(), $mutable = TRUE)
	{
		$table = ee('CP/Table', array_merge(array('autosort' => TRUE), $config));

		$columns = array(
			'id',
			'name',
			'short_name' => array(
				'encode' => FALSE
			),
			'type',
			'manage' => array(
				'type'	=> Table::COL_TOOLBAR
			)
		);

		if ($mutable)
		{
			$columns[] = array(
				'type'	=> Table::COL_CHECKBOX
			);
		}

		$table->setColumns($columns);
		$table->setNoResultsText('no_fields', 'create_new', ee('CP/URL', 'channels/fields/create'));

		$data = array();

		$field_id = ee()->session->flashdata('field_id');

		foreach ($fields->all() as $field)
		{
			$edit_url = ee('CP/URL', 'channels/fields/edit/' . $field->field_id);

			$column = array(
				$field->field_id,
				array(
					'content' => $field->field_label,
					'href' => $edit_url
				),
				'<var>{' . htmlentities($field->field_name, ENT_QUOTES) . '}</var>',
				$field->field_type,
				array('toolbar_items' => array(
					'edit' => array(
						'href' => $edit_url,
						'title' => lang('edit')
					)
				))
			);

			if ($mutable)
			{
				$column[] = array(
					'name' => 'selection[]',
					'value' => $field->field_id,
					'data' => array(
						'confirm' => lang('field') . ': <b>' . htmlentities($field->field_label, ENT_QUOTES) . '</b>'
					)
				);
			}

			$attrs = array();

			if ($field_id && $field->field_id == $field_id)
			{
				$attrs = array('class' => 'selected');
			}

			$data[] = array(
				'attrs'		=> $attrs,
				'columns'	=> $column
			);
		}

		$table->setData($data);

		return $table;
	}

	/**
	 * Builds and returns a Table object for use of displaying a list of channel field groups
	 *
	 * @param	Builder 	$groups		Query builder object for channel field groups
	 * @param	array 		$config		Optional Table class config overrides
	 * @param	boolean 	$mutable	Whether or not the data in the table is mutable, currently
	 *	determines whether or not checkboxes will be shown
	 */
	protected function buildTableFromChannelGroupsQuery(Builder $groups, $config = array(), $mutable = TRUE)
	{
		$table = ee('CP/Table', array_merge(array('autosort' => TRUE), $config));

		$columns = array(
			'group_name',
			'manage' => array(
				'type'	=> Table::COL_TOOLBAR
			)
		);

		if ($mutable)
		{
			$columns[] = array(
				'type'	=> Table::COL_CHECKBOX
			);
		}

		$table->setColumns($columns);
		$table->setNoResultsText('no_field_groups', 'create_new', ee('CP/URL', 'channels/fields/groups/create'));

		$data = array();

		$group_id = ee()->session->flashdata('group_id');

		foreach ($groups->all() as $group)
		{
			$edit_url = ee('CP/URL', 'channels/fields/groups/edit/' . $group->group_id);

			$column = array(
				array(
					'content' => $group->group_name,
					'href' => $edit_url
				),
				array('toolbar_items' => array(
					'edit' => array(
						'href' => $edit_url,
						'title' => lang('edit')
					),
					'txt-only' => array(
						'href' => ee('CP/URL', 'channels/fields/' . $group->group_id),
						'title' => lang('custom_fields'),
						'content' => strtolower(lang('fields'))
					)
				))
			);

			if ($mutable)
			{
				$column[] = array(
					'name' => 'selection[]',
					'value' => $group->group_id,
					'data' => array(
						'confirm' => lang('group') . ': <b>' . htmlentities($group->group_name, ENT_QUOTES) . '</b>'
					)
				);
			}

			$attrs = array();

			if ($group_id && $group->group_id == $group_id)
			{
				$attrs = array('class' => 'selected');
			}

			$data[] = array(
				'attrs'		=> $attrs,
				'columns'	=> $column
			);
		}

		$table->setData($data);

		return $table;
	}

	/**
	 * Builds and returns a Table object for use of displaying a list of category groups
	 *
	 * @param	Builder 	$cat_groups	Query builder object for category groups
	 * @param	array 		$config		Optional Table class config overrides
	 * @param	boolean 	$mutable	Whether or not the data in the table is mutable, currently
	 *	determines whether or not checkboxes will be shown
	 */
	protected function buildTableFromCategoryGroupsQuery(Builder $cat_groups, $config = array(), $mutable = TRUE)
	{
		$table = ee('CP/Table', $config);

		$columns = array(
			'col_id',
			'group_name',
			'manage' => array(
				'type'	=> Table::COL_TOOLBAR
			)
		);

		if ($mutable)
		{
			$columns[] = array(
				'type'	=> Table::COL_CHECKBOX
			);
		}

		$table->setColumns($columns);
		$table->setNoResultsText(
			'no_category_groups',
			'create_category_group',
			ee('CP/URL', 'channels/cat/create')
		);

		$sort_map = array(
			'col_id' => 'group_id',
			'group_name' => 'group_name'
		);

		$cat_groups = $cat_groups->order($sort_map[$table->sort_col], $table->sort_dir)
			->limit($table->config['limit'])
			->offset(($table->config['page'] - 1) * $table->config['limit'])
			->all();

		$data = array();
		foreach ($cat_groups as $group)
		{
			$edit_url = ee('CP/URL', 'channels/cat/edit/'.$group->getId());

			$columns = array(
				$group->getId(),
				array(
					'content' => $group->group_name . ' ('.count($group->getCategories()).')',
					'href' => $edit_url
				),
				array('toolbar_items' => array(
					'view' => array(
						'href' => ee('CP/URL', 'channels/cat/cat-list/'.$group->getId()),
						'title' => lang('view')
					),
					'edit' => array(
						'href' => $edit_url,
						'title' => lang('edit')
					),
					'txt-only' => array(
						'href' => ee('CP/URL', 'channels/cat/field/'.$group->getId()),
						'title' => strtolower(lang('custom_fields')),
						'content' => strtolower(lang('fields'))
					)
				))
			);

			if ($mutable)
			{
				$columns[] = array(
					'name' => 'cat_groups[]',
					'value' => $group->getId(),
					'data'	=> array(
						'confirm' => lang('category_group') . ': <b>' . htmlentities($group->group_name, ENT_QUOTES) . '</b>'
					)
				);
			}

			$attrs = array();
			if (ee()->session->flashdata('highlight_id') == $group->getId())
			{
				$attrs = array('class' => 'selected');
			}

			$data[] = array(
				'attrs' => $attrs,
				'columns' => $columns
			);
		}

		$table->setData($data);

		return $table;
	}

	/**
	 * Builds and returns a Table object for use of displaying a list of categories
	 *
	 * @param	Builder 	$categories	Query builder object for categories
	 * @param	array 		$config		Optional Table class config overrides
	 * @param	boolean 	$mutable	Whether or not the data in the table is mutable, currently
	 *	determines whether or not checkboxes will be shown
	 */
	protected function buildTableFromCategoriesQuery(Builder $categories, $config = array(), $mutable = TRUE)
	{
		$table = ee('CP/Table', $config);
		$table->setColumns(
			array(
				'col_id',
				'name',
				'url_title',
				'manage' => array(
					'type'	=> Table::COL_TOOLBAR
				)
			)
		);

		$sort_map = array(
			'col_id' => 'cat_id',
			'name' => 'cat_name',
			'url_title' => 'cat_url_title'
		);

		$categories = $categories->order($sort_map[$table->sort_col], $table->sort_dir)
			->limit($table->config['limit'])
			->offset(($table->config['page'] - 1) * $table->config['limit'])
			->all();

		$data = array();
		foreach ($categories as $category)
		{
			$edit_url = ee('CP/URL', 'channels/cat/edit-cat/'.$category->group_id.'/'.$category->cat_id);

			$data[] = array(
				$category->getId(),
				array(
					'content' => $category->cat_name,
					'href' => $edit_url
				),
				$category->cat_url_title,
				array('toolbar_items' => array(
					'edit' => array(
						'href' => $edit_url,
						'title' => lang('edit')
					)
				))
			);
		}

		$table->setData($data);

		return $table;
	}

	/**
	 * Builds and returns a Table object for use of displaying a list of status groups
	 *
	 * @param	Builder 	$status_groups	Query builder object for status groups
	 * @param	array 		$config			Optional Table class config overrides
	 * @param	boolean 	$mutable		Whether or not the data in the table is mutable,
	 *	currently determines whether or not checkboxes will be shown
	 */
	protected function buildTableFromStatusGroupsQuery(Builder $status_groups, $config = array(), $mutable = TRUE)
	{
		$table = ee('CP/Table', $config);

		$columns = array(
			'col_id',
			'group_name',
			'manage' => array(
				'type'	=> Table::COL_TOOLBAR
			)
		);

		if ($mutable)
		{
			$columns[] = array(
				'type'	=> Table::COL_CHECKBOX
			);
		}

		$table->setColumns($columns);

		$sort_map = array(
			'col_id' => 'group_id',
			'group_name' => 'group_name'
		);

		$status_groups = $status_groups->order($sort_map[$table->sort_col], $table->sort_dir)
			->limit($table->config['limit'])
			->offset(($table->config['page'] - 1) * $table->config['limit'])
			->all();

		$data = array();
		foreach ($status_groups as $group)
		{
			$columns = array(
				$group->getId(),
				$group->group_name,
				array('toolbar_items' => array(
					'view' => array(
						'href' => ee('CP/URL', 'channels/status/status-list/'.$group->getId()),
						'title' => lang('view')
					),
					'edit' => array(
						'href' => ee('CP/URL', 'channels/status/edit/'.$group->getId()),
						'title' => lang('edit')
					)
				))
			);

			if ($mutable)
			{
				$columns[] = array(
					'name' => 'status_groups[]',
					'value' => $group->getId(),
					'data'	=> array(
						'confirm' => lang('status_group') . ': <b>' . htmlentities($group->group_name, ENT_QUOTES) . '</b>'
					),
					// Cannot delete default group
					'disabled' => ($group->group_name == 'Default') ? 'disabled' : NULL
				);
			}

			$attrs = array();
			if (ee()->session->flashdata('highlight_id') == $group->getId())
			{
				$attrs = array('class' => 'selected');
			}

			$data[] = array(
				'attrs' => $attrs,
				'columns' => $columns
			);
		}

		$table->setData($data);

		return $table;
	}
}
// EOF
