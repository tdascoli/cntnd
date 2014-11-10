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
 *
 **/
$listname = "CMS_VALUE[0]";
$template = "CMS_VALUE[1]";
$img_color= "CMS_VALUE[2]";
$cms_var  = "CMS_VALUE[3]";
$csv_upload = "CMS_VALUE[4]";

$editmode = false;
if($contenido&&($view=="edit")){
    $editmode = true;
}

if ($editmode){
    echo '<p />Dynamische Liste:<div style="border: 1px dashed silver; padding: 2px;">';
}
cInclude('module', 'includes/class.template.php');
cInclude("includes", "functions.upl.php");

/**
 * Convert a comma separated file into an associated array.
 * The first row should contain the array keys.
 * 
 * Example:
 * 
 * @param string $filename Path to the CSV file
 * @param string $delimiter The separator used in the file
 * @return array
 * @link http://gist.github.com/385876
 * @author Jay Williams <http://myd3.com/>
 * @copyright Copyright (c) 2010, Jay Williams
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
function csv_to_array($filename='', $delimiter=',')
{
    if(!file_exists($filename) || !is_readable($filename))
        return FALSE;
    
    $header = NULL;
    $data = array();
    if (($handle = fopen($filename, 'r')) !== FALSE)
    {
        while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
        {
            if(!$header)
                $header = $row;
            else
                $data[] = array_combine(str_replace(array("ä","ö","ü"," ","-","."),"",$header), $row);
        }
        fclose($handle);
    }
    return $data;
}

if (!class_exists('cntndDynList')) {
   class cntndDynList {

      protected  $medien=array();
      protected  $images=array();
      protected  $folders=array();

      function cntndDynList($index,$csv_data) {

         global $edit, $client, $cfg, $cfgClient, $idart, $lang;

         // Member aufnehmen
         $this->edit = $edit;
         $this->index = 'var_'.$index;
         
         // csv data
         $this->csvData = $csv_data;
         
         // error
         $this->errorMsg="";
         
         if (!empty($_SESSION['IDART'])){
             //echo "<p>SESSION SET!</p>";
             $this->idart = $_SESSION['IDART'];
         }
         else {
             $this->idart = $idart;
         }
         $this->lang = $lang;

         // Initialisierung des Datenbankzugriffs
         $this->db = cRegistry::getDb();
         
         // Initialisierung des Templates
         $this->tpl = new Template();
         
         // prüfen ob liste bereits vorhanden oder nicht
         
         $this->db->query("SELECT idlist FROM cntnd_dynlist WHERE listname='".$this->index."' AND idart = ".$this->idart." AND idlang = ".$this->lang);
         if (!$this->db->nextRecord()){
             $this->db->query("INSERT INTO cntnd_dynlist (listname, idart, idlang) VALUES ('".$this->index."',".$this->idart.",".$this->lang.")");
         }

         // CMS_VALUE als Member aufnehmen
         $this->serialization();

          // TODO FILES!!!
          // alle arrays aufnehmen
          /*
          $this->medien=array();
          $this->images=array();
          $this->folders=array();
          */
          // Dateien aus dem Dateisystem lesen

          $this->db->query("SELECT * FROM ".$cfg['tab']['upl']." WHERE idclient='$client' AND (dirname LIKE 'pdf%' OR dirname LIKE 'excel%' OR dirname LIKE 'word%') ORDER BY dirname ASC, filename ASC");
          while ($this->db->nextRecord()) {
              $this->medien[$this->db->f('idupl')] = array ('idupl' => $this->db->f('idupl'), 'filename' => $this->db->f('dirname').$this->db->f('filename'), 'dirname' => $this->db->f('dirname'));
          }

          // Bilder und Ordner mit Bildern aus dem Dateisystem lesen
          $this->db->query("SELECT * FROM ".$cfg['tab']['upl']." WHERE idclient='$client' AND filetype IN ('jpeg','jpg','gif','png') ORDER BY dirname ASC, filename ASC");
          while ($this->db->nextRecord()) {
              // Bilder
              $this->images[$this->db->f('idupl')] = array ('idupl' => $this->db->f('idupl'), 'filename' => $this->db->f('dirname').$this->db->f('filename'), 'dirname' => $this->db->f('dirname'));

              // Ordner
              if ($prev_dir!=$this->db->f('dirname')){
                  $this->folders[$this->db->f('idupl')] = array ('idupl' => $this->db->f('idupl'), 'dirname' => $this->db->f('dirname'));
              }
              $prev_dir = $this->db->f('dirname');
          }
      }

       function setMedien($medien){
        $this->medien = $medien;
       }
       function setImages($images){
           $this->images = $images;
       }
       function setFolders($folders){
           $this->folders = $folders;
       }

      function serialization() {
        global $cfg;
         
        $this->db->query("SELECT serializeddata FROM cntnd_dynlist WHERE listname='".$this->index."' AND idart = ".$this->idart." AND idlang = ".$this->lang);
        while ($this->db->nextRecord()) {
            if (is_string($this->db->f('serializeddata'))){
                $this->cmsValue = unserialize($this->db->f('serializeddata'));
            }
        }

        if ($_POST[$this->index.'_action']=="delete") {         
            unset($this->cmsValue[$_POST['DYNLIST_delete']]);

            $serializedData = $this->db->escape(serialize($this->cmsValue));
            $this->db->query("UPDATE cntnd_dynlist SET serializeddata = '".$serializedData."' WHERE listname='".$this->index."' AND idart = ".$this->idart." and idlang = ".$this->lang);
        }
        else if ($_POST[$this->index.'_action']=="swap") {         
            $this->cmsValue=$this->swap_array_elements($this->cmsValue ,$_POST['DYNLIST_swap_id1'], $_POST['DYNLIST_swap_id2']);

            $serializedData = $this->db->escape(serialize($this->cmsValue));
            $this->db->query("UPDATE cntnd_dynlist SET serializeddata = '".$serializedData."' WHERE listname='".$this->index."' AND idart = ".$this->idart." and idlang = ".$this->lang);
        }
        else {       
             if (!empty($_POST[$this->index]) && $this->edit) {
                if (!$this->checkEmpty($_POST[$this->index]) AND $_POST[$this->index.'_action']=="save"){
                    $this->errorMsg="error";
                }
                else {
                    $this->cmsValue=$_POST[$this->index];
                    $check=current($_POST[$this->index][$_POST[$this->index.'_check']]);
                    if (empty($check['value'])){
                        unset($this->cmsValue[$_POST[$this->index.'_check']]);
                    }
                    $serializedData = $this->db->escape(serialize($this->cmsValue));
                    $this->db->query("UPDATE cntnd_dynlist SET serializeddata = '".$serializedData."' WHERE listname='".$this->index."' AND idart = ".$this->idart." and idlang = ".$this->lang);            
                }
            }
        }
      }
      
      function checkEmpty($postCmsValue){
         $return=false;
         if (is_array($postCmsValue)){
             foreach (current($postCmsValue) as $field){
                // field - name, label, type, value
                if (!empty($field['value'])){
                    $return = true;
                }
             }
         }
         return $return;    
      }

      function getRequestUri() {

         $returnValue = $_SERVER['PHP_SELF'];

         $start = true;
         if (!empty ($_GET)) {
            foreach ($_GET as $key => $value) {
               if ($key != 'moveUp' && $key != 'downloadlist') {
                  if ($start) {
                     $start = false;
                     $returnValue .= '?'.$key.'='.$value;
                  } else {
                     $returnValue .= '&'.$key.'='.$value;
                  }
               }
            }
         }
         // neu 20.08.2008---
         $returnValue .= '&downloadlist='.$this->index;

         
         return $returnValue;
      }

      function show($template) {
         if ($this->edit) {
           if (!$template OR empty($template)){
             echo "<br /><strong>Bitte in der Konfiguration das Modul-Template auswählen ansonsten wird die Liste nicht angezeigt.</strong>";
           }
           echo $this->edit();
         } else {
           $this->setMask($template);
           $this->showOutput();
         }
      }

      function edit() {

         global $cfg;
         $edit = '<div>';
         if ($this->errorMsg=="error"){ $edit .= '<div class="error">Bitte mindestens einen Wert eingeben</div>'; }
         $edit .= '<form id="DYNLIST_'.$this->index.'" name="DYNLIST_'.$this->index.'" action="'.$this->getRequestUri().'" method="POST">
                      <table class="dyn" border="0" cellspacing="0" cellpadding="0" width="100%">';
                      
         // FOR----
         $count=count($this->cmsValue);         
         $id=$this->index.$this->getKey();
         $first=true;
         
         foreach ($this->fields as $field){
            $edit.=$this->genField($field,$value,$id,$first);
            $first=false;
         }
         $edit.='<tr><td colspan="2" style="border-bottom: 2px solid black;">
                    <a href="javascript:speichern(\'DYNLIST_'.$this->index.'\',\''.$this->index.'_action\');" class="button">Speichern</a><p />
                 </td></tr>';
          
         //echo "<pre>"; var_dump($this->cmsValue);  echo "</pre>";
         $first=true;
         if (is_array($this->cmsData)){
             $first=false;
         }
         if (is_array($this->cmsValue)){
             foreach($this->cmsValue as $id => $row){      
                 foreach ($this->fields as $field){
                    $edit.=$this->genField($field,$row[$field['name']],$id,$first);
                 }

                 $edit.='<tr><td colspan="2" style="border-bottom: 2px solid black;"><a href="javascript:document.getElementById(\'DYNLIST_'.$this->index.'\').submit();" class="button">Speichern</a>&nbsp;&nbsp;&nbsp;<a href="javascript:del(\'DYNLIST_'.$this->index.'\',\''.$this->index.'_action\',\''.$id.'\');" class="button">Löschen</a>';
                 if ($count>1 AND !$first){
                    $edit.='&nbsp;&nbsp;&nbsp;<a href="javascript:swap(\'DYNLIST_'.$this->index.'\',\''.$this->index.'_action\',\''.$id.'\',\''.$id_old.'\');" class="button">Nach oben verschieben</a>';
                 }
                 $edit.='</td></tr>';
                 $first=false;
                 $id_old=$id;
             }
         }
         
         // LOAD CSV_DATA
         // echo '<pre>'; var_dump($this->csvData); echo '</pre>';
         if (is_array($this->csvData)){
             foreach($this->csvData as $id => $row){
                 $csv_id=$id;
                 $id=$id+$count;
                 foreach ($this->fields as $field){
                    $edit.=$this->genField($field,$row[$field['name']],$id,$first,true,$csv_id);
                 }
                 
                 $edit.='<tr><td colspan="2" style="border-bottom: 2px solid black;"><a href="javascript:document.getElementById(\'DYNLIST_'.$this->index.'\').submit();" class="button">Speichern</a>&nbsp;&nbsp;&nbsp;<a href="javascript:del(\'DYNLIST_'.$this->index.'\',\''.$this->index.'_action\',\''.$id.'\');" class="button">Löschen</a>';
                 if ($count>1 AND !$first){
                    $edit.='&nbsp;&nbsp;&nbsp;<a href="javascript:swap(\'DYNLIST_'.$this->index.'\',\''.$this->index.'_action\',\''.$id.'\',\''.$id_old.'\');" class="button">Nach oben verschieben</a>';
                 }
                 $edit.='</td></tr>';
                 $first=false;
                 $id_old=$id;
             }
         }
         // ENDE FOR----

         $edit.= '    </table>
                    <p />
                    <input type="hidden" id="'.$this->index.'_action" name="'.$this->index.'_action" />
                    <input type="hidden" id="'.$this->index.'_check" name="'.$this->index.'_check" value="'.$this->index.$this->getKey().'" />
                    <input type="hidden" id="DYNLIST_delete" name="DYNLIST_delete" />
                    <input type="hidden" id="DYNLIST_swap_id1" name="DYNLIST_swap_id1" />
                    <input type="hidden" id="DYNLIST_swap_id2" name="DYNLIST_swap_id2" />
                    </form>
                  </div>';

         return $edit;
      }
      
      private function getKey(){
        if (is_array($this->cmsValue)){
            end((array_keys($this->cmsValue)));
            $last_key = key($this->cmsValue);
            $last_key++;
        }
        else {
            $last_key=0;
        }
        return $last_key;
      }
      
      private function swap_array_elements($rg ,$i1, $i2) {
          $erg1 = $rg[$i1];
          $rg[$i1] = $rg[$i2];
          $rg[$i2] = $erg1;
          return $rg;
      }  
      
      function setMask($mask) {
         $this->mask = $mask;
      }
      
      function setColorPikto($img_color) {
          if (!empty($img_color)){
             $this->img_color = "-".$img_color;
          }
      }
      
      function setField($name,$type,$label,$extra){
        $this->fields[]=array("name"=>$name, "type"=>$type, "label"=>$label, "extra"=>$extra);
      }
      
      function genField($field,$value,$id,$first=false,$csv=false,$csv_id=""){
        $first_id="";
        if ($first){
            $first_id='id="'.$this->index.'_check"';
        }
        switch($field['type']){
            case 'break':
                $genField.= '<tr><td>'.$field['label'].':</td>';
                $genField.= '<td><select '.$first_id.' name="'.$this->index.'['.$id.']['.$field['name'].'][value]">';
                $genField .= "<option value='0'>-- kein --</option>\n";
                
                ($value['value'] == "br") ? $sel = ' selected="selected" ' : $sel = '';
                $genField.= '<option value="br" '.$sel.'> einfacher Umbruch </option>';
                
                ($value['value'] == "p") ? $sel = ' selected="selected" ' : $sel = '';
                $genField.= '<option value="p" '.$sel.'> doppelter Umbruch </option>';
                
                ($value['value'] == "hr") ? $sel = ' selected="selected" ' : $sel = '';
                $genField.= '<option value="hr" '.$sel.'> horizontale Linie </option>';
                $genField.= '</select></td></tr>';
                break;
            case 'titel':
            case 'text':
            case 'linktext':
                $genField.= '<tr><td>'.$field['label'].':</td><td><input '.$first_id.' type="text" name="'.$this->index.'['.$id.']['.$field['name'].'][value]" class="text" value="'.stripslashes($value['value']).'" /></td></tr>';
                break;
            case 'textarea':
                $genField.= '<tr><td>'.$field['label'].':</td><td><textarea '.$first_id.' name="'.$this->index.'['.$id.']['.$field['name'].'][value]" class="text">'.stripslashes($value['value']).'</textarea></td></tr>';
                break;
            case 'downloadlink':
                $genField .= '<tr><td>'.$field['label'].':</td><td><select '.$first_id.' name="'.$this->index.'['.$id.']['.$field['name'].'][value]">';
                $genField .= "<option value='0'>-- kein --</option>\n";
            
                ($value['value'] == 999999999) ? $sel = ' selected="selected" ' : $sel = '';
                $genField .= "<option value='999999999' ".$sel."> -ohne Download/Link- </option>\n";

                ($value['value'] == 111111111) ? $sel = ' selected="selected" ' : $sel = '';
                $genField .= "<option value='111111111' ".$sel."> -Link- </option>\n";
            
                ($value['value'] == 222222222) ? $sel = ' selected="selected" ' : $sel = '';
                $genField .= "<option value='222222222' ".$sel."> -Link intern (idart=)- </option>\n";

                foreach ($this->medien as $medium) {
                   ($value['value'] == $medium['idupl']) ? $sel = ' selected="selected" ' : $sel = '';
                   $genField .= '<option value="'.$medium['idupl'].'" '.$sel.'>'.$medium['filename'].'</option>'."\n";
                }
                $genField .= '</select></td></tr>';
                $genField .= '<tr><td><i>Pfad (URL, idart):</i></td><td><input class="text" type="text" name="'.$this->index.'['.$id.']['.$field['name'].'][link]" value="'.stripslashes($value['link']).'" size="35" /></td></tr>';
                break;
            case 'image':
                $genField .= '<tr><td>'.$field['label'].':</td><td><select '.$first_id.' name="'.$this->index.'['.$id.']['.$field['name'].'][value]">';
                $genField .= "<option value='0'>-- kein --</option>\n";

                foreach ($this->images as $medium) {
                  if (empty($field['extra']) OR (!empty($field['extra']) && $medium['dirname']==$field['extra'])){
                     ($value['value'] == $medium['idupl']) ? $sel = ' selected="selected" ' : $sel = '';
                     $genField .= '<option value="'.$medium['idupl'].'" '.$sel.'>'.$medium['filename'].'</option>'."\n";
                  }
                }
                $genField .= '</select></td></tr>';
                break;
            case 'gallery2':
                $genField .= '<tr><td>'.$field['label'].':</td><td><select '.$first_id.' name="'.$this->index.'['.$id.']['.$field['name'].'][value]">';
                $genField .= "<option value='0'>-- kein --</option>\n";

                foreach ($this->folders as $medium) {
                   ($value['value'] == $medium['idupl']) ? $sel = ' selected="selected" ' : $sel = '';
                   $genField .= '<option value="'.$medium['idupl'].'" '.$sel.'>'.$medium['dirname'].'</option>'."\n";
                }
                $genField .= '</select><input type="hidden" name="'.$this->index.'['.$id.']['.$field['name'].'][viewer]" value="'.$id.'" /></td></tr>';
                break;
            case 'gallery':
            case 'gallery3':
                $genField .= '<tr><td>'.$field['label'].' - Kommentar:</td><td><textarea '.$first_id.' name="'.$this->index.'['.$id.']['.$field['name'].'][value][kommentar]">'.stripslashes($value['value']['kommentar']).'</textarea></td></tr>';
                $genField .= '<tr><td>'.$field['label'].' - Bild:</td><td><select '.$first_id.' name="'.$this->index.'['.$id.']['.$field['name'].'][value][bild]">';
                $genField .= "<option value='0'>-- kein --</option>\n";

                foreach ($this->images as $medium) {
                   ($value['value']['bild'] == $medium['filename']) ? $sel = ' selected="selected" ' : $sel = '';
                   $genField .= '<option value="'.$medium['filename'].'" '.$sel.'>'.$medium['filename'].'</option>'."\n";
                }
                $genField .= '</select></td></tr>';
                break;
            default:
                $fieldName=$field['name'];
                ($csv) ? $val = $this->csvData[$csv_id][str_replace(array("{_csv_","}"),"",$fieldName)] : $val = stripslashes($value['value']);
                $genField= '<tr><td>'.$field['label'].':</td><td><input '.$first_id.' type="text" name="'.$this->index.'['.$id.']['.$field['name'].'][value]" class="text" value="'.$val.'" /></td></tr>';
        }
        return $genField;
      }
      
      private function tplName($name){
        return str_replace(array("{","}"),"",$name);
      }
      
      private function doBreakField($name,$value){
        if ($value['value']=="br"){
            $this->tpl->set('d', $name, '<br class="'.$this->index.' cntnd_break" />');
        }
        else if ($value['value']=="p"){
            $this->tpl->set('d', $name, '<br /><br class="'.$this->index.' cntnd_break" />');
        }
        else if ($value['value']=="hr"){
            $this->tpl->set('d', $name, '<hr class="'.$this->index.' cntnd_break" />');
        }
        else {
            $this->tpl->set('d', $name, "");
        }
      }
      
      private function doField($name,$value){
        if (!empty($value['value'])){
            $this->tpl->set('d', $name, '<div class="'.$this->index.' cntnd_text">'.stripslashes($value['value']).'</div>');
        }
        else {
            $this->tpl->set('d', $name, "");
        }
      }
      
      private function doCsvField($name,$value){
        if (!empty($value['value'])){
            $this->tpl->set('d', $name, '<span class="'.$this->index.' cntnd_text">'.stripslashes($value['value']).'</span>');
        }
        else {
            $this->tpl->set('d', $name, "");
        }
      }
      
      private function doLinkField($name,$value){
        if (!empty($value['value'])){
            $this->tpl->set('d', $name, '<span class="'.$this->index.' cntnd_linktext">'.stripslashes($value['value']).'</span>');
        }
        else {
            $this->tpl->set('d', $name, "");
        }
      }
      
      private function doTitelField($name,$value){
        if (!empty($value['value'])){
            $this->tpl->set('d', $name, '<h2 class="'.$this->index.' cntnd_title">'.stripslashes($value['value']).'</h2>');
        }
        else {
            $this->tpl->set('d', $name, "");
        }
      }

      private function doDownloadLinkField($name,$value){
        if (!empty($value['value']) AND $value['value']!=0){
            global $cfg, $client, $cfgClient;
            
            $DL_type=$value['value'];
            $DL_link=$value['link'];
            
            $target="_self";
            if ($DL_type!=999999999 AND $DL_type!=111111111 AND $DL_type!=222222222){
                $filename = $this->medien[$DL_type]['filename'];
                $link = $cfgClient[$client]["upl"]["htmlpath"].$filename;
                $icon = substr($filename,strrpos($filename,".")+1);
                $target="_blank";
            }
            if ($DL_type==111111111){
                $link = $DL_link;
                $icon = "link";
                $target="_blank";
            }
            if ($DL_type==222222222){
                $link = "front_content.php?idart=".$DL_link;
                $icon = "linkintern";
            }
            
            switch ($icon){
                case 'doc':
                case 'docx':
                case 'dot':
                case 'dotx':                                
                        $icon="word";
                        break;
                case 'xls':
                case 'xlsx':
                        $icon="excel";
                        break;
                case 'pdf':
                        $icon="pdf";
                        break;
                case 'ppt':
                case 'pptx':
                case 'pps':
                case 'ppsx':
                        $icon="powerpoint";
                        break;
                case 'qt':
                case 'avi':
                case 'mpeg':
                        $icon="quicktime";
                        break;
                case 'zip':
                        $icon="zip";
                        break;
                case 'link':
                        $icon="internet";
                        break;
                case 'linkintern':
                        $icon="linkintern";
                        break;
                default:
                        $icon="frage";
            }            
            $img_icon   ='upload/pikto/pikto-'.$icon.'.png';
            
            
            // template ausfüllen
            $id=$this->index.mt_rand();
            $icon="";
            if ($DL_type!=999999999){
                $icon = '<img src="'.$img_icon.'" border="0" class="middle" id="'.$id.'" class="'.$this->index.' cntnd_icon" />';
            }
            
            $link_tag = '<a class="'.$this->index.' cntnd_link" href="'.$link.'" target="'.$target.'">';
            $this->tpl->set('d', $name, $link_tag);
            $this->tpl->set('d', "_".$name."_end", $icon.'</a>');
        }
        else {
            $this->tpl->set('d', $name, '');
            $this->tpl->set('d', "_".$name."_end", '');
        }
      }
      
      private function doImageField($name,$value){
        if (!empty($value['value']) AND $value['value']!=0){
            $this->tpl->set('d', $name, '<img src="upload/'.$this->images[$value['value']]['filename'].'" class="'.$this->index.' cntnd_img" />');
            //$this->tpl->set('d', $name, 'upload/'.$this->images[$value['value']]['filename']);
        }
        else {
            $this->tpl->set('d', $name, "");
        }
      }
      
      private function doGallery2($name,$value){
        if (!empty($value['value']) AND $value['value']!=0){
            global $cfg, $client, $cfgClient;
      
            $dirname = $this->folders[$value['value']]['dirname'];
            
            $viewer = "viewer_".$this->idart."_".$value['viewer'];
            $javascript = "\n"."
                <script language=\"\" type=\"text/javascript\">
                <!--
                jQuery(document).ready(function() {
                    $('#".$viewer."').click(function() {
                        $.fancybox([";
                        // SQL
                        $this->db->query("SELECT filename FROM ".$cfg["tab"]["upl"]." WHERE dirname = '".$dirname."' ORDER BY filename ");
                        $first=true;
                        while ($this->db->nextRecord()) {
                             if (!$first){
                                $javascript .= ",";
                             }
                             $javascript .= "\n'".$cfgClient[$client]['upl']['htmlpath'].$dirname.$this->db->f('filename')."'";
                             $first=false;
                        }
            $javascript.="], {
                            'padding'           : 0,
                            'scrolling'         : 'yes',
                            'transitionIn'      : 'none',
                            'transitionOut'     : 'none',
                            'type'              : 'image',
                            'changeFade'        : 0
                        });
                    });
                });
                ";
            $javascript .= '
                //-->
                </script>
                '."\n";
                        
            $this->tpl->set('d', $name, $viewer);
            $this->tpl->set('d', '_javascript', $javascript);
        }
        else {
            $this->tpl->set('d', $name, "");
            $this->tpl->set('d', '_javascript', "");
        }
      }
    
      private function doGallery3($name,$value,$first,$count,$current){
        if (is_array($value['value'])){
            global $cfg, $client, $cfgClient;

            /*				{
            href : 'bildergalerien/beispiel/birne.jpg',
					title : 'Es werde Licht.'
				}, {*/
            
            if ($current==1){
                $viewer="viewer_".$this->idart."_".$value['viewer'];
                $this->tpl->set('s', $name, $viewer);
                $this->tpl->set('s', '_'.$name.'_thumb', "upload/".$value['value']['bild']);
                $this->tpl->set('s', '_'.$name.'_comment', nl2br($value['value']['kommentar']));
            }
            
            if ($current>1){                           
                $javascript .= ",";
            }
            $javascript .= "\n{\n'href' :'".$cfgClient[$client]['upl']['htmlpath'].$value['value']['bild']."',\n'title' :'".str_replace(array("\n", "\r"), ' ', nl2br($value['value']['kommentar']))."'\n}";

            $this->tpl->set('d', '_'.$name.'_bild', $javascript);
        }
        else {
            $this->tpl->set('s', $name, "");
            $this->tpl->set('d', '_'.$name.'_bild', "");
            $this->tpl->set('s', '_'.$name.'_thumb', "");
            $this->tpl->set('s', '_'.$name.'_comment', "");
        }
      }

      private function doGallery($name,$value,$first,$count,$current){
           if (is_array($value['value'])){
               global $cfg, $client, $cfgClient;
               /*
               $javascript='';
               if ($current>1){
                   $javascript = ",";
               }
               $javascript .= "\n{\n'href' :'".$cfgClient[$client]['upl']['htmlpath'].$value['value']['bild']."',\n'title' :'".str_replace(array("\n", "\r"), ' ', nl2br($value['value']['kommentar']))."'\n}";
               $this->tpl->set('d', '_gallery_array', $javascript);
               */
               $this->tpl->set('d', 'gallery', "upload/".$value['value']['bild']);
               $this->tpl->set('d', '_gallery_comment', str_replace(array("\n", "\r"), ' ', nl2br($value['value']['kommentar'])));
           }
           else {
               //$this->tpl->set('s', $name, "");
               $this->tpl->set('d', 'gallery', "");
               $this->tpl->set('d', '_gallery_comment', "");
           }
      }
      
      function genHtmlField($field,$value,$first,$count,$current){
        switch($field['type']){
            case 'break':
                $this->doBreakField($this->tplName($field['name']),$value);
                break;
            case 'titel':
                $this->doTitelField($this->tplName($field['name']),$value);
                break;
            case 'text':
            case 'textarea':
                $this->doField($this->tplName($field['name']),$value);
                break;
            case 'linktext':
                $this->doLinkField($this->tplName($field['name']),$value);
                break;  
            case 'image':
                $this->doImageField($this->tplName($field['name']),$value);
                break;
            case 'downloadlink':
                $this->doDownloadLinkField($this->tplName($field['name']),$value);
                break;
            case 'gallery':
                $this->doGallery($this->tplName($field['name']),$value,$first,$count,$current);
                break;
            case 'gallery2':
                $this->doGallery2($this->tplName($field['name']),$value);
                break;
            case 'gallery3':
                $this->doGallery3($this->tplName($field['name']),$value,$first,$count,$current);
                break;
            case 'csv':
                $this->doCsvField($this->tplName($field['name']),$value);
                break;
        }
      }

      function showOutput() {
        global $client, $cfgClient;
         
        if (is_array($this->cmsValue)){
         $this->tpl->reset();
         $count=count($this->cmsValue);
         $current=1;
         foreach($this->cmsValue as $row){
            $first=true;            
            foreach ($this->fields as $field){
                $this->genHtmlField($field,$row[$field['name']],$first,$count,$current);
                $first=false;
                $current++;
            }            
            $this->tpl->next();
         }
         $this->tpl->generate($this->mask);
        }
       }
   }
}

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