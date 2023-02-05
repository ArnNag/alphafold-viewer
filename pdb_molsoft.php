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
$awk_input = "zcat ".$path." | awk '$1 == \"_entity.pdbx_description\" || $1 == \"_ma_qa_metric_global.metric_value\" {\$1=\"\\x22\"$1\"\\x22:\"; print $0 \",\"'}";
$af2_data = "{".substr(shell_exec($awk_input), 0, -2)."}";
$decoded_af2_data = json_decode($af2_data);
$description = $decoded_af2_data->{'_entity.pdbx_description'};
$global_metric_value = $decoded_af2_data->{'_ma_qa_metric_global.metric_value'};
	    
print("<h2>AlphaFold entry ".$name."</h2>\n");
print(getAF2Link($name,"View $name on AlphaFold Protein Structure Database site<br>\n"));
print("<b>Description</b>: $description<br>\n");
print("<b>Global pLDDT</b>: ".(is_null($global_metric_value) ? "N/A" : $global_metric_value)."<br>\n");
$query = "select astral_domain.header, astral_domain.sid, blast_log10_e, pct_identical, seq1_start, seq1_length, seq2_start, seq2_length, scop_node.sunid from model_structure, model_structure_uniprot, uniprot_seq, astral_seq_blast, astral_domain, scop_node where model_structure.cif_path = \"".$path."\" and model_structure.id = model_structure_uniprot.model_structure_id and model_structure_uniprot.uniprot_id = uniprot_seq.uniprot_id and uniprot_seq.seq_id = astral_seq_blast.seq1_id and astral_domain.node_id = scop_node.id and seq2_id = astral_domain.seq_id and (astral_domain.style_id = 1 or astral_domain.style_id = 3) and blast_log10_e < 0 group by seq2_id order by blast_log10_e;";
/* print($query); */
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
print("</div>\n");
$pxperhit = 25;
?>


<div style="height: <?php print($n * $pxperhit)?>px">
  <canvas id="myChart"></canvas>
</div>

<div id="Hit"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/d3-color@3"></script>
<script src="https://cdn.jsdelivr.net/npm/d3-interpolate@3"></script>
<script src="https://cdn.jsdelivr.net/npm/d3-scale-chromatic@3"></script>
<script type="text/javascript" src="https://www.molsoft.com/lib/acticm.js"></script>

<script>

  const queryRes = <?php print(json_encode($query_res)) ?>;
  const hit_res = <?php print(json_encode($hit_res)) ?>;
  const seq_ids = <?php print(json_encode($seq_ids)) ?>;
  const e_values = <?php print(json_encode($e_values)) ?>;
  const sunids = <?php print(json_encode($sunids)) ?>;
  const seq2_lens = <?php print(json_encode($seq2_lens)) ?>;
  const colors = e_values.map(val => d3.interpolateViridis(Math.pow(10, val + 2)));

  const data = {
  labels: seq_ids,
    datasets: [{
      label: 'Hits',
      backgroundColor: colors, 
      borderColor: 'rgb(255, 99, 132)',
      data: queryRes
    }]
  };

  const config = {
    type: 'bar',
    data: data,
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
      animation: false
      /* scales: { */
      /*   yAxes: [{ */
	  /* barThickness: 5 */
      /*   }] */
      /* } */
    }
  };

  const canvas = document.getElementById('myChart');

  const myChart = new Chart(canvas, config);

  canvas.onclick = function(evt) {
    const hit_view = document.getElementById('Hit');
    let activePoints = myChart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
    let idx = activePoints[0]['index'];
    hit_view.innerHTML = '<div> Hit: <a href="https://scop.berkeley.edu/sunid=' + sunids[idx] + '">' +  seq_ids[idx] + '</a> </div> <div> Query start: ' +  queryRes[idx][0]  + '</div> <div> Query end: ' + queryRes[idx][1] + '</div> <div> Hit start: ' + hit_res[idx][0] + '</div> <div> Hit end: ' + hit_res[idx][1] + '</div> <div> log10 E-value: ' + e_values[idx] + '</div> <div> Coverage of target sequence: ' + ((hit_res[idx][1] - hit_res[idx][0]) / seq2_lens[idx]) + ' </div>';
  }
</script>

            <!-- Viewer -->
<?php 
$pdb_unzip_path = "/mnt/net/ipa.jmcnet/data/h/anagle/af2_human_v3/".$name."-model_v3.pdb.gz";
?>

            <span style="display: inline-block; vertical-align: top; margin-right: 10px; margin-bottom: 10px">
                <div class="panel panel-success" style="position: relative;">
                    <div class="panel-heading" style="color:black; font-size:14px"><b>heading</b></div>
                    <div id="panel" class="panel-body" style="padding:5px;height:500px;width:600px;">
                        <div id="molsoft_wrapper" style="padding:5px;position:relative;width:100%;height:100%">
                            <div id="loading" style="width:100%; height:100%;position:absolute;z-index:98;text-align:center;top:20%">
                                <img id="loading-image" src="images/loader.gif" alt="Loading..." style="z-index:99;" />
                            </div>
                            <div id="molsoft" style="position:relative; height:100%; width:100%; overscroll-behavior:contain; z-index:100;">
                                <script>
                                    function onLoadActiveIcm() {
                                        var af2_pdb_path = "AF-P69905-F1-model_v4.pdb";
					var domain_pdb_path = "d1a00a.pdb";

					var instance = 0;

                                        document.act = new ActiveIcmJS("molsoft");
					/* document.act.projectFile = "myfile.icb"; */
                                        document.act.OpenProject(af2_pdb_path);
                                        document.act.OpenProject(domain_pdb_path);

                                        document.act.searchBarVisible = false;
                                        document.act.onLoadProject = function() {
                                            document.act.RunCommands("display ribbon");
					    if (instance == 0) {
					        document.act.RunCommands('rename a_1 "model"');
					        document.act.RunCommands('color model yellow');
					        document.act.RunCommands('transform a_* {-0.082, 0.013, 0.997, 98.903, -0.766, -0.641, -0.055, 52.626, 0.638, -0.768, 0.063, -5.103}');
					        /* document.act.RunCommands('transform a_* {-0.082, 0.013, 0.997, 0., -0.766, -0.641, -0.055, 0., 0.638, -0.768, 0.063, 0.}'); */
					    } else if (instance == 1) {
					        document.act.RunCommands('rename a_1 "match"');
					        document.act.RunCommands('color a_ green');
					    }

					    instance++;
                                        };
                                    }
                                </script>
                            </div>
                        </div>
                        <br>
                    </div>
                </div>
            </span>

<?php
printCommonFooter($scopReleaseID);
mysqli_close($mysqlLink);
?>



