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
	<link rel="stylesheet" type="text/css" href="rcsb-molstar/build/dist/viewer/rcsb-molstar.css" />
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

		.model-info { grid-area: model; }
		.viewer-wrapper { grid-area: viewer; }
		.hit-info { grid-area: hit; }
		.structure-hits-wrapper { grid-area: structure; }
		.sequence-hits-wrapper { grid-area: sequence; }

		.grid-container {
		  display: grid;
		  grid-template-areas:
		    'model model model model'
		    'viewer viewer viewer hit'
		    'structure structure structure structure'
		    'sequence sequence sequence sequence';
		  gap: 10px;
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

<div class="grid-container">
	<div class="model-info" style="text-align: center">
	<div style="display: inline-block; text-align: left">
	<?php
		print("<h2>AlphaFold entry ".$name."</h2>\n");
		print(getAF2Link($name,"View $name on AlphaFold Protein Structure Database site<br>\n"));
		print("<b>Description</b>: $description<br>\n");
		print("<b>Global pLDDT</b>: ".(is_null($global_metric_value) ? "N/A" : $global_metric_value)."<br>\n");
	?>
	</div>
	</div>
<?php 
		$query = "SELECT astral_domain.header, astral_domain.sid, blast_log10_e, pct_identical, seq1_start, seq1_length, seq2_start, seq2_length, scop_node.sunid
FROM model_structure JOIN model_structure_uniprot ON model_structure.id = model_structure_uniprot.model_structure_id
JOIN uniprot_seq ON model_structure_uniprot.uniprot_id = uniprot_seq.uniprot_id
JOIN astral_seq_blast ON uniprot_seq.seq_id = astral_seq_blast.seq1_id
JOIN astral_domain ON seq2_id = astral_domain.seq_id
JOIN scop_node ON astral_domain.node_id = scop_node.id
WHERE model_structure.cif_path = \"".$path."\"
AND (astral_domain.style_id = 1 or astral_domain.style_id = 3)
AND blast_log10_e < 0
GROUP BY seq2_id
ORDER BY blast_log10_e;
";
	$result = mysqli_query($mysqlLink, $query);
	$n = mysqli_num_rows($result);
	$query_res=[];
	$hit_res=[];
	$seq_ids=[];
	$seq2_lens=[];
	$e_values=[];
	$colors=[];
	$sunids=[];
	$e_value_cutoff = -3;
	for ($x = 0; $x < $n; $x++) {
		$row = mysqli_fetch_assoc($result);
		$seq_ids[] = $row['sid'];
		$e_values[] = floatval($row["blast_log10_e"]);
		if ($row["blast_log10_e"] < $e_value_cutoff) {
			$colors[] = "green";
		} else {
			$colors[]= "blue";
		}
		$seq1_end = $row["seq1_start"] + $row["seq1_length"];
		$query_res[] = [intval($row["seq1_start"]), $seq1_end];
		$seq2_end = $row["seq2_start"] + $row["seq2_length"];
		$hit_res[] = [intval($row["seq2_start"]), $seq2_end];
		$sunids[] = $row["sunid"];
		$seq2_lens[] = $row["seq2_length"];
	} 
	/* print(json_encode($resdata)); */
	/* print(json_encode($seq_ids)); */
	/* print(json_encode($e_values)); */
	/* print(json_encode($colors)); */
	$structure_query = "SELECT astral_domain.sid, scop_node.sunid, model_start, model_end, domain_start, domain_end, p_value, translate_x, translate_y, translate_z, rotate_1_1, rotate_1_2, rotate_1_3, rotate_2_1, rotate_2_2, rotate_2_3, rotate_3_1, rotate_3_2, rotate_3_3
		FROM model_structure
JOIN model_vs_domain_structure_alignment AS m_vs_d ON model_structure.id = m_vs_d.model_id
JOIN astral_domain ON m_vs_d.domain_id = astral_domain.id
JOIN scop_node ON astral_domain.node_id = scop_node.id
WHERE model_structure.cif_path = \"".$path."\"
ORDER BY p_value;";
	$structure_result = mysqli_query($mysqlLink, $structure_query);
	$structure_n = mysqli_num_rows($structure_result);
	$struct_sids=[];
	$rot_mats=[];
	$p_vals=[];
	$struct_model_res=[];
	$struct_domain_res=[];

	for ($x = 0; $x < $structure_n; $x++) {
		$row = mysqli_fetch_assoc($structure_result);
		$struct_sids[] = $row['sid'];
		$rot_mats[] = [floatval($row['rotate_1_1']), floatval($row['rotate_1_2']), floatval($row['rotate_1_3']),  0, floatval($row['rotate_2_1']), floatval($row['rotate_2_2']), floatval($row['rotate_2_3']), 0, floatval($row['rotate_3_1']), floatval($row['rotate_3_2']), floatval($row['rotate_3_3']), 0, floatval($row['translate_x']), floatval($row['translate_y']), floatval($row['translate_z']), 1];
		$p_vals[] = floatval($row['p_value']);
		$struct_domain_res[] = [intval($row["domain_start"]), intval($row["domain_end"])];
		$struct_model_res[] = [intval($row["model_start"]), intval($row["model_end"])];
	} 
	/* console_log(json_encode($rot_mats[0])); */
	$pxperhit = 25;
	?>


	<div class="viewer-wrapper" style="position: relative; background-color: yellow">
	    <div id="viewer" style="height: 400px"></div>
	</div>

	<div class="hit-info" style="width: 200px; background-color: #90EE90">
		<div id="Hit"></div>
	</div>

	<script type="text/javascript">
	    const ln_path = "<?php print($ln_path) ?>";
	    const viewer = new rcsbMolstar.Viewer('viewer', {
		layoutShowControls: false,
		viewportShowExpand: true,
		viewportShowSelectionMode: true,
		layoutShowSequence: true,
		showWelcomeToast: false,
		showStrucmotifSubmitControls: false,
		showSuperpositionControls: false,
		showStructureSourceControls: false,
		showMeasurementsControls: false,
		showStructureComponentControls: false,
	    });

	    const idMat = [1, 0, 0, 0,
		    0, 1, 0, 0, 
		    0, 0, 1, 0,
		    0, 0, 0, 1];
	    const translateMat = [1, 0, 0, 0,
		    0, 1, 0, 0, 
		    0, 0, 1, 0,
		    0, 40, 0, 1];

	    function superposeDomain(idx) {
		    const sid = sids[idx];
		    const rotMat = rotMats[idx];
		    const pdb_ln = 'rcsb_molstar/pdbstyle/'.concat(sid.slice(2,4), '/', sid, '.ent');
		    viewer.clear()
			    .then(() => viewer.loadStructureFromUrl(pdb_ln, 'pdb', false))
			    .then(() => viewer.loadStructureFromUrl(ln_path, 'mmcif', false, { props: {  assemblyId: '1' }, matrix: rotMat}))
			    .then(() => viewer.resetCamera(0));
	    }

	    const rotMats = <?php print(json_encode($rot_mats)) ?>;
	    const sids = <?php print(json_encode($struct_sids)) ?>;
	    superposeDomain(0);
		</script>

	<div class="structure-hits-wrapper">
		<div class="chartAreaWrapper">
			<div class="chartAreaWrapper2">
				<canvas id="struct-hits" style="height: <?php print(100 + $structure_n * $pxperhit)?>px"></canvas>
			</div>
		</div>
	</div>

	<!-- <div class="sequence-hits-wrapper"> -->
		<div class="chartAreaWrapper">
			<div class="chartAreaWrapper2">
				<canvas id="seq-hits" style="height: <?php print(100 + $n * $pxperhit)?>px"></canvas>
			</div>
		</div>
	<!-- </div> -->


	<script>

	  const structModelRes = <?php print(json_encode($struct_model_res)) ?>;
	  const structDomainRes = <?php print(json_encode($hit_res)) ?>;
	  const structSids = <?php print(json_encode($struct_sids)) ?>;
	  const structPVals = <?php print(json_encode($p_vals)) ?>;
	  const structColors = structPVals.map(val => d3.interpolateViridis(Math.pow(10, val + 2)));

	  const queryRes = <?php print(json_encode($query_res)) ?>;
	  const hit_res = <?php print(json_encode($hit_res)) ?>;
	  const seq_ids = <?php print(json_encode($seq_ids)) ?>;
	  const e_values = <?php print(json_encode($e_values)) ?>;
	  const sunids = <?php print(json_encode($sunids)) ?>;
	  const seq2_lens = <?php print(json_encode($seq2_lens)) ?>;
	  const colors = e_values.map(val => d3.interpolateViridis(Math.pow(10, val + 2)));

	  const structData = {
	  labels: structSids,
	    datasets: [{
	      label: 'Structure hits',
	      backgroundColor: structColors,
	      borderColor: 'rgb(255, 99, 132)',
	      data: structModelRes
	    }]
	  };

	  const structConfig = {
	    type: 'bar',
	    data: structData,
	    options: {
	      indexAxis: 'y',
	      maintainAspectRatio: false,
	      plugins: {
		tooltip: {
		  displayColors: false,
		  callbacks: {
		    afterBody: function(tooltipItem) {
		      let idx = tooltipItem[0]['dataIndex'];
		      msg = 'Query start: ' +  queryRes[idx][0]  + '\nQuery end: ' + queryRes[idx][1] +  '\nHit start: ' + hit_res[idx][0] + '\nHit end: ' + hit_res[idx][1] + '\n';
		      msg += "Log10 E_value: " + Math.round(structPValues[tooltipItem[0]['dataIndex']] * 100) / 100;
		      return msg;
		    }
		  }
		}
	      }, 
	      animation: false,
	      scales: {
	        xAxis: {
			position: 'top'
	        }
	      }
	    }
	  };

	  const structCanvas = document.getElementById('struct-hits');
	  const structChart = new Chart(structCanvas, structConfig);

	  const seqData = {
	  labels: seq_ids,
	    datasets: [{
	      label: 'Sequence hits',
	      backgroundColor: colors, 
	      borderColor: 'rgb(255, 99, 132)',
	      data: queryRes
	    }]
	  };

	  const seqConfig = {
	    type: 'bar',
	    data: seqData,
	    options: {
	      indexAxis: 'y',
	      maintainAspectRatio: false,
	      plugins: {
		tooltip: {
		  displayColors: false,
		  callbacks: {
		    afterBody: function(tooltipItem) {
		      let idx = tooltipItem[0]['dataIndex'];
		      msg = 'Query start: ' +  queryRes[idx][0]  + '\nQuery end: ' + queryRes[idx][1] +  '\nHit start: ' + hit_res[idx][0] + '\nHit end: ' + hit_res[idx][1] + '\n';
		      msg += "Log10 E_value: " + Math.round(e_values[tooltipItem[0]['dataIndex']] * 100) / 100;
		      return msg;
		    }
		  }
		}
	      }, 
	      animation: false,
	      scales: {
	        xAxis: {
			position: 'top'
	        }
	      }
	    }
	  };


	  const seqCanvas = document.getElementById('seq-hits');
	  const seqChart = new Chart(seqCanvas, seqConfig);

	  structCanvas.onclick = function(evt) {
	    let activePoints = seqChart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
	    let idx = activePoints[0]['index'];
	    superposeDomain(idx);
	  }

	  seqCanvas.onclick = function(evt) {
	    const hit_view = document.getElementById('Hit');
	    let activePoints = seqChart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
	    let idx = activePoints[0]['index'];
	    hit_view.innerHTML = '<div> Hit: <a href="https://scop.berkeley.edu/sunid=' + sunids[idx] + '">' +  seq_ids[idx] + '</a> </div> <div> Query start: ' +  queryRes[idx][0]  + '</div> <div> Query end: ' + queryRes[idx][1] + '</div> <div> Hit start: ' + hit_res[idx][0] + '</div> <div> Hit end: ' + hit_res[idx][1] + '</div> <div> log10 E-value: ' + e_values[idx] + '</div> <div> Coverage of target sequence: ' + ((hit_res[idx][1] - hit_res[idx][0]) / seq2_lens[idx]) + ' </div>';

	  }
	</script>
	</div>

	<?php
	printCommonFooter($scopReleaseID);
	mysqli_close($mysqlLink);
	?>
    </body>

</html>


