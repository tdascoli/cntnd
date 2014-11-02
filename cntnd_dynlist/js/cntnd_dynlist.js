/* (C) Thomas D'Ascoli */    
function del(form,field,id){
	document.getElementById(field).value='delete';
	document.getElementById('DYNLIST_delete').value=id;
	document.getElementById(form).submit();
}
function swap(form,field,id1,id2){
	document.getElementById(field).value='swap';
	document.getElementById('DYNLIST_swap_id1').value=id1;
	document.getElementById('DYNLIST_swap_id2').value=id2;
	document.getElementById(form).submit();
}
function speichern(form,action){
	document.getElementById(action).value='save';
	document.getElementById(form).submit();
}