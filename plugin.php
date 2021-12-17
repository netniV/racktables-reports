<?php
// Custom Racktables Report v.0.4.1
// List a type of objects in a table and allow to export them via CSV

// 2016-02-04 - Mogilowski Sebastian <sebastian@mogilowski.net>
// 2018-09-10 - Lars Vogdt <lars@linux-schulserver.de>
// 2020-02-17 - Mark Brugnoli-Vinten <netniv@hotmail.com>

require_once "reportExtensionLib.php";
require_once "custom-report.php";

global $ajaxhandler;
$ajaxhandler['customreports'] = 'handleCustomReportsAjax';

function plugin_reports_info ()
{
        return array
        (
                'name' => 'reports',
                'longname' => 'Custom Reports',
                'version' => '1.0.0',
                'home_url' => 'https://github.com/netniv/racktables-reports'
        );
}

function plugin_reports_init ()
{
	global $page, $tab;
	$tab['reports']['custom'] = 'Custom';
	$tab['reports']['server'] = 'Server';
	$tab['reports']['switches'] = 'Switches';
	$tab['reports']['vm'] = 'Virtual Machines';

	$tabhandler['reports']['custom'] = 'renderCustomReport';
	$tabhandler['reports']['server'] = 'renderServerReport';
	$tabhandler['reports']['switches'] = 'renderSwitchReport';
	$tabhandler['reports']['vm'] = 'renderVMReport';

	registerTabHandler('reports', 'custom', 'renderCustomReport');
	registerTabHandler('reports', 'server', 'renderServerReport');
	registerTabHandler('reports', 'switches', 'renderSwitchReport');
	registerTabHandler('reports', 'vm', 'renderVMReport');

	registerOpHandler ('reports', 'custom', 'run', 'runCustomReport');
	registerOpHandler ('reports', 'custom', 'load', 'loadCustomReport');
	registerOpHandler ('reports', 'custom', 'save', 'saveCustomReport');
	registerOpHandler ('reports', 'custom', 'delete', 'deleteCustomReport');
	registerOpHandler ('reports', 'custom', 'share', 'shareCustomReport');
	registerOpHandler ('reports', 'custom', 'unshare', 'unshareCustomReport');

	define('CUSTOMREPORTS_CREATE_TABLE',"
CREATE TABLE IF NOT EXISTS `CustomReports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` char(64) DEFAULT NULL,
  `shared` enum('yes','no') NOT NULL default 'no',
  `created_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data` text DEFAULT NULL,
  `user_name` varchar(24) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `shared` (`shared`, `name`)
) ENGINE=InnoDB
");

}

function plugin_reports_install ()
{
	global $dbxlink;

	$dblink->query (CUSTOMREPORTS_CREATE_TABLE);

	addConfigVar('REPORTS_CSS_PATH', '', 'string', 'yes', 'no', 'no', 'Path to the CSS files of the Custom Reports plugin');
	addConfigVar('REPORTS_JS_PATH', '', 'string', 'yes', 'no', 'no', 'Path to the Javascript files of the Custom Reports plugin');
	addConfigVar('REPORTS_SHOW_MAC_FOR_SWITCHES', 'yes', 'string', 'no', 'no', 'yes', 'Show MAC addresses in Custom Switch Report' );
	return TRUE;
}

function plugin_reports_uninstall ()
{
	global $dbxlink;

	$dbxlink->query('DROP TABLE `CustomReports`');

	deleteConfigVar('REPORTS_CSS_PATH');
	deleteConfigVar('REPORTS_JS_PATH');
	deleteConfigVar('REPORTS_SHOW_MAC_FOR_SWITCHES');
	return TRUE;
}

