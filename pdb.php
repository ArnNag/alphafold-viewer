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
$query = "select astral_domain.header, astral_domain.sid, blast_log10_e, pct_identical, seq1_start, seq1_length, seq2_start, seq2_length from model_structure, model_structure_uniprot, uniprot_seq, astral_seq_blast, astral_domain where model_structure.cif_path = \"".$path."\" and model_structure.id = model_structure_uniprot.model_structure_id and model_structure_uniprot.uniprot_id = uniprot_seq.uniprot_id and uniprot_seq.seq_id = astral_seq_blast.seq1_id and seq2_id = astral_domain.seq_id and (astral_domain.style_id = 1 or astral_domain.style_id = 3) group by seq2_id order by blast_log10_e;";
/* print($query); */
$result = mysqli_query($mysqlLink, $query);
$n = mysqli_num_rows($result);
$resdata=[];
$seq_ids=[];
$e_values=[];
$colors=[];
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
	$resdata[] = [intval($row["seq1_start"]), $seq1_end];
} 
/* print(json_encode($resdata)); */
/* print(json_encode($seq_ids)); */
/* print(json_encode($e_values)); */
/* print(json_encode($colors)); */
print("</div>\n");
?>

<div style="height: 700px">
  <canvas id="myChart"></canvas>
</div>

<div id="Hit"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

  const data = {
  labels: <?php print(json_encode($seq_ids))?>,
    datasets: [{
      label: 'Hits',
      backgroundColor: <?php print(json_encode($colors))?>, 
      borderColor: 'rgb(255, 99, 132)',
      data: <?php print(json_encode($resdata))?>
    }]
  };

  const config = {
    type: 'bar',
    data: data,
    options: {
      indexAxis: 'y',
      maintainAspectRatio: false
      /* scales: { */
      /*   yAxes: [{ */
	  /* barThickness: 5 */
      /*   }] */
      /* } */
      /* animation: { */
      /*   duration: 0 */
      /* } */
    }
  };
</script>

<script>
  const myChart = new Chart(
    document.getElementById('myChart'),
    config
  );
</script>

<?php
printCommonFooter($scopReleaseID);
mysqli_close($mysqlLink);
?>



