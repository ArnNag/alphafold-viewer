<?php
require_once ("../common.php");

scopConnect();

if (isset($_GET['name']))
    $name = $_GET['name'];

function getAF2Link($name, $text=FALSE) {
    if ($text===FALSE)
        $text = $name;
    $accession = explode("-", $name)[1];
    return ("<a href=\"https://alphafold.ebi.ac.uk/entry/".$accession."\">".$text."</a>");
}
?>
<html>
    <head>
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/d3-color@3"></script>
	<script src="https://cdn.jsdelivr.net/npm/d3-interpolate@3"></script>
	<script src="https://cdn.jsdelivr.net/npm/d3-scale-chromatic@3"></script>
	<script type="text/javascript" src="rcsb_molstar/rcsb-molstar/build/dist/viewer/rcsb-molstar.js"></script>
	<style>
		.chartWrapper {
		  position: relative;
		}

		.chartWrapper > canvas {
		  position: absolute;
		  left: 0;
		  top: 0;
		  pointer-events: none;
		}

		.chartAreaWrapper {
		  height: 200px;
		    overflow-y: scroll;
		}

		.model-info { 
			grid-column: 1 / 3;
			grid-row: 1;
			text-align: center;
		}
		.viewer-wrapper { 
			grid-column: 1;
			grid-row: 2;
		}
		.hit-info { 
			grid-column: 2;
			grid-row: 2;
		}
		.structure-hits { 
			grid-column: 1 / 3;
			grid-row: 3;
		}
		.sequence-hits { 
			grid-column: 1 / 3;
			grid-row: 4;
		}

		.grid-container {
		  display: grid;
		  grid-template-columns: 1fr 1fr;
		  grid-template-rows: 1fr 25vh 25vh 25vh;
		  gap: 10px;
		  background-color: #2196F3;
		  padding: 10px;
		}
	</style>
    </head>


   <body style="background-color:white;">
	<?php 
	printCommonHeader("tab1",$scopReleaseID);
	print("<div class=\"container\">");
	if (!isset($name)) {
	    print("<p><div class=\"alert alert-danger\">\n");
	    print("<h2>Error</h2>\n");
	    print("No Alphafold file given");
	    print("</div>\n");
	}

	?>
	<script>
	$.cookie("lastBrowse", "<?php echo getLocalPDBURL($code,$scopReleaseID); ?>");
	</script>
	<?php

	// get basic info

	$path = "/mnt/net/ipa.jmcnet/data/h/anagle/af2_human_v3/".$name."-model_v3.cif.gz";
	$ln_path = "rcsb_molstar/".$name."-model_v3.cif";
	$awk_input = "zcat ".$path." | awk '$1 == \"_entity.pdbx_description\" || $1 == \"_ma_qa_metric_global.metric_value\" {\$1=\"\\x22\"$1\"\\x22:\"; print $0 \",\"'}";
	$af2_data = "{".substr(shell_exec($awk_input), 0, -2)."}";
	$decoded_af2_data = json_decode($af2_data);
	$description = $decoded_af2_data->{'_entity.pdbx_description'};
	$global_metric_value = $decoded_af2_data->{'_ma_qa_metric_global.metric_value'};
?>

<div class="grid-container" style="width:80vw; margin-left: auto; margin-right: auto;">
<div class="model-info">
	<div style="display: inline-block; text-align: left">
<?php
	print("<h2>AlphaFold entry ".$name."</h2>\n");
	print(getAF2Link($name,"View $name on AlphaFold Protein Structure Database site<br>\n"));
	print("<b>Description</b>: $description<br>\n");
	print("<b>Global pLDDT</b>: ".(is_null($global_metric_value) ? "N/A" : $global_metric_value)."<br>\n");
?>
</div>
</div>

	<div class="viewer-wrapper" style="position: relative; background-color: yellow">
		hi			
	    <div id="sviewer" style="height: 400px"></div>
	</div>

	<div class="hit-info" style="background-color: #90EE90">
		bye
		<div id="Hit"></div>
	</div>


	<div class="structure-hits">
		<div class="chartAreaWrapper">
			<div class="chartAreaWrapper2">
				desert
			</div>
		</div>
	</div>

	<div class="sequence-hits">
		<div class="chartAreaWrapper">
			<div class="chartAreaWrapper2">
				desert
			</div>
		</div>
	</div>

	</div>

	<?php
	printCommonFooter($scopReleaseID);
	mysqli_close($mysqlLink);
	?>
    </body>

</html>


