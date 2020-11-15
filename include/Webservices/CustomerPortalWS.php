<?php
/*************************************************************************************************
 * Copyright 2012-2014 JPL TSolucio, S.L.  --  This file is a part of coreBOSCP.
* You can copy, adapt and distribute the work under the "Attribution-NonCommercial-ShareAlike"
* Vizsage Public License (the "License"). You may not use this file except in compliance with the
* License. Roughly speaking, non-commercial users may share and modify this code, but must give credit
* and share improvements. However, for proper details please read the full License, available at
* http://vizsage.com/license/Vizsage-License-BY-NC-SA.html and the handy reference for understanding
* the full license at http://vizsage.com/license/Vizsage-Deed-BY-NC-SA.html. Unless required by
* applicable law or agreed to in writing, any software distributed under the License is distributed
* on an  "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
* See the License for the specific language governing permissions and limitations under the
* License terms of Creative Commons Attribution-NonCommercial-ShareAlike 3.0 (the License).
*************************************************************************************************/

function vtyiicpng_getWSEntityId($entityName, $user = '') {
	global $adb;
	$wsrs=$adb->pquery('select id from vtiger_ws_entity where name=?', array($entityName));
	if ($wsrs && $adb->num_rows($wsrs)==1) {
		$wsid = $adb->query_result($wsrs, 0, 0);
	} else {
		$wsid = 0;
	}
	return $wsid.'x';
}

function evvt_strip_html_links($text) {
	$text = preg_replace('/<a [^>]*?>/', '', $text);
	$text=str_replace('</a>', '', $text);
	return $text;
}

function vtws_changePortalUserPassword($email, $newPass, $user = '') {
	global $adb,$log;
	$log->debug('>< changePortalUserPassword');
	$nra = $adb->pquery('update vtiger_portalinfo set user_password=? where user_name=?', array($newPass,$email));
	return ($nra && $adb->getAffectedRowCount($nra) == 1);
}

function vtws_findByPortalUserName($username, $user = '') {
	global $adb,$log;
	$log->debug('>< vtws_findByPortalUserName');
	$rs = $adb->pquery('select count(*) from vtiger_portalinfo where isactive=1 and user_name=?', array($username));
	$nra=$adb->query_result($rs, 0, 0);
	return !empty($nra);
}

