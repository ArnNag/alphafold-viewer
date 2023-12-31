<?php
require_once ("../common.php");

function console_log( $data ){
  echo '<script>';
  echo 'console.log('. json_encode( $data ) .')';
  echo '</script>';
}

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

		.chartAreaWrapper {
		  height: 25vh;
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
		  grid-template-columns: 80% 20%;
		  grid-template-rows: 1fr 40vh 25vh 25vh;
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

	// get model info

	$path = "/mnt/net/ipa.jmcnet/data/h/anagle/af2_human_v3/".$name."-model_v3.cif.gz";
	$model_ln_path = "rcsb_molstar/af2_human_v3_2/".$name."-model_v3.cif";

	// TODO: This command uses awk to directly read columns from the cif file. It works and is fast, but it might break. Once everything is converted to XML, it's better to use XML parsing utilities to extract this information.
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
		// Querying for sequence hits and putting the relevant hit information in PHP arrays
		$seq_hit_query = "SELECT astral_domain.header, astral_domain.sid, blast_log10_e, pct_identical, seq1_start, seq1_length, seq2_start, seq2_length, scop_node.sunid
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
	$seq_result = mysqli_query($mysqlLink, $seq_hit_query);
	$seq_n = mysqli_num_rows($seq_result);
	$seq_model_res=[];
	$seq_domain_res=[];
	$seq_sids=[];
	$seq2_lens=[];
	$e_values=[];
	$seq_sunids=[];
	for ($x = 0; $x < $seq_n; $x++) {
		$row = mysqli_fetch_assoc($seq_result);
		$seq_sids[] = $row['sid'];
		$e_values[] = floatval($row["blast_log10_e"]);
		// TODO: seq1_length and seq2_length include gaps. Therefore, the following way of calculating where the hit ends is wrong. Need to use the astral_seq_blast_gap table to account for gaps.
		$seq1_end = $row["seq1_start"] + $row["seq1_length"];
		$seq_model_res[] = [intval($row["seq1_start"]), $seq1_end];
		$seq2_end = $row["seq2_start"] + $row["seq2_length"];
		$seq_domain_res[] = [intval($row["seq2_start"]), $seq2_end];
		$seq_sunids[] = $row["sunid"];
		$seq2_lens[] = $row["seq2_length"];
	} 
	/* print(json_encode($resdata)); */
	/* print(json_encode($seq_sids)); */
	/* print(json_encode($e_values)); */
	

	// Querying for structure hits and putting the relevant hit information in PHP arrays
	$structure_query = "SELECT astral_domain.sid, scop_node.sunid, pdb_chain.chain, model_start, model_end, domain_start_SEQRES, domain_end_SEQRES, z_score, translate_x, translate_y, translate_z, rotate_1_1, rotate_1_2, rotate_1_3, rotate_2_1, rotate_2_2, rotate_2_3, rotate_3_1, rotate_3_2, rotate_3_3
		FROM model_structure
JOIN model_vs_domain_structure_alignment AS m_vs_d ON model_structure.id = m_vs_d.model_id
JOIN astral_domain ON m_vs_d.domain_id = astral_domain.id
JOIN scop_node ON astral_domain.node_id = scop_node.id
JOIN link_pdb ON astral_domain.node_id = link_pdb.node_id
JOIN pdb_chain ON link_pdb.pdb_chain_id = pdb_chain.id
WHERE model_structure.cif_path = \"".$path."\"
AND structure_aligner_id = 1
ORDER BY z_score DESC LIMIT 50;";
	console_log($structure_query);
	$structure_result = mysqli_query($mysqlLink, $structure_query);
	$structure_n = mysqli_num_rows($structure_result);
	$struct_sids=[];
	$struct_sunids=[];
	$rot_mats=[];
	$z_scores=[];
	$struct_model_res=[];
	$struct_domain_res=[];
	$struct_chains=[];

	for ($x = 0; $x < $structure_n; $x++) {
		$row = mysqli_fetch_assoc($structure_result);
		$struct_sids[] = $row['sid'];
		$struct_sunids[] = $row['sunid'];
		$rot_mats[] = [floatval($row['rotate_1_1']), floatval($row['rotate_1_2']), floatval($row['rotate_1_3']),  0, floatval($row['rotate_2_1']), floatval($row['rotate_2_2']), floatval($row['rotate_2_3']), 0, floatval($row['rotate_3_1']), floatval($row['rotate_3_2']), floatval($row['rotate_3_3']), 0, floatval($row['translate_x']), floatval($row['translate_y']), floatval($row['translate_z']), 1];
		$z_scores[] = floatval($row['z_score']);
		$struct_domain_res[] = [intval($row["domain_start_SEQRES"]), intval($row["domain_end_SEQRES"])];
		$struct_model_res[] = [intval($row["model_start"]), intval($row["model_end"])];
		$struct_chains[] = $row['chain'];
	} 

	$pxperhit = 25; // How many vertical pixels to give each hit on the bar plot
	?>


	<div class="viewer-wrapper" style="position: relative; background-color: white">
	    <div id="viewer" style="height: 400px"></div>
	</div>

	<div class="hit-info">
		<div id="hit-panel"></div>
		<button id="model-full" type="button">Full model</button> 
		<button id="model-hit" type="button">Hit in model</button> 
		<button id="model-none" type="button">Hide model</button> 
		<button id="domain-full" type="button">Full domain</button> 
		<button id="domain-hit" type="button">Hit in domain</button> 
		<button id="domain-none" type="button">Hide domain</button> 
	</div>

	<script type="text/javascript">
	    const modelLnPath = "<?php print($model_ln_path) ?>";
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

	    // Read structure hit information from PHP arrays to JavaScript variables
	    const rotMats = <?php print(json_encode($rot_mats)) ?>;
	    const structSids = <?php print(json_encode($struct_sids)) ?>;
	    const structModelRes = <?php print(json_encode($struct_model_res)) ?>;
	    const structDomainRes = <?php print(json_encode($struct_domain_res)) ?>;
	    const structZScores = <?php print(json_encode($z_scores)) ?>;
	    const structColors = structZScores.map(val => d3.interpolateViridis((4 - val) + 1));
	    const structSunids = <?php print(json_encode($struct_sunids)) ?>;
	    const structChains = <?php print(json_encode($struct_chains)) ?>;

	    function superposeDomain(idx) {
		    const sid = structSids[idx];
		    const rotMat = rotMats[idx];
		    const domainLn = 'rcsb_molstar/pdbstyle/'.concat(sid.slice(2,4), '/', sid, '.ent');
		    viewer.clear()
			    .then(() => viewer.loadStructureFromUrl(domainLn, 'pdb', false))
			    .then(() => viewer.loadStructureFromUrl(modelLnPath, 'mmcif', false, { props: {  assemblyId: '1' }, matrix: rotMat}))
			    .then(() => viewer.makeHiddenComponent(0, structChains[idx], structDomainRes[idx][0], structDomainRes[idx][1])) // domain
			    .then(() => viewer.makeHiddenComponent(1, "A", structModelRes[idx][0], structModelRes[idx][1])) // model
			    .then(() => viewer.resetCamera(0));
	    }

	    superposeDomain(0);
	</script>

	<div class="structure-hits">
		<div class="chartAreaWrapper">
			<div>
				<canvas id="struct-hits" style="height: <?php print(100 + $structure_n * $pxperhit)?>px"></canvas>
			</div>
		</div>
	</div>

	<div class="sequence-hits">
		<div class="chartAreaWrapper">
			<div>
				<canvas id="seq-hits" style="height: <?php print(100 + $seq_n * $pxperhit)?>px"></canvas>
			</div>
		</div>
	</div>


	<script>



// Display structure hits on bar chart
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
		      let modelStart = structModelRes[idx][0];
		      let modelEnd = structModelRes[idx][1];
		      let domainStart = seqDomainRes[idx][0];
		      let domainEnd = seqDomainRes[idx][1];
		      let zScore = Math.round(structZScores[tooltipItem[0]['dataIndex']] * 100) / 100;
		      msg = `Model hit start: ${modelStart} \nModel hit end: ${modelEnd} \nDomain hit start: ${domainStart} \nDomain hit end: ${domainEnd} \nz-score: ${zScore}`;
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

	  // Read sequence hit information from PHP arrays to JavaScript variables
	  const seqModelRes = <?php print(json_encode($seq_model_res)) ?>;
	  const seqDomainRes = <?php print(json_encode($seq_domain_res)) ?>;
	  const seqSids = <?php print(json_encode($seq_sids)) ?>;
	  const e_values = <?php print(json_encode($e_values)) ?>;
	  const seqSunids = <?php print(json_encode($seq_sunids)) ?>;
	  const seq2_lens = <?php print(json_encode($seq2_lens)) ?>;
	  const seqColors = e_values.map(val => d3.interpolateViridis(Math.pow(10, val + 2)));

	  const seqData = {
	  labels: seqSids,
	    datasets: [{
	      label: 'Sequence hits',
	      backgroundColor: seqColors, 
	      borderColor: 'rgb(255, 99, 132)',
	      data: seqModelRes
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
		      msg = 'Model hit start: ' +  seqModelRes[idx][0]  + '\nModel hit end: ' + seqModelRes[idx][1] +  '\nDomain hit start: ' + seqDomainRes[idx][0] + '\nDomain hit end: ' + seqDomainRes[idx][1] + '\n';
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

	  // Show sequence hit panel when user clicks bar
	  seqCanvas.onclick = function(evt) {
	    let activePoints = seqChart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
	    let idx = activePoints[0]['index'];
	    const hit_view = document.getElementById('hit-panel');
	    let sunid = seqSunids[idx];
	    let sid = seqSids[idx];
	    let modelStart = seqModelRes[idx][0];
	    let modelEnd = seqModelRes[idx][1];
	    let domainStart = seqDomainRes[idx][0];
	    let domainEnd = seqDomainRes[idx][1];
	    let log10EVal = e_values[idx];
	    hit_view.innerHTML = `
<table>
  <tr>
    <th style="background-image: linear-gradient(to bottom, #dff0d8 0, #d0e9c6 100%); padding: 8px">Hit: <a href="https://scop.berkeley.edu/sunid=${sunid}"> ${sid}</a></th>
  </tr>
  <tr>
    <td style="background-color:#f7f7f7; padding: 4px">Model hit start: ${modelStart}</td>
  </tr>
  <tr>
    <td style="background-color:#f7f7f7; padding: 4px">Model hit end: ${modelEnd}</td>
  </tr>
    <tr>
    <td style="background-color:#f7f7f7; padding: 4px">Domain hit start: ${domainStart}</td>
  </tr>
    <tr>
    <td style="background-color:#f7f7f7; padding: 4px">Domain hit end: ${domainEnd}</td>
  </tr>
    <tr>
    <td style="background-color:#f7f7f7; padding: 4px">log10 E-value: ${log10EVal}</td>
  </tr>
    <tr>
    <td style="background-color:#f7f7f7; padding: 4px">Coverage of target sequence: TODO</td>
  </tr>
</table>`;

	  }

	  // Show structure hit panel when user clicks bar
	  structCanvas.onclick = function(evt) {
	    const hit_view = document.getElementById('hit-panel');
	    let activePoints = seqChart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
	    let idx = activePoints[0]['index'];
	    superposeDomain(idx);
	    let structSunid = structSunids[idx];
	    let structSid = structSids[idx];
	    let modelStart = structModelRes[idx][0];
	    let modelEnd = structModelRes[idx][1];
	    let domainStart = structDomainRes[idx][0];
	    let domainEnd = structDomainRes[idx][1];
	    let zScore = structZScores[idx];
	    hit_view.innerHTML = 
`<table>
  <tr>
    <th style="background-image: linear-gradient(to bottom, #dff0d8 0, #d0e9c6 100%); padding: 8px">Domain: <a href="https://scop.berkeley.edu/sunid=${structSunid}"> ${structSid}</a></th>
  </tr>
  <tr>
    <td style="background-color:#f7f7f7; padding: 4px">Model hit start: ${modelStart}</td>
  </tr>
  <tr>
    <td style="background-color:#f7f7f7; padding: 4px">Model hit end: ${modelEnd}</td>
  </tr>
    <tr>
    <td style="background-color:#f7f7f7; padding: 4px">Domain hit start: ${domainStart}</td>
  </tr>
    <tr>
    <td style="background-color:#f7f7f7; padding: 4px">Domain hit end: ${domainEnd}</td>
  </tr>
    <tr>
    <td style="background-color:#f7f7f7; padding: 4px">z-score: ${zScore}</td>
  </tr>
    <tr>
    <td style="background-color:#f7f7f7; padding: 4px">Coverage of target sequence: TODO</td>
  </tr>
</table>`;
	  }

	  // componentIdx 0 is full structure, componentIdx 1 is selection
	  // the boolean is isHidden
	  const modelFull = document.getElementById('model-full');
	  modelFull.onclick = function(evt) {
		viewer.setSubtreeVisibility(1, 0, false); // show full structure
		viewer.setSubtreeVisibility(1, 1, true); // hide selection
	  }

	  
	  const modelHit = document.getElementById('model-hit');
	  modelHit.onclick = function(evt) {
		viewer.setSubtreeVisibility(1, 0, true); // hide full structure
		viewer.setSubtreeVisibility(1, 1, false); // show selection
	  }

	  
	  const modelNone = document.getElementById('model-none');
	  modelNone.onclick = function(evt) {
		viewer.setSubtreeVisibility(1, 0, true); // hide full structure
		viewer.setSubtreeVisibility(1, 1, true); // hide selection
	  }

	  const domainFull = document.getElementById('domain-full');
	  domainFull.onclick = function(evt) {
		viewer.setSubtreeVisibility(0, 0, false); // show full structure
		viewer.setSubtreeVisibility(0, 1, true); // hide selection
	  }

	  
	  const domainHit = document.getElementById('domain-hit');
	  domainHit.onclick = function(evt) {
		viewer.setSubtreeVisibility(0, 0, true); // hide full structure
		viewer.setSubtreeVisibility(0, 1, false); // show selection
	  }

	  const domainNone = document.getElementById('domain-none');
	  domainNone.onclick = function(evt) {
		viewer.setSubtreeVisibility(0, 0, true); // hide full structure
		viewer.setSubtreeVisibility(0, 1, true); // hide selection
	  }
	</script>
	</div>

	<?php
	printCommonFooter($scopReleaseID);
	mysqli_close($mysqlLink);
	?>
    </body>

</html>


