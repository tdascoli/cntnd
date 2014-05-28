<?php
/**
 * Contains class to create a class map file.
 *
 * @category Development
 * @package mpAutoloaderClassMap
 * @author Murat Purc <murat@purc.de>
 * @copyright Copyright (c) 2009-2010 Murat Purc (http://www.purc.de)
 * @license http://www.gnu.org/licenses/gpl-2.0.html - GNU General Public
 *          License, version 2
 * @version $Id: mpClassMapFileCreator.php 6113 2013-12-23 20:55:32Z xmurrix $
 */

/**
 * Class to create a PHP file which contains a assoziative PHP array.
 *
 * Generated file will contain a PHP array as following:
 * <code>
 * return array(
 * '{classname}' => '{path_to_classfile}',
 * '{classname2}' => '{path_to_classfile2}',
 * );
 * </code>
 *
 * @category Development
 * @package mpAutoloaderClassMap
 * @author Murat Purc <murat@purc.de>
 */
class mpClassMapFileCreator {

    /**
     * Class map file template
     *
     * @var string
     */
    protected $_template = '';

    /**
     * Template replacements
     *
     * @var stdClass
     */
    protected $_data = '';

    /**
     * Sets template and template replacements
     */
    public function __construct() {
        $this->_template = trim('
<?php
/**
 {DESCRIPTION}
 *
 * @package    {PACKAGE}
 * @subpackage {SUBPACKAGE}
 * @version    {VERSION}
 * @author     {AUTHOR}
 * @copyright  {COPYRIGHT}
 * @license    {LICENSE}
 */

{CONTENT}
');
        $this->_data = new stdClass();
        $this->_data->content = '';
        $this->_data->description = trim('
 * Autoloader classmap file. Contains all available classes/interfaces and
 * related class files.
 *
 * NOTES:
 * - Don\'t edit this file manually!
 * - It was generated by ' . __CLASS__ . '
 * - Use ' . __CLASS__ . ' again, if you want to regenerate this file
 *');

        $this->_data->package = __CLASS__;
        $this->_data->subpackage = 'Classmap';
        $this->_data->version = '0.1';
        $this->_data->author = 'System';
        $this->_data->copyright = 'Copyright (c) 2009-2010 Murat Purc (http://www.purc.de)';
        $this->_data->license = 'http://www.gnu.org/licenses/gpl-2.0.html - GNU General Public License, version 2';
    }

    /**
     * Creates classmap file with passed data list
     *
     * @param array $data Assoziative list which contains class type tokens and
     *        the related path to the class file.
     * @param string $file Destination class map file
     * @return bool
     */
    public function create(array $data, $file) {
        $this->_createClassMap($data);

        return (bool) file_put_contents($file, $this->_renderTemplate());
    }

    /**
     * Fills template replacement variable with generated assoziative PHP array
     *
     * @var array $data Assoziative list with class type tokens and files
     */
    protected function _createClassMap(array $data) {
        $classMapTpl = "\r\nreturn array(\r\n%s\r\n);\r\n";
        $classMapContent = '';
        foreach ($data as $classToken => $path) {
            $classMapContent .= sprintf("    '%s' => '%s',\r\n", addslashes($classToken), addslashes($path));
        }
        $classMapContent = substr($classMapContent, 0, -3);

        $this->_data->content .= sprintf($classMapTpl, $classMapContent);
    }

    /**
     * Replaces all wildcards in template with related template variables.
     *
     * @return string Replaced template
     */
    protected function _renderTemplate() {
        $template = $this->_template;
        foreach ($this->_data as $name => $value) {
            $template = str_replace('{' . strtoupper($name) . '}', $value, $template);
        }

        return $template;
    }

}