function plugin_reports_upgrade ()
{
	$db_info = getPLugin('reports');
	$v1 = $db_info['db_version'];
	$code_info = plugin_reports_info();
	$v2 = $code_info['version'];

	if ($v1 != $v2) {
		$versionhistory = array
		(
			'0.4.1',
			'0.5.0',
			'0.5.1',
			'1.0.0',
		);

		$skip = TRUE;
		$path = NULL;

		foreach ($versionhistory as $vh)
		{
			if ($skip && ($vh == $v1)) {
				$skip = FALSE;
				$path = array();
				continue;
			}

			if ($skip) continue;

			$path[] = $vh;
			if ($vh == $v2) break;
		}

		if ($path == NULL || !count($path)) {
			throw new RackTablesError ('Unable to determine upgrade path', RackTablesError::INTERNAL);
		}

		// build a list of queries to execute
		$queries = array();
		foreach ($path as $v)
		{
			switch ($v)
			{
				case '0.4.1':
				case '0.5.0':
				case '0.5.1':
					break;

				case '1.0.0':
					$queries[] = CUSTOMREPORTS_CREATE_TABLE;
					break;

				default:
					throw new RackTablesError("Preparing to upgrade to $v failed", RackTablesError::INTERNAL);
			}
		}
		$queries[] = "UPDATE Plugin SET version = '$v' WHERE name = 'reports'";

		// execute the queries
		global $dbxlink;
		foreach ($queries as $q)
		{
			try
			{
				$result = $dbxlink->query ($q);
			}
			catch (PDOException $e)
			{
				$errorInfo = $dbxlink->errorInfo();
				throw new RackTablesError ("Query: ${errorInfo[2]}", RackTablesError::INTERNAL);
			}
		}
	}
	return TRUE;
}

function handleCustomReportsAjax() {
	$result  = '';
	$error   = '';
	$reports = '';
	try {
		$funcmap = array(
			'save' => 'saveCustomReport',
			'load' => 'loadCustomReport',
			'delete' => 'deleteCustomReport',
			'share'  => 'shareCustomReport',
		);

		assertPermission ('object', 'reports', 'custom');
		$result = $funcmap[$_REQUEST['op']] ();
	} catch (Exception $e) {
		$this_error = 'CUSTOM-REPORTS[' . $_REQUEST['op'] .'] ' . $e->getCode() . ' -- ' . $e->getMessage();
		error_log($error . ' -- ' . $e->getTraceAsString());
		$error += $this_error + '\n';
	}

	try {
		$reports = renderStoredCustomReports(false);
	} catch (Exception $e) {
		$this_error = 'CUSTOM-REPORTS-RENDER[' . $_REQUEST['op'] .'] ' . $e->getCode() . ' -- ' . $e->getMessage();
		error_log($error . ' -- ' . $e->getTraceAsString());
		$error += $this_error + '\n';
	}

	$jo = [ 'status' => empty($error)?'OK':'ERROR', 'data' => $result, 'reports' => $reports ];
	$js = json_encode($jo);
	error_log('CUSTOM[' . $_REQUEST['op'] . '] Result: ' . $js);
	echo $js;
}

function saveCustomReport() {
	return customReportSave
	(
		genericAssertion ('name', 'string0'),
		genericAssertion ('data', 'array')
	);
}

function customReportSave($name, $data) {
	global $remote_username;
	$old_data = customReportLoad('',$name);
	$new_data = is_string($data) ? $data : json_encode($data);
	if (!empty($old_data)) {
		$result = usePreparedUpdateBlade
		(
			'CustomReports',
			array('name' => $name, 'data' => $new_data),
			array('name' => $name)
		);
	} else {
		$result = usePreparedInsertBlade
		(
			'CustomReports',
			array('name' => $name, 'data' => $new_data, 'shared' => 'no', 'user_name' => $remote_username)
		);
	}

	error_log('customReportSave($name): ' . json_encode($result));
	return $result;
}

function loadCustomReport() {
	return customReportLoad
	(
		genericAssertion ('id', 'uint0')
	);
}

function runCustomReport() {
	$reportData = customReportLoad
	(
		genericAssertion ('id', 'uint0')
	);

	$convertedData = customReportConvert($reportData);
}

function customReportLoad($id,$name='') {
	if (!empty($name)) {
		$query = usePreparedSelectBlade
		(
			'SELECT * FROM `CustomReports` WHERE `name` = ?',
			array($name)
		);
	} else {
		$query = usePreparedSelectBlade
		(
			'SELECT * FROM `CustomReports` WHERE `id` = ?',
			array($id)
		);
	}

	$result = $query->fetch (PDO::FETCH_ASSOC);
	return $result;
}

