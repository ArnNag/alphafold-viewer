<?php
require_once ("../common.php");

scopConnect();
if (isset($_GET['ver']))
    $ver = $_GET['ver'];
else if (isset($_POST['ver']))
    $ver = $_POST['ver'];
if (isset($_GET['oldURL']))
    $oldURL = $_GET['oldURL'];
else if (isset($_POST['oldURL']))
    $oldURL = $_POST['oldURL'];
if (isset($_GET['code']))
    $code = $_GET['code'];
else if (isset($_POST['code']))
    $code = $_POST['code'];

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
print("<div class=\"container\">");

if (isset($oldURL))
    warnOldURL($scopReleaseID);

if (strlen($code)!=4) {
    print("<p><div class=\"alert alert-danger\">\n");
    print("<h2>Error</h2>\n");
    print("No PDB code given");
    print("</div>\n");
    goto done;
}

?>
<script>
$.cookie("lastBrowse", "<?php echo getLocalPDBURL($code,$scopReleaseID); ?>");
</script>
<?php

if (substr($code,0,1)=="s") {
    print("<p><div class=\"alert alert-warning\">\n");
    print("<h2>Literature Reference $code</h2><p>");
    print("No data are available for literature references.");
    print("</div>\n");
    goto done;
}

// get basic info
$result = mysqli_query($mysqlLink,"select id, description, deposition_date, release_date, obsolete_date, obsoleted_by from pdb_entry where code=\"".mysqli_real_escape_string($mysqlLink,$code)."\"");
if ($result===FALSE)
    goto done;
$n = mysqli_num_rows($result);
if ($n==0)
    goto done;
$row = mysqli_fetch_assoc($result);
$entryID = $row["id"];
$description = $row["description"];
$obsDate = $row["obsolete_date"];
$depDate = $row["deposition_date"];
$relDate = $row["release_date"];
if (is_null($obsDate))
    $obsDate = FALSE;
else
    $obsBy = $row["obsoleted_by"];

$releaseID = getPDBRelease($entryID, $scopReleaseID);

// try to get header info
$pdbClass = FALSE;
$pdbKeywords = FALSE;
if ($releaseID !== FALSE) {
    $result = mysqli_query($mysqlLink,"select title, class, keywords from pdb_headers where pdb_release_id=$releaseID");
    if ($result !== FALSE) {
        $row = mysqli_fetch_row($result);
        if ($row !== null) {
            $description = $row[0];
            if (ctype_upper($description))
                $description = strtolower($description);
            $pdbClass = $row[1];
            if (strlen($pdbClass)==0)
                $pdbClass = FALSE;
            if (ctype_upper($pdbClass))
                $pdbClass = strtolower($pdbClass);
            $pdbKeywords = $row[2];
            if (strlen($pdbKeywords)==0)
                $pdbKeywords = FALSE;
            if (ctype_upper($pdbKeywords))
                $pdbKeywords = strtolower($pdbKeywords);
        }
    }
}

print("<h2>PDB entry ".getPDBLink($code)."</h2>\n");
print(getPDBLink($code,"View $code on RCSB PDB site<br>\n"));
print("<b>Description</b>: $description<br>\n");
if ($pdbClass !== FALSE)
    print("<b>Class</b>: $pdbClass<br>\n");
if ($pdbKeywords !== FALSE)
    print("<b>Keywords</b>: $pdbKeywords<br>\n");
print("Deposited on <b>$depDate</b>, released <b>$relDate</b><br>\n");
if ($obsDate !== FALSE) {
    print("<b>Made obsolete</b>");
    if (!is_null($obsBy)) {
        $result = mysqli_query($mysqlLink,"select code from pdb_entry where id=$obsBy");
        $row = mysqli_fetch_assoc($result);
        print(" by ".getLocalPDBLink($row["code"],$row["code"],$scopReleaseID));
    }
    print(" on <b>$obsDate</b><p>\n");
}

if ($releaseID !== FALSE) {
    print("The last revision prior to the $dbName $scopRelease freeze date ");
}
else {
    $result = mysqli_query($mysqlLink,"select max(id) from pdb_release where pdb_entry_id=".$entryID." and file_date is not null");
    $row = mysqli_fetch_row($result);
    $releaseID = $row[0];
    print("The last revision ");
}

$result = mysqli_query($mysqlLink,"select revision_date, file_date from pdb_release where id=".$releaseID);
$row = mysqli_fetch_assoc($result);
print("was dated <b>".$row["revision_date"]."</b>, with a file datestamp of <b>".$row["file_date"]."</b>.<br>\n");

