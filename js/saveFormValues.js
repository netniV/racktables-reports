/** 
 * custom search javascript functions 
 * 
 * based on scripts from http://www.howtocreate.co.uk/jslibs
 **/

var FS_INCLUDE_NAMES = 0, FS_EXCLUDE_NAMES = 1, FS_INCLUDE_IDS = 2, FS_EXCLUDE_IDS = 3, FS_INCLUDE_CLASSES = 4, FS_EXCLUDE_CLASSES = 5;

function getFormString( formRef, oAndPass, oTypes, oNames ) {
	var data = getFormData( formRef, oAndPass, oTypes, oNames );
	return data.Text;
}

function getFormJSON( formRef, oAndPass, oTypes, oNames ) {
	var data = getFormData( formRef, oAndPass, oNames );
	return data.Json;
}

function getFormData( formRef, oAndPass, oTypes, oNames ) {
	if( oNames ) {
		oNames = new RegExp((( oTypes > 3 )?'\\b(':'^(')+oNames.replace(/([\\\/\[\]\(\)\.\+\*\{\}\?\^\$\|])/g,'\\$1').replace(/,/g,'|')+(( oTypes > 3 )?')\\b':')$'),'');
		var oExclude = oTypes % 2;
	}

	var oData = {};
	var oStr = '';

	for( var x = 0, oStr = '', y = false; formRef.elements[x]; x++ ) {
		if( formRef.elements[x].type ) {
			var oE = formRef.elements[x]; var oT = oE.type.toLowerCase();

			var oN = oE.id;
			if (typeof oN == 'undefined' || oN == '') {
				oN = oE.name;
			}

			if (typeof oN == 'undefined' || oN == '') {
				oN = oE.className;
			}

			if( oNames ) {
				var theAttr = ( oTypes > 3 ) ? formRef.elements[x].className : ( ( oTypes > 1 ) ? formRef.elements[x].id : formRef.elements[x].name );
				if( ( oExclude && theAttr && theAttr.match(oNames) ) || ( !oExclude && !( theAttr && theAttr.match(oNames) ) ) ) { continue; }

				if (typeof theAttr != 'undefined') {
					oN = theAttr;
				}
			}

			if( oT == 'text' || oT == 'textarea' || ( oT == 'password' && oAndPass ) || oT == 'datetime' || oT == 'datetime-local' || oT == 'date' || oT == 'month' || oT == 'week' || oT == 'time' || oT == 'number' || oT == 'range' || oT == 'email' || oT == 'url' ) {
				oValue = oE.value.replace(/%/g,'%p').replace(/,/g,'%c');
				if (oValue) {
					oData[oN] = oValue;
				}

				oStr += ( y ? ',' : '' ) + oValue;
				y = true;
			} else if( oT == 'radio' || oT == 'checkbox' ) {
				oValue = ( oE.checked ? '1' : '' );
				if (oValue) {
					oData[oN] = { value: oE.value, checked: oValue };
				}

				oStr += ( y ? ',' : '' ) + oValue;
				y = true;
			} else if( oT == 'select-one' ) {
				oValue = oE.selectedIndex;
				if (oValue) {
					oData[oN] = { value: oE.selectedValue, index: oE.selectedIndex };
				}

				oStr += ( y ? ',' : '' ) + oValue;
				y = true;
			} else if( oT == 'select-multiple' ) {
				var oSubValue = {};
				for( var oO = oE.options, i = 0; oO[i]; i++ ) {
					oValue = ( oO[i].selected ? '1' : '' );
					oStr += ( y ? ',' : '' ) + oValue;
					if (oValue) {
						oSubValue[i] = { value: oO[i].value, selected: oValue };
					}
					y = true;
				}

				if (oSubValue) {
					oData[oN] = oSubValue;
				}
			}
		}
	}
	return { Json: { version: 1, data: oData }, Text: oStr };
}

function recoverInputs( formRef, oStr, oAndPass, oTypes, oNames ) {
	if( oStr ) {
		var oData = null;
		try {
			if (typeof oStr == 'object') {
				oData = oStr;
			} else {
				oData = JSON.parse(oStr);
			}
		} catch (e) {
			oData = null;
		}

		if (oData !== null) {
			recoverInputsJSON( formRef, oData.data, oAndPass, oTypes, oNames );
		} else {
			recoverInputsCSV( formRef, oStr, oAndPass, oTypes, onames );
		}
	}
}