function vtws_sendRecoverPassword($username, $user = '') {
	global $adb,$log,$current_user;
	$log->debug('> vtws_sendRecoverPassword');

	$ctors=$adb->pquery('select contactid,email,user_password
			from vtiger_contactdetails
			inner join vtiger_portalinfo on id=contactid
			where isactive=1 and user_name=?', array($username));
	if (!$ctors || $adb->num_rows($ctors)==0) {
		throw new WebServiceException(WebServiceErrorCode::$INVALIDUSERPWD, 'Invalid username: username not found or not active');
	}
	require_once 'modules/Emails/mail.php';
	require_once 'modules/Contacts/Contacts.php';
	$cto=$adb->fetch_array($ctors);
	$password = $cto['user_password'];
	$entityData = VTEntityData::fromEntityId($adb, $cto['contactid']);
	$contents = Contacts::getPortalEmailContents($entityData, $password);
	$subject = getTranslatedString('Customer Portal Login Details', 'Contacts');
	$mail_status = send_mail('Contacts', $cto['email'], $current_user->user_name, '', $subject, $contents);

	$log->debug('< vtws_sendRecoverPassword');
	return $mail_status;
}

function vtws_getPortalUserDateFormat($user) {
	if (isset($user->column_fields['date_format']) && !empty($user->column_fields['date_format'])) {
		return $user->column_fields['date_format'];
	} else {
		return 'yyyy-mm-dd';
	}
}

function vtws_getPortalUserInfo($user) {
	$usrinfo = array();
	$retfields = array(
		'date_format','first_name','last_name','email1','is_admin','roleid','language',
		'currency_grouping_pattern','currency_decimal_separator','currency_grouping_separator','currency_symbol_placement',
	);
	foreach ($retfields as $fld) {
		if (isset($user->column_fields[$fld])) {
			$usrinfo[$fld] = $user->column_fields[$fld];
			if ($fld=='roleid') {
				$usrinfo['rolename'] = getRoleName($user->column_fields[$fld]);
			}
		}
	}
	$usrinfo['id'] = vtws_getEntityId('Users').'x'.$user->id;
	return $usrinfo;
}

function vtws_getAllUsers($user = '') {
	global $log;
	$log->debug('> vtws_getAllUsers');

	$usrwsid = vtyiicpng_getWSEntityId('Users');
	$usrs = getAllUserName();
	$usr_array = array();
	foreach ($usrs as $id => $usr) {
		$usr_array[$usrwsid.$id] = $usr;
	}

	$log->debug('< vtws_getAllUsers');
	return $usr_array;
}

function vtws_getAssignedUserList($module, $user) {
	global $log, $default_charset;
	$log->debug('> getAssignedUserList '.$module);
	$userprivs = $user->getPrivileges();
	$tabid=getTabid($module);
	if (!$userprivs->hasGlobalWritePermission() && !$userprivs->hasModuleWriteSharing($tabid)) {
		$users = get_user_array(false, 'Active', $user->id, 'private');
	} else {
		$users = get_user_array(false, 'Active', $user->id);
	}
	$usrwsid = vtyiicpng_getWSEntityId('Users');
	$usrinfo = array();
	foreach ($users as $id => $usr) {
		$usrinfo[] = array('userid' => $usrwsid.$id,'username'=> trim(html_entity_decode($usr, ENT_QUOTES, $default_charset)));
	}
	return json_encode($usrinfo);
}

function vtws_getAssignedGroupList($module, $user) {
	global $log, $default_charset;
	$log->debug('> vtws_getAssignedGroupList '.$module);
	$userPrivs = $user->getPrivileges();

	$tabid=getTabid($module);
	if (!$userPrivs->hasGlobalWritePermission() && !$userPrivs->hasModuleWriteSharing($tabid)) {
		$users = get_group_array(false, 'Active', $user->id, 'private');
	} else {
		$users = get_group_array(false, 'Active', $user->id);
	}
	$usrwsid = vtyiicpng_getWSEntityId('Groups');
	$usrinfo = array();
	foreach ($users as $id => $usr) {
		$usrinfo[] = array('groupid' => $usrwsid.$id,'groupname'=> trim(html_entity_decode($usr, ENT_QUOTES, $default_charset)));
	}
	return json_encode($usrinfo);
}

function vtws_AuthenticateContact($email, $password, $user = '') {
	global $adb,$log;
	$log->debug('> AuthenticateContact '.$email.','.$password);

	$rs = $adb->pquery('select id
		from vtiger_portalinfo
		inner join vtiger_customerdetails on vtiger_portalinfo.id=vtiger_customerdetails.customerid
		inner join vtiger_crmentity on vtiger_crmentity.crmid=vtiger_portalinfo.id
		where vtiger_crmentity.deleted=0 and user_name=? and user_password=?
		 and isactive=1 and vtiger_customerdetails.portal=1', array($email, $password));
	if ($rs && $adb->num_rows($rs)>0 && !empty($rs->fields['id'])) {
		return vtyiicpng_getWSEntityId('Contacts').$rs->fields['id'];
	} else {
		return false;
	}
}

function vtws_getPicklistValues($fld_module, $user = '') {
	global $adb,$log;
	include_once 'modules/PickList/PickListUtils.php';
	$log->debug('> getPicklistValues '.$fld_module);
	$res=array();
	$allpicklists=getUserFldArray($fld_module, 'H1');
	foreach ($allpicklists as $picklist) {
		$res[$picklist['fieldname']]=$picklist['value'];
	}
	if ($fld_module == 'Documents') {
		$folders=array();
		$result=$adb->query('select folderid,foldername from vtiger_attachmentsfolder');
		$number=$adb->num_rows($result);
		$DocumentFoldersWSID=vtyiicpng_getWSEntityId('DocumentFolders');
		for ($i=0; $i<$number; $i++) {
			$folderid=$DocumentFoldersWSID.$adb->query_result($result, $i, 0);
			$foldername=$adb->query_result($result, $i, 1);
			$folders[$folderid]=$foldername;
		}
		$res['folderid']=$folders;
	}
	return serialize($res);
}

function vtws_getUItype($module, $user) {
	global $adb,$log;
	$log->debug('> getUItype '.$module);
	$res=$adb->pquery('select uitype,fieldname from vtiger_field where tabid=? and presence in (0,2)', array(getTabid($module)));
	$nr=$adb->num_rows($res);
	$resp=array();
	for ($i=0; $i<$nr; $i++) {
		$fieldname=$adb->query_result($res, $i, 'fieldname');
		$resp[$fieldname]=$adb->query_result($res, $i, 'uitype');
	}
	return $resp;
}

function vtws_getReferenceValue($strids, $user) {
	global $log, $adb, $default_charset;
	$log->debug('> vtws_getReferenceValue '.$strids);
	$ids=unserialize($strids);
	if ($ids===false) {
		$ids = array();
	}
	$result = array();
	foreach ($ids as $idref) {
		if (strpos($idref, '|')>0) {
			$idref = explode('|', trim($idref, '|'));
		}
		foreach ((array) $idref as $id) {
			list($wsid,$realid)=explode('x', $id);
			$rs = $adb->pquery('select name from vtiger_ws_entity where id=?', array($wsid));
			$modulename = $adb->query_result($rs, 0, 0);
			if ($modulename=='DocumentFolders') {
				$rs1 = $adb->pquery('select foldername from vtiger_attachmentsfolder where folderid=?', array($realid));
				$result[$id]=array(
					'module'=>$modulename,
					'reference'=>html_entity_decode($adb->query_result($rs1, 0, 0), ENT_QUOTES, $default_charset),
					'cbuuid' => '',
				);
			} elseif ($modulename=='Groups') {
				$rs1 = $adb->pquery('select groupname from vtiger_groups where groupid=?', array($realid));
				$result[$id]=array(
					'module'=>$modulename,
					'reference'=>$adb->query_result($rs1, 0, 0),
					'cbuuid' => '',
				);
			} else {
				$cbuuid = '';
				if ($modulename == 'Currency') {
					$entityinfo[$realid] = getCurrencyName($realid, true);
				} else {
					$entityinfo = getEntityName($modulename, $realid);
					if (isset($entityinfo[$realid])) {
						$entityinfo[$realid] = html_entity_decode($entityinfo[$realid], ENT_QUOTES, $default_charset);
						if ($modulename != 'Users') {
							$cbuuid = CRMEntity::getUUIDfromCRMID($realid);
						}
					}
				}
				if (empty($entityinfo[$realid])) {
					$entityinfo[$realid] = '';
				}
				$result[$id]=array(
					'module'=>$modulename,
					'reference'=>$entityinfo[$realid],
					'cbuuid' => $cbuuid,
				);
			}
		}
	}
	$log->debug('< vtws_getReferenceValue');
	return serialize($result);
}

/**
 * launch a global search in the application
 * @param string $query contains the search term we are looking for
 * @param string $search_onlyin comma separated list of modules to search in
 * @param array $restrictionids contains the user we are to search as and the account and contact restrictions
 * @return array with the results and total number of records per module
 * @example {'query':'che', 'search_onlyin':'Accounts,Contacts', 'restrictionids': JSON.stringify({'userId': '19x1', 'accountId':'11x74', 'contactId':'12x1084'}) })
 * @example {'query':'che', 'search_onlyin':'Accounts,Contacts', 'restrictionids': JSON.stringify({'userId': '19x1', 'accountId':'11x0', 'contactId':'12x0'}) })
 */
$cbwsgetSearchResultsTotals = array();
function cbwsgetSearchResultsWithTotals($query, $search_onlyin, $restrictionids, $user) {
	global $cbwsgetSearchResultsTotals;
	return array(
		'records' => cbwsgetSearchResults($query, $search_onlyin, $restrictionids, $user),
		'totals' => $cbwsgetSearchResultsTotals,
	);
}

/**
 * launch a global search in the application
 * @param string $query contains the search term we are looking for
 * @param string $search_onlyin comma separated list of modules to search in
 * @param array $restrictionids contains the user we are to search as and the account and contact restrictions
 * @return array with the results
 * @example {'query':'che', 'search_onlyin':'Accounts,Contacts', 'restrictionids': JSON.stringify({'userId': '19x1', 'accountId':'11x74', 'contactId':'12x1084'}) })
 * @example {'query':'che', 'search_onlyin':'Accounts,Contacts', 'restrictionids': JSON.stringify({'userId': '19x1', 'accountId':'11x0', 'contactId':'12x0'}) })
 */
function cbwsgetSearchResults($query, $search_onlyin, $restrictionids, $user) {
	global $adb,$current_user, $cbwsgetSearchResultsTotals;
	$res=array();
	// security restrictions
	if (empty($query) || empty($restrictionids) || !is_array($restrictionids)) {
		return $res;
	}
	if (empty($restrictionids['userId']) || empty($restrictionids['accountId']) || empty($restrictionids['contactId'])) {
		return $res;
	}
	list($void,$accountId) = explode('x', $restrictionids['accountId']);
	list($void,$contactId) = explode('x', $restrictionids['contactId']);
	list($void,$userId) = explode('x', $restrictionids['userId']);
	$limit = (isset($restrictionids['limit']) ? $restrictionids['limit'] : 0);
	// if connected user does not have admin privileges > user must be the connected user
	if ($user->is_admin!='on' && $user->id!=$userId) {
		return $res;
	}
	$newUser = new Users();
	$newUser->retrieveCurrentUserInfoFromFile($userId);
	$current_user = $newUser;
	// connected user must have access to account and contact > this will be restricted by the coreBOS system and the rest of the code
	// start work
	require_once 'modules/CustomView/CustomView.php';
	require_once 'include/utils/utils.php';
	// Was the search limited by user for specific modules?
	$search_onlyin = (empty($search_onlyin) ? array() : explode(',', $search_onlyin));
	$search_onlyin = array_filter(array_unique($search_onlyin), function ($var) {
		return !empty(trim($var));
	});
	$accessModules = vtws_listtypes('', $user); // filter modules user does not have access to
	$object_array = array_intersect(getSearchModules($search_onlyin), $accessModules['types']);
	$total_record_count = 0;
	$i = 0;
	$j=0;
	$cbwsgetSearchResultsTotals = array();
	$moduleRecordCount = array();
	foreach ($object_array as $module => $object_name) {
		$focus = CRMEntity::getInstance($module);
		$listquery = getListQuery($module);
		$oCustomView = new CustomView($module);
		//Instead of getting current customview id, use cvid of All so that all entities will be found
		$cv_res = $adb->pquery("select cvid from vtiger_customview where viewname='All' and entitytype=?", array($module));
		$viewid = $adb->query_result($cv_res, 0, 'cvid');
		$listquery = $oCustomView->getModifiedCvListQuery($viewid, $listquery, $module);
		if (!empty($accountId) && !empty($contactId)) {
			switch ($module) {
				case 'Documents':
					$listquery = str_replace(
						' WHERE ',
						" inner join vtiger_senotesrel on vtiger_senotesrel.notesid=vtiger_notes.notesid and (vtiger_senotesrel.crmid=$accountId or vtiger_senotesrel.crmid=$contactId) WHERE ",
						$listquery
					);
					break;
				default:
					break;
			}
		}
		$bmapname = $module.'_ListColumns';
		$cbMapid = GlobalVariable::getVariable('BusinessMapping_'.$bmapname, cbMap::getMapIdByName($bmapname));
		if ($cbMapid) {
			$cbMap = cbMap::getMapByID($cbMapid);
			$cbMapLC = $cbMap->ListColumns();
			$parentmodule = 'Home';
			$focus->list_fields = $cbMapLC->getListFieldsFor($parentmodule);
			$focus->list_fields_name = $cbMapLC->getListFieldsNameFor($parentmodule);
			$focus->list_link_field = $cbMapLC->getListLinkFor($parentmodule);
			$oCustomView->list_fields = $focus->list_fields;
			$oCustomView->list_fields_name = $focus->list_fields_name;
		}
		if ($oCustomView) {
			if (isset($oCustomView->list_fields)) {
				$focus->list_fields = $oCustomView->list_fields;
				$focus->list_fields_name = $oCustomView->list_fields_name;
			}
		}

		// Remove fields which are made inactive
		$focus->filterInactiveFields($module);

		$field_list = '';
		foreach ($focus->list_fields as $tableinfo) {
			foreach ($tableinfo as $tbl => $col) {
				if (!empty($tbl) && !empty($col)) {
					$field_list .= (substr($tbl, 0, 7)=='vtiger_' ? '' : 'vtiger_').$tbl.'.'.$col.',';
				}
			}
		}
		$field_list .= 'vtiger_crmentity.crmid';

		$listquery = 'select '.$field_list.substr($listquery, stripos($listquery, ' from '));

		$where = getUnifiedWhere($listquery, $module, $query);
		if ($where != '') {
			$listquery .= ' and ('.$where.')';
		}
		if (!empty($accountId) && !empty($contactId)) {
			$cond = evvt_PortalModuleRestrictions($module, $accountId, $contactId);
			if ($cond != '') {
				$listquery .= ' and ('.$cond.')';
			}
		}
		if ($limit > 0) {
			$listquery = $listquery.' limit '.$limit;
		}
		$list_result = $adb->query($listquery);
		$noofrows = $adb->num_rows($list_result);
		if ($noofrows>0) {
			$count_result = $adb->query(mkCountQuery($listquery));
			$cbwsgetSearchResultsTotals[$module] = (int) $count_result->fields['count'];
		} else {
			$cbwsgetSearchResultsTotals[$module] = 0;
		}
		$moduleRecordCount[$module]['count'] = $noofrows;
		$navigation_array = VT_getSimpleNavigationValues(1, ($limit>0 ? $limit : 100), $noofrows);
		$listview_entries = getSearchingListViewEntries($focus, $module, $list_result, $navigation_array, '', '', '', '', $oCustomView, '', '', '', true);
		$total_record_count = $total_record_count + $noofrows;
		if (!empty($listview_entries)) {
			foreach ($listview_entries as $element) {
				$res[$j]=$element;
				$j++;
			}
		}
		$i++;
	}
	if ($limit > 0 && count($res)>$limit) {
		shuffle($res);
		$res = array_slice($res, 0, $limit);
	}
	$current_user = $user;
	return $res;
}

/**
 * launch a global search in the application. use cbwsgetSearchResults instead of this function
 * @see cbwsgetSearchResults
 * @param string $query contains the search term we are looking for
 * @param string $search_onlyin comma separated list of modules to search in
 * @param array $restrictionids contains the user we are to search as and the account and contact restrictions
 * @return string php serialized array with the results
 * @example {'query':'che', 'search_onlyin':'Accounts,Contacts', 'restrictionids': JSON.stringify({'userId': '19x1', 'accountId':'11x74', 'contactId':'12x1084'}) })
 * @example {'query':'che', 'search_onlyin':'Accounts,Contacts', 'restrictionids': JSON.stringify({'userId': '19x1', 'accountId':'11x0', 'contactId':'12x0'}) })
 */
function vtws_getSearchResults($query, $search_onlyin, $restrictionids, $user) {
	return serialize(cbwsgetSearchResults($query, $search_onlyin, $restrictionids, $user));
}

function evvt_PortalModuleRestrictions($module, $accountId, $contactId) {
	$condition = '';
	switch ($module) {
		case 'Contacts':
			$condition = "vtiger_contactdetails.accountid=$accountId";
			break;
		case 'Accounts':
			$condition = "vtiger_account.accountid=$accountId";
			break;
		case 'Quotes':
			$condition = "vtiger_quotes.accountid=$accountId or vtiger_quotes.contactid=$contactId";
			break;
		case 'SalesOrder':
			$condition = "vtiger_salesorder.accountid=$accountId or vtiger_salesorder.contactid=$contactId";
			break;
		case 'ServiceContracts':
			$condition = "vtiger_servicecontracts.sc_related_to=$accountId or vtiger_servicecontracts.sc_related_to=$contactId";
			break;
		case 'Invoice':
			$condition = "vtiger_invoice.accountid=$accountId or vtiger_invoice.contactid=$contactId";
			break;
		case 'HelpDesk':
			$condition = "vtiger_troubletickets.parent_id=$accountId or vtiger_troubletickets.parent_id=$contactId";
			break;
		case 'Assets':
			$condition = "vtiger_assets.account=$accountId";
			break;
		case 'Project':
			$condition = "vtiger_project.linktoaccountscontacts=$accountId or vtiger_project.linktoaccountscontacts=$contactId";
			break;
		case 'Products':
			//$condition = "related.Contacts='".$contactId."'";
			break;
		case 'Services':
			//$condition = "related.Contacts='".$contactId."'";
			break;
		case 'Faq':
			$condition = "faqstatus='Published'";
			break;
		case 'Documents':
			// already added in main search function
			break;
		default:
			$condition = '';
	}
	return $condition;
}

// To get the modules allowed for global search
if (!function_exists('getSearchModules')) {
	function getSearchModules($filter = array()) {
		return getSearchModulesCommon($filter);
	}
}

function getSearchingListViewEntries($focus, $module, $list_result, $navigation_array, $relatedlist = '', $returnset = '', $edit_action = 'EditView', $del_action = 'Delete', $oCv = '', $page = '', $selectedfields = '', $contRelatedfields = '', $skipActions = false, $linksallowed = false) {
	global $log, $adb, $current_user, $theme;
	$log->debug('> getSearchingListViewEntries in CPWS '.get_class($focus).','. $module);
	$noofrows = $adb->num_rows($list_result);
	$list_block = array();
	//getting the field table entries from database
	$tabid = getTabid($module);

	//Added to reduce the no. of queries logging for non-admin user
	$field_list = array();
	$userprivs = $current_user->getPrivileges();
	foreach ($focus->list_fields as $name => $tableinfo) {
		$fieldname = $focus->list_fields_name[$name];
		if ($oCv) {
			if (isset($oCv->list_fields_name)) {
				$fieldname = $oCv->list_fields_name[$name];
			}
		}
		if ($fieldname == 'accountname' && $module != 'Accounts') {
			$fieldname = 'account_id';
		}
		if ($fieldname == 'lastname' && ($module == 'SalesOrder' || $module == 'PurchaseOrder' || $module == 'Invoice' || $module == 'Quotes')) {
			$fieldname = 'contact_id';
		}
		if ($fieldname == 'productname' && $module != 'Products') {
			$fieldname = 'product_id';
		}
		$field_list[] = $fieldname;
	}
	$field = array();
	if (!is_admin($current_user)) {
		if ($module == 'Emails') {
			$query = 'SELECT fieldname FROM vtiger_field WHERE tabid = ? and vtiger_field.presence in (0,2)';
			$params = array($tabid);
		} else {
			$profileList = getCurrentUserProfileList();
			$query = 'SELECT DISTINCT vtiger_field.fieldname
				FROM vtiger_field
				INNER JOIN vtiger_profile2field ON vtiger_profile2field.fieldid = vtiger_field.fieldid
				INNER JOIN vtiger_def_org_field ON vtiger_def_org_field.fieldid = vtiger_field.fieldid
				WHERE vtiger_field.tabid=? and vtiger_field.presence in (0,2)
				AND vtiger_profile2field.visible = 0
				AND vtiger_profile2field.visible = 0
				AND vtiger_def_org_field.visible = 0
				AND vtiger_profile2field.profileid IN ('. generateQuestionMarks($profileList) .')
				AND vtiger_field.fieldname IN ('. generateQuestionMarks($field_list) .')';
			$params = array($tabid, $profileList, $field_list);
		}

		$result = $adb->pquery($query, $params);
		for ($k = 0; $k < $adb->num_rows($result); $k++) {
			$field[] = $adb->query_result($result, $k, 'fieldname');
		}
	}
	//constructing the uitype and columnname array
	$ui_col_array = array();

	$query = 'SELECT uitype, columnname, fieldname, typeofdata
		FROM vtiger_field
		WHERE vtiger_field.tabid=? and vtiger_field.presence in (0,2) AND fieldname IN ('. generateQuestionMarks($field_list).') ';
	$params = array($tabid, $field_list);
	$result = $adb->pquery($query, $params);
	$num_rows = $adb->num_rows($result);
	for ($i = 0; $i < $num_rows; $i++) {
		$tempArr = array();
		$uitype = $adb->query_result($result, $i, 'uitype');
		$columnname = $adb->query_result($result, $i, 'columnname');
		$field_name = $adb->query_result($result, $i, 'fieldname');
		$typeofdata = $adb->query_result($result, $i, 'typeofdata');
		$tempArr[$uitype] = $columnname;
		$tempArr['typeofdata'] = $typeofdata;
		$ui_col_array[$field_name] = $tempArr;
	}

	if ($navigation_array['start'] !=0) {
		for ($i = 0; $i < $noofrows; $i++) {
			$list_header = array();
			//Getting the entityid
			if ($module != 'Users') {
				$entity_id = $adb->query_result($list_result, $i, 'crmid');
				//$owner_id = $adb->query_result($list_result, $i, 'smownerid');
			} else {
				$entity_id = $adb->query_result($list_result, $i, 'id');
			}
			foreach ($focus->list_fields as $name => $tableinfo) {
				$fieldname = $focus->list_fields_name[$name];
				if ($oCv) {
					if (isset($oCv->list_fields_name)) {
						$fieldname = $oCv->list_fields_name[$name];
						if ($fieldname == 'accountname' && $module != 'Accounts') {
							$fieldname = 'account_id';
						}
						if ($fieldname=='lastname' && ($module=='SalesOrder' || $module=='PurchaseOrder' || $module=='Invoice' || $module=='Quotes')) {
							$fieldname = 'contact_id';
						}
						if ($fieldname == 'productname' && $module != 'Products') {
							$fieldname = 'product_id';
						}
					} else {
						$fieldname = $focus->list_fields_name[$name];
					}
				} elseif (isset($focus->list_fields_name[$name])) {
					$fieldname = $focus->list_fields_name[$name];
					if ($fieldname == 'accountname' && $module != 'Accounts') {
						$fieldname = 'account_id';
					}
					if ($fieldname == 'lastname' && ($module == 'SalesOrder' || $module == 'PurchaseOrder' || $module == 'Invoice' || $module == 'Quotes')) {
						$fieldname = 'contact_id';
					}
					if ($fieldname == 'productname' && $module != 'Products') {
						$fieldname = 'product_id';
					}
				} else {
					continue;
				}
				if ($userprivs->hasGlobalReadPermission() || in_array($fieldname, $field) || $fieldname == '') {
					if ($fieldname == '') {
						//$table_name = '';
						$column_name = '';
						foreach ($tableinfo as $colname) {
							//$table_name = $tablename;
							$column_name = $colname;
						}
						$value = $adb->query_result($list_result, $i, $column_name);
					} else {
						if ($module == 'Calendar') {
							$act_id = $adb->query_result($list_result, $i, 'activityid');

							$cal_sql = 'select activitytype from vtiger_activity where activityid=?';
							$cal_res = $adb->pquery($cal_sql, array($act_id));
							if ($adb->num_rows($cal_res)>=0) {
								$activitytype = $adb->query_result($cal_res, 0, 'activitytype');
							}
						}
						if (($module=='Emails' || $module=='HelpDesk' || $module=='Invoice' || $module=='Leads' || $module=='Contacts')
							&& (($fieldname=='parent_id') || ($name=='Contact Name') || ($fieldname == 'firstname'))
						) {
							if ($module == 'Calendar') {
								if ($fieldname == 'status') {
									if ($activitytype == 'Task') {
										$fieldname = 'taskstatus';
									} else {
										$fieldname = 'eventstatus';
									}
								}
								if ($activitytype == 'Task') {
									if (getFieldVisibilityPermission('Calendar', $current_user->id, $fieldname) == '0') {
										$has_permission = 'yes';
									} else {
										$has_permission = 'no';
									}
								} else {
									if (getFieldVisibilityPermission('Events', $current_user->id, $fieldname) == '0') {
										$has_permission = 'yes';
									} else {
										$has_permission = 'no';
									}
								}
							}
							if ($module != 'Calendar' || ($module == 'Calendar' && $has_permission == 'yes')) {
								if ($fieldname == 'parent_id') {
									$value = getRelatedTo($module, $list_result, $i);
								}
								if ($name == 'Contact Name') {
									$contact_id = $adb->query_result($list_result, $i, 'contactid');
									$contact_name = getFullNameFromQResult($list_result, $i, 'Contacts');
									$value = '';
									//Added to get the contactname for activities custom view - t=2190
									if ($contact_id != '' && !empty($contact_name)) {
										$contact_name = getContactName($contact_id);
									}
									if (($contact_name != '') && ($contact_id !='NULL')) {
										$value = $contact_name;
									}
								}
								if ($fieldname == 'firstname') {
									$first_name = textlength_check($adb->query_result($list_result, $i, 'firstname'));
									$value = $first_name;
								}
							} else {
								$value = '';
							}
						} elseif ($module=='Documents'
							&& ($fieldname=='filelocationtype' || $fieldname=='filename' || $fieldname=='filesize' || $fieldname=='filestatus' || $fieldname=='filetype')
						) {
							$value = $adb->query_result($list_result, $i, $fieldname);
							if ($fieldname == 'filelocationtype') {
								if ($value == 'I') {
									$value = getTranslatedString('LBL_INTERNAL', $module);
								} elseif ($value == 'E') {
									$value = getTranslatedString('LBL_EXTERNAL', $module);
								} else {
									$value = ' --';
								}
							}
							if ($fieldname == 'filename') {
								$downloadtype = $adb->query_result($list_result, $i, 'filelocationtype');
								if ($downloadtype == 'I') {
									$fld_value = $value;
									$ext_pos = strrpos($fld_value, '.');
									$ext = substr($fld_value, $ext_pos + 1);
									$ext = strtolower($ext);
									if ($value != '') {
										if ($ext == 'bin' || $ext == 'exe' || $ext == 'rpm') {
											$fileicon = "<img src='" . vtiger_imageurl('fExeBin.gif', $theme) . "' hspace='3' align='absmiddle' border='0'>";
										} elseif ($ext == 'jpg' || $ext == 'gif' || $ext == 'bmp') {
											$fileicon = "<img src='" . vtiger_imageurl('fbImageFile.gif', $theme) . "' hspace='3' align='absmiddle' border='0'>";
										} elseif ($ext == 'txt' || $ext == 'doc' || $ext == 'xls') {
											$fileicon = "<img src='" . vtiger_imageurl('fbTextFile.gif', $theme) . "' hspace='3' align='absmiddle' border='0'>";
										} elseif ($ext == 'zip' || $ext == 'gz' || $ext == 'rar') {
											$fileicon = "<img src='" . vtiger_imageurl('fbZipFile.gif', $theme) . "' hspace='3' align='absmiddle'	border='0'>";
										} else {
											$fileicon = "<img src='" . vtiger_imageurl('fbUnknownFile.gif', $theme) . "' hspace='3' align='absmiddle' border='0'>";
										}
									}
								} elseif ($downloadtype == 'E') {
									if (trim($value) != '') {
										$fld_value = $value;
										$fileicon = "<img src='" . vtiger_imageurl('fbLink.gif', $theme) . "' alt='" . getTranslatedString('LBL_EXTERNAL_LNK', $module)
											."' title='" . getTranslatedString('LBL_EXTERNAL_LNK', $module) . "' hspace='3' align='absmiddle' border='0'>";
									} else {
										$fld_value = '--';
										$fileicon = '';
									}
								} else {
									$fld_value = ' --';
									$fileicon = '';
								}

								$file_name = $adb->query_result($list_result, $i, 'filename');
								$notes_id = $adb->query_result($list_result, $i, 'crmid');
								//$folder_id = $adb->query_result($list_result, $i, 'folderid');
								$download_type = $adb->query_result($list_result, $i, 'filelocationtype');
								$file_status = $adb->query_result($list_result, $i, 'filestatus');
								$fileidQuery = 'select attachmentsid from vtiger_seattachmentsrel where crmid=?';
								$fileidres = $adb->pquery($fileidQuery, array($notes_id));
								$fileid = $adb->query_result($fileidres, 0, 'attachmentsid');
								if ($file_name != '' && $file_status == 1) {
									if ($download_type == 'I') {
										$fld_value = "<a href='index.php?module=Utilities&action=UtilitiesAjax&file=ExecuteFunctions&functiontocall=downloadfile&entityid=$notes_id&fileid=$fileid' title='"
											.getTranslatedString('LBL_DOWNLOAD_FILE', $module) . "' onclick='javascript:dldCntIncrease($notes_id);'>"
											.textlength_check($fld_value) . '</a>';
									} elseif ($download_type == 'E') {
										$fld_value = "<a target='_blank' href='$file_name' onclick='javascript:dldCntIncrease($notes_id);' title='"
											.getTranslatedString('LBL_DOWNLOAD_FILE', $module) . "'>" . textlength_check($fld_value) . '</a>';
									} else {
										$fld_value = ' --';
									}
								}
								$value = $fileicon . $fld_value;
							}
							if ($fieldname == 'filesize') {
								$downloadtype = $adb->query_result($list_result, $i, 'filelocationtype');
								if ($downloadtype == 'I') {
									$filesize = $value;
									if ($filesize < 1024) {
										$value = $filesize . ' B';
									} elseif ($filesize > 1024 && $filesize < 1048576) {
										$value = round($filesize / 1024, 2) . ' KB';
									} elseif ($filesize > 1048576) {
										$value = round($filesize / (1024 * 1024), 2) . ' MB';
									}
								} else {
									$value = ' --';
								}
							}
							if ($fieldname == 'filestatus') {
								$filestatus = $value;
								if ($filestatus == 1) {
									$value = getTranslatedString('yes', $module);
								} elseif ($filestatus == 0) {
									$value = getTranslatedString('no', $module);
								} else {
									$value = ' --';
								}
							}
							if ($fieldname == 'filetype') {
								$downloadtype = $adb->query_result($list_result, $i, 'filelocationtype');
								$filetype = $adb->query_result($list_result, $i, 'filetype');
								if ($downloadtype == 'E' || $downloadtype != 'I') {
									$value = ' --';
								} else {
									$value = $filetype;
								}
							}
							if ($fieldname == 'notecontent') {
								$value = decode_html($value);
								$value = textlength_check($value);
							}
						} elseif ($module == 'Products' && $name == 'Related to') {
							$value = getRelatedTo($module, $list_result, $i);
						} elseif ($name == 'Contact Name' && ($module == 'SalesOrder' || $module == 'Quotes' || $module == 'PurchaseOrder')) {
							if ($name == 'Contact Name') {
								$contact_id = $adb->query_result($list_result, $i, 'contactid');
								$contact_name = getFullNameFromQResult($list_result, $i, 'Contacts');
								$value = '';
								if (($contact_name != '') && ($contact_id != 'NULL')) {
									$value = $contact_name;
								}
							}
						} elseif ($name == 'Product') {
							$product_id = textlength_check($adb->query_result($list_result, $i, 'productname'));
							$value = $product_id;
						} elseif ($name == 'Account Name') {
							if ($module == 'Accounts') {
								$account_id = $adb->query_result($list_result, $i, 'crmid');
								//$account_name = getAccountName($account_id);
								$account_name = textlength_check($adb->query_result($list_result, $i, 'accountname'));
								$value = $account_name;
							} elseif ($module == 'Potentials' || $module == 'Contacts' || $module == 'Invoice' || $module == 'SalesOrder' || $module == 'Quotes') {
								//Potential,Contacts,Invoice,SalesOrder & Quotes records sort by Account Name
								//$accountname = textlength_check($adb->query_result($list_result,$i,'accountname'));
								$accountid = $adb->query_result($list_result, $i, 'accountid');
								$accountname = textlength_check(getAccountName($accountid));
								$value = $accountname;
							} else {
								$account_id = $adb->query_result($list_result, $i, 'accountid');
								$account_name = getAccountName($account_id);
								$acc_name = textlength_check($account_name);
								$value = $acc_name;
							}
						} elseif (($module=='HelpDesk' || $module=='PriceBook' || $module=='Quotes' || $module=='PurchaseOrder' || $module=='Faq') && $name=='Product Name') {
							if ($module == 'HelpDesk' || $module == 'Faq') {
								$product_id = $adb->query_result($list_result, $i, 'product_id');
							} else {
								$product_id = $adb->query_result($list_result, $i, 'productid');
							}
							if ($product_id != '') {
								$product_name = getProductName($product_id);
							} else {
								$product_name = '';
							}
							$value = textlength_check($product_name);
						} elseif (($module == 'Quotes' && $name == 'Potential Name') || ($module == 'SalesOrder' && $name == 'Potential Name')) {
							$potential_id = $adb->query_result($list_result, $i, 'potentialid');
							$potential_name = getPotentialName($potential_id);
							$value = textlength_check($potential_name);
						} elseif ($module == 'Emails' && $relatedlist != '' && ($name == 'Subject' || $name == 'Date Sent' || $name == 'To')) {
							$tmp_value = getValue($ui_col_array, $list_result, $fieldname, $focus, $module, $entity_id, $i, 'list', '');
							$tmp_value = evvt_strip_html_links($tmp_value);
							$value = textlength_check($tmp_value);
							if ($name == 'Date Sent') {
								$sql='select email_flag from vtiger_emaildetails where emailid=?';
								$result=$adb->pquery($sql, array($entity_id));
								$email_flag=$adb->query_result($result, 0, 'email_flag');
								if ($email_flag != 'SAVED') {
									$value = getValue($ui_col_array, $list_result, $fieldname, $focus, $module, $entity_id, $i, 'list', '');
									$value = evvt_strip_html_links($value);
								} else {
									$value = '';
								}
							}
						} else {
							$value = getValue($ui_col_array, $list_result, $fieldname, $focus, $module, $entity_id, $i, 'list', '');
							$value = evvt_strip_html_links(strip_tags($value));
						}
					}
					$list_header[$name] = $value;
				}
			}
			$webserviceEntityId=vtyiicpng_getWSEntityId($module);
			$list_header['id']=$webserviceEntityId.$entity_id;
			$list_header['search_module_name']=$module;
			$list_block[$entity_id] = $list_header;
		}
	}
	$log->debug('< getSearchingListViewEntries CPWS');
	return $list_block;
}

function getReferenceAutocomplete($term, $filter, $searchinmodules, $limit, $user) {
	global $adb,$default_charset;

	if (!empty($searchinmodules)) {
		$searchin = explode(',', $searchinmodules);
	} else {
		$searchin = array('HelpDesk','Project','ProjectTask','Potentials','ProjectMilestone',
		'Invoice','PurchaseOrder','Quotes','SalesOrder','ServiceContracts','Accounts','Contacts');
	}
	if (empty($limit)) {
		$limit = 30;  // hard coded default
	}
	$respuesta=array();

	if (empty($term)) {
		$term='%';
		$op='like';
	} else {
		switch ($filter) {
			case 'eq':
				$op='=';
				break;
			case 'neq':
				$op='!=';
				break;
			case 'startswith':
				$term=$term.'%';
				$op='like';
				break;
			case 'endswith':
				$term='%'.$term;
				$op='like';
				break;
			case 'contains':
				$op='like';
				$term='%'.$term.'%';
				break;
			default:
				$op='=';
				break;
		}
	}

	$num_search_modules = count($searchin);
	foreach ($searchin as $srchmod) {
		if (!(vtlib_isModuleActive($srchmod) && isPermitted($srchmod, 'DetailView'))) {
			continue;
		}
		$eirs = $adb->pquery('select fieldname,tablename,entityidfield from vtiger_entityname where modulename=?', array($srchmod));
		$ei = $adb->fetch_array($eirs);
		$fieldsname = $ei['fieldname'];
		$wherefield = $ei['fieldname']." $op '$term'";
		if (!(strpos($fieldsname, ',') === false)) {
			$fieldlists = explode(',', $fieldsname);
			$fieldsname = 'concat(';
			$fieldsname = $fieldsname . implode(",' ',", $fieldlists);
			$fieldsname = $fieldsname . ')';
			$wherefield = implode(" $op '$term' or ", $fieldlists)." $op '$term' or $fieldsname $op '$term'";
		}
		$qry = "select crmid,$fieldsname as crmname
			from {$ei['tablename']}
			inner join vtiger_crmentity on crmid = {$ei['entityidfield']}
			where deleted = 0 and ($wherefield)";
		$rsemp=$adb->query($qry);
		$trmod = getTranslatedString($srchmod, $srchmod);
		$wsid = vtyiicpng_getWSEntityId($srchmod);
		while ($emp=$adb->fetch_array($rsemp)) {
			$respuesta[] = array(
				'crmid'=>$wsid.$emp['crmid'],
				'crmname'=>html_entity_decode($emp['crmname'], ENT_QUOTES, $default_charset).($num_search_modules>1 ? " :: $trmod" : ''),
				'crmmodule'=>$srchmod,
			);
			if (count($respuesta)>=$limit) {
				break;
			}
		}
	}
	return $respuesta;
}

/**
 * @param String $term: search term
 * @param Array $returnfields: array of fields to return as result, maybe for the future
 * @param Number $limit: maximum number of values to return
 * @return Array values found
 */
function getProductServiceAutocomplete($term, $returnfields = array(), $limit = 5) {
	global $adb, $current_user;
	$cur_user_decimals = $current_user->column_fields['no_of_currency_decimals'];
	$term = $adb->sql_escape_string(vtlib_purify($term));
	$limit = $adb->sql_escape_string(vtlib_purify($limit));
	$sourceModule = $adb->sql_escape_string(vtlib_purify($_REQUEST['sourceModule']));

	$bmapname = $sourceModule . '_FieldInfo';
	$cbMapid = GlobalVariable::getVariable('BusinessMapping_FieldInfo', cbMap::getMapIdByName($bmapname), $sourceModule, $current_user->id);
	$productsearchfields = array('productname','mfr_part_no','vendor_part_no');
	$servicesearchfields = array('servicename');
	$productsearchquery = '';
	$servicesearchquery = '';
	$prodconds = array();
	$servconds = array();
	$prodcondquery = '';
	$servcondquery = '';
	$opmap = array('equals' => '=','smaller'=>'<','greater'=>'>');
	$prodffs = array('description=description');
	$servffs = array('description=description');
	$entitytablemap = array('description');

	require_once 'include/fields/CurrencyField.php';
	require_once 'include/utils/CommonUtils.php';

	if ($cbMapid) {
		$cbMap = cbMap::getMapByID($cbMapid);
		$cbMapFI = $cbMap->FieldInfo();
		$cbMapFI = $cbMapFI['fields'];
		if (array_key_exists('cbProductServiceField', $cbMapFI) && array_key_exists('searchfields', $cbMapFI['cbProductServiceField'])) {
			$sf = $cbMapFI['cbProductServiceField']['searchfields'];
			$productsearchfields = array_key_exists('Products', $sf) ? explode(',', $sf['Products']) : $productsearchfields;
			$servicesearchfields = array_key_exists('Service', $sf) ? explode(',', $sf['Service']) : $servicesearchfields;
		}
		if (array_key_exists('cbProductServiceField', $cbMapFI) && array_key_exists('searchcondition', $cbMapFI['cbProductServiceField'])) {
			$sc = json_decode($cbMapFI['cbProductServiceField']['searchcondition'], true);
			$prodconds = array_key_exists('Products', $sc) ? $sc['Products'] : $prodconds;
			$servconds = array_key_exists('Service', $sc) ? $sc['Service'] : $servconds;
		}
		if (array_key_exists('cbProductServiceField', $cbMapFI) && array_key_exists('fillfields', $cbMapFI['cbProductServiceField'])) {
			$ff = $cbMapFI['cbProductServiceField']['fillfields'];
			$prodffs = array_key_exists('Products', $ff) ? explode(',', $ff['Products']) : $prodffs;
			$servffs = array_key_exists('Service', $ff) ? explode(',', $ff['Service']) : $servffs;
		}
	}

	for ($i=0; $i < count($productsearchfields); $i++) {
		if (preg_match('/\[[\w_|]+\]/', $productsearchfields[$i])) {
			// It's a compounded field definition
			$cfields = explode('|', $productsearchfields[$i]);
			array_walk($cfields, function (&$cfield) {
				$cfield = preg_replace('/\[|\]/', '', $cfield);
				$cfield = preg_match('/cf_/', $cfield) ? 'vtiger_productcf.' . $cfield : 'vtiger_products.' . $cfield;
			});
			array_unshift($cfields, 'CONCAT_WS(\' \'');
			$productsearchquery .= implode(',', $cfields) . ') LIKE \'%' . $term . '%\'';
		} else {
			$productsearchquery .= 'vtiger_products.' . $productsearchfields[$i] . ' LIKE \'%' . $term . '%\'';
		}
		if (($i + 1) < count($productsearchfields)) {
			$productsearchquery .= ' OR ';
		}
	}

	for ($i=0; $i < count($servicesearchfields); $i++) {
		if (preg_match('/\[[\w_|]+\]/', $servicesearchfields[$i])) {
			// It's a compounded field definition
			$cfields = explode('|', $servicesearchfields[$i]);
			array_walk($cfields, function (&$cfield) {
				$cfield = preg_replace('/\[|\]/', '', $cfield);
				$cfield = preg_match('/cf_/', $cfield) ? 'vtiger_servicecf.' . $cfield : 'vtiger_service.' . $cfield;
			});
			array_unshift($cfields, 'CONCAT_WS(\' \'');
			$servicesearchquery .= implode(',', $cfields) . ') LIKE \'%' . $term . '%\'';
		} else {
			$servicesearchquery .= 'vtiger_service.' . $servicesearchfields[$i] . ' LIKE \'%' . $term . '%\'';
		}
		if (($i + 1) < count($servicesearchfields)) {
			$servicesearchquery .= ' OR ';
		}
	}

	$prodcondquery .= count($prodconds) > 0 ? 'AND (' : '';
	for ($i=0; $i < count($prodconds); $i++) {
		if ($i % 2 == 0) {
			$prodcondoperation = $prodconds[$i]['field'] . ' ' . $opmap[$prodconds[$i]['operator']] . ' ' . $prodconds[$i]['value'];
			$prodcondquery .= substr($prodconds[$i], 0, 3) == 'cf_' ? 'vtiger_productcf.'.$prodcondoperation : 'vtiger_products.'.$prodcondoperation;
		} else {
			$prodcondquery .= ' ' . $prodconds[$i] . ' ';
		}
	}
	$prodcondquery .= count($prodconds) > 0 ? ')' : '';

	$servcondquery .= count($servconds) > 0 ? 'AND (' : '';
	for ($i=0; $i < count($servconds); $i++) {
		if ($i % 2 == 0) {
			$servcondoperation = $servconds[$i]['field'] . ' ' . $opmap[$servconds[$i]['operator']] . ' ' . $servconds[$i]['value'];
			$servcondquery .= substr($servconds[$i], 0, 3) == 'cf_' ? 'vtiger_servicecf.'.$servcondoperation : 'vtiger_service.'.$servcondoperation;
		} else {
			$servcondquery .= ' ' . $servconds[$i] . ' ';
		}
	}
	$servcondquery .= count($servconds) > 0 ? ')' : '';
	$prod_aliasquery = '';
	foreach ($prodffs as $prodff) {
		list($palias, $pcolumn) = explode('=', $prodff);
		$table = in_array($pcolumn, $entitytablemap) ? 'vtiger_crmentity' : 'vtiger_products';
		$table = substr($pcolumn, 0, 3) == 'cf_' ? 'vtiger_productcf' : $table;
		$selector = $pcolumn == '\'\'' ? $pcolumn : $table . '.' . $pcolumn;
		$prod_aliasquery .= $selector . ' AS ' . $palias . ',';
	}
	$serv_aliasquery = '';
	foreach ($servffs as $servff) {
		list($salias, $scolumn) = explode('=', $servff);
		$table = in_array($scolumn, $entitytablemap) ? 'vtiger_crmentity' : 'vtiger_service';
		$table = substr($scolumn, 0, 3) == 'cf_' ? 'vtiger_servicecf' : $table;
		$selector = $scolumn == '\'\'' ? $scolumn : $table . '.' . $scolumn;
		$serv_aliasquery .= $selector . ' AS ' . $salias . ',';
	}

	$r = $adb->query("
		SELECT 
			vtiger_products.productname AS name,
			vtiger_products.divisible AS divisible,
			'Products' AS type,
			vtiger_products.vendor_part_no AS ven_no,
			vtiger_products.cost_price AS cost_price,
			vtiger_products.mfr_part_no AS mfr_no,
			vtiger_products.qtyinstock AS qtyinstock,
			vtiger_products.qty_per_unit AS qty_per_unit,
			vtiger_products.usageunit AS usageunit,
			vtiger_products.qtyindemand AS qtyindemand,
			{$prod_aliasquery}
			vtiger_crmentity.deleted AS deleted,
			vtiger_crmentity.crmid AS id,
			vtiger_products.unit_price AS unit_price
			FROM vtiger_products
			INNER JOIN vtiger_crmentity ON vtiger_products.productid = vtiger_crmentity.crmid
			INNER JOIN vtiger_productcf ON vtiger_products.productid = vtiger_productcf.productid
			".getNonAdminAccessControlQuery('Products', $current_user)."
			WHERE ({$productsearchquery}) 
			{$prodcondquery} 
			AND vtiger_products.discontinued = 1 AND vtiger_crmentity.deleted = 0
		UNION
		SELECT
			vtiger_service.servicename AS name,
			vtiger_service.divisible AS divisible,
			'Services' AS type,
			'' AS ven_no,
			'' AS mfr_no,
			0 AS qtyinstock,
			'' AS cost_price,
			vtiger_service.qty_per_unit AS qty_per_unit,
			vtiger_service.service_usageunit AS usageunit,
			0 AS qtyindemand,
			{$serv_aliasquery}
			vtiger_crmentity.deleted AS deleted,
			vtiger_crmentity.crmid AS id,
			vtiger_service.unit_price AS unit_price
			FROM vtiger_service
			INNER JOIN vtiger_crmentity ON vtiger_service.serviceid = vtiger_crmentity.crmid
			INNER JOIN vtiger_servicecf ON vtiger_service.serviceid = vtiger_servicecf.serviceid
			".getNonAdminAccessControlQuery('Services', $current_user)."
			WHERE ({$servicesearchquery}) 
			{$servcondquery} 
			AND vtiger_service.discontinued = 1 AND vtiger_crmentity.deleted = 0
		LIMIT $limit");
	$ret = array();

	$parr = array(
		'module' => vtlib_purify($_REQUEST['sourceModule']),
		'moduleid' => vtlib_purify($_REQUEST['modid']),
		'accountid' => isset($_REQUEST['accid']) ? vtlib_purify($_REQUEST['accid']) : 0,
		'contactid' => isset($_REQUEST['ctoid']) ? vtlib_purify($_REQUEST['ctoid']) : 0,
		'productid' => 0,
		'related_module' => $sourceModule,
	);
	while ($prodser = $adb->fetch_array($r)) {
		$unitprice = $prodser['unit_price'];
		if (!empty($_REQUEST['currencyid'])) {
			$prod_prices = getPricesForProducts($_REQUEST['currencyid'], array($prodser['id']));
			$unitprice = $prod_prices[$prodser['id']];
		}
		$parr['productid'] = $prodser['id'];
		list($unitprice, $dtopdo, $void) = cbEventHandler::do_filter('corebos.filter.inventory.getprice', array($unitprice, 0, $parr));

		$ret_prodser = array(
			'meta' => array(
				'image' => '',
				'name' => $prodser['name'],
				'divisible' => $prodser['divisible'],
				'comments' => $prodser['description'],
				'ven_no' => $prodser['ven_no'],
				'mfr_no' => $prodser['mfr_no'],
				'type' => $prodser['type'],
				'id' => $prodser['id'],
			),
			'pricing' => array(
				'unit_price' => number_format((float)$unitprice, $cur_user_decimals, '.', ''),
				'discount' => number_format((float)$dtopdo, $cur_user_decimals, '.', ''),
				'unit_cost' => number_format((float)$prodser['cost_price'], $cur_user_decimals, '.', ''),
			),
			'logistics' => array(
				'qtyinstock' => number_format((float)$prodser['qtyinstock'], $cur_user_decimals, '.', ''),
				'qty_per_unit' => number_format((float)$prodser['qty_per_unit'], $cur_user_decimals, '.', ''),
				'usageunit' => $prodser['usageunit'],
				'qtyindemand' => number_format((float)$prodser['qtyindemand'], $cur_user_decimals, '.', ''),
			),
			'translations' => array(
				'ven_no' => getTranslatedString('Mfr PartNo', 'Products'),
				'mfr_no' => getTranslatedString('Vendor PartNo', 'Products'),
			),
		);
		$multic = $adb->pquery('select * from vtiger_productcurrencyrel where productid=?', array($prodser['id']));
		$mc = array();
		while ($mcinfo = $adb->fetch_array($multic)) {
			$mc[$mcinfo['currencyid']] = array(
				'converted_price' => number_format((float)$mcinfo['converted_price'], $cur_user_decimals, '.', ''),
				'actual_price' => number_format((float)$mcinfo['actual_price'], $cur_user_decimals, '.', ''),
			);
		}
		$ret_prodser['pricing']['multicurrency'] = $mc;
		$ret[] = $ret_prodser;
	}
	return $ret;
}

/**
 * @param String $term: search term
 * @param String $filter: operator to use: eq, neq, startswith, endswith, contains
 * @param String $searchinmodule: valid module to search in
 * @param String $fields: comma separated list of fields to search in
 * @param String $returnfields: comma separated list of fields to return as result, if empty $fields will be returned
 * @param Number $limit: maximum number of values to return
 * @param Users $user
 * @return Array values found: crmid => array($returnfields)
 */
function getFieldAutocomplete($term, $filter, $searchinmodule, $fields, $returnfields, $limit, $user) {
	global $current_user, $adb, $default_charset;

	$respuesta=array();
	if (empty($searchinmodule) || empty($fields)) {
		return $respuesta;
	}
	if (!(vtlib_isModuleActive($searchinmodule) && isPermitted($searchinmodule, 'DetailView')=='yes')) {
		return $respuesta;
	}
	if (empty($returnfields)) {
		$returnfields = $fields;
	}
	if (empty($limit)) {
		$limit = 30;  // hard coded default
	}

	if (empty($term)) {
		$term='%';
		$op='like';
	} else {
		switch ($filter) {
			case 'eq':
				$op='e';
				break;
			case 'neq':
				$op='n';
				break;
			case 'startswith':
				$op='s';
				break;
			case 'endswith':
				$op='ew';
				break;
			case 'contains':
				$op='c';
				break;
			default:
				$op='e';
				break;
		}
	}
	$current_user = VTWS_PreserveGlobal::preserveGlobal('current_user', $user);
	$smod = CRMEntity::getInstance($searchinmodule);
	$sindex = $smod->table_index;
	$queryGenerator = new QueryGenerator($searchinmodule, $current_user);
	$sfields = explode(',', $fields);
	$rfields = array_filter(explode(',', $returnfields));
	$flds = array_unique(array_merge($rfields, $sfields, array('id')));

	$queryGenerator->setFields($flds);
	$queryGenerator->startGroup();
	foreach ($sfields as $sfld) {
		$queryGenerator->addCondition($sfld, $term, $op, $queryGenerator::$OR);
	}
	$queryGenerator->endGroup();
	$query = $queryGenerator->getQuery();
	$rsemp=$adb->query($query);
	$wsid = vtyiicpng_getWSEntityId($searchinmodule);
	while ($emp=$adb->fetch_array($rsemp)) {
		$rsp = array();
		foreach ($rfields as $rf) {
			$mod_fields = $queryGenerator->getModuleFields();
			$colum_name = $mod_fields[$rf]->getColumnName();
			$rsp[$rf] = html_entity_decode($emp[$colum_name], ENT_QUOTES, $default_charset);
		}
		$respuesta[] = array(
			'crmid'=>$wsid.$emp[$sindex],
			'crmfields'=>$rsp,
		);
		if (count($respuesta)>=$limit) {
			break;
		}
	}
	VTWS_PreserveGlobal::flush();
	return $respuesta;
}

/**
 * @param String $term: search term
 * @param String $filter: operator to use: eq, neq, startswith, endswith, contains
 * @param String $searchinmodule: valid module to search in
 * @param String $fields: comma separated list of fields to search in
 * @param String $returnfields: comma separated list of fields to return as result, if empty $fields will be returned
 * @param Number $limit: maximum number of values to return
 * @param Users $user
 * @return Array values found: crmid => array($returnfields)
 */
function getGlobalSearch($term, $searchin, $limit, $user) {
	global $current_user,$adb,$default_charset;

	$respuesta=array();
	if (empty($searchin)) {
		return $respuesta;
	}
	if (empty($limit)) {
		$limit = GlobalVariable::getVariable('Application_Global_Search_Autocomplete_Limit', 15);
	}

	$current_user = VTWS_PreserveGlobal::preserveGlobal('current_user', $user);
	$query = array();
	$total=0;
	foreach ($searchin as $key => $value) {
		$searchinmodule=$key;
		$smod = CRMEntity::getInstance($searchinmodule);
		$sindex = $smod->table_index;
		$wsid = vtyiicpng_getWSEntityId($searchinmodule);
		if (!(vtlib_isModuleActive($searchinmodule) && isPermitted($searchinmodule, 'DetailView'))) {
			continue;
		}
		$filter =$value['searchcondition'];
		$sfields = $value['searchfields'];
		$rfields = $value['showfields'];
		$queryGenerator = new QueryGenerator($searchinmodule, $current_user);
		if (empty($term)) {
			$term='%';
			$op='like';
		} else {
			switch ($filter) {
				case 'neq':
					$op='n';
					break;
				case 'startswith':
					$op='s';
					break;
				case 'endswith':
					$op='ew';
					break;
				case 'contains':
					$op='c';
					break;
				case 'eq':
				default:
					$op='e';
					break;
			}
		}
		$flds = array_unique(array_merge($sfields, $rfields, array('id')));
		$queryGenerator->setFields($flds);
		$queryGenerator->startGroup();
		foreach ($sfields as $sfld) {
			$queryGenerator->addCondition($sfld, $term, $op, $queryGenerator::$OR);
		}
		$queryGenerator->endGroup();
		$query = $queryGenerator->getQuery();
		$mod_fields = $queryGenerator->getModuleFields();
		$qryFrom= explode('FROM', $query);
		$countQry='Select count(*) FROM '.$qryFrom[1];
		$totalQry=$adb->query($countQry);
		$total=$total+$adb->query_result($totalQry, 0, 0);

		$queryGenerator->limit= $limit;
		$query = $queryGenerator->getQuery(false, $limit);
		$rsemp=$adb->query($query);

		while ($emp=$adb->fetch_array($rsemp)) {
			$rsp = array();
			foreach ($rfields as $rf) {
				if (strpos($rf, '.')>0) { // other module reference field
					list($cmod, $cnme) = explode('.', $rf);
					if ($cmod=='Users') {
						$colum_name = $cnme;
					} else {
						$colum_name = strtolower(str_replace('.', '', $rf));
					}
				} else {
					$colum_name = $mod_fields[$rf]->getColumnName();
				}
				$rsp[$rf] = html_entity_decode($emp[$colum_name], ENT_QUOTES, $default_charset);
			}
			$respuesta[] = array(
				'crmid'=>$wsid.$emp[$sindex],
				'query_string'=>getTranslatedString($searchinmodule),
				'crmmodule'=> $searchinmodule,
				'crmfields'=>$rsp,
			);
			if (count($respuesta)>=$limit) {
				break;
			}
		}
	}
	VTWS_PreserveGlobal::flush();
	$final=array('data'=>$respuesta,'total'=>$total);
	return $final;
}
?>
