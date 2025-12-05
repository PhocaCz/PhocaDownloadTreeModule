<?php
/*
 * @package Joomla 1.5
 * @copyright Copyright (C) 2005 Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 *
 * @module Phoca - Phoca Gallery Module
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @based on javascript: dTree 2.05 www.destroydrop.com/javascript/tree/
 * @copyright (c) 2002-2003 Geir Landrö
 */
defined('_JEXEC') or die('Restricted access');// no direct access

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

// Include Phoca Download
if (!ComponentHelper::isEnabled('com_phocadownload', true)) {
	echo '<div class="alert alert-danger">Phoca Download Error: Phoca Download component is not installed or not published on your system</div>';
	return;
}

if (! class_exists('PhocaDownloadLoader')) {
    require_once( JPATH_ADMINISTRATOR.'/components/com_phocadownload/libraries/loader.php');
}
phocadownloadimport('phocadownload.access.access');
phocadownloadimport('phocadownload.path.path');
phocadownloadimport('phocadownload.path.route');
phocadownloadimport('phocadownload.access.access');
phocadownloadimport('phocadownload.ordering.ordering');

$user 		= Factory::getUser();
$db 		= Factory::getDBO();
$app 		= Factory::getApplication();
$menu 		= $app->getMenu();
$document	= Factory::getDocument();
$paramsC		= ComponentHelper::getParams('com_phocadownload') ;
$category_ordering		= $paramsC->get( 'category_ordering', 1 );
$categoryOrdering 		= PhocaDownloadOrdering::getOrderingText($category_ordering, 2);
$moduleclass_sfx 			= htmlspecialchars((string)$params->get('moduleclass_sfx'), ENT_COMPAT, 'UTF-8');
HTMLHelper::_('stylesheet', 'media/mod_phocadownload_tree/jstree/themes/proton/style.min.css', array('version' => 'auto'));
HTMLHelper::_('script', 'media/mod_phocadownload_tree/jstree/jstree.min.js', array('version' => 'auto'));


$treeId = uniqid( "phdtjstree" );

// Current category info
$id 	= $app->getInput()->get( 'id', 0, 'int' );
$option = $app->getInput()->get( 'option', '', 'string' );
$view 	= $app->getInput()->get( 'view', '', 'string' );

if ( $option == 'com_phocadownload' && $view == 'category' ) {
	$categoryId = $id;
} else {
	$categoryId = 0;
}

$hide_categories = '';
if ($params->get( 'hide_categories' ) != '') {
	$hide_categories = $params->get( 'hide_categories' );
}

// PARAMS - Hide categories
$hideCat		= trim( $hide_categories );
$hideCatArray	= explode( ',', $hide_categories );
$hideCatSql		= '';
if (is_array($hideCatArray)) {
	foreach ($hideCatArray as $value) {
		$hideCatSql .= ' AND cc.id != '. (int) trim($value) .' ';
	}
}


// PARAMS - Access Category - display category in category list, which user cannot access
$display_access_category = $params->get( 'display_access_category',0 );

// ACCESS - Only registered or not registered
$hideCatAccessSql = '';
$user  = Factory::getUser();
$aid = max ($user->getAuthorisedViewLevels());
if ($display_access_category == 0) {
 $hideCatAccessSql = ' AND cc.access <= '. $aid;
}

// All categories -------------------------------------------------------
$query = 'SELECT cc.title AS title, cc.id AS id, cc.parent_id as parent_id, cc.alias as alias, cc.access as access, cc.accessuserid as accessuserid'
		. ' FROM #__phocadownload_categories AS cc'
		. ' WHERE cc.published = 1'
		//. ' AND cc.approved = 1'
		. $hideCatSql
		. $hideCatAccessSql
		. ' ORDER BY '.$categoryOrdering;

$db->setQuery( $query );
$categories = $db->loadAssocList();


$unSet = 0;
foreach ($categories as $key => $category) {
	// USER RIGHT - ACCESS =======================================
	$rightDisplay	= 1;

	if (isset($categories[$key])) {
		//$rightDisplay = PhocaGalleryAccess::getUserRight( 'accessuserid', $categories[$key]->accessuserid, $categories[$key]->access, $user->get('aid', 0), $user->get('id', 0), $display_access_category);
		$rightDisplay = PhocaDownloadAccess::getUserRight( 'accessuserid', $categories[$key]['accessuserid'], $categories[$key]['access'], $user->getAuthorisedViewLevels(), $user->get('id', 0), $display_access_category);
	}
	//$user->authorisedLevels()
	if ($rightDisplay == 0) {
		unset($categories[$key]);
		$unSet = 1;
	}
	// ============================================================
}
if ($unSet == 1) {
	$categories = array_values($categories);
}