function deleteCustomReport() {
	return customReportDelete
	(
		genericAssertion ('id', 'uint0')
	);
}

function customReportDelete($id) {
	global $remote_username;

	$result = usePreparedDeleteBlade
	(
		'CustomReports',
		array('id' => $id, 'user_name' => $remote_username)
	);

	return $result;
}

function shareCustomReport() {
	return customReportShared
	(
		genericAssertion ('id', 'uint0'),
		genericAssertion ('shared', 'enum/yesno')
	);
}

function customReportShared($id, $shared) {
	global $remote_username;

	$result = usePreparedUpdateBlade
	(
		'CustomReports',
		array('shared' => $shared),
		array('id' => $id, 'user_name' => $remote_username)
	);

	return $result;
}

function getCustomReports($all = false) {
	global $remote_username;

	$where = ($all) ? '' : ' WHERE user_name = ? OR shared = "yes"';
	$params = ($all) ? array() : array($remote_username);
	$result = usePreparedSelectBlade
	(
		'SELECT * FROM `CustomReports`' . $where .
		' ORDER BY Name ASC',
		$params
	);

	return $result->fetchAll (PDO::FETCH_ASSOC);
}

function formatCsvFieldType($Result) {
	static $phys_typelist;
	if (empty($phys_typelist))
		$phys_typelist = readChapter (CHAP_OBJTYPE, 'o');
	return isset( $Result['objtype_id'] ) ? $phys_typelist[$Result['objtype_id']] . ' ' . $Result['objtype_id'] : '';
}

function formatCsvFieldComment($Result) {
	return str_replace('&quot;',"'",$Result['comment']);
}

function formatCsvFieldLocation($Result) {
	return preg_replace('/<a[^>]*>(.*)<\/a>/iU', '$1', getLocation($Result));
}

function formatCsvFieldMac($Result) {
	$sTemp = '';
	foreach ( getObjectPortsAndLinks($Result['id']) as $portNumber => $aPortDetails ) {
		if ( trim($aPortDetails['l2address']) != '')
			$sTemp .= $aPortDetails['l2address'].' ';
	}
	return $sTemp;
}

function formatCsvFieldIP($Result) {
	$sTemp = '';
	foreach ( getObjectIPv4AllocationList($Result['id']) as $key => $aDetails ) {
		if ( function_exists('ip4_format') )
			$key = ip4_format($key);

		if ( trim($key) != '')
			$sTemp .= $key.' ';
	}

	foreach ( getObjectIPv6AllocationList($Result['id']) as $key => $aDetails ) {
		if ( function_exists('ip6_format') )
			$key = ip6_format($key);
		else
		 	$key = new IPv6Address($key);

		if ( trim($key) != '')
			$sTemp .= $key.' ';
	}

	return $sTemp;
}

function formatCsvFieldAttribute($Result, $fieldData) {
	$attributes = getAttrValues ($Result['id']);
	$aCSVRow = array();
	foreach ( $fieldData['attributeIDs'] as $attributeID ) {
		$aValue = '';
		if ( isset( $attributes[$attributeID] ) ) {
			if ( isset( $attributes[$attributeID]['a_value'] ) && ($attributes[$attributeID]['a_value'] != '') )
				$aValue = $attributes[$attributeID]['a_value'];
			elseif ( ($attributes[$attributeID]['value'] != '') && ( $attributes[$attributeID]['type'] == 'date' )   )
				$aValue = date("Y-m-d",$attributes[$attributeID]['value']);
		}
		array_push($aCSVRow, $aValue);
	}
	return $aCSVRow;
}

function formatCsvFieldTag($Result) {
	$sTemp = '';
	foreach ( $Result['tags'] as $aTag ) {
		$sTemp .= $aTag['tag'].' ';
	}

	if ( count($Result['itags']) > 0 ) {
		$sTemp .=  '(';
		foreach ( $Result['itags'] as $aTag ) {
			$sTemp .= $aTag['tag'].' ';
		}
		$sTemp .=  ')';
	}
	return $sTemp;
}

