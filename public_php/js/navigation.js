var _____WB$wombat$assign$function_____ = function(name) {return (self._wb_wombat && self._wb_wombat.local_init && self._wb_wombat.local_init(name)) || self[name]; };
if (!self.__WB_pmw) { self.__WB_pmw = function(obj) { this.__WB_source = obj; return this; } }
{
  let window = _____WB$wombat$assign$function_____("window");
  let self = _____WB$wombat$assign$function_____("self");
  let document = _____WB$wombat$assign$function_____("document");
  let location = _____WB$wombat$assign$function_____("location");
  let top = _____WB$wombat$assign$function_____("top");
  let parent = _____WB$wombat$assign$function_____("parent");
  let frames = _____WB$wombat$assign$function_____("frames");
  let opener = _____WB$wombat$assign$function_____("opener");

var navbar = document.getElementById("header-nav");
var navul = document.getElementById("nav-ul");
var pillow = document.getElementById('pillow');
var navbar_offset_height = navbar.offsetHeight;
var navPoints = document.getElementById("nav-points");

var linktoggle = document.getElementsByClassName('link-toggle')[0];
var wrapper = document.getElementById("wrapper");
var content = document.getElementById('content');
var scrollY;

var rPanel = document.getElementById("r-panel");
var rPhone = rPanel.firstElementChild;
var rMessage = rPanel.firstElementChild.nextElementSibling;
var rChat = rPanel.lastElementChild;
var rpItems = rPanel.getElementsByClassName('rp-name');
var rpiContent = rPanel.getElementsByClassName('rpi-content');

var caretz = document.getElementsByClassName('caret');

var modal = document.getElementById('myModal');
var modalBody = document.getElementsByClassName("modal-body")[0];
var informX = document.getElementById('inform').firstElementChild;
var feedback = document.getElementsByClassName("feedback")[0];
var modalText = document.getElementById('modalText');
var iMap = document.getElementById("iMap");
var iMapBox = document.getElementById("iMapBox");
var spanClose = modal.getElementsByClassName("close")[0];

function topFunction() {
	$('html, body').animate({scrollTop: 0}, 800);
	//document.body.scrollTop = 0;
    //document.documentElement.scrollTop = 0; 
}

function dropped() {
	navPoints.classList.remove("dropped");
	for (var i = 0; i < caretz.length; i++) {
			caretz[i].nextElementSibling.classList.remove("dm-on");
			caretz[i].textContent = "";
	}
	
	var dmrn = document.querySelectorAll('.dropdown-menu-right-next');
	if(dmrn.length > 0) {
		for(var i = 0; i < dmrn.length; i++) {
			dmrn[i].style.display = 'none';	
		}
	}
	if(document.getElementById('rightDropList')) {
	document.getElementById('rightDropList').style.color = "#2AC1A0";
	}
	
	if(document.getElementById('rightDropListUd')) {
	document.getElementById('rightDropListUd').style.color = "#2AC1A0";
	}
}


function droppedStill() {
	for (var i = 0; i < caretz.length; i++) {
			caretz[i].nextElementSibling.classList.remove("dm-on");
			caretz[i].textContent = "";
	}

}

window.onscroll = function() {
	viewBtnUp();
	if (location.href != "https://web.archive.org/web/20220618125443/https://pravosudie18.ru/advokat/kontakty" && location.href != "https://web.archive.org/web/20220618125443/https://pravosudie18.ru/urist/contacts") {
		viewRightPanel();
	}
};

function viewRightPanel() {
    if (document.body.scrollTop > 700 || document.documentElement.scrollTop > 700) {

        rPanel.style.display = "block";
    } else {
		if(rPanel.className != 'rpl') {
	
			rPanel.style.display = "none";
		}

    }
}

function viewBtnUp() {
    if (document.body.scrollTop > 498 || document.documentElement.scrollTop > 498) {
        document.getElementById("myBtn").style.display = "block";

    } else {
        document.getElementById("myBtn").style.display = "none";
  
    }
}

// function sticky_nav() {
//   if (window.pageYOffset >= navbar_offset_height +300 && navul.className == "toggle-off") {
//   	 // pillow.style.height = navbar_offset_height;
//   	navbar.classList.add("sticky");
//   	navul.style.padding = "0";
//   	navul.style.transition = "0.5s";


//   } else {
//     navbar.classList.remove("sticky");
//   	// pillow.style.height = "0px";
//   	navul.style.padding = "0.7em";
//   	navul.style.transition = "0s";
//   }
// };

function rpiContentOff() {
	for (var i = 0; i < rpItems.length; i++) {
			rpItems[i].lastElementChild.style.display = "none";
			rpItems[i].firstElementChild.classList.remove("rpi-active");
			rPhone.firstElementChild.innerHTML = "";
			rMessage.firstElementChild.innerHTML = "";
			rChat.firstElementChild.innerHTML = "";
	}
}

function show() {
	if(navul.className == "toggle-on") {
		navul.classList.remove("toggle-on");
		navul.classList.add("toggle-off");

		linktoggle.classList.remove("ltf");		
		linktoggle.classList.add("lto");

		content.classList.remove("hidden");
		navbar.classList.remove("relative");
		document.documentElement.scrollTop = scrollY; 
  	} 

	else {
	  	scrollY = document.documentElement.scrollTop;
	  	document.documentElement.scrollTop = '0';
		content.classList.add("hidden");

		navul.classList.remove("toggle-off");
		navul.classList.add("toggle-on");

		navbar.classList.add("relative");

		linktoggle.classList.remove("lto");		
		linktoggle.classList.add("ltf");
	}
}

function send_question_on() {
rpiContentOff();
modal.classList.add("send_question");
modalText.innerHTML = "<p>Если Вы хотите получить устную консультацию, в поле для текста укажите ваш номер телефона.</p>";
modalBody.appendChild(feedback);
feedback.style.display = "block";
modal.style.display = "block";
}

function send_question_close() {
modal.classList.remove("send_question");
informX.appendChild(feedback);
feedback.style.display = "none";
modal.style.display = "none";
modalText.innerHTML = "";
}

function view_map_on() {
modal.classList.add("view_map");
modalBody.appendChild(iMap);
modal.style.display = "block";
modalText.innerHTML = "<p>г. Ижевск, пер. Северный, 54</p>";
}

function view_map_close() {
modal.classList.remove("view_map");
iMapBox.appendChild(iMap);
modal.style.display = "none";
modalText.innerHTML = "";
}

function modal_close() {
	if(modal.className == "send_question") {
		send_question_close();
	}

	else {
		view_map_close();
	}
}

if(document.getElementById('rightDropList')!=null) {
		var crdl = document.getElementById('rightDropList');
		crdl.onclick = function crdl_click() {
	
		if (crdl.nextElementSibling.style.display == "" || crdl.nextElementSibling.style.display == "none") {
			crdl.nextElementSibling.style.display = 'block';
			crdl.style.color = "#4E8FF5";
			
			if(document.getElementById('rightDropListUd').nextElementSibling.style.display == 'block') {
				document.getElementById('rightDropListUd').nextElementSibling.style.display = 'none';
				document.getElementById('rightDropListUd').style.color = "#2AC1A0";
			}
		}

		else {
			crdl.nextElementSibling.style.display = "none";
			crdl.style.color = "#2AC1A0";
		}
	}
}

if(document.getElementById('rightDropListUd')!=null) {
		var crdl_ud = document.getElementById('rightDropListUd');
		crdl_ud.onclick = function crdl_ud_click() {
	
		if (crdl_ud.nextElementSibling.style.display == "" || crdl_ud.nextElementSibling.style.display == "none") {
			crdl_ud.nextElementSibling.style.display = 'block';
			crdl_ud.style.color = "#4E8FF5";
			if(document.getElementById('rightDropList').nextElementSibling.style.display == 'block') {
				document.getElementById('rightDropList').nextElementSibling.style.display = 'none';
				document.getElementById('rightDropList').style.color = "#2AC1A0";
			}

		}

		else {
			crdl_ud.nextElementSibling.style.display = "none";
			crdl_ud.style.color = "#2AC1A0";
		}
	}
}


window.addEventListener("click", function(e) {

	if (e.target.dataset.event == "toggle_menu") {
		show();
	}

	if (navPoints.classList == "dropped" && e.target.className != "caret" 
		&& e.target.parentNode.hasAttribute("name") == false && e.target.hasAttribute("name") != true 
		&& e.target.classList != "caret-right") {
		dropped();
	}

	if (rPanel.className == "rpl" && e.target.parentNode.className != "rp-name" 
		&& e.target.parentNode.className != "rpi-content" 
		&& e.target.className != "rpics") {
		rpiContentOff();
	}

	if (e.target == modal) {
		modal_close();	
	}



if (e.target.className == 'caret') {
	if(navPoints.classList != "dropped") {
	e.target.textContent = "";
	e.target.nextElementSibling.classList.add("dm-on");
	navPoints.classList.add("dropped");
	}
	
	else if (e.target.nextElementSibling.classList == "dropdown-menu dm-on") {
	e.target.nextElementSibling.classList.remove("dm-on");
	navPoints.classList.remove("dropped");
	event.target.textContent = "";
	}

	else {
	droppedStill();
	e.target.textContent = "";
	e.target.nextElementSibling.classList.add("dm-on");
	navPoints.classList.add("dropped");
	}
}
/*///////////////////////////*//*///////////////////////////*//*///////////////////////////*//*///////////////////////////*/

if(e.target.parentNode.className == 'rp-name') {
	if (rPanel.className === "") {
		rPanel.classList.add("rpl");
		e.target.nextElementSibling.style.display = 'block';
		e.target.classList.add("rpi-active");
		e.target.innerHTML = "";
	}

	else if (e.target.nextElementSibling.style.display == "block"){
		rPanel.classList.remove("rpl");
		e.target.nextElementSibling.style.display = "none";
		e.target.classList.remove("rpi-active");
		rPhone.firstElementChild.innerHTML = "";
		rMessage.firstElementChild.innerHTML = "";
		rChat.firstElementChild.innerHTML = "";
	}

	else {
		rpiContentOff();
		e.target.innerHTML = "";
		e.target.nextElementSibling.style.display = "block";
		e.target.classList.add("rpi-active");
		
	}

}


if (e.target.className == 'viewMap') {
	view_map_on(); 
}
if (e.target.className == 'sendQuestion') {
	 send_question_on();
}
if (e.target.className == 'close') {
	modal_close();
}

if (e.target.parentNode.id == 'myBtn') {
	topFunction();
}


});



}
/*
     FILE ARCHIVED ON 12:54:43 Jun 18, 2022 AND RETRIEVED FROM THE
     INTERNET ARCHIVE ON 19:51:18 Mar 19, 2025.
     JAVASCRIPT APPENDED BY WAYBACK MACHINE, COPYRIGHT INTERNET ARCHIVE.

     ALL OTHER CONTENT MAY ALSO BE PROTECTED BY COPYRIGHT (17 U.S.C.
     SECTION 108(a)(3)).
*/
/*
playback timings (ms):
  captures_list: 0.563
  exclusion.robots: 0.021
  exclusion.robots.policy: 0.009
  esindex: 0.01
  cdx.remote: 17.891
  LoadShardBlock: 90.517 (3)
  PetaboxLoader3.datanode: 222.207 (6)
  load_resource: 486.687 (2)
  PetaboxLoader3.resolve: 330.667 (2)
  loaddict: 33.628
*/