$tree = PDTMcategoryTree($categories);
$tree = PDTMnestedToUl($tree, $categoryId);


function PDTMcategoryTree($d, $r = 0, $pk = 'parent_id', $k = 'id', $c = 'children') {
	$m = array();
	foreach ($d as $e) {
		isset($m[$e[$pk]]) ?: $m[$e[$pk]] = array();
		isset($m[$e[$k]]) ?: $m[$e[$k]] = array();
		$m[$e[$pk]][] = array_merge($e, array($c => &$m[$e[$k]]));
	}
	//return $m[$r][0]; // remove [0] if there could be more than one root nodes
	if (isset($m[$r])) {
		return $m[$r];
	}
	return 0;
}

function PDTMnestedToUl($data, $currentCatid = 0) {
	$result = array();

	if (!empty($data) && count($data) > 0) {
		$result[] = '<ul>';
		foreach ($data as $k => $v) {
			$link 		= Route::_(PhocaDownloadRoute::getCategoryRoute($v['id'], $v['alias']));

			// Current Category is selected
			if ($currentCatid == $v['id']) {
				$result[] = sprintf(
					'<li data-jstree=\'{"opened":true,"selected":true}\' >%s%s</li>',
					'<a href="'.$link.'">' . $v['title']. '</a>',
					PDTMnestedToUl($v['children'], $currentCatid)
				);
			} else {
				$result[] = sprintf(
					'<li>%s%s</li>',
					'<a href="'.$link.'">' . $v['title']. '</a>',
					PDTMnestedToUl($v['children'], $currentCatid)
				);
			}
		}
		$result[] = '</ul>';
	}

	return implode($result);
}




// Categories (Head)
$itemsCategories	= $menu->getItems('link', 'index.php?option=com_phocadownload&view=categories');
$linkCategories 	= '';
$categoriesHeader 	= '';
if(isset($itemsCategories[0]) && (int)$params->get('display_header', 1) == 1) {
	$itemId = $itemsCategories[0]->id;
	$linkCategories = Route::_('index.php?option=com_phocadownload&view=categories&Itemid='.$itemId);
	$categoriesHeader = '<div><a href="'.$linkCategories.'" style="text-decoration:none;color:#333;">'.Text::_( 'MOD_PHOCADOWNLOAD_TREE_CATEGORIES' ).'</a></div>';
}



HTMLHelper::_('jquery.framework', false);
$js	  = array();
$js[] = ' ';
$js[] = 'jQuery(document).ready(function() {';
$js[] = '   jQuery("#'.$treeId.'").jstree({';
$js[] = '      "core": {';
$js[] = '         "themes": {';
$js[] = '            "name": "proton",';
$js[] = '            "responsive": true';
$js[] = '         }';
$js[] = '      }';
$js[] = '   }).on("select_node.jstree", function (e, data) {';
$js[] = '      document.location = data.instance.get_node(data.node, true).children("a").attr("href");';
$js[] = '   });';
$js[] = '   jQuery("#'.$treeId.'").on("changed.jstree", function (e, data) {';
//$js[] = '      con sole.log(data.selected);';
$js[] = '   });';
$js[] = '   ';
$js[] = '   jQuery("#'.$treeId.' button").on("click", function () {';
$js[] = '      jQuery("#'.$treeId.'").jstree(true).select_node("child_node_1");';
$js[] = '      jQuery("#'.$treeId.'").jstree("select_node", "child_node_1");';
$js[] = '      jQuery.jstree.reference("#'.$treeId.'").select_node("child_node_1");';
$js[] = '   });';
$js[] = '});';
$js[] = ' ';

//$document->addScriptDeclaration(implode("\n", $js));
$app = Factory::getApplication();
$wa  = $app->getDocument()->getWebAssetManager();
$wa->addInlineScript(implode("\n",$js));

require(ModuleHelper::getLayoutPath('mod_phocadownload_tree'));
?>
