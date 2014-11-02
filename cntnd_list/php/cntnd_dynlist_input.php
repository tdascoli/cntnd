$sql = "SELECT DISTINCT dirname from ".$cfg["tab"]["upl"]; 
$db->query($sql); 
while ( $db->next_record() ) { 
    $dirs[] = $db->f("dirname");
}

function getChooseFields($cms_var,$field,$value){
    $internal="";    
    $no_fields=array("{id}","{icon}","{img_over}","{img_icon}","{target}","{javascript}");
    
    if (in_array($field,$no_fields) OR substr($field,0,2)=="{_"){
        if (substr($field,0,6)=="{_csv_"){
            $csv="selected";
        }
        else {
            $internal="selected";
        }
    }
    if (!empty($value)){
        $$value="selected";
    }
    
    $choose_fields='<select name="'."CMS_VAR[$cms_var]".'" size="1">
                    <option value="NULL" '.$NULL.'> --bitte w&auml;hlen-- </option>
                    <option value="internal" '.$internal.'> -internes Feld- </option>
                    <option value="csv" '.$csv.'> -internes Feld (CSV)- </option>
                    <option value="break" '.$break.'> Umbruch, Horizontale Linie </option>
                    <option value="titel" '.$titel.'> Titel (Einzeilig) </option>
                    <option value="text" '.$text.'> Eingabefeld (Einzeilig) </option>
                    <option value="linktext" '.$linktext.'> Eingabefeld (für Linktitel) </option>
                    <option value="textarea" '.$textarea.'> Eingabefeld (Mehrzeilig) </option>
                    <option value="downloadlink" '.$downloadlink.'> Link-, Downloadfeld </option>
                    <option value="image" '.$image.'> Bild / Bilderstreifen </option>
                    <option value="gallery2" '.$gallery2.'> Bildergalerie </option>
                    <option value="gallery3" '.$gallery3.'> Bildstreifen als Bildergalerie </option>
                    </select>';

    return $choose_fields;
}

function getExtraFields($cms_var,$type,$value){
    global $dirs;

    switch($type){
        case 'image':
            $ret= '<select name="CMS_VAR['.$cms_var.']">
                    <option value="0">  --bitte w&auml;hlen-- </option> ';
            foreach ($dirs as $dir){
                if ( $value == $dir) { 
                    $ret.= '<option selected="selected" value="'.$dir.'">'.$dir.'</option>'; 
                } else { 
                    $ret.= '<option value="'.$dir.'">'.$dir.'</option>'; 
                }
            }
            $ret.= '</select>';
            break;
    }
    return $ret;
}

?>
<table>
<?php
$listname = "CMS_VALUE[0]";
if (empty($listname)){
    $listname="dyn_list";
}
$template = "CMS_VALUE[1]";
$color_pikto = "CMS_VALUE[2]";
$csv_upload = "CMS_VALUE[4]";
$csv_selected="";
if ($csv_upload OR $csv_upload=="true"){
    $csv_selected="CHECKED";
}

if (!$template OR empty($template) OR $template=="false"){
 echo '<tr><td colspan="4"><strong>Bitte das Modul-Template auswählen ansonsten wird die Liste nicht angezeigt.</strong><hr /></td></tr>';
}
?>
<tr>
  <td colspan="2">Name der Liste (default: dyn_list):</td>
  <td colspan="2"><input type="text" name="CMS_VAR[0]" value="<?= $listname ?>" style="width:100%;" /></td>
</tr>
<tr>
  <td colspan="2">Farbe der Piktogramme (falls nicht Standard/optional):</td>
  <td colspan="2"><input type="text" name="CMS_VAR[2]" value="<?= $color_pikto ?>" style="width:100%;" /></td>
</tr>
<tr>
  <td colspan="2" valign="top">CSV-Upload (CSV im Editor Uploaden):</td>
  <td><input type="checkbox" name="CMS_VAR[4]" value="true" <?= $csv_selected ?> /> Ja, aktivieren / Upload-Verzeichnis:</td>
    <td>
            <select name="CMS_VAR[5]"> 
            <option value="0"> --bitte w&auml;hlen-- </option> 
            <?php 
            global $dirs;
            foreach ($dirs as $dir){
                $upload_dir=substr_replace($dir,'',(strlen($dir)-1)); 
                if ( "CMS_VALUE[5]"== $upload_dir) { 
                    echo '<option selected="selected" value="'.$upload_dir.'">'.$dir.'</option>'; 
                } else { 
                    echo '<option value="'.$upload_dir.'">'.$dir.'</option>'; 
                }
            }
            ?> 
            </select> 
    </td>
</tr>
<tr>
    <td>Modul-Template wählen:</td>
  <td colspan="2">
<?php
echo '<select name="CMS_VAR[1]" size="1" onchange="this.form.submit()" style="width:100%;">
            <option value="false">'.mi18n("-bitte w&auml;hlen-").'</option>'."\n";
// TODO name des moduls...
$strPath_fs     = $cfgClient[$client]["module"]["path"].'cntnd_dynlist/template/';
$optionFields   = "";
$handle         = opendir($strPath_fs);
$files          = array();

while ($entryName = readdir($handle)){
    if (is_file($strPath_fs.$entryName)) $files[]=array($strPath_fs.$entryName,$entryName);
}
closedir($handle);
asort($files);

while (list ($key, $val) = each ($files)){
    if (substr($val[1],-4)=="html"){
        $optionFields .= ("CMS_VALUE[1]"==$val[0]) ? "\n\t".'<option selected="selected" value="'.$val[0].'">'.$val[1].'</option>' : "\n\t".'<option value="'.$val[0].'">'.$val[1].'</option>';
    }
}
echo $optionFields . '</select>';
?>
  </td>
</tr>
<?php
if (!empty($template) AND $template!="false"){
  echo '<tr><td colspan="4"><hr /></td></tr>';
  echo '<tr><td>Feld</td><td>Label im Editor</td><td>Typ</td><td>Extra</td></tr>';

  $handle = fopen($template, "r");
  $contents = fread($handle, filesize($template));
  fclose($handle);  
  
  $pattern = '@\{\w*?\}@is'; 
  $result = preg_match_all($pattern, $contents, $fields);
  
  $cms_var=0;
  foreach(array_unique($fields[0]) as $field){
      $cms_var_field=100+$cms_var;
      $cms_var_type =200+$cms_var;
      $cms_var_name =300+$cms_var;
      $cms_var_extra=400+$cms_var;
      echo '<tr>
              <td><b>'.$field.'</b>:</td>
              <td><input type="text" name='."CMS_VAR[$cms_var_name]".' value="'."CMS_VALUE[$cms_var_name]".'" /></td>
              <td>
                  '.getChooseFields($cms_var_type,$field,"CMS_VALUE[$cms_var_type]").'
                  <input type="hidden" name='."CMS_VAR[$cms_var_field]".' value="'.$field.'" />
              </td>
              <td>
                  '.getExtraFields($cms_var_extra,"CMS_VALUE[$cms_var_type]","CMS_VALUE[$cms_var_extra]").'
              </td>
            </tr>';
      $cms_var++;
  }
  echo '<tr><td colspan="4"><input type="hidden" name="CMS_VAR[3]" value="'.$cms_var.'" /><hr /></td></tr>';
}
?>
</table>
<?php