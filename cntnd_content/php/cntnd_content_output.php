<?php
// Includes
cInclude('module', 'includes/class.module.cntnd.php');

// Module configuration
$aModuleConfiguration = array(
    'debug' => false,
    'name' => 'cntnd',
    'idmod' => $cCurrentModule,
    'container' => $cCurrentContainer,

    // Selected category id
    'cmsCatID' => (int) "CMS_VALUE[1]",

    // Selected article id
    'cmsArtID' => (int) "CMS_VALUE[2]",

    // Start and end marker
    'cmsStartMarker' => "CMS_VALUE[3]",
    'cmsEndMarker' => "CMS_VALUE[4]",
    'defaultStartMarker' => '<!--start:content-->',
    'defaultEndMarker' => '<!--end:content-->',

    'db' => cRegistry::getDb(),
    'cfg' => cRegistry::getConfig(),
    'client' => cRegistry::getClientId(),
    'lang' => cRegistry::getLanguageId(),
);
//##echo "<pre>" . print_r($aModuleConfiguration, true) . "</pre>";

// Create mpArticleInclude module instance
$oModule = new ModuleCntnd($aModuleConfiguration);

// Retrieve article
if (true === $oModule->includeArticle()) {
    echo $oModule->getCode();
} else {
    // Do your error handling here...
}

// Cleanup
unset($oModule);

?>