function formatCsvFieldPort($Result) {
	$sTemp = '';

	foreach ( $Result['portsLinks'] as $port ) {
		$sTemp .= $port['name'].': '.$port['remote_object_name'];
		if ( trim($port['cableid']) != '')
			$sTemp .= ' Cable ID: '.$port['cableid'];

		$sTemp .= ' ';
	}

	return trim($sTemp);
}

function formatCsvFieldContainer($Result) {
	$sTemp = '';

	foreach ( getObjectContainerList($Result['id']) as $key => $aDetails ) {
		$sTemp .= trim($aDetails['container_name']).' ';
	}
	return trim($sTemp);
}

function formatCsvFieldChild($Result) {
	$sTemp = '';

	foreach ( getObjectChildObjectList($Result['id']) as $key => $aDetails ) {
		$sTemp .= trim($aDetails['object_name']).' ';
	}
	return trim($sTemp);
}

function formatClassFieldName($Result) {
	return 'object_' . str_replace('$','',$Result['atags'][1]['tag']);
}

function formatHtmlFieldName($Result) {
	return '<a href="'. makeHref ( array( 'page' => 'object', 'object_id' => $Result['id']) )  .'">'.$Result['name'].'</a>';
}

function formatHtmlFieldComment($Result) {
	return makeLinksInText($Result['comment']);
}

function formatHtmlFieldLocation($Result) {
	return getLocation($Result);
}

function formatHtmlFieldMac($Result) {
	$aResult = '';
	foreach ( getObjectPortsAndLinks($Result['id']) as $portNumber => $aPortDetails ) {
		if ( trim($aPortDetails['l2address']) != '')
			$aResult .= $aPortDetails['l2address'].'<br/>';
	}
	return trim($aResult);
}

function formatHtmlFieldIP($Result) {
	$aResult = '';
	foreach ( getObjectIPv4AllocationList($Result['id']) as $key => $aDetails ) {
		if ( function_exists('ip4_format') )
			$key = ip4_format($key);
		if ( trim($key) != '')
			$aResult .= $key . '<br/>';
	}

	foreach ( getObjectIPv6AllocationList($Result['id']) as $key => $aDetails ) {
		if ( function_exists('ip6_format') )
			$key = ip6_format($key);
		else
			$key = new IPv6Address($key);

		if ( trim($key) != '')
			$aResult .= $key . '<br/>';
	}
	return $aResult;
}

function formatHtmlFieldAttribute($Result, $fieldData) {
	$attributes = getAttrValues ($Result['id']);
	$aResult = array();
	foreach ( $fieldData['attributeIDs'] as $attributeID ) {
		$aValue = '&nbsp;';
		if ( isset( $attributes[$attributeID] ) ) {
			if ( isset( $attributes[$attributeID]['a_value'] ) && ($attributes[$attributeID]['a_value'] != '') )
				$aValue = $attributes[$attributeID]['a_value'];
			elseif ( ($attributes[$attributeID]['value'] != '') && ( $attributes[$attributeID]['type'] == 'date' )   )
				$aValue = date("Y-m-d",$attributes[$attributeID]['value']);
		}
		array_push($aResult, $aValue);
	}

	return implode('</td><td>',$aResult);
}

function formatHtmlFieldTag($Result) {
	$aResult = '';
	foreach ( $Result['tags'] as $aTag )
		$aResult .= '<a href="'. makeHref ( array( 'page' => 'depot', 'tab' => 'default', 'andor' => 'and', 'cft[]' => $aTag['id']) ) .'">'.$aTag['tag'].'</a> ';

	if ( count($Result['itags']) > 0 ) {
		$aResult .= '(';
		foreach ( $Result['itags'] as $aTag )
			$aResult .= '<a href="'. makeHref ( array( 'page' => 'depot', 'tab' => 'default', 'andor' => 'and', 'cft[]' => $aTag['id']) ) .'">'.$aTag['tag'].'</a> ';

		$aResult .= ')';
	}
	return $aResult;
}

