<?php

if (!defined('CON_FRAMEWORK')) {
    die('Illegal call');
}

/**
 * CONTENIDO module class for mpNivoSlider
 */
class ModuleDynlist {

    /**
     * Unique module id (module id + container)
     * @var  string
     */
    protected $_uid = '';

    /**
     * Module properties structure.
     * NOTE:
     * Only options defined here will be accepted within passed $options to the module constructor!
     * @var  array
     */
    protected $_properties = array(
        'debug' => false,
        'name' => 'cntnd_dynlist',
        'idmod' => 0,
        'container' => 0,

        'db' => '',
        'cfg' => '',
        'client' => 0,
        'lang' => 0
    );

    /**
     * Module translations
     * @var  array
     */
    protected $_i18n = array();

    /**
     * Constructor, sets some properties
     * @param  array  $options  Options array
     * @param  array  $translations  Assoziative translations list
     */
    public function __construct(array $options, array $translations = array()) {

        foreach ($options as $k => $v) {
            $this->$k = $v;
        }

        $this->_validate();

        $this->_i18n = $translations;
        $this->_uid = $this->idmod . '_' . $this->container;
    }

    // TODO rename/rewrite function and set public/private
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
        // TODO DB
        $this->db = new DB_Contenido;

        // Initialisierung des Templates
        $this->tpl = new Template();

        // prüfen ob liste bereits vorhanden oder nicht

        $this->db->query("SELECT idlist FROM cntnd_dynlist WHERE listname='".$this->index."' AND idart = ".$this->idart." AND idlang = ".$this->lang);
        if (!$this->db->next_record()){
            $this->db->query("INSERT INTO cntnd_dynlist (listname, idart, idlang) VALUES ('".$this->index."',".$this->idart.",".$this->lang.")");
        }

        // CMS_VALUE als Member aufnehmen
        $this->serialization();

        // alle arrays aufnehmen
        $this->medien=array();
        $this->images=array();
        $this->folders=array();

        // Dateien aus dem Dateisystem lesen
        $this->db->query("SELECT * FROM ".$cfg['tab']['upl']." WHERE idclient='$client' AND (dirname LIKE 'pdf%' OR dirname LIKE 'excel%' OR dirname LIKE 'word%') ORDER BY dirname ASC, filename ASC");
        while ($this->db->next_record()) {
            $this->medien[$this->db->f('idupl')] = array ('idupl' => $this->db->f('idupl'), 'filename' => $this->db->f('dirname').$this->db->f('filename'), 'dirname' => $this->db->f('dirname'));
        }

