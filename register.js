	var loginService = document.domain=='localhost'||document.domain=='127.0.0.1'? './pss_login.php': 'https://www.elettra.eu/service/pss/pss_login.php';

	document.domain = document.domain=='localhost'||document.domain=='127.0.0.1'? document.domain: 'elettra.eu';

	function popupParameters() {
		var popupWidth = 300;
		var popupHeight = 400;
		var width = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
		var height = window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight;
		// console.log('width: '+width+', width-popupWidth: '+(width-popupWidth)+', window.screenX: '+window.screenX);
		return 'width='+popupWidth+',height='+popupHeight+',left='+(window.screenX+(width-popupWidth)/2)+',top='+(window.screenY+(height-popupHeight)/2);
	}

	setTimeout(myRefresh, 10*60*1000);
	var refreshEnabled = true;

	var popupWindow = null;

	function getQueryParams(qs) {
		qs = qs.split("+").join(" ");
		var params = {},
				tokens,
				re = /[?&]?([^=]+)=([^&]*)/g;
		while (tokens = re.exec(qs)) {
			params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
		}
		return params;
	}

	function myRefresh() {
		var $_GET = getQueryParams(document.location.search);
		if (refreshEnabled) {
			window.location = typeof($_GET['backoffice']) !== 'undefined'? './register.php?backoffice=': './register.php';
		}
	}

	function myInsert() {
		window.location = './register.php';
	}

	function multi_input() {
		window.location = './register.php?multi_input';
	}

	function pson() {
		message = "Presa visione dell'istruzione operativa relativa agli accessi controllati in tunnel Elettra con PS accesi";
		if (document.getElementById('note').value.indexOf(message) > -1);
		document.getElementById('note').value = document.getElementById('note').value + ' ' + message;
		return false;
	}

	function receiveMessage(e) {
		var origin = e.origin || e.originalEvent.origin;
		if (origin=='https://www.elettra.eu') document.getElementById(e.data).submit();
	}	

	window.addEventListener("message", receiveMessage, false);

	function myDate(dateString) {
		var t = dateString.split(' ');
		if (t.length != 2) return 'Invalid Date';
		var Ymd = t[0].split('-');
		if (Ymd.length != 3) return 'Invalid Date';
		var Hi = t[1].split(':');
		if (Hi.length != 2) return 'Invalid Date';
		return new Date(Ymd[0], Ymd[1]-1, Ymd[2], Hi[0], Hi[1]);
	}

	function open_input_multi(machine) {
		if (document.getElementById('start').value.length==0) {alert("ERRORE!\nOrario d'ingresso mancante"); return;}
		var currentdate = new Date();
		var start = myDate(document.getElementById('start').value);
		if (start=='Invalid Date') {alert("ERRORE!\nOrario d'ingresso non valido"); return;}
		if ((currentdate - start)/1000 > 8*86400) {alert("ERRORE!\nOrario d'ingresso piu' di sette giorni nel passato"); return;}
		if ((currentdate - start)/1000 < -3700) {alert("ERRORE!\nOrario d'ingresso nel futuro"); return;}
		if (document.getElementById('name').value.length==0) {alert("ERRORE!\nOperatore mancante"); return;}
		if (document.getElementById('name_0').value.length==0) {alert("ERRORE!\nUsername mancante"); return;}
		for (var i='0'; i<12; i++) {
			if (document.getElementById('name_'+i).value.length>0 && typeof(userList) !== 'undefined' && userList.indexOf(document.getElementById('name_'+i).value)==-1) {alert("ERRORE!\nUsername: "+document.getElementById('name_'+i).value+" non valido"); return;}
			if (document.getElementById('badge_'+i).value=='personal' && typeof(dosimeteredUser) !== 'undefined' && dosimeteredUser.indexOf(document.getElementById('name_'+i).value)==-1) {alert("ATTENZIONE!\nUsername: "+document.getElementById('name_'+i).value+" non risulta essere dotato di badge personale");}
			if (document.getElementById('dosimeter_'+i).value=='personal' && typeof(dosimeteredUser) !== 'undefined' && dosimeteredUser.indexOf(document.getElementById('name_'+i).value)==-1) {alert("ATTENZIONE!\nUsername: "+document.getElementById('name_'+i).value+" non risulta essere dotato di dosimetro personale");}
		}
		if (machine=='elettra') {
			if (!(document.getElementById('place_psa').checked || document.getElementById('place_bo').checked || document.getElementById('place_ring').checked || document.getElementById('place_sa').checked)) {alert("ERRORE!\nLuogo di intervento mancante"); return;}
		}
		if (machine=='fermi') {
			if (!(document.getElementById('place_linac').checked || document.getElementById('place_uh').checked || document.getElementById('place_kgzc').checked)) {alert("ERRORE!\nLuogo di intervento mancante"); return;}
		}
		if (document.getElementById('badge_0').value.length==0) {alert("ERRORE!\nBadge mancante"); return;}
		if (document.getElementById('badge_0').value!='personal' && document.getElementById('badge_id_0').value.length==0) {alert("ERRORE!\nNumero di badge ospite o ronda mancante"); return;}
		if (document.getElementById('dosimeter_0').value.length==0) {alert("ERRORE!\nDosimetro mancante"); return;}
		if (document.getElementById('dosimeter_0').value!='personal' && document.getElementById('dosimeter_id_0').value.length==0) {alert("ERRORE!\nNumero di dosimetro ospite mancante"); return;}
		if (document.getElementById('dosimeter_0').value!='personal' && document.getElementById('dosimeter_value_0').value.length==0) {alert("ERRORE!\nLettura dosimetro ospite mancante"); return;}
		var myaddress = loginService+'?pss_username='+document.getElementById('name').value+'&pss_token=-1';
		if (!document.getElementById('confirm')) return;
		document.getElementById('confirm').value = 'ok';
		if (!window.popupWindow) {    // has not yet been defined
			popupWindow = window.open(myaddress, 'events', popupParameters());
		}
		else {   // has been defined
			if (!popupWindow.closed) {
				popupWindow.focus();
			}
			else {
				popupWindow = window.open(myaddress, 'events', popupParameters());
			}
		}
	}

	function open_input(machine) {
		if (document.getElementById('start').value.length==0) {alert("ERRORE!\nOrario d'ingresso mancante"); return;}
		var currentdate = new Date(); 
		var start = myDate(document.getElementById('start').value);
		if (start=='Invalid Date') {alert("ERRORE!\nOrario d'ingresso non valido"); return;}
		if ((currentdate - start)/1000 > 365*86400) {alert("ERRORE!\nOrario d'ingresso piu' di un anno nel passato"); return;}
		if ((currentdate - start)/1000 < -3700) {alert("ERRORE!\nOrario d'ingresso nel futuro"); return;}
console.log('name');
		if (document.getElementById('name').value.length==0) {alert("ERRORE!\nUsername mancante"); return;}

		if (typeof(userList) !== 'undefined' && userList.indexOf(document.getElementById('name').value)==-1) {alert("ERRORE!\nUsername non valido"); return;}
		if (typeof(entered) !== 'undefined' && entered.indexOf(document.getElementById('name').value) != -1) {alert("ERRORE!\nOperatore gia' registrato all'interno di una zona controllata"); return;}
		if (machine=='elettra') {
			if (!(document.getElementById('place_psa').checked || document.getElementById('place_bo').checked || document.getElementById('place_ring').checked || document.getElementById('place_sa').checked)) {alert("ERRORE!\nLuogo di intervento mancante"); return;}
		}
		if (machine=='fermi') {
			if (!(document.getElementById('place_linac').checked || document.getElementById('place_uh').checked || document.getElementById('place_kgzc').checked)) {alert("ERRORE!\nLuogo di intervento mancante"); return;}
		}
console.log('badge');
		if (document.getElementById('badge').value.length==0) {alert("ERRORE!\nBadge mancante"); return;}
		if (document.getElementById('badge').value!='personal' && document.getElementById('badge_id').value.length==0) {alert("ERRORE!\nNumero di badge ospite o ronda mancante"); return;}
		if (document.getElementById('badge').value=='personal' && typeof(dosimeteredUser) !== 'undefined' && dosimeteredUser.indexOf(document.getElementById('name').value)==-1) {alert("ATTENZIONE!\nUsername: "+document.getElementById('name').value+" non risulta essere dotato di badge personale");}
		if (badge[document.getElementById('badge_id').value]) {r = confirm("ATTENZIONE!\nSi prega di ricontrollare il NUMERO del badge ospite\nin quanto risulta gia' assegnato"); if (r != true) return;}
		if (document.getElementById('dosimeter').value.length==0) {alert("ERRORE!\nDosimetro mancante"); return;}
		if (document.getElementById('dosimeter').value!='personal' && document.getElementById('dosimeter_id').value.length==0) {alert("ERRORE!\nNumero di dosimetro ospite mancante"); return;}
		if (document.getElementById('dosimeter').value!='personal' && document.getElementById('dosimeter_value').value.length==0) {alert("ERRORE!\nLettura dosimetro ospite mancante"); return;}
		if (document.getElementById('dosimeter').value=='personal' && typeof(dosimeteredUser) !== 'undefined' && dosimeteredUser.indexOf(document.getElementById('name').value)==-1) {alert("ATTENZIONE!\nUsername: "+document.getElementById('name').value+" non risulta essere dotato di dosimetro personale");}
		if (dosimeter[document.getElementById('dosimeter_id').value] && dosimeter[document.getElementById('dosimeter_id').value][2]==-1) {r = confirm("ATTENZIONE!\nSi prega di ricontrollare il NUMERO del dosimetro ospite\nin quanto risulta gia' assegnato"); if (r != true) return;}
		var dosimeter_diff = dosimeter[document.getElementById('dosimeter_id').value]? document.getElementById('dosimeter_value').value - dosimeter[document.getElementById('dosimeter_id').value][1]: -1;
		if (document.getElementById('dosimeter').value!='personal' && (dosimeter_diff<0 || dosimeter_diff>1)) {r = confirm("ATTENZIONE\nLettura dosimetro ospite a rischio di errore.\nSi prega di ricontrollare il NUMERO e la LETTURA del dosimetro ospite e\npremere OK solo dopo aver verificato la correttezza di entrambi i valori."); if (r != true) return;}
		var myaddress = loginService+'?pss_username='+document.getElementById('name').value+'&pss_token=-1';
		if (!document.getElementById('confirm')) return;
console.log('ok');
		document.getElementById('confirm').value = 'ok';
		if (!window.popupWindow) {    // has not yet been defined
			popupWindow = window.open(myaddress, 'events', popupParameters());
		}
		else {   // has been defined
			if (!popupWindow.closed) {
				popupWindow.focus();
			}
			else {
				popupWindow = window.open(myaddress, 'events', popupParameters());
			}
		}
	}

	function validate_backoffice(machine) {
console.log('validate_backoffice');
		if (document.getElementById('start').value.length==0) {alert("ERRORE!\nOrario d'ingresso mancante"); return;}
		var currentdate = new Date(); 
console.log(currentdate);
		var start = myDate(document.getElementById('start').value);
console.log('start: '+start);
		if (start=='Invalid Date') {alert("ERRORE!\nOrario d'ingresso non valido"); return;}
		if ((currentdate - start)/1000 > 3653*86400) {alert("ERRORE!\nOrario d'ingresso piu' di 10 anni nel passato"); return;}
		if ((currentdate - start)/1000 < -3700) {alert("ERRORE!\nOrario d'ingresso nel futuro"); return;}
		if (document.getElementById('name').value.length==0) {alert("ERRORE!\nUsername mancante"); return;}
		// if (typeof(userList) !== 'undefined' && userList.indexOf(document.getElementById('name').value)==-1) {alert("ERRORE!\nUsername non valido"); return;}
		if (typeof(entered) !== 'undefined' && entered.indexOf(document.getElementById('name').value) != -1) {alert("ERRORE!\nOperatore gia' registrato all'interno di una zona controllata"); return;}
		if (machine=='elettra') {
			if (!(document.getElementById('place_psa').checked || document.getElementById('place_bo').checked || document.getElementById('place_ring').checked || document.getElementById('place_sa').checked)) {alert("ERRORE!\nLuogo di intervento mancante"); return;}
		}
		if (machine=='fermi') {
			if (!(document.getElementById('place_linac').checked || document.getElementById('place_uh').checked || document.getElementById('place_kgzc').checked)) {alert("ERRORE!\nLuogo di intervento mancante"); return;}
		}
		if (document.getElementById('badge').value.length==0) {alert("ERRORE!\nBadge mancante"); return;}
		if (document.getElementById('badge').value!='personal' && document.getElementById('badge_id').value.length==0) {alert("ERRORE!\nNumero di badge ospite o ronda mancante"); return;}
		if (document.getElementById('badge').value=='personal' && typeof(dosimeteredUser) !== 'undefined' && dosimeteredUser.indexOf(document.getElementById('name').value)==-1) {alert("ATTENZIONE!\nUsername: "+document.getElementById('name').value+" non risulta essere dotato di badge personale");}
		if (badge[document.getElementById('badge_id').value]) {r = confirm("ATTENZIONE!\nSi prega di ricontrollare il NUMERO del badge ospite"); if (r != true) return;}
		if (document.getElementById('dosimeter').value.length==0) {alert("ERRORE!\nDosimetro mancante"); return;}
		if (document.getElementById('dosimeter').value!='personal' && document.getElementById('dosimeter_id').value.length==0) {alert("ERRORE!\nNumero di dosimetro ospite mancante"); return;}
		if (document.getElementById('dosimeter').value!='personal' && document.getElementById('dosimeter_value').value.length==0) {alert("ERRORE!\nLettura dosimetro ospite mancante"); return;}
		if (document.getElementById('dosimeter').value=='personal' && typeof(dosimeteredUser) !== 'undefined' && dosimeteredUser.indexOf(document.getElementById('name').value)==-1) {alert("ATTENZIONE!\nUsername: "+document.getElementById('name').value+" non risulta essere dotato di dosimetro personale");}
		if (dosimeter[document.getElementById('dosimeter_id').value] && dosimeter[document.getElementById('dosimeter_id').value][2]==-1) {r = confirm("ATTENZIONE!\nSi prega di ricontrollare il NUMERO del dosimetro ospite"); if (r != true) return;}
		var dosimeter_diff = dosimeter[document.getElementById('dosimeter_id').value]? document.getElementById('dosimeter_value').value - dosimeter[document.getElementById('dosimeter_id').value][1]: -1;
		if (document.getElementById('dosimeter').value!='personal' && (dosimeter_diff<0 || dosimeter_diff>1)) {r = confirm("ATTENZIONE\nLettura dosimetro ospite a rischio di errore.\nSi prega di ricontrollare il NUMERO e la LETTURA del dosimetro ospite e\npremere OK solo dopo aver verificato la correttezza di entrambi i valori."); if (r != true) return;}
		if (document.getElementById('stop').value.length==0) {alert("ERRORE!\nOrario d'uscita mancante"); return;}
		var stop = new Date(document.getElementById('stop').value);
console.log('stop: '+stop);
		if (stop=='Invalid Date') {alert("ERRORE!\nOrario d'uscita non valido"); return;}
console.log(document.getElementById('confirm'));
		if (!document.getElementById('confirm')) return;
console.log('confirmed');
		document.getElementById('confirm').value = 'ok';
	}

	function validate_del(username) {
		var myaddress = loginService+'?pss_username='+username+'&pss_token=-2';
		if (!document.getElementById('confirm_del')) return;
		document.getElementById('confirm_del').value = 'ok';
		if (!window.delWindow) {    // has not yet been defined
			delWindow = window.open(myaddress, 'events', popupParameters());
		}
		else {   // has been defined
			if (!delWindow.closed) {
				delWindow.focus();
			}
			else {
				delWindow = window.open(myaddress, 'events', popupParameters());
			}
		}
	}

	function open_validation(username, token) {
		if (document.getElementById('stop_'+token).value.length==0) {alert("ERRORE!\nOrario d'uscita mancante"); return;}
		/*
		var currentdate = new Date(); 
		var stop = new Date(document.getElementById('stop_'+token).value);
		if (stop=='Invalid Date') {alert("ERRORE!\nOrario d'uscita non valido"); return;}
		var start = new Date(document.getElementById('start_'+token).value);
		if (stop <= start) {alert("ERRORE!\nOrario d'uscita non successivo a quello d'ingresso"); return;}
		if ((currentdate - stop)/1000 < -3700) {alert("ERRORE!\nOrario d'uscita nel futuro"); return;}
		*/
		if (document.getElementById('dosimeter_exitvalue_'+token) && document.getElementById('dosimeter_exitvalue_'+token).value.length==0) {alert("ERRORE!\nLettura dosimetro mancante"); return;}
		if (document.getElementById('dosimeter_id_'+token)) {
			var dosimeter_diff = dosimeter[document.getElementById('dosimeter_id_'+token).value]? document.getElementById('dosimeter_exitvalue_'+token).value - dosimeter[document.getElementById('dosimeter_id_'+token).value][0]: -1;
			if (document.getElementById('dosimeter_exitvalue_'+token) && (dosimeter_diff<0 || dosimeter_diff>1)) {r = confirm("ATTENZIONE\nLettura dosimetro ospite a rischio di errore.\nSi prega di ricontrollare il NUMERO e la LETTURA del dosimetro ospite e\npremere OK solo dopo aver verificato la correttezza di entrambi i valori."); if (r != true) return;}
		}
		var currentdate = new Date(); 
		var start = new Date(document.getElementById('start_'+token).value);
		var stop = new Date(document.getElementById('stop_'+token).value);
		if ((stop - start) < 0) {alert("ERRORE!\nOrario d'uscita precedente a quello d'ingresso"); return;}
		if ((currentdate - stop)/1000 < -3700) {alert("ERRORE!\nOrario d'uscita nel futuro"); return;}
		var myaddress = loginService+'?pss_username='+username+'&pss_token='+token;
		if (!document.getElementById('confirm_'+token)) return;
		document.getElementById('confirm_'+token).value = 'ok';
		if (!window.popupWindow) {    // has not yet been defined
			popupWindow = window.open(myaddress, 'events', popupParameters());
		}
		else {   // has been defined
			if (!popupWindow.closed) {
				popupWindow.focus();
			}
			else {
				popupWindow = window.open(myaddress, 'events', popupParameters());
			}
		}
	}

	function open_edit(token) {
		window.location = './register.php?edit='+token;
	}

	function mydate(elementId, savedBy) {
		var currentdate = new Date(); 
		document.getElementById(elementId).value = 
			currentdate.getFullYear() + "-" + 
			(currentdate.getMonth()>8? '': '0') + (currentdate.getMonth()+1)  + "-"  + 
			(currentdate.getDate()>9? '': '0') + currentdate.getDate() + " " + 
			(currentdate.getHours()>9? '': '0') + currentdate.getHours() + ":"  + 
			(currentdate.getMinutes()>9? '': '0') + currentdate.getMinutes();
		changeElement(elementId, savedBy);
	}

	function changeElement(elementId, applyTo) {
		refreshEnabled = false;
		setTimeout(myInsert, 10*60*1000);
		$('button').removeClass('btn-danger');
		$('button').addClass('btn-primary');
		if (document.getElementById(elementId).value.length>0) {
			$(applyTo).removeClass('btn-primary');
			$(applyTo).addClass('btn-danger');
		}
	}

	function changeBadge(id) {
		var myid = (typeof id !== "undefined")? '_'+id: '';
		if (document.getElementById('badge'+myid).value!='personal' && document.getElementById('badge'+myid).value!='') {
			document.getElementById('badge_number'+myid).style.display = 'block';
			document.getElementById('badge_id'+myid).required = true;
		}
		else {
			document.getElementById('badge_number'+myid).style.display = 'none';
			document.getElementById('badge_id'+myid).required = false;
		}
		changeElement('badge'+myid, '#save');
	}

	function changeDosimeter(id) {
		var myid = (typeof id !== "undefined")? '_'+id: '';
		if (document.getElementById('dosimeter'+myid).value!='personal' && document.getElementById('dosimeter'+myid).value!='') {
			document.getElementById('dosimeter_number'+myid).style.display = 'block';
			document.getElementById('dosimeter_value'+myid).style.display = 'block';
			document.getElementById('dosimeter_id'+myid).required = true;
			document.getElementById('dosimeter_value'+myid).required = true;
		}
		else {
			document.getElementById('dosimeter_number'+myid).style.display = 'none';
			document.getElementById('dosimeter_value'+myid).style.display = 'none';
			document.getElementById('dosimeter_id'+myid).required = false;
			document.getElementById('dosimeter_value'+myid).required = false;
		}
		changeElement('dosimeter'+myid, '#save');
	}
