/**
 * CONTENIDO JavaScript registerTips module
 *
 * @version    SVN Revision $Rev: 5937 $
 * @author     ???
 * @copyright  four for business AG <www.4fb.de>
 * @license    http://www.contenido.org/license/LIZENZ.txt
 * @link       http://www.4fb.de
 * @link       http://www.contenido.org
 */

(function(Con, $) {
    $(function() {
        $('.tooltip').tipsy({
            gravity : $.fn.tipsy.autoWE,
            html : true
        });
        $('.tooltip-north').tipsy({
            gravity : 'n',
            html : true
        });
    });
})(Con, Con.$);