$spaci = getSPACI($entryID,$scopReleaseID,FALSE);
if ($spaci == FALSE) {
    print("<b>No data on structure quality are available</b><br>\n");
}
else {
    print("<b>Experiment type</b>: ".$spaci["method_summary"]."<br>\n");
    print("<b>Resolution</b>: ".(is_null($spaci["resolution"]) ? "N/A" : $spaci["resolution"]." &Aring;")."<br>\n");
    print("<b>R-factor</b>: ".(is_null($spaci["r_factor"]) ? "N/A" : $spaci["r_factor"])."<br>\n");
    print("<b><font size=-1>AEROSPACI</font> score</b>: ".round($spaci["aerospaci"],2)." <a href=\"$rootURL/astral/spaci/".$verString."&pdb=$code\">(click here for full SPACI score report)</a>\n");
}

print("<p><b>Chains and heterogens</b>:<br>\n");
$result = mysqli_query($mysqlLink,"select id, chain from pdb_chain where pdb_release_id=".$releaseID." order by chain");
$n = mysqli_num_rows($result);
$inSCOP = FALSE;
print("<ul>\n");
for ($i=0; $i<$n; $i++) {
    $row = mysqli_fetch_assoc($result);
    $chainID = $row["id"];
    $chain = $row["chain"];
    print("<li><b>Chain</b> '".$chain."':<br>\n");
    $compound = getChainPDBCompound($chainID);
    if ($compound !== FALSE)
        print("<b>Compound</b>: $compound<br>\n");
    $species = getChainPDBSpecies($chainID);
    if ($species !== FALSE)
        print("<b>Species</b>: $species<br>\n");
    $gene = getChainPDBGene($chainID);
    if ($gene !== FALSE)
        print("<b>Gene</b>: $gene<br>\n");
    $result2 = mysqli_query($mysqlLink,"select id, db_name, db_code, db_accession, pdb_align_start, pdb_align_end from pdb_chain_dbref where pdb_chain_id=".$chainID);
    $n2 = mysqli_num_rows($result2);
    if ($n2 > 0) {
        print("<b>Database cross-references and differences</b> (RAF-indexed):<ul>\n");
        for ($j=0; $j<$n2; $j++) {
            $row2 = mysqli_fetch_assoc($result2);
	    $dbrefID = $row2["id"];
	    if ($row2["db_name"]=="UNP") {
		$row2["db_name"] = "Uniprot";
		$row2["db_code"] = getUniprotLink($row2["db_accession"]);
	    }
	    print("<li>".$row2["db_name"]." ".$row2["db_code"]);
	    if (!is_null($row2["pdb_align_start"]) ||
		!is_null($row2["pdb_align_end"])) {
		if (is_null($row2["pdb_align_start"]))
		    $row2["pdb_align_start"] = "Start";
		if (is_null($row2["pdb_align_end"]))
		    $row2["pdb_align_end"] = "End";
		print(" (".$row2["pdb_align_start"]."-".$row2["pdb_align_end"].")");
	    }
	    print("\n");
	    $result3 = mysqli_query($mysqlLink,"select lower(c.description) as description, d.diff_start, d.diff_end from pdb_chain_diff_category c, pdb_chain_diff d where d.pdb_chain_dbref_id=".$dbrefID." and c.id=d.category_id");
	    $n3 = mysqli_num_rows($result3);
	    if ($n3 > 0) {
		print("<ul>\n");
		for ($k=0; $k<$n3; $k++) {
		    $row3 = mysqli_fetch_assoc($result3);
		    print("<li>".$row3["description"]." (".$row3["diff_start"]);
		    if ($row3["diff_start"]!=$row3["diff_end"])
			print("-".$row3["diff_end"]);
		    print(")\n");
		}
		print("</ul>\n");
	    }
	}
	print("</ul>\n");
    }
    $result2 = mysqli_query($mysqlLink,"select n.id, n.sid from link_pdb l, scop_node n where l.node_id=n.id and l.pdb_chain_id=".$chainID." and n.release_id=".$scopReleaseID." order by n.sid");
    $n2 = mysqli_num_rows($result2);
    if ($n2 > 0) {
        $inSCOP = TRUE;
        print("<b>Domains</b> in $dbName $scopRelease: ");
        for ($j=0; $j<$n2; $j++) {
            $row2 = mysqli_fetch_assoc($result2);
            print(getNodeLink($row2["id"],$row2["sid"]));
            if ($j<$n2-1)
                print(", ");
        }
    }
    if (($compound===FALSE) &&
	($species===FALSE) &&
	($gene===FALSE) &&
	($n2==0)) {
        print("<i>no info in <span class=\"dbplain\">PDB</span> for this chain</i>");
    }
}

$result = mysqli_query($mysqlLink,"select h.description from pdb_heterogen h, pdb_release_heterogen l where l.pdb_release_id=".$releaseID." and l.pdb_heterogen_id=h.id");
$n = mysqli_num_rows($result);
if ($n > 0) {
    print("<li><b>Heterogens</b>: ");
    for ($i=0; $i<$n; $i++) {
        $row = mysqli_fetch_row($result);
        print($row[0]);
        if ($i<$n-1)
            print(", ");
    }
}
print("</ul>\n");

