let visitorKeyHiddenInput = document.getElementById("clientify_vk");
let visitorValue = "";

if(document.cookie !== "undefined") {
	let name = "vk=";
	let decodedCookie = decodeURIComponent(document.cookie);
	let ca = decodedCookie.split(";");
	for (let i = 0; i < ca.length; i++) {
		let c = ca[i];
		while (c.charAt(0) == " ") {
				c = c.substring(1);
		}
		if (c.indexOf(name) == 0) {
				visitorValue = c.substring(name.length, c.length);
		}
	}
	if ( visitorValue ) {
		visitorKeyHiddenInput.setAttribute('value',visitorValue);
	}
}