function recoverInputsCSV( formRef, oStr, oAndPass, oTypes, oNames ) {
	if( oStr ) {
		oStr = oStr.split( ',' );
		if( oNames ) {
			oNames = new RegExp((( oTypes > 3 )?'\\b(':'^(')+oNames.replace(/([\\\/\[\]\(\)\.\+\*\{\}\?\^\$\|])/g,'\\$1').replace(/,/g,'|')+(( oTypes > 3 )?')\\b':')$'),'');
			var oExclude = oTypes % 2;
		}

		for( var x = 0, y = 0; formRef.elements[x]; x++ ) {
			if( formRef.elements[x].type ) {
				if( oNames ) {
					var theAttr = ( oTypes > 3 ) ? formRef.elements[x].className : ( ( oTypes > 1 ) ? formRef.elements[x].id : formRef.elements[x].name );
					if( ( oExclude && theAttr && theAttr.match(oNames) ) || ( !oExclude && ( !theAttr || !theAttr.match(oNames) ) ) ) { continue; }
				}

				var oE = formRef.elements[x]; var oT = oE.type.toLowerCase();
				if( oT == 'text' || oT == 'textarea' || ( oT == 'password' && oAndPass ) || oT == 'datetime' || oT == 'datetime-local' || oT == 'date' || oT == 'month' || oT == 'week' || oT == 'time' || oT == 'number' || oT == 'range' || oT == 'email' || oT == 'url' ) {
					oE.value = oStr[y].replace(/%c/g,',').replace(/%p/g,'%');
					y++;
				} else if( oT == 'radio' || oT == 'checkbox' ) {
					oE.checked = oStr[y] ? true : false;
					y++;
				} else if( oT == 'select-one' ) {
					oE.selectedIndex = parseInt( oStr[y] );
					y++;
				} else if( oT == 'select-multiple' ) {
					for( var oO = oE.options, i = 0; oO[i]; i++ ) {
						oO[i].selected = oStr[y] ? true : false;
						y++;
					}
				}
			}
		}
	}
}

function recoverInputsJSON( formRef, oStr, oAndPass, oTypes, oNames ) {
	if( oStr ) {
		if( oNames ) {
			oNames = new RegExp((( oTypes > 3 )?'\\b(':'^(')+oNames.replace(/([\\\/\[\]\(\)\.\+\*\{\}\?\^\$\|])/g,'\\$1').replace(/,/g,'|')+(( oTypes > 3 )?')\\b':')$'),'');
			var oExclude = oTypes % 2;
		}

		formRef.reset();
		for( var x = 0, y = 0; formRef.elements[x]; x++ ) {
			if( formRef.elements[x].type ) {
				var oE = formRef.elements[x]; var oT = oE.type.toLowerCase();

				var oN = oE.id;
				if (typeof oN == 'undefined' || oN == '') {
					oN = oE.name;
				}

				if (typeof oN == 'undefined' || oN == '') {
					oN = oE.className;
				}

				if( oNames ) {
					var theAttr = ( oTypes > 3 ) ? formRef.elements[x].className : ( ( oTypes > 1 ) ? formRef.elements[x].id : formRef.elements[x].name );
					if( ( oExclude && theAttr && theAttr.match(oNames) ) || ( !oExclude && !( theAttr && theAttr.match(oNames) ) ) ) { continue; }

					if (typeof theAttr != 'undefined') {
						oN = theAttr;
					}
				}

				if (oStr[oN]) {
					if( oT == 'text' || oT == 'textarea' || ( oT == 'password' && oAndPass ) || oT == 'datetime' || oT == 'datetime-local' || oT == 'date' || oT == 'month' || oT == 'week' || oT == 'time' || oT == 'number' || oT == 'range' || oT == 'email' || oT == 'url' ) {
						oE.value = oStr[oN].replace(/%c/g,',').replace(/%p/g,'%');
					} else if( oT == 'radio' || oT == 'checkbox' ) {
						oE.checked = oStr[oN].checked ? true : false;
					} else if( oT == 'select-one' ) {
						oE.selectedValue = oStr[oN].value;
					} else if( oT == 'select-multiple' ) {
						for( var oO = oE.options, i = 0; oO[i]; i++ ) {
							for (var n = 0; oStr[oN][n]; n++ ) {
								if (oO[i].value == oStr[oN][n].value) {
									oO[i].selected = oStr[oN][n].selected ? true : false;
								}
							}
						}
					}
				}
			}
		}
	}
}

function retrieveCookie( cookieName ) {
	/* retrieved in the format
	cookieName4=value; cookieName3=value; cookieName2=value; cookieName1=value
	only cookies for this domain and path will be retrieved */
	var cookieJar = document.cookie.split( "; " );
	for( var x = 0; x < cookieJar.length; x++ ) {
		var oneCookie = cookieJar[x].split( "=" );
		if( oneCookie[0] == escape( cookieName ) ) { return oneCookie[1] ? unescape( oneCookie[1] ) : ''; }
	}
	return null;
}

