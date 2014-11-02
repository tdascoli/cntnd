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

    /**
     * Main function to retrieve the article, runs some checks, like if article and category is
     * available and finally it requests the article.
     * @return  bool  Success state
     */
    public function includeArticle() {
        $this->_printInfo("idcat {$this->cmsCatID}, idart {$this->cmsArtID}");

        if ($this->cmsCatID < 0) {
            $this->_printInfo("No idcat!");
            return false;
        }

        $startIdArtLang = $this->_startIdArtLang();

        if (false === $this->_collectArticles($startIdArtLang)) {
            $this->_printInfo("There are none Articles to collect!");
            return false;
        }

        if (false === $this->_checkCategory()) {
            $this->_printInfo("Category is not public or visible!");
            return false;
        }

        $this->_code='';
        foreach ($this->_arrArticles as $article) {
            if (false === $this->_requestArticle($article['incIdart'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the extracted code (HTML output) from article
     * @return string
     */
    public function getCode() {
        return $this->_code;
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
}