function formatHtmlFieldPort($Result) {
	$aResult = '';
	foreach ( $Result['portsLinks'] as $port ) {
		$aResult .= $port['name'].': ';
		if ( $port['remote_object_name'] != 'unknown' )
			$aResult .= formatPortLink ($port['remote_object_id'], $port['remote_object_name'], $port['remote_id'], NULL);
		else
			$aResult .= $port['remote_object_name'];

		if ( trim($port['cableid']) != '')
			$aResult .= ' Cable ID: '.$port['cableid'];

		$aResult .= '<br/>';
	}
	return $aResult;
}

function formatHtmlFieldContainer($Result) {
	$aResult = '';
	foreach ( getObjectContainerList($Result['id']) as $key => $aDetails ) {
		$aResult .= '<a href="'. makeHref ( array( 'page' => 'object', 'object_id' => $key) )  .'">'.$aDetails['container_name'].'</a><br/>';
	}
	return $aResult;
}

function formatHtmlFieldChild($Result) {
	$aResult = '';
	foreach ( getObjectChildObjectList($Result['id']) as $key => $aDetails ) {
		$aResult .= '<a href="'. makeHref ( array( 'page' => 'object', 'object_id' => $key) )  .'">'.$aDetails['object_name'].'</a><br/>';
	}
	return $aResult;
}

function getCustomReportPostVars() {
	static $postVars;
	if (empty($postVars)) {
		$postVars = array(
			'sName' => array(
				'Title'     => 'Name',
				'Data'      => 'name',
				'Span'      => 'formatClassFieldName',
				'Html'      => 'formatHtmlFieldName',
			),
			'label' => array(
				'Title'     => 'Label',
			),
			'type' => array(
				'Title'     => 'Type',
				'Data'      => 'objtype_id',
				'Csv'       => 'formatCsvFieldType',
				'Html'      => 'formatCsvFieldType',
			),
			'asset_no' => array(
				'Title'     => 'Asset Tag',
			),
			'has_problems' => array(
				'Title'     => 'Has Problems',
			),
			'comment' => array(
				'Title'     => 'Comment',
				'Csv'       => 'formatCsvFieldComment',
				'Html'      => 'formatHtmlFieldComment',
			),
			'runs8021Q' => array(
				'Title'     => 'Runs 8021Q',
			),
			'location' => array(
				'Title'     => 'Location',
				'Data'      => 'id',
				'Csv'       => 'formatCsvFieldLocation',
				'Html'      => 'formatHtmlFieldLocation',
			),
			'MACs' => array(
				'Title'     => 'MACs',
				'Data'      => 'id',
				'Csv'       => 'formatCsvFieldMac',
				'Html'      => 'formatHtmlFieldMac',
			),
			'IPs' => array(
				'Title'     => 'IPs',
				'Data'      => 'id',
				'Csv'       => 'formatCsvFieldIP',
				'Html'      => 'formatHtmlFieldIP',
			),
			'attributeIDs' => array(
				'Data'      => 'id',
				'Csv'       => 'formatCsvFieldAttribute',
				'Html'      => 'formatHtmlFieldAttribute',
			),
			'Tags' => array(
				'Title'     => 'Tags',
				'Data'      => 'tags',
				'Csv'       => 'formatCsvFieldTag',
				'Html'      => 'formatHtmlFieldTag',
			),
			'Ports' => array(
				'Title'     => 'Ports',
				'Data'      => 'portsLinks',
				'Csv'       => 'formatCsvFieldPort',
				'Html'      => 'formatHtmlFieldPort',
			),
			'Containers' => array(
		   		'Title'     => 'Containers',
				'Data'      => 'id',
				'Csv'       => 'formatCsvFieldContainer',
				'Html'      => 'formatHtmlFieldContainer',
			),
			'Childs' => array(
				'Title'     => 'Child objects',
				'Data'      => 'id',
				'Csv'       => 'formatCsvFieldChild',
				'Html'      => 'formatHtmlFieldChild',
			),
		);
	}

	return $postVars;
}

function renderServerReport()
{
	$filter='{$typeid_4}'; # typeid_4 = Server
	renderReport($filter);
}

function renderSwitchReport()
{
	$filter='{$typeid_8}'; # typeid_8 = Switches
	renderReport($filter);
}