function setCookie( cookieName, cookieValue, lifeTime, path, domain, isSecure ) {
	if( !cookieName ) { return false; }
	if( lifeTime == "delete" ) { lifeTime = -10; } //this is in the past. Expires immediately.
	/* This next line sets the cookie but does not overwrite other cookies.
	syntax: cookieName=cookieValue[;expires=dataAsString[;path=pathAsString[;domain=domainAsString[;secure]]]]
	Because of the way that document.cookie behaves, writing this here is equivalent to writing
	document.cookie = whatIAmWritingNow + "; " + document.cookie; */
	document.cookie = escape( cookieName ) + "=" + escape( cookieValue ) +
		( lifeTime ? ";expires=" + ( new Date( ( new Date() ).getTime() + ( 1000 * lifeTime ) ) ).toGMTString() : "" ) +
		( path ? ";path=" + path : "") + ( domain ? ";domain=" + domain : "") + 
		( isSecure ? ";secure" : "");
	//check if the cookie has been set/deleted as required
	if( lifeTime < 0 ) { if( typeof( retrieveCookie( cookieName ) ) == "string" ) { return false; } return true; }
	if( typeof( retrieveCookie( cookieName ) ) == "string" ) { return true; } return false;
}

function get_cookies_array() {

	var cookies = { };

	if (document.cookie && document.cookie != '') {
		var split = document.cookie.split(';');
		for (var i = 0; i < split.length; i++) {
			var name_value = split[i].split("=");
			name_value[0] = name_value[0].replace(/^ /, '');
			//cookies[decodeURIComponent(name_value[0])] = decodeURIComponent(name_value[1]);
			cookies[unescape(name_value[0])] = unescape(name_value[1]);
		}
	}

	return cookies;

}

/** Custom function to save and load search queries **/

function getQueryPrefix(isShared, isJson) {
	return 'custom' + (isShared ? 'Shared' : '') + 'Report' + (isJson ? 'JSON' : '');
}

function displayResults(data) {
	if (data) {
		var oData = null;
		try {
			oData = JSON.parse(data.responseText);
		} catch (e) {
			oData = data.responseText;
		}

		if (typeof oData == 'string') {
			$("#dialog-message-text").html(oData);
			$("#dialog-message").dialog({
				resizeable: false,
				height: 'auto',
				width: 400,
				modal: true,
				buttons: {
					"OK" : function() {
						$(this).dialog('close');
					}
					}
			});
		}

		if (oData !== null && oData.reports) {
			var reports = $('#searchReports');
			reports.html(oData.reports);
			applyReportFunctions();
		}

		if (oData !== null && oData.data) {
			if (oData.data.data) {
				recoverInputs( document.forms.searchForm, oData.data.data);
			}
		}
	}
}

function defaultActionComplete( data, status, request ) {
	displayResults(data);
}

function saveQuery(sName) {
	if (typeof sName == 'undefined' || sName == '') {
		sName = document.getElementById("nameQuery").value;
	}

	if (typeof sName == 'undefined' || sName == '') {
		alert('No report name specified');
	} else {
		var sData = getFormData(document.forms.searchForm,true);
		postAction( 'save', { name: sName, data: sData.Json });
	}
}

function shareQuery(id) {
	postAction( 'share', { id: id, shared: 'yes' });
}

function unshareQuery(id) {
	postAction( 'share', { id: id, shared: 'no' });
}

function loadQuery(id) {
	postAction( 'load', { id: id });
}

function deleteQuery(id) {
	postAction( 'delete', { id: id });
}

function postAction( sAction, sData, fComplete ) {
	var url = new URL(window.location.href);
	var url_params = url.searchParams;
	url_params.set('module', 'ajax');
	url_params.set('ac', 'customreports');
	url_params.set('op', sAction);
	url.search = url_params.toString();

	if (typeof fComplete == 'undefined') {
		fComplete = defaultActionComplete;
	}

	$.ajax({
		url: url.toString(),
		type: 'post',
		data: sData,
		complete: fComplete
	});
}

function convertReports() {
	var cookies = get_cookies_array();
	var searchReport = /customReport_(.*)/;
	for(var name in cookies) {
		isCustomReport = searchReport.test( name );

		if (isCustomReport) {
			var matches = name.match(searchReport);
			name = matches[1];

			recoverInputsString(document.forms.searchForm,retrieveCookie(matches[0]));
			saveQuery(name);
		}
	}

	document.forms.searchForm.reset();
}

function handleReportFunction(event) {
	event.preventDefault();

	var mode = '';
	var vars = this.id.split('_');
	if (vars.length == 3) {
		var mode = vars[2];
		var id   = vars[1];

		if (mode == 'load') {
			loadQuery(id);
		} else if (mode == 'share') {
			shareQuery(id);
		} else if (mode == 'unshare') {
			unshareQuery(id);
		} else if (mode == 'delete') {
			deleteQuery(id);
		}
	}
}

function applyReportFunctions() {
	$('.searchReportIcon').off('click').on('click', handleReportFunction);
}

$(function() {
	applyReportFunctions();
});
