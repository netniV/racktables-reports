<?php
// Custom Racktables Report v.0.4.0
// List a type of objects in a table and allow to export them via CSV

// 2016-02-04 - Mogilowski Sebastian <sebastian@mogilowski.net>
// 2018-09-10 - Lars Vogdt <lars@linux-schulserver.de>


function renderCustomReport()
{
	# Get object list
	$phys_typelist = readChapter (CHAP_OBJTYPE, 'o');
	$attibutes     = getAttrMap();
	$aTagList      = getTagList();
	$aReportVar    = getCustomReportPostVars();
	$report_type   = 'custom';

	if ( ( $_SERVER['REQUEST_METHOD'] == 'POST' ) && ( isset( $_POST['csv'] ) ) ) {

		header('Content-type: text/csv');
		header('Content-Disposition: attachment; filename=export_'.$report_type.'_'.date("Ymdhis").'.csv');
		header('Pragma: no-cache');
		header('Expires: 0');

		$outstream = fopen("php://output", "w");

		$aResult = getResult($_POST); // Get Result
		$_POST['name'] = validateColumns($_POST); // Fix empty Columns

		$csvDelimiter = (isset( $_POST['csvDelimiter'] )) ? $_POST['csvDelimiter'] : ',';

		/* Create Header */
		$aCSVRow = array();
		foreach ($aReportVar as $postName => $postData) {
			if ( isset( $_POST[$postName] ) && $_POST[$postName] )
				if ($postName == 'attributeIDs') {
					foreach ( $_POST['attributeIDs'] as $attributeID )
						array_push($aCSVRow, $attibutes[$attributeID]['name']);
				} else {
					array_push($aCSVRow, $postData["Title"]);
				}
		}

		fputcsv( $outstream, $aCSVRow, $csvDelimiter );

		/* Create data rows */
		foreach ( $aResult as $Result ) {
			$aCSVRow = array();

			foreach ($aReportVar as $postName => $postData) {
				if ( isset( $_POST[$postName] ) ) {
					$postField = $postName;
					if ( isset( $postData['Data'] ) ) {
						$postField = $postData['Data'];
					}

					$resultVal = '';
					if (isset($postData['Csv'])) {
						$resultVal = call_user_func($postData['Csv'],$Result);
					} elseif (isset($Result[$postField])) {
						$resultVal = $Result[$postField];
					}

					if (is_array($resultVal)) {
						foreach ($resultVal as $tempVal)
							array_push($aCSVRow, $tempVal);
					} else {
						array_push($aCSVRow, $resultVal);
					}
				}
			}

			fputcsv( $outstream, $aCSVRow, $csvDelimiter );
		}

		fclose($outstream);

		exit(0); # Exit normally after send CSV to browser

	}

	renderIncludes();

	echo '<h1> Custom report</h1><ul>';

	if ( $_SERVER['REQUEST_METHOD'] == 'POST' )
		echo '<a href="#" class="show_hide">Show/hide search form</a><br/><br/>';

	echo '
      <div class="searchForm">
        <form method="post" name="searchForm">
          <div class="searchTable">
            <div class="searchRow">
              <div class="searchTitle">
                <h3>Object Type</h3>
              </div>
';
	$i=0;
	foreach ( $phys_typelist as $objectTypeID => $sName ) {
		$checked = (isset($_POST['objectIDs']) && in_array($objectTypeID, $_POST['objectIDs']) );
		renderCustomReportSearchCheckBox('objectIDs[]',"objectID${objectTypeID}", $objectTypeID, $sName, $checked);
	}

	$commonValues = array(
		array('name' => 'sName',        'value' => '1', 'post' => 'sName',        'text' => 'Name'),
		array('name' => 'label',        'value' => '1', 'post' => 'label',        'text' => 'Label'),
		array('name' => 'type',         'value' => '1', 'post' => 'type',         'text' => 'Type'),
		array('name' => 'asset_no',     'value' => '1', 'post' => 'asset_no',     'text' => 'Asset Tag'),
		array('name' => 'location',     'value' => '1', 'post' => 'location',     'text' => 'Location'),
		array('name' => 'has_problems', 'value' => '1', 'post' => 'has_problems', 'text' => 'Has Problems'),
		array('name' => 'comment',      'value' => '1', 'post' => 'comment',      'text' => 'Comment'),
		array('name' => 'runs8021Q',    'value' => '1', 'post' => 'runs8021Q',    'text' => 'Runs 8021Q'),
		array('name' => 'MACs',         'value' => '1', 'post' => 'MACs',         'text' => 'MACs'),
		array('name' => 'IPs',          'value' => '1', 'post' => 'IPs',          'text' => 'IPs'),
		array('name' => 'Tags',         'value' => '1', 'post' => 'Tags',         'text' => 'Tags'),
		array('name' => 'Ports',        'value' => '1', 'post' => 'Ports',        'text' => 'Ports'),
		array('name' => 'Containers',   'value' => '1', 'post' => 'Containers',   'text' => 'Containers'),
		array('name' => 'Childs',       'value' => '1', 'post' => 'Childs',       'text' => 'Child objects'),
	);

	echo '
            </div>
            <div class="searchRow">
              <div class="searchTitle">
                <h3>Common Values</h3>
              </div>
';

	foreach ($commonValues as $cv) {
		$checked = isset($_POST[$cv['post']]);
		renderCustomReportSearchCheckBox($cv['name'], $cv['name'], $cv['value'], $cv['text'], $checked);
	}

	echo '
            </div>
            <div class="searchRow">
              <div class="searchTitle">
                <h3>Attributess</h3>
              </div>
';
	foreach ( $attibutes as $attributeID => $aRow ) {
		$sName = $aRow['name'];
		$checked = (isset($_POST['attributeIDs']) && in_array($attributeID, $_POST['attributeIDs']) );
		renderCustomReportSearchCheckBox('attributeIDs[]',"attributeID${attributeID}", $attributeID, $sName, $checked);
	}

	echo '
            </div>
            <div class="searchRow">
              <div class="searchTitle">
                <h3>Tag</h3>
              </div>
';

	foreach ( $aTagList as $aTag ) {
		$tagID = $aTag['id'];
		$sName = $aTag['tag'];

		$checked = (isset($_POST['tagIDs']) && in_array($tagID, $_POST['tagIDs']) );
		renderCustomReportSearchCheckBox('tagIDs[]',"tagID${tagID}", $tagID, $sName, $checked);
	}

	if ( count($aTagList) < 1 )
		echo '<tr><td><i>No Tags available</i></td></tr>';

	echo '
            </div>
            <div class="searchRow">
              <div class="searchTitle">
                <h3>Filters</h3>
              </div>
';

	$preg_tag     = isset($_POST['tag_preg'])     ? $_POST['tag_preg']     : '';
	$preg_name    = isset($_POST['name_preg'])    ? $_POST['name_preg']    : '';
	$preg_comment = isset($_POST['comment_preg']) ? $_POST['comment_preg'] : '';

	renderCustomReportSearchCheckBoxReversed('csv','csv','1','CSV Export', '');
	renderCustomReportSearchTextBox('csvDelimiter', 'csvDelimiter', ',',           'CSV Delimiter');
	renderCustomReportSearchTextBox('name_preg',    'name_preg',    $preg_name,    'Name: <i>(Regular Expression)</i>');
	renderCustomReportSearchTextBox('tag_preg',     'tag_preg',     $preg_tag,     'Asset Tag: <i>(Regular Expression)</i>');
	renderCustomReportSearchTextBox('comment_preg', 'comment_preg', $preg_comment, 'Comment: <i>(Regular Expression)</i>');
	renderCustomReportSearchButton('search','search','Search','', 'submit');
	echo '
              <div class="searchTitle">
                <h3>Report Functions</h3>
              </div>
';

	renderCustomReportSearchTextBox('nameQuery', 'nameQuery', '', 'Report Name:');
	renderCustomReportSearchButton('buttonSave','buttonSave',' Save ', ' Save Report ', '', 'saveQuery();');
	echo '
              <div class="searchTitle">
                <h3>Saved Reports</h3>
              </div>
              <div id="searchReports" class="searchReport">
';
	renderStoredCustomReports();
	echo'
              </div>
            </div>
          </div>
        </form>
        <div id="dialog-message">
          <p><span class="ui-icon ui-icon-alert" style="float:left; margin:12px 12px 20px 0;"></span><span id="dialog-message-text">There was an error</span></p>
        </div>
      </div>
';

	if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {

		$aResult = getResult($_POST); // Get Result
		$_POST['sName'] = validateColumns($_POST); // Fix empty Columns

		if ( count($aResult) > 0) {
			echo '
      <table  id="customTable" class="tablesorter">
        <thead>
          <tr>
';

			foreach ($aReportVar as $varName => $varData) {
				if ( isset( $_POST[$varName] ) ) {
					if ( $varName == 'attributeIDs' ) {
						foreach ( $_POST['attributeIDs'] as $attributeID )
							echo '<th>'.$attibutes[$attributeID]['name'].'</th>';
					} else {
						echo '<th>' . $varData['Title'] . '</th>';
					}
				}
			}

			echo '
          </tr>
        </thead>
        <tbody>
';

			foreach ( $aResult as $Result ) {
				echo '
          <tr>
';
				foreach ( $aReportVar as $varName => $varData) {
					if ( isset( $_POST[$varName] ) ) {
						echo '<td>';

						$spanClass = '';
						if ( isset( $varData['Span'] ) ) {
							$spanName = call_user_func($varData['Span'], $Result);
						}

						if (!empty($spanClass)) {
							echo '<span class="'.$spanClass.'">';
						}

						$varField = $varName;
						if ( isset( $varData['Data'] ) ) {
							$varField = $varData['Data'];
						}

						$resultVal = ''; //$varField; //$varName . ': ' . json_encode($varData);
						if ( isset( $Result[$varField] ) ) {
							$resultVal = $Result[$varField];
							if ( isset( $varData['Html'] ) ) {
//mjv								echo 'Calling ' . $varName . "'s ". $varData['Html'] . '<br/>';
								$resultVal = call_user_func($varData['Html'], $Result);
							}
						}

						if (empty($resultVal)) {
							$resultVal = '&nbsp;';
						}
						echo $resultVal;

						if (!empty($spanClass)) {
							echo '</span>';
						}

						echo '</td>';
					}
				}

//mjv				echo '<td><pre>'.htmlspecialchars(json_encode($Result,JSON_PRETTY_PRINT)) . '</pre></td>';
				echo '</tr>';

			}

			echo '
        </tbody>
      </table>
      <script type="text/javascript">$(".searchForm").hide();</script>';
		} else {
			echo '<br/><br/><div align="center" style="font-size:10pt;"><i>No items found !!!</i></div><br/>';
		}
     }

     echo '<script type="text/javascript">
               $(document).ready(function()
                 {
                   $.tablesorter.defaults.widgets = ["zebra"];
                   $("#customTable").tablesorter(
                     { headers: {
                     }, sortList: [[0,0]] }
                   );
                   $("#customTable").tableFilter();

                   $(".show_hide").show();

                   $(".show_hide").click(function(){
                     $(".searchForm").slideToggle(\'slow\');
                   });

                 }
                 );
            </script>';
}

function renderCustomReportSearchCheckBox($name, $id, $value, $text, $checked) {
	$checked = (!empty($checked)) ? ' checked="checked"' : '';
	echo "
              <div class='searchItem'>
                <input class='searchCheck searchItemLeft' type='checkbox' name='${name}' id='${id}' value='${value}' ${checked}>
                <label class='searchLabel searchItemRight' for='${id}'>${text}</label>
              </div>
";
}

function renderCustomReportSearchCheckBoxReversed($name, $id, $value, $text, $checked) {
	$checked = (!empty($checked)) ? ' checked="checked"' : '';
	echo "
              <div class='searchItem'>
                <label class='searchLabel searchItemLeft' for='${id}'>${text}</label>
                <input class='searchCheck searchItemRight' type='checkbox' name='${name}' id='${id}' value='${value}' ${checked}>
              </div>
";
}

function renderCustomReportSearchTextBox($name, $id, $value, $text) {
	echo "
              <div class='searchItem'>
                <label class='searchLabel searchItemLeft' for='${id}'>${text}</label>
                <input class='searchText searchItemRight' type='textbox' name='${name}' id='${id}' value='${value}'>
              </div>
";
}

function renderCustomReportSearchButton($name, $id, $value, $text, $buttontype = 'button', $buttonclick = '') {
	$onclick = empty($buttonclick) ? '' : ' onclick="'.$buttonclick.'" ';
	echo "
              <div class='searchItem'>
                <input class='searchButton searchItemLeft' id='$id' name='$id' type='$buttontype' value='$value' $onclick tooltip='$text'>
              </div>
";
}

function renderCustomReportItem($report) {
	global $remote_username;
	$unshare = ($report['shared'] == 'no') ? '' : 'un';
	$share = ($report['shared'] == 'no') ? 'plus' : 'minus';
	$owned = ($report['user_name'] == $remote_username);
	$output = "
              <div class='searchReportItem'>
                <label id='searchReportName_${report['id']}' class='searchReportName'>${report['name']}</label>
                <button id='searchReport_${report['id']}_load' class='searchReportIcon' title='load'><i class='fas fa-print fa-lg'></i></button>
";
	if ($owned) {
		$output .= "
                <button id='searchReport_${report['id']}_${unshare}share' class='searchReportIcon' title='${unshare}share'><i class='fas fa-folder-$share fa-lg'></i></button>
<!--
                <button id='searchReport_${report['id']}_save' class='searchReportIcon' title='save'><i class='far fa-save fa-lg'></i></button>
-->
                <button id='searchReport_${report['id']}_delete' class='searchReportIcon' title='delete'><i class='far fa-trash-alt fa-lg'></i></button>
";
	}
	$output .= "
              </div>
";
	return $output;
}

function renderStoredCustomReports($asOutput = true) {
	$reports = getCustomReports();
	$output  = '';
	foreach ($reports as $report) {
		$output .= renderCustomReportItem($report);
	}

	if ($asOutput) {
		echo $output;
	}
	return $output;
}

/**
 * getResult Function
 *
 * Call Racktables API to get Objects and filter the result if required
 *
 * @param array $post
 * @return array Result
 */
function getResult ( $post ) {

	#Get available objects
	$phys_typelist = readChapter (CHAP_OBJTYPE, 'o');

	$rackObjectTypeID     = array_search('Rack', $phys_typelist);
	$rowObjectTypeID      = array_search('Row', $phys_typelist);
	$locationObjectTypeID = array_search('Location', $phys_typelist);

	$rackRealm     = false;
	$rowRealm      = false;
	$locationRealm = false;

	$sFilter = '';
	if ( isset ($post['objectIDs']) ) {
		foreach ( $post['objectIDs'] as $sFilterValue ) {
			$sFilter.='{$typeid_'.$sFilterValue.'} or ';

			if (($rackObjectTypeID) && ($sFilterValue == $rackObjectTypeID))
				$rackRealm = true;
			if (($rowObjectTypeID) && ($sFilterValue == $rowObjectTypeID))
				$rowRealm = true;
			if (($locationObjectTypeID) && ($sFilterValue == $locationObjectTypeID))
				$locationRealm = true;

		}
		$sFilter=substr($sFilter, 0, -4);
		$sFilter = '('.$sFilter.')';
	}

	$aResult = scanRealmByText ( 'object', $sFilter );

	# Get other realms than objects if user selected them
	if ($rackRealm)
		$aResult = array_merge($aResult, scanRealmByText ( 'rack') );
	if ($rowRealm)
		$aResult = array_merge($aResult, scanRealmByText ( 'row') );
	if ($locationRealm)
		$aResult = array_merge($aResult, scanRealmByText ( 'location') );

	// Add tags
	$aTemp = array();
	foreach ( $aResult as $ID => $Result) {
		$Result['tags']  = loadEntityTags( 'object', $Result['id'] );
		$Result['itags'] = getImplicitTags( $Result['tags'] );

		array_push($aTemp, $Result);
	}
	$aResult = $aTemp;

	// Search / Filter by name
	if ( isset ($post['name_preg']) && ($post['name_preg'] != '') ) {
		$aTemp = array();
		foreach ( $aResult as $ID => $Result ) {
			 if ( preg_match ( '/'.$post['name_preg'].'/' , $Result['name']) )
				array_push($aTemp, $Result);
		}
		$aResult = $aTemp;
	}

	// Search / Filter by tag
	if ( isset ($post['tag_preg']) && ($post['tag_preg'] != '') ) {
		$aTemp = array();
		foreach ( $aResult as $ID => $Result ) {
			if ( preg_match ( '/'.$post['tag_preg'].'/' , $Result['asset_no']) )
				array_push($aTemp, $Result);
		}
		$aResult = $aTemp;
	}

	// Search / Filter by comment
	if ( isset ($post['comment_preg']) && ($post['comment_preg'] != '') ) {
		$aTemp = array();
		foreach ( $aResult as $ID => $Result ) {
			if ( preg_match ( '/'.$post['comment_preg'].'/' , $Result['comment']) )
				array_push($aTemp, $Result);
		}
		$aResult = $aTemp;
	}

	// Tags
	if ( (isset ($post['tag'])) && ( count($post['tag']) >0 ) ) {
		$aTemp = array();
		$aSearchTags = array_keys($post['tag']);

		foreach ( $aResult as $Result ) {

			foreach ( $Result['tags'] as $aTag ) {
				if ( in_array($aTag['id'], $aSearchTags) )
					array_push($aTemp, $Result);
			}

			foreach ( $Result['itags'] as $aTag ) {
				if ( in_array($aTag['id'], $aSearchTags) )
					array_push($aTemp, $Result);
			}

		}

		$aResult = $aTemp;
	}

	// Ports - Load port data if necessary
	if ( isset ($post['Ports']) ) {
		$aTemp = array();

		foreach ( $aResult as $ID => $Result ) {

			$Result['portsLinks'] = getObjectPortsAndLinks ($Result['id']);

			foreach ( $Result['portsLinks'] as $i => $port) {

				$Result['portsLinks'][$i]['remote_object_name'] = 'unknown';
				if (!is_null( $port['remote_object_id'] )){
					$remote_object = spotEntity ('object', intval($port['remote_object_id']));
					$Result['portsLinks'][$i]['remote_object_name'] = $remote_object['name'];
				}
			}

			array_push($aTemp, $Result);
		}

		$aResult = $aTemp;
	}

	return $aResult;
}

/**
 * validateColumns Function
 *
 * If user doesn't select any colum to display this function preselect the name colum
 * to display the results
 *
 * @param array $_POST
 * @return bool display
 */
function validateColumns($POST) {
	if (isset( $POST['sName'] ) )
		return true;

	if ( (!isset($POST['label'])) &&
	     (!isset($POST['asset_no'])) &&
	     (!isset($POST['has_problems'])) &&
	     (!isset($POST['comment'])) &&
	     (!isset($POST['runs8021Q'])) &&
	     (!isset($POST['location'])) &&
	     (!isset($POST['MACs'])) &&
	     (!isset($POST['label'])) &&
	     (!isset($POST['attributeIDs'])) ) {
		return true;
	}
}
