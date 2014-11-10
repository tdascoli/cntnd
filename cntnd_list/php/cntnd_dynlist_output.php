<?php
/**
 * Dynamisches Listenmodul CNTND
 *
Tabellenstruktur:
CREATE TABLE IF NOT EXISTS `cntnd_dynlist` (
  `idlist` int(11) NOT NULL AUTO_INCREMENT,
  `listname` varchar(200) NOT NULL,
  `idart` int(11) NOT NULL,
  `idlang` int(11) NOT NULL,
  `serializeddata` longtext,
  PRIMARY KEY (`idlist`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=0 ;
$sql_create = "
CREATE TABLE IF NOT EXISTS cntnd_dynlist (
  idlist int(11) NOT NULL AUTO_INCREMENT,
  listname varchar(200) NOT NULL,
  idart int(11) NOT NULL,
  idlang int(11) NOT NULL,
  serializeddata longtext,
  PRIMARY KEY (idlist)
) DEFAULT CHARSET=utf8 AUTO_INCREMENT=0;
";
if (!empty($sql_create)){
 $dbC = new DB_Contenido;
 $dbC->query($sql_create);
 echo mysql_error();
}
 */
// Includes
cInclude('module', 'includes/class.module.dynlist.php');
// Module configuration
$aModuleConfiguration = array(
    'debug' => false,
    'name' => 'dynlist',
    'idmod' => $cCurrentModule,
    'container' => $cCurrentContainer,

    'listname' => "CMS_VALUE[0]",
    'template' => "CMS_VALUE[1]",
    'img_color' => "CMS_VALUE[2]",
    'cms_var' => "CMS_VALUE[3]",
    'csv_upload' => "CMS_VALUE[4]",

    'db' => cRegistry::getDb(),
    'cfg' => cRegistry::getConfig(),
    'client' => cRegistry::getClientId(),
    'lang' => cRegistry::getLanguageId()
);

$editmode = false;
if($contenido&&($view=="edit")){
    $editmode = true;
}

if ($editmode){
echo '<p />Dynamische Liste:<div style="border: 1px dashed silver; padding: 2px;">';
}

// TODO INCLUDES
cInclude("includes", "functions.upl.php");
cInclude("classes", "class.template.php");

// CSV UPLOAD
if ($editmode AND $csv_upload=="true"){
    echo "<strong>CSV-Upload</strong>";
    // UPLOAD FORM
   // Absoluter Pfad zum Ordner in den die Datei hochgeladen werden soll.
   $pfad = "upload/"."CMS_VALUE[5]";
   // Ist eine maximale Größe der Datei festgelegt worden ?
   $sizeabfrage = "no";
   // Wird die maximale Größe auch angezeigt ?
   $sizeanzeige = "no";
   // Was für Dateitypen sollen erlaubt sein ?
   $extend = "csv";
   // Sollen die erlaubten Dateitypen angezeigt werden ?
   $extendanzeige = "no";
   $fehler = FALSE;
   
   if ($senden) {
      if ($_FILES["file"]["name"] == "") {
         $desc = $Beschreibung;
         echo "<font color=#FF0000><b>Es wurde keine Datei ausgewählt!</b></font>";
         $fehler = TRUE;
      }
      
      if (!$fehler)
      if ($sizeabfrage == "yes") {
         if ($file_size > $filesize) {
            $desc = $Beschreibung;
            echo "<font color=#FF0000><b>Die Datei ist zu gross!</b></font>";
            $fehler = TRUE;
         }
      }

      if (!$fehler)
      if (!preg_match("/($extend)$/i", $_FILES["file"]["name"])) {
         $desc = $Beschreibung;
         echo "<font color=#FF0000><b>Dieser Dateityp ist nicht erlaubt!</b></font>";
         $fehler = TRUE;
      }

      ini_set("auto_detect_line_endings", true);
      
      if (!$fehler) {
          $csv_data=csv_to_array($_FILES["file"]["tmp_name"],";");
          echo "<p /><strong>Bitte die Liste Abspeichern, ansonsten gehen alle Daten verloren.</strong>";
      }
      else {
        $fehler = TRUE;
      }
    }
    if ($fehler || !$senden) {
        ?>    
        <form action="<?php $PHP_SELF; ?>" method="post" enctype="multipart/form-data">
        <b>Bitte Datei auswählen:</b><BR />
        <input type="file" name="file" />
        <input type="Submit" name="senden" value="Hochladen"><br>
        </form>
        <?php
    }
    echo '<hr />';
}

// TODO NEW CLASS NAME
$cntndList = new cntndDynList($listname,$csv_data);
for($i=0;$i<$cms_var;$i++){  
    $cms_var_field=100+$i;
    $cms_var_type =200+$i;
    $cms_var_name =300+$i;
    $cms_var_extra=400+$i;
    
    $name="CMS_VALUE[$cms_var_field]";
    $type="CMS_VALUE[$cms_var_type]";
    $label="CMS_VALUE[$cms_var_name]";
    $extra="CMS_VALUE[$cms_var_extra]";
    if ($type!="internal" AND $type!="NULL" AND !empty($type)){          
        $cntndList->setField($name,$type,$label,$extra);
    }
}
$cntndList->setColorPikto($img_color);
$cntndList->show($template);

if ($editmode){
    echo '</div>';
}
?>