print("<section id=\"seq\"><p><b>PDB Chain Sequences</b>:<br>\n");
if ($inSCOP===FALSE) {
    print("<i>This <span class=\"dbplain\">PDB</span> entry is not classified in <span class=\"dbplain\">$dbName</span> $scopRelease, so the chain sequences below are not included in the <span class=\"dbplain\">ASTRAL</span> sequence sets.</i><p>\n");
    $scopReleaseID = FALSE;
}
$result = mysqli_query($mysqlLink,"select id, chain from pdb_chain where pdb_release_id=".$releaseID." order by chain");
$n = mysqli_num_rows($result);
print("<ul>\n");
for ($i=0; $i<$n; $i++) {
    $row = mysqli_fetch_assoc($result);
    $chainID = $row["id"];
    $chain = $row["chain"];
    print("<li><b>Chain</b> '".$chain."':<br>\n");
    $header = $header2 = $seq = $seq2 = FALSE;
    $data = getChainSeq($chainID, $scopReleaseID, FALSE);
    if ($data !== FALSE)
	list($header, $seq) = $data;
    $data = getChainSeq($chainID, $scopReleaseID, TRUE);
    if ($data !== FALSE)
	list($header2, $seq2) = $data;
    if (($seq === FALSE) || (strlen($seq)==0))
        print("No sequence available.<br><br>");
    else {
        if ($seq != $seq2) {
            print("Sequence, based on <b>SEQRES</b> records:");
	    if ($inSCOP) {
		print(" (<a href=\"".$rootURL."astral/seq/".$verString."&id=".$code.$chain."&seqOption=0&output=text\">download</a>)\n");
	    }
            print("<br><pre class=\"seq\">\n");
            printSeq($header, $seq, FALSE);
            print("</pre><br>\n");
	    if ($seq2 !== FALSE) {
		print("Sequence, based on observed residues (<b>ATOM</b> records):");
		if ($inSCOP) {
		    print(" (<a href=\"".$rootURL."astral/seq/".$verString."&id=".$code.$chain."&seqOption=1&output=text\">download</a>)\n");
		}
		print("<br><pre class=\"seq\">\n");
		printSeq($header2, $seq2, FALSE);
		print("</pre><br>\n");
	    }
        }
        else {
            print("Sequence; same for both <b>SEQRES</b> and <b>ATOM</b> records:");
	    if ($inSCOP) {
		print(" (<a href=\"".$rootURL."astral/seq/".$verString."&id=".$code.$chain."&seqOption=0&output=text\">download</a>)\n");
	    }
            print("<br><pre class=\"seq\">\n");
            printSeq($header, $seq, FALSE);
            print("</pre><br>\n");
        }
    }
}
print("</ul></section>\n");

if ($isProduction)
    goto done;

print("<h2>Details of all old releases</h2>");

$result = mysqli_query($mysqlLink,"select id, revision_date, file_date, replaced_by from pdb_release where pdb_entry_id=".$entryID." order by file_date desc");
$n = mysqli_num_rows($result);
print("<ol>\n");
for ($i=0; $i<$n; $i++) {
    $row = mysqli_fetch_assoc($result);
    $releaseID = $row["id"];
    $replacedBy = $row["replaced_by"];
    print("<li>Revision dated ".$row["revision_date"].", file dated ".$row["file_date"]."<br>\n");
    $result2 = mysqli_query($mysqlLink,"select pdb_path, xml_path from pdb_local where pdb_release_id=".$releaseID);
    $row2 = mysqli_fetch_assoc($result2);
    if ($row2 !== null) {
        print("local path: ".$row2["pdb_path"]."<br>\n");
        print("local XML path: ".$row2["xml_path"]."<br>\n");
    }
    $result2 = mysqli_query($mysqlLink,"select id, chain from pdb_chain where pdb_release_id=".$releaseID." order by chain");
    $n2 = mysqli_num_rows($result2);
    print("<ol>\n");
    for ($j=0; $j<$n2; $j++) {
        $row2 = mysqli_fetch_assoc($result2);
        $chainID = $row2["id"];
        $chain = $row2["chain"];
        print("<li>Chain ".$chain."<br>\n");
        $info = getChainPDBInfo($chainID);
        if (strlen($info) > 0)
            print($info."<br>\n");
        if (is_null($replacedBy)) {
            $result3 = mysqli_query($mysqlLink,"select line from raf where pdb_chain_id=".$chainID." order by last_release_id desc limit 1");
            $row3 = mysqli_fetch_row($result3);
            if ($row3 !== null) {
                echo ("<p>Latest RAF for $chain:<p><pre class=\"seq\">");
                $parts = str_split($row3[0],50);
                foreach ($parts as $part)
                    echo($part."\n");
                echo("</pre>");
            }
        }
    }
    print("</ol>\n");
}
print("</ol>\n");

done:
print("</div>\n");
printCommonFooter($scopReleaseID);
mysqli_close($mysqlLink);
?>
