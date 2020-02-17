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

	echo '<h2> Custom report</h2><ul>';

	// Load stylesheet and jquery scripts
	$css_path=getConfigVar('REPORTS_CSS_PATH');
	if (empty($css_path)) $css_path = 'reports/css';

	$js_path=getConfigVar('REPORTS_JS_PATH');
	if (empty($js_path)) $js_path = 'reports/js';

	addCSSInternal ("$css_path/style.css");
	addJSInternal ("$js_path/saveFormValues.js");
	addJSInternal ("$js_path/jquery-latest.js");
	addJSInternal ("$js_path/jquery.tablesorter.js");
	addJSInternal ("$js_path/picnet.table.filter.min.js");

	if ( $_SERVER['REQUEST_METHOD'] == 'POST' )
		echo '<a href="#" class="show_hide">Show/hide search form</a><br/><br/>';

	echo '
      <div class="searchForm">
        <form method="post" name="searchForm">
          <table class="searchTable">
            <tr>
              <th>Object Type</th>
              <th>Common Values</th>
              <th>Attributes</th>
              <th>Tags</th>
              <th>Misc</th>
            </tr>
            <tr>
              <td valign="top">
                <table class="searchTable">
';
	$i=0;
	foreach ( $phys_typelist as $objectTypeID => $sName ) {
		if( $i % 2 )
			echo '<tr class="odd">';
		else
			echo '<tr>';

		echo '<td><input type="checkbox" name="objectIDs[]" value="'.$objectTypeID.'"';
		if (isset($_POST['objectIDs']) && in_array($objectTypeID, $_POST['objectIDs']) )
					  echo ' checked="checked"';

		echo '	 > '.$sName.' </td></tr>' . PHP_EOL;
		$i++;
	}

	echo '
	            </table>
              </td>
              <td valign="top">
	        <table class="searchTable">
                  <tr><td><input type="checkbox" name="sName" value="1" ';if (isset($_POST['sName'])) echo ' checked="checked"'; echo '> Name</td></tr>
                  <tr class="odd"><td><input type="checkbox" name="label" value="1" ';if (isset($_POST['label'])) echo ' checked="checked"'; echo '> Label</td></tr>
                  <tr><td><input type="checkbox" name="type" value="1" ';if (isset($_POST['type'])) echo ' checked="checked"'; echo '> Type</td></tr>
                  <tr class="odd"><td><input type="checkbox" name="asset_no" value="1" ';if (isset($_POST['asset_no'])) echo ' checked="checked"'; echo '> Asset Tag</td></tr>
                  <tr><td><input type="checkbox" name="location" value="1" ';if (isset($_POST['location'])) echo ' checked="checked"'; echo '> Location</td></tr>
                  <tr class="odd"><td><input type="checkbox" name="has_problems" value="1" ';if (isset($_POST['has_problems'])) echo ' checked="checked"'; echo '> Has Problems</td></tr>
                  <tr><td><input type="checkbox" name="comment" value="1" ';if (isset($_POST['comment'])) echo ' checked="checked"'; echo '> Comment</td></tr>
                  <tr class="odd"><td><input type="checkbox" name="runs8021Q" value="1" ';if (isset($_POST['runs8021Q'])) echo ' checked="checked"'; echo '> Runs 8021Q</td></tr>
                  <tr><td><input type="checkbox" name="MACs" value="1" ';if (isset($_POST['MACs'])) echo ' checked="checked"'; echo '> MACs</td></tr>
                  <tr class="odd"><td><input type="checkbox" name="IPs" value="1" ';if (isset($_POST['IPs'])) echo ' checked="checked"'; echo '> IPs</td></tr>
                  <tr><td><input type="checkbox" name="Tags" value="1" ';if (isset($_POST['Tags'])) echo ' checked="checked"'; echo '> Tags</td></tr>
                  <tr class="odd"><td><input type="checkbox" name="Ports" value="1" ';if (isset($_POST['Ports'])) echo ' checked="checked"'; echo '> Ports</td></tr>
                  <tr><td><input type="checkbox" name="Containers" value="1" ';if (isset($_POST['Containers'])) echo ' checked="checked"'; echo '> Containers</td></tr>
                  <tr class="odd"><td><input type="checkbox" name="Childs" value="1" ';if (isset($_POST['Childs'])) echo ' checked="checked"'; echo '> Child objects</td></tr>
                </table>
              </td>
              <td valign="top">
                <table class="searchTable">
';
	$i=0;
	foreach ( $attibutes as $attributeID => $aRow ) {
		if( $i % 2 )
			echo '<tr class="odd">';
		else
			echo '<tr>';

		echo '<td><input type="checkbox" name="attributeIDs[]" value="'.$attributeID.'"';
		if (isset($_POST['attributeIDs']) && in_array($attributeID, $_POST['attributeIDs']) )
			 echo ' checked="checked"';

		echo '> '.$aRow['name'].'</td></tr>' . PHP_EOL;
		$i++;
	}

	echo '
                </table>
              </td>
              <td valign="top">
                <table class="searchTable">
';

	$i = 0;
	foreach ( $aTagList as $aTag ) {
		echo '<tr '.($i%2 ? 'class="odd"' : '').'><td><input type="checkbox" name="tag[' .
			$aTag['id'] . ']" value="1" ' .
			( isset($_POST['tag'][$aTag['id']]) ? 'checked="checked" ' : '').'> '.
			$aTag['tag'].'</td></tr>';
		$i++;
	}

	if ( count($aTagList) < 1 )
		echo '<tr><td><i>No Tags available</i></td></tr>';

	echo '
                </table>
              </td>
              <td valign="top">
                <table class="searchTable">
                  <tr class="odd"><td><input type="checkbox" name="csv" value="1"> CSV Export</td></tr>
                  <tr><td><input type="text" name="csvDelimiter" value="," size="1"> CSV Delimiter</td></tr>
                  <tr class="odd"><td>Name Filter: <i>(Regular Expression)</i></td></tr>
                  <tr><td><input type="text" name="name_preg" value="'; if (isset($_POST['name_preg'])) echo $_POST['name_preg']; echo '" style="height: 11pt;"></td></tr>
                  <tr class="odd"><td>Asset Tag Filter: <i>(Regular Expression)</i></td></tr>
                  <tr><td><input type="text" name="tag_preg" value="'; if (isset($_POST['tag_preg'])) echo $_POST['tag_preg']; echo '" style="height: 11pt;"></td></tr>
                  <tr class="odd"><td>Comment Filter: <i>(Regular Expression)</i></td></tr>
                  <tr><td><input type="text" name="comment_preg" value="'; if (isset($_POST['comment_preg'])) echo $_POST['comment_preg']; echo '" style="height: 11pt;"></td></tr>
                  <tr class="odd"><td>&nbsp;</td></tr>
                  <tr>
                    <td>
                      Save:
                      <input id="nameQuery" type="text" name="nameQuery" value="" style="height: 11pt; width:150px"/> <input type="button" value=" Ok " onclick="saveQuery();">
                    </td>
                  </tr>
                  <tr class="odd">
                    <td>
                      Load:<br/>
                      <span id="loadButtons"></span>
                      <script type="text/javascript">
                        loadButtons();
                      </script>
                    </td>
                  </tr>
                  <tr><td>&nbsp;</td></tr>
                  <tr><td align="right"><input type="submit" value=" Search "></td></tr>
                </table>
              </td>
            </tr>
          </table>
        </form>
      </div>';

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
