<!DOCTYPE html>
<html>
<head>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
<style>
body{
	display:none;
}
</style>
<script>
$(document).ready(()=>{
	$("body").show();
});
<?php if(isset($_GET['airport']) && isset($_GET['type'])){?>
$(document).ready(()=>{
	generate_table();
	setInterval(generate_table,15000);
});
var updatedAt = 0;
async function get_data(){
	var url = "http://localhost/ivao/whazzup.php";
	var data = await $.get(url, {"airport":"<?php echo $_GET['airport'];?>"});
	var type = "<?php echo $_GET['type'];?>";
	if(data){
		try{
			var parsed = JSON.parse(data);
			if(parsed.message == "forbidden"){
				$("#table").hide();
				$("#forbidden").show();
				return false;
			}
			updatedAt = parsed.updatedAt;
			if(type=="arrivals"){
				return parsed.arrivals;
			} else if(type=="departures") {
				return parsed.departues;
			}
		} catch(e) {
			console.log(e);
			console.log(data);
		}
	}
	return false;
}
function get_time(seconds){
	var hours = Math.floor(seconds / 3600);
	var minutes = Math.floor((seconds % 3600) / 60);
	if(hours<10){
		hours = "0"+hours;
	}
	if(minutes<10){
		minutes = "0"+minutes;
	}
	return hours+":"+minutes;
}
async function generate_table(){
	var data = await get_data();
	var html = "";
	if(data && data.length > 0){
		for(var flight of data){
			switch (flight.state){
				case "Initial Climb":
					flight.state="Departed";
					break;
				case "Approach":
					flight.state="Approaching";
					break;
				case "On Blocks":
					flight.state="Landed";
					break;
			}
			html+='<tr><td>'+flight.callsign+'</td><td>'+flight.departureId+'</td><td>'+get_time(flight.estimatedTime)+'</td><td>'+flight.state+'</td></tr>';
		}
	} else {
		html = '<tr><th colspan="4" class="text-center">No flights.</th></tr>';
	}
	$("#tbody").html(html);
	$("#update").text("Last update: "+get_time(updatedAt));
}
<?php }?>
</script>
<title>Airport information</title>
</head>
<body>
<?php 
if(!isset($_GET['airport']) && !isset($_GET['type'])){
	echo '<div class="alert alert-danger">No airport is selected.</div>';
} else { ?>
	<div>
		<div class="alert alert-danger" style="display:none;" id="forbidden">This airport is not allowed. Please contact IL-WM.</div>
		<table class="table table-bordered" id="table">
		<thead>
		<tr><th>Flight</th>
		<?php if($_GET['type'] == "arrivals"){
			echo '<th>Comming from</th>';
		} else if ($_GET['type'] == "departures"){
			echo '<th>Departing to</th>';
		}?>
		<th>Estimated time</th><th>State</th></tr>
		</thead>
		<tbody id="tbody">
		</tbody>
		<tfoot>
		<tr><th colspan="4" id="update"></th></tr>
		</tfoot>
		</table>
	</div>
<?php }?>
</body>
</html>