function renderVMReport()
{
	$filter='{$typeid_1504}'; # typeid_1504 = Virtual machines
	renderReport($filter);
}

function renderIncludes() {
	// Load stylesheet and jquery scripts
	$css_path=getConfigVar('REPORTS_CSS_PATH');
	if (empty($css_path)) $css_path = 'reports/css';

	$js_path=getConfigVar('REPORTS_JS_PATH');
	if (empty($js_path)) $js_path = 'reports/js';

	addCSSExternal ("https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css");
	addCSSInternal ("$css_path/style.css");
	addJSInternal ("$js_path/jquery-latest.js");
	addJSInternal ("$js_path/jquery-ui-1.12.1.min.js");
	addJSInternal ("$js_path/jquery.tablesorter.js");
	addJSInternal ("$js_path/picnet.table.filter.min.js");
	addJSInternal ("$js_path/saveFormValues.js");
	addJSExternal ("https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.1/js/all.min.js");
}

function renderReport($sFilter)
{
	$aResult = array();
	$iTotal = 0;

	foreach (scanRealmByText ('object', $sFilter) as $Result)
	{

		$aResult[$Result['id']] = array();
		$aResult[$Result['id']]['sName'] = $Result['name'];

		// Asset Number
		$aResult[$Result['id']]['sAsset'] = $Result['asset_no'];

		// Load additional attributes:
		$attributes = getAttrValues ($Result['id']);

		// Contact information
		$aResult[$Result['id']]['sContact'] = '';
		if ( isset( $attributes['14']['a_value'] ) )
			$aResult[$Result['id']]['sContact'] = $attributes['14']['a_value'];

		// Create active links in comment
		$aResult[$Result['id']]['sComment'] = $Result['comment'];

		// Hardware Type
		$aResult[$Result['id']]['HWtype'] = '';
		if ( isset( $attributes['2']['a_value'] ) )
			$aResult[$Result['id']]['HWtype'] = $attributes['2']['a_value'];

		// OEM S/N
		$aResult[$Result['id']]['OEMSN'] = '';
		if ( isset( $attributes['1']['a_value'] ) )
			$aResult[$Result['id']]['OEMSN'] = $attributes['1']['a_value'];

			// HW Expire Date
			$aResult[$Result['id']]['HWExpDate'] = '';
			if ( isset( $attributes['22']['value'] ) )
				$aResult[$Result['id']]['HWExpDate'] = date("Y-m-d",$attributes['22']['value']);

			// Operating System (OS)
			$aResult[$Result['id']]['sOS'] = '';
			if ( isset( $attributes['4']['a_value'] ) )
				$aResult[$Result['id']]['sOS'] = $attributes['4']['a_value'];

			// OS Version (for Switches)
			$aResult[$Result['id']]['sOSVersion'] = '';
			if ( isset( $attributes['5']['a_value'] ) )
				$aResult[$Result['id']]['sOSVersion'] = $attributes['5']['a_value'];


			$aResult[$Result['id']]['sSlotNumber'] = 'unknown';
			if ( isset( $attributes['28']['a_value'] ) && ( $attributes['28']['a_value'] != '' ) )
				$aResult[$Result['id']]['sSlotNumber'] = $attributes['28']['a_value'];

			// Location
			$aResult[$Result['id']]['sLocation'] = getLocation($Result);

			// IP Informations
			$aResult[$Result['id']]['ipV4List'] = getObjectIPv4AllocationList($Result['id']);
			$aResult[$Result['id']]['ipV6List'] = getObjectIPv6AllocationList($Result['id']);

			// Port (MAC) Informations
			$aResult[$Result['id']]['ports'] = getObjectPortsAndLinks($Result['id']);

			// Container
			$aResult[$Result['id']]['container'] = getObjectContainerList($Result['id']);

			$iTotal++;
		}

		// define standard fields for all filters
		$aCSVRow = array('Name','MAC(s)','IP(s)','Comment','Contact');
		$title = 'Unnamed report';

		// add specific fields depending on filter value
		switch($sFilter)
		{
			case '{$typeid_4}':
				$title = 'Server report';
				$report_type = 'server';
				array_push($aCSVRow, 'Type','Asset No.','Location','OEM S/N','HW Expire Date','OS');
				break;

			case '{$typeid_8}':
				$title = 'Switch report';
				$report_type = 'switches';
				array_push($aCSVRow, 'Type','Asset No.','Location','OEM S/N','HW Expire Date','OS Version');
				break;

			case '{$typeid_1504}':
				$title = 'Virtual machines report';
				$report_type = 'vm';
				array_push($aCSVRow, 'OS','Hypervisor');
				break;

			default:
				echo '<h2>Unknown/No valid filter definition found</h2>';
				break;
		}

		if ( isset($_GET['csv']) ) {

			header('Content-type: text/csv');
			header('Content-Disposition: attachment; filename=export_'.$report_type.'_'.date("Ymdhis").'.csv');
			header('Pragma: no-cache');
			header('Expires: 0');

			$outstream = fopen("php://output", "w");

			fputcsv( $outstream, $aCSVRow );

			foreach ($aResult as $id => $aRow)
			{
				 //					 0			1			2				3				 4				5				6						7			 8						9				10
				 // Server: 'Name','MAC','IP(s)','Comment','Contact', 'Type','Asset No.','Location','OEM','HW Expire Date','OS'
				 // Switch: 'Name','MAC','IP(s)','Comment','Contact', 'Type','Asset No.','Location','OEM','HW Expire Date','OS Version'
				 // VM		: 'Name','MAC','IP(s)','Comment','Contact', 'OS',	'Hypervisor'

				 $aCSVRow = array();
				 // Name
				 $aCSVRow[0] = $aRow['sName'];

				 // MAC
				 $aCSVRow[1] = '';
				 foreach ( $aRow['ports'] as $portNumber => $aPortDetails ) {
					if (trim($aPortDetails['l2address']) != '')
						$aCSVRow[1] .= $aPortDetails['l2address'] . ' ';
				 }
				 $aCSVRow[1] = trim($aCSVRow[1]);

				 // IP(s)
				 $aCSVRow[2] = '';
				 foreach ( $aRow['ipV4List'] as $key => $aDetails ) {
					if ( function_exists('ip4_format') )
						$key = ip4_format($key);
					if ( trim($key) != '')
						$aCSVRow[2] .= $key . ' ';
				 }

				 foreach ( $aRow['ipV6List'] as $key => $aDetails ) {
					if ( function_exists('ip6_format') )
						$key = ip6_format($key);
					if ( trim($key) != '')
						$aCSVRow[2] .= $key . ' ';
				 }
				 $aCSVRow[2] = trim($aCSVRow[2]);

				 // Comment
				 $aCSVRow[3] = str_replace('&quot;',"'",$aRow['sComment']);

				 // Contact
				 $aCSVRow[4] = $aRow['sContact'];

				 switch($sFilter)
				 {
					case '{$typeid_4}':
					case '{$typeid_8}':
						// Type
						$aCSVRow[5] = $aRow['HWtype'];
						// Asset No
						$aCSVRow[6] = $aRow['sAsset'];
						// Location
						$aCSVRow[7] = preg_replace('/<a[^>]*>(.*)<\/a>/iU', '$1', $aRow['sLocation']);
						// OEM S/N
						$aCSVRow[8] = $aRow['OEMSN'];
						// HW Expire Date
						$aCSVRow[9] = $aRow['HWExpDate'];
						break;
					case '{$typeid_1504}':
						// OS
						$aCSVRow[5] = $aRow['sOS'];
						// Container
						$aCSVRow[6] = '';
						foreach ( $aRow['container'] as $key => $aDetails ) {
							$aCSVRow[6] .= trim($aDetails['container_name']).' ';
						}
						break;
				 }

				 switch($sFilter)
				 {
					case '{$typeid_4}': // OS
						$aCSVRow[10] = $aRow['sOS'];
						break;
					case '{$typeid_8}': // OS Version
						$aCSVRow[10] = $aRow['sOSVersion'];
						 break;
				 }

				 fputcsv( $outstream, $aCSVRow );
		 }

		 fclose($outstream);

		 exit(0); # Exit normally after send CSV to browser

	}

	renderIncludes();

	// Display the stat array
	echo "\n<h2>$title (".$iTotal.")</h2><ul>";
	echo "<a href='index.php?page=reports&tab=$report_type&csv'>CSV Export</a>\n";
	echo "<table id=\"reportTable\" class=\"tablesorter\">\n	<thead>\n		<tr>\n";

	foreach ($aCSVRow  as $row)
	{
		echo "      <th>$row</th>\n";
	}
	echo "    </tr>\n  </thead>\n<tbody>\n";

	foreach ($aResult as $id => $aRow)
	{
		//           0      1      2        3         4        5        6            7       8            9        10
		// Server: 'Name','MAC','IP(s)','Comment','Contact', 'Type','Asset No.','Location','OEM','HW Expire Date','OS'
		// Switch: 'Name','MAC','IP(s)','Comment','Contact', 'Type','Asset No.','Location','OEM','HW Expire Date','OS Version'
		// VM    : 'Name','MAC','IP(s)','Comment','Contact', 'OS',  'Hypervisor'
		//
		// Name
		echo "<tr>\n  <td><a href=\"". makeHref ( array( 'page' => 'object', 'object_id' => $id) )  ."\">".$aRow['sName']."</a></td>\n  <td>";

		// MAC
		if (getConfigVar ('REPORTS_SHOW_MAC_FOR_SWITCHES') == 'yes')
		{
			foreach ( $aRow['ports'] as $portNumber => $aPortDetails ) {
				if (trim($aPortDetails['l2address']) != '')
					echo $aPortDetails['l2address'] . "<br/>\n";
			}
		}
		echo "  </td>\n  <td>";

		// IP(s)
		foreach ( $aRow['ipV4List'] as $key => $aDetails ) {
			if ( function_exists('ip4_format') )
				$key = ip4_format($key);
			if ( trim($key) != '')
				echo $key . "<br/>\n";
		}

		foreach ( $aRow['ipV6List'] as $key => $aDetails ) {
			if ( function_exists('ip6_format') )
				$key = ip6_format($key);
			if ( trim($key) != '')
				echo $key . "<br/>\n";
		}

		// Comment & Contact
		echo "  </td>\n  <td>".makeLinksInText($aRow['sComment'])."  </td>\n";
		echo '  <td>'.makeLinksInText($aRow['sContact'])."  </td>\n";

		switch($sFilter)
		{
			case '{$typeid_4}':
			case '{$typeid_8}':
				// Type
				echo '  <td>'.$aRow['HWtype']."</td>\n";
				// Asset No
				echo '  <td>'.$aRow['sAsset']."</td>\n";
				// Location
				echo '  <td>'.$aRow['sLocation']."</td>\n";
				// OEM S/N
				echo '  <td>'.$aRow['OEMSN']."</td>\n";
				// HW Expire Date
				echo '  <td>'.$aRow['HWExpDate']."</td>\n";
				break;

			case '{$typeid_1504}':
				// OS
				echo '  <td>'.$aRow['sOS']."</td>\n";
				// Container
				echo '  <td>';
				foreach ( $aRow['container'] as $key => $aDetails ) {
					echo trim($aDetails['container_name'])."<br/>\n";
				}
				echo "  </td>\n";
				break;
		}

		switch($sFilter)
		{
			case '{$typeid_4}': // OS
				echo '  <td>'.$aRow['sOS']."</td>\n";
				break;

			case '{$typeid_8}': // OS Version
                                 echo '  <td>'.$aRow['sOSVersion']."</td>\n";
                                 break;
		}
		echo "  </tr>\n";
	}

	echo "  </tbody>\n</table>\n";

	echo '
<script type="text/javascript">
	$(document).ready(function()
	{
		$.tablesorter.defaults.widgets = ["zebra"];
		$("#reportTable").tablesorter({
			headers: {
				2: { sorter: "ipAddress" },
			},
			sortList: [[0,0]]
		});
		$("#reportTable").tableFilter();
	});
	</script>';
}