        // Bilder und Ordner mit Bildern aus dem Dateisystem lesen
        $this->db->query("SELECT * FROM ".$cfg['tab']['upl']." WHERE idclient='$client' AND filetype IN ('jpeg','jpg','gif','png') ORDER BY dirname ASC, filename ASC");
        while ($this->db->next_record()) {
            // Bilder
            $this->images[$this->db->f('idupl')] = array ('idupl' => $this->db->f('idupl'), 'filename' => $this->db->f('dirname').$this->db->f('filename'), 'dirname' => $this->db->f('dirname'));

            // Ordner
            if ($prev_dir!=$this->db->f('dirname')){
                $this->folders[$this->db->f('idupl')] = array ('idupl' => $this->db->f('idupl'), 'dirname' => $this->db->f('dirname'));
            }
            $prev_dir = $this->db->f('dirname');
        }
    }

    function serialization() {
        global $cfg;

        $this->db->query("SELECT serializeddata FROM cntnd_dynlist WHERE listname='".$this->index."' AND idart = ".$this->idart." AND idlang = ".$this->lang);
        while ($this->db->next_record()) {
            if (is_string($this->db->f('serializeddata'))){
                $this->cmsValue = unserialize($this->db->f('serializeddata'));
            }
        }

        if ($_POST[$this->index.'_action']=="delete") {
            unset($this->cmsValue[$_POST['DYNLIST_delete']]);

            $serializedData = mysql_real_escape_string(serialize($this->cmsValue));
            $this->db->query("UPDATE cntnd_dynlist SET serializeddata = '".$serializedData."' WHERE listname='".$this->index."' AND idart = ".$this->idart." and idlang = ".$this->lang);
        }
        else if ($_POST[$this->index.'_action']=="swap") {
            $this->cmsValue=$this->swap_array_elements($this->cmsValue ,$_POST['DYNLIST_swap_id1'], $_POST['DYNLIST_swap_id2']);

            $serializedData = mysql_real_escape_string(serialize($this->cmsValue));
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
                    $serializedData = mysql_real_escape_string(serialize($this->cmsValue));
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
            case 'gallery':
            case 'gallery2':
                $genField .= '<tr><td>'.$field['label'].':</td><td><select '.$first_id.' name="'.$this->index.'['.$id.']['.$field['name'].'][value]">';
                $genField .= "<option value='0'>-- kein --</option>\n";

                foreach ($this->folders as $medium) {
                    ($value['value'] == $medium['idupl']) ? $sel = ' selected="selected" ' : $sel = '';
                    $genField .= '<option value="'.$medium['idupl'].'" '.$sel.'>'.$medium['dirname'].'</option>'."\n";
                }
                $genField .= '</select><input type="hidden" name="'.$this->index.'['.$id.']['.$field['name'].'][viewer]" value="'.$id.'" /></td></tr>';
                break;
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
            $img_icon   ='upload/pikto/pikto_'.$icon.'.png';


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
            while ($this->db->next_record()) {
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
                $this->doGallery($this->tplName($field['name']),$value);
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

    /**
     * Magic getter, see PHP doc...
     */
    public function __get($name) {
        return (isset($this->_properties[$name])) ? $this->_properties[$name] : null;
    }

    /**
     * Magic setter, see PHP doc...
     */
    public function __set($name, $value) {
        if (isset($this->_properties[$name])) {
            $this->_properties[$name] = $value;
        }
    }

    /**
     * Magic method, see PHP doc...
     */
    public function __isset($name) {
        return (isset($this->_properties[$name]));
    }

    /**
     * Magic method, see PHP doc...
     */
    public function __unset($name) {
        if (isset($this->_properties[$name])) {
            unset($this->_properties[$name]);
        }
    }

    /**
     * Validates module configuration/data
     */
    protected function _validate() {
        // debug mode
        $this->debug = (bool) $this->debug;

        $this->name = (string) $this->name;
        $this->idmod = (int) $this->idmod;
        $this->container = (int) $this->container;
        $this->client = (int) $this->client;
        $this->lang = (int) $this->lang;
    }

    /**
     * Returns the checked attribute sub string usable for checkboxes.
     * @param string $name Configuration item name
     * @return string
     */
    public function getCheckedAttribute($name) {
        if (isset($this->$name) && '' !== $this->$name) {
            return ' checked="checked"';
        } else {
            return '';
        }
    }

    /**
     * Returns the id attribute value by concatenating passed name with the module uid.
     * @param string $name
     * @return string
     */
    public function getIdValue($name) {
        return $name . '_' . $this->getUid();
    }

    /**
     * Returns the module uid (module id + container).
     * @return string
     */
    public function getUid() {
        return $this->_uid;
    }

    /**
     * gets start article id
     * @return  int
     */
    protected function _startIdArtLang()
    {

        // get idcat, idcatart, idart and lastmodified from the database
        $sql = "SELECT startidartlang FROM ".$this->cfg["tab"]["cat_lang"]." WHERE idcat = ".$this->cmsCatID;
        $this->_printInfo("SQL start article $sql");

        $this->db->query($sql);
        $startIdArtLang=false;
        if ($this->db->nextRecord()) {
            $startIdArtLang = $this->db->f('startidartlang');
        }
        $this->db->free();

        return $startIdArtLang;
    }

    /**
     * Collects articles
     * @return  bool
     */
    protected function _collectArticles($startIdArtLang){
        $this->articleIsAvailable = false;

        // get idcat, idcatart, idart and lastmodified from the database
        $sql = "SELECT ca.idart, ca.idcat, ca.idcatart, al.artsort "
            . "FROM " . $this->cfg["tab"]["cat_art"] . " AS ca, " . $this->cfg["tab"]["art_lang"] . " AS al "
            . "WHERE ca.idart = al.idart AND al.online = 1 AND al.idartlang != ".$startIdArtLang." AND al.idlang = " . $this->lang . " AND ";
        if ($this->cmsArtID == 0) {
            // if only idcat specified, get latest article of category
            $sql .= "ca.idcat = " . $this->cmsCatID . " ORDER BY al.artsort ASC";
        } else {
            // article specified
            $sql .= "al.idart = " . $this->cmsArtID;
        }
        $this->_printInfo("SQL to collect article $sql");

        $this->db->query($sql);
        $arrArticles=null;
        while ($this->db->nextRecord()) {
            if (!$this->articleIsAvailable) {
                $this->articleIsAvailable = true;
                $this->incIdcatart = $this->db->f('idcatart');
                $this->incIdcat = $this->db->f('idcat');
                $this->incIdart = $this->db->f('idart');
            }

            $art = array();
            $art['incIdart'] = $this->db->f('idart');
            $arrArticles[] = $art;
        }
        $this->_printInfo("array: " . print_r($arrArticles, true));
        $this->db->free();

        if (is_array($arrArticles)){
            $this->setArrArticles($arrArticles);
            return true;
        }

        return false;
    }

    /**
     * Checks if article exists and is online
     * @return  bool
     */
    protected function _checkArticle() {
        $this->articleIsAvailable = false;

        // get idcat, idcatart, idart and lastmodified from the database
        $sql = "SELECT ca.idart, ca.idcat, ca.idcatart, al.artsort "
            . "FROM " . $this->cfg["tab"]["cat_art"] . " AS ca, " . $this->cfg["tab"]["art_lang"] . " AS al "
            . "WHERE ca.idart = al.idart AND al.online = 1 AND al.idlang = " . $this->lang . " AND ";
        if ($this->cmsArtID == 0) {
            // if only idcat specified, get latest article of category
            $sql .= "ca.idcat = " . $this->cmsCatID . " ORDER BY al.artsort ASC";
        } else {
            // article specified
            $sql .= "al.idart = " . $this->cmsArtID;
        }
        $this->_printInfo("SQL to check article $sql");

        $this->db->query($sql);
        if ($this->db->nextRecord()) {
            $this->articleIsAvailable = true;
            $this->incIdcatart = $this->db->f('idcatart');
            $this->incIdcat = $this->db->f('idcat');
            $this->incIdart = $this->db->f('idart');
        }
        $this->db->free();

        return $this->articleIsAvailable;
    }

    /**
     * Checks if category exists, is online and public
     * @return  bool
     */
    protected function _checkCategory() {
        // check if category is online or protected
        $oCatLang = new cApiCategoryLanguage();
        $oCatLang->loadByCategoryIdAndLanguageId($this->incIdcat, $this->lang);
        $this->_printInfo('$this->incIdcat: ' . print_r($this->incIdcat, true));
        $this->_printInfo('$this->lang: ' . print_r($this->lang, true));
        $this->_printInfo('$oCatLang->toArray(): ' . print_r($oCatLang->toArray(), true));
        $catIsPublic = (int) $oCatLang->get('public');
        $catIsVisible = (int) $oCatLang->get('visible');

        if ($catIsPublic && $catIsVisible) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Requests the article by using Snoopy
     * @return  bool
     */
    protected function _requestArticle($idart=false) {
        // Get article output
        $moduleHandler = new cModuleHandler($this->idmod);
        include_once($moduleHandler->getModulePath() . 'vendor/Snoopy.class.php');

        $sel_idart=$this->incIdart;
        if (is_numeric($idart)){
            $sel_idart=$idart;
        }

        $url = cUri::getInstance()->build(array(
            'idart' => $sel_idart, 'lang' => $this->lang
        ), true);

        $snoopy = new Snoopy();
        $snoopy->fetch($url);
        $code = trim($snoopy->results);
        //$this->_code = trim($snoopy->results);

        // Extract content from article code output
        //if (!empty($this->_code)) {
        if (!empty($code)) {
            $cmsStartMarker = htmlspecialchars_decode($this->cmsStartMarker);
            $cmsEndMarker = htmlspecialchars_decode($this->cmsEndMarker);

            //$startPos = strpos($this->_code, $cmsStartMarker);
            //$endPos   = strpos($this->_code, $cmsEndMarker);
            $startPos = strpos($code, $cmsStartMarker);
            $endPos   = strpos($code, $cmsEndMarker);

            if ($startPos !== false || $endPos !== false) {
                $diffLen = $endPos - $startPos + strlen($cmsEndMarker);
                //$this->_code = substr($this->_code, $startPos, $diffLen);
                $this->_code .= substr($code, $startPos, $diffLen);
                return true;
            } else {
                $msg = "ERROR in module " . $this->name . "<pre>Couldn't detect marker {$this->cmsStartMarker} and/or {$this->cmsEndMarker}!\n"
                    . "idcat {$this->cmsCatID}, idart {$sel_idart}, idlang {$this->lang}, idclient {$this->client}";
                $this->_printInfo($msg);
                return false;
            }
        } else {
            $msg = "ERROR in module " . $this->name . "<pre>Can't get article to include!\n"
                . "idcat {$this->cmsCatID}, idart {$sel_idart}, idlang {$this->lang}, idclient {$this->client}\n";
            $this->_printInfo($msg);
            return false;
        }
    }

    /**
     * Simple debugger, print preformatted text, if debugging is enabled
     * @param  $msg
     */
    protected function _printInfo($msg) {
        if ($this->debug) {
            echo "<pre>{$msg}</pre>";
        }
    }

    /**
     * @return array
     */
    public function getArrArticles()
    {
        return $this->_arrArticles;
    }

    /**
     * @param array $arrArticles
     */
    public function setArrArticles($arrArticles)
    {
        $this->_arrArticles = $arrArticles;
    }

    /** MISC FUNCTIONS */
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


}
