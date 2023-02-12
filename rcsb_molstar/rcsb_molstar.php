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

	    $result = mysqli_query($mysqlLink,"select * from scop_node where id = 1060");
            $row = mysqli_fetch_row($result);
		    
            $row = mysqli_fetch_row($result);

	    $path = "/mnt/net/ipa.jmcnet/data/h/anagle/af2_human_v2/".$name;

	    $awk_input = "zcat ".$path." | awk '$1 == \"_entity.pdbx_description\" || $1 == \"_ma_qa_metric_global.metric_value\" {\$1=\"\\x22\"$1\"\\x22:\"; print $0 \",\"'}";
	    
	    ?>
<style>
    #app {
        position: absolute;
        left: 100px;
        top: 100px;
        width: 800px;
        height: 600px;
    }
</style>
<!-- 
    molstar.js and .css are obtained from
    - the folder build/viewer after cloning and building the molstar package 
    - from the build/viewer folder in the Mol* NPM package
-->
<link rel="stylesheet" type="text/css" href="rcsb-molstar/build/dist/viewer/rcsb-molstar.css" />
<script type="text/javascript" src="rcsb-molstar/build/dist/viewer/rcsb-molstar.js"></script>

<div id="app"></div>
        <script type="text/javascript">
	    const viewer = new rcsbMolstar.Viewer('app', {
                layoutShowControls: false,
                viewportShowExpand: false,
                viewportShowSelectionMode: true,
                layoutShowSequence: true
	    });

	    const idMat = [1, 0, 0, 0,
		    0, 1, 0, 0, 
		    0, 0, 1, 0,
		    0, 0, 0, 1];
	    const translateMat = [1, 0, 0, 0,
		    0, 1, 0, 0, 
		    0, 0, 1, 0,
		    0, 40, 0, 1];

	    const rotMat = [
							                             0.057,  0.997, -0.049, 0,
							                             0.997, -0.059, -0.044, 0,
							                            -0.047, -0.046, -0.998, 0,
										     17.832, -16.343, 53.159, 1
					                            ] ;
	    viewer.clear()
		    .then(() => viewer.loadStructureFromUrl("d1av1b1.pdb", 'pdb', false))
                    .then(() => viewer.loadStructureFromUrl("d1av1d1.pdb", 'pdb', false, { props: {  assemblyId: '1' }, matrix: rotMat}))
		    .then(() => viewer.resetCamera(0));
	    
	    
                /* viewer.clear() */
	    /*                     .then(function() { */
			                        /* return viewer.loadStructureFromUrl({fileOrUrl: 'https://files.rcsb.org/download/3PQR.pdb', format: 'pdb', isBinary: false}, { props: { kind: 'standard', assemblyId: '1' } }) */
					                    /* }) */
					                        /* .then(function() { */
						                            /* return viewer.loadPdbId('1u19', { props: {  assemblyId: '1' }, matrix: rotMat}) */
			                        /* }) */
                        /* .then(function() { */
                        /* viewer.resetCamera(0) */
                    /* }); */
        </script>
<?php 
	    $af2_data = "{".substr(shell_exec($awk_input), 0, -2)."}";
            $decoded_af2_data = json_decode($af2_data);
	    $description = $decoded_af2_data->{'_entity.pdbx_description'};
	    $global_metric_value = $decoded_af2_data->{'_ma_qa_metric_global.metric_value'};
	    echo "description: ".$description;
	    echo "global pLDDT: ".$global_metric_value;
	   
        done:
            // print("</div>\n");
            printCommonFooter($scopReleaseID);
            mysqli_close($mysqlLink);
        ?>
    </body>
</html>
