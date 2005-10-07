<?php

/**
 * Controls the general user functionality of the module
 *
 * @author Matthew McNaney <mcnaney at gmail dot com>
 * @version $Id$
 */

PHPWS_Core::initModClass('webpage', 'Volume.php');

class Webpage_User {
    function main($command=NULL)
    {
        if (empty($command)) {
            if (isset($_REQUEST['wp_user'])) {
                $command = $_REQUEST['wp_user'];
            } else {
                PHPWS_Core::errorPage(404);
                exit();
            }
        }

        switch ($command) {
        case 'view':
            if (!isset($_REQUEST['id'])) {
                PHPWS_Core::errorPage(404);
                exit();
            }

            $volume = & new Webpage_Volume($_REQUEST['id']);
            @$page = $_REQUEST['page'];
            Layout::add($volume->view($page));
            PHPWS_Core::initModClass('menu', 'Menu.php');
            break;

        default:
            echo $command;
            break;
        }

    }
}


?>