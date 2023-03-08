<!DOCTYPE html>

<?php

function console_log( $data ){
  echo '<script>';
  echo 'console.log('. json_encode( $data ) .')';
  echo '</script>';
}

function valid( $variable) {
    return isset($variable) && !is_null($variable);
}

if (!function_exists('array_key_first')) {
    function array_key_first(array $arr) {
        foreach($arr as $key => $unused) {
            return $key;
        }
        return NULL;
    }
}

if (!function_exists('mysqli_fetch_all')) {
    function mysqli_fetch_all($result, $unused) {
      $rv = array();
      while($row = $result->fetch_assoc()) {
        $rv[] = $row;
      }
      return $rv;
    }
}

if (isset($_GET['name']))
    $name = $_GET['name'];

require_once ("../../common.php");
?>

<html>
    <head>
    </head>
    
   <body style="background-color:white;">
<?php
    scopConnect();
    $scopReleaseID = 0;

    if (isset($ver))
	$scopReleaseID = lookupSCOPVersion(mysqli_real_escape_string($mysqlLink,$ver));
    $maxSCOP = getLatestSCOPRelease();
    if ($scopReleaseID==0)
	$scopReleaseID = $maxSCOP;
    $scopRelease = getSCOPVersion($scopReleaseID);
    $dbName = getDBName($scopReleaseID);
    if (($isProduction) && ($hackedURLs))
	$verString = "ver=$scopRelease";
    else
	$verString = "?ver=$scopRelease";

    printCommonHeader("tab1",$scopReleaseID);
    if (isset($oldURL))
	warnOldURL($scopReleaseID);

    $path = "/mnt/net/ipa.jmcnet/data/h/anagle/af2_human_v3/".$name."-model_v3.cif.gz";
    /* $ln_path = "/af2_human_v3/".$name."-model_v3.cif.gz"; */
    $ln_path = $name."-model_v3.cif";

    $awk_input = "zcat ".$path." | awk '$1 == \"_entity.pdbx_description\" || $1 == \"_ma_qa_metric_global.metric_value\" {\$1=\"\\x22\"$1\"\\x22:\"; print $0 \",\"'}";
    
    $structure_query = "SELECT astral_domain.sid, translate_x, translate_y, translate_z, rotate_1_1, rotate_1_2, rotate_1_3, rotate_2_1, rotate_2_2, rotate_2_3, rotate_3_1, rotate_3_2, rotate_3_3 FROM model_structure JOIN model_vs_domain_structure_alignment AS m_vs_d ON model_structure.id = m_vs_d.model_id JOIN astral_domain ON m_vs_d.domain_id = astral_domain.id WHERE model_structure.cif_path = \"".$path."\";";

$structure_result = mysqli_query($mysqlLink, $structure_query);
$structure_n = mysqli_num_rows($structure_result);
$sids=[];
$rot_mats=[];

for ($x = 0; $x < $structure_n; $x++) {
	$row = mysqli_fetch_assoc($structure_result);
	$sids[] = $row['sid'];
	$rot_mats[] = [floatval($row['rotate_1_1']), floatval($row['rotate_1_2']), floatval($row['rotate_1_3']),  0, floatval($row['rotate_2_1']), floatval($row['rotate_2_2']), floatval($row['rotate_2_3']), 0, floatval($row['rotate_3_1']), floatval($row['rotate_3_2']), floatval($row['rotate_3_3']), 0, floatval($row['translate_x']), floatval($row['translate_y']), floatval($row['translate_z']), 1];
} 

console_log(json_encode($rot_mats[0]));


    console_log($ln_path);
    ?>
<style>
/* #app { */
/* position: fixed; */
/* left: 100px; */
/* top: 100px; */
/* width: 800px; */
/* height: 600px; */
/* } */
</style>
<!-- 
molstar.js and .css are obtained from
- the folder build/viewer after cloning and building the molstar package 
- from the build/viewer folder in the Mol* NPM package
-->
<link rel="stylesheet" type="text/css" href="rcsb-molstar/build/dist/viewer/rcsb-molstar.css" />
<script type="text/javascript" src="rcsb-molstar/build/dist/viewer/rcsb-molstar.js"></script>


<!--
<div style="position: relative; z-index: -100;">
	<div id="app" style="width: 600px; height: 400px; position: absolute; top: 50px; left: 50px;"></div>
</div>
//-->
<div style="position: relative">
    <div id="app" style="width: 600px; height: 400px"></div>
</div>

<script type="text/javascript">
    const ln_path = "<?php print($ln_path) ?>";
    const viewer = new rcsbMolstar.Viewer('app', {
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
	    const pdb_ln = 'pdbstyle/'.concat(sid.slice(2,4), '/', sid, '.ent');
	    viewer.clear()
                    .then(() => viewer.loadStructureFromUrl(pdb_ln, 'pdb', false))
		    .then(() => viewer.loadStructureFromUrl(ln_path, 'mmcif', false, { props: {  assemblyId: '1' }, matrix: rotMat}))
		    .then(() => viewer.resetCamera(0));
    }

    const rotMats = <?php print(json_encode($rot_mats)) ?>;
    const sids = <?php print(json_encode($sids)) ?>;
    superposeDomain(0);
        </script>
<?php 
	    $af2_data = "{".substr(shell_exec($awk_input), 0, -2)."}";
            $decoded_af2_data = json_decode($af2_data);
	    $description = $decoded_af2_data->{'_entity.pdbx_description'};
	    $global_metric_value = $decoded_af2_data->{'_ma_qa_metric_global.metric_value'};
	    echo "description: ".$description;
	    echo "global pLDDT: ".$global_metric_value;
	    print($ln_path);

for ($x = 0; $x < $structure_n; $x++) {
	print('<button onclick="superposeDomain('.$x.')">'.$sids[$x].'</button>');
}
?>
Test   

<?php
        done:
            printCommonFooter($scopReleaseID);
            mysqli_close($mysqlLink);
	    
?>
    </body>
</html>
