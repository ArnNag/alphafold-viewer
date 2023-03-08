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

require_once ("../common.php");
?>

<html>
    <head>
        <script type="text/javascript" src="https://www.molsoft.com/lib/acticm.js"></script>
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
	    
	    echo "donkey"; ?>
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
<link rel="stylesheet" type="text/css" href="node_modules/molstar/build/viewer/molstar.css" />
<script type="text/javascript" src="node_modules/molstar/build/viewer/molstar.js"></script>

<div id="app"></div>
        <script type="text/javascript">
            molstar.Viewer.create('app', {
                layoutIsExpanded: false,
                layoutShowControls: false,
                layoutShowRemoteState: false,
                layoutShowSequence: true,
                layoutShowLog: false,
                layoutShowLeftPanel: true,

                viewportShowExpand: true,
                viewportShowSelectionMode: false,
                viewportShowAnimation: false,

                pdbProvider: 'rcsb',
                emdbProvider: 'rcsb',
            }).then(viewer => 
		buildStaticSuperposition(viewer.plugin, StaticSuperpositionTestData));
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
