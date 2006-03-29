<?php

  /**
   * Uninstall file for webpage
   * 
   * @author Matthew McNaney <mcnaney at gmail dot com>
   * @version $Id$
   */

function webpage_uninstall(&$content)
{
    PHPWS_DB::dropTable('webpage_volume');
    PHPWS_DB::dropTable('webpage_page');
    $content[] = _('Web Page tables removed.');
    return TRUE;
}

?>